<?php
/**
 * Auditoria de COERÊNCIA: o `tipo` declarado do item × o que a verba REALMENTE traz.
 *  - Material        → só pode trazer insumos de material.
 *  - Mão de obra     → só MO.
 *  - Material + MO / Empreitada → ambos (ok).
 * Verba ANALÍTICA (linha inteira) = sempre material + MO → incoerente se o tipo é de um lado só.
 * Verba por COMPOSIÇÃO → checa os insumos escolhidos (tem algum do lado errado?).
 * Retorna os itens incoerentes + o quanto está "embutido" do lado errado (pra priorizar e separar em massa).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

function _classeTipo($t){
    $n = strtolower(trim((string)$t));
    if ($n === '') return 'skip';
    if (strpos($n,'+') !== false || strpos($n,'empreit') !== false) return 'ambos';
    if (strpos($n,'mao') !== false || strpos($n,'mão') !== false || strpos($n,'obra') !== false || $n === 'mo') return 'mo';
    if (strpos($n,'loca') !== false) return 'skip';   // locação
    if (strpos($n,'material') !== false) return 'material';
    return 'skip';
}

try {
    $pdo = db();
    // pré-carrega tudo (sem N queries por item)
    $lineById = [];
    foreach ($pdo->query("SELECT id, descricao, qtde, valor FROM orcamento_linha WHERE folha=1")->fetchAll() as $l)
        $lineById[(int)$l['id']] = $l;
    $cidByDesc = [];
    foreach ($pdo->query("SELECT id, descricao FROM composicao")->fetchAll() as $c)
        $cidByDesc[$c['descricao']] = (int)$c['id'];
    $insByCid = [];
    foreach ($pdo->query("SELECT composicao_id, descricao, tipo, coef, rs_unit FROM composicao_insumo ORDER BY composicao_id, id")->fetchAll() as $ci)
        $insByCid[(int)$ci['composicao_id']][] = $ci;

    $rows = $pdo->query("SELECT r.servico_id ordem, s.nome, r.tipo, r.orcamento_refs, r.composicao_sel
                         FROM radar_item r JOIN servico s ON s.id=r.servico_id WHERE r.obra_id=1")->fetchAll();

    $flagged = []; $totalEmbutido = 0.0;
    foreach ($rows as $r) {
        $classe = _classeTipo($r['tipo']);
        if ($classe === 'ambos' || $classe === 'skip') continue;   // só audita itens de um lado só
        $refs = json_decode($r['orcamento_refs'] ?? '[]', true) ?: [];
        $csel = json_decode($r['composicao_sel'] ?? '[]', true) ?: [];
        if (!$refs && !$csel) continue;                            // sem verba

        $issue = $classe === 'material' ? 'mat_com_mo' : 'mo_com_mat';

        if ($refs) {
            // ANALÍTICO: linha inteira traz os dois lados → incoerente p/ tipo de um lado só
            $total = 0.0; $embutido = 0.0;
            foreach ($refs as $lid) {
                $l = $lineById[(int)$lid] ?? null; if (!$l) continue;
                $total += (float)$l['valor'];
                $cid = $cidByDesc[$l['descricao']] ?? null; if (!$cid) continue;
                $qt = (float)$l['qtde'];
                foreach ($insByCid[$cid] ?? [] as $ins) {
                    $isMo = ($ins['tipo'] === 'mo');
                    $v = $qt * (float)$ins['coef'] * (float)$ins['rs_unit'];
                    if (($classe === 'material' && $isMo) || ($classe === 'mo' && !$isMo)) $embutido += $v;
                }
            }
            if ($embutido > 0.5) {
                $flagged[] = ['ordem'=>(int)$r['ordem'], 'nome'=>$r['nome'], 'tipo'=>$r['tipo'], 'classe'=>$classe,
                              'metodo'=>'analitico', 'issue'=>$issue, 'verba'=>$total, 'embutido'=>$embutido,
                              'correto'=>$total - $embutido, 'remover'=>null];
                $totalEmbutido += $embutido;
            }
        } else {
            // COMPOSIÇÃO: checa os insumos escolhidos
            $valWrong = 0.0; $remover = [];
            foreach ($csel as $s) {
                $cid = (int)($s['cid'] ?? 0); $idx = (int)($s['idx'] ?? -1); $area = (float)($s['area'] ?? 0);
                $ins = $insByCid[$cid][$idx] ?? null; if (!$ins) continue;
                $isMo = ($ins['tipo'] === 'mo');
                $wrong = ($classe === 'material' && $isMo) || ($classe === 'mo' && !$isMo);
                if ($wrong) { $valWrong += $area * (float)$ins['coef'] * (float)$ins['rs_unit']; $remover[] = ['cid'=>$cid, 'idx'=>$idx]; }
            }
            if ($remover) {
                $flagged[] = ['ordem'=>(int)$r['ordem'], 'nome'=>$r['nome'], 'tipo'=>$r['tipo'], 'classe'=>$classe,
                              'metodo'=>'composicao', 'issue'=>$issue, 'verba'=>null, 'embutido'=>$valWrong,
                              'correto'=>null, 'remover'=>$remover];
                $totalEmbutido += $valWrong;
            }
        }
    }
    usort($flagged, function($a,$b){ return $b['embutido'] <=> $a['embutido']; });

    echo json_encode(['flagged'=>$flagged, 'n'=>count($flagged), 'total_embutido'=>$totalEmbutido,
        'n_mat_com_mo'=>count(array_filter($flagged, function($f){ return $f['issue']==='mat_com_mo'; })),
        'n_mo_com_mat'=>count(array_filter($flagged, function($f){ return $f['issue']==='mo_com_mat'; }))
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
