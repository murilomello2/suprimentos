<?php
/**
 * Diagnóstico: quais ITENS DO RADAR usam as composições que MUDARAM (insumos separados) no R06.
 * Compara data/seed/composicao_trinity.json (R06) com data/seed/_backup_r05/composicao_trinity.json (R05)
 * por id; as composições com lista de insumos diferente são as "separadas". Depois varre radar_item.composicao_sel
 * e lista os itens que referenciam esses cids — são os que precisam de re-curadoria (a posição idx mudou).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();
    $r6 = json_decode(@file_get_contents(SEED_DIR . '/composicao_trinity.json'), true);
    $r5 = json_decode(@file_get_contents(SEED_DIR . '/_backup_r05/composicao_trinity.json'), true);
    if (!is_array($r6) || !is_array($r5)) { echo json_encode(['error' => 'seeds não encontrados pra comparar']); exit; }

    $insNames = function($co) { return array_map(function($i){ return $i['descricao']; }, $co['insumos'] ?? []); };
    $r5by = []; foreach ($r5['composicoes'] as $c) $r5by[(int)$c['id']] = $c;

    $mudadas = []; // cid => descricao
    foreach ($r6['composicoes'] as $c) {
        $cid = (int)$c['id']; $old = $r5by[$cid] ?? null;
        if ($old && $insNames($c) !== $insNames($old)) $mudadas[$cid] = $c['descricao'];
    }

    // varre a curadoria online
    $rows = $pdo->query("SELECT r.servico_id AS ordem, s.nome, r.composicao_sel
                         FROM radar_item r JOIN servico s ON s.id=r.servico_id WHERE r.obra_id=1")->fetchAll();
    $itens = [];
    foreach ($rows as $r) {
        $sel = json_decode($r['composicao_sel'] ?? '[]', true) ?: [];
        $cidsUsados = [];
        foreach ($sel as $s) { $cid = (int)($s['cid'] ?? 0); if (isset($mudadas[$cid])) $cidsUsados[$cid] = $mudadas[$cid]; }
        if ($cidsUsados) $itens[] = ['ordem' => (int)$r['ordem'], 'nome' => $r['nome'], 'composicoes' => array_values($cidsUsados)];
    }
    usort($itens, function($a, $b){ return strcmp($a['nome'], $b['nome']); });

    echo json_encode([
        'n_composicoes_mudadas' => count($mudadas),
        'composicoes_mudadas'   => array_values($mudadas),
        'n_itens_afetados'      => count($itens),
        'itens'                 => $itens,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
