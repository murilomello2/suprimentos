<?php
/**
 * DESCARTÁVEL — remove o .user.ini que eu subi por engano em 14/08/2026 e se apaga em seguida.
 *
 * Por que existe: o deploy é por FTP e só ENVIA arquivo, nunca remove. O .user.ini foi subido para
 * levantar o limite de upload (a hospedagem aceita 2 MB) e teve um efeito colateral grave: o
 * `auto_prepend_file` da hospedagem (o banner "App Protótipo") passou a valer para esta pasta e
 * começou a sair ANTES do JSON de todo endpoint — com a saída já iniciada, o header
 * Content-Type: application/json é ignorado e o front quebra em toda chamada.
 *
 * Volta ao estado conhecido-bom. O limite de upload continua sendo assunto do TI (cPanel ›
 * MultiPHP INI Editor); o app agora, ao menos, mostra o teto real e barra o arquivo grande antes.
 */
header('Content-Type: text/plain; charset=utf-8');
$alvo = __DIR__ . '/../.user.ini';
$r = [];
$r[] = 'existe antes: ' . (is_file($alvo) ? 'sim' : 'nao');
if (is_file($alvo)) $r[] = 'unlink: ' . (@unlink($alvo) ? 'ok' : 'FALHOU');
clearstatcache();
$r[] = 'existe depois: ' . (is_file($alvo) ? 'SIM (nao consegui apagar)' : 'nao');
$r[] = 'auto_prepend_file: [' . ini_get('auto_prepend_file') . ']';
$r[] = 'upload_max_filesize: ' . ini_get('upload_max_filesize') . ' | post_max_size: ' . ini_get('post_max_size');
$r[] = 'autodestruicao: ' . (@unlink(__FILE__) ? 'ok' : 'FALHOU (apague actions/_fixini.php na mao)');
echo "RESULTADO_FIXINI\n" . implode("\n", $r) . "\n";
