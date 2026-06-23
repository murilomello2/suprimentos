<?php
/* Remoção emergencial + autodelete. Uso único. */
header('Content-Type: text/plain; charset=utf-8');
$n = 0;
foreach ([__DIR__ . '/../Bases', __DIR__ . '/../tools'] as $dir) {
    foreach (glob($dir . '/*') ?: [] as $f) { if (is_file($f) && @unlink($f)) $n++; }
    foreach (glob($dir . '/.*') ?: [] as $f) { if (is_file($f) && @unlink($f)) $n++; }
    @rmdir($dir);
}
foreach (['/.token_cache.json', '/.users_cache.json'] as $c) {
    $p = __DIR__ . '/../data' . $c; if (is_file($p) && @unlink($p)) $n++;
}
foreach (glob(__DIR__ . '/../data/.crono_*.json') ?: [] as $f) { if (@unlink($f)) $n++; }
echo "removidos: $n\n";
echo "Bases existe? " . (is_dir(__DIR__ . '/../Bases') ? 'SIM' : 'nao') . "\n";
@unlink(__FILE__); // autodelete
echo "purge autodeletado.\n";
