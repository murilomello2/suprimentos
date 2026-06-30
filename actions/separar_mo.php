<?php
/**
 * "Separar material × MO": converte a verba ANALÍTICA de um item (linhas inteiras = material + MO)
 * em verba por COMPOSIÇÃO contendo SÓ os insumos de MATERIAL — liberando a MO (encanador, ajudante…)
 * que estava embutida, pra poder ir pra outro item (ex.: "MO de instalações hidráulicas").
 * GET ?ordem=<servico_id>  -> retorna o plano (composicao_sel só-material) + resumo antes/depois (NÃO aplica).
 *   composicao_sel: [{cid,idx,area,q,locais,desc,tipo,unidade,coef,rs_unit,compdesc}]  (idx = pos. ORDER BY id)
 *   resumo: {verba_antes, verba_depois, mo_liberada, n_linhas, n_composicoes, n_insumos_mat, sem_composicao:[...]}
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();
    $ordem = (int)($_GET['ordem'] ?? 0);
    $manter = ($_GET['manter'] ?? 'material') === 'mo' ? 'mo' : 'material'; // o que FICA no item (o resto é liberado)
    $st = $pdo->prepare("SELECT orcamento_refs FROM radar_item WHERE servico_id=? AND obra_id=1");
    $st->execute([$ordem]);
    $refs = json_decode($st->fetchColumn() ?: '[]', true) ?: [];
    if (!$refs) { echo json_encode(['error'=>'Esse item não tem verba analítica (linhas do orçamento) pra separar.']); exit; }

    $in = implode(',', array_map('intval', $refs));
    $linhas = $pdo->query("SELECT id, descricao, qtde, valor FROM orcamento_linha WHERE id IN ($in)")->fetchAll();
    $verbaAntes = array_sum(array_map(function($l){ return (float)$l['valor']; }, $linhas));

    // linha -> composição (por descrição)
    $descs = array_values(array_unique(array_map(function($l){ return $l['descricao']; }, $linhas)));
    $compByDesc = [];
    if ($descs) {
        $ph = implode(',', array_fill(0, count($descs), '?'));
        $q = $pdo->prepare("SELECT id, descricao FROM composicao WHERE descricao IN ($ph)");
        $q->execute($descs);
        foreach ($q->fetchAll() as $c) $compByDesc[$c['descricao']] = (int)$c['id'];
    }
    // insumos por composição (idx = posição ORDER BY id)
    $cids = array_values(array_unique(array_values($compByDesc)));
    $insByCid = [];
    if ($cids) {
        $inC = implode(',', array_map('intval', $cids));
        foreach ($pdo->query("SELECT composicao_id, id, descricao, tipo, coef, rs_unit, unidade
                              FROM composicao_insumo WHERE composicao_id IN ($inC) ORDER BY composicao_id, id")->fetchAll() as $r)
            $insByCid[$r['composicao_id']][] = $r;
    }
    // agrupa linhas por composição
    $linhasByCid = []; $semComp = [];
    foreach ($linhas as $l) {
        $cid = $compByDesc[$l['descricao']] ?? null;
        if ($cid) $linhasByCid[$cid][] = $l; else $semComp[] = $l['descricao'];
    }

    // monta composicao_sel só com os insumos MATERIAL
    $compSel = []; $verbaDepois = 0.0;
    foreach ($linhasByCid as $cid => $ls) {
        $locais = array_map(function($l){ return (int)$l['id']; }, $ls);
        $area   = array_sum(array_map(function($l){ return (float)$l['qtde']; }, $ls));
        foreach (($insByCid[$cid] ?? []) as $idx => $ins) {
            if ($ins['tipo'] !== $manter) continue;   // mantém SÓ a classe exata (material OU mo); mat_mo/equip ficam de fora
            $val = $area * (float)$ins['coef'] * (float)$ins['rs_unit'];
            $compSel[] = ['cid'=>(int)$cid, 'idx'=>$idx, 'area'=>$area, 'q'=>0, 'locais'=>$locais,
                          'desc'=>$ins['descricao'], 'tipo'=>$ins['tipo'], 'unidade'=>$ins['unidade'],
                          'coef'=>(float)$ins['coef'], 'rs_unit'=>(float)$ins['rs_unit'], 'compdesc'=>$ls[0]['descricao']];
            $verbaDepois += $val;
        }
    }

    echo json_encode(['composicao_sel'=>$compSel, 'manter'=>$manter, 'resumo'=>[
        'verba_antes'=>$verbaAntes, 'verba_depois'=>$verbaDepois,
        'mo_liberada'=>$verbaAntes - $verbaDepois, 'liberada'=>$verbaAntes - $verbaDepois,
        'n_linhas'=>count($linhas), 'n_composicoes'=>count($linhasByCid), 'n_insumos_mat'=>count($compSel),
        'sem_composicao'=>array_values(array_unique($semComp))
    ]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
