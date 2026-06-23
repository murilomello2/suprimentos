<?php
/**
 * Orçamento analítico (árvore navegável) para o seletor de composição de verba.
 * GET:
 *   children_of=<codigo>  -> filhos diretos do nó (navegação na árvore)
 *   q=<termo>             -> busca folhas (N8) por descrição/caminho
 *   ids=<csv>             -> resolve uma seleção (para mostrar a composição atual)
 *   (sem params)          -> raiz: os grandes grupos (filhos de "1" = Café Filho)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

function rows_out($rows){
    return array_map(function($r){
        return [
            'id'=>(int)$r['id'], 'codigo'=>$r['codigo'], 'descricao'=>$r['descricao'],
            'depth'=>(int)$r['depth'], 'nivel'=>(int)$r['nivel'], 'valor'=>$r['valor']!==null?(float)$r['valor']:null,
            'folha'=>(int)$r['folha'], 'unidade'=>$r['unidade'], 'qtde'=>$r['qtde'],
            'path_str'=>$r['path_str'], 'expansivel'=>((int)$r['folha'])===0,
        ];
    }, $rows);
}

try {
    $pdo = db();
    db_seed_if_empty();

    if (isset($_GET['ids'])) {
        $arr = array_values(array_filter(array_map('intval', explode(',', $_GET['ids']))));
        if (!$arr) { echo json_encode(['linhas'=>[]]); exit; }
        $in = implode(',', array_fill(0, count($arr), '?'));
        $st = $pdo->prepare("SELECT * FROM orcamento_linha WHERE id IN ($in)");
        $st->execute($arr);
        echo json_encode(['linhas'=>rows_out($st->fetchAll())], JSON_UNESCAPED_UNICODE); exit;
    }

    if (isset($_GET['q']) && trim($_GET['q']) !== '') {
        $q = '%' . trim($_GET['q']) . '%';
        $st = $pdo->prepare("SELECT * FROM orcamento_linha
            WHERE folha=1 AND (descricao LIKE ? OR path_str LIKE ?)
            ORDER BY valor DESC LIMIT 60");
        $st->execute([$q, $q]);
        echo json_encode(['modo'=>'busca','linhas'=>rows_out($st->fetchAll())], JSON_UNESCAPED_UNICODE); exit;
    }

    $codigo = $_GET['children_of'] ?? '1'; // raiz = filhos de "1" (Café Filho)
    $st = $pdo->prepare("SELECT * FROM orcamento_linha WHERE parent = ? ORDER BY id");
    $st->execute([$codigo]);
    echo json_encode(['modo'=>'arvore','parent'=>$codigo,'linhas'=>rows_out($st->fetchAll())], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
