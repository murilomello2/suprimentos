<?php
/**
 * Quebra a verba de um item em MATERIAL / MO / EQUIPAMENTO / MATERIAL+MO, mesmo quando ela foi
 * definida por ORÇAMENTO ANALÍTICO (linha inteira). Resolve cada linha -> a composição dela -> insumos.
 * GET ?ordem=<servico_id>
 * Retorna:
 *   tot_por_tipo {material,mo,mat_mo,equip}, total
 *   linhas: [{id, descricao, path, valor, sem_composicao, tot_por_tipo,
 *             insumos:[{desc,tipo,unidade,qtde,rs_unit,valor}]}]    (verba analítica → por LINHA)
 *   por_tipo: { material:[{desc,unidade,qtde,valor}], mo:[...], mat_mo:[...], equip:[...] }  (agregado de tudo)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/verba.php';

try {
    $pdo = db();
    $OBRA = max(1, (int)($_GET['obra'] ?? 1));   // multi-obra
    $ordem = (int)($_GET['ordem'] ?? 0);
    // Overrides p/ EDIÇÃO ao vivo (seleção/exclusões ainda não salvas). excl = [{l:lineId, d:descrição}]
    $refs = isset($_GET['refs']) ? array_values(array_filter(array_map('intval', explode(',', $_GET['refs'])))) : null;
    $excl = isset($_GET['excl']) ? (json_decode($_GET['excl'], true) ?: []) : null;
    $out = verba_breakdown_data($pdo, $ordem, $OBRA, $refs, $excl);
    if (isset($out['error'])) { echo json_encode($out, JSON_UNESCAPED_UNICODE); exit; }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
