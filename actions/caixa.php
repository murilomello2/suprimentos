<?php
/**
 * CAIXA DE E-MAIL — suprimentos@ (enviados e recebidos), SÓ LEITURA.
 *
 * GET  ?listar&dir=in|out&me=..[&q=&de=&para=&desde=&ate=&anexo=1&origem=cockpit|webmail&pagina=]
 * GET  ?abrir&id=N&me=..      -> cabeçalho + CORPO INTEIRO + lista de anexos (buscados no IMAP na hora)
 * GET  ?anexo&id=N&i=K&me=..  -> baixa UM anexo
 * POST {acao:'sync', me}      -> varre a caixa e indexa o que é novo
 *
 * NÃO EXISTE ação de apagar, mover ou marcar — de propósito, e não é só a UI: a conexão IMAP é
 * aberta em OP_READONLY e todo fetch usa FT_PEEK (includes/imap_inbox.php). Mesmo que alguém
 * chame este endpoint na mão, não há caminho que altere a caixa.
 *
 * Quem entra: perm_email (configurável por pessoa na aba Configuração) ou administrador.
 * A caixa tem preço e condição comercial de fornecedor — não é tela de consulta de obra.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/caixa.php';

define('CAIXA_POR_PAGINA', 40);

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'];
    $in  = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];
    $me  = $method === 'POST' ? ($in['me'] ?? null) : ($_GET['me'] ?? null);
    $perms = user_perms($pdo, $me);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }
    $pode = !empty($perms['perm_admin']) || !empty($perms['perm_email']);
    if (!$pode) { http_response_code(403);
        echo json_encode(['error' => 'Você não tem acesso à caixa de e-mail. Um administrador libera em Configuração › Permissões específicas.'], JSON_UNESCAPED_UNICODE); exit; }

    // ───────────────────────── SYNC ─────────────────────────
    if (($in['acao'] ?? '') === 'sync' || isset($_GET['sync'])) {
        $cfg = caixa_cfg();
        if (empty($cfg['senha'])) { echo json_encode(['ok' => true, 'sem_config' => true,
            'aviso' => 'A conta de e-mail ainda não foi configurada — o admin cadastra em Configuração › E-mail.'], JSON_UNESCAPED_UNICODE); exit; }
        if (!inbox_ext_ok()) { echo json_encode(['ok' => true, 'sem_imap' => true,
            'aviso' => 'A extensão imap do PHP não está disponível neste servidor.'], JSON_UNESCAPED_UNICODE); exit; }
        // trava leve: evita que 5 pessoas com a tela aberta varram a caixa ao mesmo tempo
        $last = (int)caixa_meta_get($pdo, 'caixa_sync_ts', 0);
        if (!empty($_GET['forcar']) || !empty($in['forcar'])) $last = 0;
        if (time() - $last < 60) { echo json_encode(['ok' => true, 'throttled' => true, 'msg' => 'Acabei de varrer.']); exit; }
        caixa_meta_set($pdo, 'caixa_sync_ts', time());

        [$mbox, $err] = inbox_conectar($cfg, 'INBOX');
        if (!$mbox) { echo json_encode(['ok' => true, 'erro_imap' => $err], JSON_UNESCAPED_UNICODE); exit; }
        $res = ['ok' => true, 'novas' => 0, 'pastas' => [], 'avisos' => []];
        try {
            $env = caixa_pasta_enviados($mbox, $cfg);
            if (!$env) $res['avisos'][] = 'Não encontrei a pasta de enviados nesta conta — a aba Enviados vai ficar vazia.';
            $alvos = [];
            foreach (caixa_pastas_entrada($mbox, $cfg) as $p) $alvos[] = [$p, 'in'];
            if ($env) $alvos[] = [$env, 'out'];
            foreach ($alvos as [$pasta, $dir]) {
                [$n, $tot, $e, $naPasta, $diag] = caixa_sync_pasta($pdo, $cfg, $mbox, $pasta, $dir);
                if ($e) { $res['avisos'][] = $e; continue; }
                $res['novas'] += $n; $res['pastas'][$pasta] = $n;
                $res['na_pasta'][$pasta] = $naPasta;   // quantas a pasta tem ao todo — separa "vazia" de "não achei"
                $res['diag'][$pasta] = $diag;          // marca de UID, quantas viu, quantas falharam
                if (!empty($diag['falhas'])) $res['avisos'][] = $diag['falhas'] . ' mensagem(ns) em ' . $pasta
                    . ' não puderam ser lidas — a marca parou nelas e a próxima varredura tenta de novo.';
                // só avisa de backlog quando o LOTE encheu (a varredura de recuperação vê a janela
                // inteira, então "total > novas" virou o normal — avisar ali seria mentira na tela)
                if ($n > 0 && (int)($diag['vistas'] ?? 0) >= CAIXA_MAX_SYNC)
                    $res['avisos'][] = 'Ainda há mensagens antigas em ' . $pasta . ' — varra de novo para continuar.';
            }
        } finally { inbox_fechar($mbox); }
        echo json_encode($res, JSON_UNESCAPED_UNICODE); exit;
    }

    // ───────────────────────── LISTAR ─────────────────────────
    if (isset($_GET['listar'])) {
        $dir = ($_GET['dir'] ?? 'in') === 'out' ? 'out' : 'in';
        $w = ['direcao = ?']; $a = [$dir];
        $like = function ($col, $v) use (&$w, &$a) { $w[] = "$col LIKE ?"; $a[] = '%' . $v . '%'; };
        if (($v = trim((string)($_GET['de'] ?? ''))) !== '')   { $w[] = "(de_email LIKE ? OR de_nome LIKE ?)"; $a[] = "%$v%"; $a[] = "%$v%"; }
        if (($v = trim((string)($_GET['para'] ?? ''))) !== '') { $w[] = "(para LIKE ? OR cc LIKE ?)"; $a[] = "%$v%"; $a[] = "%$v%"; }
        if (($v = trim((string)($_GET['q'] ?? ''))) !== '')    { $w[] = "(assunto LIKE ? OR preview LIKE ? OR anexos_nomes LIKE ?)"; $a[] = "%$v%"; $a[] = "%$v%"; $a[] = "%$v%"; }
        if (($v = trim((string)($_GET['desde'] ?? ''))) !== '') { $w[] = "data_email >= ?"; $a[] = $v; }
        if (($v = trim((string)($_GET['ate'] ?? ''))) !== '')   { $w[] = "data_email <= ?"; $a[] = $v . 'T23:59:59'; }
        if (!empty($_GET['anexo']))  $w[] = "tem_anexo = 1";
        if (($v = trim((string)($_GET['origem'] ?? ''))) !== '' && $dir === 'out') { $w[] = "origem = ?"; $a[] = $v; }
        if (($v = (int)($_GET['cotacao'] ?? 0))) { $w[] = "cotacao_id = ?"; $a[] = $v; }

        $where = implode(' AND ', $w);
        $tot = $pdo->prepare("SELECT COUNT(*) FROM caixa_msg WHERE $where"); $tot->execute($a);
        $total = (int)$tot->fetchColumn();

        $pag = max(1, (int)($_GET['pagina'] ?? 1));
        $off = ($pag - 1) * CAIXA_POR_PAGINA;
        $q = $pdo->prepare("SELECT id,direcao,pasta,de_email,de_nome,para,cc,assunto,data_email,
                                   tem_anexo,anexos_nomes,preview,origem,cotacao_id,ref_tipo,ref_valor,disparado_por
                            FROM caixa_msg WHERE $where ORDER BY data_email DESC, id DESC
                            LIMIT " . CAIXA_POR_PAGINA . " OFFSET " . $off);
        $q->execute($a);
        echo json_encode(['ok' => true, 'itens' => $q->fetchAll(), 'total' => $total,
            'pagina' => $pag, 'por_pagina' => CAIXA_POR_PAGINA,
            'ultimo_sync' => caixa_meta_get($pdo, 'caixa_sync_ts', 0),
            'conta' => (string)(caixa_cfg()['user'] ?? '')], JSON_UNESCAPED_UNICODE); exit;
    }

    // ───────────────────────── ABRIR (corpo inteiro, na hora) ─────────────────────────
    if (isset($_GET['abrir'])) {
        $id = (int)$_GET['abrir'];
        $q = $pdo->prepare("SELECT * FROM caixa_msg WHERE id=? LIMIT 1"); $q->execute([$id]);
        $m = $q->fetch(); if (!$m) { http_response_code(404); echo json_encode(['error' => 'mensagem não encontrada']); exit; }

        $cfg = caixa_cfg(); $corpo = null; $anexos = []; $aviso = ''; $embutidos = 0;
        [$mbox, $err] = inbox_conectar($cfg, (string)$m['pasta']);
        if (!$mbox) { $aviso = 'Não consegui abrir a caixa agora (' . $err . ') — mostrando só a prévia guardada.'; }
        else {
            try {
                $uvAgora = inbox_uidvalidity($mbox, $cfg, (string)$m['pasta']);
                if ($uvAgora && (int)$m['uidvalidity'] && $uvAgora !== (int)$m['uidvalidity']) {
                    // o servidor renumerou a pasta: a UID guardada aponta para outra mensagem. Não arrisca.
                    $aviso = 'O servidor renumerou esta pasta — a mensagem precisa ser varrida de novo. Mostrando a prévia.';
                } else {
                    $p = inbox_parse_msg($mbox, (int)$m['imap_uid'], 200000);
                    if ($p) {
                        $corpo = (string)($p['corpo'] ?? '');
                        /* O índice `i` tem de ser a posição REAL na mensagem — é por ele que o
                           download busca depois. Filtrar antes de indexar baixaria o arquivo errado. */
                        foreach ((array)($p['anexos'] ?? []) as $i => $an) {
                            if (!empty($an['inline'])) { $embutidos++; continue; }
                            $anexos[] = ['i' => $i, 'nome' => (string)($an['nome'] ?? 'anexo'), 'bytes' => strlen((string)($an['bytes'] ?? ''))];
                        }
                    }
                }
            } catch (Throwable $e) { $aviso = 'Falha ao ler a mensagem: ' . $e->getMessage(); }
            finally { inbox_fechar($mbox); }
        }
        unset($m['dedup_key']);
        echo json_encode(['ok' => true, 'msg' => $m, 'corpo' => $corpo, 'preview' => $m['preview'],
            'anexos' => $anexos, 'embutidos' => $embutidos, 'aviso' => $aviso], JSON_UNESCAPED_UNICODE); exit;
    }

    // ───────────────────────── ANEXO ─────────────────────────
    if (isset($_GET['anexo'])) {
        $id = (int)$_GET['anexo']; $idx = (int)($_GET['i'] ?? 0);
        $q = $pdo->prepare("SELECT pasta, imap_uid, uidvalidity FROM caixa_msg WHERE id=? LIMIT 1"); $q->execute([$id]);
        $m = $q->fetch(); if (!$m) { http_response_code(404); echo json_encode(['error' => 'mensagem não encontrada']); exit; }
        [$mbox, $err] = inbox_conectar(caixa_cfg(), (string)$m['pasta']);
        if (!$mbox) { http_response_code(502); echo json_encode(['error' => 'IMAP: ' . $err], JSON_UNESCAPED_UNICODE); exit; }
        try {
            $p = inbox_parse_msg($mbox, (int)$m['imap_uid'], 1);
            $an = $p['anexos'][$idx] ?? null;
            if (!$an) { http_response_code(404); echo json_encode(['error' => 'anexo não encontrado']); exit; }
            $nome = preg_replace('/[^\w\.\- ]+/u', '_', (string)($an['nome'] ?: 'anexo'));
            header_remove('Content-Type');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $nome . '"');
            header('Content-Length: ' . strlen((string)$an['bytes']));
            echo $an['bytes'];
        } finally { inbox_fechar($mbox); }
        exit;
    }

    throw new Exception('ação inválida');

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
