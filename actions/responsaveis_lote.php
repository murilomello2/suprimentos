<?php
/**
 * Atribuição de RESPONSÁVEL (comprador) EM LOTE + REGRA PADRÃO — parte administrativa.
 * Permissão validada NO SERVIDOR: perm_admin OU perm_responsaveis (capacidade),
 * e o ESCOPO da obra ainda exige can_edit_obra (mesma invariante de item_update).
 * O responsável é gravado como NOME (mesmo formato do modal / respOptions).
 *
 * POST {acao:'atribuir', me, obra, servico_ids:[...], responsavel, tornar_padrao?}  // responsavel='' => limpa
 *      tornar_padrao=1 também grava servico.responsavel_padrao (template — novas obras herdam).
 *      Definir padrão é GLOBAL → exige admin OU editar_escopo='todas'.
 * POST {acao:'preencher_padrao', me, obra}  // preenche os VAZIOS da obra com o responsavel_padrao do serviço
 *   -> { ok, n, padrao?, obra, responsavel? }
 */
header('Content-Type: application/json; charset=utf-8');
set_time_limit(120);
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $perms = user_perms($pdo, $in['me'] ?? null);
    if (empty($perms['perm_admin']) && empty($perms['perm_responsaveis'])) {
        http_response_code(403);
        echo json_encode(['error'=>'Sem permissão para atribuir responsáveis em lote.']); exit;
    }
    $acao = $in['acao'] ?? '';
    $obra = (int)($in['obra'] ?? 0);
    if ($obra < 1) throw new Exception('obra obrigatória');
    $chk = $pdo->prepare("SELECT COUNT(*) FROM obra WHERE id=?"); $chk->execute([$obra]);
    if (!(int)$chk->fetchColumn()) throw new Exception('obra não encontrada');
    // ESCOPO por obra (admin e editar_escopo='todas' passam; 'sel' preso a obras_editar)
    if (empty($perms['perm_admin']) && !can_edit_obra($perms, $obra)) {
        http_response_code(403);
        echo json_encode(['error'=>'Sem permissão de edição nesta obra.']); exit;
    }

    // ---- PREENCHER VAZIOS com o padrão do serviço ----
    if ($acao === 'preencher_padrao') {
        $rows = $pdo->prepare("SELECT r.servico_id, s.responsavel_padrao, s.nome
                               FROM radar_item r JOIN servico s ON s.id = r.servico_id
                               WHERE r.obra_id = ? AND (r.responsavel IS NULL OR r.responsavel = '')
                                 AND COALESCE(s.responsavel_padrao,'') <> ''");
        $rows->execute([$obra]); $rows = $rows->fetchAll();
        $pdo->beginTransaction();
        $up = $pdo->prepare("UPDATE radar_item SET responsavel = ?, updated_at = ? WHERE obra_id = ? AND servico_id = ?");
        $n = 0; $now = date('c');
        foreach ($rows as $r) {
            $rp = (string)$r['responsavel_padrao'];
            $up->execute([$rp, $now, $obra, (int)$r['servico_id']]);
            log_historico($pdo, $obra, (int)$r['servico_id'], $r['nome'], $in['me'] ?? null, $perms['nome'] ?? null,
                          'Responsável', '—', $rp);
            $n++;
        }
        $pdo->commit();
        echo json_encode(['ok'=>true, 'n'=>$n, 'obra'=>$obra], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao !== 'atribuir') throw new Exception('acao inválida');

    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($in['servico_ids'] ?? [])))));
    if (!$ids) throw new Exception('nenhum item selecionado');
    $resp = trim((string)($in['responsavel'] ?? ''));   // '' = limpar
    $tornarPadrao = !empty($in['tornar_padrao']);
    if ($tornarPadrao) {   // padrão é GLOBAL (template): exige escopo amplo
        $podePadrao = !empty($perms['perm_admin']) || (($perms['editar_escopo'] ?? '') === 'todas');
        if (!$podePadrao) { http_response_code(403); echo json_encode(['error'=>'Definir padrão exige acesso a todas as obras (ou admin).']); exit; }
    }

    // estado atual (p/ log antes→depois e pular no-op) + nome do serviço
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $cur = $pdo->prepare("SELECT r.servico_id, r.responsavel, s.nome
                          FROM radar_item r JOIN servico s ON s.id = r.servico_id
                          WHERE r.obra_id = ? AND r.servico_id IN ($ph)");
    $cur->execute(array_merge([$obra], $ids));
    $rows = $cur->fetchAll();

    $pdo->beginTransaction();
    $up = $pdo->prepare("UPDATE radar_item SET responsavel = ?, updated_at = ? WHERE obra_id = ? AND servico_id = ?");
    $n = 0; $now = date('c');
    foreach ($rows as $r) {
        $antes = (string)($r['responsavel'] ?? '');
        if ($antes === $resp) continue;   // sem mudança → não grava nem loga
        $up->execute([$resp !== '' ? $resp : null, $now, $obra, (int)$r['servico_id']]);
        log_historico($pdo, $obra, (int)$r['servico_id'], $r['nome'], $in['me'] ?? null, $perms['nome'] ?? null,
                      'Responsável', $antes !== '' ? $antes : '—', $resp !== '' ? $resp : '—');
        $n++;
    }
    // REGRA PADRÃO (template, novas obras herdam) — obra_id=0 no histórico
    $padN = 0;
    if ($tornarPadrao) {
        $upP = $pdo->prepare("UPDATE servico SET responsavel_padrao = ? WHERE id = ?");
        foreach ($rows as $r) {
            $upP->execute([$resp !== '' ? $resp : null, (int)$r['servico_id']]);
            log_historico($pdo, 0, (int)$r['servico_id'], $r['nome'], $in['me'] ?? null, $perms['nome'] ?? null,
                          'Responsável padrão', '', $resp !== '' ? $resp : '—');
            $padN++;
        }
    }
    $pdo->commit();
    echo json_encode(['ok'=>true, 'n'=>$n, 'padrao'=>$padN, 'obra'=>$obra, 'responsavel'=>$resp], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
