<?php
/* Remoção emergencial de arquivos que não deveriam estar no servidor público.
   Apaga a pasta Bases/ (orçamento/De-Para) e caches sensíveis. Uso único. */
header('Content-Type: text/plain; charset=utf-8');
$n = 0;
$alvos = [__DIR__ . '/../Bases', __DIR__ . '/../tools'];
foreach ($alvos as $dir) {
    foreach (glob($dir . '/*') ?: [] as $f) { if (is_file($f) && @unlink($f)) $n++; }
    foreach (glob($dir . '/.*') ?: [] as $f) { if (is_file($f) && @unlink($f)) $n++; }
    @rmdir($dir);
}
foreach (['/.token_cache.json', '/.users_cache.json'] as $c) {
    $p = __DIR__ . '/../data' . $c;
    if (is_file($p) && @unlink($p)) $n++;
}
foreach (glob(__DIR__ . '/../data/.crono_*.json') ?: [] as $f) { if (@unlink($f)) $n++; }
foreach (glob(__DIR__ . '/../_tmp_*.txt') ?: [] as $f) { if (@unlink($f)) $n++; }
echo "removidos: $n\n";
echo "Bases existe? " . (is_dir(__DIR__ . '/../Bases') ? 'SIM' : 'nao') . "\n";
