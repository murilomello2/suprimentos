<?php
/**
 * Árvore do cronograma (Supabase), navegação lazy por prefixo de outline_number.
 * GET:
 *   children_of=<outline>  -> filhos diretos (outline_number like PREFIXO.* e level = n+1)
 *   (sem param)            -> raiz (outline_level = 1)
 * Sempre filtra o cronograma da obra e ordena por 'ordem' (sequência do MS-Project).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/supabase.php';

try {
    $pdo  = db();
    $cid  = ($pdo->query("SELECT cronograma_id FROM obra WHERE id=1")->fetch())['cronograma_id'] ?? '';
    if (!$cid) { echo json_encode(['nos'=>[]]); exit; }

    $sel = 'outline_number,wbs,nome,outline_level,is_summary,is_milestone,start,finish';
    $base = 'obra_cronograma_tarefas?cronograma_id=eq.' . rawurlencode($cid)
          . '&select=' . $sel . '&order=ordem.asc';

    $codigo = $_GET['children_of'] ?? '';
    if ($codigo === '') {
        $path = $base . '&outline_level=eq.1&limit=50';
    } else {
        $nivel = count(explode('.', $codigo)) + 1;
        $path = $base
              . '&outline_number=like.' . rawurlencode($codigo . '.*')
              . '&outline_level=eq.' . $nivel . '&limit=300';
    }
    $rows = sb_get($path);
    $nos = array_map(function($t){
        return [
            'outline'=>$t['outline_number'], 'wbs'=>$t['wbs'], 'nome'=>$t['nome'],
            'nivel'=>(int)$t['outline_level'], 'start'=>$t['start'], 'finish'=>$t['finish'],
            'is_summary'=>(bool)$t['is_summary'], 'is_milestone'=>(bool)$t['is_milestone'],
            'expansivel'=>(bool)$t['is_summary'],
        ];
    }, $rows);
    echo json_encode(['parent'=>$codigo, 'nos'=>$nos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
