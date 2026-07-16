<?php
/**
 * TOP 20 — grupos de NEGOCIAÇÃO consolidados (aço, concreto, blocos, argamassas…) × PRÓXIMOS 12 MESES.
 * Objetivo: chegar no fornecedor com o VOLUME Caprem inteiro na mão (todas as obras do radar).
 * Duas categorias (abas no front): 'material' e 'servico' (MO + equipamentos).
 *
 * ===== MOTOR DE JANELA (v2) — nunca janela só com data inicial =====
 * A janela [início→fim] de cada item vem do CRONOGRAMA VIVO, em camadas:
 *   1. 'marco'  — TODAS as ocorrências da tarefa-âncora (mesmo nome repete por torre/pavimento) → min início/max fim;
 *   2. 'fase'   — se ficou pontual (<2 meses) ou sem fim: sobe pro RESUMO ANCESTRAL na WBS (o "grande marco":
 *                 Estrutura, Fundação, Acabamento…), unificado entre torres pelo nome (nível >= 2, nunca a raiz);
 *   3. 'termos' — janela de todas as tarefas que casam os termos do De-Para do serviço;
 *   4. 'marco' curto com fim (janela de 1 mês legítima) — só depois de tentar alargar;
 *   5. 'estimada' — último recurso: início + 6 meses (sinalizado no drill).
 *
 * ===== CONSUMO PELO ANDAMENTO =====
 * Se o marco já andou X% (percent_complete do cronograma vivo, ponderado pela duração das tarefas), X% do
 * volume JÁ FOI CONSUMIDO e sai da conta. O RESTANTE distribui LINEARMENTE do mês atual (ou do início, se
 * futuro) até o fim da janela. Sem % no cronograma → estima pelo tempo decorrido da janela; janela toda no
 * passado sem % → o restante é demanda AGORA (mês atual — o radar diz que não foi comprado).
 * Itens 'Finalizado' e 'Não se aplica' ficam FORA por padrão (?fin=1 inclui). Sem data nenhuma = coluna 'sem'.
 * Unidades NUNCA se somam entre si: consolida POR UNIDADE (kg vira t na exibição).
 *
 * GET                         -> {grupos, meses, matriz} (semeia os grupos padrão se a tabela estiver vazia)
 * GET ?detalhe=<gid>&mes=<..> -> {detalhe:[…]} (a CONTA da célula: janela, fonte, % andado, alocado)
 * GET ?catalogo=1             -> {servicos:[{id,nome,grupo}]} (p/ o config escolher o que entra)
 * POST (ADMIN) {acao:'salvar_grupo', id?, nome, servicos[], categoria, ordem} | {acao:'excluir_grupo', id} | {acao:'reseed'}
 */
if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) @ob_start('ob_gzhandler');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/cronograma.php';

function t20_norm($s) {
    $map = ['Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e','Í'=>'i','Ó'=>'o','Ô'=>'o','Õ'=>'o','Ú'=>'u','Ç'=>'c',
            'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c'];
    return strtolower(strtr((string)$s, $map));
}

/** Casa um termo no nome normalizado. Padrão = PREFIXO em início de palavra (\bterm — 'forma' pega "formas",
 *  mas "plataforma" não). Termo com '=' no fim = palavra EXATA (\bterm\b — 'aco=' NÃO pega "Saco"/"Acoplada"). */
function t20_match($n, $t) {
    if (substr($t, -1) === '=') return (bool)preg_match('/\b' . preg_quote(substr($t, 0, -1), '/') . '\b/', $n);
    return (bool)preg_match('/\b' . preg_quote($t, '/') . '/', $n);
}

// grupos-padrão: [nome, categoria, INCLUDE = lista de listas (AND de ORs), EXCLUDE]
function t20_seed($pdo) {
    $SEED = [
        ['AÇO (CA-50/CA-60 + TELAS)', 'material', [['aco=','ca-50','ca-60','vergalh','tela de aco','telas de aco']], ['telha']],
        ['CONCRETO USINADO', 'material', [['concreto']], ['bloco','alvenaria','projetado','mo=','m.o','mao de obra','controle']],
        ['BLOCOS DE CONCRETO (ESTRUTURAL + VEDAÇÃO)', 'material', [['bloco']], ['desmold']],
        ['ARGAMASSAS E GRAUTE', 'material', [['argamassa','graute','grout']], []],
        ['ESQUADRIAS DE ALUMÍNIO', 'material', [['esquadria','janela','contra marco','contramarco','envidracamento','brise']], []],
        ['MÃO DE OBRA — ALVENARIA', 'servico', [['alvenaria'], ['mo=','m.o','mao de obra','empreitada']], ['argamassa','bloco de']],
        ['MÃO DE OBRA — ESTRUTURA (CONCRETO)', 'servico', [['estrutura'], ['mo=','m.o','mao de obra']], ['alvenaria']],
        ['FORMAS', 'servico', [['forma']], ['plataforma','informa']],
        ['ESCORAMENTO', 'servico', [['escoramento']], []],
        ['ELEVADORES DEFINITIVOS', 'material', [['elevador']], ['cremalheira','de obra']],
        ['PISOS E PORCELANATOS', 'material', [['porcelanato','pisos e revestimentos','piso intertravado','revestimento ceramic']], []],
        ['LOUÇAS E METAIS', 'material', [['louca','metais','bacia','cuba','lavatorio']], []],
        ['PINTURA (TINTAS/TEXTURA)', 'material', [['pintura','tinta','textura','selador']], []],
        ['GESSO E FORROS', 'servico', [['gesso','drywall','forro']], []],
        ['IMPERMEABILIZAÇÃO', 'servico', [['impermeabiliza']], []],
        ['COBERTURA (TELHAS/CALHAS)', 'material', [['cobertura','telha','calha','rufo']], []],
        ['FIOS E CABOS', 'material', [['fios e cabos','cabo isolado','fios']], []],
        ['MATERIAIS HIDRÁULICOS (TUBOS/CONEXÕES)', 'material', [['hidraulico','tubos e conexoes','pex','cpvc']], []],
        ['CONTRAPISO E REGULARIZAÇÃO', 'servico', [['contrapiso','contra piso','regularizacao']], []],
        ['EQUIPAMENTOS DE OBRA (GRUA/CREMALHEIRA/BALANCIM)', 'servico', [['grua','cremalheira','balancim']], []],
    ];
    $svs = $pdo->query("SELECT id, nome FROM servico ORDER BY id")->fetchAll();
    $ins = $pdo->prepare("INSERT INTO neg_grupo (nome, categoria, ordem, servicos, updated_at) VALUES (?,?,?,?,?)");
    $o = 0;
    foreach ($SEED as [$nome, $cat, $incAll, $exc]) {
        $ids = [];
        foreach ($svs as $s) {
            $n = t20_norm($s['nome']);
            $ok = true;
            foreach ($incAll as $orlist) { $hit = false; foreach ($orlist as $t) if (t20_match($n, $t)) { $hit = true; break; } if (!$hit) { $ok = false; break; } }
            if ($ok) foreach ($exc as $t) if (t20_match($n, $t)) { $ok = false; break; }
            if ($ok) $ids[] = (int)$s['id'];
        }
        $ins->execute([$nome, $cat, ++$o, json_encode($ids), date('c')]);
    }
}

function t20_mes_add($ym, $n) { $y=(int)substr($ym,0,4); $m=(int)substr($ym,5,2)+$n; $y+=intdiv($m-1,12); $m=($m-1)%12+1; return sprintf('%04d-%02d',$y,$m); }
function t20_mes_diff($a, $b) { return ((int)substr($b,0,4)-(int)substr($a,0,4))*12 + ((int)substr($b,5,2)-(int)substr($a,5,2)); }

/** Janela consolidada de um conjunto de tarefas: [minStart, maxFinish, pct ponderado pela duração].
 *  Tarefa DATADA sem percent_complete pesa como 0% (conservador: mantém a demanda viva — nunca somir volume
 *  porque só uma das torres reporta %); pct só é null quando NENHUMA ocorrência reporta %. */
function t20_span($tks) {
    $mi = null; $mf = null; $wsum = 0.0; $psum = 0.0; $wnull = 0.0; $temPct = false;
    foreach ($tks as $tk) {
        $s = $tk['start'] ?? null; $f = $tk['finish'] ?? null;
        if ($s && (!$mi || $s < $mi)) $mi = $s;
        if ($f && (!$mf || $f > $mf)) $mf = $f;
        if (!$s && !$f) continue;
        $w = ($s && $f && $f > $s) ? max(1.0, (strtotime($f) - strtotime($s)) / 86400.0) : 1.0;
        if (isset($tk['percent_complete']) && $tk['percent_complete'] !== null && $tk['percent_complete'] !== '') {
            $wsum += $w; $psum += $w * (float)$tk['percent_complete']; $temPct = true;
        } else $wnull += $w;
    }
    return [$mi, $mf, ($temPct && ($wsum + $wnull) > 0) ? $psum / ($wsum + $wnull) : null];
}

/** Todas as tarefas que casam qualquer termo do De-Para (com fronteira de palavra — 'forma' NÃO pega "plataforma"). */
function t20_termo_set($termos, $tasks) {
    $set = [];
    foreach ($tasks as $tk) { $n = $tk['_n'] ?? ''; foreach ($termos as $t) if (t20_match($n, $t)) { $set[] = $tk; break; } }
    return $set;
}

/** Resolve a JANELA do item (nunca só data inicial — ver doc no topo). Retorna [start, finish, pct, fonte]. */
function t20_janela($marco, $marcoWbs, $termos, $byName, $byWbs, $tasks) {
    $tset = null;   // termo-set lazy (usado nas camadas 1 e 3)
    $termoSet = function() use (&$tset, $termos, $tasks) { if ($tset === null) $tset = $termos ? t20_termo_set($termos, $tasks) : []; return $tset; };
    // 1) todas as ocorrências da tarefa-âncora (mesmo nome repete por torre/pavimento)
    $mk = _norm_txt($marco);
    $A = $byName[$mk] ?? [];
    [$mi, $mf, $pct] = t20_span($A);
    if ($mi && $mf && t20_mes_diff(substr($mi,0,7), substr($mf,0,7)) >= 1) {
        // âncora de ocorrência ÚNICA não representa o item inteiro (ex.: "… TORRE 03" 100% concluída com as
        // outras torres em obra) → o % de consumo vem do conjunto dos TERMOS (cobre todas as torres)
        if (count($A) === 1 && $pct !== null) { $ts = $termoSet(); if (count($ts) > 1) { [, , $tp] = t20_span($ts); if ($tp !== null) $pct = $tp; } }
        return [$mi, $mf, $pct, 'marco'];
    }
    // 2) fase: resumo ancestral do marco na WBS (nível >= 2 — nunca a raiz da obra), unificado entre torres
    if ($marcoWbs !== null && $marcoWbs !== '') {
        $parts = explode('.', (string)$marcoWbs);
        for ($i = count($parts) - 1; $i >= 1; $i--) {           // do pai mais próximo ao topo
            $anc = $byWbs[implode('.', array_slice($parts, 0, $i))] ?? null;
            if (!$anc || (int)($anc['outline_level'] ?? 99) < 2) continue;
            $set = $byName[$anc['_n'] ?? _norm_txt($anc['nome'] ?? '')] ?? [$anc];
            [$ai, $af, $ap] = t20_span($set);
            if ($ai && $af && t20_mes_diff(substr($ai,0,7), substr($af,0,7)) >= 1) return [$ai, $af, $ap, 'fase: ' . $anc['nome']];
        }
    }
    // 3) termos do De-Para: todas as tarefas que casam qualquer termo (fronteira de palavra)
    $set = $termoSet();
    if ($set) { [$ti, $tf, $tp] = t20_span($set);
        if ($ti && $tf && t20_mes_diff(substr($ti,0,7), substr($tf,0,7)) >= 1) return [$ti, $tf, $tp, 'termos do de-para']; }
    // 4) marco com fim (janela curta legítima — só depois de tentar alargar)
    if ($mi && $mf) return [$mi, $mf, $pct, 'marco'];
    // 5) estimada: 6 meses a partir do início (aritmética de MÊS — dia 31 não pode rolar a janela p/ 7 meses)
    if ($mi) return [$mi, t20_mes_add(substr($mi, 0, 7), 5) . '-28', $pct, 'estimada (6 meses)'];
    return [null, null, null, 'sem data'];
}

/** Distribui o item nos meses DESCONTANDO o consumido. Retorna [alocMap (mes => fração do TOTAL), consumidoFrac, pctFonte]. */
function t20_aloca($mi, $mf, $pct, $mesAtual, $horizonte) {
    if (!$mi) return [['sem' => 1.0], 0.0, null];
    $a = substr($mi, 0, 7); $b = $mf ? substr($mf, 0, 7) : $a; if ($b < $a) $b = $a;
    $nm = t20_mes_diff($a, $b) + 1;
    if ($pct !== null)          $consumido = min(1.0, max(0.0, (float)$pct / 100.0));   // % real do cronograma vivo
    elseif ($b < $mesAtual)     $consumido = 0.0;   // janela passada SEM % → não dá p/ saber; radar diz não comprado → demanda toda AGORA
    else                        $consumido = max(0, min($nm, t20_mes_diff($a, $mesAtual))) / $nm;  // estimado pelo tempo decorrido
    $pctFonte = $pct !== null ? 'cronograma' : ($consumido > 0 ? 'tempo' : null);
    $rem = 1.0 - $consumido;
    if ($rem <= 0.0001) return [[], $consumido, $pctFonte];
    if ($b < $mesAtual) return [[$mesAtual => $rem], $consumido, $pctFonte];   // sobra de janela passada = demanda AGORA
    $ini = ($a > $mesAtual) ? $a : $mesAtual;                                  // restante distribui do mês atual até o fim
    $k = t20_mes_diff($ini, $b) + 1;
    $out = [];
    for ($i = 0; $i < $k; $i++) { $m = t20_mes_add($ini, $i); if ($m > $horizonte) $m = '12+'; $out[$m] = ($out[$m] ?? 0) + $rem / $k; }
    return [$out, $consumido, $pctFonte];
}

try {
    $pdo = db();
    // tabela + coluna categoria (self-heal — o db.php também cria; produção pode ter a tabela sem a coluna)
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS neg_grupo (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, ordem INTEGER DEFAULT 0, categoria TEXT DEFAULT 'material', servicos TEXT, nota TEXT, updated_by TEXT, updated_at TEXT)"); } catch (Throwable $e) {}
    try { $pdo->query("SELECT categoria FROM neg_grupo LIMIT 1"); }
    catch (Throwable $e) {
        try {
            $pdo->exec("ALTER TABLE neg_grupo ADD COLUMN categoria VARCHAR(20) DEFAULT 'material'");
            // backfill dos padrões já semeados sem a coluna
            $srv = ['MÃO DE OBRA — ALVENARIA','MÃO DE OBRA — ESTRUTURA (CONCRETO)','FORMAS','ESCORAMENTO',
                    'EQUIPAMENTOS DE OBRA (GRUA/CREMALHEIRA/BALANCIM)','IMPERMEABILIZAÇÃO','CONTRAPISO E REGULARIZAÇÃO','GESSO E FORROS'];
            $up = $pdo->prepare("UPDATE neg_grupo SET categoria='servico' WHERE nome=?");
            foreach ($srv as $nm) $up->execute([$nm]);
            $pdo->exec("UPDATE neg_grupo SET categoria='material' WHERE categoria IS NULL OR categoria=''");
        } catch (Throwable $e2) {}
    }

    // ---------- POST: gestão dos grupos (ADMIN) ----------
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $perms = user_perms($pdo, $in['me'] ?? null);
        if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Apenas administradores configuram os grupos.']); exit; }
        $acao = $in['acao'] ?? '';
        if ($acao === 'salvar_grupo') {
            $nome = trim((string)($in['nome'] ?? '')); if ($nome === '') throw new Exception('nome obrigatório');
            $ids = array_values(array_unique(array_map('intval', (array)($in['servicos'] ?? []))));
            // campo ausente = PRESERVA a categoria atual (front antigo em cache não pode mover grupo de aba)
            $cat = array_key_exists('categoria', $in) ? (($in['categoria'] === 'servico') ? 'servico' : 'material') : null;
            $ordem = (int)($in['ordem'] ?? 0);
            $id = (int)($in['id'] ?? 0);
            if ($id) $pdo->prepare("UPDATE neg_grupo SET nome=?, servicos=?, categoria=COALESCE(?, categoria), ordem=?, updated_by=?, updated_at=? WHERE id=?")
                          ->execute([$nome, json_encode($ids), $cat, $ordem, (string)($in['me'] ?? ''), date('c'), $id]);
            else { $pdo->prepare("INSERT INTO neg_grupo (nome, categoria, ordem, servicos, updated_by, updated_at) VALUES (?,?,?,?,?,?)")
                        ->execute([$nome, $cat ?? 'material', $ordem, json_encode($ids), (string)($in['me'] ?? ''), date('c')]); $id = (int)$pdo->lastInsertId(); }
            echo json_encode(['ok'=>true, 'id'=>$id]); exit;
        }
        if ($acao === 'excluir_grupo') {
            $pdo->prepare("DELETE FROM neg_grupo WHERE id=?")->execute([(int)($in['id'] ?? 0)]);
            echo json_encode(['ok'=>true]); exit;
        }
        if ($acao === 'reseed') {   // recomeça do padrão (apaga e re-semeia)
            $pdo->exec("DELETE FROM neg_grupo"); t20_seed($pdo);
            echo json_encode(['ok'=>true]); exit;
        }
        throw new Exception('ação inválida');
    }

    // ---------- GET ?catalogo=1 : serviços p/ o config ----------
    if (isset($_GET['catalogo'])) {
        $rows = $pdo->query("SELECT id, nome, grupo FROM servico ORDER BY grupo_ordem, id")->fetchAll();
        echo json_encode(['servicos'=>$rows], JSON_UNESCAPED_UNICODE); exit;
    }

    // semeia os grupos-padrão na primeira vez
    if (!(int)$pdo->query("SELECT COUNT(*) FROM neg_grupo")->fetchColumn()) t20_seed($pdo);

    $grupos = $pdo->query("SELECT * FROM neg_grupo ORDER BY ordem, id")->fetchAll();
    foreach ($grupos as &$g) { $g['servicos'] = json_decode($g['servicos'] ?: '[]', true) ?: []; }
    unset($g);

    $incluirFin = !empty($_GET['fin']);
    $mesAtual = date('Y-m');
    $horizonte = t20_mes_add($mesAtual, 11);
    $meses = []; for ($k = 0; $k < 12; $k++) $meses[] = t20_mes_add($mesAtual, $k);

    // serviço -> grupos que o contêm
    $svGrupo = [];
    foreach ($grupos as $g) foreach ($g['servicos'] as $sid) $svGrupo[(int)$sid][] = (int)$g['id'];

    $DET_G = isset($_GET['detalhe']) ? (int)$_GET['detalhe'] : 0;
    $DET_M = isset($_GET['mes']) ? (string)$_GET['mes'] : '';

    // ---------- varre TODAS as obras do radar ----------
    $obras = $pdo->query("SELECT id, nome, cronograma_id FROM obra ORDER BY id")->fetchAll();
    $matriz = []; $detItens = [];
    foreach ($obras as $ob) {
        $oid = (int)$ob['id'];
        $tasks = [];
        if (!empty($ob['cronograma_id'])) { try { $tasks = crono_tasks($ob['cronograma_id']); } catch (Throwable $e) { $tasks = []; } }
        // índices: nome normalizado -> TODAS as ocorrências (torres/pavtos); wbs -> tarefa (p/ subir na árvore)
        $byName = []; $byWbs = [];
        foreach ($tasks as $tk) {
            $k = $tk['_n'] ?? _norm_txt($tk['nome'] ?? '');
            if ($k !== '') $byName[$k][] = $tk;
            if (isset($tk['wbs']) && $tk['wbs'] !== '') $byWbs[(string)$tk['wbs']] = $tk;
        }

        $q = $pdo->prepare("SELECT s.id sid, COALESCE(NULLIF(r.nome_override,''), s.nome) nome, s.termos_cronograma, s.lead_dias,
                                   r.status, r.quantitativo_valor, r.quantitativo_unidade,
                                   r.verba_override, r.verba_estim, r.crono_marco_override, r.data_necessaria_override
                            FROM servico s JOIN radar_item r ON r.servico_id=s.id AND r.obra_id=?");
        $q->execute([$oid]);
        foreach ($q->fetchAll() as $r) {
            $sid = (int)$r['sid'];
            if (empty($svGrupo[$sid])) continue;
            $st = (string)($r['status'] ?? '');
            if (!$incluirFin && ($st === 'Finalizado' || $st === 'Não se aplica')) continue;

            // termos do De-Para (p/ validar a âncora e p/ as camadas 1/3 da janela)
            $termos = [];
            foreach (preg_split('/[;,\/]/', (string)$r['termos_cronograma']) as $t) { $t = trim(_norm_txt($t)); if (strlen($t) >= 4) $termos[] = $t; }

            // âncora: override curado (confiável) > auto (crono_resolver)
            $marco = trim((string)($r['crono_marco_override'] ?? '')); $marcoWbs = null;
            if ($marco === '' && $tasks) {
                $auto = crono_resolver($r, $tasks); $marco = (string)($auto['marco_casado'] ?? ''); $marcoWbs = $auto['marco_wbs'] ?? null;
                // valida a âncora AUTOMÁTICA com fronteira de palavra: o crono_resolver casa por substring e
                // 'estrutura' acha "INFRAESTRUTURA" (fase errada, % errado) → descarta e deixa os termos resolverem
                if ($marco !== '' && $termos) {
                    $mn = _norm_txt($marco); $okAnc = false;
                    foreach ($termos as $t) if (t20_match($mn, $t)) { $okAnc = true; break; }
                    if (!$okAnc) { $marco = ''; $marcoWbs = null; }
                }
            }
            elseif ($marco !== '' && $tasks) { $marcoWbs = crono_wbs_por_nome($marco, $tasks); }

            [$mi, $mf, $pct, $fonte] = (($marco !== '' || $termos) && $tasks)
                ? t20_janela($marco, $marcoWbs, $termos, $byName, $byWbs, $tasks)
                : [null, null, null, 'sem data'];
            if (!empty($r['data_necessaria_override'])) {
                $mi = $r['data_necessaria_override'];
                if ($mf && $mf < $mi) { $mf = null; $pct = null; }   // janela resolvida incompatível com a data curada → o % dela também não vale
                if ($fonte === 'sem data') $fonte = 'data curada';
            }

            [$aloc, $consumido, $pctFonte] = t20_aloca($mi, $mf, $pct, $mesAtual, $horizonte);
            $janela = $mi ? (substr($mi,0,7) . ($mf && substr($mf,0,7) !== substr($mi,0,7) ? ' → ' . substr($mf,0,7) : '')) : 'sem data';

            $verba = ($r['verba_override'] !== null && $r['verba_override'] !== '') ? (float)$r['verba_override'] : (float)$r['verba_estim'];
            $qv = $r['quantitativo_valor'] !== null ? (float)$r['quantitativo_valor'] : null;
            $qu = t20_norm(trim((string)($r['quantitativo_unidade'] ?? '')));
            if ($qu === 'kg') { if ($qv !== null) $qv /= 1000.0; $qu = 't'; }   // consolida aço em toneladas
            if ($qu === '' && $qv !== null) $qu = '?';

            foreach ($svGrupo[$sid] as $gid) {
                foreach ($aloc as $mk => $fr) {
                    if (!isset($matriz[$gid][$mk])) $matriz[$gid][$mk] = ['verba'=>0.0, 'quant'=>[]];
                    $matriz[$gid][$mk]['verba'] += $verba * $fr;
                    if ($qv !== null) $matriz[$gid][$mk]['quant'][$qu] = ($matriz[$gid][$mk]['quant'][$qu] ?? 0) + $qv * $fr;
                    if ($DET_G === $gid && ($DET_M === '' || $DET_M === $mk)) {
                        $detItens[] = ['obra'=>$ob['nome'], 'item'=>$r['nome'], 'status'=>$st,
                            'quant_total'=>$qv !== null ? round($qv, 2) : null, 'unidade'=>$qu ?: null,
                            'verba_total'=>round($verba, 2), 'janela'=>$janela, 'fonte'=>$fonte, 'mes'=>$mk,
                            'consumido'=>round($consumido * 100), 'pct_fonte'=>$pctFonte,
                            'alocado_quant'=>$qv !== null ? round($qv * $fr, 2) : null, 'alocado_verba'=>round($verba * $fr, 2),
                            'fracao'=>round($fr, 4)];
                    }
                }
            }
        }
    }

    if ($DET_G) {   // drill da célula: a CONTA
        usort($detItens, fn($a,$b) => ($b['alocado_verba'] <=> $a['alocado_verba']));
        echo json_encode(['detalhe'=>$detItens, 'grupo'=>$DET_G, 'mes'=>$DET_M ?: 'todos'], JSON_UNESCAPED_UNICODE); exit;
    }

    // arredonda p/ resposta
    foreach ($matriz as &$gm) foreach ($gm as &$cel) { $cel['verba'] = round($cel['verba'], 2); foreach ($cel['quant'] as &$v) $v = round($v, 2); unset($v); }
    unset($gm, $cel);

    echo json_encode([
        'grupos' => array_map(fn($g) => ['id'=>(int)$g['id'], 'nome'=>$g['nome'], 'ordem'=>(int)$g['ordem'],
                                          'categoria'=>($g['categoria'] ?? '') === 'servico' ? 'servico' : 'material',
                                          'n_servicos'=>count($g['servicos']), 'servicos'=>$g['servicos']], $grupos),
        'meses' => $meses, 'mes_atual' => $mesAtual, 'incluir_finalizados' => $incluirFin,
        'matriz' => $matriz,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
