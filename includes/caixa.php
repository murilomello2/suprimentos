<?php
/**
 * CAIXA DE E-MAIL do suprimentos@ — índice da caixa real, nas duas direções.
 *
 * Por que indexar em vez de ler o IMAP a cada tela: filtrar por destinatário, período e anexo
 * direto no IMAP é lento e frágil (cada filtro vira um SEARCH remoto). Aqui o cabeçalho fica no
 * banco — a lista responde na hora — e só o CORPO e os ANEXOS são buscados no IMAP quando alguém
 * abre a mensagem, que é como qualquer cliente de e-mail funciona.
 *
 * Só-leitura por construção: tudo passa pelo includes/imap_inbox.php, que abre a caixa em
 * OP_READONLY e usa FT_PEEK em todo fetch. Não existe caminho daqui que apague, mova ou marque
 * mensagem como lida — a caixa é lida por gente no webmail e não pode ser bagunçada por nós.
 */

require_once __DIR__ . '/imap_inbox.php';

define('CAIXA_PREVIEW', 1200);          // prévia guardada por mensagem (o corpo inteiro vem na hora de abrir)
define('CAIXA_MAX_SYNC', 60);           // mensagens por pasta por varredura — mantém a requisição curta
define('CAIXA_JANELA', '-90 days');     // 1ª varredura de uma pasta: até onde voltar

function caixa_cfg() {
    $j = @json_decode(@file_get_contents(__DIR__ . '/../data/.email.json'), true);
    return is_array($j) ? $j : [];
}

function caixa_meta_get($pdo, $k, $def = null) {
    try { $q = $pdo->prepare("SELECT v FROM meta WHERE k=?"); $q->execute([$k]); $v = $q->fetchColumn();
          return $v === false ? $def : $v; } catch (Throwable $e) { return $def; }
}
function caixa_meta_set($pdo, $k, $v) {
    try { $u = $pdo->prepare("UPDATE meta SET v=? WHERE k=?"); $u->execute([(string)$v, $k]);
          if ($u->rowCount() === 0) $pdo->prepare("INSERT INTO meta (k,v) VALUES (?,?)")->execute([$k, (string)$v]);
    } catch (Throwable $e) {}
}

/** Message-ID próprio, para conseguirmos reconhecer depois, na caixa, o que saiu do cockpit.
    Sem isto o servidor de e-mail gera um id que nunca vemos, e a mensagem fica indistinguível
    de uma escrita à mão no webmail — some a resposta de "quem disparou". */
function caixa_msgid($cfg = null) {
    $cfg = $cfg ?: caixa_cfg();
    $host = 'capremconstrutora.com.br';
    if (!empty($cfg['user']) && strpos((string)$cfg['user'], '@') !== false) $host = substr((string)$cfg['user'], strpos((string)$cfg['user'], '@') + 1);
    return '<' . bin2hex(random_bytes(12)) . '.' . time() . '@' . $host . '>';
}

/** Registra, NO ATO DO ENVIO, o que o cockpit disparou. O IMAP guarda a mensagem; só isto guarda
    a pessoa. Casa depois por Message-ID. Nunca deixa o envio falhar por causa do log. */
function caixa_log_saida($pdo, $msgid, $tipo, $refValor, $bitrixId, $quem, $assunto, $para, $cotacaoId = null) {
    try {
        $pdo->prepare("INSERT INTO caixa_saida (message_id,tipo,ref_valor,cotacao_id,assunto,para,bitrix_id,quem,enviado_em)
                       VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([(string)$msgid, (string)$tipo, (string)$refValor, $cotacaoId ? (int)$cotacaoId : null,
                       (string)$assunto, is_array($para) ? implode(', ', $para) : (string)$para,
                       (string)$bitrixId, (string)$quem, date('c')]);
    } catch (Throwable $e) {}
}

/**
 * Arquiva na pasta Enviados uma cópia do que acabamos de mandar.
 *
 * Enviar por SMTP não põe cópia em lugar nenhum — quem põe é o cliente de e-mail. Como o cockpit
 * fala SMTP direto, tudo que ele mandou até hoje simplesmente não existe na pasta Enviados da
 * conta: nem aqui, nem no webmail que o time abre. Medido em 05/08/2026: INBOX.Sent com 0
 * mensagens numa conta que dispara carta de cotação e pedido de compra.
 *
 * Esta é a ÚNICA escrita que o cockpit faz na caixa, e ela só ACRESCENTA. Continua não havendo
 * caminho que apague, mova ou marque mensagem existente. Falhar aqui nunca pode derrubar o envio:
 * o e-mail já saiu, e perder a cópia é bem menos grave do que dizer ao comprador que não enviou.
 */
function caixa_arquivar_enviado($cfg, $raw) {
    if (!$raw || !inbox_ext_ok()) return false;
    $user = trim((string)($cfg['user'] ?? '')); $pass = (string)($cfg['senha'] ?? '');
    if ($user === '' || $pass === '') return false;
    $mbox = null;
    try {
        // conexão PRÓPRIA e sem OP_READONLY: a de leitura é read-only de propósito e não serve para append
        $mbox = @imap_open(inbox_mailbox_str($cfg, 'INBOX'), $user, $pass, 0, 0,
                           ['DISABLE_AUTHENTICATOR' => ['GSSAPI', 'NTLM']]);
        imap_errors(); imap_alerts();
        if (!$mbox) return false;
        $pasta = caixa_pasta_enviados($mbox, $cfg);
        if (!$pasta) return false;
        $ok = @imap_append($mbox, inbox_mailbox_str($cfg, $pasta), str_replace("\n", "\r\n", str_replace("\r\n", "\n", (string)$raw)), "\\Seen");
        imap_errors(); imap_alerts();
        return (bool)$ok;
    } catch (Throwable $e) { return false; }
    finally { if ($mbox) { @imap_close($mbox); imap_errors(); imap_alerts(); } }
}

/** A pasta de enviados muda de nome conforme o servidor (Sent, INBOX.Sent, "Sent Items",
    "Enviados"...). Descobre pela lista real em vez de chutar — chutar erra e a tela fica vazia
    sem explicar por quê. Prefere a que o servidor marca com \Sent (RFC 6154), quando existe. */
function caixa_pasta_enviados($mbox, $cfg) {
    $ref = '{' . trim((string)($cfg['host'] ?? 'mail.capremconstrutora.com.br')) . ':' . (int)($cfg['imap_port'] ?? 993) . '/imap/ssl/novalidate-cert}';
    $list = @imap_getmailboxes($mbox, $ref, '*'); imap_errors();
    $cands = [];
    foreach ((array)$list as $mb) {
        $nome = str_replace($ref, '', (string)($mb->name ?? ''));
        if ($nome === '') continue;
        if (!empty($mb->attributes) && ((int)$mb->attributes & LATT_NOSELECT)) continue;
        $cands[] = $nome;
    }
    foreach ($cands as $n) if (preg_match('/(^|[.\/])sent[ _-]?(items|mail)?$/i', $n)) return $n;
    foreach ($cands as $n) if (preg_match('/(^|[.\/])(enviad[oa]s?|itens enviados)$/i', $n)) return $n;
    foreach ($cands as $n) if (stripos($n, 'sent') !== false || stripos($n, 'enviad') !== false) return $n;
    return null;
}

/** Pastas de ENTRADA: INBOX + qualquer spam/lixo — resposta de fornecedor cai lá com frequência
    (o inbox.php da cotação já aprendeu isso na marra). */
function caixa_pastas_entrada($mbox, $cfg) {
    $ref = '{' . trim((string)($cfg['host'] ?? 'mail.capremconstrutora.com.br')) . ':' . (int)($cfg['imap_port'] ?? 993) . '/imap/ssl/novalidate-cert}';
    $out = ['INBOX'];
    $list = @imap_list($mbox, $ref, '*'); imap_errors();
    if (is_array($list)) foreach ($list as $mb) {
        $n = str_replace($ref, '', (string)$mb);
        if ($n !== '' && strcasecmp($n, 'INBOX') !== 0 && preg_match('/(junk|spam|lixo|bulk)/i', $n)) $out[] = $n;
    }
    return array_values(array_unique($out));
}

function caixa_pessoas_txt($lista) {
    $p = [];
    foreach ((array)$lista as $x) {
        $nm = trim((string)($x['nome'] ?? '')); $em = trim((string)($x['email'] ?? ''));
        if ($em === '') continue;
        $p[] = $nm !== '' ? ($nm . ' <' . $em . '>') : $em;
    }
    return implode(', ', $p);
}

/**
 * Varre UMA pasta e indexa o que ainda não está no banco. Marca de UID por pasta (high-water),
 * igual ao inbox da cotação: a faixa UID n+1:* pega só o que chegou depois, sem reprocessar.
 * Devolve [novas, total_disponivel, erro].
 */
function caixa_sync_pasta($pdo, $cfg, $mbox, $pasta, $direcao, $max = CAIXA_MAX_SYNC) {
    if (!@imap_reopen($mbox, inbox_mailbox_str($cfg, $pasta))) { imap_errors(); return [0, 0, 'não consegui abrir a pasta ' . $pasta, 0]; }
    imap_errors();
    $naPasta = (int)@imap_num_msg($mbox); imap_errors();   // quantas a pasta TEM (≠ quantas são novas p/ nós)
    $uv   = inbox_uidvalidity($mbox, $cfg, $pasta);
    $kUv  = 'caixa_uv_' . md5($pasta);
    $kUid = 'caixa_uid_' . md5($pasta);
    $uvAnt = (int)caixa_meta_get($pdo, $kUv, 0);
    $last  = (int)caixa_meta_get($pdo, $kUid, 0);
    // uidvalidity mudou = o servidor renumerou tudo; as UIDs guardadas não valem mais nada
    if ($uv !== $uvAnt) { $last = 0; caixa_meta_set($pdo, $kUv, $uv); }

    $marcaAntes = $last;
    [$uids, $total] = inbox_buscar_novos($mbox, date('c', strtotime(CAIXA_JANELA)), $last, $max);

    /* VARREDURA DE RECUPERAÇÃO — a marca de UID é só ATALHO; quem garante que nada duplica é a
       dedup_key. Enquanto a marca era a única fonte da verdade, qualquer mensagem que ficasse
       para trás (fetch que falhou, timeout no meio do lote, pasta renumerada) sumia PARA SEMPRE:
       a marca já tinha passado por cima dela e a faixa UID marca+1:* nunca mais a alcançava.
       Foi exatamente o que aconteceu — medido em 14/08/2026: a caixa parou em 04/08 enquanto o
       INBOX tinha mensagens de 11 a 14/08 que o inbound da cotação lia normalmente.
       Então: quando a faixa por UID não traz NADA novo, varre a janela por DATA e deixa a
       dedup_key filtrar. Custa um SEARCH e um SELECT indexado por mensagem já conhecida. */
    $recuperando = false;
    if (!$uids && $naPasta > 0) {
        [$uids, $total] = inbox_buscar_novos($mbox, date('c', strtotime(CAIXA_JANELA)), 0, $max, true);
        $recuperando = true;
    }
    if (!$uids) return [0, 0, '', $naPasta, ['marca' => $marcaAntes, 'vistas' => 0]];

    $ja  = $pdo->prepare("SELECT id FROM caixa_msg WHERE dedup_key=? LIMIT 1");
    $ins = $pdo->prepare("INSERT INTO caixa_msg
        (direcao,pasta,imap_uid,uidvalidity,dedup_key,message_id,in_reply_to,de_email,de_nome,para,cc,
         assunto,data_email,tem_anexo,anexos_nomes,preview,origem,cotacao_id,ref_tipo,ref_valor,disparado_por,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    // quem disparou: só o cockpit sabe. Casa pelo Message-ID que nós mesmos geramos no envio.
    $saida = $pdo->prepare("SELECT tipo, ref_valor, cotacao_id, quem FROM caixa_saida WHERE message_id=? LIMIT 1");
    $novas = 0; $conhecidas = 0; $falhas = 0; $travou = false;
    /* A marca só avança enquanto NADA falhou neste lote. Antes ela avançava para a maior UID
       processada com sucesso — então uma mensagem que falhava no meio ficava para trás da marca e
       nunca mais era buscada. Parando de avançar na primeira falha, a próxima varredura recomeça
       exatamente onde o buraco começou. (A varredura de recuperação acima é a segunda rede.) */
    foreach ($uids as $uid) {
        $uid = (int)$uid;
        $dedup = $direcao . ':' . $uv . ':' . $uid . ':' . md5($pasta);
        $ja->execute([$dedup]);
        if ($ja->fetchColumn()) { $conhecidas++; if (!$travou && $uid > $last) $last = $uid; continue; }
        try { $m = inbox_parse_msg($mbox, $uid, CAIXA_PREVIEW); } catch (Throwable $e) { $m = null; }
        if (!$m) { $falhas++; $travou = true; continue; }

        // só anexo DE VERDADE conta para o clipe: imagem embutida de assinatura não é anexo
        $anexNomes = [];
        foreach ((array)($m['anexos'] ?? []) as $a) if (empty($a['inline'])) $anexNomes[] = (string)($a['nome'] ?? 'anexo');

        $origem = ''; $cotId = null; $refTipo = ''; $refVal = ''; $quem = '';
        if ($direcao === 'out') {
            $origem = 'webmail';                     // até prova em contrário: saiu da conta, não do cockpit
            $mid = trim((string)($m['message_id'] ?? ''));
            if ($mid !== '') {
                $saida->execute([$mid]);
                if ($s = $saida->fetch()) {
                    $origem = 'cockpit'; $refTipo = (string)$s['tipo']; $refVal = (string)$s['ref_valor'];
                    $cotId = $s['cotacao_id'] ? (int)$s['cotacao_id'] : null; $quem = (string)$s['quem'];
                }
            }
        }
        $ins->execute([$direcao, $pasta, $uid, $uv, $dedup,
            substr((string)($m['message_id'] ?? ''), 0, 190), substr((string)($m['in_reply_to'] ?? ''), 0, 190),
            (string)($m['from_email'] ?? ''), substr((string)($m['from_nome'] ?? ''), 0, 190),
            caixa_pessoas_txt($m['to'] ?? []), caixa_pessoas_txt($m['cc'] ?? []),
            substr((string)($m['subject'] ?? ''), 0, 490), (string)($m['recebido_em'] ?? date('c')),
            count($anexNomes) ? 1 : 0, implode(' | ', $anexNomes),
            substr((string)($m['corpo'] ?? ''), 0, CAIXA_PREVIEW),
            $origem, $cotId, $refTipo, $refVal, $quem, date('c')]);
        $novas++;
        if (!$travou && $uid > $last) $last = $uid;
    }
    caixa_meta_set($pdo, $kUid, $last);
    // o diagnóstico volta junto: "0 novas" tem causas MUITO diferentes (caixa em dia × marca à
    // frente × mensagem que não abre), e sem isto a tela só sabe dizer "nada novo".
    return [$novas, $total, '', $naPasta,
            ['marca_antes' => $marcaAntes, 'marca_depois' => $last, 'vistas' => count($uids),
             'conhecidas' => $conhecidas, 'falhas' => $falhas, 'recuperacao' => $recuperando ? 1 : 0]];
}
