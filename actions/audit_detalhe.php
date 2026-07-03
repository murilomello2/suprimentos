<?php
/**
 * Detalhe da verba de UM item, insumo por insumo, marcando o que é material × MO e qual lado
 * é coerente com o `tipo` declarado (e qual está "embutido" no lado errado).
 * Serve pra conferir antes de "Separar".
 * GET ?ordem=<servico_id>
 * Retorna: { nome, tipo, classe, metodo, insumos:[{comp,desc,tipo,valor,lado}], tot_material, tot_mo, tot_correto, tot_errado, total, sem_composicao }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

function _classeT($t){
    $n = strtolower(trim((string)$t));
    if ($n === '') return 'skip';
    if (strpos($n,'+') !== false || strpos($n,'empreit') !== false) return 'ambos';
    if (strpos($n,'mao') !== false || strpos($n,'mão') !== false || strpos($n,'obra') !== false || $n === 'mo') return 'mo';
    if (strpos($n,'loca') !== false) return 'skip';
    if (strpos($n,'material') !== false) return 'material';
    return 'skip';
}

try {
    $pdo = db();
    $OBRA = max(1, (int)($_GET['obra'] ?? 1));   // multi-obra
    $ordem = (int)($_GET['ordem'] ?? 0);
    $st = $pdo->prepare("SELECT s.nome, r.tipo, r.orcamento_refs, r.composicao_sel
                         FROM radar_item r JOIN servico s ON s.id=r.servico_id WHERE r.servico_id=? AND r.obra_id=?");
    $st->execute([$ordem, $OBRA]);
    $it = $st->fetch();
    if (!$it) { echo json_encode(['error'=>'item não encontrado']); exit; }

    $classe = _classeT($it['tipo']);
    $refs = json_decode($it['orcamento_refs'] ?? '[]', true) ?: [];
    $csel = json_decode($it['composicao_sel'] ?? '[]', true) ?: [];
    $metodo = $refs ? 'analitico' : ($csel ? 'composicao' : 'nenhum');
    $insumos = []; $semComp = 0;

    if ($refs) {
        $in = implode(',', array_map('intval', $refs));
        $linhas = $pdo->query("SELECT id, descricao, qtde FROM orcamento_linha WHERE id IN ($in)")->fetchAll();
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
            foreach ($pdo->query("SELECT composicao_id, descricao, tipo, coef, rs_unit FROM composicao_insumo WHERE composicao_id IN ($inC)")->fetchAll() as $r)
                $insByCid[(int)$r['composicao_id']][] = $r;
        }
        $agg = [];
        foreach ($linhas as $l) {
            $cid = $compByDesc[$l['descricao']] ?? null;
            if (!$cid) { $semComp++; continue; }
            $qt = (float)$l['qtde'];
            foreach ($insByCid[$cid] ?? [] as $ins) {
                $k = $cid . '|' . $ins['descricao'];
                if (!isset($agg[$k])) $agg[$k] = ['comp'=>$l['descricao'], 'desc'=>$ins['descricao'], 'tipo'=>$ins['tipo'], 'valor'=>0.0];
                $agg[$k]['valor'] += $qt * (float)$ins['coef'] * (float)$ins['rs_unit'];
            }
        }
        $insumos = array_values($agg);
    } elseif ($csel) {
        $cids = array_values(array_unique(array_map(function($s){ return (int)($s['cid'] ?? 0); }, $csel)));
        $compDesc = []; $insByCid = [];
        if ($cids) {
            $inC = implode(',', array_map('intval', $cids));
            foreach ($pdo->query("SELECT id, descricao FROM composicao WHERE id IN ($inC)")->fetchAll() as $c) $compDesc[(int)$c['id']] = $c['descricao'];
            foreach ($pdo->query("SELECT composicao_id, descricao, tipo, coef, rs_unit FROM composicao_insumo WHERE composicao_id IN ($inC) ORDER BY composicao_id, id")->fetchAll() as $r)
                $insByCid[(int)$r['composicao_id']][] = $r;
        }
        foreach ($csel as $s) {
            $cid = (int)($s['cid'] ?? 0); $idx = (int)($s['idx'] ?? -1); $area = (float)($s['area'] ?? 0);
            $ins = $insByCid[$cid][$idx] ?? null; if (!$ins) continue;
            $insumos[] = ['comp'=>($compDesc[$cid] ?? ''), 'desc'=>$ins['descricao'], 'tipo'=>$ins['tipo'],
                          'valor'=>$area * (float)$ins['coef'] * (float)$ins['rs_unit']];
        }
    }

    $totByTipo = ['material'=>0.0, 'mo'=>0.0, 'mat_mo'=>0.0, 'equip'=>0.0];
    foreach ($insumos as &$x) {
        $tp = $x['tipo']; if (!isset($totByTipo[$tp])) $totByTipo[$tp] = 0.0; $totByTipo[$tp] += $x['valor'];
        $x['lado'] = $classe === 'material' ? ($tp === 'material' ? 'certo' : 'errado')
                   : ($classe === 'mo' ? ($tp === 'mo' ? 'certo' : 'errado') : '?');
    }
    unset($x);
    usort($insumos, function($a,$b){ return $b['valor'] <=> $a['valor']; });
    $totalAll = array_sum($totByTipo);
    $correto = $classe === 'material' ? $totByTipo['material'] : ($classe === 'mo' ? $totByTipo['mo'] : 0);
    $errado  = $totalAll - $correto;

    echo json_encode(['ordem'=>$ordem, 'nome'=>$it['nome'], 'tipo'=>$it['tipo'], 'classe'=>$classe, 'metodo'=>$metodo,
        'insumos'=>$insumos, 'tot_por_tipo'=>$totByTipo, 'tot_material'=>$totByTipo['material'], 'tot_mo'=>$totByTipo['mo'],
        'tot_correto'=>$correto, 'tot_errado'=>$errado, 'total'=>$totalAll, 'sem_composicao'=>$semComp], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
