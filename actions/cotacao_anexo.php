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
/* Teto do anexo: 25 MB é a REGRA do produto, mas o PHP do servidor pode aceitar menos. Vale o
   MENOR dos dois — assim o "máximo X MB" que aparece na tela é a verdade daquele servidor, e não
   uma promessa que o upload quebra depois. */
function anexo_ini_bytes($v) {
    $v = trim((string)$v); if ($v === '') return 0;
    $n = (float)$v; $u = strtolower(substr($v, -1));
    return (int)($n * ($u === 'g' ? 1073741824 : ($u === 'm' ? 1048576 : ($u === 'k' ? 1024 : 1))));
}
define('ANEXO_MAX', (function () {
    $lim = [25 * 1024 * 1024];
    if (($b = anexo_ini_bytes(ini_get('upload_max_filesize'))) > 0) $lim[] = $b;          // teto POR ARQUIVO
    if (($b = anexo_ini_bytes(ini_get('post_max_size'))) > 0) $lim[] = $b - 256 * 1024;   // teto do CORPO (menos os outros campos)
    return max(256 * 1024, min($lim));
})());

// pode GERIR a cotação (anexar/excluir/vincular) = MESMA regra de lançar proposta: admin | gerente | criador |
// colaborador. Antes o anexo exigia can_edit_obra (edição de obra) → comprador criador/colaborador via o botão
// mas o upload dava 403 ("Falha no carregamento"). Retorna [$perms, $obra, $pode_gerir].
function anexo_can($pdo, $me, $cotacao_id) {
    $perms = user_perms($pdo, $me);
    if (empty($perms['autorizado'])) return [null, null, false];
    $row = $pdo->prepare("SELECT COALESCE(obra_id,1) AS obra, criado_por, colaboradores FROM cotacao WHERE id=?");
    $row->execute([(int)$cotacao_id]); $c = $row->fetch() ?: [];
    $obra = max(1, (int)($c['obra'] ?? 1));
    $pode = !empty($perms['perm_admin']) || (($perms['papel'] ?? '') === 'gerente');
    if (!$pode && $me !== null && $me !== '' && $c) {
        if ((string)($c['criado_por'] ?? '') === (string)$me) $pode = true;
        else foreach ((array)(json_decode((string)($c['colaboradores'] ?? ''), true) ?: []) as $b) if (trim((string)$b) === trim((string)$me)) { $pode = true; break; }
    }
    return [$perms, $obra, $pode];
}

try {
    $pdo = db();

    /* LIMITE REAL deste servidor — a tela pergunta antes de deixar escolher o arquivo, para dizer
       "máximo X MB" em vez de prometer 25 e falhar no envio. */
    if (isset($_GET['limite'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'max_bytes' => ANEXO_MAX, 'max_mb' => round(ANEXO_MAX / 1048576, 1),
            'upload_max_filesize' => ini_get('upload_max_filesize'), 'post_max_size' => ini_get('post_max_size')]); exit;
    }

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
        $q = $pdo->prepare("SELECT id, cotacao_id, proposta_id, fornecedor_id, fornecedor_nome, nome, tamanho, mime, embutido, criado_por, url, created_at FROM cotacao_anexo WHERE cotacao_id=? AND (fornecedor_nome IS NULL OR fornecedor_nome<>'__CARTA__') ORDER BY id");
        $q->execute([(int)$_GET['cotacao']]);
        /* Assinatura/logo do fornecedor fica FORA desta lista: ela alimenta os chips de anexo do mapa
           e o seletor da IA, e 4 "image001.png" por e-mail afogavam a proposta (ver anexo_eh_embutido).
           Continuam alcançáveis no modal do e-mail, que mostra o que veio embutido em separado. */
        $todos = $q->fetchAll();
        $anexos = array_values(array_filter($todos, function ($a) { return !anexo_eh_embutido($a); }));
        echo json_encode(['anexos' => $anexos, 'embutidos' => count($todos) - count($anexos)], JSON_UNESCAPED_UNICODE); exit;
    }

    // ---------- SET FORNECEDOR (JSON) — vincula anexos avulsos ao fornecedor identificado pela IA (item B) ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_FILES) && (json_decode(file_get_contents('php://input'), true)['acao'] ?? '') === 'set_fornecedor') {
        header('Content-Type: application/json; charset=utf-8');
        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $ids = array_values(array_filter(array_map('intval', (array)($in['ids'] ?? []))));
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$ids || !$cid) throw new Exception('ids e cotacao_id obrigatórios');
        [$perms, $obra, $pode] = anexo_can($pdo, $in['me'] ?? null, $cid);
        if (!$pode) { http_response_code(403); echo json_encode(['error'=>'Sem permissão.']); exit; }
        $fid = (int)($in['fornecedor_id'] ?? 0) ?: null; $fnome = trim((string)($in['fornecedor_nome'] ?? '')) ?: null;
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE cotacao_anexo SET fornecedor_id=?, fornecedor_nome=? WHERE cotacao_id=? AND id IN ($ph)")
            ->execute(array_merge([$fid, $fnome, $cid], $ids));
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    // ---------- EXCLUIR (JSON) ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_FILES)) {
        header('Content-Type: application/json; charset=utf-8');
        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        if (($in['acao'] ?? '') !== 'excluir') throw new Exception('ação inválida');
        $id = (int)($in['id'] ?? 0); if (!$id) throw new Exception('id obrigatório');
        $a = $pdo->prepare("SELECT * FROM cotacao_anexo WHERE id=?"); $a->execute([$id]); $a = $a->fetch();
        if (!$a) { echo json_encode(['ok'=>true]); exit; }
        [$perms, $obra, $pode] = anexo_can($pdo, $in['me'] ?? null, $a['cotacao_id']);
        if (!$pode) { http_response_code(403); echo json_encode(['error'=>'Sem permissão.']); exit; }
        $path = ANEXO_DIR . '/' . basename((string)$a['arquivo']);
        if (is_file($path)) @unlink($path);
        $pdo->prepare("DELETE FROM cotacao_anexo WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    // ---------- UPLOAD (multipart) ----------
    header('Content-Type: application/json; charset=utf-8');

    /* POST MAIOR QUE O LIMITE DO PHP = requisição VAZIA. Quando o corpo passa de post_max_size, o
       PHP joga fora $_POST E $_FILES juntos e não marca erro em lugar nenhum: chegava aqui como
       "cotacao_id obrigatório", que manda a pessoa procurar no lugar errado. Foi o que fez o anexo
       falhar para uns e funcionar para outros — depende do TAMANHO do arquivo, não de quem é.
       Diagnóstico medido em 14/08/2026, a pedido do Murilo (relato do comprador Gabriel Borges). */
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $lim = ini_get('post_max_size');
        http_response_code(413);
        echo json_encode(['error' => 'O arquivo passou do limite do servidor (' . $lim . ' por envio). '
            . 'Mande um arquivo menor, ou envie um de cada vez — se precisar de mais, peça ao TI para aumentar post_max_size.',
            'limite' => $lim], JSON_UNESCAPED_UNICODE); exit;
    }

    $cid = (int)($_POST['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
    [$perms, $obra, $pode] = anexo_can($pdo, $_POST['me'] ?? null, $cid);
    if (!$pode) { http_response_code(403); echo json_encode(['error'=>'Sem permissão para anexar nesta cotação (só admin, gerente, quem criou ou um colaborador).']); exit; }
    // a carta em PDF (__CARTA__) é ÚNICA por cotação — ao re-salvar, remove a anterior (não conta p/ o limite nem polui)
    if (trim((string)($_POST['fornecedor_nome'] ?? '')) === '__CARTA__') {
        $old = $pdo->prepare("SELECT arquivo FROM cotacao_anexo WHERE cotacao_id=? AND fornecedor_nome='__CARTA__'"); $old->execute([$cid]);
        foreach ($old->fetchAll() as $o) { $op = ANEXO_DIR . '/' . basename((string)$o['arquivo']); if (is_file($op)) @unlink($op); }
        $pdo->prepare("DELETE FROM cotacao_anexo WHERE cotacao_id=? AND fornecedor_nome='__CARTA__'")->execute([$cid]);
    }
    if (empty($_FILES['arquivo']) || ($_FILES['arquivo']['error'] ?? 1) !== UPLOAD_ERR_OK) {
        $err = $_FILES['arquivo']['error'] ?? 'sem arquivo';
        throw new Exception($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE ? 'arquivo maior que o limite do servidor' : 'falha no upload (' . $err . ')');
    }
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM cotacao_anexo WHERE cotacao_id=" . $cid)->fetchColumn();
    if ($cnt >= 40) throw new Exception('limite de 40 anexos por cotação atingido');
    $pid = (int)($_POST['proposta_id'] ?? 0);   // valida que a proposta é DESTA cotação (senão vira anexo da cotação)
    if ($pid) { $ck = $pdo->prepare("SELECT COUNT(*) FROM cotacao_proposta WHERE id=? AND cotacao_id=?"); $ck->execute([$pid, $cid]); if (!$ck->fetchColumn()) $pid = 0; }
    $f = $_FILES['arquivo'];
    if ($f['size'] > ANEXO_MAX) throw new Exception('máximo ' . round(ANEXO_MAX / 1048576) . ' MB por arquivo neste servidor');
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
