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

function _normt($s){
    $map = ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c',
            'Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e','Í'=>'i','Ó'=>'o','Ô'=>'o','Õ'=>'o','Ú'=>'u','Ç'=>'c'];
    return strtolower(strtr((string)$s, $map));
}
function _sistema($p){ // p = path normalizado
    if (strpos($p,'gas')      !== false) return 'Gás';
    if (strpos($p,'quente')   !== false) return 'Água Quente';
    if (strpos($p,'agua fria')!== false || strpos($p,'fria')!==false) return 'Água Fria';
    if (strpos($p,'esgoto')   !== false || strpos($p,'sanit')!==false) return 'Esgoto / Sanitário';
    if (strpos($p,'pluvial')  !== false) return 'Águas Pluviais';
    if (strpos($p,'incendio') !== false) return 'Incêndio';
    if (strpos($p,'hidr')     !== false) return 'Hidráulica (geral)';
    return 'Outras';
}

try {
    $pdo = db();
    $termos = array_values(array_filter(array_map(function($t){ return _normt(trim($t)); }, explode(',', $_GET['termos'] ?? ''))));
    if (!$termos) { echo json_encode(['matches'=>[], 'total'=>0, 'n'=>0]); exit; }

    // todos os insumos, agrupados por composição (ordenados por id p/ bater o idx do front)
    $rows = $pdo->query("SELECT composicao_id, id, descricao, unidade, coef, rs_unit, tipo
                         FROM composicao_insumo ORDER BY composicao_id, id")->fetchAll();
    $byComp = []; foreach ($rows as $r) $byComp[$r['composicao_id']][] = $r;

    $matchIns = []; $cids = [];
    foreach ($byComp as $cid => $arr) {
        foreach ($arr as $idx => $r) {
            $nd = _normt($r['descricao']);
            foreach ($termos as $t) {
                if ($t !== '' && strpos($nd, $t) !== false) {
                    $matchIns[] = ['cid'=>(int)$cid, 'idx'=>$idx, 'desc'=>$r['descricao'], 'tipo'=>$r['tipo'],
                                   'coef'=>(float)$r['coef'], 'rs_unit'=>(float)$r['rs_unit'], 'unidade'=>$r['unidade']];
                    $cids[$cid] = 1; break;
                }
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
        $st = $pdo->prepare("SELECT id, descricao, path_str, qtde FROM orcamento_linha WHERE folha=1 AND descricao IN ($ph)");
        $st->execute($descs);
        foreach ($st->fetchAll() as $l) $linhasByDesc[$l['descricao']][] = $l;
    }

    $out = []; $total = 0.0;
    foreach ($matchIns as $m) {
        $comp = $comps[$m['cid']] ?? null; if (!$comp) continue;
        $linhas = $linhasByDesc[$comp['descricao']] ?? [];
        $locais = array_map(function($l){
            $parts = array_map('trim', explode('›', (string)($l['path_str'] ?? '')));
            return ['id'=>(int)$l['id'], 'q'=>(float)$l['qtde'],
                    'local'=>($parts[0] !== '' ? $parts[0] : '(sem local)'),
                    'sub'=>(count($parts) > 1 ? $parts[1] : '—')];
        }, $linhas);
        $area = $linhas ? array_sum(array_map(function($l){ return (float)$l['qtde']; }, $linhas)) : (float)$comp['qtde_total'];
        $sistema = $linhas ? _sistema(_normt($linhas[0]['path_str'])) : 'Outras';
        $valor = $area * $m['coef'] * $m['rs_unit'];
        $out[] = ['cid'=>$m['cid'], 'comp'=>$comp['descricao'], 'idx'=>$m['idx'], 'ins'=>$m['desc'], 'tipo'=>$m['tipo'],
                  'coef'=>$m['coef'], 'rs_unit'=>$m['rs_unit'], 'unidade'=>$m['unidade'], 'area'=>$area,
                  'valor'=>$valor, 'sistema'=>$sistema, 'locais'=>$locais];
        $total += $valor;
    }
    echo json_encode(['matches'=>$out, 'total'=>$total, 'n'=>count($out)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
