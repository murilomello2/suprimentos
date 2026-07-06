<?php
/**
 * Atribuição de RESPONSÁVEL (comprador) EM LOTE — parte administrativa.
 * Permissão validada NO SERVIDOR: perm_admin OU perm_responsaveis.
 * O responsável é gravado como NOME (mesmo formato do modal / respOptions).
 *
 * POST {acao:'atribuir', me, obra, servico_ids:[...], responsavel}   // responsavel='' => limpa
 *   -> { ok, n, obra, responsavel }
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
    if (($in['acao'] ?? '') !== 'atribuir') throw new Exception('acao inválida');

    $obra = (int)($in['obra'] ?? 0);
    if ($obra < 1) throw new Exception('obra obrigatória');
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($in['servico_ids'] ?? [])))));
    if (!$ids) throw new Exception('nenhum item selecionado');
    $resp = trim((string)($in['responsavel'] ?? ''));   // '' = limpar

    $chk = $pdo->prepare("SELECT COUNT(*) FROM obra WHERE id=?"); $chk->execute([$obra]);
    if (!(int)$chk->fetchColumn()) throw new Exception('obra não encontrada');

    // ESCOPO por obra: perm_responsaveis libera a CAPACIDADE, mas a obra ainda precisa estar no
    // escopo de edição do usuário (mesma invariante de item_update/item_create — admin e
    // editar_escopo='todas' passam direto; 'sel' fica preso a obras_editar).
    if (empty($perms['perm_admin']) && !can_edit_obra($perms, $obra)) {
        http_response_code(403);
        echo json_encode(['error'=>'Sem permissão de edição nesta obra.']); exit;
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
    $pdo->commit();
    echo json_encode(['ok'=>true, 'n'=>$n, 'obra'=>$obra, 'responsavel'=>$resp], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
