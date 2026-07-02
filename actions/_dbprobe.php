<?php
/**
 * DESATIVADO (410). Já cumpriu o papel (migração SQLite->MySQL e o reset one-shot da flag verba_curada).
 * Mantido neutralizado para não haver risco de re-rodar operações administrativas. Pode ser removido do FTP.
 */
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'gone', 'nota' => 'ferramenta administrativa desativada']);
