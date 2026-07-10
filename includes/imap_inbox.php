<?php
/**
 * LEITOR IMAP (Fase 4 — ler respostas dos fornecedores). PURO IMAP: sem IA, sem SQL, sem mb_*.
 * READ-ONLY (OP_READONLY + FT_PEEK em todo fetch): NUNCA marca \Seen, nunca apaga/responde —
 * a caixa suprimentos@ é lida por humanos no webmail e não pode ser bagunçada.
 * Credenciais vêm do MESMO data/.email.json do envio (host/user/senha) + imap_port (993).
 * O prod NÃO tem mbstring: decodificação de header via imap_mime_header_decode + imap_utf8;
 * corpo por base64/quoted-printable + iconv-se-existir; cortes por substr() byte-based.
 * Prefixo inbox_ nas funções p/ nunca colidir com as funções nativas imap_*.
 */

function inbox_ext_ok() { return extension_loaded('imap'); }

function inbox_mailbox_str($cfg, $folder = 'INBOX') {
    $host = trim((string)($cfg['host'] ?? 'mail.capremconstrutora.com.br'));
    $port = (int)($cfg['imap_port'] ?? 993);                       // IMAP tem porta PRÓPRIA (993) — não a do SMTP (465)
    return '{' . $host . ':' . $port . '/imap/ssl/novalidate-cert}' . $folder;   // cert self-signed no prod
}

/** -> [resource|IMAP\Connection $mbox, ''] em sucesso; [null, string $erro] em falha. */
function inbox_conectar($cfg, $folder = 'INBOX') {
    if (!inbox_ext_ok()) return [null, 'a extensão imap do PHP não está disponível no servidor'];
    $user = trim((string)($cfg['user'] ?? '')); $pass = (string)($cfg['senha'] ?? '');
    if ($user === '' || $pass === '') return [null, 'conta de e-mail não configurada (falta usuário/senha)'];
    if (function_exists('imap_timeout')) { @imap_timeout(IMAP_OPENTIMEOUT, 15); @imap_timeout(IMAP_READTIMEOUT, 20); }
    // OP_READONLY: nunca altera flags. retries=0: não insiste 3x em falha (lento). Desliga GSSAPI/NTLM -> vai ao AUTH LOGIN.
    $mbox = @imap_open(inbox_mailbox_str($cfg, $folder), $user, $pass, OP_READONLY, 0,
                       ['DISABLE_AUTHENTICATOR' => ['GSSAPI', 'NTLM']]);
    $errs = imap_errors(); imap_alerts();                          // DRENA a fila global (senão vaza p/ a próxima chamada)
    if (!$mbox) return [null, 'IMAP falhou: ' . ($errs ? implode('; ', array_slice($errs, 0, 3)) : 'conexão/login recusado')];
    return [$mbox, ''];
}

function inbox_fechar($mbox) { if ($mbox) { @imap_close($mbox); imap_errors(); imap_alerts(); } }

function inbox_uidvalidity($mbox, $cfg, $folder = 'INBOX') {
    $st = @imap_status($mbox, inbox_mailbox_str($cfg, $folder), SA_UIDVALIDITY); imap_errors();
    return ($st && isset($st->uidvalidity)) ? (int)$st->uidvalidity : 0;
}

/**
 * UIDs a processar, do MAIS ANTIGO p/ o mais novo (ASC) — assim o high-water mark de UID avança sem deixar buracos.
 * Se $lastUid>0: busca por FAIXA de UID (lastUid+1:*) — pega só o que chegou depois, sem reprocessar (e sem perder o excedente).
 * Se $lastUid==0 (1ª vez / uidvalidity mudou): busca por data (SINCE $desde).
 * Devolve [uids_fatiados_asc, total_disponivel] — total>contagem sinaliza backlog p/ o chamador avisar.
 */
function inbox_buscar_novos($mbox, $desde, $lastUid = 0, $max = 40) {
    if ((int)$lastUid > 0) {
        $uids = @imap_search($mbox, 'UID ' . ((int)$lastUid + 1) . ':*', SE_UID); imap_errors();
        // o '*' do IMAP casa a MAIOR UID mesmo quando lastUid+1 > todas — filtra o falso positivo
        $uids = array_values(array_filter((array)$uids, fn($u) => (int)$u > (int)$lastUid));
    } else {
        $ts = strtotime((string)$desde); if (!$ts) $ts = strtotime('-14 days');
        $uids = @imap_search($mbox, 'SINCE "' . date('j-M-Y', $ts) . '"', SE_UID); imap_errors();   // formato IMAP inglês: 1-Jul-2026
        $uids = array_values(array_filter((array)$uids, fn($u) => (int)$u > 0));   // descarta UIDs vazias/0 (quirk de algumas pastas)
    }
    if (!$uids) return [[], 0];
    sort($uids, SORT_NUMERIC);                                     // ASC: processa do mais antigo (high-water monotônico)
    $total = count($uids);
    return [array_slice($uids, 0, max(1, (int)$max)), $total];
}

/** Header MIME (=?UTF-8?B?..?=) -> UTF-8, SEM mb_*. */
function inbox_hdr_decode($raw) {
    $raw = (string)$raw; if ($raw === '') return '';
    $out = '';
    foreach ((array)@imap_mime_header_decode($raw) as $p) {
        $cs = strtolower((string)($p->charset ?? 'default'));
        $out .= in_array($cs, ['default', 'utf-8', 'us-ascii', 'ascii', ''], true)
              ? (string)$p->text : inbox_to_utf8((string)$p->text, $cs);
    }
    return trim($out);
}

function inbox_to_utf8($s, $charset) {
    $charset = strtolower(trim((string)$charset));
    if ($s === '' || in_array($charset, ['utf-8', 'utf8', 'us-ascii', 'ascii', ''], true)) return $s;
    // CRÍTICO: se já é UTF-8 válido, o charset declarado está errado (Gmail às vezes rotula iso-8859-1 mas manda UTF-8).
    // Converter iso-8859-1->UTF-8 aqui DUPLICA a codificação (ç -> Ã§). Então: já-UTF-8 => devolve como está.
    if (preg_match('//u', $s)) return $s;
    if (function_exists('iconv')) { $r = @iconv($charset, 'UTF-8//TRANSLIT', $s); if ($r !== false && $r !== '') return $r; }
    $r = @imap_utf8($s);                                            // cobre iso-8859-1/latin1 (o caso BR) quando NÃO é UTF-8
    return ($r !== false && $r !== '') ? $r : $s;
}

/** 3=base64, 4=quoted-printable; 0/1/2 = 7bit/8bit/binary (inalterado). */
function inbox_decode_part($bytes, $enc) {
    if ((int)$enc === 3) return imap_base64($bytes);
    if ((int)$enc === 4) return imap_qprint($bytes);
    return $bytes;
}

/** Corpo texto: prefere text/plain; se só houver text/html, strip. FT_PEEK sempre. Cap p/ msg gigante. */
function inbox_body_text($mbox, $uid, $struct, $cap = 40000) {
    $plain = ''; $html = '';
    $walk = function ($st, $sec) use (&$walk, $mbox, $uid, &$plain, &$html) {
        if (!empty($st->parts) && is_array($st->parts)) {
            foreach ($st->parts as $i => $sub) $walk($sub, $sec === '' ? (string)($i + 1) : $sec . '.' . ($i + 1));
            return;
        }
        $sec = ($sec === '') ? '1' : $sec;
        $isAttach = (!empty($st->ifdisposition) && strtolower((string)$st->disposition) === 'attachment');
        if ((int)($st->type ?? 0) !== 0 || $isAttach) return;      // 0 = TEXT
        $raw = @imap_fetchbody($mbox, $uid, $sec, FT_UID | FT_PEEK); imap_errors();
        $dec = inbox_decode_part($raw, $st->encoding ?? 0);
        $cs = 'utf-8'; foreach (($st->parameters ?? []) as $pp) if (strtolower((string)$pp->attribute) === 'charset') $cs = $pp->value;
        $dec = inbox_to_utf8($dec, $cs);
        if (strtoupper((string)($st->subtype ?? '')) === 'HTML') $html .= $dec; else $plain .= $dec . "\n";
    };
    $walk($struct, '');
    $txt = trim($plain);
    if ($txt === '' && $html !== '') {
        $h = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $html);
        $h = preg_replace('#<br\s*/?>#i', "\n", $h); $h = preg_replace('#</p>#i', "\n", $h);
        $txt = trim(html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    return strlen($txt) > $cap ? substr($txt, 0, $cap) : $txt;
}

/** Bytes de um anexo (section path), FT_PEEK, decodificado. '' se falhar/>25MB. */
function inbox_anexo_bytes($mbox, $uid, $section, $encoding, $max = 26214400) {
    $raw = @imap_fetchbody($mbox, $uid, $section, FT_UID | FT_PEEK); imap_errors();
    if ($raw === false || $raw === '') return '';
    $b = inbox_decode_part($raw, $encoding);
    return (strlen($b) > $max || $b === '') ? '' : $b;
}

/** PARSE completo por UID. NÃO altera flags. Devolve array com headers + corpo + anexos[{nome,bytes}]. */
function inbox_parse_msg($mbox, $uid, $cap = 40000) {
    $rawh = @imap_fetchheader($mbox, $uid, FT_UID); imap_errors();  // header NÃO marca \Seen
    $hdr = @imap_rfc822_parse_headers($rawh);
    $fe = ''; $fn = '';
    if ($hdr && !empty($hdr->from) && is_array($hdr->from)) {
        $a = $hdr->from[0];
        $fe = strtolower(trim(((string)($a->mailbox ?? '')) . '@' . ((string)($a->host ?? ''))));
        if ($fe === '@') $fe = '';
        $fn = isset($a->personal) ? inbox_hdr_decode($a->personal) : '';
    }
    $irt = trim((string)($hdr->in_reply_to ?? '')); if ($irt !== '' && preg_match('/<[^>]+>/', $irt, $m)) $irt = $m[0];
    $refs = []; if ($hdr && !empty($hdr->references)) { preg_match_all('/<[^>]+>/', (string)$hdr->references, $mm); $refs = $mm[0]; }
    $struct = @imap_fetchstructure($mbox, $uid, FT_UID); imap_errors();
    $anexos = [];
    $collect = function ($st, $sec) use (&$collect, $mbox, $uid, &$anexos) {
        if (!empty($st->parts) && is_array($st->parts)) {
            foreach ($st->parts as $i => $sub) $collect($sub, $sec === '' ? (string)($i + 1) : $sec . '.' . ($i + 1));
            return;
        }
        $sec = ($sec === '') ? '1' : $sec;
        $nome = '';
        foreach (($st->dparameters ?? []) as $p) if (strtolower((string)$p->attribute) === 'filename') $nome = inbox_hdr_decode($p->value);
        if ($nome === '') foreach (($st->parameters ?? []) as $p) if (strtolower((string)$p->attribute) === 'name') $nome = inbox_hdr_decode($p->value);
        $isAttach = (!empty($st->ifdisposition) && strtolower((string)$st->disposition) === 'attachment');
        $type = (int)($st->type ?? 0);                             // 3=application, 5=image
        if (!$isAttach && $nome === '' && $type !== 3 && $type !== 5) return;   // não é anexo
        $bytes = inbox_anexo_bytes($mbox, $uid, $sec, $st->encoding ?? 0);
        if ($bytes === '') return;                                 // vazio/>25MB -> ignora
        $anexos[] = ['nome' => $nome !== '' ? $nome : 'anexo', 'bytes' => $bytes];
    };
    if ($struct) $collect($struct, '');
    return [
        'uid' => (int)$uid,
        'from_email' => $fe, 'from_nome' => $fn,
        'subject' => inbox_hdr_decode($hdr->subject ?? ''),
        'message_id' => trim((string)($hdr->message_id ?? '')),
        'in_reply_to' => $irt, 'references' => $refs,
        'recebido_em' => (!empty($hdr->date) && strtotime((string)$hdr->date)) ? date('c', strtotime((string)$hdr->date)) : date('c'),
        'corpo' => $struct ? inbox_body_text($mbox, $uid, $struct, $cap) : '',
        'anexos' => $anexos,
    ];
}
