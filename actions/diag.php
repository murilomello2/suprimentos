<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
$out = [
    'php_version' => PHP_VERSION,
    'pdo_sqlite'  => extension_loaded('pdo_sqlite'),
    'pdo_drivers' => class_exists('PDO') ? PDO::getAvailableDrivers() : [],
    'curl'        => extension_loaded('curl'),
    'arrow_fn'    => version_compare(PHP_VERSION, '7.4.0', '>='),
    'data_dir'        => dirname(DB_PATH),
    'data_dir_exists' => is_dir(dirname(DB_PATH)),
    'data_writable'   => is_writable(dirname(DB_PATH)),
    'seed_exists'     => is_file(SEED_DIR . '/trinity.json'),
    /* LIMITES DE UPLOAD — o anexo de proposta falhava "sem motivo" para alguns compradores e não
       para outros: quando o POST passa de post_max_size o PHP descarta $_FILES E $_POST inteiros,
       então o endpoint nem sabe que houve arquivo. Sem estes números não dá para diagnosticar. */
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size'       => ini_get('post_max_size'),
    'max_file_uploads'    => ini_get('max_file_uploads'),
    'memory_limit'        => ini_get('memory_limit'),
    'file_uploads'        => (bool)ini_get('file_uploads'),
    'upload_tmp_dir'      => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
    'tmp_gravavel'        => is_writable(ini_get('upload_tmp_dir') ?: sys_get_temp_dir()),
];
// tenta criar/escrever um arquivo de teste na pasta data
$probe = dirname(DB_PATH) . '/.probe';
$out['can_write_probe'] = @file_put_contents($probe, 'x') !== false;
@unlink($probe);
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
