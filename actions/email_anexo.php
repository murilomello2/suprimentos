<?php
/**
 * ANEXOS QUE SAEM no e-mail — upload/lista/exclusão/download.
 *
 * O fornecedor pede o projeto para conseguir cotar; até aqui o comprador tinha de sair do cockpit,
 * abrir o webmail e mandar de lá — e o disparo padrão nunca levava nada além da carta em PDF.
 * Os arquivos daqui entram no MESMO envio (actions/email.php no disparo, actions/inbox.php na
 * resposta), então a mensagem continua sendo uma só, com Message-ID nosso e cópia nos Enviados.
 *
 * GET  ?limite=1                                     -> tetos reais deste servidor + formatos aceitos
 * GET  ?listar=1&escopo=&cotacao=&ref=&me=           -> anexos pendentes daquele e-mail
 * GET  ?download=N&me=                               -> baixa o arquivo (stream, autenticado)
 * POST multipart (arquivo, escopo, cotacao_id, ref_id, me)  -> sobe um arquivo
 * POST JSON {acao:'excluir', id, me}                 -> remove (arquivo + registro)
 *
 * A identidade continua vindo do `me` do cliente, como em todo o app — quem pode gerir a COTAÇÃO
 * pode anexar nela (admin | gerente | quem criou | colaborador), e a trava do papel visualizador
 * pega qualquer POST em includes/db.php.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email_anexo.php';

define('EMAILANX_MAX_ARQUIVOS', 15);   // por e-mail; acima disso o servidor do fornecedor costuma recusar

/**
 * Quem pode mexer nos anexos DESTE e-mail. Devolve [pode, cotacao_id, ref_id] — o cotacao_id do
 * escopo 'resposta' é RESOLVIDO no banco a partir da mensagem, nunca aceito do cliente: senão
 * bastaria mandar o id de uma cotação própria para anexar na conversa de outra pessoa.
 */
function emailanx_pode($pdo, $me, $escopo, $cotacaoId, $refId) {
    $perms = user_perms($pdo, $me);
    if (empty($perms['autorizado'])) return [false, 0, 0];
    if ($escopo === 'resposta') {
        $refId = (int)$refId; if (!$refId) return [false, 0, 0];
        try { $q = $pdo->prepare("SELECT cotacao_id FROM cotacao_email_in WHERE id=?"); $q->execute([$refId]); $m = $q->fetch(); }
        catch (Throwable $e) { return [false, 0, 0]; }
        if (!$m) return [false, 0, 0];
        $cid = (int)($m['cotacao_id'] ?? 0);
        // e-mail sem cotação casada = só admin (espelha a regra de responder em actions/inbox.php)
        if (!$cid) return [!empty($perms['perm_admin']), 0, $refId];
        return [cot_pode_gerir($pdo, $me, $cid), $cid, $refId];
    }
    $cid = (int)$cotacaoId; if (!$cid) return [false, 0, 0];
    return [cot_pode_gerir($pdo, $me, $cid), $cid, 0];
}

try {
    $pdo = db();

    // ---------- TETOS + FORMATOS (a tela avisa ANTES de deixar escolher o arquivo) ----------
    if (isset($_GET['limite'])) {
        header('Content-Type: application/json; charset=utf-8');
        $ma = emailanx_max_arquivo(); $mt = emailanx_max_total();
        echo json_encode(['ok' => true,
            'max_arquivo' => $ma, 'max_arquivo_mb' => round($ma / 1048576, 1),
            'max_total' => $mt, 'max_total_mb' => round($mt / 1048576, 1),
            'max_arquivos' => EMAILANX_MAX_ARQUIVOS,
            'extensoes' => array_keys(emailanx_tipos()),
            'accept' => '.' . implode(',.', array_keys(emailanx_tipos()))], JSON_UNESCAPED_UNICODE); exit;
    }

    // ---------- DOWNLOAD (stream) ----------
    if (isset($_GET['download'])) {
        $a = $pdo->prepare("SELECT * FROM email_anexo WHERE id=?"); $a->execute([(int)$_GET['download']]); $a = $a->fetch();
        if (!$a) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'não encontrado'; exit; }
        [$pode] = emailanx_pode($pdo, $_GET['me'] ?? null, (string)$a['escopo'], (int)$a['cotacao_id'], (int)$a['ref_id']);
        if (!$pode) { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); echo 'sem acesso'; exit; }
        $path = EMAILANX_DIR . '/' . basename((string)$a['arquivo']);   // basename: nunca sai da pasta
        if (!is_file($path)) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'arquivo ausente'; exit; }
        $nome = preg_replace('/[^A-Za-z0-9 ._-]/', '_', (string)($a['nome'] ?: 'anexo'));
        $mime = (string)($a['mime'] ?: 'application/octet-stream');
        $inline = in_array($mime, ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'text/plain'], true);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $nome . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path); exit;
    }

    // ---------- LISTA ----------
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['listar'])) {
        header('Content-Type: application/json; charset=utf-8');
        $escopo = (string)($_GET['escopo'] ?? 'disparo');
        [$pode, $cid, $ref] = emailanx_pode($pdo, $_GET['me'] ?? null, $escopo, (int)($_GET['cotacao'] ?? 0), (int)($_GET['ref'] ?? 0));
        if (!$pode) { http_response_code(403); echo json_encode(['error' => 'Sem permissão.']); exit; }
        $lista = emailanx_listar($pdo, $escopo, $cid, $ref);
        $soma = 0; foreach ($lista as $a) $soma += (int)$a['tamanho'];
        echo json_encode(['ok' => true, 'anexos' => $lista, 'total' => $soma,
            'max_total' => emailanx_max_total()], JSON_UNESCAPED_UNICODE); exit;
    }

    // ---------- EXCLUIR (JSON) ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_FILES)) {
        header('Content-Type: application/json; charset=utf-8');
        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        if (($in['acao'] ?? '') !== 'excluir') throw new Exception('ação inválida');
        $id = (int)($in['id'] ?? 0); if (!$id) throw new Exception('id obrigatório');
        $a = $pdo->prepare("SELECT * FROM email_anexo WHERE id=?"); $a->execute([$id]); $a = $a->fetch();
        if (!$a) { echo json_encode(['ok' => true]); exit; }
        [$pode] = emailanx_pode($pdo, $in['me'] ?? null, (string)$a['escopo'], (int)$a['cotacao_id'], (int)$a['ref_id']);
        if (!$pode) { http_response_code(403); echo json_encode(['error' => 'Sem permissão.']); exit; }
        $path = EMAILANX_DIR . '/' . basename((string)$a['arquivo']);
        if (is_file($path)) @unlink($path);
        $pdo->prepare("DELETE FROM email_anexo WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE); exit;
    }

    // ---------- UPLOAD (multipart) ----------
    header('Content-Type: application/json; charset=utf-8');

    /* POST MAIOR QUE post_max_size = requisição VAZIA: o PHP joga fora $_POST E $_FILES juntos e não
       marca erro em lugar nenhum. Sem este aviso a falha chega como "cotacao_id obrigatório" e manda
       a pessoa procurar no lugar errado (mesma lição de cotacao_anexo.php, 14/08/2026). */
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        http_response_code(413);
        echo json_encode(['error' => 'O arquivo passou do limite do servidor (' . ini_get('post_max_size') . ' por envio). '
            . 'Compacte em .zip ou mande um de cada vez.'], JSON_UNESCAPED_UNICODE); exit;
    }

    $escopo = (string)($_POST['escopo'] ?? 'disparo');
    if (!emailanx_escopo_ok($escopo)) throw new Exception('escopo inválido');
    [$pode, $cid, $ref] = emailanx_pode($pdo, $_POST['me'] ?? null, $escopo, (int)($_POST['cotacao_id'] ?? 0), (int)($_POST['ref_id'] ?? 0));
    if (!$pode) { http_response_code(403); echo json_encode(['error' => 'Sem permissão para anexar neste e-mail (só admin, gerente, quem criou a cotação ou um colaborador).']); exit; }

    if (empty($_FILES['arquivo']) || ($_FILES['arquivo']['error'] ?? 1) !== UPLOAD_ERR_OK) {
        $err = $_FILES['arquivo']['error'] ?? 'sem arquivo';
        throw new Exception($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE
            ? 'arquivo maior que o limite do servidor' : 'falha no upload (' . $err . ')');
    }
    $f = $_FILES['arquivo'];
    if ($f['size'] <= 0) throw new Exception('arquivo vazio');
    $maxA = emailanx_max_arquivo();
    if ($f['size'] > $maxA) throw new Exception('máximo ' . round($maxA / 1048576) . ' MB por arquivo neste servidor');

    $jaTem = emailanx_listar($pdo, $escopo, $cid, $ref);
    if (count($jaTem) >= EMAILANX_MAX_ARQUIVOS) throw new Exception('limite de ' . EMAILANX_MAX_ARQUIVOS . ' anexos por e-mail');
    $soma = 0; foreach ($jaTem as $a) $soma += (int)$a['tamanho'];
    $maxT = emailanx_max_total();
    if ($soma + (int)$f['size'] > $maxT) {
        throw new Exception('a soma dos anexos passaria de ' . round($maxT / 1048576, 1) . ' MB — acima disso o servidor do '
            . 'fornecedor recusa a mensagem. Compacte os arquivos ou mande em dois e-mails.');
    }

    $fh = fopen($f['tmp_name'], 'rb'); $head = $fh ? fread($fh, 8) : ''; if ($fh) fclose($fh);
    $t = emailanx_tipo((string)$f['name'], $head);                  // valida extensão E conteúdo

    if (!is_dir(EMAILANX_DIR)) @mkdir(EMAILANX_DIR, 0775, true);
    $stored = 'eml_' . $escopo[0] . $cid . '_' . bin2hex(random_bytes(10)) . '.' . $t['ext'];   // nome no disco SEMPRE gerado
    if (!move_uploaded_file($f['tmp_name'], EMAILANX_DIR . '/' . $stored)) throw new Exception('não foi possível salvar');

    $nome = trim((string)$f['name']); if ($nome === '') $nome = 'anexo.' . $t['ext'];
    if (strlen($nome) > 240) $nome = substr($nome, -240);
    $perms = user_perms($pdo, $_POST['me'] ?? null);
    $pdo->prepare("INSERT INTO email_anexo (escopo, cotacao_id, ref_id, nome, arquivo, tamanho, mime, criado_por, criado_nome, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([$escopo, $cid, $ref, $nome, $stored, (int)$f['size'], $t['mime'], $_POST['me'] ?? null,
                   (string)($perms['nome'] ?? ''), date('c')]);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'nome' => $nome,
        'tamanho' => (int)$f['size'], 'mime' => $t['mime'], 'total' => $soma + (int)$f['size']], JSON_UNESCAPED_UNICODE); exit;

} catch (Throwable $e) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
