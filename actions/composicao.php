<?php
/**
 * Composições (Lista de Composição) para a fonte de verba/quantitativo por composição.
 * GET:
 *   q=<termo>  -> busca composições por descrição
 *   id=<id>    -> detalhe da composição + insumos (com tipo material/mo e coeficiente)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db(); db_seed_if_empty();

    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $c = $pdo->prepare("SELECT * FROM composicao WHERE id=?");
        $c->execute([$id]);
        $comp = $c->fetch();
        if (!$comp) { echo json_encode(['error'=>'composição não encontrada']); exit; }
        $ins = $pdo->prepare("SELECT descricao,unidade,coef,rs_unit,rs_total,tipo FROM composicao_insumo WHERE composicao_id=? ORDER BY id");
        $ins->execute([$id]);
        $comp['insumos'] = $ins->fetchAll();
        echo json_encode($comp, JSON_UNESCAPED_UNICODE); exit;
    }

    $q = trim($_GET['q'] ?? '');
    if ($q === '') { echo json_encode(['composicoes'=>[]]); exit; }
    $st = $pdo->prepare("SELECT id,descricao,unidade,qtde_total,rs_unit FROM composicao
                         WHERE descricao LIKE ? ORDER BY rs_total DESC LIMIT 40");
    $st->execute(["%$q%"]);
    echo json_encode(['composicoes'=>$st->fetchAll()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
