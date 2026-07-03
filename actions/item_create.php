<?php
/**
 * Cria itens no radar.
 * POST JSON:
 *   {acao:"novo", nome, grupo, tipo, curva, copy_from?}     -> 1 item
 *   {acao:"desdobrar", ordem}                                -> 2 itens (MAT) e (MO) a partir do item
 *   {acao:"excluir", ordem}                                  -> remove o item
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

$SUFIXO = ['Material'=>'(MAT)', 'Mão de obra'=>'(MO)', 'Material + MO'=>'(MAT + MO)', 'Empreitada'=>'(EMP)', 'Locação'=>'(LOC)'];
function base_nome($n){ return trim(preg_replace('/\s*\((MAT|MO|EMP|LOC|MAT \+ MO)\)\s*$/u','',$n)); }

try {
    $pdo = db(); db_seed_if_empty();
    $in = json_decode(file_get_contents('php://input'), true);
    $acao = $in['acao'] ?? 'novo';
    $me = $in['me'] ?? null;
    $OBRA = max(1, (int)($in['obra'] ?? 1));   // multi-obra (criação replica p/ todas; responsável/exclusão usam a obra)

    // ---- PERMISSÃO (servidor): só cria/altera quem tem escopo de edição ----
    $perms = user_perms($pdo, $me);
    if (!can_edit_obra($perms, $OBRA)) {
        http_response_code(403);
        echo json_encode(['error'=>'Sem permissão de edição.'], JSON_UNESCAPED_UNICODE); exit;
    }
    $pdo->beginTransaction(); // criação/exclusão + histórico atômicos (evita item duplicado em retry)

    if ($acao === 'excluir') {
        $ordem = (int)($in['ordem'] ?? 0);
        $nm = $pdo->prepare("SELECT nome FROM servico WHERE id=?"); $nm->execute([$ordem]); $nome = $nm->fetchColumn() ?: ('#'.$ordem);
        $pdo->prepare("DELETE FROM radar_item WHERE servico_id=?")->execute([$ordem]);   // catálogo: sai de TODAS as obras
        $pdo->prepare("DELETE FROM servico WHERE id=?")->execute([$ordem]);
        log_historico($pdo, $OBRA, $ordem, $nome, $me, $perms['nome'], 'Item excluído', $nome, '');
        $pdo->commit();
        echo json_encode(['ok'=>true]); exit;
    }

    if ($acao === 'desdobrar') {
        global $SUFIXO;
        $ordem = (int)($in['ordem'] ?? 0);
        $s = $pdo->prepare("SELECT * FROM servico WHERE id=?"); $s->execute([$ordem]);
        $src = $s->fetch();
        if (!$src) throw new Exception('item de origem não encontrado');
        $base = base_nome($src['nome']);
        $o1 = criar_item($pdo, "$base (MAT)", $src['grupo'], 'Material', $src['curva'], $ordem);
        $o2 = criar_item($pdo, "$base (MO)",  $src['grupo'], 'Mão de obra', $src['curva'], $ordem);
        log_historico($pdo, 1, $ordem, $src['nome'], $me, $perms['nome'], 'Desdobrado em Material + MO', $src['nome'], "$base (MAT) + $base (MO)");
        $pdo->commit();
        echo json_encode(['ok'=>true, 'criados'=>[$o1,$o2]]); exit;
    }

    // novo
    $nome  = trim($in['nome'] ?? '');
    $grupo = trim($in['grupo'] ?? '');
    $tipo  = trim($in['tipo'] ?? '');
    $curva = trim($in['curva'] ?? '');
    if ($nome === '' || $grupo === '') throw new Exception('nome e grupo são obrigatórios');
    // anexa sufixo do tipo se ainda não tiver
    if ($tipo && isset($SUFIXO[$tipo]) && !preg_match('/\((MAT|MO|EMP|MAT \+ MO)\)\s*$/u', $nome)) {
        $nome .= ' ' . $SUFIXO[$tipo];
    }
    $ordem = criar_item($pdo, $nome, $grupo, $tipo, $curva, $in['copy_from'] ?? null);
    $resp = trim($in['responsavel'] ?? '');
    if ($resp !== '') {
        if (stripos($resp, 'camila') !== false) throw new Exception('responsável inválido'); // mesma regra do item_update
        $pdo->prepare("UPDATE radar_item SET responsavel=? WHERE obra_id=? AND servico_id=?")
            ->execute([$resp, $OBRA, $ordem]);
    }
    log_historico($pdo, $OBRA, $ordem, $nome, $me, $perms['nome'], 'Item criado', '', $nome.' · grupo '.$grupo);
    $pdo->commit();
    echo json_encode(['ok'=>true, 'ordem'=>$ordem]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
