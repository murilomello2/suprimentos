<?php
/**
 * IDENTIDADE VERIFICADA — provar quem é quem, em vez de acreditar.
 *
 * O buraco: todo endpoint recebe `me=<bitrix_id>` do navegador e confia. Medido em 06/08/2026,
 * de fora, com curl, sem sessão nenhuma:
 *     actions/usuarios.php?me=20  ->  "Murilo Mello, admin=SIM"
 *     actions/cotacoes.php?me=20  ->  809 cotações
 * Qualquer pessoa que saiba a URL é administradora. O bloqueio que pus no navegador impede o
 * ACIDENTE (a Paloma virar o Murilo sem querer); não impede ninguém deliberado.
 *
 * O conserto usa o que o Bitrix já manda e a gente ignorava: ao abrir um aplicativo local, ele
 * faz POST no handler com AUTH_ID — um token OAuth do USUÁRIO LOGADO. Esse token não dá para
 * forjar, porque quem confere é o próprio Bitrix: perguntamos a ele "de quem é este token?".
 *
 * Com a resposta na mão emitimos um bilhete nosso, assinado (HMAC), com validade curta. Todas as
 * chamadas seguintes mandam o bilhete; o servidor confere a assinatura e usa o id que está DENTRO
 * dele — nunca o `me` que veio na requisição.
 *
 * Bilhete em vez de cookie de propósito: o app roda em iframe de outro domínio (caprem.tech
 * embutindo appdemo.capremconstrutora.com.br), e cookie de terceiro é bloqueado pelo navegador.
 */

define('AUTH_SEGREDO_FILE', __DIR__ . '/../data/.appsecret');
define('AUTH_TTL', 12 * 3600);            // 12 h: cobre um dia de trabalho sem reabrir o app
define('AUTH_MODO_FILE', __DIR__ . '/../data/.authmodo');   // 'auditoria' | 'estrito'

/** Segredo do HMAC. Nasce sozinho na 1ª execução e nunca sai do servidor. */
function auth_segredo() {
    static $s = null;
    if ($s !== null) return $s;
    if (is_file(AUTH_SEGREDO_FILE)) { $s = trim((string)@file_get_contents(AUTH_SEGREDO_FILE)); if ($s !== '') return $s; }
    $s = bin2hex(random_bytes(32));
    @file_put_contents(AUTH_SEGREDO_FILE, $s);
    @chmod(AUTH_SEGREDO_FILE, 0600);
    return $s;
}

/** 'auditoria' (padrão): sem bilhete o sistema funciona, mas anota. 'estrito': sem bilhete, 401.
    Existe modo de auditoria porque virar a chave às cegas tranca todo mundo para fora se o
    AUTH_ID não chegar como se espera — e aí o remédio é pior. */
function auth_modo() {
    $m = is_file(AUTH_MODO_FILE) ? trim((string)@file_get_contents(AUTH_MODO_FILE)) : '';
    return $m === 'estrito' ? 'estrito' : 'auditoria';
}
function auth_modo_set($m) {
    @file_put_contents(AUTH_MODO_FILE, $m === 'estrito' ? 'estrito' : 'auditoria');
    @chmod(AUTH_MODO_FILE, 0600);
}

/** Pergunta ao BITRIX de quem é este AUTH_ID. É aqui que a confiança vira verificação. */
function auth_bitrix_quem($authId, $dominio = null) {
    $authId = trim((string)$authId);
    if ($authId === '') return [null, 'sem AUTH_ID'];
    $dom = trim((string)($dominio ?: 'caprem.tech'));
    if (!preg_match('/^[a-z0-9.\-]+$/i', $dom)) return [null, 'domínio inválido'];
    $url = 'https://' . $dom . '/rest/user.current.json?auth=' . rawurlencode($authId);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    $ca = ini_get('curl.cainfo'); if ($ca && is_file($ca)) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch);
    curl_close($ch);
    if ($code !== 200) return [null, 'Bitrix HTTP ' . $code . ($err ? (': ' . $err) : '')];
    $j = json_decode((string)$res, true);
    $id = $j['result']['ID'] ?? null;
    if (!$id) return [null, 'resposta sem ID: ' . substr((string)$res, 0, 160)];
    return [(string)$id, ''];
}

/** Emite o bilhete assinado: <bitrix_id>.<expira>.<assinatura> */
function auth_emitir($bitrixId, $ttl = AUTH_TTL) {
    $exp = time() + (int)$ttl;
    $base = $bitrixId . '.' . $exp;
    return $base . '.' . hash_hmac('sha256', $base, auth_segredo());
}

/** Confere o bilhete. -> bitrix_id | null. Compara em tempo constante (hash_equals). */
function auth_verificar($tk) {
    $tk = trim((string)$tk);
    if ($tk === '' || substr_count($tk, '.') !== 2) return null;
    [$id, $exp, $sig] = explode('.', $tk);
    if ($id === '' || !ctype_digit($exp)) return null;
    if ((int)$exp < time()) return null;                       // vencido
    $ok = hash_hmac('sha256', $id . '.' . $exp, auth_segredo());
    return hash_equals($ok, $sig) ? $id : null;
}

/** O bilhete da requisição atual, venha de onde vier (query, POST form ou corpo JSON). */
function auth_tk_da_requisicao() {
    if (!empty($_GET['tk']))  return (string)$_GET['tk'];
    if (!empty($_POST['tk'])) return (string)$_POST['tk'];
    static $body = null;
    if ($body === null) {
        $raw = @file_get_contents('php://input');
        $body = $raw ? (json_decode($raw, true) ?: []) : [];
    }
    return isset($body['tk']) ? (string)$body['tk'] : '';
}

/** Identidade VERIFICADA desta requisição, ou null. */
function auth_id_verificada() {
    static $cache = false;
    if ($cache !== false) return $cache;
    return $cache = auth_verificar(auth_tk_da_requisicao());
}

/** Registra chamadas sem bilhete — é como vamos saber se dá para virar a chave sem trancar gente. */
function auth_auditar($meAlegado) {
    try {
        $f = __DIR__ . '/../data/.auth_audit.log';
        if (is_file($f) && filesize($f) > 2 * 1024 * 1024) @unlink($f);   // não deixa crescer sem fim
        @file_put_contents($f, json_encode([
            'q' => date('c'), 'me' => (string)$meAlegado,
            'end' => basename((string)($_SERVER['SCRIPT_NAME'] ?? '')),
            'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 80),
            'ref' => substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 120),
        ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    } catch (Throwable $e) {}
}

/** Registra bilhete EMITIDO com sucesso. É a contraprova do log de falhas: sem ela, "nenhuma
    chamada sem bilhete" não distingue "todo mundo com bilhete" de "ninguém abriu o sistema". */
function auth_registrar_ok($bitrixId, $nome = '') {
    try {
        $f = __DIR__ . '/../data/.auth_ok.log';
        if (is_file($f) && filesize($f) > 512 * 1024) @unlink($f);
        @file_put_contents($f, json_encode([
            'q' => date('c'), 'id' => (string)$bitrixId, 'nome' => (string)$nome,
            'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    } catch (Throwable $e) {}
}
