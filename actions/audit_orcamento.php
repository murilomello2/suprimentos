<?php
/**
 * Auditoria de DUPLICAÇÃO de vínculo de ORÇAMENTO (verba) entre itens do radar.
 * Regra: a mesma linha do orçamento (orcamento_linha) NÃO pode entrar em 2+ itens,
 * senão a verba é contada em dobro e a cobertura fica inflada.
 * (Cronograma NÃO é auditado aqui — datas/marcos podem ser compartilhados sem problema.)
 *
 * READ-ONLY. Não altera nada. GET -> relatório JSON.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();
    $obra = $pdo->query("SELECT orcamento_total FROM obra WHERE id=1")->fetch();
    $total_obra = (float)($obra['orcamento_total'] ?? 0);
    $total_leaf = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM orcamento_linha WHERE folha=1")->fetchColumn();

    $rows = $pdo->query("SELECT s.ordem, s.nome, s.grupo, r.orcamento_refs
        FROM radar_item r JOIN servico s ON s.id = r.servico_id
        WHERE r.obra_id=1 AND r.orcamento_refs IS NOT NULL
              AND r.orcamento_refs <> '' AND r.orcamento_refs <> '[]'")->fetchAll();

    $uso = [];                 // id_linha => [ {ordem,nome,grupo} ]
    $itens_refs = 0;
    foreach ($rows as $r) {
        $refs = json_decode($r['orcamento_refs'], true);
        if (!is_array($refs) || !$refs) continue;
        $itens_refs++;
        foreach ($refs as $id) {
            $uso[(int)$id][] = ['ordem'=>(int)$r['ordem'], 'nome'=>$r['nome'], 'grupo'=>$r['grupo']];
        }
    }

    $val = []; $desc = []; $path = [];
    $ids = array_keys($uso);
    if ($ids) {
        $inq = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT id, valor, descricao, path_str FROM orcamento_linha WHERE id IN ($inq)");
        $st->execute($ids);
        foreach ($st->fetchAll() as $l) {
            $val[$l['id']] = (float)$l['valor']; $desc[$l['id']] = $l['descricao']; $path[$l['id']] = $l['path_str'];
        }
    }

    $cov_dist = 0; $cov_dups = 0; $inflado = 0; $dups = [];
    foreach ($uso as $id => $itens) {
        $v = $val[$id] ?? 0;
        $cov_dist += $v;
        $cov_dups += $v * count($itens);
        if (count($itens) > 1) {
            $inflado += $v * (count($itens) - 1);
            $dups[] = ['id'=>$id, 'valor'=>$v, 'n'=>count($itens),
                       'descricao'=>$desc[$id] ?? '', 'path'=>$path[$id] ?? '', 'itens'=>$itens];
        }
    }
    usort($dups, fn($a,$b) => ($b['valor']*$b['n']) <=> ($a['valor']*$a['n']));

    $comp = $pdo->query("SELECT COUNT(*) c, COALESCE(SUM(COALESCE(verba_override,0)),0) v
                         FROM radar_item WHERE obra_id=1 AND verba_metodo='composicao'")->fetch();

    echo json_encode([
        'total_obra'                 => $total_obra,
        'total_leaf'                 => $total_leaf,
        'itens_com_refs_analitico'   => $itens_refs,
        'linhas_distintas_usadas'    => count($uso),
        'linhas_duplicadas'          => count($dups),
        'valor_coberto_distinto'     => round($cov_dist, 2),
        'valor_coberto_com_dups'     => round($cov_dups, 2),
        'valor_inflado_por_dup'      => round($inflado, 2),
        'cobertura_distinta_pct_folhas' => $total_leaf ? round($cov_dist/$total_leaf*100, 1) : null,
        'cobertura_distinta_pct_obra'   => $total_obra ? round($cov_dist/$total_obra*100, 1) : null,
        'composicao_itens'           => (int)$comp['c'],
        'composicao_verba'           => round((float)$comp['v'], 2),
        'duplicatas'                 => $dups,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
