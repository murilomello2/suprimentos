<?php
/**
 * Carimbo do build que está no ar. Sem banco, sem auth: não é dado, é o número da versão —
 * e precisa responder mesmo para uma aba que ficou aberta a noite toda com sessão vencida.
 * Deliberadamente sem cache: é justamente ele que denuncia o cache dos outros.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../includes/versao.php';
echo json_encode(['v' => sup_versao()]);
