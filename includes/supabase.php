<?php
require_once __DIR__ . '/config.php';

/**
 * Proxy de leitura do Cockpit de Obras (Supabase).
 * Faz login com o usuário de serviço, cacheia o access_token (~1h) em arquivo
 * no servidor e renomeia/renova quando expira. A senha nunca sai desta camada.
 */

function sb_http($method, $url, $headers, $body = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [$code, $res, $err];
}

/** Faz login e devolve [access_token, refresh_token, expira_em_epoch]. */
function sb_login() {
    [$code, $res] = sb_http('POST', SUPABASE_URL . '/auth/v1/token?grant_type=password', [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json',
    ], json_encode([
        'email'    => SUPABASE_SVC_EMAIL,
        'password' => CAPREM_API_PASSWORD,
    ]));
    $j = json_decode($res, true);
    if ($code !== 200 || empty($j['access_token'])) {
        throw new Exception('Supabase login falhou (HTTP ' . $code . ')');
    }
    return [
        'access_token'  => $j['access_token'],
        'refresh_token' => $j['refresh_token'] ?? '',
        'exp'           => time() + (int)($j['expires_in'] ?? 3600) - 120, // 2min de folga
    ];
}

/** Retorna um access_token válido, usando cache em arquivo. */
function sb_token() {
    $cache = @file_get_contents(TOKEN_CACHE);
    if ($cache) {
        $c = json_decode($cache, true);
        if (!empty($c['access_token']) && ($c['exp'] ?? 0) > time()) {
            return $c['access_token'];
        }
    }
    $c = sb_login();
    @file_put_contents(TOKEN_CACHE, json_encode($c));
    return $c['access_token'];
}

/** GET no PostgREST. $path ex.: 'obra_cronograma_tarefas?cronograma_id=eq.x&limit=5' */
function sb_get($path) {
    $tok = sb_token();
    $url = SUPABASE_URL . '/rest/v1/' . $path;
    [$code, $res] = sb_http('GET', $url, [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . $tok,
        'Accept: application/json',
    ]);
    if ($code === 401) { // token revogado/expirado fora do esperado: refaz login 1x
        @unlink(TOKEN_CACHE);
        $tok = sb_token();
        [$code, $res] = sb_http('GET', $url, [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $tok,
            'Accept: application/json',
        ]);
    }
    if ($code >= 300) throw new Exception('Supabase GET ' . $code . ': ' . substr($res, 0, 200));
    return json_decode($res, true);
}
