<?php
/**
 * OPORTUNIDADES (Curva ABC) — encontra os GRANDES itens do orçamento de uma obra que o radar
 * AINDA NÃO cobre (nenhum item de aquisição toca aquelas folhas) e deixa criar/agrupar em item novo.
 * Objetivo do usuário: capturar toda a curva A e a maior parte da B (70-80% do orçamento).
 *
 * GET ?obra=X
 *   -> { resumo:{total, coberto, coberto_pct, gap, gap_pct, indiretos, indiretos_pct},
 *        gaps:[{descricao, valor, valor_pct, n_linhas, grupos:[...], curva}] }   (curva A/B/C por % acumulado do GAP)
 * POST (ADMIN) {acao:'criar', me, obra, nome, grupo, curva, descricoes:[...]}
 *   -> cria um SERVIÇO (catálogo) e VINCULA a verba (analítico) às folhas DESCOBERTAS dessas descrições
 *      na obra; tudo entra como SUGERIDO (auto_flags.verba). termos_cronograma sai do nome (chance de
 *      o marco automático já achar a data). -> { ok, servico_id, linhas, valor }
 */
header('Content-Type: application/json; charset=utf-8');
set_time_limit(180);
require_once __DIR__ . '/../includes/db.php';

/** conjunto de ids de folha COBERTOS por qualquer item do radar da obra (analítico + composição).
 *  Composição: itens curados à mão guardam só o `cid` (locais=None), então a cobertura é resolvida
 *  por cid → DESCRIÇÃO da composição → todas as folhas com essa descrição (mesmo critério da
 *  "cobertura real" do radar). Sem isso, alvenaria/gesso/etc. (composição) apareciam como falso-gap. */
function opp_cobertas($pdo, $obraId) {
    $cov = []; $cids = [];
    $q = $pdo->prepare("SELECT orcamento_refs, composicao_sel FROM radar_item WHERE obra_id=?");
    $q->execute([$obraId]);
    foreach ($q->fetchAll() as $r) {
        foreach ((json_decode($r['orcamento_refs'] ?? '[]', true) ?: []) as $L) $cov[(int)$L] = 1;   // analítico
        foreach ((json_decode($r['composicao_sel'] ?? '[]', true) ?: []) as $s) {
            $loc = is_array($s['locais'] ?? null) ? $s['locais'] : [];
            if ($loc) { foreach ($loc as $L) $cov[(int)$L] = 1; }        // tem locais → PRECISO (mostra o parcial certo)
            elseif (!empty($s['cid'])) $cids[(int)$s['cid']] = 1;        // SEM locais (curadoria antiga) → fallback por descrição da composição
        }
    }
    if ($cids) {   // cid → descrição da composição → folhas com essa descrição na obra
        $descs = [];
        foreach ($pdo->query("SELECT descricao FROM composicao WHERE id IN (" . implode(',', array_map('intval', array_keys($cids))) . ")")->fetchAll() as $c)
            if ($c['descricao'] !== null && $c['descricao'] !== '') $descs[$c['descricao']] = 1;
        if ($descs) {
            $ph = implode(',', array_fill(0, count($descs), '?'));
            $q2 = $pdo->prepare("SELECT id FROM orcamento_linha WHERE obra_id=? AND folha=1 AND descricao IN ($ph)");
            $q2->execute(array_merge([$obraId], array_keys($descs)));
            foreach ($q2->fetchAll() as $l) $cov[(int)$l['id']] = 1;
        }
    }
    return $cov;
}
function opp_topo($path) { $p = explode('›', (string)$path); return trim($p[0]); }

try {
    $pdo = db();
    $obraId = max(1, (int)($_GET['obra'] ?? ($_POST['obra'] ?? 0)));

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        if (!$obraId) throw new Exception('obra obrigatória');
        $cov = opp_cobertas($pdo, $obraId);
        $q = $pdo->prepare("SELECT id, descricao, valor, path_str FROM orcamento_linha WHERE obra_id=? AND folha=1");
        $q->execute([$obraId]);
        $total = 0.0; $coberto = 0.0; $indiretos = 0.0;
        $agg = [];   // descricao => [valor, n, grupos{}]
        foreach ($q->fetchAll() as $l) {
            $v = (float)$l['valor']; $total += $v;
            if (isset($cov[(int)$l['id']])) { $coberto += $v; continue; }
            $topo = opp_topo($l['path_str']);
            if (stripos($topo, 'INDIRETO') !== false) { $indiretos += $v; continue; }
            $d = (string)$l['descricao'];
            if (!isset($agg[$d])) $agg[$d] = ['descricao'=>$d, 'valor'=>0.0, 'n_linhas'=>0, 'grupos'=>[]];
            $agg[$d]['valor'] += $v; $agg[$d]['n_linhas']++; $agg[$d]['grupos'][$topo] = 1;
        }
        $gaps = array_values($agg);
        usort($gaps, function($a, $b){ return $b['valor'] <=> $a['valor']; });
        $gapTotal = array_sum(array_map(function($g){ return $g['valor']; }, $gaps));
        // curva A/B/C por % ACUMULADO do gap (A até 80%, B até 95%, C o resto)
        $cum = 0.0;
        foreach ($gaps as &$g) {
            $g['grupos'] = array_keys($g['grupos']);
            $g['valor_pct'] = $total > 0 ? round(100 * $g['valor'] / $total, 2) : 0;
            $cum += $g['valor'];
            $p = $gapTotal > 0 ? $cum / $gapTotal : 1;
            $g['curva'] = $p <= 0.80 ? 'A' : ($p <= 0.95 ? 'B' : 'C');
            $g['valor'] = round($g['valor']);
        }
        unset($g);
        // grupos JÁ EXISTENTES + lista de ITENS do catálogo (p/ os pickers do "criar novo" / "vincular a existente")
        $grupos = [];
        foreach ($pdo->query("SELECT grupo FROM servico WHERE grupo IS NOT NULL AND grupo<>'' GROUP BY grupo ORDER BY MIN(grupo_ordem)")->fetchAll() as $gr)
            $grupos[] = $gr['grupo'];
        $itens = $pdo->query("SELECT id, nome, grupo FROM servico ORDER BY nome")->fetchAll();
        echo json_encode(['resumo'=>[
            'total'=>round($total), 'coberto'=>round($coberto), 'coberto_pct'=>$total?round(100*$coberto/$total,1):0,
            'gap'=>round($gapTotal), 'gap_pct'=>$total?round(100*$gapTotal/$total,1):0,
            'indiretos'=>round($indiretos), 'indiretos_pct'=>$total?round(100*$indiretos/$total,1):0,
            'n_gaps'=>count($gaps)],
            'grupos'=>$grupos, 'itens'=>$itens, 'gaps'=>$gaps], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ---- POST: criar item de aquisição (bundle) a partir de descrições descobertas ----
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $perms = user_perms($pdo, $in['me'] ?? null);
    if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Apenas administradores mexem no catálogo.']); exit; }
    $acao = $in['acao'] ?? '';
    $obraId = max(1, (int)($in['obra'] ?? 0));
    if (!$obraId) throw new Exception('obra obrigatória');
    $descs = array_values(array_filter(array_map(function($x){ return trim((string)$x); }, (array)($in['descricoes'] ?? []))));
    if (!$descs) throw new Exception('selecione ao menos uma descrição do orçamento');

    // folhas DESCOBERTAS dessas descrições, na obra (não reivindica linha que outro item já pegou)
    $cov = opp_cobertas($pdo, $obraId);
    $ph = implode(',', array_fill(0, count($descs), '?'));
    $q = $pdo->prepare("SELECT id, valor FROM orcamento_linha WHERE obra_id=? AND folha=1 AND descricao IN ($ph)");
    $q->execute(array_merge([$obraId], $descs));
    $refs = []; $valor = 0.0;
    foreach ($q->fetchAll() as $l) { if (isset($cov[(int)$l['id']])) continue; $refs[] = (int)$l['id']; $valor += (float)$l['valor']; }
    if (!$refs) throw new Exception('nenhuma folha descoberta para essas descrições (já foram cobertas?)');

    if ($acao === 'criar') {
        $nome  = trim((string)($in['nome'] ?? ''));
        $grupo = trim((string)($in['grupo'] ?? ''));
        $curva = strtoupper(trim((string)($in['curva'] ?? ''))) ?: 'A';
        if ($nome === '')  throw new Exception('nome obrigatório');
        if ($grupo === '') throw new Exception('grupo obrigatório');
        $pdo->beginTransaction();
        // termos do cronograma a partir do nome → dá chance do marco automático achar a data (prioridade: cronograma)
        $termos = implode('; ', array_values(array_filter(array_map('trim', preg_split('/[\/,;+()]| e | de | para | com /i', $nome)),
                          function($t){ return mb_strlen($t) >= 4; })));
        $sid = criar_item($pdo, $nome, $grupo, '', $curva);
        if ($termos !== '') $pdo->prepare("UPDATE servico SET termos_orcamento=?, termos_cronograma=? WHERE id=?")->execute([$termos, $termos, $sid]);
        $pdo->prepare("UPDATE radar_item SET orcamento_refs=?, verba_metodo='analitico', verba_override=?, verba_curada=0,
                       auto_flags=?, updated_at=? WHERE obra_id=? AND servico_id=?")
            ->execute([json_encode(array_values($refs)), $valor, json_encode(['verba'=>1]), date('c'), $obraId, $sid]);
        log_historico($pdo, $obraId, $sid, $nome, $in['me'] ?? null, $perms['nome'] ?? null,
                      'Item criado (Curva ABC)', '', count($refs) . ' linhas · R$ ' . number_format($valor, 0, ',', '.'));
        $pdo->commit();
        echo json_encode(['ok'=>true, 'servico_id'=>$sid, 'nome'=>$nome, 'linhas'=>count($refs), 'valor'=>round($valor)], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'vincular') {   // vincula as folhas descobertas a um item que JÁ EXISTE (verba analítica)
        $sid = (int)($in['servico_id'] ?? 0);
        if (!$sid) throw new Exception('escolha o item existente');
        $ri = $pdo->prepare("SELECT r.verba_metodo, r.orcamento_refs, r.verba_curada, r.auto_flags, s.nome
                             FROM radar_item r JOIN servico s ON s.id = r.servico_id WHERE r.obra_id=? AND r.servico_id=?");
        $ri->execute([$obraId, $sid]); $ri = $ri->fetch();
        if (!$ri) throw new Exception('esse item não existe nesta obra');
        $met = $ri['verba_metodo'];
        if ($met === 'composicao' || $met === 'manual')
            throw new Exception('o item “' . $ri['nome'] . '” já usa verba por ' . $met . ' — nesse caso vincule pela curadoria do item (aba Orçamento do modal).');
        // acrescenta as folhas descobertas aos refs que o item já tem (analítico ou vazio)
        $existing = array_map('intval', json_decode($ri['orcamento_refs'] ?? '[]', true) ?: []);
        $allRefs = array_values(array_unique(array_merge($existing, $refs)));
        $inR = implode(',', array_map('intval', $allRefs));
        $vtot = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM orcamento_linha WHERE id IN ($inR)")->fetchColumn();
        $af = json_decode($ri['auto_flags'] ?? '{}', true) ?: [];
        if (empty($ri['verba_curada'])) $af['verba'] = 1;   // sugerido (só se ainda não estava curado)
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE radar_item SET orcamento_refs=?, verba_metodo='analitico', verba_override=?, auto_flags=?, updated_at=? WHERE obra_id=? AND servico_id=?")
            ->execute([json_encode($allRefs), $vtot, ($af ? json_encode($af) : null), date('c'), $obraId, $sid]);
        log_historico($pdo, $obraId, $sid, $ri['nome'], $in['me'] ?? null, $perms['nome'] ?? null,
                      'Vínculo de verba (Curva ABC)', '', '+' . count($refs) . ' linhas · R$ ' . number_format($valor, 0, ',', '.'));
        $pdo->commit();
        echo json_encode(['ok'=>true, 'servico_id'=>$sid, 'nome'=>$ri['nome'], 'linhas'=>count($refs), 'valor'=>round($valor), 'vinculado'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    throw new Exception('acao inválida');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
