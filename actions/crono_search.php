<?php
/**
 * Busca tarefas do cronograma vivo (Supabase) para o seletor de match manual.
 * GET: q (termo). Retorna tarefas cujo nome casa TODAS as palavras (ordem livre, níveis acima e abaixo),
 * tolerando PLURAL (ex.: "acabamentos elétricos" acha "ACABAMENTO ELÉTRICOS"), com o CAMINHO (WBS por nome).
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

    $base = 'obra_cronograma_tarefas?cronograma_id=eq.' . rawurlencode($cid);

    // TOKENIZA: cada palavra vira um ilike; o nó tem que conter TODAS (ordem livre → acha em qualquer nível).
    // PLURAL: tira o "s" final de cada palavra (ASCII, sem mbstring) — assim "acabamentos" casa "ACABAMENTO".
    $vals = [];
    foreach (preg_split('/\s+/', $q) as $t) {
        $t = trim($t);
        if (strlen($t) < 3) continue;                                       // ignora "de","e","1"…
        if (substr($t, -1) === 's' && strlen($t) > 3) $t = substr($t, 0, -1); // stem plural
        $vals[] = rawurlencode('*' . $t . '*');
    }
    if (!$vals) $vals[] = rawurlencode('*' . $q . '*');                      // fallback: frase inteira
    // 1 palavra = filtro top-level (nome=ilike.); 2+ = grupo AND (nome.ilike. dentro de and=())
    if (count($vals) === 1) {
        $filter = '&nome=ilike.' . $vals[0];
    } else {
        $filter = '&and=(' . implode(',', array_map(function ($v) { return 'nome.ilike.' . $v; }, $vals)) . ')';
    }

    // 1 SÓ chamada ao Supabase por busca (o caminho por ancestrais foi removido — fazia uma 2ª chamada por tecla
    // e sobrecarregava o Supabase, agravando a lentidão. O front mostra o WBS, que já situa a tarefa.)
    $rows = sb_get($base . $filter
          . '&select=outline_number,nome,wbs,start,finish,is_milestone,is_summary,outline_level'
          . '&order=start.asc&limit=60');

    echo json_encode(['tarefas'=>$rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
