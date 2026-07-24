<?php
/**
 * Ação EM LOTE nos itens do radar — v2 (barra de seleção do Radar + conferência de status).
 * Campos suportados: status, fornecedor, responsavel (este último SÓ admin/gerente).
 *
 * PERMISSÕES (decisão do Murilo, 21/jul/2026):
 *   admin/gerente → qualquer item. Comprador (editor) → SÓ itens onde ELE é o responsável
 *   (item de outro/sem dono é PULADO e contado em sem_permissao — não derruba o lote).
 * REVERSÍVEL: cada mudança vira linha no `historico` ("Status (lote)"/"Fornecedor (lote)"/"Responsável (lote)").
 * Idempotente: pula célula inexistente ou que já está no valor. dry:1 = preview sem gravar.
 *
 * POST JSON (novo):    { me, dry?, itens:[{obra_id, servico_id}], campos:{status?|fornecedor?|responsavel?} }
 * POST JSON (legado):  { me, dry?, itens:[{obra_id, servico_id, status}] }   — status por item (conferência v1)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
if (!function_exists('sup_nome_limpo')) { function sup_nome_limpo($s) { return trim(preg_replace('/\s+/u', ' ', (string)$s)); } }   // resiliência a deploy parcial (db.php pode chegar depois)

$STATUS_OK = ['Não Iniciado','Cotação Iniciada','Com Pendências','Em Andamento','Finalizado','Não se aplica'];
$LOTE_LABEL = ['status'=>'Status (lote)', 'fornecedor'=>'Fornecedor (lote)', 'responsavel'=>'Responsável (lote)'];

try {
    $in    = json_decode(file_get_contents('php://input'), true) ?: [];
    $me    = $in['me'] ?? null;
    $dry   = !empty($in['dry']);
    $itens = $in['itens'] ?? [];
    $campos = is_array($in['campos'] ?? null) ? $in['campos'] : null;   // null => modo legado (status por item)
    if (!is_array($itens) || !$itens) throw new Exception('itens vazio');
    if (count($itens) > 5000) throw new Exception('lote grande demais (max 5000)');

    $pdo = db();
    $perms = user_perms($pdo, $me);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error'=>'Não autorizado.'], JSON_UNESCAPED_UNICODE); exit; }
    $is_admin   = !empty($perms['perm_admin']);
    $is_gerente = (($perms['papel'] ?? '') === 'gerente');
    $euNome     = trim((string)($perms['nome'] ?? ''));
    $unome      = $perms['nome'] ?? ('Usuário ' . $me);

    // valida o PACOTE (modo novo) antes de tocar em qualquer linha
    if ($campos !== null) {
        $campos = array_intersect_key($campos, array_flip(['status','fornecedor','responsavel']));
        if (!$campos) throw new Exception('campos vazio (status/fornecedor/responsavel)');
        if (array_key_exists('status', $campos) && !in_array((string)$campos['status'], $STATUS_OK, true))
            throw new Exception('status inválido: "' . $campos['status'] . '"');
        if (array_key_exists('responsavel', $campos)) {
            if (!$is_admin && !$is_gerente) { http_response_code(403); echo json_encode(['error'=>'Atribuir responsável em lote é só para administrador/gerente.'], JSON_UNESCAPED_UNICODE); exit; }
            if (stripos((string)$campos['responsavel'], 'camila') !== false) throw new Exception('responsável inválido');
            $campos['responsavel'] = sup_nome_limpo((string)$campos['responsavel']);   // higiene de nome
        }
        if (array_key_exists('fornecedor', $campos)) $campos['fornecedor'] = sup_nome_limpo((string)$campos['fornecedor']);
    } else {
        foreach ($itens as $i => $it) {
            $st = (string)($it['status'] ?? '');
            if (!in_array($st, $STATUS_OK, true)) throw new Exception("status inválido no item $i: \"$st\"");
        }
    }

    // nomes dos serviços (p/ o histórico)
    $SN = [];
    foreach ($pdo->query("SELECT id, nome FROM servico")->fetchAll() as $s) $SN[(int)$s['id']] = $s['nome'];

    $sel = $pdo->prepare("SELECT status, fornecedor, responsavel FROM radar_item WHERE obra_id=? AND servico_id=?");
    $now = date('c');
    $aplicados = 0; $inexistente = 0; $sem_mudanca = 0; $sem_permissao = 0; $log = [];

    if (!$dry) $pdo->beginTransaction();
    foreach ($itens as $it) {
        $ob  = (int)($it['obra_id'] ?? 0);
        $sid = (int)($it['servico_id'] ?? 0);
        if ($ob < 1 || $sid < 1) { $inexistente++; continue; }
        $sel->execute([$ob, $sid]);
        $cur = $sel->fetch();
        if (!$cur) { $inexistente++; continue; }

        // ---- permissão POR ITEM: admin/gerente tudo; senão editor da obra E responsável pelo item ----
        if (!$is_admin && !$is_gerente) {
            // comprador só mexe no PRÓPRIO item (é o responsável) — NÃO exige edição de obra (dinâmica de comprador)
            $respItem = sup_nome_limpo((string)($cur['responsavel'] ?? ''));
            $euN = sup_nome_limpo($euNome);
            if ($respItem === '' || $euN === '' || strcasecmp($respItem, $euN) !== 0) { $sem_permissao++; continue; }
        }

        $alvos = $campos !== null ? $campos : ['status' => (string)$it['status']];
        $set = []; $vals = []; $mud = [];
        foreach ($alvos as $c => $v) {
            $v = trim((string)$v);
            if ((string)($cur[$c] ?? '') === $v) continue;   // já está no valor
            $set[] = "$c=?"; $vals[] = ($v === '' ? null : $v);
            $mud[$c] = ['de' => $cur[$c], 'para' => $v];
        }
        if (!$set) { $sem_mudanca++; continue; }
        $log[] = ['obra_id'=>$ob, 'servico_id'=>$sid] + array_map(fn($m) => $m['de'] . ' → ' . $m['para'], $mud);
        if (!$dry) {
            $set[] = "updated_at=?"; $vals[] = $now; $vals[] = $ob; $vals[] = $sid;
            $pdo->prepare("UPDATE radar_item SET " . implode(',', $set) . " WHERE obra_id=? AND servico_id=?")->execute($vals);
            foreach ($mud as $c => $m) log_historico($pdo, $ob, $sid, $SN[$sid] ?? '', $me, $unome, $LOTE_LABEL[$c] ?? $c, $m['de'], $m['para']);
        }
        $aplicados++;
    }
    if (!$dry) $pdo->commit();

    echo json_encode(['ok'=>true, 'dry'=>$dry, 'aplicados'=>$aplicados, 'sem_mudanca'=>$sem_mudanca,
                      'inexistente'=>$inexistente, 'sem_permissao'=>$sem_permissao, 'log'=>$log], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
