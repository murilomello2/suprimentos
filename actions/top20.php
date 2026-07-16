<?php
/**
 * TOP 20 — grupos de NEGOCIAÇÃO consolidados (aço, concreto, blocos, argamassas…) × PRÓXIMOS 12 MESES.
 * Objetivo: chegar no fornecedor com o VOLUME Caprem inteiro na mão (todas as obras do radar).
 *
 * Como o mês é calculado: cada item do radar tem quantitativo total + tarefa-âncora no CRONOGRAMA VIVO.
 * O quantitativo (e a verba) é distribuído LINEARMENTE pelos meses da janela [início→fim] da âncora.
 * Sem fim = tudo no mês do início. Janela no passado (e item não finalizado) = cai no MÊS ATUAL (demanda já).
 * Sem data nenhuma = coluna "sem data". Início além do horizonte = coluna "12+".
 * Itens 'Finalizado' e 'Não se aplica' ficam FORA por padrão (negocia-se o que ainda não foi comprado); ?fin=1 inclui.
 * Unidades NUNCA se somam entre si: consolida POR UNIDADE (kg vira t na exibição).
 *
 * GET                         -> {grupos, meses, matriz, cat_n} (semeia os grupos padrão se a tabela estiver vazia)
 * GET ?detalhe=<gid>&mes=<..> -> {itens:[{obra,item,quant,unidade,verba,janela,alocado}]} (a CONTA da célula)
 * GET ?catalogo=1             -> {servicos:[{id,nome,grupo}]} (p/ o config escolher o que entra)
 * POST (ADMIN) {acao:'salvar_grupo', id?, nome, servicos[], ordem} | {acao:'excluir_grupo', id} | {acao:'reseed'}
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

// grupos-padrão: [nome, INCLUDE = lista de listas (AND de ORs), EXCLUDE]
function t20_seed($pdo) {
    $SEED = [
        ['AÇO (CA-50/CA-60 + TELAS)', [['aco=','ca-50','ca-60','vergalh','tela de aco','telas de aco']], ['telha']],
        ['CONCRETO USINADO', [['concreto']], ['bloco','alvenaria','projetado','mo=','m.o','mao de obra','controle']],
        ['BLOCOS DE CONCRETO (ESTRUTURAL + VEDAÇÃO)', [['bloco']], ['desmold']],
        ['ARGAMASSAS E GRAUTE', [['argamassa','graute','grout']], []],
        ['ESQUADRIAS DE ALUMÍNIO', [['esquadria','janela','contra marco','contramarco','envidracamento','brise']], []],
        ['MÃO DE OBRA — ALVENARIA', [['alvenaria'], ['mo=','m.o','mao de obra','empreitada']], ['argamassa','bloco de']],
        ['MÃO DE OBRA — ESTRUTURA (CONCRETO)', [['estrutura'], ['mo=','m.o','mao de obra']], ['alvenaria']],
        ['FORMAS', [['forma']], ['plataforma','informa']],
        ['ESCORAMENTO', [['escoramento']], []],
        ['ELEVADORES DEFINITIVOS', [['elevador']], ['cremalheira','de obra']],
        ['PISOS E PORCELANATOS', [['porcelanato','pisos e revestimentos','piso intertravado','revestimento ceramic']], []],
        ['LOUÇAS E METAIS', [['louca','metais','bacia','cuba','lavatorio']], []],
        ['PINTURA (TINTAS/TEXTURA)', [['pintura','tinta','textura','selador']], []],
        ['GESSO E FORROS', [['gesso','drywall','forro']], []],
        ['IMPERMEABILIZAÇÃO', [['impermeabiliza']], []],
        ['COBERTURA (TELHAS/CALHAS)', [['cobertura','telha','calha','rufo']], []],
        ['FIOS E CABOS', [['fios e cabos','cabo isolado','fios']], []],
        ['MATERIAIS HIDRÁULICOS (TUBOS/CONEXÕES)', [['hidraulico','tubos e conexoes','pex','cpvc']], []],
        ['CONTRAPISO E REGULARIZAÇÃO', [['contrapiso','contra piso','regularizacao']], []],
        ['EQUIPAMENTOS DE OBRA (GRUA/CREMALHEIRA/BALANCIM)', [['grua','cremalheira','balancim']], []],
    ];
    $svs = $pdo->query("SELECT id, nome FROM servico ORDER BY id")->fetchAll();
    $ins = $pdo->prepare("INSERT INTO neg_grupo (nome, ordem, servicos, updated_at) VALUES (?,?,?,?)");
    $o = 0;
    foreach ($SEED as [$nome, $incAll, $exc]) {
        $ids = [];
        foreach ($svs as $s) {
            $n = t20_norm($s['nome']);
            $ok = true;
            foreach ($incAll as $orlist) { $hit = false; foreach ($orlist as $t) if (t20_match($n, $t)) { $hit = true; break; } if (!$hit) { $ok = false; break; } }
            if ($ok) foreach ($exc as $t) if (t20_match($n, $t)) { $ok = false; break; }
            if ($ok) $ids[] = (int)$s['id'];
        }
        $ins->execute([$nome, ++$o, json_encode($ids), date('c')]);
    }
}

function t20_mes_add($ym, $n) { $y=(int)substr($ym,0,4); $m=(int)substr($ym,5,2)+$n; $y+=intdiv($m-1,12); $m=($m-1)%12+1; return sprintf('%04d-%02d',$y,$m); }
function t20_mes_diff($a, $b) { return ((int)substr($b,0,4)-(int)substr($a,0,4))*12 + ((int)substr($b,5,2)-(int)substr($a,5,2)); }

/** Distribui um item nos meses. Retorna [ [mesKey => fração], janela_str ]. mesKey: 'YYYY-MM' | '12+' | 'sem' */
function t20_aloca($start, $finish, $mesAtual, $horizonte) {
    if (!$start) return [['sem'=>1.0], 'sem data'];
    $mi = substr($start, 0, 7); $mf = $finish ? substr($finish, 0, 7) : $mi;
    if ($mf < $mi) $mf = $mi;
    $jan = ($mi === $mf) ? $mi : "$mi → $mf";
    $nm = t20_mes_diff($mi, $mf) + 1;
    $out = [];
    for ($k = 0; $k < $nm; $k++) {
        $m = t20_mes_add($mi, $k);
        if ($m < $mesAtual) $m = $mesAtual;              // passado (não finalizado) = demanda AGORA
        elseif ($m > $horizonte) $m = '12+';
        $out[$m] = ($out[$m] ?? 0) + 1.0 / $nm;
    }
    return [$out, $jan];
}

try {
    $pdo = db();
    // tabela (self-heal local — o db.php também cria)
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS neg_grupo (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, ordem INTEGER DEFAULT 0, servicos TEXT, nota TEXT, updated_by TEXT, updated_at TEXT)"); } catch (Throwable $e) {}

    // ---------- POST: gestão dos grupos (ADMIN) ----------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $perms = user_perms($pdo, $in['me'] ?? null);
        if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Apenas administradores configuram os grupos.']); exit; }
        $acao = $in['acao'] ?? '';
        if ($acao === 'salvar_grupo') {
            $nome = trim((string)($in['nome'] ?? '')); if ($nome === '') throw new Exception('nome obrigatório');
            $ids = array_values(array_unique(array_map('intval', (array)($in['servicos'] ?? []))));
            $ordem = (int)($in['ordem'] ?? 0);
            $id = (int)($in['id'] ?? 0);
            if ($id) $pdo->prepare("UPDATE neg_grupo SET nome=?, servicos=?, ordem=?, updated_by=?, updated_at=? WHERE id=?")
                          ->execute([$nome, json_encode($ids), $ordem, (string)($in['me'] ?? ''), date('c'), $id]);
            else { $pdo->prepare("INSERT INTO neg_grupo (nome, ordem, servicos, updated_by, updated_at) VALUES (?,?,?,?,?)")
                        ->execute([$nome, $ordem, json_encode($ids), (string)($in['me'] ?? ''), date('c')]); $id = (int)$pdo->lastInsertId(); }
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
        // índice nome-normalizado -> [start, finish] (1ª ocorrência)
        $tIdx = [];
        foreach ($tasks as $tk) { $k = $tk['_n'] ?? t20_norm($tk['nome'] ?? ''); if ($k !== '' && !isset($tIdx[$k])) $tIdx[$k] = [$tk['start'] ?? null, $tk['finish'] ?? null]; }

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

            // janela do marco: override > auto (crono_resolver) — pega start+finish da tarefa pelo nome
            $start = $r['data_necessaria_override'] ?: null; $finish = null; $marco = trim((string)($r['crono_marco_override'] ?? ''));
            if ($marco === '' && $tasks) { $auto = crono_resolver($r, $tasks); $marco = (string)($auto['marco_casado'] ?? ''); $start = $start ?: ($auto['data_necessaria'] ?? null); }
            if ($marco !== '' && isset($tIdx[t20_norm($marco)])) { [$ts, $tf] = $tIdx[t20_norm($marco)]; $start = $start ?: $ts; $finish = $tf; }

            [$aloc, $janela] = t20_aloca($start, $finish, $mesAtual, $horizonte);

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
                            'verba_total'=>round($verba, 2), 'janela'=>$janela, 'mes'=>$mk,
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
        'grupos' => array_map(fn($g) => ['id'=>(int)$g['id'], 'nome'=>$g['nome'], 'ordem'=>(int)$g['ordem'], 'n_servicos'=>count($g['servicos']), 'servicos'=>$g['servicos']], $grupos),
        'meses' => $meses, 'mes_atual' => $mesAtual, 'incluir_finalizados' => $incluirFin,
        'matriz' => $matriz,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
