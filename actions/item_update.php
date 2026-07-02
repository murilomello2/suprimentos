<?php
/**
 * Atualiza/cura um item do radar.
 * POST JSON: { "ordem": <int>, "campos": { ... }, "me": <bitrix_id do usuário logado> }
 *
 * ENFORCEMENT: só grava quem tem escopo de edição na obra (admin ou editar_escopo libera).
 * HISTÓRICO: toda alteração vira linha em `historico` (quem/quando/item/campo/antes→depois).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

$SIMPLES = ['status','fornecedor','responsavel','observacoes','validado','tipo',
            'lead_override','crono_marco_override','data_necessaria_override','verba_override',
            'quantitativo_valor','quantitativo_unidade','quantitativo_fonte'];
$TIPOS_OK = ['', 'Material', 'Mão de obra', 'Empreitada', 'Material + MO', 'Locação'];
$STATUS_OK = ['Não Iniciado','Cotação Iniciada','Com Pendências','Em Andamento','Finalizado'];
$LABEL = ['status'=>'Status','tipo'=>'Tipo','responsavel'=>'Responsável','fornecedor'=>'Fornecedor',
          'observacoes'=>'Observações','lead_override'=>'Lead time','validado'=>'Validado',
          'crono_marco_override'=>'Cronograma','data_necessaria_override'=>'Data em obra',
          'verba_override'=>'Verba (manual)','quantitativo_valor'=>'Quantitativo'];

function nullable($v){ return ($v === '' || $v === null) ? null : $v; }

try {
    $in = json_decode(file_get_contents('php://input'), true);
    $ordem  = isset($in['ordem']) ? (int)$in['ordem'] : 0;
    $campos = $in['campos'] ?? [];
    $me     = $in['me'] ?? null;
    if (!$ordem || !is_array($campos) || !$campos) throw new Exception('payload inválido (precisa de ordem + campos)');

    $pdo = db();

    // ---- PERMISSÃO (servidor): enforcement POR CAMPO (inline; resiliente a deploy parcial) ----
    // editor geral (editar_escopo) altera status/fornecedor/observação; vínculos e dicionário exigem
    // permissão específica; grupo/tipo/nome/responsável/lead = só admin. Admin faz tudo.
    // Lógica INLINE (não depende de funções novas do db.php) — usa só user_perms + can_edit_obra (antigas);
    // permissões ausentes no $perms (db.php desatualizado) caem em "negado" (fail-safe), nunca em 500.
    $perms    = user_perms($pdo, $me);
    // resiliência a deploy parcial: se o db.php online ainda for antigo, user_perms não traz as perms
    // granulares — busca direto na tabela (colunas garantidas pelo self-heal do usuarios.php).
    if ($me !== null && $me !== '' && !array_key_exists('perm_orcamento', $perms)) {
        try {
            $pg = $pdo->prepare("SELECT perm_crono,perm_orcamento,perm_quant,perm_dicionario FROM usuario WHERE TRIM(bitrix_id)=? AND ativo=1");
            $pg->execute([trim((string)$me)]);
            if ($row = $pg->fetch()) $perms = $perms + $row;   // adiciona as perm_* que faltavam
        } catch (Throwable $e) {}
    }
    $is_admin = !empty($perms['perm_admin']);
    $editor   = $is_admin || can_edit_obra($perms, 1);
    $FG = [
        'status'=>'geral','fornecedor'=>'geral','observacoes'=>'geral',
        'crono_marco_override'=>'crono','data_necessaria_override'=>'crono',
        'orcamento_refs'=>'orcamento','orcamento_excl'=>'orcamento','composicao_sel'=>'orcamento','composicao'=>'orcamento','verba_override'=>'orcamento',
        'quant_refs'=>'quant','quant_comp_sel'=>'quant','quantitativo_valor'=>'quant','quantitativo_unidade'=>'quant','quantitativo_fonte'=>'quant',
        'dicionario'=>'dicionario',
    ];                                   // chave ausente => 'admin'
    $PERM_FLAG   = ['crono'=>'perm_crono','orcamento'=>'perm_orcamento','quant'=>'perm_quant','dicionario'=>'perm_dicionario'];
    $GRUPO_LABEL = ['geral'=>'status/fornecedor/observação','crono'=>'vínculo de cronograma',
                    'orcamento'=>'vínculo de orçamento/verba','quant'=>'vínculo de quantitativo',
                    'dicionario'=>'dicionário','admin'=>'grupo/tipo/nome/responsável (só admin)'];
    $negados = [];
    foreach (array_keys($campos) as $k) {
        $g  = $FG[$k] ?? 'admin';
        $ok = $is_admin
            || ($g === 'geral' && $editor)
            || (isset($PERM_FLAG[$g]) && $editor && !empty($perms[$PERM_FLAG[$g]]));
        if (!$ok) $negados[$g] = true;
    }
    if ($negados) {
        http_response_code(403);
        $quais = implode(', ', array_map(function($g) use ($GRUPO_LABEL){ return $GRUPO_LABEL[$g] ?? $g; }, array_keys($negados)));
        echo json_encode(['error' => 'Sem permissão para alterar: '.$quais.'.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // snapshot ANTES (p/ histórico)
    $r0 = $pdo->prepare("SELECT * FROM radar_item WHERE obra_id=1 AND servico_id=?"); $r0->execute([$ordem]);
    $before = $r0->fetch() ?: [];
    $s0 = $pdo->prepare("SELECT nome, grupo FROM servico WHERE id=?"); $s0->execute([$ordem]);
    $beforeS = $s0->fetch() ?: ['nome'=>'','grupo'=>''];
    $item_nome = $beforeS['nome'] ?? '';
    $hist = [];                      // [ [campo, antes, depois], ... ]
    $h = function($campo,$antes,$depois) use (&$hist){ $hist[] = [$campo,$antes,$depois]; };

    $set = []; $vals = [];
    $pdo->beginTransaction();        // dado + histórico atômicos

    // ----- vínculo de verba por LINHAS do orçamento (analítico) -----
    if (array_key_exists('orcamento_refs', $campos)) {
        $refs = array_values(array_filter(array_map('intval', (array)$campos['orcamento_refs'])));
        // exclusões de insumos DENTRO das linhas (ex.: tirar o espaçador) — [{l:lineId, d:desc}]. null = não mexer.
        $exclIn = array_key_exists('orcamento_excl', $campos)
            ? (is_array($campos['orcamento_excl']) ? $campos['orcamento_excl'] : (json_decode((string)$campos['orcamento_excl'], true) ?: []))
            : null;
        $soma = 0; $inq = '';
        if ($refs) {
            $inq = implode(',', array_fill(0, count($refs), '?'));
            $st = $pdo->prepare("SELECT COALESCE(SUM(valor),0) s FROM orcamento_linha WHERE id IN ($inq)");
            $st->execute($refs); $soma = (float)$st->fetch()['s'];
        }
        // valor dos insumos excluídos (subtrai da verba). Ignora exclusões de linhas não selecionadas.
        $exclClean = []; $excl_val = 0.0;
        if ($refs && is_array($exclIn) && $exclIn) {
            $refsSet = array_flip($refs); $lmeta = [];
            $lm = $pdo->prepare("SELECT id, qtde, valor, descricao FROM orcamento_linha WHERE id IN ($inq)"); $lm->execute($refs);
            foreach ($lm->fetchAll() as $r) $lmeta[(int)$r['id']] = $r;
            $descs = array_values(array_unique(array_map(function($r){ return $r['descricao']; }, $lmeta)));
            $cidByDesc = [];
            if ($descs) {
                $ph = implode(',', array_fill(0, count($descs), '?'));
                $q = $pdo->prepare("SELECT id, descricao FROM composicao WHERE descricao IN ($ph)"); $q->execute($descs);
                foreach ($q->fetchAll() as $c) $cidByDesc[$c['descricao']] = (int)$c['id'];
            }
            $insUnit = []; $cids = array_values(array_unique(array_values($cidByDesc)));
            if ($cids) {
                $inC = implode(',', array_fill(0, count($cids), '?'));
                $qi = $pdo->prepare("SELECT composicao_id, descricao, coef, rs_unit FROM composicao_insumo WHERE composicao_id IN ($inC)"); $qi->execute($cids);
                foreach ($qi->fetchAll() as $r) $insUnit[$r['composicao_id'].'|'.$r['descricao']] = (float)$r['coef'] * (float)$r['rs_unit'];
            }
            foreach ($exclIn as $e) {
                $l = (int)($e['l'] ?? 0); $d = (string)($e['d'] ?? '');
                if (!$l || $d === '' || !isset($refsSet[$l]) || !isset($lmeta[$l])) continue;
                $exclClean[] = ['l'=>$l, 'd'=>$d];
                $cid = $cidByDesc[$lmeta[$l]['descricao']] ?? 0;
                $u = $insUnit[$cid.'|'.$d] ?? null;
                if ($u !== null) $excl_val += (float)$lmeta[$l]['qtde'] * $u;
                elseif ($d === $lmeta[$l]['descricao']) $excl_val += (float)$lmeta[$l]['valor']; // linha direta excluída inteira
            }
        }
        $verba = $soma - $excl_val;
        $set[] = "orcamento_refs = ?"; $vals[] = $refs ? json_encode($refs) : null;
        // orcamento_excl só é tocado se veio no payload (null = mantém); ao limpar refs, zera.
        if ($exclIn !== null || !$refs) { $set[] = "orcamento_excl = ?"; $vals[] = ($refs && $exclClean) ? json_encode($exclClean, JSON_UNESCAPED_UNICODE) : null; }
        $set[] = "verba_override = ?"; $vals[] = $refs ? $verba : null;
        $set[] = "verba_metodo = ?";   $vals[] = $refs ? 'analitico' : null;
        $set[] = "verba_curada = ?";   $vals[] = $refs ? 1 : 0;
        if ($refs) {
            $sq = $pdo->prepare("SELECT COALESCE(SUM(qtde),0) s FROM orcamento_linha WHERE id IN ($inq)");
            $sq->execute($refs);
            $uq = $pdo->prepare("SELECT unidade, COUNT(*) c FROM orcamento_linha WHERE id IN ($inq) GROUP BY unidade ORDER BY c DESC LIMIT 1");
            $uq->execute($refs);
            $set[] = "quantitativo_valor = ?";   $vals[] = (float)$sq->fetch()['s'];
            $set[] = "quantitativo_unidade = ?"; $vals[] = ($uq->fetch()['unidade'] ?? null) ?: null;
            $set[] = "quantitativo_fonte = ?";   $vals[] = 'orcamento';
            $set[] = "quant_curada = ?";         $vals[] = 1;
        }
        $h('Verba (vínculo analítico)', '', $refs ? ('R$ '.number_format($verba,2,',','.').' · '.count($refs).' linhas'.($exclClean ? (' · −'.count($exclClean).' insumo') : '')) : 'limpo');
        unset($campos['orcamento_refs']); unset($campos['orcamento_excl']);
    }

    // ----- DICIONÁRIO (template por tipo de serviço) — atualiza servico -----
    if (array_key_exists('dicionario', $campos)) {
        $dic = (array)$campos['dicionario'];
        $allow = ['escopo','variaveis_cotar','licoes','documentos','marco_cronograma','quantitativo','forma_contratacao','unidade','responsavel_padrao'];
        $ds = []; $dv = [];
        foreach ($dic as $k => $v) { if (in_array($k, $allow, true)) { $ds[] = "$k = ?"; $dv[] = (string)$v; } }
        if ($ds) { $dv[] = $ordem; $pdo->prepare("UPDATE servico SET " . implode(', ', $ds) . " WHERE id = ?")->execute($dv); $h('Dicionário','','(atualizado)'); }
        unset($campos['dicionario']);
    }

    // ----- NOME do item — atualiza o catálogo (servico) -----
    if (array_key_exists('nome', $campos)) {
        $nm = trim((string)$campos['nome']);
        if ($nm !== '' && $nm !== $beforeS['nome']) {
            $pdo->prepare("UPDATE servico SET nome=?, slug=? WHERE id=?")->execute([$nm, _slugify($nm), $ordem]);
            $h('Nome do item', $beforeS['nome'], $nm); $item_nome = $nm;
        }
        unset($campos['nome']);
    }

    // ----- MOVER de GRUPO — atualiza o catálogo (servico) -----
    if (array_key_exists('grupo', $campos)) {
        $g = trim((string)$campos['grupo']);
        if ($g !== '' && $g !== $beforeS['grupo']) {
            $go = $pdo->prepare("SELECT grupo_ordem FROM servico WHERE grupo=? AND id<>? LIMIT 1");
            $go->execute([$g, $ordem]); $gord = $go->fetchColumn();
            if ($gord === false || $gord === null) $gord = (int)$pdo->query("SELECT COALESCE(MAX(grupo_ordem),0)+1 FROM servico")->fetchColumn();
            $pdo->prepare("UPDATE servico SET grupo=?, grupo_ordem=? WHERE id=?")->execute([$g, $gord, $ordem]);
            $h('Grupo', $beforeS['grupo'], $g);
        }
        unset($campos['grupo']);
    }

    // ----- vínculo de QUANTITATIVO por linhas do orçamento -----
    if (array_key_exists('quant_refs', $campos)) {
        $refs = array_values(array_filter(array_map('intval', (array)$campos['quant_refs'])));
        $soma = 0; $unid = null;
        if ($refs) {
            $inq = implode(',', array_fill(0, count($refs), '?'));
            $st = $pdo->prepare("SELECT COALESCE(SUM(qtde),0) s FROM orcamento_linha WHERE id IN ($inq)");
            $st->execute($refs); $soma = (float)$st->fetch()['s'];
            $u = $pdo->prepare("SELECT unidade, COUNT(*) c FROM orcamento_linha WHERE id IN ($inq) GROUP BY unidade ORDER BY c DESC LIMIT 1");
            $u->execute($refs); $unid = ($u->fetch()['unidade'] ?? null) ?: null;
        }
        $set[] = "quantitativo_refs = ?";    $vals[] = $refs ? json_encode($refs) : null;
        $set[] = "quantitativo_valor = ?";   $vals[] = $refs ? $soma : null;
        $set[] = "quantitativo_unidade = ?"; $vals[] = $refs ? $unid : null;
        $set[] = "quantitativo_fonte = ?";   $vals[] = $refs ? 'orcamento' : null;
        $set[] = "quant_curada = ?";         $vals[] = $refs ? 1 : 0;
        $h('Quantitativo (vínculo)', '', $refs ? (number_format($soma,2,',','.').' '.$unid) : 'limpo');
        unset($campos['quant_refs']);
    }

    // ----- QUANTITATIVO por COMPOSIÇÃO: cesta de insumos (soma área × coef) -----
    if (array_key_exists('quant_comp_sel', $campos)) {
        $sel = $campos['quant_comp_sel'];
        $qval = 0; $qun = ''; $clean = []; $cache = []; $cnames = [];
        if (is_array($sel)) foreach ($sel as $s) {
            $cid = (int)($s['cid'] ?? 0); $idx = (int)($s['idx'] ?? -1); $area = (float)($s['area'] ?? 0);
            if ($cid <= 0 || $idx < 0 || $area <= 0) continue;
            if (!isset($cache[$cid])) {
                $q = $pdo->prepare("SELECT descricao,unidade,coef,rs_unit,tipo FROM composicao_insumo WHERE composicao_id=? ORDER BY id");
                $q->execute([$cid]); $cache[$cid] = $q->fetchAll();
                $cn = $pdo->prepare("SELECT descricao FROM composicao WHERE id=?"); $cn->execute([$cid]); $cnames[$cid] = $cn->fetchColumn() ?: '';
            }
            if (!isset($cache[$cid][$idx])) continue;
            $ins = $cache[$cid][$idx];
            $qval += $area * (float)$ins['coef']; if ($qun === '') $qun = $ins['unidade'];
            $clean[] = ['cid'=>$cid,'idx'=>$idx,'area'=>$area,'desc'=>$ins['descricao'],'tipo'=>$ins['tipo'],
                        'unidade'=>$ins['unidade'],'coef'=>(float)$ins['coef'],'rs_unit'=>(float)$ins['rs_unit'],'compdesc'=>$cnames[$cid]];
        }
        if ($clean) {
            $set[]="quant_comp_sel = ?";       $vals[]=json_encode($clean, JSON_UNESCAPED_UNICODE);
            $set[]="quantitativo_valor = ?";   $vals[]=$qval;
            $set[]="quantitativo_unidade = ?"; $vals[]=$qun;
            $set[]="quantitativo_fonte = ?";   $vals[]='composicao';
            $set[]="quantitativo_refs = ?";    $vals[]=null;
            $set[]="quant_curada = ?";         $vals[]=1;
            $h('Quantitativo (composição)', '', number_format($qval,2,',','.').' '.$qun.' · '.count($clean).' insumo(s)');
        } else {
            $set[]="quant_comp_sel = ?";     $vals[]=null;
            $set[]="quantitativo_valor = ?"; $vals[]=null;
            $set[]="quantitativo_fonte = ?"; $vals[]=null;
            $set[]="quant_curada = ?";       $vals[]=0;
            $h('Quantitativo (composição)', '', 'limpo');
        }
        unset($campos['quant_comp_sel']);
    }

    // ----- VERBA por COMPOSIÇÃO: cesta de insumos selecionados (de 1+ composições) -----
    if (array_key_exists('composicao_sel', $campos)) {
        $sel = $campos['composicao_sel'];
        $vmat = 0; $vmo = 0; $qval = 0; $qun = ''; $clean = []; $cache = [];
        if (is_array($sel)) foreach ($sel as $s) {
            $cid = (int)($s['cid'] ?? 0); $idx = (int)($s['idx'] ?? -1); $area = (float)($s['area'] ?? 0);
            // LOCAIS: se vierem linhas do orçamento selecionadas, a ÁREA = soma das qtdes delas (recalcula no servidor)
            // e monta o DETALHE (por local de 1º nível) p/ mostrar no read-only da verba e do quantitativo.
            $locais = (isset($s['locais']) && is_array($s['locais'])) ? array_values(array_filter(array_map('intval', $s['locais']))) : null;
            $locais_det = null;
            if ($locais) {
                $inq = implode(',', array_fill(0, count($locais), '?'));
                $lq = $pdo->prepare("SELECT path_str, qtde, unidade FROM orcamento_linha WHERE id IN ($inq)");
                $lq->execute($locais);
                $area = 0.0; $grp = []; $lun = '';
                foreach ($lq->fetchAll() as $ln) {
                    $area += (float)$ln['qtde']; if ($lun === '') $lun = $ln['unidade'] ?? '';
                    $parts = array_map('trim', explode('›', (string)($ln['path_str'] ?? '')));
                    $loc = $parts[0] !== '' ? $parts[0] : '(local)';
                    $grp[$loc] = ($grp[$loc] ?? 0) + (float)$ln['qtde'];
                }
                $locais_det = [];
                foreach ($grp as $loc => $qq) $locais_det[] = ['local'=>$loc, 'qtde'=>$qq, 'unidade'=>$lun];
            }
            if ($cid <= 0 || $idx < 0 || $area <= 0) continue;
            if (!isset($cache[$cid])) {
                $q = $pdo->prepare("SELECT descricao,unidade,coef,rs_unit,tipo FROM composicao_insumo WHERE composicao_id=? ORDER BY id");
                $q->execute([$cid]); $cache[$cid] = $q->fetchAll();
            }
            if (!isset($cache[$cid][$idx])) continue;
            $ins = $cache[$cid][$idx];
            $custo = $area * (float)$ins['coef'] * (float)$ins['rs_unit'];
            if ($ins['tipo'] === 'mo') $vmo += $custo; else $vmat += $custo;
            if (!empty($s['q'])) { $qval += $area * (float)$ins['coef']; if ($qun === '') $qun = $ins['unidade']; }
            $clean[] = ['cid'=>$cid,'idx'=>$idx,'area'=>$area,'q'=>!empty($s['q']),
                        'desc'=>$ins['descricao'],'tipo'=>$ins['tipo'],'unidade'=>$ins['unidade'],
                        'coef'=>(float)$ins['coef'],'rs_unit'=>(float)$ins['rs_unit'],'locais'=>$locais,'locais_det'=>$locais_det];
        }
        if ($clean) {
            $verba = $vmat + $vmo;
            $set[]="verba_metodo = ?";    $vals[]='composicao';
            $set[]="composicao_sel = ?";  $vals[]=json_encode($clean, JSON_UNESCAPED_UNICODE);
            $set[]="composicao_id = ?";   $vals[]=null;
            $set[]="area_base = ?";       $vals[]=null;
            $set[]="verba_material = ?";  $vals[]=$vmat ?: null;
            $set[]="verba_mo = ?";        $vals[]=$vmo ?: null;
            $set[]="verba_override = ?";  $vals[]=$verba;
            $set[]="orcamento_refs = ?";  $vals[]=null;
            $set[]="verba_curada = ?";    $vals[]=1;
            if ($qval > 0) {
                $set[]="quantitativo_valor = ?";   $vals[]=$qval;
                $set[]="quantitativo_unidade = ?"; $vals[]=$qun;
                $set[]="quantitativo_fonte = ?";   $vals[]='composicao';
                $set[]="quantitativo_refs = ?";    $vals[]=null;
                $set[]="quant_curada = ?";         $vals[]=1;
            }
            $h('Verba (composição)', '', 'R$ '.number_format($verba,2,',','.').' · '.count($clean).' insumo(s)');
        } else {
            $set[]="composicao_sel = ?"; $vals[]=null;
            $set[]="verba_metodo = ?";   $vals[]=null;
            $set[]="verba_override = ?"; $vals[]=null;
            $set[]="verba_material = ?"; $vals[]=null;
            $set[]="verba_mo = ?";       $vals[]=null;
            $set[]="verba_curada = ?";   $vals[]=0;
            $h('Verba (composição)', '', 'limpo');
        }
        unset($campos['composicao_sel']);
    }

    // ----- VERBA/QUANTITATIVO por COMPOSIÇÃO (modelo antigo, 1 composição) -----
    if (array_key_exists('composicao', $campos)) {
        $cfg  = $campos['composicao'];
        $cid  = (int)($cfg['id'] ?? 0); $area = (float)($cfg['area'] ?? 0);
        $qidx = isset($cfg['quant_idx']) ? (int)$cfg['quant_idx'] : -1;
        $papel = $cfg['papel'] ?? 'ambos';
        if ($cid && $area > 0) {
            $ins = $pdo->prepare("SELECT descricao,unidade,coef,rs_unit,tipo FROM composicao_insumo WHERE composicao_id=? ORDER BY id");
            $ins->execute([$cid]); $rowsI = $ins->fetchAll();
            $vmat = 0; $vmo = 0;
            foreach ($rowsI as $row) { $custo = $area * (float)$row['coef'] * (float)$row['rs_unit']; if ($row['tipo'] === 'mo') $vmo += $custo; else $vmat += $custo; }
            $compRow = $pdo->prepare("SELECT unidade FROM composicao WHERE id=?"); $compRow->execute([$cid]); $compUn = $compRow->fetchColumn() ?: '';
            $verba = $papel==='material' ? $vmat : ($papel==='mo' ? $vmo : $vmat + $vmo);
            $set[]="verba_metodo = ?";   $vals[]='composicao';
            $set[]="composicao_id = ?";  $vals[]=$cid;
            $set[]="area_base = ?";      $vals[]=$area;
            $set[]="verba_material = ?"; $vals[]=$papel==='mo' ? null : $vmat;
            $set[]="verba_mo = ?";       $vals[]=$papel==='material' ? null : $vmo;
            $set[]="verba_override = ?"; $vals[]=$verba;
            $set[]="orcamento_refs = ?"; $vals[]=null;
            $set[]="verba_curada = ?";   $vals[]=1;
            $set[]="quant_curada = ?";   $vals[]=1;
            if ($papel === 'mo') {
                $set[]="quantitativo_valor = ?"; $vals[]=$area;
                $set[]="quantitativo_unidade = ?"; $vals[]=$compUn;
                $set[]="quantitativo_fonte = ?"; $vals[]='composicao';
                $set[]="quantitativo_refs = ?"; $vals[]=null;
            } else {
                $qsel = ($qidx >= 0 && isset($rowsI[$qidx])) ? $rowsI[$qidx] : null;
                if (!$qsel) foreach ($rowsI as $row) { if ($row['tipo']==='material'){ $qsel=$row; break; } }
                if ($qsel) {
                    $set[]="quantitativo_valor = ?"; $vals[]=$area * (float)$qsel['coef'];
                    $set[]="quantitativo_unidade = ?"; $vals[]=$qsel['unidade'];
                    $set[]="quantitativo_fonte = ?"; $vals[]='composicao';
                    $set[]="quantitativo_refs = ?"; $vals[]=null;
                }
            }
            $h('Verba (composição)', '', 'R$ '.number_format($verba,2,',','.'));
        }
        unset($campos['composicao']);
    }

    // ----- vínculo de CRONOGRAMA: SEMPRE registra o save (curadoria explícita), mesmo se o valor não mudou -----
    // (a verba e o quantitativo já logam sempre via seus branches; aqui igualamos o cronograma)
    if (array_key_exists('crono_marco_override', $campos) || array_key_exists('data_necessaria_override', $campos)) {
        $cm = array_key_exists('crono_marco_override', $campos)    ? nullable($campos['crono_marco_override'])    : ($before['crono_marco_override'] ?? null);
        $dn = array_key_exists('data_necessaria_override', $campos) ? nullable($campos['data_necessaria_override']) : ($before['data_necessaria_override'] ?? null);
        if (array_key_exists('crono_marco_override', $campos))     { $set[]="crono_marco_override = ?";    $vals[]=$cm; }
        if (array_key_exists('data_necessaria_override', $campos)) { $set[]="data_necessaria_override = ?"; $vals[]=$dn; }
        $h('Cronograma (vínculo)', '', $cm ? ($cm . ($dn ? ' → ' . $dn : '')) : 'limpo (voltou ao automático)');
        unset($campos['crono_marco_override'], $campos['data_necessaria_override']);
    }

    // ----- campos simples do radar_item -----
    foreach ($campos as $k => $v) {
        if (!in_array($k, $SIMPLES, true)) continue;
        if ($k === 'tipo' && !in_array($v, $TIPOS_OK, true)) throw new Exception('tipo inválido: ' . $v);
        if ($k === 'status' && $v !== '' && !in_array($v, $STATUS_OK, true)) throw new Exception('status inválido: ' . $v);
        if ($k === 'responsavel' && stripos((string)$v, 'camila') !== false) throw new Exception('responsável inválido');
        $antes = $before[$k] ?? '';
        if ((string)$antes !== (string)$v) $h($LABEL[$k] ?? $k, $antes, $v);
        if ($k === 'validado')          { $set[]="$k = ?"; $vals[]=(int)(bool)$v; continue; }
        if ($k === 'lead_override')     { $set[]="$k = ?"; $vals[]= nullable($v)===null?null:(int)$v; continue; }
        if ($k === 'verba_override')    { $vc=nullable($v); $set[]="$k = ?"; $vals[]= $vc===null?null:(float)$v; $set[]="verba_curada = ?"; $vals[]= $vc===null?0:1; continue; }
        if ($k === 'quantitativo_valor'){ $qc=nullable($v); $set[]="$k = ?"; $vals[]= $qc===null?null:(float)$v; $set[]="quant_curada = ?"; $vals[]= $qc===null?0:1; continue; }
        $set[] = "$k = ?"; $vals[] = nullable($v);
    }

    if (!$set && !$hist) throw new Exception('nenhuma alteração');

    if ($set) {
        $set[] = "updated_at = ?"; $vals[] = date('c'); $vals[] = $ordem;
        $pdo->prepare("UPDATE radar_item SET " . implode(', ', $set) . " WHERE obra_id=1 AND servico_id=?")->execute($vals);
    }

    // grava o histórico (quem/quando/item/campo/antes→depois)
    foreach ($hist as $e) log_historico($pdo, 1, $ordem, $item_nome, $me, $perms['nome'], $e[0], $e[1], $e[2]);
    $pdo->commit();

    $row = $pdo->prepare("SELECT * FROM radar_item WHERE obra_id=1 AND servico_id=?"); $row->execute([$ordem]);
    echo json_encode(['ok'=>true, 'item'=>$row->fetch(), 'hist'=>count($hist)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
