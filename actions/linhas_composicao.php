<?php
/**
 * Converte um conjunto de LINHAS-folha do orçamento nos INSUMOS de composição (só material OU só MO).
 * Usado pela busca em massa pra deixar escolher "o que entra na verba": linha inteira × só material × só MO.
 * GET: ids=1,2,3   tipo=material|mo
 * Retorna: { composicao_sel:[{cid,idx,area,q,locais:[{id,q}],desc,tipo,unidade,coef,rs_unit,compdesc}],
 *            resumo:{valor,n_composicoes,n_insumos,sem_composicao:[...]} }
 *   idx = posição do insumo na composição (ORDER BY id) — bate com COMP_DATA.insumos[idx] do front.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();
    $ids  = array_values(array_filter(array_map('intval', explode(',', $_GET['ids'] ?? ''))));
    $tipo = ($_GET['tipo'] ?? 'material') === 'mo' ? 'mo' : 'material';
    if (!$ids) { echo json_encode(['composicao_sel'=>[], 'resumo'=>['valor'=>0,'n_composicoes'=>0,'n_insumos'=>0,'sem_composicao'=>[]]]); exit; }

    $in = implode(',', $ids);
    $linhas = $pdo->query("SELECT id, obra_id, descricao, qtde FROM orcamento_linha WHERE id IN ($in)")->fetchAll();

    // multi-obra: a obra vem das PRÓPRIAS linhas (ids já são únicos entre obras) — a composição tem que ser da mesma
    $OBRA = $linhas ? (int)($linhas[0]['obra_id'] ?: 1) : 1;
    $descs = array_values(array_unique(array_map(function($l){ return $l['descricao']; }, $linhas)));
    $compByDesc = [];
    if ($descs) {
        $ph = implode(',', array_fill(0, count($descs), '?'));
        $q = $pdo->prepare("SELECT id, descricao FROM composicao WHERE obra_id=? AND descricao IN ($ph)");
        $q->execute(array_merge([$OBRA], $descs));
        foreach ($q->fetchAll() as $c) $compByDesc[$c['descricao']] = (int)$c['id'];
    }
    $cids = array_values(array_unique(array_values($compByDesc)));
    $insByCid = [];
    if ($cids) {
        $inC = implode(',', array_map('intval', $cids));
        foreach ($pdo->query("SELECT composicao_id, id, descricao, tipo, coef, rs_unit, unidade
                              FROM composicao_insumo WHERE composicao_id IN ($inC) ORDER BY composicao_id, id")->fetchAll() as $r)
            $insByCid[$r['composicao_id']][] = $r;
    }
    $linhasByCid = []; $semComp = [];
    foreach ($linhas as $l) {
        $cid = $compByDesc[$l['descricao']] ?? null;
        if ($cid) $linhasByCid[$cid][] = $l; else $semComp[] = $l['descricao'];
    }

    $compSel = []; $valor = 0.0;
    foreach ($linhasByCid as $cid => $ls) {
        $locais = array_map(function($l){ return ['id'=>(int)$l['id'], 'q'=>(float)$l['qtde']]; }, $ls);
        $area   = array_sum(array_map(function($l){ return (float)$l['qtde']; }, $ls));
        foreach (($insByCid[$cid] ?? []) as $idx => $ins) {
            if ($ins['tipo'] !== $tipo) continue;   // só a classe EXATA pedida (material OU mo)
            $val = $area * (float)$ins['coef'] * (float)$ins['rs_unit'];
            $compSel[] = ['cid'=>(int)$cid, 'idx'=>$idx, 'area'=>$area, 'q'=>0, 'locais'=>$locais,
                          'desc'=>$ins['descricao'], 'tipo'=>$ins['tipo'], 'unidade'=>$ins['unidade'],
                          'coef'=>(float)$ins['coef'], 'rs_unit'=>(float)$ins['rs_unit'], 'compdesc'=>$ls[0]['descricao']];
            $valor += $val;
        }
    }

    echo json_encode(['composicao_sel'=>$compSel, 'resumo'=>[
        'valor'=>$valor, 'n_composicoes'=>count($linhasByCid), 'n_insumos'=>count($compSel),
        'sem_composicao'=>array_values(array_unique($semComp))
    ]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
