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
];
// tenta criar/escrever um arquivo de teste na pasta data
$probe = dirname(DB_PATH) . '/.probe';
$out['can_write_probe'] = @file_put_contents($probe, 'x') !== false;
@unlink($probe);
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
