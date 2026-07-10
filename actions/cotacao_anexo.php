<?php
/**
 * ANEXOS (PDF) das cotações/propostas do Mapa de Cotações.
 * Guarda os PDFs recebidos dos fornecedores. Storage em data/anexos/ (data/ é HTTP-403 no prod →
 * arquivos NÃO acessíveis direto; o único acesso é por este endpoint, autenticado).
 *
 * POST multipart (arquivo, cotacao_id, proposta_id?, me)  -> upload (só PDF, <= 25MB)
 * GET  ?cotacao=N                                          -> lista anexos da cotação
 * GET  ?download=N&me=..                                   -> baixa o PDF (stream, autenticado)
 * POST JSON {acao:'excluir', me, id}                       -> remove o anexo (arquivo + registro)
 */
require_once __DIR__ . '/../includes/db.php';

define('ANEXO_DIR', __DIR__ . '/../data/anexos');
define('ANEXO_MAX', 25 * 1024 * 1024);

function anexo_can($pdo, $me, $cotacao_id) {
    $perms = user_perms($pdo, $me);
    if (empty($perms['autorizado'])) return [null, null];
    $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . (int)$cotacao_id)->fetchColumn();
    return [$perms, max(1, $obra)];
}

try {
    $pdo = db();

    // ---------- DOWNLOAD (stream) ----------
    if (isset($_GET['download'])) {
        $perms = user_perms($pdo, $_GET['me'] ?? null);
        if (empty($perms['autorizado'])) { http_response_code(403); header('Content-Type: text/plain'); echo 'sem acesso'; exit; }
        $a = $pdo->prepare("SELECT * FROM cotacao_anexo WHERE id=?"); $a->execute([(int)$_GET['download']]); $a = $a->fetch();
        if (!$a) { http_response_code(404); header('Content-Type: text/plain'); echo 'não encontrado'; exit; }
        $path = ANEXO_DIR . '/' . basename((string)$a['arquivo']);   // basename: nunca sai da pasta
        if (!is_file($path)) { http_response_code(404); header('Content-Type: text/plain'); echo 'arquivo ausente'; exit; }
        $nome = preg_replace('/[^A-Za-z0-9 ._-]/', '_', (string)($a['nome'] ?: 'anexo'));
        $mime = (string)($a['mime'] ?: 'application/octet-stream');
        $inline = in_array($mime, ['application/pdf', 'image/png', 'image/jpeg'], true);   // PDF/imagem abrem inline; Excel baixa
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $nome . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path); exit;
    }

    // ---------- LISTA ----------
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['cotacao'])) {
        header('Content-Type: application/json; charset=utf-8');
        $perms = user_perms($pdo, $_GET['me'] ?? null);
        if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error'=>'sem acesso']); exit; }
        $q = $pdo->prepare("SELECT id, cotacao_id, proposta_id, fornecedor_id, fornecedor_nome, nome, tamanho, mime, created_at FROM cotacao_anexo WHERE cotacao_id=? ORDER BY id");
        $q->execute([(int)$_GET['cotacao']]);
        echo json_encode(['anexos' => $q->fetchAll()], JSON_UNESCAPED_UNICODE); exit;
    }

    // ---------- EXCLUIR (JSON) ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_FILES)) {
        header('Content-Type: application/json; charset=utf-8');
        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        if (($in['acao'] ?? '') !== 'excluir') throw new Exception('ação inválida');
        $id = (int)($in['id'] ?? 0); if (!$id) throw new Exception('id obrigatório');
        $a = $pdo->prepare("SELECT * FROM cotacao_anexo WHERE id=?"); $a->execute([$id]); $a = $a->fetch();
        if (!$a) { echo json_encode(['ok'=>true]); exit; }
        [$perms, $obra] = anexo_can($pdo, $in['me'] ?? null, $a['cotacao_id']);
        if (!$perms || !can_edit_obra($perms, $obra)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão.']); exit; }
        $path = ANEXO_DIR . '/' . basename((string)$a['arquivo']);
        if (is_file($path)) @unlink($path);
        $pdo->prepare("DELETE FROM cotacao_anexo WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    // ---------- UPLOAD (multipart) ----------
    header('Content-Type: application/json; charset=utf-8');
    $cid = (int)($_POST['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
    [$perms, $obra] = anexo_can($pdo, $_POST['me'] ?? null, $cid);
    if (!$perms || !can_edit_obra($perms, $obra)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão de edição.']); exit; }
    if (empty($_FILES['arquivo']) || ($_FILES['arquivo']['error'] ?? 1) !== UPLOAD_ERR_OK) {
        $err = $_FILES['arquivo']['error'] ?? 'sem arquivo';
        throw new Exception($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE ? 'arquivo maior que o limite do servidor' : 'falha no upload (' . $err . ')');
    }
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM cotacao_anexo WHERE cotacao_id=" . $cid)->fetchColumn();
    if ($cnt >= 40) throw new Exception('limite de 40 anexos por cotação atingido');
    $pid = (int)($_POST['proposta_id'] ?? 0);   // valida que a proposta é DESTA cotação (senão vira anexo da cotação)
    if ($pid) { $ck = $pdo->prepare("SELECT COUNT(*) FROM cotacao_proposta WHERE id=? AND cotacao_id=?"); $ck->execute([$pid, $cid]); if (!$ck->fetchColumn()) $pid = 0; }
    $f = $_FILES['arquivo'];
    if ($f['size'] > ANEXO_MAX) throw new Exception('máximo 25 MB por arquivo');
    if ($f['size'] <= 0) throw new Exception('arquivo vazio');
    // detecta o TIPO por MAGIC BYTES (não confia no mime/extensão do cliente): PDF, Excel (xlsx/xls) e imagem (PNG/JPG)
    $fh = fopen($f['tmp_name'], 'rb'); $head = $fh ? fread($fh, 8) : ''; if ($fh) fclose($fh);
    $ext = null; $mime = null;
    if (strncmp($head, '%PDF-', 5) === 0) { $ext = 'pdf'; $mime = 'application/pdf'; }
    elseif (strncmp($head, "\x89PNG\x0d\x0a\x1a\x0a", 8) === 0) { $ext = 'png'; $mime = 'image/png'; }
    elseif (strncmp($head, "\xFF\xD8\xFF", 3) === 0) { $ext = 'jpg'; $mime = 'image/jpeg'; }
    elseif (strncmp($head, "PK\x03\x04", 4) === 0) { $ext = 'xlsx'; $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'; } // xlsx (zip); validação leve
    elseif (strncmp($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1a\xE1", 8) === 0) { $ext = 'xls'; $mime = 'application/vnd.ms-excel'; } // OLE (xls antigo)
    else throw new Exception('formato não aceito — envie PDF, Excel (xlsx/xls) ou imagem (PNG/JPG)');
    if (!is_dir(ANEXO_DIR)) @mkdir(ANEXO_DIR, 0775, true);
    $rand = bin2hex(random_bytes(10));
    $stored = 'anx_' . $cid . '_' . $rand . '.' . $ext;             // nome no disco = SEMPRE gerado (nunca o do cliente)
    if (!move_uploaded_file($f['tmp_name'], ANEXO_DIR . '/' . $stored)) throw new Exception('não foi possível salvar');
    $nome = trim((string)$f['name']); if ($nome === '') $nome = 'anexo.' . $ext;
    if (strlen($nome) > 240) $nome = substr($nome, -240);
    $fornId = (int)($_POST['fornecedor_id'] ?? 0) ?: null;
    $fornNome = trim((string)($_POST['fornecedor_nome'] ?? '')); if ($fornNome === '') $fornNome = null; elseif (strlen($fornNome) > 180) $fornNome = substr($fornNome, 0, 180);
    $pdo->prepare("INSERT INTO cotacao_anexo (cotacao_id, proposta_id, fornecedor_id, fornecedor_nome, nome, arquivo, tamanho, mime, criado_por, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([$cid, $pid ?: null, $fornId, $fornNome, $nome, $stored, (int)$f['size'], $mime, $_POST['me'] ?? null, date('c')]);
    echo json_encode(['ok'=>true, 'id'=>(int)$pdo->lastInsertId(), 'nome'=>$nome, 'tamanho'=>(int)$f['size'], 'mime'=>$mime], JSON_UNESCAPED_UNICODE); exit;

} catch (Throwable $e) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
