<?php
/**
 * AUTO-VÍNCULO: aplica o dicionário de receitas numa obra nova — a replicação da curadoria.
 *
 * Para cada serviço com receita (mesmo método construtivo da obra), preenche APENAS o que está vazio:
 *   CRONO  — busca a tarefa-âncora por nome (tokenizado, tolera plural) no cronograma DA OBRA e
 *            pega a PRIMEIRA data (regra do usuário: a decisão de empreitada antecede as torres).
 *   VERBA  — analitico: re-seleciona linhas pela DESCRIÇÃO (+ re-aplica exclusões de insumo);
 *            composicao: re-executa o recorte (sistema/tipo) ou casa insumos por nome dentro dos
 *            sistemas aprendidos. Anti-dup: respeita o que outros itens da obra já reivindicaram.
 *   QUANT  — orcamento: re-soma linhas pela descrição; composicao: marca os insumos-driver.
 *
 * Tudo entra como SUGERIDO (auto_flags {crono,verba,quant}; verba_curada/quant_curada = 0) —
 * a curadoria humana confirma salvando a aba (item_update limpa a flag da dimensão).
 *
 * POST (ADMIN) {acao:'aplicar', obra_id, me, dry:0|1}  -> relatório por item + totais
 */
header('Content-Type: application/json; charset=utf-8');
set_time_limit(600);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/supabase.php';

if (!function_exists('sup_normt')) { // fallback deploy parcial
    function sup_normt($s){ $m=['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c','Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e','Í'=>'i','Ó'=>'o','Ô'=>'o','Õ'=>'o','Ú'=>'u','Ç'=>'c']; return strtolower(strtr((string)$s,$m)); }
}
if (!function_exists('sup_sistema')) {
    function sup_sistema($p){ $w=function($re) use ($p){ return preg_match('#\b'.$re.'\b#',$p)===1; };
        if ($w('gas')) return 'Gás'; if (strpos($p,'quente')!==false) return 'Água Quente';
        if (strpos($p,'agua fria')!==false || $w('fria')) return 'Água Fria';
        if (strpos($p,'esgoto')!==false || strpos($p,'sanit')!==false) return 'Esgoto / Sanitário';
        if (strpos($p,'pluvia')!==false) return 'Águas Pluviais'; if (strpos($p,'incendio')!==false) return 'Incêndio';
        if (strpos($p,'hidr')!==false) return 'Hidráulica (geral)'; return null; }
}

function av_tokens($s) {   // tokens de busca da âncora: palavras >=3 chars, plural tolerado (mesma regra do crono_search)
    $out = [];
    foreach (preg_split('/\s+/', sup_normt($s)) as $t) {
        $t = preg_replace('/[^a-z0-9]/', '', $t);
        if (strlen($t) < 3) continue;
        if (substr($t, -1) === 's' && strlen($t) > 3) $t = substr($t, 0, -1);
        $out[] = $t;
    }
    return $out;
}

// Mapa de KEYWORDS p/ vincular verba em orçamentos FORA do padrão Tron (Polastri) por SISTEMA (path) + TERMO (descrição).
// [nome_servico_normalizado => ['prio'=>N (menor reivindica 1º), 'sis'=>[termos no path], 'inc'=>[termos na descrição; 'x='=palavra exata], 'exc'=>[]]]
// Foco nos GRANDES de concreto armado (estrutura/fundação/contenção/alvenaria/elevadores + imperm/contrapiso). O resto = curadoria.
function av_keyword_map() {
    return [
        // CONTENÇÃO
        'cravacao de perfil metalico'            => ['prio'=>5,  'sis'=>['contencao','cortina'], 'inc'=>['perfil','cravacao'], 'exc'=>[]],
        'painel dupla face cortina de contencao' => ['prio'=>6,  'sis'=>['contencao','cortina'], 'inc'=>['painel','cortina','prancha'], 'exc'=>['perfil']],
        'tirantes - mo para execucao'            => ['prio'=>7,  'sis'=>['contencao'], 'inc'=>['tirante'], 'exc'=>[]],
        // FUNDAÇÃO
        'execucao de estaca helice continua'     => ['prio'=>9,  'sis'=>['fundacao'], 'inc'=>['estaca','helice','perfuracao','escava'], 'exc'=>['aco=','concreto','armacao']],
        'aco para fundacao profunda - c&d + armacao' => ['prio'=>10, 'sis'=>['fundacao'], 'inc'=>['aco=','armacao','vergalhao','ca-50','ca-60'], 'exc'=>['tela']],
        'concreto para fundacao profunda'        => ['prio'=>11, 'sis'=>['fundacao'], 'inc'=>['concreto'], 'exc'=>['magro','bombeamento']],
        'aco para fundacao rasa - c&d'           => ['prio'=>12, 'sis'=>['fundacao','baldrame','sapata'], 'inc'=>['aco=','armacao'], 'exc'=>['tela']],
        'concreto fundacao rasa (blocos + vigas baldrame)' => ['prio'=>13, 'sis'=>['fundacao','baldrame','sapata'], 'inc'=>['concreto'], 'exc'=>['magro','bombeamento']],
        'forma madeira para fundacao (mat)'      => ['prio'=>14, 'sis'=>['fundacao','baldrame'], 'inc'=>['forma'], 'exc'=>[]],
        // ESTRUTURA
        'bombeamento de concreto'                => ['prio'=>20, 'sis'=>[], 'inc'=>['bombeamento','bomba lanca','bombeavel'], 'exc'=>[]],
        'tela de aco para laje (mat)'            => ['prio'=>26, 'sis'=>['estrutura','torre'], 'inc'=>['tela'], 'exc'=>[]],
        'escoramento torres + periferia'         => ['prio'=>28, 'sis'=>['estrutura','torre','escora'], 'inc'=>['escora'], 'exc'=>[]],
        'aco estrutura (c&d)'                    => ['prio'=>30, 'sis'=>['estrutura','torre'], 'inc'=>['aco=','armacao','vergalhao','ca-50','ca-60'], 'exc'=>['tela','fundacao']],
        'concreto estrutura'                     => ['prio'=>31, 'sis'=>['estrutura','torre'], 'inc'=>['concreto'], 'exc'=>['bombeamento','magro','fundacao']],
        'formas pronta obra toda'                => ['prio'=>32, 'sis'=>['estrutura','torre'], 'inc'=>['forma'], 'exc'=>['fundacao']],
        // ALVENARIA ESTRUTURAL (obras de bloco estrutural sem aba de composição no R10 — Licel/LTB-3).
        // 'metodo'=>'alvenaria': só dispara em obra de método alvenaria (não contamina obra de concreto).
        // Prio menor que a vedação → reivindica o bloco estrutural 1º. A linha COMPOSTA "Alvenaria estrutural
        // com blocos…" (que embute bloco+argamassa+M.O.) cai inteira em Blocos (não dá pra rachar sem composição).
        'alvenaria estrutural — blocos de concreto'          => ['prio'=>38, 'metodo'=>'alvenaria', 'sis'=>['alvenaria'], 'inc'=>['bloco','canaleta','verga'], 'exc'=>[]],
        'alvenaria estrutural — argamassa e demais materiais' => ['prio'=>39, 'metodo'=>'alvenaria', 'sis'=>['alvenaria'], 'inc'=>['argamassa','graute','grouteamento'], 'exc'=>['bloco']],
        'alvenaria estrutural — mao de obra (empreitada)'    => ['prio'=>44, 'metodo'=>'alvenaria', 'sis'=>['alvenaria'], 'inc'=>['assentamento','elevacao de alvenaria','levantamento de alvenaria'], 'exc'=>['bloco','argamassa']],
        // ALVENARIA (vedação)
        'blocos (vedacao)'                       => ['prio'=>40, 'sis'=>['alvenaria'], 'inc'=>['bloco'], 'exc'=>[]],
        'argamassa para alvenaria de vedacao (mat)' => ['prio'=>41, 'sis'=>['alvenaria'], 'inc'=>['argamassa'], 'exc'=>[]],
        'mo alvenaria de vedacao'                => ['prio'=>42, 'sis'=>['alvenaria'], 'inc'=>['mao de obra','m.o','empreitada','assentamento','pedreiro','servente'], 'exc'=>['bloco','argamassa','tela']],
        // ELEVADORES
        'elevadores definitivos'                 => ['prio'=>45, 'sis'=>[], 'inc'=>['elevador'], 'exc'=>['cremalheira','de obra','provisorio','carga']],
        // IMPERMEABILIZAÇÃO / CONTRAPISO (limpos)
        'impermeabilizacao torre'                => ['prio'=>60, 'sis'=>['impermeabiliza'], 'inc'=>['impermeabiliza','manta','asfaltic','cristaliza'], 'exc'=>[]],
        'contra piso - torre'                    => ['prio'=>62, 'sis'=>['regulariza','revestimento','contrapiso','piso'], 'inc'=>['contrapiso','contra piso','regulariza'], 'exc'=>['reboco','chapisco','emboco']],
    ];
}

try {
    $pdo = db();
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $perms = user_perms($pdo, $in['me'] ?? null);
    if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Apenas administradores.']); exit; }

    // ============ VÍNCULO POR KEYWORD (orçamentos FORA do padrão Tron — Polastri/Vitrius) ============
    // ISOLADO POR OBRA: lê SÓ o orçamento desta obra, escreve SÓ nas células desta obra. NÃO toca no
    // dicionário `receita` nem em nenhuma outra obra. Grava idêntico ao analítico (orcamento_refs + verba,
    // 🤖 sugerido, verba_curada=0) → reversível/curável. Só preenche onde a verba está VAZIA (não clobbera).
    // POST (ADMIN) {acao:'vincular_keyword', obra_id, me, dry:0|1}
    if (($in['acao'] ?? '') === 'vincular_keyword') {
        $obraId = (int)($in['obra_id'] ?? 0); if ($obraId < 2) throw new Exception('obra_id >= 2');
        $dry = !empty($in['dry']);
        $ob = $pdo->prepare("SELECT nome, orcamento_total, metodo_construtivo FROM obra WHERE id=?"); $ob->execute([$obraId]); $ob = $ob->fetch();
        if (!$ob) throw new Exception('obra não encontrada');
        $isAlv = stripos((string)($ob['metodo_construtivo'] ?? ''), 'alvenaria') !== false;   // entradas 'metodo'=>'alvenaria' só disparam aqui
        // folhas do orçamento DESTA obra
        $F = [];
        $q = $pdo->prepare("SELECT id, descricao, path_str, valor FROM orcamento_linha WHERE obra_id=? AND folha=1"); $q->execute([$obraId]);
        foreach ($q->fetchAll() as $l) { $l['_d'] = sup_normt($l['descricao']); $l['_p'] = sup_normt($l['path_str']); $F[(int)$l['id']] = $l; }
        if (!$F) throw new Exception('obra sem orçamento importado');
        // serviços do radar DESTA obra (por nome normalizado) + estado da verba
        $SV = [];
        $q = $pdo->prepare("SELECT r.servico_id sid, s.nome, r.verba_metodo, r.verba_curada, r.auto_flags FROM radar_item r JOIN servico s ON s.id=r.servico_id WHERE r.obra_id=?");
        $q->execute([$obraId]);
        // chave = nome normalizado SEM pontuação/espaços (o em-dash "—" dos serviços de alvenaria some) → casa robusto
        foreach ($q->fetchAll() as $r) $SV[preg_replace('/[^a-z0-9]/', '', sup_normt($r['nome']))] = $r;
        // matcher: sis=substring no path; termo com sufixo '=' = palavra exata (\bX\b), senão substring
        $mt = function($hay, $terms) { foreach ((array)$terms as $t) { if ($t === '') continue;
            if (substr($t, -1) === '=') { if (preg_match('/\b' . preg_quote(substr($t, 0, -1), '/') . '\b/', $hay)) return true; }
            elseif (strpos($hay, $t) !== false) return true; } return false; };
        $MAP = av_keyword_map();
        uasort($MAP, fn($a, $b) => ($a['prio'] <=> $b['prio']));   // mais específico (prio menor) reivindica 1º
        $claimed = []; $out = [];
        foreach ($MAP as $nomeNorm => $sp) {
            if (($sp['metodo'] ?? '') === 'alvenaria' && !$isAlv) continue; // entrada de alvenaria estrutural não roda em obra de concreto
            $s = $SV[preg_replace('/[^a-z0-9]/', '', $nomeNorm)] ?? null; if (!$s) continue;
            if ((int)($s['verba_curada'] ?? 0) === 1) continue;            // respeita curadoria humana
            if (trim((string)($s['verba_metodo'] ?? '')) !== '') continue; // não clobbera verba já preenchida
            $sid = (int)$s['sid']; $refs = []; $sum = 0.0;
            foreach ($F as $fid => $f) {
                if (isset($claimed[$fid])) continue;
                if (!empty($sp['sis']) && !$mt($f['_p'], $sp['sis'])) continue;
                if (!$mt($f['_d'], $sp['inc'])) continue;
                if (!empty($sp['exc']) && $mt($f['_d'], $sp['exc'])) continue;
                $claimed[$fid] = $sid; $refs[] = $fid; $sum += (float)$f['valor'];
            }
            if ($sum > 0) {
                $am = array_slice(array_map(fn($id) => $F[$id]['descricao'] . ' — R$ ' . number_format($F[$id]['valor'], 0, ',', '.'), $refs), 0, 4);
                $out[$sid] = ['servico_id' => $sid, 'nome' => $s['nome'], 'verba' => round($sum, 2), 'linhas' => count($refs), 'amostra' => $am, '_af' => $s['auto_flags'], '_refs' => $refs];
            }
        }
        $tot = 0.0; foreach ($out as $o) $tot += $o['verba'];
        if ($dry) {
            $prev = array_map(fn($o) => ['servico_id' => $o['servico_id'], 'nome' => $o['nome'], 'verba' => $o['verba'], 'linhas' => $o['linhas'], 'amostra' => $o['amostra']], array_values($out));
            usort($prev, fn($a, $b) => $b['verba'] <=> $a['verba']);
            echo json_encode(['ok' => true, 'dry' => true, 'obra' => $ob['nome'], 'itens' => count($out), 'verba_total' => round($tot, 2),
                'cobertura_pct' => $ob['orcamento_total'] ? round(100 * $tot / (float)$ob['orcamento_total'], 1) : null,
                'folhas_reivindicadas' => count($claimed), 'folhas_total' => count($F), 'preview' => $prev], JSON_UNESCAPED_UNICODE); exit;
        }
        $upd = $pdo->prepare("UPDATE radar_item SET orcamento_refs=?, verba_override=?, verba_metodo='analitico', verba_material=?, verba_mo=NULL, verba_curada=0, auto_flags=?, updated_at=? WHERE obra_id=? AND servico_id=?");
        $now = date('c'); $n = 0;
        $pdo->beginTransaction();
        foreach ($out as $sid => $o) {
            $af = json_decode($o['_af'] ?: '{}', true) ?: []; $af['verba'] = 1;   // preserva crono/quant, marca verba=1 (🤖)
            $upd->execute([json_encode(array_values($o['_refs'])), $o['verba'], $o['verba'], json_encode($af), $now, $obraId, $sid]);
            $n++;
        }
        $pdo->commit();
        echo json_encode(['ok' => true, 'dry' => false, 'obra' => $ob['nome'], 'itens_gravados' => $n, 'verba_total' => round($tot, 2),
            'cobertura_pct' => $ob['orcamento_total'] ? round(100 * $tot / (float)$ob['orcamento_total'], 1) : null], JSON_UNESCAPED_UNICODE); exit;
    }

    if (($in['acao'] ?? '') !== 'aplicar') throw new Exception('acao inválida');
    $obraId = (int)($in['obra_id'] ?? 0);
    if ($obraId < 2) throw new Exception('obra_id deve ser >= 2 (a origem do aprendizado não se auto-aplica)');
    $dry = !empty($in['dry']);

    $oq = $pdo->prepare("SELECT * FROM obra WHERE id=?"); $oq->execute([$obraId]);
    $obra = $oq->fetch(); if (!$obra) throw new Exception('obra não encontrada');
    $mc = $obra['metodo_construtivo'] ?: 'concreto armado convencional';

    // ---------- bases da obra ----------
    $LIN = []; $linesByDesc = [];
    $q = $pdo->prepare("SELECT id, descricao, path_str, qtde, valor, unidade FROM orcamento_linha WHERE obra_id=? AND folha=1");
    $q->execute([$obraId]);
    foreach ($q->fetchAll() as $l) {
        $id = (int)$l['id'];
        $parts = explode('›', (string)$l['path_str']);
        $l['topo'] = trim($parts[0]);
        $l['sis']  = sup_sistema(sup_normt($l['path_str'])) ?: '(geral)';
        $LIN[$id] = $l;
        $linesByDesc[$l['descricao']][] = $id;
    }
    $COMPD = []; $compByDesc = []; $INS = [];
    $q = $pdo->prepare("SELECT id, descricao FROM composicao WHERE obra_id=?"); $q->execute([$obraId]);
    foreach ($q->fetchAll() as $c) { $COMPD[(int)$c['id']] = $c['descricao']; $compByDesc[$c['descricao']] = (int)$c['id']; }
    $q = $pdo->prepare("SELECT ci.composicao_id, ci.descricao, ci.unidade, ci.coef, ci.rs_unit, ci.tipo
                        FROM composicao_insumo ci JOIN composicao c ON c.id=ci.composicao_id
                        WHERE c.obra_id=? ORDER BY ci.composicao_id, ci.id");
    $q->execute([$obraId]);
    foreach ($q->fetchAll() as $r) $INS[(int)$r['composicao_id']][] = $r;

    // receitas do método construtivo da obra
    $RC = [];
    $q = $pdo->prepare("SELECT * FROM receita WHERE metodo_construtivo=?"); $q->execute([$mc]);
    foreach ($q->fetchAll() as $r) $RC[(int)$r['servico_id']] =
        ['crono'=>json_decode($r['crono'] ?: 'null', true), 'verba'=>json_decode($r['verba'] ?: 'null', true), 'quant'=>json_decode($r['quant'] ?: 'null', true)];

    // itens da obra
    $ITENS = [];
    $q = $pdo->prepare("SELECT r.servico_id sid, s.nome, r.data_necessaria_override, r.crono_marco_override,
                               r.verba_metodo, r.quantitativo_fonte, r.orcamento_refs, r.composicao_sel, r.auto_flags
                        FROM radar_item r JOIN servico s ON s.id=r.servico_id WHERE r.obra_id=? ORDER BY r.servico_id");
    $q->execute([$obraId]);
    foreach ($q->fetchAll() as $r) $ITENS[(int)$r['sid']] = $r;

    // ---------- anti-dup: reivindicações já existentes na obra ----------
    $whole = [];      // lineId => ['ordem'=>, 'excl'=>set]
    $insClaim = [];   // "cid#idx|line" => ordem;  + $lineHasIns[line] = true
    $lineHasIns = [];
    foreach ($ITENS as $sid => $r) {
        foreach ((json_decode($r['orcamento_refs'] ?: '[]', true) ?: []) as $L) $whole[(int)$L] = ['ordem'=>$sid, 'excl'=>[]];
        foreach ((json_decode($r['composicao_sel'] ?: '[]', true) ?: []) as $s) {
            $cid = (int)($s['cid'] ?? 0); $idx = (int)($s['idx'] ?? -1);
            foreach ((is_array($s['locais'] ?? null) ? $s['locais'] : []) as $L) {
                $insClaim["$cid#$idx|" . (int)$L] = $sid; $lineHasIns[(int)$L] = true;
            }
        }
    }

    // ---------- cronograma da obra: TODAS as tarefas (paginado) ----------
    $TASKS = []; $off = 0;
    while (true) {
        $page = sb_get('obra_cronograma_tarefas?cronograma_id=' . rawurlencode('eq.' . $obra['cronograma_id'])
             . '&select=nome,start&order=ordem.asc&limit=1000&offset=' . $off);
        foreach ($page as $t) if (!empty($t['start'])) $TASKS[] = ['n'=>sup_normt($t['nome']), 'nome'=>$t['nome'], 'start'=>substr($t['start'],0,10)];
        if (count($page) < 1000) break;
        $off += 1000;
    }

    // ---------- aplicação ----------
    $upd = [];   // sid => [col => val]
    $af  = [];   // sid => auto flags a acrescentar
    $rel = [];   // relatório
    $tot = ['crono'=>0,'verba'=>0,'quant'=>0];
    $setU = function($sid, $col, $val) use (&$upd) { $upd[$sid][$col] = $val; };

    // ===== FASE A: CRONO =====
    foreach ($ITENS as $sid => $it) {
        $rc = $RC[$sid] ?? null; if (!$rc) continue;
        $R = &$rel[$sid]; $R['nome'] = $it['nome'];
        $anc = $rc['crono']['ancora_nome'] ?? null;
        if ($it['data_necessaria_override']) { $R['crono'] = 'já definido'; continue; }
        if (!$anc) { $R['crono'] = 'sem âncora (auto por termos segue valendo)'; continue; }
        // a âncora pode trazer ALTERNATIVAS separadas por ";" (sinônimos de tarefa) — tenta cada uma
        $cands = array_values(array_filter(array_map('trim', explode(';', $anc)), fn($x)=>$x!==''));
        if (!$cands) $cands = [$anc];
        $best = null; $bestNome = null; $aprox = false;
        foreach ($cands as $cand) {   // 1º: match EXATO (todas as palavras do candidato) → 1ª data; melhor entre candidatos
            $toks = av_tokens($cand); if (!$toks) continue;
            foreach ($TASKS as $t) {
                $ok = true;
                foreach ($toks as $tk) if (strpos($t['n'], $tk) === false) { $ok = false; break; }
                if ($ok && ($best === null || $t['start'] < $best)) { $best = $t['start']; $bestNome = $t['nome']; }
            }
        }
        if (!$best) {   // 2º: APROXIMADO — melhor pontuação de palavras entre TODOS os candidatos (regra do usuário:
            $bestScore = 0;                                    // se a tarefa exata não existe, algo de prazo similar)
            foreach ($cands as $cand) {
                $toks = av_tokens($cand); if (count($toks) < 2) continue;
                $minHit = max(2, (int)ceil(count($toks) * 0.5));
                foreach ($TASKS as $t) {
                    $hit = 0;
                    foreach ($toks as $tk) if (strpos($t['n'], $tk) !== false) $hit++;
                    if ($hit < $minHit) continue;
                    if ($hit > $bestScore || ($hit === $bestScore && $best !== null && $t['start'] < $best)) {
                        $bestScore = $hit; $best = $t['start']; $bestNome = $t['nome'];
                    }
                }
            }
            if ($best) $aprox = true;
        }
        if ($best) {
            $setU($sid, 'crono_marco_override', $bestNome);
            $setU($sid, 'data_necessaria_override', $best);
            $af[$sid]['crono'] = 1; $tot['crono']++;
            $R['crono'] = ($aprox ? '≈ ' : '✓ ') . "“" . $bestNome . "” → $best" . ($aprox ? ' (aproximado — confira)' : '');
        } else $R['crono'] = "âncora não encontrada: “{$anc}”";
    }

    // helper: valor unitário de um insumo (desc) dentro da composição de uma linha (p/ exclusões do analítico)
    $insUnit = function($lineDesc, $insDesc) use ($compByDesc, $INS) {
        $cid = $compByDesc[$lineDesc] ?? 0; if (!$cid) return null;
        foreach (($INS[$cid] ?? []) as $i) if ($i['descricao'] === $insDesc) return (float)$i['coef'] * (float)$i['rs_unit'];
        return null;
    };

    // ===== FASE B: VERBA ANALÍTICO =====
    foreach ($ITENS as $sid => $it) {
        $rc = $RC[$sid] ?? null; if (!$rc || ($rc['verba']['metodo'] ?? null) !== 'analitico') continue;
        $R = &$rel[$sid];
        if ($it['verba_metodo']) { $R['verba'] = 'já definida'; continue; }
        $exclNames = array_map(function($e){ return $e['insumo']; }, $rc['verba']['exclusoes'] ?? []);
        $refs = []; $conf = 0; $naoachou = [];
        foreach (($rc['verba']['linhas'] ?? []) as $ln) {
            $ids = $linesByDesc[$ln['descricao']] ?? [];
            if (!$ids) { $naoachou[] = $ln['descricao']; continue; }
            foreach ($ids as $L) {
                if (isset($whole[$L]) || isset($lineHasIns[$L])) { $conf++; continue; }   // já reivindicada → não duplica
                $refs[] = $L;
            }
        }
        if (!$refs) { $R['verba'] = 'analítico: nenhuma linha casou' . ($naoachou ? ' (' . count($naoachou) . ' descrições ausentes)' : ''); continue; }
        // exclusões re-aplicadas por descrição de insumo
        $excl = []; $soma = 0.0; $exclVal = 0.0; $exclSet = [];
        foreach ($refs as $L) {
            $soma += (float)$LIN[$L]['valor'];
            foreach ($exclNames as $E) {
                $u = $insUnit($LIN[$L]['descricao'], $E);
                if ($u !== null) { $excl[] = ['l'=>$L, 'd'=>$E]; $exclVal += (float)$LIN[$L]['qtde'] * $u; $exclSet[$E] = 1; }
                elseif ($E === $LIN[$L]['descricao']) { $excl[] = ['l'=>$L, 'd'=>$E]; $exclVal += (float)$LIN[$L]['valor']; $exclSet[$E] = 1; }
            }
        }
        $verba = $soma - $exclVal;
        foreach ($refs as $L) $whole[$L] = ['ordem'=>$sid, 'excl'=>$exclSet];
        $setU($sid, 'orcamento_refs', json_encode(array_values($refs)));
        $setU($sid, 'orcamento_excl', $excl ? json_encode($excl, JSON_UNESCAPED_UNICODE) : null);
        $setU($sid, 'verba_metodo', 'analitico');
        $setU($sid, 'verba_override', $verba);
        $setU($sid, 'verba_curada', 0);
        $af[$sid]['verba'] = 1; $tot['verba']++;
        $R['verba'] = '✓ analítico: ' . count($refs) . ' linhas · R$ ' . number_format($verba, 0, ',', '.')
                    . ($excl ? ' · ' . count($excl) . ' exclusões' : '') . ($conf ? " · $conf em conflito (puladas)" : '')
                    . ($naoachou ? ' · ' . count($naoachou) . ' descrições sem par' : '');
    }

    // ===== FASE C: VERBA COMPOSIÇÃO =====
    foreach ($ITENS as $sid => $it) {
        $rc = $RC[$sid] ?? null; if (!$rc || ($rc['verba']['metodo'] ?? null) !== 'composicao') continue;
        $R = &$rel[$sid];
        if ($it['verba_metodo']) { $R['verba'] = 'já definida'; continue; }
        $recorte = $rc['verba']['recorte_sugerido'] ?? null;
        $specs = [];   // cada spec: {sis:set|null, tipo:str|null, desc:normalizada|null}
        if ($recorte) $specs[] = ['sis'=>[$recorte['sistema'] => 1], 'tipo'=>$recorte['tipo'] ?? null, 'desc'=>null];
        else foreach (($rc['verba']['insumos'] ?? []) as $x) {
            $sis = null;
            if (!empty($x['sistemas'])) { $sis = []; foreach ($x['sistemas'] as $k => $_) $sis[$k] = 1; }
            $specs[] = ['sis'=>$sis, 'tipo'=>$x['tipo'] ?: null, 'desc'=>sup_normt($x['insumo'])];
        }
        if (!$specs) { $R['verba'] = 'composição: receita sem insumos'; continue; }
        // enumera folhas candidatas e agrupa por (cid,idx)
        // DEDUP: itera $compByDesc (1 composição por DESCRIÇÃO), não $COMPD (todos os cids). Composições
        // DUPLICADAS (mesma descrição, ex.: orçamento do Signa) re-casavam as MESMAS folhas sob cids diferentes
        // → o anti-dup insClaim (chave cid#idx|L) não pegava e a verba contava a mesma linha N vezes (Aço 96 grupos = R$191M).
        $sel = []; $confl = 0;
        foreach ($compByDesc as $cdesc => $cid) {
            $lines = $linesByDesc[$cdesc] ?? []; if (!$lines) continue;
            foreach (($INS[$cid] ?? []) as $idx => $i) {
                $din = sup_normt($i['descricao']);
                foreach ($specs as $sp) {
                    if ($sp['tipo'] !== null && $i['tipo'] !== $sp['tipo']) continue;
                    if ($sp['desc'] !== null && $din !== $sp['desc']) continue;
                    foreach ($lines as $L) {
                        if ($sp['sis'] !== null && !isset($sp['sis'][$LIN[$L]['sis']])) continue;
                        $k = "$cid#$idx|$L";
                        if (isset($insClaim[$k])) { $confl++; continue; }
                        if (isset($whole[$L]) && !isset($whole[$L]['excl'][$i['descricao']])) { $confl++; continue; }
                        $g = &$sel["$cid#$idx"];
                        if (!$g) $g = ['cid'=>$cid, 'idx'=>$idx, 'ins'=>$i, 'locais'=>[], 'area'=>0.0];
                        if (!in_array($L, $g['locais'], true)) { $g['locais'][] = $L; $g['area'] += (float)$LIN[$L]['qtde']; }
                        unset($g);
                    }
                    break;   // 1 spec por insumo basta
                }
            }
        }
        if (!$sel) { $R['verba'] = 'composição: nenhum insumo casou' . ($confl ? " ($confl folhas em conflito)" : ''); continue; }
        $clean = []; $vmat = 0.0; $vmo = 0.0;
        foreach ($sel as $g) {
            $i = $g['ins'];
            $custo = $g['area'] * (float)$i['coef'] * (float)$i['rs_unit'];
            if ($i['tipo'] === 'mo') $vmo += $custo; else $vmat += $custo;
            $det = [];   // locais_det agrupado por topo (o read-only do front usa isso)
            $grp = []; $lun = '';
            foreach ($g['locais'] as $L) { $t = $LIN[$L]['topo'] ?: '(local)'; $grp[$t] = ($grp[$t] ?? 0) + (float)$LIN[$L]['qtde']; if ($lun === '') $lun = $LIN[$L]['unidade'] ?? ''; }
            foreach ($grp as $t => $qq) $det[] = ['local'=>$t, 'qtde'=>$qq, 'unidade'=>$lun];
            $clean[] = ['cid'=>$g['cid'], 'idx'=>$g['idx'], 'area'=>$g['area'], 'q'=>false,
                        'desc'=>$i['descricao'], 'tipo'=>$i['tipo'], 'unidade'=>$i['unidade'],
                        'coef'=>(float)$i['coef'], 'rs_unit'=>(float)$i['rs_unit'], 'locais'=>$g['locais'], 'locais_det'=>$det];
            foreach ($g['locais'] as $L) { $insClaim[$g['cid'] . '#' . $g['idx'] . '|' . $L] = $sid; $lineHasIns[$L] = true; }
        }
        $verba = $vmat + $vmo;
        $setU($sid, 'composicao_sel', json_encode($clean, JSON_UNESCAPED_UNICODE));
        $setU($sid, 'verba_metodo', 'composicao');
        $setU($sid, 'verba_material', $vmat ?: null);
        $setU($sid, 'verba_mo', $vmo ?: null);
        $setU($sid, 'verba_override', $verba);
        $setU($sid, 'orcamento_refs', null);
        $setU($sid, 'verba_curada', 0);
        $af[$sid]['verba'] = 1; $tot['verba']++;
        $R['verba'] = '✓ composição: ' . count($clean) . ' insumos · R$ ' . number_format($verba, 0, ',', '.')
                    . ($recorte ? ' · recorte ' . $recorte['sistema'] : '') . ($confl ? " · $confl folhas em conflito" : '');
    }

    // ===== FASE D: QUANTITATIVO =====
    foreach ($ITENS as $sid => $it) {
        $rc = $RC[$sid] ?? null; if (!$rc) continue;
        $R = &$rel[$sid];
        if ($it['quantitativo_fonte']) { $R['quant'] = 'já definido'; continue; }
        $qf = $rc['quant']['fonte'] ?? null;
        if ($qf === 'composicao' && isset($upd[$sid]['composicao_sel'])) {
            $drv = []; foreach (($rc['quant']['driver_na_verba'] ?? $rc['quant']['insumos'] ?? []) as $d) $drv[sup_normt($d)] = 1;
            if (!$drv) { $R['quant'] = 'composição: receita sem driver'; continue; }
            $clean = json_decode($upd[$sid]['composicao_sel'], true); $qval = 0.0; $qun = ''; $hit = 0;
            foreach ($clean as &$s) {
                if (isset($drv[sup_normt($s['desc'])])) { $s['q'] = true; $qval += $s['area'] * $s['coef']; if ($qun === '') $qun = $s['unidade']; $hit++; }
            }
            unset($s);
            if ($hit && $qval > 0) {
                $setU($sid, 'composicao_sel', json_encode($clean, JSON_UNESCAPED_UNICODE));
                $setU($sid, 'quantitativo_valor', $qval); $setU($sid, 'quantitativo_unidade', $qun);
                $setU($sid, 'quantitativo_fonte', 'composicao'); $setU($sid, 'quantitativo_refs', null); $setU($sid, 'quant_curada', 0);
                $af[$sid]['quant'] = 1; $tot['quant']++;
                $R['quant'] = "✓ composição: " . number_format($qval, 0, ',', '.') . " $qun ($hit driver)";
            } else $R['quant'] = 'composição: driver não casou';
        } elseif ($qf === 'orcamento') {
            $refs = []; $soma = 0.0; $uns = [];
            foreach (($rc['quant']['linhas'] ?? []) as $ln) foreach (($linesByDesc[$ln['descricao']] ?? []) as $L) {
                $refs[] = $L; $soma += (float)$LIN[$L]['qtde']; $u = $LIN[$L]['unidade'] ?: ''; $uns[$u] = ($uns[$u] ?? 0) + 1;
            }
            if ($refs) {
                arsort($uns);
                $setU($sid, 'quantitativo_refs', json_encode(array_values(array_unique($refs))));
                $setU($sid, 'quantitativo_valor', $soma); $setU($sid, 'quantitativo_unidade', array_key_first($uns) ?: null);
                $setU($sid, 'quantitativo_fonte', 'orcamento'); $setU($sid, 'quant_curada', 0);
                $af[$sid]['quant'] = 1; $tot['quant']++;
                $R['quant'] = '✓ orçamento: ' . number_format($soma, 0, ',', '.');
            } else $R['quant'] = 'orçamento: descrições sem par';
        } elseif ($qf === 'manual') { $R['quant'] = 'manual na origem (não transferível)'; }
    }

    // ---------- grava ----------
    $gravados = 0;
    if (!$dry) {
        $pdo->beginTransaction();
        foreach ($upd as $sid => $cols) {
            // merge das auto_flags existentes com as novas
            $cur = !empty($ITENS[$sid]['auto_flags']) ? (json_decode($ITENS[$sid]['auto_flags'], true) ?: []) : [];
            foreach (($af[$sid] ?? []) as $k => $v) $cur[$k] = 1;
            $cols['auto_flags'] = $cur ? json_encode($cur) : null;
            $cols['updated_at'] = date('c');
            $set = []; $vals = [];
            foreach ($cols as $c => $v) { $set[] = "`$c` = ?"; $vals[] = $v; }
            $vals[] = $obraId; $vals[] = $sid;
            $pdo->prepare("UPDATE radar_item SET " . implode(', ', $set) . " WHERE obra_id=? AND servico_id=?")->execute($vals);
            $dims = implode('+', array_keys($af[$sid] ?? []));
            log_historico($pdo, $obraId, $sid, $ITENS[$sid]['nome'], $in['me'] ?? null, 'Auto-vínculo (receitas)',
                          'Auto-vínculo', '', $dims ?: '(sem dimensão)');
            $gravados++;
        }
        $pdo->commit();
    }

    echo json_encode(['ok'=>true, 'dry'=>$dry, 'obra'=>$obra['nome'], 'metodo_construtivo'=>$mc,
                      'sugeridos'=>$tot, 'itens_gravados'=>$gravados, 'tarefas_cronograma'=>count($TASKS),
                      'relatorio'=>$rel], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
