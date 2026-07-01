<?php
/**
 * DESATIVADO. Era a ferramenta temporária de sonda/migração SQLite->MySQL.
 * A migração foi concluída (app já roda em MySQL) — re-rodar sobrescreveria o MySQL
 * com o snapshot antigo do SQLite. Neutralizado de propósito. Pode ser removido do FTP.
 */
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'gone', 'nota' => 'ferramenta de migração desativada após o cutover para MySQL']);
