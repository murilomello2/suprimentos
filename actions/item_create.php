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

    if ($acao === 'excluir') {
        $ordem = (int)($in['ordem'] ?? 0);
        $pdo->prepare("DELETE FROM radar_item WHERE obra_id=1 AND servico_id=?")->execute([$ordem]);
        $pdo->prepare("DELETE FROM servico WHERE id=?")->execute([$ordem]);
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
    echo json_encode(['ok'=>true, 'ordem'=>$ordem]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
