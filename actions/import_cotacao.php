<?php
/**
 * IMPORTAÇÃO de cotações do sistema ANTIGO (Caprem Mapa de Cotações / Supabase) para o novo.
 * Recebe a cotação já NORMALIZADA (o assistente lê o conector e monta o payload) e recria tudo:
 * cotação (PRESERVANDO created_at) + itens + fornecedores + propostas + itens-da-proposta (com observação por item×fornecedor).
 * Dedup por cotacao.import_origem (o id antigo). Admin-only.
 *
 * POST JSON {
 *   me, forcar?,
 *   cotacao:{titulo,descricao,obra_nome,categoria,tipo_servico,verba,criado_nome,created_at,status,origem_id},
 *   itens:[{_oldid,descricao,unidade,quantidade,observacao}],
 *   propostas:[{fornecedor_nome,cnpj,email,telefone,cidade,contato,total,observacoes?,itens:[{_item_oldid,preco_unit,preco_total,observacao}]}]
 * }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $perms = user_perms($pdo, $in['me'] ?? null);
    if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error' => 'Apenas administradores importam.']); exit; }

    $c = $in['cotacao'] ?? []; $itens = $in['itens'] ?? []; $propostas = $in['propostas'] ?? [];
    $origem = trim((string)($c['origem_id'] ?? '')); if ($origem === '') throw new Exception('cotacao.origem_id obrigatório');

    // DEDUP: se já importada, não duplica (a menos que forcar=1)
    $ex = $pdo->prepare("SELECT id FROM cotacao WHERE import_origem=? LIMIT 1"); $ex->execute([$origem]);
    $jaId = (int)($ex->fetchColumn() ?: 0);
    if ($jaId && empty($in['forcar'])) { echo json_encode(['ok' => true, 'ja_importada' => true, 'cotacao_id' => $jaId], JSON_UNESCAPED_UNICODE); exit; }

    $created = trim((string)($c['created_at'] ?? '')) ?: date('c');            // PRESERVA a data original
    $status  = (($c['status'] ?? '') === 'finalizada') ? 'finalizada' : 'aberta';

    $pdo->beginTransaction();
    if ($jaId && !empty($in['forcar'])) {   // reimportar: apaga o import anterior (cotação + filhos) e recomeça limpo
        $props = $pdo->query("SELECT id FROM cotacao_proposta WHERE cotacao_id=" . $jaId)->fetchAll(PDO::FETCH_COLUMN);
        if ($props) { $ph = implode(',', array_map('intval', $props)); $pdo->exec("DELETE FROM cotacao_proposta_item WHERE proposta_id IN ($ph)"); }
        $pdo->exec("DELETE FROM cotacao_proposta WHERE cotacao_id=" . $jaId);
        $pdo->exec("DELETE FROM cotacao_fornecedor WHERE cotacao_id=" . $jaId);
        $pdo->exec("DELETE FROM cotacao_item WHERE cotacao_id=" . $jaId);
        $pdo->exec("DELETE FROM cotacao_anexo WHERE cotacao_id=" . $jaId . " AND criado_por='__IMPORT__'");
        $pdo->exec("DELETE FROM cotacao WHERE id=" . $jaId);
    }
    $pdo->prepare("INSERT INTO cotacao (obra_id, servico_id, titulo, categoria, tipo_servico, verba, verba_origem, descricao, status, aprovacao, criado_por, criado_nome, obra_livre, import_origem, created_at, updated_at) VALUES (NULL,NULL,?,?,?,?, 'definida', ?,?, 'aguardando', ?,?,?,?,?,?)")
        ->execute([(string)($c['titulo'] ?? ''), (string)($c['categoria'] ?? ''), (string)($c['tipo_servico'] ?? ''),
            ($c['verba'] ?? null) !== null ? (float)$c['verba'] : null, (string)($c['descricao'] ?? ''), $status,
            (string)($in['me'] ?? ''), (string)($c['criado_nome'] ?? ''), (string)($c['obra_nome'] ?? ''), $origem, $created, $created]);
    $cid = (int)$pdo->lastInsertId();

    // itens (map _oldid -> novo id)
    $itemMap = [];
    $insI = $pdo->prepare("INSERT INTO cotacao_item (cotacao_id, descricao, unidade, quantidade, observacao, ordem) VALUES (?,?,?,?,?,?)");
    $o = 0;
    foreach ($itens as $it) {
        $insI->execute([$cid, (string)($it['descricao'] ?? ''), (string)($it['unidade'] ?? ''),
            ($it['quantidade'] ?? null) !== null ? (float)$it['quantidade'] : null, (string)($it['observacao'] ?? ''), $o++]);
        $nid = (int)$pdo->lastInsertId(); if (!empty($it['_oldid'])) $itemMap[(string)$it['_oldid']] = $nid;
    }

    // fornecedores (find-or-create por NOME — mantém "Aço Rio" e "Aço Rio 2" distintos, como no mapa antigo) + propostas + itens-da-proposta
    $findF = $pdo->prepare("SELECT id FROM cot_fornecedor WHERE LOWER(nome)=LOWER(?) LIMIT 1");
    $insF  = $pdo->prepare("INSERT INTO cot_fornecedor (nome, categoria, cidade, contato, telefone, email, cnpj, ativo, created_at) VALUES (?,?,?,?,?,?,?,1,?)");
    $insConv = $pdo->prepare("INSERT INTO cotacao_fornecedor (cotacao_id, fornecedor_id, fornecedor_nome, categoria, contato, email, telefone, created_at) VALUES (?,?,?,?,?,?,?,?)");
    $insProp = $pdo->prepare("INSERT INTO cotacao_proposta (cotacao_id, fornecedor_id, fornecedor_nome, observacoes, total, data_resposta, created_at) VALUES (?,?,?,?,?,?,?)");
    $insPI   = $pdo->prepare("INSERT INTO cotacao_proposta_item (proposta_id, cotacao_item_id, preco_unit, preco_total, observacao) VALUES (?,?,?,?,?)");
    $insAnx  = $pdo->prepare("INSERT INTO cotacao_anexo (cotacao_id, fornecedor_id, fornecedor_nome, nome, arquivo, url, tamanho, mime, criado_por, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $nForn = 0; $nProp = 0; $nObs = 0; $nAnx = 0;
    foreach ($propostas as $p) {
        $fnome = trim((string)($p['fornecedor_nome'] ?? '')); if ($fnome === '') continue;
        $findF->execute([$fnome]); $fid = (int)($findF->fetchColumn() ?: 0);
        if (!$fid) {
            $insF->execute([$fnome, (string)($c['categoria'] ?? ''), (string)($p['cidade'] ?? ''), (string)($p['contato'] ?? ''),
                (string)($p['telefone'] ?? ''), (string)($p['email'] ?? ''), (string)($p['cnpj'] ?? ''), $created]);
            $fid = (int)$pdo->lastInsertId(); $nForn++;
        }
        $insConv->execute([$cid, $fid, $fnome, (string)($c['categoria'] ?? ''), (string)($p['contato'] ?? ''), (string)($p['email'] ?? ''), (string)($p['telefone'] ?? ''), $created]);
        $total = ($p['total'] ?? null) !== null ? (float)$p['total'] : null;
        $insProp->execute([$cid, $fid, $fnome, (string)($p['observacoes'] ?? ''), $total, $created, $created]);
        $pid = (int)$pdo->lastInsertId(); $nProp++;
        foreach (($p['itens'] ?? []) as $pi) {
            $ciid = $itemMap[(string)($pi['_item_oldid'] ?? '')] ?? 0; if (!$ciid) continue;
            $obs = (string)($pi['observacao'] ?? ''); if ($obs !== '') $nObs++;
            $insPI->execute([$pid, $ciid, ($pi['preco_unit'] ?? null) !== null ? (float)$pi['preco_unit'] : null,
                ($pi['preco_total'] ?? null) !== null ? (float)$pi['preco_total'] : null, $obs]);
        }
        // anexos da proposta (PDF por LINK — do storage antigo). 1 registro por PDF único do fornecedor.
        foreach (($p['anexos'] ?? []) as $ax) {
            $url = trim((string)($ax['url'] ?? '')); if ($url === '') continue;
            $insAnx->execute([$cid, $fid, $fnome, (string)($ax['nome'] ?? 'anexo.pdf'), '', $url, (int)($ax['tamanho'] ?? 0), (string)($ax['mime'] ?? 'application/pdf'), '__IMPORT__', $created]);
            $nAnx++;
        }
    }
    $pdo->commit();
    echo json_encode(['ok' => true, 'cotacao_id' => $cid, 'itens' => count($itemMap), 'fornecedores_novos' => $nForn,
        'propostas' => $nProp, 'observacoes' => $nObs, 'anexos' => $nAnx, 'created_at' => $created, 'substituiu' => $jaId ?: null], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500); echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
