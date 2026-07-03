<?php
/**
 * Busca em MASSA de linhas-folha do orçamento por vários TERMOS.
 * Pra montar a verba de itens com muitos insumos (ex.: tubos e conexões hidráulicas).
 * Cada linha volta marcada com TERMO (1º que casa) e MATERIAL (PVC/CPVC/PEX/cobre/metal/outro),
 * pra separar por fornecedor (PVC+CPVC=Tigre/Krona; PEX=outro; registros de ferro=Deca/Docol).
 * GET:
 *   termos=tubo,luva,joelho,...   (separados por vírgula)
 *   escopo=hidr | tudo            (hidr = só caminhos de Instalações hidráulicas/sanitárias; default hidr)
 *   material=pvc,cpvc,...         (opcional: filtra só esses materiais)
 * Retorna: { linhas:[{id,desc,local,valor,termo,material}], total, n_linhas }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

// normaliza sem mbstring (servidor não tem): tira acento + minúscula
function _normt($s){
    $map = ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c',
            'Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e','Í'=>'i','Ó'=>'o','Ô'=>'o','Õ'=>'o','Ú'=>'u','Ç'=>'c'];
    return strtolower(strtr((string)$s, $map));
}
// detecta o material (=fornecedor) a partir da descrição normalizada
function _matr($nd){
    if (strpos($nd,'cpvc') !== false) return 'cpvc';
    if (strpos($nd,'pex')  !== false) return 'pex';
    if (strpos($nd,'pvc')  !== false) return 'pvc';
    if (strpos($nd,'cobre')!== false) return 'cobre';
    if (preg_match('/\b(ferro|galvaniz|latao|bronze|metalic|metal|inox)\b/', $nd)) return 'metal';
    if (preg_match('/\b(registro|misturador|valvula|misturadora)\b/', $nd))        return 'metal'; // Deca/Docol
    if (preg_match('/\b(esgoto|sifonad|coletor)\b/', $nd))                          return 'pvc';   // PVC por construção
    return 'outro';
}

try {
    $pdo = db();
    $termos = array_values(array_filter(array_map(function($t){ return _normt(trim($t)); }, explode(',', $_GET['termos'] ?? ''))));
    if (!$termos) { echo json_encode(['linhas'=>[], 'total'=>0, 'n_linhas'=>0]); exit; }
    $escopo = ($_GET['escopo'] ?? 'hidr');
    $matFiltro = array_values(array_filter(array_map(function($t){ return _normt(trim($t)); }, explode(',', $_GET['material'] ?? ''))));

    $OBRA = max(1, (int)($_GET['obra'] ?? 1));   // multi-obra
    $where = "obra_id=? AND folha=1";
    if ($escopo === 'hidr') $where .= " AND (lower(path_str) LIKE '%hidr%' OR lower(path_str) LIKE '%sanit%')";
    $stq = $pdo->prepare("SELECT id, descricao, path_str, qtde, unidade, valor FROM orcamento_linha WHERE $where");
    $stq->execute([$OBRA]);
    $rows = $stq->fetchAll();

    $linhas = []; $total = 0.0;
    foreach ($rows as $r) {
        $nd = _normt($r['descricao']);
        foreach ($termos as $t) {
            if ($t !== '' && preg_match('/\b' . preg_quote($t, '/') . '/', $nd)) {
                $mat = _matr($nd);
                if ($matFiltro && !in_array($mat, $matFiltro, true)) break; // fora do filtro de material
                $loc = trim(explode('›', (string)$r['path_str'])[0]);
                $linhas[] = ['id'=>(int)$r['id'], 'desc'=>$r['descricao'], 'local'=>$loc,
                             'valor'=>(float)$r['valor'], 'termo'=>$t, 'material'=>$mat];
                $total += (float)$r['valor'];
                break; // cada linha em UM termo (1º que casar)
            }
        }
    }
    echo json_encode(['linhas'=>$linhas, 'total'=>$total, 'n_linhas'=>count($linhas)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
