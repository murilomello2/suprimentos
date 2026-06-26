<?php
/**
 * Mapa de USO das linhas-folha do orçamento na VERBA dos itens do radar + detecção de duplicação.
 *
 * Regra (modelo do usuário): toda linha do orçamento É uma composição (material + MO + ...).
 *   - Uso ANALÍTICO de uma linha = consome a linha INTEIRA (todos os insumos dela).
 *   - Uso por COMPOSIÇÃO de um insumo (cid#idx) numa linha = consome só AQUELE insumo (uma fração).
 *
 * Conflito (double-count) numa linha L:
 *   - 2+ itens usam L inteira (analítico), OU
 *   - 1+ usa L inteira E outro item usa qualquer insumo de L, OU
 *   - 2+ itens usam o MESMO insumo (cid#idx) de L.
 *   (2 itens usando insumos DIFERENTES da mesma linha NÃO é conflito — dinheiros distintos.)
 *
 * GET -> {
 *   usos:  { "<lineId>": [ordem,...] },               // qualquer uso — trava a seleção ANALÍTICA (cliente)
 *   nomes: { "<ordem>": "nome" },
 *   duplicatas: [ {id, descricao, path, valor, n, itens:[{ordem,nome,vias:[...]}]} ],
 *   n_dups
 * }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();
    $rows = $pdo->query("SELECT r.servico_id, s.nome, r.orcamento_refs, r.composicao_sel
                         FROM radar_item r JOIN servico s ON s.id=r.servico_id WHERE r.obra_id=1")->fetchAll();

    $usos = [];      // L => [ ['ordem'=>, 'kind'=>'A'|'C', 'ins'=>cid#idx|null, 'desc'=>insumoDesc] ]
    $nomes = [];
    $compCache = [];
    $compLines = function($cid) use ($pdo, &$compCache) {
        if (isset($compCache[$cid])) return $compCache[$cid];
        $ids = [];
        $d = $pdo->prepare("SELECT descricao FROM composicao WHERE id=?"); $d->execute([$cid]);
        $desc = $d->fetchColumn();
        if ($desc !== false) {
            $q = $pdo->prepare("SELECT id FROM orcamento_linha WHERE descricao=? AND folha=1");
            $q->execute([$desc]); $ids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
        }
        return $compCache[$cid] = $ids;
    };

    foreach ($rows as $r) {
        $ordem = (int)$r['servico_id']; $nomes[$ordem] = $r['nome'];
        foreach ((json_decode($r['orcamento_refs'] ?? '[]', true) ?: []) as $id)
            $usos[(int)$id][] = ['ordem'=>$ordem, 'kind'=>'A', 'ins'=>null, 'desc'=>null];
        foreach ((json_decode($r['composicao_sel'] ?? '[]', true) ?: []) as $ins) {
            $cid = (int)($ins['cid'] ?? 0); if (!$cid) continue;
            $insKey = $cid . '#' . ($ins['idx'] ?? ($ins['desc'] ?? '?'));
            $insDesc = $ins['desc'] ?? '';
            $lineIds = (isset($ins['locais']) && is_array($ins['locais']) && $ins['locais'])
                       ? array_map('intval', $ins['locais']) : $compLines($cid);
            foreach ($lineIds as $id)
                $usos[(int)$id][] = ['ordem'=>$ordem, 'kind'=>'C', 'ins'=>$insKey, 'desc'=>$insDesc];
        }
    }

    // mapa achatado pro guard do cliente (qualquer uso) + linhas em conflito real
    $usosFlat = []; $dupLids = [];
    foreach ($usos as $L => $claims) {
        $items = []; foreach ($claims as $c) $items[$c['ordem']] = 1;
        $usosFlat[$L] = array_map('intval', array_keys($items));

        $whole = []; $byIns = [];
        foreach ($claims as $c) {
            if ($c['kind'] === 'A') $whole[$c['ordem']] = 1;
            else { if (!isset($byIns[$c['ins']])) $byIns[$c['ins']] = []; $byIns[$c['ins']][$c['ordem']] = 1; }
        }
        $conflict = false;
        if (count($whole) >= 2) $conflict = true;
        elseif (count($whole) >= 1 && count($items) >= 2) $conflict = true;
        else foreach ($byIns as $os) if (count($os) >= 2) { $conflict = true; break; }
        if ($conflict) $dupLids[] = $L;
    }

    $duplicatas = [];
    if ($dupLids) {
        $in = implode(',', array_map('intval', $dupLids));
        $dmap = [];
        foreach ($pdo->query("SELECT id, descricao, path_str, valor FROM orcamento_linha WHERE id IN ($in)")->fetchAll() as $r)
            $dmap[(int)$r['id']] = $r;
        foreach ($dupLids as $L) {
            $byOrdem = [];
            foreach ($usos[$L] as $c) {
                $via = $c['kind'] === 'A' ? 'analítico' : ('composição' . ($c['desc'] ? ' (' . $c['desc'] . ')' : ''));
                $byOrdem[$c['ordem']][$via] = 1;
            }
            $itens = [];
            foreach ($byOrdem as $ordem => $vias)
                $itens[] = ['ordem'=>(int)$ordem, 'nome'=>($nomes[$ordem] ?? ('item '.$ordem)), 'vias'=>array_keys($vias)];
            $rr = $dmap[$L] ?? ['descricao'=>'(linha '.$L.')', 'path_str'=>'', 'valor'=>0];
            $duplicatas[] = ['id'=>(int)$L, 'descricao'=>$rr['descricao'], 'path'=>$rr['path_str'],
                             'valor'=>(float)$rr['valor'], 'n'=>count($byOrdem), 'itens'=>$itens];
        }
        usort($duplicatas, function($a,$b){ return $b['valor'] <=> $a['valor']; });
    }

    echo json_encode(['usos'=>$usosFlat, 'nomes'=>$nomes, 'duplicatas'=>$duplicatas, 'n_dups'=>count($duplicatas)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
