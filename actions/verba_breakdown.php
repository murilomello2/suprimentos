<?php
/**
 * Quebra a verba de um item em MATERIAL / MO / EQUIPAMENTO / MATERIAL+MO, mesmo quando ela foi
 * definida por ORÇAMENTO ANALÍTICO (linha inteira). Resolve cada linha -> a composição dela -> insumos.
 * GET ?ordem=<servico_id>
 * Retorna:
 *   tot_por_tipo {material,mo,mat_mo,equip}, total
 *   linhas: [{id, descricao, path, valor, sem_composicao, tot_por_tipo,
 *             insumos:[{desc,tipo,unidade,qtde,rs_unit,valor}]}]    (verba analítica → por LINHA)
 *   por_tipo: { material:[{desc,unidade,qtde,valor}], mo:[...], mat_mo:[...], equip:[...] }  (agregado de tudo)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();
    $ordem = (int)($_GET['ordem'] ?? 0);
    $st = $pdo->prepare("SELECT tipo, orcamento_refs, composicao_sel, orcamento_excl FROM radar_item WHERE servico_id=? AND obra_id=1");
    $st->execute([$ordem]);
    $it = $st->fetch();
    if (!$it) { echo json_encode(['error'=>'item não encontrado']); exit; }

    $refs = json_decode($it['orcamento_refs'] ?? '[]', true) ?: [];
    $csel = json_decode($it['composicao_sel'] ?? '[]', true) ?: [];
    // Overrides p/ EDIÇÃO ao vivo (seleção/exclusões ainda não salvas). excl = [{l:lineId, d:descrição}]
    if (isset($_GET['refs'])) $refs = array_values(array_filter(array_map('intval', explode(',', $_GET['refs']))));
    $excl = isset($_GET['excl']) ? (json_decode($_GET['excl'], true) ?: [])
                                 : (!empty($it['orcamento_excl']) ? (json_decode($it['orcamento_excl'], true) ?: []) : []);
    $exclSet = [];
    foreach ($excl as $e) $exclSet[((int)($e['l'] ?? 0)) . '|' . ($e['d'] ?? '')] = true;
    $tpc  = function($t){ return in_array($t, ['material','mo','mat_mo','equip'], true) ? $t : 'material'; };

    $linhas = [];
    $agg = ['material'=>[], 'mo'=>[], 'mat_mo'=>[], 'equip'=>[]];   // desc => {desc,unidade,qtde,valor}
    $totItem = ['material'=>0.0, 'mo'=>0.0, 'mat_mo'=>0.0, 'equip'=>0.0];

    $addAgg = function($t, $desc, $un, $q, $v) use (&$agg, $tpc) {
        $t = $tpc($t); $k = $t . '|' . $desc;
        if (!isset($agg[$t][$k])) $agg[$t][$k] = ['desc'=>$desc, 'unidade'=>$un, 'qtde'=>0.0, 'valor'=>0.0];
        $agg[$t][$k]['qtde'] += $q; $agg[$t][$k]['valor'] += $v;
    };

    if ($refs) {
        $in = implode(',', array_map('intval', $refs));
        $rows = $pdo->query("SELECT id, descricao, path_str, unidade, qtde, valor FROM orcamento_linha WHERE id IN ($in)")->fetchAll();
        $descs = array_values(array_unique(array_map(function($l){ return $l['descricao']; }, $rows)));
        $compByDesc = [];
        if ($descs) {
            $ph = implode(',', array_fill(0, count($descs), '?'));
            $q = $pdo->prepare("SELECT id, descricao FROM composicao WHERE descricao IN ($ph)"); $q->execute($descs);
            foreach ($q->fetchAll() as $c) $compByDesc[$c['descricao']] = (int)$c['id'];
        }
        $cids = array_values(array_unique(array_values($compByDesc)));
        $insByCid = [];
        if ($cids) {
            $inC = implode(',', array_map('intval', $cids));
            foreach ($pdo->query("SELECT composicao_id, descricao, tipo, coef, rs_unit, unidade FROM composicao_insumo WHERE composicao_id IN ($inC) ORDER BY composicao_id, id")->fetchAll() as $r)
                $insByCid[(int)$r['composicao_id']][] = $r;
        }
        foreach ($rows as $l) {
            $cid = $compByDesc[$l['descricao']] ?? null;
            $insumos = []; $lt = ['material'=>0.0,'mo'=>0.0,'mat_mo'=>0.0,'equip'=>0.0]; $semc = false;
            $lq = (float)$l['qtde'];
            if ($cid && !empty($insByCid[$cid])) {
                foreach ($insByCid[$cid] as $ins) {
                    $q = $lq * (float)$ins['coef']; $v = $q * (float)$ins['rs_unit']; $t = $tpc($ins['tipo']);
                    $isX = isset($exclSet[$l['id'] . '|' . $ins['descricao']]);   // insumo excluído desta linha?
                    $insumos[] = ['desc'=>$ins['descricao'], 'tipo'=>$t, 'unidade'=>$ins['unidade'], 'qtde'=>$q, 'rs_unit'=>(float)$ins['rs_unit'], 'valor'=>$v, 'excl'=>$isX];
                    if (!$isX) { $lt[$t] += $v; $totItem[$t] += $v; $addAgg($t, $ins['descricao'], $ins['unidade'], $q, $v); }
                }
            } else {
                // linha sem composição (material direto) → conta como material, valor da linha
                $semc = true; $v = (float)$l['valor'];
                $isX = isset($exclSet[$l['id'] . '|' . $l['descricao']]);
                $insumos[] = ['desc'=>$l['descricao'], 'tipo'=>'material', 'unidade'=>$l['unidade'], 'qtde'=>$lq, 'rs_unit'=>($lq?($v/$lq):0), 'valor'=>$v, 'excl'=>$isX];
                if (!$isX) { $lt['material'] += $v; $totItem['material'] += $v; $addAgg('material', $l['descricao'], $l['unidade'], $lq, $v); }
            }
            $linhas[] = ['id'=>(int)$l['id'], 'descricao'=>$l['descricao'], 'path'=>$l['path_str'], 'valor'=>(float)$l['valor'],
                         'sem_composicao'=>$semc, 'tot_por_tipo'=>$lt, 'insumos'=>$insumos];
        }
    } elseif ($csel) {
        // verba por composição: os insumos são os escolhidos
        $cids = array_values(array_unique(array_map(function($s){ return (int)($s['cid'] ?? 0); }, $csel)));
        $compDesc = []; $insByCid = [];
        if ($cids) {
            $inC = implode(',', array_map('intval', $cids));
            foreach ($pdo->query("SELECT id, descricao FROM composicao WHERE id IN ($inC)")->fetchAll() as $c) $compDesc[(int)$c['id']] = $c['descricao'];
            foreach ($pdo->query("SELECT composicao_id, descricao, tipo, coef, rs_unit, unidade FROM composicao_insumo WHERE composicao_id IN ($inC) ORDER BY composicao_id, id")->fetchAll() as $r)
                $insByCid[(int)$r['composicao_id']][] = $r;
        }
        $byComp = [];
        foreach ($csel as $s) {
            $cid = (int)($s['cid'] ?? 0); $idx = (int)($s['idx'] ?? -1); $area = (float)($s['area'] ?? 0);
            $ins = $insByCid[$cid][$idx] ?? null; if (!$ins) continue;
            $q = $area * (float)$ins['coef']; $v = $q * (float)$ins['rs_unit']; $t = $tpc($ins['tipo']);
            $byComp[$cid]['descricao'] = $compDesc[$cid] ?? '';
            $byComp[$cid]['insumos'][] = ['desc'=>$ins['descricao'], 'tipo'=>$t, 'unidade'=>$ins['unidade'], 'qtde'=>$q, 'rs_unit'=>(float)$ins['rs_unit'], 'valor'=>$v];
            $byComp[$cid]['valor'] = ($byComp[$cid]['valor'] ?? 0) + $v;
            $totItem[$t] += $v; $addAgg($t, $ins['descricao'], $ins['unidade'], $q, $v);
        }
        foreach ($byComp as $cid => $c) {
            $lt = ['material'=>0.0,'mo'=>0.0,'mat_mo'=>0.0,'equip'=>0.0];
            foreach ($c['insumos'] as $x) $lt[$x['tipo']] += $x['valor'];
            $linhas[] = ['id'=>(int)$cid, 'descricao'=>$c['descricao'], 'path'=>'', 'valor'=>$c['valor'],
                         'sem_composicao'=>false, 'tot_por_tipo'=>$lt, 'insumos'=>$c['insumos']];
        }
    }

    $porTipo = [];
    foreach (['material','mo','mat_mo','equip'] as $t) { usort($agg[$t], function($a,$b){ return $b['valor'] <=> $a['valor']; }); $porTipo[$t] = array_values($agg[$t]); }

    echo json_encode([
        'metodo'=>$refs ? 'analitico' : ($csel ? 'composicao' : 'nenhum'),
        'tot_por_tipo'=>$totItem, 'total'=>array_sum($totItem),
        'linhas'=>$linhas, 'por_tipo'=>$porTipo,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
