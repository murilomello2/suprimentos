<?php
/**
 * Busca em MASSA de linhas-folha do orçamento por vários TERMOS, agrupadas por termo.
 * Pra montar a verba de itens com muitos insumos (ex.: tubos e conexões hidráulicas).
 * GET:
 *   termos=tubo,luva,joelho,...   (separados por vírgula)
 *   escopo=hidr | tudo            (hidr = só caminhos de Instalações hidráulicas/sanitárias; default hidr)
 * Retorna: { grupos:[{termo,n,valor,linhas:[{id,desc,local,valor}]}], total, n_linhas }
 * Cada linha entra em UM grupo só (o 1º termo da lista que casar) — não conta em dobro.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

// normaliza sem mbstring (servidor não tem): tira acento + minúscula
function _normt($s){
    $map = ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c',
            'Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e','Í'=>'i','Ó'=>'o','Ô'=>'o','Õ'=>'o','Ú'=>'u','Ç'=>'c'];
    return strtolower(strtr((string)$s, $map));
}

try {
    $pdo = db();
    $termos = array_values(array_filter(array_map(function($t){ return _normt(trim($t)); }, explode(',', $_GET['termos'] ?? ''))));
    if (!$termos) { echo json_encode(['grupos'=>[], 'total'=>0, 'n_linhas'=>0]); exit; }
    $escopo = ($_GET['escopo'] ?? 'hidr');

    $where = "folha=1";
    if ($escopo === 'hidr') $where .= " AND (lower(path_str) LIKE '%hidr%' OR lower(path_str) LIKE '%sanit%')";
    $rows = $pdo->query("SELECT id, descricao, path_str, qtde, unidade, valor FROM orcamento_linha WHERE $where")->fetchAll();

    $grupos = []; foreach ($termos as $t) $grupos[$t] = ['termo'=>$t, 'n'=>0, 'valor'=>0.0, 'linhas'=>[]];
    $total = 0.0; $nlin = 0;
    foreach ($rows as $r) {
        $nd = _normt($r['descricao']);
        foreach ($termos as $t) {
            if ($t !== '' && preg_match('/\b' . preg_quote($t, '/') . '/', $nd)) {
                $loc = trim(explode('›', (string)$r['path_str'])[0]);
                $grupos[$t]['linhas'][] = ['id'=>(int)$r['id'], 'desc'=>$r['descricao'], 'local'=>$loc, 'valor'=>(float)$r['valor']];
                $grupos[$t]['n']++; $grupos[$t]['valor'] += (float)$r['valor'];
                $total += (float)$r['valor']; $nlin++;
                break; // cada linha em UM grupo (1º termo que casar)
            }
        }
    }
    $grupos = array_values(array_filter($grupos, function($g){ return $g['n'] > 0; }));
    usort($grupos, function($a,$b){ return $b['valor'] <=> $a['valor']; });
    echo json_encode(['grupos'=>$grupos, 'total'=>$total, 'n_linhas'=>$nlin], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
