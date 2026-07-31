<?php
/**
 * Cliente SMTP mínimo (implicit SSL, ex.: porta 465) — sem dependências externas (o prod não tem PHPMailer/mbstring).
 * A senha vem do config server-side (data/.email.json), preenchido pelo admin no app. Nunca fica no código.
 */

function smtp_hdr_enc($s) { return '=?UTF-8?B?' . base64_encode((string)$s) . '?='; }

/**
 * Envia UM e-mail. $cfg = {host, port, user, senha, from, from_name}. $attachments = [{nome, mime, conteudo(raw bytes)}].
 * Retorna [bool ok, string msg]  — ATENCAO: e um par, nao um booleano.
 *
 * $opts: ['html' => true]  -> manda o corpo como text/html (senão o cliente mostra a marcação crua,
 *                             que foi o que aconteceu no primeiro teste de pedido)
 *        ['cc'  => [...]]  -> cópias de verdade (RCPT TO para cada uma + cabeçalho Cc)
 */
function smtp_send($cfg, $to, $subject, $body, $attachments = [], $extraHeaders = [], $opts = []) {
    $host = trim((string)($cfg['host'] ?? '')); $port = (int)($cfg['port'] ?? 465);
    $user = trim((string)($cfg['user'] ?? '')); $pass = (string)($cfg['senha'] ?? '');
    $from = trim((string)($cfg['from'] ?? '')) ?: $user; $fromName = (string)($cfg['from_name'] ?? 'Suprimentos · Caprem');
    if ($host === '' || $user === '' || $pass === '') return [false, 'conta de e-mail não configurada'];
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return [false, 'destinatário inválido: ' . $to];

    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $fp = @stream_socket_client('ssl://' . $host . ':' . $port, $en, $es, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        /* O socket nem chegou a existir, então usuário e senha NÃO foram usados — o erro não pode
           soar como credencial. 110 (ETIMEDOUT) é o pacote saindo e ninguém respondendo: rota de
           saída bloqueada. 111 (ECONNREFUSED) é o outro lado dizendo não. Dizer só "Connection timed
           out (110)" mandou o comprador conferir número de porta, com os números certos, à toa. */
        $dica = '';
        if ((int)$en === 110) {
            $dica = ' — este servidor não alcança ' . $host . ':' . $port . '. Os pacotes saem e ninguém'
                  . ' responde, o que é a rota de saída da hospedagem, não a senha nem a porta.'
                  . ' Use "Testar conexão" em Configurações › E-mail do pedido para ver por onde há saída.';
        } elseif ((int)$en === 111) {
            $dica = ' — ' . $host . ' respondeu recusando a porta ' . $port . '.';
        }
        return [false, 'conexão SMTP falhou: ' . $es . ' (' . $en . ')' . $dica];
    }
    stream_set_timeout($fp, 20);
    $read = function () use ($fp) { $data = ''; while (($line = fgets($fp, 515)) !== false) { $data .= $line; if (strlen($line) < 4 || $line[3] === ' ') break; } return $data; };
    $cmd  = function ($c) use ($fp, $read) { fwrite($fp, $c . "\r\n"); return $read(); };
    $is   = function ($resp, $code) { return substr((string)$resp, 0, 3) === (string)$code; };

    $read(); // banner 220
    $r = $cmd('EHLO caprem-suprimentos'); if (!$is($r, 250)) { $r = $cmd('HELO caprem-suprimentos'); if (!$is($r, 250)) { fclose($fp); return [false, 'EHLO/HELO recusado: ' . trim($r)]; } }
    $r = $cmd('AUTH LOGIN'); if (!$is($r, 334)) { fclose($fp); return [false, 'AUTH LOGIN não suportado: ' . trim($r)]; }
    $r = $cmd(base64_encode($user)); if (!$is($r, 334)) { fclose($fp); return [false, 'usuário recusado: ' . trim($r)]; }
    $r = $cmd(base64_encode($pass)); if (!$is($r, 235)) { fclose($fp); return [false, 'autenticação falhou (senha?): ' . trim($r)]; }
    $r = $cmd('MAIL FROM:<' . $from . '>'); if (!$is($r, 250)) { fclose($fp); return [false, 'MAIL FROM recusado: ' . trim($r)]; }
    $r = $cmd('RCPT TO:<' . $to . '>'); if (!$is($r, 250) && !$is($r, 251)) { fclose($fp); return [false, 'RCPT recusado: ' . trim($r)]; }
    /* Copia so chega se houver RCPT TO para cada endereco — o cabecalho Cc sozinho e decorativo.
       Uma copia recusada NAO derruba o envio principal: perder o pedido por causa de um e-mail
       interno errado seria trocar a regra 4 por um detalhe. */
    $cc = [];
    foreach ((array)($opts['cc'] ?? []) as $e) {
        $e = trim((string)$e);
        if ($e === '' || !filter_var($e, FILTER_VALIDATE_EMAIL) || strcasecmp($e, $to) === 0) continue;
        $rc = $cmd('RCPT TO:<' . $e . '>');
        if ($is($rc, 250) || $is($rc, 251)) $cc[] = $e;
    }
    $r = $cmd('DATA'); if (!$is($r, 354)) { fclose($fp); return [false, 'DATA recusado: ' . trim($r)]; }

    $bd = '=_' . bin2hex(random_bytes(9));
    $h  = 'From: ' . smtp_hdr_enc($fromName) . ' <' . $from . ">\r\n";
    $h .= 'To: <' . $to . ">\r\n";
    if ($cc) $h .= 'Cc: ' . implode(', ', array_map(function ($e) { return '<' . $e . '>'; }, $cc)) . "\r\n";
    $h .= 'Subject: ' . smtp_hdr_enc($subject) . "\r\n";
    $h .= "MIME-Version: 1.0\r\n" . 'Date: ' . date('r') . "\r\n";
    // headers extras (ex.: Message-ID p/ casar a resposta via In-Reply-To/References). Sanitiza CRLF (anti header-injection).
    foreach ((array)$extraHeaders as $hk => $hv) {
        $hk = trim(str_replace(["\r", "\n"], '', (string)$hk)); $hv = trim(str_replace(["\r", "\n"], '', (string)$hv));
        if ($hk !== '' && $hv !== '') $h .= $hk . ': ' . $hv . "\r\n";
    }
    /* O corpo do pedido e HTML. Sem isto o cliente mostra a marcacao crua — foi o que
       aconteceu no primeiro teste que chegou na caixa do Murilo. */
    $tipo = !empty($opts['html']) ? 'text/html' : 'text/plain';
    if ($attachments) {
        $h .= 'Content-Type: multipart/mixed; boundary="' . $bd . "\"\r\n\r\n";
        $m  = '--' . $bd . "\r\n" . 'Content-Type: ' . $tipo . "; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body)) . "\r\n";
        foreach ($attachments as $a) {
            $nome = preg_replace('/[^A-Za-z0-9 ._-]/', '_', (string)($a['nome'] ?? 'anexo'));
            $m .= '--' . $bd . "\r\n" . 'Content-Type: ' . ($a['mime'] ?? 'application/octet-stream') . '; name="' . $nome . "\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n" . 'Content-Disposition: attachment; filename="' . $nome . "\"\r\n\r\n"
                . chunk_split(base64_encode((string)($a['conteudo'] ?? ''))) . "\r\n";
        }
        $m .= '--' . $bd . "--\r\n";
    } else {
        $h .= 'Content-Type: ' . $tipo . "; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $m  = chunk_split(base64_encode($body));
    }
    fwrite($fp, $h . $m . "\r\n.\r\n");   // base64 não tem linha começando com '.', dispensa dot-stuffing
    $r = $read(); $ok = $is($r, 250);
    $cmd('QUIT'); fclose($fp);
    return [$ok, $ok ? 'enviado' : ('servidor recusou o envio: ' . trim($r))];
}
