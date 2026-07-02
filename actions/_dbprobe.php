<?php
/**
 * DESATIVADO (410). Já cumpriu o papel (migração SQLite->MySQL, reset da flag verba_curada,
 * e re-import do orçamento com multiplicadores). Neutralizado para não re-rodar por acidente.
 */
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'gone', 'nota' => 'ferramenta administrativa desativada']);
