<?php
require_once __DIR__ . '/config.php';

/**
 * Chama qualquer método REST do Bitrix24 usando o webhook do config.php.
 * O webhook nunca sai desta camada PHP.
 */
function bx_call($method, $params = []) {
    $url = rtrim(BITRIX_WEBHOOK, '/') . '/' . $method . '.json';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false) {
        return ['error' => 'curl_failed', 'error_description' => $err, 'http_status' => $code];
    }
    $json = json_decode($res, true);
    return $json !== null ? $json : ['error' => 'invalid_json', 'raw' => $res];
}
