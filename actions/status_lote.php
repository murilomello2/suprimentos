<?php
/**
 * Atualização de STATUS em LOTE (admin) — aplica uma lista de {obra_id, servico_id, status} de uma vez.
 * REVERSÍVEL: cada mudança vira linha no `historico` (campo "Status (lote)", antes→depois), igual ao item_update.
 * Idempotente: pula célula que já está no status ou que não existe. dry:1 = preview sem gravar.
 * POST JSON: { me:<bitrix_id>, dry:0|1, itens:[{obra_id, servico_id, status}, ...] }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

$STATUS_OK = ['Não Iniciado','Cotação Iniciada','Com Pendências','Em Andamento','Finalizado','Não se aplica'];

try {
    $in    = json_decode(file_get_contents('php://input'), true) ?: [];
    $me    = $in['me'] ?? null;
    $dry   = !empty($in['dry']);
    $itens = $in['itens'] ?? [];
    if (!is_array($itens) || !$itens) throw new Exception('itens vazio');
    if (count($itens) > 5000) throw new Exception('lote grande demais (max 5000)');

    $pdo = db();
    $perms = user_perms($pdo, $me);
    if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Apenas administradores.'], JSON_UNESCAPED_UNICODE); exit; }
    $unome = $perms['nome'] ?? ('Usuário ' . $me);

    // valida TODOS os statuses antes de tocar em qualquer linha
    foreach ($itens as $i => $it) {
        $st = (string)($it['status'] ?? '');
        if (!in_array($st, $STATUS_OK, true)) throw new Exception("status inválido no item $i: \"$st\"");
    }

    // nomes dos serviços (p/ o histórico)
    $SN = [];
    foreach ($pdo->query("SELECT id, nome FROM servico")->fetchAll() as $s) $SN[(int)$s['id']] = $s['nome'];

    $sel = $pdo->prepare("SELECT status FROM radar_item WHERE obra_id=? AND servico_id=?");
    $upd = $pdo->prepare("UPDATE radar_item SET status=?, updated_at=? WHERE obra_id=? AND servico_id=?");
    $now = date('c');
    $aplicados = 0; $inexistente = 0; $sem_mudanca = 0; $log = [];

    if (!$dry) $pdo->beginTransaction();
    foreach ($itens as $it) {
        $ob  = (int)($it['obra_id'] ?? 0);
        $sid = (int)($it['servico_id'] ?? 0);
        $st  = (string)$it['status'];
        if ($ob < 1 || $sid < 1) { $inexistente++; continue; }
        $sel->execute([$ob, $sid]);
        $cur = $sel->fetchColumn();
        if ($cur === false) { $inexistente++; continue; }          // célula não existe nessa obra
        if ((string)$cur === $st) { $sem_mudanca++; continue; }     // já está no status alvo
        $log[] = ['obra_id'=>$ob, 'servico_id'=>$sid, 'de'=>$cur, 'para'=>$st];
        if (!$dry) {
            $upd->execute([$st, $now, $ob, $sid]);
            log_historico($pdo, $ob, $sid, $SN[$sid] ?? '', $me, $unome, 'Status (lote)', $cur, $st);
        }
        $aplicados++;
    }
    if (!$dry) $pdo->commit();

    echo json_encode(['ok'=>true, 'dry'=>$dry, 'aplicados'=>$aplicados,
                      'sem_mudanca'=>$sem_mudanca, 'inexistente'=>$inexistente,
                      'log'=>$log], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
