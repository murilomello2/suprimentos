<?php
/**
 * MOTOR DE IA — lê os anexos (PDF / Excel / imagem) de um fornecedor e PRÉ-PREENCHE uma proposta
 * (RASCUNHO para validação humana; nunca vira proposta oficial sozinho).
 * Reusa a chave OpenAI do Radar IA (data/.oracle.json). Prompt de extração editável pelo admin.
 *
 * POST {acao:'preencher', me, cotacao_id, fornecedor_id?, fornecedor_nome?, anexo_ids:[...]}
 *   -> {ok, draft:{itens:[{item_id,preco_unit,observacao}], extras:[{descricao,valor}],
 *              prazo_entrega, condicao_pagamento, validade, observacao_geral, confianca}, usados, avisos, modelo}
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
define('ORACLE_LIB_ONLY', 1);
require_once __DIR__ . '/oracle.php';   // reusa oracle_cfg() (chave/modelo) e oracle_post() (curl → OpenAI)

define('IA_ANEXO_DIR', __DIR__ . '/../data/anexos');

// Extrai texto de um .xlsx (zip com XML): sharedStrings (1 por <si>) + células dos worksheets. Best-effort.
function ia_xlsx_text($path) {
    if (!class_exists('ZipArchive')) return '';
    $z = new ZipArchive(); if ($z->open($path) !== true) return '';
    $shared = [];
    $ss = $z->getFromName('xl/sharedStrings.xml');
    if ($ss !== false && preg_match_all('/<si>(.*?)<\/si>/s', $ss, $sim)) {
        foreach ($sim[1] as $si) { $t = ''; if (preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $si, $tm)) $t = implode('', $tm[1]);
            $shared[] = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_XML1, 'UTF-8'); }
    }
    $out = [];
    for ($i = 1; $i <= 12; $i++) {
        $sheet = $z->getFromName("xl/worksheets/sheet$i.xml");
        if ($sheet === false) { if ($i === 1) continue; else break; }
        if (preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $sheet, $rows)) {
            foreach ($rows[1] as $row) {
                $cells = [];
                if (preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/s', $row, $cm, PREG_SET_ORDER)) {
                    foreach ($cm as $c) { $attr = $c[1]; $body = $c[2];
                        if (preg_match('/<v>(.*?)<\/v>/s', $body, $vm)) {
                            $v = $vm[1];
                            if (preg_match('/\bt="s"/', $attr)) $cells[] = $shared[(int)$v] ?? '';
                            else $cells[] = html_entity_decode(strip_tags($v), ENT_QUOTES | ENT_XML1, 'UTF-8');
                        } elseif (preg_match('/\bt="inlineStr"/', $attr) && preg_match('/<t[^>]*>(.*?)<\/t>/s', $body, $tm2)) {
                            $cells[] = html_entity_decode(strip_tags($tm2[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                        }
                    }
                }
                if ($cells) $out[] = implode(' | ', $cells);
                if (count($out) > 400) break;
            }
        }
        if (count($out) > 400) break;
    }
    $z->close();
    return trim(implode("\n", array_slice($out, 0, 400)));
}

try {
    $pdo = db();

    // ===== ITEM A: extrair a LISTA DE ITENS de um orçamento (PDF/Excel/imagem) enviado no cadastro (multipart, sem cotação) =====
    if (!empty($_FILES['arquivo'])) {
        $perms = user_perms($pdo, $_POST['me'] ?? null);
        if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }
        $cfg = oracle_cfg(); $key = $cfg['key'] ?? '';
        if (!$key) { echo json_encode(['error' => 'IA não configurada — o admin precisa cadastrar a chave da OpenAI em Radar IA.']); exit; }
        $model = trim((string)($cfg['model_extracao'] ?? '')) ?: 'gpt-4o';
        $f = $_FILES['arquivo'];
        if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) throw new Exception('falha no upload do arquivo');
        if (($f['size'] ?? 0) > 25 * 1024 * 1024) throw new Exception('máximo 25 MB');
        $bytes = file_get_contents($f['tmp_name']); if ($bytes === false || $bytes === '') throw new Exception('arquivo vazio');
        $head = substr($bytes, 0, 8); $part = null;
        if (strncmp($head, '%PDF-', 5) === 0) $part = ['type' => 'file', 'file' => ['filename' => 'orcamento.pdf', 'file_data' => 'data:application/pdf;base64,' . base64_encode($bytes)]];
        elseif (strncmp($head, "\x89PNG\x0d\x0a\x1a\x0a", 8) === 0) $part = ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,' . base64_encode($bytes)]];
        elseif (strncmp($head, "\xFF\xD8\xFF", 3) === 0) $part = ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . base64_encode($bytes)]];
        elseif (strncmp($head, "PK\x03\x04", 4) === 0) { $txt = ia_xlsx_text($f['tmp_name']); if ($txt === '') throw new Exception('planilha não pôde ser lida — tente PDF/imagem'); $part = ['type' => 'text', 'text' => "PLANILHA:\n" . $txt]; }
        else throw new Exception('formato não aceito — envie PDF, Excel (xlsx) ou imagem (PNG/JPG)');
        $prompt = trim((string)($cfg['prompt_itens'] ?? '')) ?: oracle_itens_default_prompt();
        $instr = $prompt . "\n\nFORMATO DA RESPOSTA (JSON): {\"itens\":[{\"descricao\":\"<texto>\",\"unidade\":\"<UN/KG/...>\",\"quantidade\":<numero|null>,\"observacao\":\"<detalhe>\"}]}";
        $payload = ['model' => $model, 'temperature' => 0.1, 'max_tokens' => 3000, 'response_format' => ['type' => 'json_object'],
            'messages' => [['role' => 'user', 'content' => [['type' => 'text', 'text' => $instr], $part]]]];
        [$code, $res, $err] = oracle_post('https://api.openai.com/v1/chat/completions', $key, $payload);
        if ($err) throw new Exception('falha de conexão com a IA: ' . $err);
        $j = json_decode((string)$res, true);
        if ($code >= 400 || !$j) throw new Exception('IA: ' . ($j['error']['message'] ?? ('HTTP ' . $code)));
        $d = json_decode($j['choices'][0]['message']['content'] ?? '', true);
        $itens = (is_array($d) && isset($d['itens']) && is_array($d['itens'])) ? $d['itens'] : [];
        echo json_encode(['ok' => true, 'itens' => $itens, 'modelo' => $model], JSON_UNESCAPED_UNICODE); exit;
    }

    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $me = $in['me'] ?? null; $perms = user_perms($pdo, $me);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }
    if (($in['acao'] ?? '') !== 'preencher') throw new Exception('ação inválida');
    $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
    $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
    if (!can_edit_obra($perms, max(1, $obra))) { http_response_code(403); echo json_encode(['error' => 'Sem permissão de edição.']); exit; }

    $cfg = oracle_cfg(); $key = $cfg['key'] ?? '';
    if (!$key) { echo json_encode(['error' => 'IA não configurada — o admin precisa cadastrar a chave da OpenAI em Radar IA.']); exit; }
    $model  = trim((string)($cfg['model_extracao'] ?? '')) ?: 'gpt-4o';   // vision + leitura de PDF
    $prompt = trim((string)($cfg['prompt_extracao'] ?? '')) ?: oracle_extracao_default_prompt();

    // itens a cotar
    $iq = $pdo->prepare("SELECT id, descricao, unidade, quantidade, observacao FROM cotacao_item WHERE cotacao_id=? ORDER BY ordem, id");
    $iq->execute([$cid]); $itens = $iq->fetchAll();
    if (!$itens) throw new Exception('esta cotação não tem itens para preencher');
    $itensLista = array_map(fn($it) => ['item_id' => (int)$it['id'], 'descricao' => $it['descricao'], 'unidade' => $it['unidade'],
        'quantidade' => $it['quantidade'] !== null ? (float)$it['quantidade'] : null, 'obs' => $it['observacao']], $itens);

    // anexos selecionados — validados como DESTA cotação
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($in['anexo_ids'] ?? [])))));
    if (!$ids) throw new Exception('selecione ao menos um anexo');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $aq = $pdo->prepare("SELECT id, nome, arquivo, mime FROM cotacao_anexo WHERE cotacao_id=? AND id IN ($ph)");
    $aq->execute(array_merge([$cid], $ids)); $anexos = $aq->fetchAll();
    if (!$anexos) throw new Exception('anexos não encontrados nesta cotação');

    // conteúdo multimodal para a OpenAI
    $instr = $prompt . "\n\nITENS QUE ESTAMOS COTANDO (use o item_id na resposta):\n" . json_encode($itensLista, JSON_UNESCAPED_UNICODE)
        . "\n\nFORMATO DA RESPOSTA (JSON):\n"
        . '{"itens":[{"item_id":<id>,"preco_unit":<numero|null>,"observacao":"<texto>"}],"extras":[{"descricao":"<frete/imposto/etc>","valor":<numero|null>}],"equalizacao":[{"ponto":"<Frete|Condição de pagamento|Descarregamento|Imposto|...>","valor":"<texto curto>"}],"prazo_entrega":"<texto>","condicao_pagamento":"<texto>","validade":"<texto>","observacao_geral":"<texto>","confianca":"<alta|media|baixa>"}';
    $content = [['type' => 'text', 'text' => $instr]];
    $usados = []; $avisos = [];
    foreach ($anexos as $a) {
        $path = IA_ANEXO_DIR . '/' . basename((string)$a['arquivo']);
        if (!is_file($path)) { $avisos[] = $a['nome'] . ' (arquivo ausente)'; continue; }
        $mime = (string)$a['mime']; $bytes = file_get_contents($path);
        if ($bytes === false || $bytes === '') { $avisos[] = $a['nome']; continue; }
        if ($mime === 'image/png' || $mime === 'image/jpeg') {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . base64_encode($bytes)]];
            $usados[] = $a['nome'];
        } elseif ($mime === 'application/pdf') {
            $content[] = ['type' => 'file', 'file' => ['filename' => (preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$a['nome']) ?: 'anexo.pdf'), 'file_data' => 'data:application/pdf;base64,' . base64_encode($bytes)]];
            $usados[] = $a['nome'];
        } elseif (strpos($mime, 'spreadsheet') !== false || strpos($mime, 'ms-excel') !== false) {
            $txt = ia_xlsx_text($path);
            if ($txt !== '') { $content[] = ['type' => 'text', 'text' => "PLANILHA \"" . $a['nome'] . "\":\n" . $txt]; $usados[] = $a['nome']; }
            else $avisos[] = $a['nome'] . ' (planilha não pôde ser lida — tente PDF/print)';
        } else { $avisos[] = $a['nome'] . ' (formato não suportado pela IA)'; }
    }
    if (count($content) < 2) throw new Exception('nenhum anexo legível — envie PDF, imagem (PNG/JPG) ou xlsx');

    $payload = ['model' => $model, 'temperature' => 0.1, 'max_tokens' => 2000,
        'response_format' => ['type' => 'json_object'],
        'messages' => [['role' => 'user', 'content' => $content]]];
    [$code, $res, $err] = oracle_post('https://api.openai.com/v1/chat/completions', $key, $payload);
    if ($err) throw new Exception('falha de conexão com a IA: ' . $err);
    $j = json_decode((string)$res, true);
    if ($code >= 400 || !$j) { $msg = $j['error']['message'] ?? ('HTTP ' . $code); throw new Exception('IA: ' . $msg); }
    $txt = $j['choices'][0]['message']['content'] ?? '';
    $draft = json_decode($txt, true);
    if (!is_array($draft)) throw new Exception('a IA não devolveu um JSON válido');

    echo json_encode(['ok' => true, 'draft' => $draft, 'usados' => $usados, 'avisos' => $avisos, 'modelo' => $model], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
