<?php
/**
 * Locais de uma composição = as linhas-folha do orçamento que usam essa composição
 * (mesma descrição), agrupadas pelo local de 1º nível do caminho (Torre 1, Serviços Externos, …).
 * Serve pra o usuário marcar/desmarcar de quais locais a verba/quantitativo daquele item vem.
 * GET ?id=<composicao_id>  -> { descricao, total, grupos:[{local, qtde, valor, linhas:[{id,sub,qtde,valor}]}] }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();
    $id  = (int)($_GET['id'] ?? 0);
    $c   = $pdo->prepare("SELECT descricao, obra_id FROM composicao WHERE id=?");
    $c->execute([$id]);
    $comp = $c->fetch();
    if (!$comp) { echo json_encode(['grupos'=>[], 'total'=>0]); exit; }
    $desc = $comp['descricao'];
    $obraId = (int)($comp['obra_id'] ?: 1);   // multi-obra: o casamento por descrição NÃO pode vazar pra outra obra

    // linhas-folha do orçamento DA MESMA OBRA com a MESMA descrição (cada uma = a composição aplicada num local)
    $st = $pdo->prepare("SELECT id, path_str, qtde, valor, unidade FROM orcamento_linha WHERE obra_id=? AND descricao=? AND folha=1 ORDER BY path_str");
    $st->execute([$obraId, $desc]);
    $rows = $st->fetchAll();

    $grupos = []; $total = 0.0;
    foreach ($rows as $r) {
        $parts = array_map('trim', explode('›', (string)($r['path_str'] ?? '')));
        $local = $parts[0] !== '' ? $parts[0] : '(sem local)';
        $sub   = count($parts) > 1 ? implode(' › ', array_slice($parts, 1)) : $local;
        $un    = $r['unidade'] ?? '';
        if (!isset($grupos[$local])) $grupos[$local] = ['local'=>$local, 'qtde'=>0.0, 'valor'=>0.0, 'unidade'=>$un, 'linhas'=>[]];
        $grupos[$local]['linhas'][] = ['id'=>(int)$r['id'], 'sub'=>$sub, 'qtde'=>(float)$r['qtde'], 'valor'=>(float)$r['valor'], 'unidade'=>$un];
        $grupos[$local]['qtde']  += (float)$r['qtde'];
        $grupos[$local]['valor'] += (float)$r['valor'];
        $total += (float)$r['qtde'];
    }
    echo json_encode(['descricao'=>$desc, 'total'=>$total, 'grupos'=>array_values($grupos)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
