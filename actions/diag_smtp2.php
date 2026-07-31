<?php
/**
 * DIAGNÓSTICO 2 — se a saída SMTP está bloqueada, POR ONDE este servidor consegue mandar e-mail?
 *
 * O primeiro diagnóstico provou que nenhuma porta SMTP sai daqui: nem para a Caprem, nem para o
 * Gmail. A porta 25 é recusada em 4ms (resposta local, não viagem até o destino) e 465/587 morrem
 * em timeout — a assinatura clássica do firewall de hospedagem compartilhada, que rejeita uma e
 * dropa as outras.
 *
 * Nesse cenário sobram dois caminhos, e este endpoint mede os dois:
 *  1. o MTA LOCAL — o mesmo bloqueio quase sempre abre exceção para 127.0.0.1, e quem entrega é o
 *     Exim da própria hospedagem;
 *  2. a função mail() do PHP, que fala com o sendmail local por pipe e nem chega a abrir socket.
 *
 * Também confere o que o PHP deixa usar (disable_functions costuma tirar justamente mail()) e
 * mostra a conta das cotações — se ela aponta para um host diferente, isso explica o teste que
 * funcionou antes. Nada é enviado aqui e nenhuma senha é impressa.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
define('EC_LIB_ONLY', 1); require_once __DIR__ . '/envio_config.php';

@set_time_limit(180);
$TO = 5;

function d2_tentar($transporte, $host, $porta, $timeout) {
    $t0 = microtime(true);
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $en = 0; $es = '';
    $fp = @stream_socket_client("$transporte://$host:$porta", $en, $es, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    $ms = (int)round((microtime(true) - $t0) * 1000);
    if (!$fp) return ['ok' => false, 'ms' => $ms, 'errno' => $en, 'erro' => trim((string)$es)];
    stream_set_timeout($fp, $timeout);
    $banner = @fgets($fp, 512);
    @fwrite($fp, "QUIT\r\n"); @fclose($fp);
    return ['ok' => true, 'ms' => (int)round((microtime(true) - $t0) * 1000),
            'banner' => trim((string)$banner) !== '' ? substr(trim($banner), 0, 140) : null];
}

try {
    $pdo = db();
    $perms = user_perms($pdo, $_GET['me'] ?? null);
    if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error' => 'Apenas administradores.']); exit; }

    $out = [];

    /* ---- 1. quem e este servidor ---- */
    $out['servidor'] = [
        'hostname'   => @gethostname(),
        'ip_proprio' => $_SERVER['SERVER_ADDR'] ?? null,
        'php'        => PHP_VERSION,
        'appdemo_resolve_para' => @gethostbyname('appdemo.capremconstrutora.com.br'),
        'mail_caprem_resolve_para' => @gethostbyname('mail.caprem.com.br'),
    ];

    /* ---- 2. o MTA local aceita conexao? ---- */
    $out['mta_local'] = [];
    foreach ([['tcp', '127.0.0.1', 25], ['tcp', 'localhost', 25], ['tcp', '127.0.0.1', 587],
              ['ssl', '127.0.0.1', 465], ['tcp', '127.0.0.1', 465], ['tcp', '127.0.0.1', 2525]] as [$tr, $h, $p]) {
        $out['mta_local'][] = ['alvo' => "$tr://$h:$p"] + d2_tentar($tr, $h, $p, $TO);
    }

    /* ---- 3. o PHP consegue falar com o sendmail? (nao envia nada, so verifica o caminho) ---- */
    $sp = (string)@ini_get('sendmail_path');
    $bin = trim(explode(' ', trim($sp))[0]);
    $out['sendmail'] = [
        'mail_existe'      => function_exists('mail'),
        'sendmail_path'    => $sp !== '' ? $sp : null,
        'binario'          => $bin !== '' ? $bin : null,
        'binario_existe'   => $bin !== '' ? @is_file($bin) : null,
        'binario_executavel' => $bin !== '' ? @is_executable($bin) : null,
        'disable_functions' => (string)@ini_get('disable_functions'),
        'SMTP_ini'         => (string)@ini_get('SMTP'),
        'smtp_port_ini'    => (string)@ini_get('smtp_port'),
    ];
    foreach (['/usr/sbin/sendmail', '/usr/lib/sendmail', '/usr/sbin/exim', '/usr/sbin/ssmtp'] as $c)
        $out['sendmail']['candidatos'][$c] = @is_file($c) ? (@is_executable($c) ? 'executavel' : 'existe') : 'nao existe';

    /* ---- 4. a conta das cotacoes aponta para outro lugar? ---- */
    $g = @json_decode(@file_get_contents(__DIR__ . '/../data/.email.json'), true);
    $out['conta_cotacoes'] = is_array($g)
        ? ['host' => $g['host'] ?? null, 'port' => $g['port'] ?? null, 'user' => $g['user'] ?? null,
           'tem_senha' => trim((string)($g['senha'] ?? '')) !== '']
        : null;
    $p = ec_conta();
    $out['conta_pedidos'] = ['host' => $p['host'] ?? null, 'port' => $p['port'] ?? null,
                             'user' => $p['user'] ?? null, 'tem_senha' => trim((string)($p['senha'] ?? '')) !== ''];

    /* ---- 5. sai alguma coisa deste servidor? (HTTPS funciona — a fila vem do Supabase) ---- */
    $t0 = microtime(true);
    $ch = curl_init('https://www.google.com/generate_204');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 8, CURLOPT_NOBODY => 1]);
    curl_exec($ch);
    $out['https_saida'] = ['http' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
                           'ms' => (int)round((microtime(true) - $t0) * 1000),
                           'erro' => curl_error($ch) ?: null];
    curl_close($ch);

    /* ---- leitura ---- */
    $mtaOk = array_values(array_filter($out['mta_local'], fn($t) => $t['ok']));
    if ($mtaOk) {
        $out['veredito'] = 'O MTA LOCAL responde em ' . $mtaOk[0]['alvo'] . '. E por ai que o e-mail sai: '
            . 'apontar o host da conta para 127.0.0.1 nessa porta (o bloqueio de saida abre excecao para o proprio servidor).';
    } elseif (!empty($out['sendmail']['mail_existe']) && !empty($out['sendmail']['binario_existe'])) {
        $out['veredito'] = 'Nenhum socket SMTP funciona, mas o sendmail local existe e mail() esta liberada: '
            . 'o caminho e trocar o cliente SMTP por mail(), que fala por pipe e nao abre socket.';
    } else {
        $out['veredito'] = 'Nem socket nem sendmail. So resta liberar a saida SMTP com a hospedagem, '
            . 'ou mandar por API HTTPS (que comprovadamente sai: ver https_saida).';
    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
