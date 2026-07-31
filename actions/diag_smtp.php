<?php
/**
 * DIAGNÓSTICO DE SAÍDA SMTP — por que o envio de pedido morre em "Connection timed out (110)".
 *
 * O erro 110 é ETIMEDOUT: o SYN saiu e ninguém respondeu. Isso NÃO é senha errada (daria "autenticação
 * falhou") nem porta fechada (daria "Connection refused", 111). Sobram poucas causas, e daqui do meu
 * lado não dá para distinguir nenhuma delas — todas dependem da rota de saída DO SERVIDOR. Então este
 * endpoint roda lá e mede.
 *
 * O que ele faz: resolve o nome, tenta abrir o socket em várias combinações de host/porta/transporte,
 * cronometra cada uma e lê o banner. O que ele NÃO faz: autenticar, enviar, ou imprimir a senha —
 * um diagnóstico que vaza credencial é pior do que o defeito que investiga.
 *
 * Suspeita principal: includes/mailer.php linha 25 fixa 'ssl://', ou seja, só fala SSL IMPLÍCITO
 * (porta 465). Se a conta estiver configurada na 587, que é o que quase todo provedor mostra como
 * "SMTP", o cliente começa o handshake TLS enquanto o servidor espera um comando em texto puro:
 * ninguém fala, e os 20s estouram como timeout. Os "números certos" do cliente de e-mail seriam,
 * nesse caso, exatamente o que quebra aqui.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
define('EC_LIB_ONLY', 1); require_once __DIR__ . '/envio_config.php';

@set_time_limit(180);

$TO = 6;   // segundos por tentativa. Curto de propósito: o que funciona responde em milissegundos.

/** Abre, cronometra e lê o banner. Devolve o que aconteceu, sem nunca falar quem somos. */
function d_tentar($transporte, $host, $porta, $timeout) {
    $t0 = microtime(true);
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $en = 0; $es = '';
    $fp = @stream_socket_client("$transporte://$host:$porta", $en, $es, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    $ms = (int)round((microtime(true) - $t0) * 1000);
    if (!$fp) return ['ok' => false, 'ms' => $ms, 'errno' => $en, 'erro' => trim((string)$es)];
    stream_set_timeout($fp, $timeout);
    $banner = @fgets($fp, 512);
    $meta = stream_get_meta_data($fp);
    @fwrite($fp, "QUIT\r\n"); @fclose($fp);
    return ['ok' => true, 'ms' => (int)round((microtime(true) - $t0) * 1000),
            'banner' => trim((string)$banner) !== '' ? substr(trim($banner), 0, 120) : null,
            'banner_timeout' => !empty($meta['timed_out'])];
}

try {
    $pdo = db();
    $perms = user_perms($pdo, $_GET['me'] ?? null);
    if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error' => 'Apenas administradores.']); exit; }

    $conta = ec_conta_efetiva();
    $host  = trim((string)($conta['host'] ?? '')) ?: 'mail.caprem.com.br';
    $porta = (int)($conta['port'] ?? 465);
    $user  = trim((string)($conta['user'] ?? ''));
    $dom   = strpos($user, '@') !== false ? substr($user, strpos($user, '@') + 1) : '';

    $out = ['conta' => ['fonte' => $conta['fonte'] ?? '', 'user' => $user,
                        'host_configurado' => $host, 'porta_configurada' => $porta,
                        'imap_port' => (int)($conta['imap_port'] ?? 0),
                        'tem_senha' => trim((string)($conta['senha'] ?? '')) !== '']];

    /* ---- 1. o nome resolve? e para quê? IPv6 sem rota é causa clássica de ETIMEDOUT ---- */
    $ipv4 = @gethostbyname($host);
    $out['dns'] = ['host' => $host, 'ipv4' => ($ipv4 !== $host ? $ipv4 : null)];
    foreach (['AAAA' => DNS_AAAA, 'MX' => DNS_MX] as $tipo => $const) {
        $alvo = $tipo === 'MX' ? ($dom ?: $host) : $host;
        $r = @dns_get_record($alvo, $const);
        $out['dns'][strtolower($tipo)] = $r ? array_map(function ($x) use ($tipo) {
            return $tipo === 'MX' ? ($x['target'] . ' (' . $x['pri'] . ')') : ($x['ipv6'] ?? '');
        }, $r) : [];
    }

    /* ---- 2. a matriz. Cada linha responde a UMA pergunta ---- */
    $matriz = [
        ['tcp', $host, $porta,  'a porta configurada aceita TCP? (separa rede de TLS)'],
        ['ssl', $host, $porta,  'exatamente o que o mailer faz hoje'],
        ['ssl', $host, 465,     'SSL implicito, o unico dialeto que o mailer fala'],
        ['tcp', $host, 587,     'STARTTLS: o banner chega em texto puro antes do TLS'],
        ['tcp', $host, 25,      'SMTP puro — quase sempre bloqueado em hospedagem'],
    ];
    // o MX do domínio costuma ser o host de verdade quando "mail.dominio" é só um apelido do painel
    $mx = $out['dns']['mx'][0] ?? '';
    if ($mx && ($mxh = trim(explode(' ', $mx)[0])) && strcasecmp($mxh, $host) !== 0) {
        $matriz[] = ['ssl', $mxh, 465, 'o MX do dominio, caso o host configurado seja so um apelido'];
        $matriz[] = ['tcp', $mxh, 587, 'o MX do dominio em STARTTLS'];
    }
    /* Referência externa: se NADA sai, é bloqueio de saída da hospedagem, e nenhum ajuste de host
       resolve. Se isto conecta e o host da Caprem não, o problema é do outro lado. */
    $matriz[] = ['ssl', 'smtp.gmail.com', 465, 'referencia: a hospedagem deixa sair na 465?'];
    $matriz[] = ['tcp', 'smtp.gmail.com', 587, 'referencia: a hospedagem deixa sair na 587?'];

    $out['testes'] = [];
    foreach ($matriz as [$tr, $h, $p, $pq]) {
        $r = d_tentar($tr, $h, $p, $TO);
        $out['testes'][] = ['alvo' => "$tr://$h:$p", 'pergunta' => $pq] + $r;
    }

    /* ---- 3. leitura ---- */
    $achou = function ($alvo) use ($out) {
        foreach ($out['testes'] as $t) if ($t['alvo'] === $alvo) return $t; return null;
    };
    $ok = array_values(array_filter($out['testes'], fn($t) => $t['ok']));
    $refExterna = array_filter($out['testes'], fn($t) => strpos($t['alvo'], 'gmail') !== false && $t['ok']);
    $doHost     = array_filter($out['testes'], fn($t) => strpos($t['alvo'], 'gmail') === false && $t['ok']);

    if (!$ok) {
        $v = 'NADA sai deste servidor — nem para o Gmail. A hospedagem bloqueia SMTP de saida; '
           . 'nenhum ajuste de host ou porta resolve, e o caminho passa a ser liberar a saida com o provedor.';
    } elseif (!$doHost && $refExterna) {
        $v = 'A saida SMTP funciona (o Gmail respondeu), mas NENHUMA porta de ' . $host . ' responde. '
           . 'O host esta errado, ou o servidor de e-mail da Caprem esta bloqueando o IP desta hospedagem.';
    } else {
        $v = 'Alguma combinacao conecta. Ver "recomendado" — se for porta 587, o mailer precisa aprender STARTTLS.';
    }
    $out['veredito'] = $v;

    /* Qual combinação usar. Preferência por 465, que é o que o mailer já fala hoje. */
    $rec = null;
    foreach ($out['testes'] as $t) {
        if (!$t['ok'] || strpos($t['alvo'], 'gmail') !== false) continue;
        if (strpos($t['alvo'], 'ssl://') === 0) { $rec = ['alvo' => $t['alvo'], 'muda_mailer' => false,
            'nota' => 'SSL implicito — o mailer ja fala isso, basta apontar host/porta.']; break; }
    }
    if (!$rec) foreach ($out['testes'] as $t) {
        if (!$t['ok'] || strpos($t['alvo'], 'gmail') !== false || strpos($t['alvo'], 'tcp://') !== 0) continue;
        $rec = ['alvo' => $t['alvo'], 'muda_mailer' => true,
                'nota' => 'So responde em texto puro: o mailer precisa de STARTTLS (hoje ele fixa ssl:// na linha 25).'];
        break;
    }
    $out['recomendado'] = $rec;

    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
