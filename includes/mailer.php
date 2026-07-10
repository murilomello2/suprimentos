<?php
/**
 * Cliente SMTP mínimo (implicit SSL, ex.: porta 465) — sem dependências externas (o prod não tem PHPMailer/mbstring).
 * A senha vem do config server-side (data/.email.json), preenchido pelo admin no app. Nunca fica no código.
 */

function smtp_hdr_enc($s) { return '=?UTF-8?B?' . base64_encode((string)$s) . '?='; }

/**
 * Envia UM e-mail. $cfg = {host, port, user, senha, from, from_name}. $attachments = [{nome, mime, conteudo(raw bytes)}].
 * Retorna [bool ok, string msg].
 */
function smtp_send($cfg, $to, $subject, $body, $attachments = []) {
    $host = trim((string)($cfg['host'] ?? '')); $port = (int)($cfg['port'] ?? 465);
    $user = trim((string)($cfg['user'] ?? '')); $pass = (string)($cfg['senha'] ?? '');
    $from = trim((string)($cfg['from'] ?? '')) ?: $user; $fromName = (string)($cfg['from_name'] ?? 'Suprimentos · Caprem');
    if ($host === '' || $user === '' || $pass === '') return [false, 'conta de e-mail não configurada'];
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return [false, 'destinatário inválido: ' . $to];

    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $fp = @stream_socket_client('ssl://' . $host . ':' . $port, $en, $es, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return [false, 'conexão SMTP falhou: ' . $es . ' (' . $en . ')'];
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
    $r = $cmd('DATA'); if (!$is($r, 354)) { fclose($fp); return [false, 'DATA recusado: ' . trim($r)]; }

    $bd = '=_' . bin2hex(random_bytes(9));
    $h  = 'From: ' . smtp_hdr_enc($fromName) . ' <' . $from . ">\r\n";
    $h .= 'To: <' . $to . ">\r\n";
    $h .= 'Subject: ' . smtp_hdr_enc($subject) . "\r\n";
    $h .= "MIME-Version: 1.0\r\n" . 'Date: ' . date('r') . "\r\n";
    if ($attachments) {
        $h .= 'Content-Type: multipart/mixed; boundary="' . $bd . "\"\r\n\r\n";
        $m  = '--' . $bd . "\r\n" . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body)) . "\r\n";
        foreach ($attachments as $a) {
            $nome = preg_replace('/[^A-Za-z0-9 ._-]/', '_', (string)($a['nome'] ?? 'anexo'));
            $m .= '--' . $bd . "\r\n" . 'Content-Type: ' . ($a['mime'] ?? 'application/octet-stream') . '; name="' . $nome . "\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n" . 'Content-Disposition: attachment; filename="' . $nome . "\"\r\n\r\n"
                . chunk_split(base64_encode((string)($a['conteudo'] ?? ''))) . "\r\n";
        }
        $m .= '--' . $bd . "--\r\n";
    } else {
        $h .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $m  = chunk_split(base64_encode($body));
    }
    fwrite($fp, $h . $m . "\r\n.\r\n");   // base64 não tem linha começando com '.', dispensa dot-stuffing
    $r = $read(); $ok = $is($r, 250);
    $cmd('QUIT'); fclose($fp);
    return [$ok, $ok ? 'enviado' : ('servidor recusou o envio: ' . trim($r))];
}
