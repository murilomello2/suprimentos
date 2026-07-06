<?php
/**
 * DICIONÁRIO DE APRENDIZADO — receitas de curadoria por serviço × método construtivo.
 *
 * A receita captura COMO cada decisão foi tomada, por NOME/semântica (nunca IDs — outra obra
 * tem outros IDs de linha/composição):
 *   crono: tarefa-âncora (nome) + regra "buscar nome → PRIMEIRA data" + termos de fallback
 *   verba: analitico (descrições, escopo parcial, exclusões) | composicao (insumos nome/tipo/
 *          sistema/escopo + recorte sugerido, ex. Gás+MO) | manual
 *   quant: fonte + drivers
 * (responsavel_padrao fica FORA da receita — modelo de responsabilidades ainda em definição.)
 *
 * GET                                      -> { receitas: [...] } (join servico p/ nome/grupo)
 * POST {acao:'derivar', obra_id, me}       -> re-deriva da curadoria da obra (ADMIN). Upsert por
 *                                             (servico_id, metodo_construtivo da obra). Corrigir
 *                                             receita = re-curar o item e re-derivar.
 * POST {acao:'nota', servico_id, metodo_construtivo, nota, me} -> anotação manual (ADMIN)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

// canônicos em includes/db.php (sup_normt/sup_sistema); fallback local só p/ deploy parcial (db.php antigo no FTP)
if (!function_exists('sup_normt')) {
    function sup_normt($s) {
        $map = ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c',
                'Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e','Í'=>'i','Ó'=>'o','Ô'=>'o','Õ'=>'o','Ú'=>'u','Ç'=>'c'];
        return strtolower(strtr((string)$s, $map));
    }
}
if (!function_exists('sup_sistema')) {
    function sup_sistema($p) {
        $w = function($re) use ($p){ return preg_match('#\b'.$re.'\b#', $p) === 1; };
        if ($w('gas'))                                      return 'Gás';
        if (strpos($p,'quente') !== false)                  return 'Água Quente';
        if (strpos($p,'agua fria') !== false || $w('fria')) return 'Água Fria';
        if (strpos($p,'esgoto') !== false || strpos($p,'sanit') !== false) return 'Esgoto / Sanitário';
        if (strpos($p,'pluvia') !== false)                  return 'Águas Pluviais';
        if (strpos($p,'incendio') !== false)                return 'Incêndio';
        if (strpos($p,'hidr') !== false)                    return 'Hidráulica (geral)';
        return null;
    }
}

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $rows = $pdo->query("SELECT rc.*, s.nome, s.grupo, s.curva
                             FROM receita rc JOIN servico s ON s.id = rc.servico_id
                             ORDER BY s.grupo, s.ordem")->fetchAll();
        foreach ($rows as &$r) foreach (['crono','verba','quant'] as $k) $r[$k] = $r[$k] ? json_decode($r[$k], true) : null;
        unset($r);
        echo json_encode(['receitas'=>$rows, 'n'=>count($rows)], JSON_UNESCAPED_UNICODE); exit;
    }

    // ---- POST: só ADMIN mexe no dicionário ----
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $perms = user_perms($pdo, $in['me'] ?? null);
    if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Apenas administradores.']); exit; }
    $acao = $in['acao'] ?? '';

    if ($acao === 'nota') {
        $sid = (int)($in['servico_id'] ?? 0); $mc = (string)($in['metodo_construtivo'] ?? '');
        if (!$sid || $mc === '') throw new Exception('servico_id + metodo_construtivo obrigatórios');
        $pdo->prepare("UPDATE receita SET nota=?, updated_at=? WHERE servico_id=? AND metodo_construtivo=?")
            ->execute([(string)($in['nota'] ?? ''), date('c'), $sid, $mc]);
        echo json_encode(['ok'=>true]); exit;
    }

    // ---- EDIÇÃO MANUAL da receita (didática): mescla os campos editáveis SEM perder a seleção
    //      fina derivada (linhas/insumos/recorte vêm de curar uma obra real). ----
    if ($acao === 'salvar') {
        $sid = (int)($in['servico_id'] ?? 0);
        $mc  = trim((string)($in['metodo_construtivo'] ?? '')) ?: 'concreto armado convencional';
        if (!$sid) throw new Exception('servico_id obrigatório');
        $ex = $pdo->prepare("SELECT crono, verba, quant, nota, obra_origem FROM receita WHERE servico_id=? AND metodo_construtivo=?");
        $ex->execute([$sid, $mc]); $ex = $ex->fetch() ?: null;
        $crono = ($ex && $ex['crono']) ? (json_decode($ex['crono'], true) ?: []) : [];
        $verba = ($ex && $ex['verba']) ? (json_decode($ex['verba'], true) ?: []) : [];
        $quant = ($ex && $ex['quant']) ? (json_decode($ex['quant'], true) ?: []) : [];
        $origem = $ex ? $ex['obra_origem'] : null;

        $ic = is_array($in['crono'] ?? null) ? $in['crono'] : [];
        $iv = is_array($in['verba'] ?? null) ? $in['verba'] : [];
        $iq = is_array($in['quant'] ?? null) ? $in['quant'] : [];

        if (array_key_exists('ancora_nome', $ic)) {
            $an = trim((string)$ic['ancora_nome']);
            $crono['ancora_nome'] = ($an !== '') ? $an : null;
            $crono['regra'] = ($an !== '') ? 'buscar_nome_pegar_primeira_data' : 'auto_por_termos';
        }
        if (array_key_exists('termos_template', $ic)) {   // termos do cronograma → também no serviço (marco automático usa)
            $t = trim((string)$ic['termos_template']);
            if ($t !== '') $crono['termos_template'] = $t; else unset($crono['termos_template']);
            // servico.termos_cronograma é POR SERVIÇO (não por método) e o marco automático o consome p/ TODAS
            // as obras → só o método BASE propaga, e só quando não-vazio (não sobrescreve variantes nem apaga global)
            if ($mc === 'concreto armado convencional' && $t !== '')
                $pdo->prepare("UPDATE servico SET termos_cronograma=? WHERE id=?")->execute([$t, $sid]);
        }
        if (array_key_exists('metodo', $iv)) {
            $m = trim((string)$iv['metodo']);
            if ($m === '') $verba['metodo'] = null;
            elseif (in_array($m, ['analitico','composicao','manual'], true)) $verba['metodo'] = $m;
        }
        if (array_key_exists('exclusoes', $iv)) {
            $exs = [];
            foreach ((array)$iv['exclusoes'] as $e) { $e = trim((string)$e); if ($e !== '') $exs[] = ['insumo'=>$e]; }
            if ($exs) $verba['exclusoes'] = $exs; else unset($verba['exclusoes']);
        }
        // ITENS editados à mão (insumos p/ composição, descrições de linha p/ analítico). O motor casa por
        // NOME, então adicionar/remover aqui muda o auto-vínculo. Preserva tipo/sistemas dos que continuam.
        if (array_key_exists('itens', $iv)) {
            $met = $verba['metodo'] ?? null;
            $nomes = [];
            foreach ((array)$iv['itens'] as $x) { $x = trim((string)$x); if ($x !== '') $nomes[] = $x; }
            if ($met === 'composicao') {
                $antNomes = []; foreach (($verba['insumos'] ?? []) as $ins) if (isset($ins['insumo'])) $antNomes[] = $ins['insumo'];
                $ant = []; foreach (($verba['insumos'] ?? []) as $ins) if (isset($ins['insumo'])) $ant[$ins['insumo']] = $ins;
                $novos = []; foreach ($nomes as $nm) $novos[] = $ant[$nm] ?? ['insumo'=>$nm, 'tipo'=>null];
                $verba['insumos'] = $novos;
                // só descarta o recorte aprendido se a lista REALMENTE mudou. O editor pré-preenche o
                // textarea com os insumos derivados; um "salvar" sem tocar na lista não pode apagar o
                // recorte (senão degrada "pega o sistema inteiro" → "só estes N insumos" num no-op).
                if ($antNomes !== $nomes) unset($verba['recorte_sugerido']);
            } elseif ($met === 'analitico') {
                $ant = []; foreach (($verba['linhas'] ?? []) as $ln) if (isset($ln['descricao'])) $ant[$ln['descricao']] = $ln;
                $novas = []; foreach ($nomes as $nm) $novas[] = $ant[$nm] ?? ['descricao'=>$nm, 'ocorrencias'=>1];
                $verba['linhas'] = $novas; $verba['n_linhas'] = count($novas);
            }
        }
        if (array_key_exists('termos_template', $iv)) {   // termos da verba → também no serviço (busca em massa usa)
            $t = trim((string)$iv['termos_template']);
            if ($t !== '') $verba['termos_template'] = $t; else unset($verba['termos_template']);
            if ($mc === 'concreto armado convencional' && $t !== '')   // só o método base propaga p/ a coluna global do serviço
                $pdo->prepare("UPDATE servico SET termos_orcamento=? WHERE id=?")->execute([$t, $sid]);
        }
        if (array_key_exists('fonte', $iq)) {
            $f = trim((string)$iq['fonte']);
            if ($f === '') $quant['fonte'] = null;
            elseif (in_array($f, ['orcamento','composicao','manual'], true)) $quant['fonte'] = $f;
        }
        if (array_key_exists('unidade', $iq)) { $u = trim((string)$iq['unidade']); $quant['unidade'] = $u !== '' ? $u : null; }
        if (array_key_exists('driver', $iq)) {   // insumo(s) que dirigem o quantitativo (o motor conta por eles)
            $drv = [];
            foreach ((array)$iq['driver'] as $d) { $d = trim((string)$d); if ($d !== '') $drv[] = $d; }
            if ($drv) $quant['driver_na_verba'] = $drv; else unset($quant['driver_na_verba']);
        }
        $nota = array_key_exists('nota', $in) ? (string)$in['nota'] : ($ex['nota'] ?? null);

        $pdo->prepare("REPLACE INTO receita (servico_id, metodo_construtivo, obra_origem, crono, verba, quant, nota, updated_at)
                       VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$sid, $mc, $origem,
                       json_encode($crono, JSON_UNESCAPED_UNICODE), json_encode($verba, JSON_UNESCAPED_UNICODE),
                       json_encode($quant, JSON_UNESCAPED_UNICODE), $nota, date('c')]);
        echo json_encode(['ok'=>true, 'servico_id'=>$sid, 'metodo_construtivo'=>$mc]); exit;
    }

    // ---- NOVO ITEM no catálogo (aparece em TODAS as obras) + receita vazia editável ----
    if ($acao === 'criar_item') {
        $nome  = trim((string)($in['nome'] ?? ''));
        $grupo = trim((string)($in['grupo'] ?? ''));
        $curva = strtoupper(trim((string)($in['curva'] ?? ''))) ?: 'C';
        $mc    = trim((string)($in['metodo_construtivo'] ?? '')) ?: 'concreto armado convencional';
        if ($nome === '')  throw new Exception('nome obrigatório');
        if ($grupo === '') throw new Exception('grupo obrigatório');
        $sid = criar_item($pdo, $nome, $grupo, '', $curva);
        $pdo->prepare("REPLACE INTO receita (servico_id, metodo_construtivo, obra_origem, crono, verba, quant, nota, updated_at)
                       VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$sid, $mc, null,
                       json_encode(['regra'=>'auto_por_termos','ancora_nome'=>null], JSON_UNESCAPED_UNICODE),
                       json_encode(['metodo'=>null], JSON_UNESCAPED_UNICODE),
                       json_encode(['fonte'=>null], JSON_UNESCAPED_UNICODE),
                       '', date('c')]);
        echo json_encode(['ok'=>true, 'servico_id'=>$sid, 'nome'=>$nome, 'grupo'=>$grupo]); exit;
    }

    if ($acao !== 'derivar') throw new Exception('acao inválida');
    $obraId = (int)($in['obra_id'] ?? 1);
    $obra = $pdo->prepare("SELECT * FROM obra WHERE id=?"); $obra->execute([$obraId]);
    $obra = $obra->fetch();
    if (!$obra) throw new Exception('obra não encontrada');
    $mc = $obra['metodo_construtivo'] ?: 'concreto armado convencional';

    // ---- bases da obra: linhas-folha (id→desc/path) + composições (id→desc) + insumos ----
    $LIN = []; $descCount = []; $linesByDesc = []; $SIS = [];
    $q = $pdo->prepare("SELECT id, descricao, path_str FROM orcamento_linha WHERE obra_id=? AND folha=1");
    $q->execute([$obraId]);
    foreach ($q->fetchAll() as $l) {
        $id = (int)$l['id'];
        $LIN[$id] = $l;
        $SIS[$id] = sup_sistema(sup_normt($l['path_str'])) ?: '(geral)';
        $descCount[$l['descricao']] = ($descCount[$l['descricao']] ?? 0) + 1;
        $linesByDesc[$l['descricao']][] = $id;
    }
    $COMPD = [];
    $q = $pdo->prepare("SELECT id, descricao FROM composicao WHERE obra_id=?"); $q->execute([$obraId]);
    foreach ($q->fetchAll() as $c) $COMPD[(int)$c['id']] = $c['descricao'];
    $INSCNT = [];   // cid => contagem de insumos por tipo (p/ teste de COBERTURA do recorte)
    $q = $pdo->prepare("SELECT ci.composicao_id, ci.tipo, COUNT(*) n FROM composicao_insumo ci
                        JOIN composicao c ON c.id=ci.composicao_id WHERE c.obra_id=? GROUP BY ci.composicao_id, ci.tipo");
    $q->execute([$obraId]);
    foreach ($q->fetchAll() as $r) $INSCNT[(int)$r['composicao_id']][$r['tipo']] = (int)$r['n'];

    $topo = function($lid) use ($LIN) {
        $l = $LIN[(int)$lid] ?? null; $p = $l ? (string)($l['path_str'] ?? '') : '';
        if ($p === '') return '';
        $parts = explode('›', $p); return trim($parts[0]);
    };

    $rows = $pdo->prepare("
        SELECT s.id AS sid, s.nome, s.grupo, s.curva, s.termos_cronograma, s.termos_orcamento, s.marco_cronograma,
               r.tipo, r.crono_marco_override, r.data_necessaria_override, r.verba_metodo, r.verba_override,
               r.orcamento_refs, r.orcamento_excl, r.composicao_sel, r.verba_curada,
               r.quantitativo_fonte, r.quantitativo_valor, r.quantitativo_unidade, r.quantitativo_refs,
               r.quant_comp_sel, r.quant_curada
        FROM servico s JOIN radar_item r ON r.servico_id = s.id AND r.obra_id = ?
        ORDER BY s.id");
    $rows->execute([$obraId]);
    $rows = $rows->fetchAll();

    // REPLACE apaga a linha inteira — lê as notas manuais ANTES pra preservá-las no upsert
    $NOTAS = [];
    $q = $pdo->prepare("SELECT servico_id, nota FROM receita WHERE metodo_construtivo=?"); $q->execute([$mc]);
    foreach ($q->fetchAll() as $x) if ($x['nota'] !== null && $x['nota'] !== '') $NOTAS[(int)$x['servico_id']] = $x['nota'];
    $up = $pdo->prepare("REPLACE INTO receita (servico_id, metodo_construtivo, obra_origem, crono, verba, quant, nota, updated_at)
                         VALUES (?,?,?,?,?,?,?,?)");

    $n = 0; $stats = ['crono_com_ancora'=>0,'verba_analitico'=>0,'verba_composicao'=>0,'verba_manual'=>0,'verba_sem'=>0];
    foreach ($rows as $r) {
        // ---------- CRONO ----------
        $anc = trim((string)($r['crono_marco_override'] ?? ''));
        $cr = ['curado' => (bool)$r['data_necessaria_override']];
        if ($anc !== '') { $cr['regra'] = 'buscar_nome_pegar_primeira_data'; $cr['ancora_nome'] = $anc; $stats['crono_com_ancora']++; }
        else { $cr['regra'] = 'auto_por_termos'; $cr['ancora_nome'] = null; }
        if (trim((string)$r['termos_cronograma'])) $cr['termos_template'] = trim($r['termos_cronograma']);
        if (trim((string)$r['marco_cronograma']))  $cr['marco_template']  = trim($r['marco_cronograma']);

        // ---------- VERBA ----------
        $met = $r['verba_metodo'] ?: null;
        $vb = ['metodo'=>$met, 'curado'=>(bool)(int)($r['verba_curada'] ?? 0)];
        if ($met === 'analitico') {
            $refs = json_decode($r['orcamento_refs'] ?? '[]', true) ?: [];
            $descs = [];
            foreach ($refs as $L) {
                $l = $LIN[(int)$L] ?? null; if (!$l) continue;
                $d = $l['descricao'];
                if (!isset($descs[$d])) $descs[$d] = ['n'=>0,'topos'=>[]];
                $descs[$d]['n']++; $descs[$d]['topos'][$topo($L)] = 1;
            }
            uasort($descs, function($a,$b){ return $b['n'] <=> $a['n']; });
            $vb['linhas'] = []; $parciais = [];
            foreach ($descs as $d => $v) {
                $vb['linhas'][] = ['descricao'=>$d, 'ocorrencias'=>$v['n'], 'locais_topo'=>array_keys($v['topos'])];
                if ($v['n'] < ($descCount[$d] ?? $v['n']))
                    $parciais[] = ['descricao'=>$d, 'pegou'=>$v['n'], 'de'=>$descCount[$d], 'locais'=>array_keys($v['topos'])];
            }
            $vb['n_linhas'] = count($refs);
            if ($parciais) $vb['escopo_parcial'] = $parciais;
            $ex = json_decode($r['orcamento_excl'] ?? '[]', true) ?: [];
            if ($ex) {
                $exd = [];
                foreach ($ex as $e) { $d = $e['d'] ?? '?'; $exd[$d] = ($exd[$d] ?? 0) + 1; }
                arsort($exd);
                $vb['exclusoes'] = [];
                foreach ($exd as $k=>$v) $vb['exclusoes'][] = ['insumo'=>$k, 'de_n_linhas'=>$v];
            }
            $stats['verba_analitico']++;
        } elseif ($met === 'composicao') {
            $sel = json_decode($r['composicao_sel'] ?? '[]', true) ?: [];
            $agg = []; $todosSis = [];
            foreach ($sel as $s) {
                $key = ($s['desc'] ?? '?') . '|' . ($s['tipo'] ?? '');
                if (!isset($agg[$key])) $agg[$key] = ['insumo'=>$s['desc'] ?? '?','tipo'=>$s['tipo'] ?? '','n_comp'=>0,
                                                     'comps'=>[],'sistemas'=>[],'escopo_total'=>true,'custo'=>0.0];
                $a = &$agg[$key]; $a['n_comp']++;
                $cd = $COMPD[(int)($s['cid'] ?? 0)] ?? null;
                if ($cd) $a['comps'][$cd] = 1;
                $a['custo'] += (float)($s['area'] ?? 0) * (float)($s['coef'] ?? 0) * (float)($s['rs_unit'] ?? 0);
                $locais = $s['locais'] ?? null;
                if (is_array($locais) && $locais) {
                    foreach ($locais as $L) {
                        $l = $LIN[(int)$L] ?? null;
                        $sis = $l ? (sup_sistema(sup_normt($l['path_str'])) ?: '(geral)') : '(geral)';
                        $a['sistemas'][$sis] = ($a['sistemas'][$sis] ?? 0) + 1;
                        $todosSis[$sis] = ($todosSis[$sis] ?? 0) + 1;
                    }
                    if ($cd !== null && count($locais) < ($descCount[$cd] ?? count($locais))) $a['escopo_total'] = false;
                }
                unset($a);
            }
            uasort($agg, function($a,$b){ return $b['custo'] <=> $a['custo']; });
            $vb['insumos'] = [];
            foreach ($agg as $a) {
                arsort($a['sistemas']);
                $vb['insumos'][] = ['insumo'=>$a['insumo'], 'tipo'=>$a['tipo'], 'em_n_composicoes'=>$a['n_comp'],
                    'composicoes_exemplo'=>array_slice(array_keys($a['comps']), 0, 4),
                    'sistemas'=>($a['sistemas'] ?: null),
                    'escopo'=>$a['escopo_total'] ? 'todos_os_locais' : 'recorte_de_locais',
                    'custo_origem'=>round($a['custo'], 2)];
            }
            if ($todosSis) {
                arsort($todosSis);
                $top = array_key_first($todosSis); $nTop = $todosSis[$top]; $tot = array_sum($todosSis);
                if ($top !== '(geral)' && $tot > 0 && $nTop / $tot >= 0.85) {
                    $tipos = array_unique(array_map(function($a){ return $a['tipo']; }, array_values($agg)));
                    $tipoR = (count($tipos) === 1 ? $tipos[0] : null);
                    // COBERTURA: o recorte "pegue o sistema inteiro" só vale se a seleção da origem COBRIU
                    // a maior parte das folhas disponíveis nesse sistema (senão 1 insumo concentrado num
                    // sistema viraria "pegue tudo" — ex.: Ligação Provisória classificada em Esgoto).
                    $avail = 0;
                    foreach ($COMPD as $cid => $cdesc) {
                        $ls = $linesByDesc[$cdesc] ?? []; if (!$ls) continue;
                        $nS = 0; foreach ($ls as $L) if ($SIS[$L] === $top) $nS++;
                        if (!$nS) continue;
                        $cnt = $INSCNT[$cid] ?? [];
                        $nIns = $tipoR !== null ? (int)($cnt[$tipoR] ?? 0) : array_sum($cnt);
                        $avail += $nS * $nIns;
                    }
                    if ($avail > 0 && $nTop / $avail >= 0.6) {
                        $vb['recorte_sugerido'] = ['sistema'=>$top, 'tipo'=>$tipoR,
                                                   'cobertura_origem'=>round($nTop / $avail, 2)];
                    }
                }
            }
            $stats['verba_composicao']++;
        } elseif ($met === 'manual') {
            $vb['valor_manual_origem'] = $r['verba_override'] !== null ? (float)$r['verba_override'] : null;
            $stats['verba_manual']++;
        } else { $stats['verba_sem']++; }
        if (trim((string)$r['termos_orcamento'])) $vb['termos_template'] = trim($r['termos_orcamento']);

        // ---------- QUANT ----------
        $qf = $r['quantitativo_fonte'] ?: null;
        $qt = ['fonte'=>$qf, 'curado'=>(bool)(int)($r['quant_curada'] ?? 0), 'unidade'=>$r['quantitativo_unidade'] ?: null];
        if ($qf === 'orcamento') {
            $qrefs = json_decode($r['quantitativo_refs'] ?? '[]', true) ?: [];
            $qd = [];
            foreach ($qrefs as $L) { $l = $LIN[(int)$L] ?? null; if ($l) $qd[$l['descricao']] = ($qd[$l['descricao']] ?? 0) + 1; }
            arsort($qd);
            if ($qd) { $qt['linhas'] = []; foreach ($qd as $k=>$v) $qt['linhas'][] = ['descricao'=>$k,'ocorrencias'=>$v]; }
        } elseif ($qf === 'composicao') {
            $qs = json_decode($r['quant_comp_sel'] ?? '[]', true) ?: [];
            $qt['insumos'] = array_values(array_unique(array_map(function($s){ return $s['desc'] ?? '?'; }, $qs)));
            $drv = [];
            foreach ((json_decode($r['composicao_sel'] ?? '[]', true) ?: []) as $s) if (!empty($s['q'])) $drv[$s['desc'] ?? '?'] = 1;
            if ($drv) $qt['driver_na_verba'] = array_keys($drv);
        } elseif ($qf === 'manual') {
            $qt['valor_manual_origem'] = $r['quantitativo_valor'] !== null ? (float)$r['quantitativo_valor'] : null;
        }

        $up->execute([(int)$r['sid'], $mc, $obra['nome'],
                      json_encode($cr, JSON_UNESCAPED_UNICODE), json_encode($vb, JSON_UNESCAPED_UNICODE),
                      json_encode($qt, JSON_UNESCAPED_UNICODE),
                      $NOTAS[(int)$r['sid']] ?? null, date('c')]);
        $n++;
    }
    echo json_encode(['ok'=>true, 'derivadas'=>$n, 'obra'=>$obra['nome'], 'metodo_construtivo'=>$mc, 'stats'=>$stats], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
