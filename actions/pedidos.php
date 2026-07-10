<?php
/**
 * PEDIDOS DE COMPRA — leitura da base do TOTVS (Supabase pedidos_itens), só leitura.
 * GET ?numero=<pedido>&me=..   -> a "fotinha" do pedido: coligada, fornecedor(es), itens (qtd/unid/preço unit/total) e total geral
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/pedidos.php';

try {
    $pdo = db();
    $perms = user_perms($pdo, $_GET['me'] ?? null);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }

    if (isset($_GET['numero'])) {
        $p = pedido_por_numero($_GET['numero']);
        echo json_encode($p ? ['ok' => true, 'pedido' => $p] : ['error' => 'Pedido não encontrado na base do TOTVS.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (isset($_GET['solicitacao'])) {   // pedidos que nasceram de uma solicitação (vínculo exato SC→PC)
        $peds = pedidos_por_solicitacao($_GET['solicitacao'], $_GET['coligada'] ?? null);
        echo json_encode(['ok' => true, 'pedidos' => $peds], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['error' => 'informe o número do pedido ou da solicitação'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
