<?php
/**
 * Busca em MASSA por INSUMO dentro das composições (ex.: "encanador" → traz o insumo Encanador/Ajudante
 * de TODAS as composições onde aparece, com a área/locais de cada composição e o valor).
 * Serve pra montar verba de MÃO DE OBRA pulverizada (a MO mora dentro de composições com nome de material).
 * GET: termos=encanador,ajudante   (separados por vírgula; casa por substring sem acento)
 * Retorna: { matches:[{cid, comp, idx, ins, tipo, coef, rs_unit, unidade, area, valor, sistema, locais:[{id,q}]}], total, n }
 *   idx = posição do insumo na composição (ORDER BY id) — igual ao usado no front (COMP_DATA.insumos[idx]).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

// canônicos em includes/db.php (sup_normt/sup_sistema — a MESMA lógica da derivação de receitas,
// pra classificação nunca divergir); fallback local só p/ deploy parcial (db.php antigo no FTP)
function _normt($s){
    if (function_exists('sup_normt')) return sup_normt($s);
    $map = ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c',
            'Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e','Í'=>'i','Ó'=>'o','Ô'=>'o','Õ'=>'o','Ú'=>'u','Ç'=>'c'];
    return strtolower(strtr((string)$s, $map));
}
function _sistema($p){ // p = path normalizado (minúsculo, sem acento)
    if (function_exists('sup_sistema')) return sup_sistema($p) ?: 'Outras';
    // PALAVRA INTEIRA nas ambíguas curtas: "gas" NÃO pode casar dentro de "vigas"/"desgaste"; "fria" idem.
    $w = function($re) use ($p){ return preg_match('#\b'.$re.'\b#', $p) === 1; };
    if ($w('gas'))                                        return 'Gás';
    if (strpos($p,'quente') !== false)                    return 'Água Quente';
    if (strpos($p,'agua fria') !== false || $w('fria'))   return 'Água Fria';
    if (strpos($p,'esgoto') !== false || strpos($p,'sanit') !== false) return 'Esgoto / Sanitário';
    if (strpos($p,'pluvia') !== false)                    return 'Águas Pluviais';
    if (strpos($p,'incendio') !== false)                  return 'Incêndio';
    if (strpos($p,'hidr') !== false)                      return 'Hidráulica (geral)';
    return 'Outras';
}

try {
    $pdo = db();
    $OBRA = max(1, (int)($_GET['obra'] ?? 1));   // multi-obra: insumos/composições/linhas só DESTA obra
    $termos = array_values(array_filter(array_map(function($t){ return _normt(trim($t)); }, explode(',', $_GET['termos'] ?? ''))));
    $sisFilter = trim($_GET['sistema'] ?? '');   // LABEL de _sistema() (ex.: 'Gás', 'Água Fria') — escopo por subsistema; '' = todos
    $tipoF     = strtolower(trim($_GET['tipo'] ?? ''));   // '', 'material' (não-MO), 'mo'
    // precisa de PELO MENOS um critério: termo OU sistema (senão traria o catálogo inteiro)
    if (!$termos && $sisFilter === '') { echo json_encode(['matches'=>[], 'total'=>0, 'n'=>0, 'nota'=>'informe um termo ou um sistema']); exit; }

    // todos os insumos DA OBRA, agrupados por composição (ordenados por id p/ bater o idx do front)
    $st = $pdo->prepare("SELECT ci.composicao_id, ci.id, ci.descricao, ci.unidade, ci.coef, ci.rs_unit, ci.tipo
                         FROM composicao_insumo ci JOIN composicao c ON c.id = ci.composicao_id
                         WHERE c.obra_id = ? ORDER BY ci.composicao_id, ci.id");
    $st->execute([$OBRA]);
    $rows = $st->fetchAll();
    $byComp = []; foreach ($rows as $r) $byComp[$r['composicao_id']][] = $r;

    $matchIns = []; $cids = [];
    foreach ($byComp as $cid => $arr) {
        foreach ($arr as $idx => $r) {
            if ($tipoF === 'mo' && $r['tipo'] !== 'mo') continue;          // só mão de obra
            if ($tipoF === 'material' && $r['tipo'] === 'mo') continue;    // só materiais (tudo que não é MO)
            $nd = _normt($r['descricao']);
            $hit = empty($termos);   // sem termo = casa tudo (será recortado por tipo/sistema)
            if (!$hit) foreach ($termos as $t) { if ($t !== '' && strpos($nd, $t) !== false) { $hit = true; break; } }
            if ($hit) {
                $matchIns[] = ['cid'=>(int)$cid, 'idx'=>$idx, 'desc'=>$r['descricao'], 'tipo'=>$r['tipo'],
                               'coef'=>(float)$r['coef'], 'rs_unit'=>(float)$r['rs_unit'], 'unidade'=>$r['unidade']];
                $cids[$cid] = 1;
            }
        }
    }
    if (!$matchIns) { echo json_encode(['matches'=>[], 'total'=>0, 'n'=>0]); exit; }

    // composições envolvidas
    $cidList = implode(',', array_map('intval', array_keys($cids)));
    $comps = [];
    foreach ($pdo->query("SELECT id, descricao, qtde_total, unidade FROM composicao WHERE id IN ($cidList)")->fetchAll() as $c)
        $comps[(int)$c['id']] = $c;

    // linhas-folha (locais) por descrição de composição
    $descs = [];
    foreach ($cids as $cid => $_) if (isset($comps[$cid])) $descs[$comps[$cid]['descricao']] = 1;
    $descs = array_keys($descs);
    $linhasByDesc = [];
    if ($descs) {
        $ph = implode(',', array_fill(0, count($descs), '?'));
        $st = $pdo->prepare("SELECT id, descricao, path_str, qtde FROM orcamento_linha WHERE obra_id=? AND folha=1 AND descricao IN ($ph)");
        $st->execute(array_merge([$OBRA], $descs));
        foreach ($st->fetchAll() as $l) $linhasByDesc[$l['descricao']][] = $l;
    }

    $out = []; $total = 0.0; $porSis = [];
    foreach ($matchIns as $m) {
        $comp = $comps[$m['cid']] ?? null; if (!$comp) continue;
        $linhas = $linhasByDesc[$comp['descricao']] ?? [];
        // sis POR FOLHA (path próprio); se houver filtro de sistema, recorta as folhas fora do escopo
        $locais = [];
        foreach ($linhas as $l) {
            $pn = _normt($l['path_str'] ?? ''); $sis = _sistema($pn);
            if ($sisFilter !== '' && $sis !== $sisFilter) continue;
            $parts = array_map('trim', explode('›', (string)($l['path_str'] ?? '')));
            $locais[] = ['id'=>(int)$l['id'], 'q'=>(float)$l['qtde'],
                         'local'=>($parts[0] !== '' ? $parts[0] : '(sem local)'),
                         'sub'=>(count($parts) > 1 ? $parts[1] : '—'), 'sis'=>$sis];
        }
        if ($sisFilter !== '' && !$locais) continue;   // esse insumo não tem folha no escopo escolhido
        // ÁREA = só das folhas no escopo (não do total da composição)
        $area = $locais ? array_sum(array_map(function($l){ return $l['q']; }, $locais)) : (float)$comp['qtde_total'];
        $sistema = $locais ? $locais[0]['sis'] : 'Outras';
        $valor = $area * $m['coef'] * $m['rs_unit'];
        $out[] = ['cid'=>$m['cid'], 'comp'=>$comp['descricao'], 'idx'=>$m['idx'], 'ins'=>$m['desc'], 'tipo'=>$m['tipo'],
                  'coef'=>$m['coef'], 'rs_unit'=>$m['rs_unit'], 'unidade'=>$m['unidade'], 'area'=>$area,
                  'valor'=>$valor, 'sistema'=>$sistema, 'locais'=>$locais];
        $total += $valor;
        if (!isset($porSis[$sistema])) $porSis[$sistema] = ['n'=>0, 'valor'=>0.0];
        $porSis[$sistema]['n']++; $porSis[$sistema]['valor'] += $valor;
    }
    echo json_encode(['matches'=>$out, 'total'=>$total, 'n'=>count($out), 'por_sistema'=>$porSis], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
