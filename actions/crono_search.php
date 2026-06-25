<?php
/**
 * Busca tarefas do cronograma vivo (Supabase) para o seletor de match manual.
 * GET: q (termo). Retorna tarefas cujo nome contém o termo, com nome/wbs/start.
 * Busca direto no Supabase (não só no cache de resumo) para achar tarefas profundas.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/supabase.php';

try {
    $pdo  = db();
    $obra = $pdo->query("SELECT cronograma_id FROM obra WHERE id=1")->fetch();
    $cid  = $obra['cronograma_id'] ?? '';
    $q    = trim($_GET['q'] ?? '');
    if (!$cid || strlen($q) < 2) { echo json_encode(['tarefas'=>[]]); exit; }  // strlen: servidor não tem mbstring

    // ilike no nome; pega tarefas (não-resumo de preferência) com data
    $path = 'obra_cronograma_tarefas?cronograma_id=eq.' . rawurlencode($cid)
          . '&nome=ilike.' . rawurlencode('*' . $q . '*')
          . '&select=outline_number,nome,wbs,start,finish,is_milestone,is_summary,outline_level'
          . '&order=start.asc&limit=40';
    $rows = sb_get($path);
    echo json_encode(['tarefas'=>$rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
