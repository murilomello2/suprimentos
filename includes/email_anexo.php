<?php
/**
 * ANEXOS QUE SAEM — os arquivos que o comprador manda JUNTO com o e-mail.
 *
 * Dois momentos, o mesmo mecanismo:
 *   • disparo  — o e-mail de cotação que vai para TODOS os fornecedores da leva (projeto em DWG,
 *                memorial descritivo em PDF, um .zip de pranchas). Fica preso à COTAÇÃO: uma
 *                segunda leva, dias depois, leva os mesmos arquivos sem ninguém subir de novo.
 *   • resposta — a resposta a uma dúvida do fornecedor ("me manda o projeto pra eu conseguir
 *                cotar"). Fica presa àquela MENSAGEM e é marcada como usada ao enviar, senão o
 *                arquivo voltaria de carona na próxima resposta da mesma conversa.
 *
 * Por que tabela própria e não `cotacao_anexo`: aquela guarda o que CHEGA do fornecedor (proposta,
 * carta em PDF) e é a entrada da IA que lê proposta. Misturar faria o arquivo que sai aparecer no
 * mapa como se fosse resposta de fornecedor.
 *
 * O arquivo mora em data/anexos/ — a MESMA pasta dos recebidos, com prefixo eml_. A proteção HTTP
 * (data/anexos/.htaccess + o RedirectMatch da raiz) já existe ali; uma pasta nova nasceria sem ela
 * e os projetos ficariam baixáveis por quem adivinhasse o nome.
 */
require_once __DIR__ . '/db.php';

define('EMAILANX_DIR', __DIR__ . '/../data/anexos');

function emailanx_ini_bytes($v) {
    $v = trim((string)$v); if ($v === '') return 0;
    $n = (float)$v; $u = strtolower(substr($v, -1));
    return (int)($n * ($u === 'g' ? 1073741824 : ($u === 'm' ? 1048576 : ($u === 'k' ? 1024 : 1))));
}

/** Teto POR ARQUIVO: 25 MB é a regra do produto, mas vale o menor entre ela e o que este PHP aceita. */
function emailanx_max_arquivo() {
    $lim = [25 * 1024 * 1024];
    if (($b = emailanx_ini_bytes(ini_get('upload_max_filesize'))) > 0) $lim[] = $b;
    if (($b = emailanx_ini_bytes(ini_get('post_max_size'))) > 0) $lim[] = $b - 256 * 1024;
    return max(256 * 1024, min($lim));
}

/**
 * Teto do TOTAL anexado a UM e-mail. Dois motivos para existir, e nenhum é capricho:
 *   • o servidor do fornecedor recusa mensagem grande (o comum é 25 MB já codificada, e base64
 *     engorda o arquivo em ~33%) — 18 MB de arquivo viram ~24 MB de mensagem;
 *   • a mensagem inteira é montada em memória no PHP (corpo + base64 + a cópia que vai para a
 *     pasta Enviados), então o memory_limit da hospedagem é um teto real, não teórico.
 */
function emailanx_max_total() {
    $lim = [18 * 1024 * 1024];
    $ml = emailanx_ini_bytes(ini_get('memory_limit'));
    if ($ml > 0) $lim[] = (int)($ml / 8);
    return max(1024 * 1024, min($lim));
}

/**
 * Formatos aceitos: [extensão => [mime, magic|null]].
 * Lista FECHADA — é o que o fornecedor precisa receber (projeto, planilha, prancha), e nada que o
 * cliente de e-mail dele execute. Onde existe assinatura conhecida, o conteúdo é conferido: um
 * .exe renomeado para .pdf não sai daqui com a nossa assinatura embaixo.
 */
function emailanx_tipos() {
    return [
        'pdf'  => ['application/pdf', '%PDF-'],
        'dwg'  => ['application/acad', null],                    // AutoCAD (AC10xx, mas há variantes antigas)
        'dxf'  => ['application/dxf', null],
        'dwf'  => ['model/vnd.dwf', null],
        'zip'  => ['application/zip', 'PK'],
        'rar'  => ['application/vnd.rar', 'Rar!'],
        '7z'   => ['application/x-7z-compressed', "7z\xBC\xAF\x27\x1C"],
        'png'  => ['image/png', "\x89PNG"],
        'jpg'  => ['image/jpeg', "\xFF\xD8\xFF"],
        'jpeg' => ['image/jpeg', "\xFF\xD8\xFF"],
        'gif'  => ['image/gif', 'GIF8'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'PK'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'PK'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'PK'],
        'xls'  => ['application/vnd.ms-excel', null],
        'doc'  => ['application/msword', null],
        'ppt'  => ['application/vnd.ms-powerpoint', null],
        'csv'  => ['text/csv', null],
        'txt'  => ['text/plain', null],
        'xml'  => ['application/xml', null],
        'rvt'  => ['application/octet-stream', null],            // Revit
        'ifc'  => ['application/octet-stream', null],            // BIM aberto
        'skp'  => ['application/octet-stream', null],            // SketchUp
        'kmz'  => ['application/vnd.google-earth.kmz', 'PK'],
        'kml'  => ['application/vnd.google-earth.kml+xml', null],
    ];
}
function emailanx_exts_txt() { return '.' . implode(', .', array_keys(emailanx_tipos())); }

/** Valida nome+conteúdo e devolve ['ext','mime']. Lança Exception com o motivo em português. */
function emailanx_tipo($nomeArquivo, $head) {
    $ext = strtolower((string)pathinfo((string)$nomeArquivo, PATHINFO_EXTENSION));
    $t = emailanx_tipos();
    if ($ext === '' || !isset($t[$ext])) {
        throw new Exception('formato não aceito (' . ($ext === '' ? 'sem extensão' : '.' . $ext) . ') — aceita ' . emailanx_exts_txt());
    }
    // executável disfarçado: o nome pode ser qualquer um, a assinatura não mente
    if (strncmp((string)$head, 'MZ', 2) === 0 || strncmp((string)$head, "\x7fELF", 4) === 0) {
        throw new Exception('este arquivo é um programa executável — não pode ser enviado por e-mail');
    }
    [$mime, $magic] = $t[$ext];
    if ($magic !== null && strncmp((string)$head, $magic, strlen($magic)) !== 0) {
        throw new Exception('o conteúdo não confere com a extensão .' . $ext . ' — confira se o arquivo não está corrompido ou renomeado');
    }
    return ['ext' => $ext, 'mime' => $mime];
}

function emailanx_escopo_ok($e) { return in_array((string)$e, ['disparo', 'resposta'], true); }

/**
 * Os anexos PENDENTES de um escopo (os que ainda vão junto do próximo envio).
 * 'disparo' vive enquanto o comprador não excluir; 'resposta' some assim que é enviada.
 */
function emailanx_listar($pdo, $escopo, $cotacaoId, $refId = 0) {
    if (!emailanx_escopo_ok($escopo)) return [];
    try {
        $q = $pdo->prepare("SELECT id, nome, tamanho, mime, criado_nome, created_at FROM email_anexo
                            WHERE escopo=? AND cotacao_id=? AND ref_id=? AND usado_em IS NULL ORDER BY id");
        $q->execute([(string)$escopo, (int)$cotacaoId, (int)$refId]);
        return $q->fetchAll();
    } catch (Throwable $e) { return []; }   // tabela ainda não criada num banco velho: e-mail sem anexo, não erro
}

function emailanx_soma($pdo, $escopo, $cotacaoId, $refId = 0) {
    $t = 0; foreach (emailanx_listar($pdo, $escopo, $cotacaoId, $refId) as $a) $t += (int)$a['tamanho'];
    return $t;
}

/** Lê os arquivos do disco no formato que o smtp_send() espera. Arquivo sumido é PULADO, não quebra o envio. */
function emailanx_para_envio($pdo, $escopo, $cotacaoId, $refId = 0) {
    if (!emailanx_escopo_ok($escopo)) return [];
    $out = [];
    try {
        $q = $pdo->prepare("SELECT nome, arquivo, mime FROM email_anexo
                            WHERE escopo=? AND cotacao_id=? AND ref_id=? AND usado_em IS NULL ORDER BY id");
        $q->execute([(string)$escopo, (int)$cotacaoId, (int)$refId]);
        foreach ($q->fetchAll() as $a) {
            $p = EMAILANX_DIR . '/' . basename((string)$a['arquivo']);
            if (!is_file($p)) continue;
            $bin = @file_get_contents($p);
            if ($bin === false || $bin === '') continue;
            $out[] = ['nome' => (string)$a['nome'], 'mime' => (string)($a['mime'] ?: 'application/octet-stream'), 'conteudo' => $bin];
        }
    } catch (Throwable $e) {}
    return $out;
}

/** Marca como já enviados (só a resposta usa: o anexo do disparo vale para todas as levas). */
function emailanx_marcar_usados($pdo, $escopo, $cotacaoId, $refId = 0) {
    if (!emailanx_escopo_ok($escopo)) return;
    try {
        $pdo->prepare("UPDATE email_anexo SET usado_em=? WHERE escopo=? AND cotacao_id=? AND ref_id=? AND usado_em IS NULL")
            ->execute([date('c'), (string)$escopo, (int)$cotacaoId, (int)$refId]);
    } catch (Throwable $e) {}
}
