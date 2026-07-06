<?php
/**
 * IMPORTA uma obra nova (orçamento + composições + células do radar) a partir dos seeds
 * data/seed/orcamento_<slug>.json e composicao_<slug>.json — gerados por tools/seed_orcamento.py
 * e tools/seed_composicao.py a partir do Excel do orçamento.
 *
 * IDs com OFFSET por obra (obra_id × 100000): linhas e composições ficam com id único ENTRE obras,
 * então todo lookup por id continua válido sem obra_id (os catálogos casados por descrição JÁ são
 * escopados por obra nas actions).
 *
 * POST (ADMIN) {acao:'importar', me, obra_id, slug, nome, codinome, local, cronograma_id,
 *               metodo_construtivo, orcamento_total}
 * POST (ADMIN) {acao:'wipe', me, obra_id}   -> desfaz a importação (obra_id > 1 apenas)
 * GET  ?status=1                            -> contagens por obra (read-only, sem auth)
 */
header('Content-Type: application/json; charset=utf-8');
set_time_limit(300);
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();

    if (isset($_GET['status'])) {
        $out = [];
        foreach ($pdo->query("SELECT id, nome, codinome, cronograma_id, metodo_construtivo FROM obra ORDER BY id")->fetchAll() as $o) {
            $oid = (int)$o['id'];
            $c = function($sql) use ($pdo, $oid) { $s = $pdo->prepare($sql); $s->execute([$oid]); return (int)$s->fetchColumn(); };
            $out[] = ['obra'=>$o,
                'orcamento_linhas' => $c("SELECT COUNT(*) FROM orcamento_linha WHERE obra_id=?"),
                'folhas'           => $c("SELECT COUNT(*) FROM orcamento_linha WHERE obra_id=? AND folha=1"),
                'composicoes'      => $c("SELECT COUNT(*) FROM composicao WHERE obra_id=?"),
                'radar_itens'      => $c("SELECT COUNT(*) FROM radar_item WHERE obra_id=?"),
                'receitas'         => (int)$pdo->query("SELECT COUNT(*) FROM receita")->fetchColumn()];
        }
        echo json_encode(['obras'=>$out], JSON_UNESCAPED_UNICODE); exit;
    }

    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $perms = user_perms($pdo, $in['me'] ?? null);
    if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Apenas administradores.']); exit; }

    $acao   = $in['acao'] ?? '';

    // Reclassifica insumos de PESSOAL de guarda/portaria material→mo em obras JÁ importadas
    // (seeds futuros já saem certos pelo classificador). Não mexe no valor da verba, só no rótulo
    // material/MO do split. {acao:'reclassificar_mo', me, obra?(0=todas), dry?}
    if ($acao === 'reclassificar_mo') {
        $alvo = (int)($in['obra'] ?? 0);   // 0 = todas as obras
        $dry  = !empty($in['dry']);
        // prefiltro amplo por LIKE (termos ascii presentes no texto); confirmação precisa no PHP via sup_mo_guarda
        $like = "(ci.descricao LIKE '%vigilante%' OR ci.descricao LIKE '%porteiro%' OR ci.descricao LIKE '%patrimonial%' OR ci.descricao LIKE '%vigia%')";
        $base = "SELECT ci.composicao_id, ci.descricao, ci.tipo, c.obra_id, c.descricao AS comp
                 FROM composicao_insumo ci JOIN composicao c ON c.id=ci.composicao_id
                 WHERE ci.tipo<>'mo' AND $like";
        if ($alvo > 0) { $st = $pdo->prepare($base . " AND c.obra_id=?"); $st->execute([$alvo]); }
        else           { $st = $pdo->query($base); }
        $hit = [];
        foreach ($st->fetchAll() as $r) if (sup_mo_guarda($r['descricao'])) $hit[] = $r;
        if (!$dry && $hit) {
            $pdo->beginTransaction();
            $up = $pdo->prepare("UPDATE composicao_insumo SET tipo='mo' WHERE composicao_id=? AND descricao=? AND tipo<>'mo'");
            foreach ($hit as $r) $up->execute([(int)$r['composicao_id'], $r['descricao']]);
            $pdo->commit();
        }
        echo json_encode(['ok'=>true, 'dry'=>$dry, 'obra'=>$alvo ?: 'todas', 'reclassificados'=>count($hit),
            'itens'=>array_map(function($r){ return ['obra'=>(int)$r['obra_id'], 'composicao'=>$r['comp'],
                     'insumo'=>$r['descricao'], 'de'=>$r['tipo'], 'para'=>'mo']; }, $hit)],
            JSON_UNESCAPED_UNICODE); exit;
    }

    $obraId = (int)($in['obra_id'] ?? 0);
    if ($obraId < 2) throw new Exception('obra_id deve ser >= 2 (a 1 é a Trinity, intocável por aqui)');
    $OFF = $obraId * 100000;   // offset de IDs da obra

    if ($acao === 'wipe') {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM composicao_insumo WHERE composicao_id >= ? AND composicao_id < ?")->execute([$OFF, $OFF + 100000]);
        $pdo->prepare("DELETE FROM composicao WHERE obra_id=?")->execute([$obraId]);
        $pdo->prepare("DELETE FROM orcamento_linha WHERE obra_id=?")->execute([$obraId]);
        $pdo->prepare("DELETE FROM radar_item WHERE obra_id=?")->execute([$obraId]);
        $pdo->prepare("DELETE FROM obra WHERE id=?")->execute([$obraId]);
        $pdo->commit();
        echo json_encode(['ok'=>true, 'wiped'=>$obraId]); exit;
    }

    if ($acao !== 'importar') throw new Exception('acao inválida');
    $slug = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($in['slug'] ?? '')));
    if ($slug === '') throw new Exception('slug obrigatório (ex.: firenze)');

    $orc  = json_decode(@file_get_contents(SEED_DIR . "/orcamento_$slug.json"), true);
    $comp = json_decode(@file_get_contents(SEED_DIR . "/composicao_$slug.json"), true);
    if (empty($orc['linhas']))       throw new Exception("seed orcamento_$slug.json ausente/vazio");
    if (empty($comp['composicoes'])) throw new Exception("seed composicao_$slug.json ausente/vazio");

    // idempotência: recusa se a obra já tem dados (use wipe antes pra re-importar)
    $chk = $pdo->prepare("SELECT COUNT(*) FROM orcamento_linha WHERE obra_id=?"); $chk->execute([$obraId]);
    if ((int)$chk->fetchColumn() > 0) throw new Exception("obra $obraId já tem orçamento importado — use acao=wipe antes de re-importar");

    $totalLeaf = 0.0;
    foreach ($orc['linhas'] as $l) if (!empty($l['folha'])) $totalLeaf += (float)($l['valor'] ?? 0);

    $pdo->beginTransaction();

    // ---- obra ----
    $pdo->prepare("REPLACE INTO obra (id, nome, slug, codinome, `local`, cronograma_id, orcamento_total, cobertura_orcamento, metodo_construtivo)
                   VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$obraId, (string)($in['nome'] ?? ucfirst($slug)), $slug, (string)($in['codinome'] ?? ''),
                   (string)($in['local'] ?? ''), (string)($in['cronograma_id'] ?? ''),
                   (float)($in['orcamento_total'] ?? $totalLeaf), null,
                   (string)($in['metodo_construtivo'] ?? 'concreto armado convencional')]);

    // ---- orçamento (INSERT em lotes) ----
    $nL = 0;
    $cols = "(id,obra_id,codigo,parent,depth,nivel,descricao,path_str,unidade,qtde,valor,folha)";
    $batch = []; $vals = [];
    $flush = function() use (&$batch, &$vals, $pdo, $cols) {
        if (!$batch) return;
        $pdo->prepare("INSERT INTO orcamento_linha $cols VALUES " . implode(',', $batch))->execute($vals);
        $batch = []; $vals = [];
    };
    foreach ($orc['linhas'] as $l) {
        $batch[] = "(?,?,?,?,?,?,?,?,?,?,?,?)";
        array_push($vals, $OFF + (int)$l['id'], $obraId, $l['codigo'], $l['parent'], (int)$l['depth'], (int)$l['nivel'],
                   $l['descricao'], $l['path_str'], $l['unidade'] ?? '', $l['qtde'], $l['valor'], (int)$l['folha']);
        $nL++;
        if (count($batch) >= 200) $flush();
    }
    $flush();

    // ---- composições + insumos ----
    $nC = 0; $nI = 0;
    $insC = $pdo->prepare("INSERT INTO composicao (id, obra_id, descricao, unidade, qtde_total, rs_unit, rs_total) VALUES (?,?,?,?,?,?,?)");
    $insI = $pdo->prepare("INSERT INTO composicao_insumo (composicao_id, descricao, unidade, coef, rs_unit, rs_total, tipo) VALUES (?,?,?,?,?,?,?)");
    foreach ($comp['composicoes'] as $c) {
        $cid = $OFF + (int)$c['id'];
        $insC->execute([$cid, $obraId, $c['descricao'], $c['unidade'] ?? '', $c['qtde_total'], $c['rs_unit'], $c['rs_total']]);
        $nC++;
        foreach (($c['insumos'] ?? []) as $i) {
            $insI->execute([$cid, $i['descricao'], $i['unidade'] ?? '', $i['coef'], $i['rs_unit'], $i['rs_total'], $i['tipo'] ?? 'material']);
            $nI++;
        }
    }

    // ---- células do radar: 1 por serviço do catálogo (curadoria/auto-vínculo vêm depois) ----
    $nR = 0;
    // a obra nova HERDA o responsável padrão de cada serviço (regra padrão)
    $insR = $pdo->prepare("INSERT INTO radar_item (obra_id, servico_id, status, responsavel, updated_at) VALUES (?,?,?,?,?)");
    foreach ($pdo->query("SELECT id, responsavel_padrao FROM servico ORDER BY id")->fetchAll() as $s) {
        $rp = trim((string)($s['responsavel_padrao'] ?? ''));
        $insR->execute([$obraId, (int)$s['id'], 'Não Iniciado', $rp !== '' ? $rp : null, date('c')]);
        $nR++;
    }

    $pdo->commit();
    echo json_encode(['ok'=>true, 'obra_id'=>$obraId, 'slug'=>$slug,
                      'orcamento_linhas'=>$nL, 'total_folhas'=>round($totalLeaf, 2),
                      'composicoes'=>$nC, 'insumos'=>$nI, 'radar_itens'=>$nR], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
