<?php
/**
 * VERSÃO DO BUILD — o carimbo que diz "o front que está no ar é este".
 *
 * É o maior mtime entre o index.php e todos os js/*.js. Serve a dois propósitos:
 *   1) ETag do index.php, para o navegador revalidar em vez de reusar HTML velho às cegas;
 *   2) o app aberto há horas descobrir que saiu versão nova e se atualizar sozinho.
 *
 * Por que glob() e não uma lista fixa: quando alguém criar js/app08.js, o carimbo tem de
 * passar a considerá-lo sem ninguém lembrar de vir aqui. Lista fixa envelhece calada.
 */
function sup_versao() {
    static $v = null;
    if ($v !== null) return $v;
    $base = dirname(__DIR__);
    $v = 0;
    foreach (array_merge([$base . '/index.php'], glob($base . '/js/*.js') ?: []) as $f) {
        if (is_file($f)) { $m = (int)filemtime($f); if ($m > $v) $v = $m; }
    }
    return $v;
}

/** ETag do build. Apache com mod_deflate anexa "-gzip" ao ETag que devolve, e o
    If-None-Match volta com esse sufixo — comparar cru daria "mudou" em toda requisição. */
function sup_etag_bate($etag) {
    $in = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    if ($in === '') return false;
    foreach (explode(',', $in) as $cand) {
        $cand = trim($cand);
        $cand = preg_replace('/^W\//', '', $cand);
        $cand = preg_replace('/-(gzip|br)"$/', '"', $cand);
        if ($cand === $etag) return true;
    }
    return false;
}
