<?php
// Endpoint de teste: chamado pelo front via fetch. Retorna JSON.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/bitrix.php';

$out = [];
// 1) Perfil do dono do webhook
$out['profile'] = bx_call('profile');
// 2) Escopos (permissões) liberados para o webhook
$out['scope'] = bx_call('scope');
// 3) Amostra do CRM (prova acesso a dados) — se o escopo crm estiver liberado
$out['deal_sample'] = bx_call('crm.deal.list', [
    'order'  => ['ID' => 'DESC'],
    'select' => ['ID', 'TITLE'],
    'start'  => 0,
]);

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
