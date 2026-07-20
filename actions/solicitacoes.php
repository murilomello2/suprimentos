<?php
/**
 * SOLICITAÇÕES DE COMPRA — lê a fila ao vivo (Supabase/Power Automate/TOTVS), junta com o de-para de obra
 * (coligada+centro de custo → comprador) e o overlay do comprador (status/observações), monta dashboard + lista.
 * GET                       -> {solicitacoes, dashboard, compradores, status_opts}
 * GET ?obras                -> {obras:[pares (coligada,obra_cod) + atribuição], usuarios, radar_obras}
 * POST {acao:'salvar_obra', me, obra{coligada,obra_cod,nome_comercial,comprador_id,radar_obra_id}}
 * POST {acao:'salvar_overlay', me, coligada, numero, status?, observacoes?}
 * POST {acao:'gerar_cotacao', me, coligada, numero}   -> cria cotação no mapa a partir da solicitação
 */
if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) @ob_start('ob_gzhandler');   // PERF: gzip do JSON (hosting não faz)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/solic.php';
require_once __DIR__ . '/../includes/obra_registry.php';   // cadastro único: resolve obra da solicitação

function sol_dias($emissao) { if (!$emissao) return null; $d = (int)(new DateTime(substr($emissao,0,10)))->diff(new DateTime('today'))->format('%r%a'); return $d; }
// normaliza descrição p/ casar item-da-SC ↔ cotacao_item quando não há codprd (fallback do casamento exato)
function sol_norm($s) {
    $s = strtr((string)$s, ['Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e','Í'=>'i','Ó'=>'o','Ô'=>'o','Õ'=>'o','Ú'=>'u','Ç'=>'c','á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);
    return preg_replace('/\s+/', ' ', strtolower(trim($s)));
}
// nomes de centro de custo da CAPRETZ (do sistema antigo) — pré-preenche o nome comercial
$GLOBALS['CAPRETZ_CC'] = ['001'=>'Comercial Americana','010'=>'Sede','015'=>'MKT','020'=>'SAT','032'=>'Licel','033'=>'Obras SAT','036'=>'Piamonte','039'=>'Contrap. Piamonte','040'=>'Cajá','041'=>'Espazo','042'=>'Prades'];
function sol_nome_default($coligada, $obra_cod) {
    if (stripos($coligada, 'CAPRETZ') !== false && isset($GLOBALS['CAPRETZ_CC'][$obra_cod])) return $GLOBALS['CAPRETZ_CC'][$obra_cod];
    // nome comercial curto = a coligada sem o "EMPREENDIMENTO...SPE LTDA"
    $n = preg_replace('/\s+(EMPREENDIMENTO|EMPREENDIMENTOS).*/i', '', $coligada);
    return trim($n) ?: $coligada;
}
// título automático pelos números das SCs: "SC 1533" | "SCs 1533 e 1544" | "SCs 1533, 1544 e 1550" | "SCs 1533, 1544, 1550 +3"
function sol_titulo_scs($nums) {
    $nums = array_values(array_filter(array_map('strval', (array)$nums), fn($x)=>$x !== ''));
    $n = count($nums);
    if ($n === 0) return 'Cotação';
    if ($n === 1) return 'SC ' . $nums[0];
    if ($n <= 4) { $last = array_pop($nums); return 'SCs ' . implode(', ', $nums) . ' e ' . $last; }
    return 'SCs ' . implode(', ', array_slice($nums, 0, 3)) . ' +' . ($n - 3);
}

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $rows = solic_fila_all();
        $sol = solic_agrupar($rows);
        // mapas do cockpit
        $obraMap = []; foreach ($pdo->query("SELECT * FROM solic_obra") as $o) $obraMap[$o['coligada'].'|'.$o['obra_cod']] = $o;
        $ovMap = []; foreach ($pdo->query("SELECT * FROM solic_overlay") as $v) $ovMap[$v['coligada'].'|'.$v['numero']] = $v;

        if (isset($_GET['obras'])) {
            // universo de atribuição = pares distintos (coligada, obra_cod) + qtd + atribuição atual
            $pares = [];
            foreach ($sol as $s) { $k = $s['coligada'].'|'.$s['obra_cod'];
                if (!isset($pares[$k])) $pares[$k] = ['coligada'=>$s['coligada'],'obra_cod'=>$s['obra_cod'],'n'=>0];
                $pares[$k]['n']++; }
            $out = [];
            foreach ($pares as $k => $p) { $o = $obraMap[$k] ?? null;
                $out[] = ['coligada'=>$p['coligada'],'obra_cod'=>$p['obra_cod'],'n'=>$p['n'],
                    'nome_comercial'=>$o['nome_comercial'] ?? sol_nome_default($p['coligada'],$p['obra_cod']),
                    'cnpj'=>$o['cnpj'] ?? '', 'endereco'=>$o['endereco'] ?? '',
                    'comprador_id'=>$o['comprador_id'] ?? '','comprador_nome'=>$o['comprador_nome'] ?? '',
                    'radar_obra_id'=>$o['radar_obra_id'] ?? null]; }
            usort($out, fn($a,$b)=>$b['n']-$a['n']);
            $usuarios = $pdo->query("SELECT bitrix_id, nome FROM usuario WHERE ativo=1 ORDER BY nome")->fetchAll();
            $radar = $pdo->query("SELECT id, nome FROM obra ORDER BY id")->fetchAll();
            echo json_encode(['obras'=>$out, 'usuarios'=>$usuarios, 'radar_obras'=>$radar], JSON_UNESCAPED_UNICODE); exit;
        }

        // HEAL: overlay apontando p/ cotação inexistente (o comprador excluiu a cotação) — limpa o vínculo pendurado
        // e, se o status ainda era o automático 'em_cotacao', reverte p/ 'pendente' (a solicitação volta a precisar de cotação).
        $cotIds = []; foreach ($pdo->query("SELECT id FROM cotacao") as $r) $cotIds[(int)$r['id']] = true;
        foreach ($ovMap as $kk => $vv) {
            if (!empty($vv['cotacao_id']) && !isset($cotIds[(int)$vv['cotacao_id']])) {
                $ns = ($vv['status'] === 'em_cotacao') ? 'pendente' : $vv['status'];
                $pdo->prepare("UPDATE solic_overlay SET cotacao_id=NULL, status=? WHERE id=?")->execute([$ns, $vv['id']]);
                $ovMap[$kk]['cotacao_id'] = null; $ovMap[$kk]['status'] = $ns;
            }
        }

        // FASE 2 — COBERTURA DE COTAÇÃO POR ITEM (cinza=sem cotação · amarelo=em cotação aberta · verde=finalizada/com PC).
        // Mapa (coligada|numero) → { matchkey → status }. matchkey = codprd (exato) ou produto normalizado (fallback legado).
        $cov = [];   // (coligada|numero) => [ matchkey => ['status'=>, 'cid'=>, 'ctit'=>] ]
        // matchkey: SEQ (linha EXATA da SC — distingue itens do mesmo produto) > codprd > produto normalizado
        $addCov = function($col,$num,$seq,$codprd,$prod,$status,$cid,$ctit) use (&$cov) {
            $col = trim((string)$col); $num = trim((string)$num); if ($col==='' || $num==='') return;
            $seq = trim((string)$seq); $codprd = trim((string)$codprd);
            $k = $col.'|'.$num;
            $mk = $seq!=='' ? ('s:'.$seq) : ($codprd!=='' ? ('c:'.$codprd) : ('p:'.sol_norm($prod)));
            if (!isset($cov[$k])) $cov[$k]=[];
            $cur = $cov[$k][$mk] ?? null;
            if ($cur === null || ($cur['status'] !== 'coberto' && $status === 'coberto'))   // 'coberto' vence 'cotando'; senão mantém o 1º
                $cov[$k][$mk] = ['status'=>$status, 'cid'=>(int)$cid, 'ctit'=>$ctit];
        };
        try {
            // PC por COLIGADA (multi-PC) + nº de coligadas por cotação → 'coberto' é POR COLIGADA (não o num_pedido agregado do header)
            $cotPed = [];   // cotacao_id => [coligada => num_pedido]
            foreach ($pdo->query("SELECT cotacao_id, coligada, num_pedido FROM cotacao_pedido WHERE num_pedido IS NOT NULL AND num_pedido<>''") as $r)
                $cotPed[(int)$r['cotacao_id']][trim((string)$r['coligada'])] = trim((string)$r['num_pedido']);
            $cotNCol = [];  // cotacao_id => nº de coligadas distintas
            foreach ($pdo->query("SELECT cotacao_id, COUNT(DISTINCT solic_coligada) n FROM cotacao_item WHERE solic_coligada IS NOT NULL AND solic_coligada<>'' GROUP BY cotacao_id") as $r)
                $cotNCol[(int)$r['cotacao_id']] = (int)$r['n'];
            // (a) itens carimbados: coberto = cotação finalizada OU a COLIGADA DO ITEM tem PC (por coligada; header só serve p/ coligada única)
            foreach ($pdo->query("SELECT ci.cotacao_id cid, ci.solic_coligada col, ci.solic_numero num, ci.solic_seq seq, ci.solic_codprd codprd, ci.descricao prod, c.status st, c.num_pedido hdr, c.titulo ctit
                                  FROM cotacao_item ci JOIN cotacao c ON c.id=ci.cotacao_id
                                  WHERE ci.solic_coligada IS NOT NULL AND ci.solic_coligada<>'' AND ci.solic_numero IS NOT NULL AND ci.solic_numero<>''") as $r) {
                $cid = (int)$r['cid']; $colPc = $cotPed[$cid][trim((string)$r['col'])] ?? '';
                $isMulti = ($cotNCol[$cid] ?? 1) > 1;
                $effPc = $colPc !== '' ? $colPc : ($isMulti ? '' : trim((string)$r['hdr']));   // multi: só o PC da PRÓPRIA coligada conta
                $status = (($r['st']==='finalizada') || $effPc !== '') ? 'coberto' : 'cotando';
                $addCov($r['col'],$r['num'],$r['seq'],$r['codprd'],$r['prod'],$status,$cid,$r['ctit']);
            }
            // (b) cotações antigas (single, itens sem origem): liga pela SC via overlay e casa por produto (header vale — é coligada única)
            foreach ($pdo->query("SELECT c.id cid, o.coligada col, o.numero num, ci.descricao prod, c.status st, c.num_pedido pc, c.titulo ctit
                                  FROM solic_overlay o JOIN cotacao c ON c.id=o.cotacao_id JOIN cotacao_item ci ON ci.cotacao_id=c.id
                                  WHERE o.cotacao_id IS NOT NULL AND (ci.solic_coligada IS NULL OR ci.solic_coligada='')") as $r) {
                $status = (($r['st']==='finalizada') || trim((string)$r['pc'])!=='') ? 'coberto' : 'cotando';
                $addCov($r['col'],$r['num'],'','',$r['prod'],$status,(int)$r['cid'],$r['ctit']);
            }
        } catch (Throwable $e) { $cov = []; }

        // LISTA + DASHBOARD
        $lista = []; $dash = ['total'=>0,'b'=>['r'=>0,'a'=>0,'l'=>0,'c'=>0],'por_obra'=>[],'por_comprador'=>[],'por_status'=>[]];
        foreach ($sol as $s) {
            $k = $s['coligada'].'|'.$s['obra_cod']; $o = $obraMap[$k] ?? null;
            $nomeObra = $o['nome_comercial'] ?? sol_nome_default($s['coligada'], $s['obra_cod']);
            $compNome = $o['comprador_nome'] ?? ''; $compId = $o['comprador_id'] ?? '';
            $ov = $ovMap[$s['coligada'].'|'.$s['numero']] ?? null;
            $status = $ov['status'] ?? 'pendente';
            $dias = sol_dias($s['emissao']);
            $bk = $dias===null?'r':($dias<7?'r':($dias<15?'a':($dias<30?'l':'c')));
            // carimba a cobertura em cada item + agrega a cobertura da SC (cinza/parcial/total)
            $cmap = $cov[$s['coligada'].'|'.$s['numero']] ?? []; $nCob=0; $nAny=0; $itensC = $s['itens'];
            // nomes normalizados que se REPETEM nesta SC — nesses, o fallback por nome é ambíguo (não usar)
            $nameCount = [];
            foreach ($itensC as $__c) { $nn = sol_norm($__c['produto'] ?? ''); $nameCount[$nn] = ($nameCount[$nn] ?? 0) + 1; }
            $scCots = [];   // cotações distintas que tocam esta SC (id => titulo)
            foreach ($itensC as &$__it) {
                // 1º SEQ (linha exata) · 2º codprd · 3º nome (só se único na SC) — evita falso-positivo entre itens iguais
                $sq = trim((string)($__it['seq'] ?? '')); $cp = trim((string)($__it['codprd'] ?? '')); $nn = sol_norm($__it['produto'] ?? '');
                $m = ($sq !== '') ? ($cmap['s:'.$sq] ?? null) : null;
                if ($m === null && $cp !== '') $m = $cmap['c:'.$cp] ?? null;
                if ($m === null && ($nameCount[$nn] ?? 0) <= 1) $m = $cmap['p:'.$nn] ?? null;
                if ($m) { $__it['cot'] = $m['status']; $__it['cot_cid'] = $m['cid']; $__it['cot_ctit'] = $m['ctit']; if (!empty($m['cid'])) $scCots[$m['cid']] = $m['ctit']; }
                else { $__it['cot'] = 'vazio'; }
                if (($__it['cot'] ?? '')==='coberto') $nCob++; if (($__it['cot'] ?? '')!=='vazio') $nAny++;
            }
            unset($__it);
            $nI = count($itensC);
            $cobertura = ($nI>0 && $nCob===$nI) ? 'total' : ($nAny>0 ? 'parcial' : 'vazio');
            $cotList = []; foreach ($scCots as $ccid=>$cctit) $cotList[] = ['id'=>$ccid, 'titulo'=>$cctit];
            $lista[] = ['coligada'=>$s['coligada'],'numero'=>$s['numero'],'obra_cod'=>$s['obra_cod'],'nome_obra'=>$nomeObra,
                'comprador_id'=>$compId,'comprador_nome'=>$compNome,'emissao'=>$s['emissao'],'dias'=>$dias,'bucket'=>$bk,
                'status'=>$status,'observacoes'=>$ov['observacoes'] ?? '','cotacao_id'=>$ov['cotacao_id'] ?? null,
                'cobertura'=>$cobertura,'cot_cob'=>$nCob,'cot_any'=>$nAny,'cotacoes'=>$cotList,
                'n_itens'=>count($itensC),'primeiro'=>$itensC[0]['produto'] ?? '','itens'=>$itensC];
            // dashboard
            $dash['total']++; $dash['b'][$bk]++;
            $dash['por_status'][$status] = ($dash['por_status'][$status] ?? 0) + 1;
            if (!isset($dash['por_obra'][$nomeObra])) $dash['por_obra'][$nomeObra] = ['total'=>0,'recentes'=>0,'criticos'=>0];
            $dash['por_obra'][$nomeObra]['total']++; if ($bk==='r') $dash['por_obra'][$nomeObra]['recentes']++; if ($bk==='c') $dash['por_obra'][$nomeObra]['criticos']++;
            $cn = $compNome ?: '(sem comprador)';
            if (!isset($dash['por_comprador'][$cn])) $dash['por_comprador'][$cn] = ['total'=>0,'r'=>0,'a'=>0,'l'=>0,'c'=>0];
            $dash['por_comprador'][$cn]['total']++; $dash['por_comprador'][$cn][$bk]++;
        }
        // ordena as tabelas do dashboard
        uasort($dash['por_obra'], fn($a,$b)=>$b['total']-$a['total']);
        uasort($dash['por_comprador'], fn($a,$b)=>$b['total']-$a['total']);
        echo json_encode(['solicitacoes'=>$lista, 'dashboard'=>$dash,
            'compradores'=>array_values(array_unique(array_filter(array_map(fn($l)=>$l['comprador_nome'], $lista)))),
            'status_opts'=>['pendente','em_cotacao','cotacoes_recebidas','pedido_criado','cancelado']], JSON_UNESCAPED_UNICODE); exit;
    }

    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $me = $in['me'] ?? null; $perms = user_perms($pdo, $me);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error'=>'Não autorizado.']); exit; }
    $acao = $in['acao'] ?? ''; $now = date('c');

    if ($acao === 'salvar_obra') {   // atribuição da obra (de-para + comprador) — admin/edita todas
        if (empty($perms['perm_admin']) && ($perms['editar_escopo'] ?? '') !== 'todas') { http_response_code(403); echo json_encode(['error'=>'Atribuição de obra é global — só admin.']); exit; }
        $o = (array)($in['obra'] ?? []); $col = trim((string)($o['coligada'] ?? '')); $cod = trim((string)($o['obra_cod'] ?? ''));
        if ($col === '') throw new Exception('coligada obrigatória');
        $compId = trim((string)($o['comprador_id'] ?? ''));
        $compNome = '';
        if ($compId !== '') { $q = $pdo->prepare("SELECT nome FROM usuario WHERE bitrix_id=?"); $q->execute([$compId]); $compNome = (string)$q->fetchColumn(); }
        $cnpj = trim((string)($o['cnpj'] ?? ''));
        $endereco = trim((string)($o['endereco'] ?? ''));
        $q = $pdo->prepare("SELECT id FROM solic_obra WHERE coligada=? AND obra_cod=?"); $q->execute([$col,$cod]); $id = (int)($q->fetchColumn() ?: 0);
        $vals = [trim((string)($o['nome_comercial'] ?? '')), $cnpj ?: null, $endereco ?: null, $compId ?: null, $compNome ?: null, ($o['radar_obra_id'] ?? null) ?: null];
        if ($id) $pdo->prepare("UPDATE solic_obra SET nome_comercial=?, cnpj=?, endereco=?, comprador_id=?, comprador_nome=?, radar_obra_id=?, updated_at=? WHERE id=?")->execute(array_merge($vals,[$now,$id]));
        else $pdo->prepare("INSERT INTO solic_obra (coligada,obra_cod,nome_comercial,cnpj,endereco,comprador_id,comprador_nome,radar_obra_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)")->execute(array_merge([$col,$cod],$vals,[$now,$now]));
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'salvar_overlay') {   // status/observações do comprador numa solicitação
        $col = trim((string)($in['coligada'] ?? '')); $num = trim((string)($in['numero'] ?? ''));
        if ($col === '' || $num === '') throw new Exception('coligada e numero obrigatórios');
        $q = $pdo->prepare("SELECT id FROM solic_overlay WHERE coligada=? AND numero=?"); $q->execute([$col,$num]); $id = (int)($q->fetchColumn() ?: 0);
        $sets = []; $args = [];
        if (array_key_exists('status', $in)) { $sets[]='status=?'; $args[]=trim((string)$in['status']); }
        if (array_key_exists('observacoes', $in)) { $sets[]='observacoes=?'; $args[]=trim((string)$in['observacoes']); }
        if (!$sets) throw new Exception('nada a salvar');
        if ($id) { $sets[]='updated_by=?'; $args[]=$me; $sets[]='updated_at=?'; $args[]=$now; $args[]=$id;
            $pdo->prepare("UPDATE solic_overlay SET ".implode(',', $sets)." WHERE id=?")->execute($args); }
        else { $pdo->prepare("INSERT INTO solic_overlay (coligada,numero,status,observacoes,updated_by,updated_at) VALUES (?,?,?,?,?,?)")
            ->execute([$col,$num, array_key_exists('status',$in)?trim((string)$in['status']):'pendente', array_key_exists('observacoes',$in)?trim((string)$in['observacoes']):'', $me, $now]); }
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'gerar_cotacao') {   // cria cotação no Mapa a partir da solicitação
        $col = trim((string)($in['coligada'] ?? '')); $num = trim((string)($in['numero'] ?? ''));
        if ($col === '' || $num === '') throw new Exception('coligada e numero obrigatórios');
        // lê os itens da solicitação da fila (filtro server-side no PostgREST)
        $rows = solic_rest('select=*&coligada=eq.' . rawurlencode($col) . '&numero=eq.' . rawurlencode($num) . '&order=seq.asc');
        if (!$rows) throw new Exception('solicitação não encontrada na fila');
        $obraCod = $rows[0]['obra'] ?? '';
        $q = $pdo->prepare("SELECT nome_comercial, comprador_id, radar_obra_id FROM solic_obra WHERE coligada=? AND obra_cod=?"); $q->execute([$col,$obraCod]); $so = $q->fetch();
        $nomeObra = $so['nome_comercial'] ?? sol_nome_default($col, $obraCod);
        // OBRA pelo CADASTRO ÚNICO (coligada + centro de custo → obra_ficha → promove ao radar); fallback no vínculo antigo
        $obraId = obra_radar_de_solicitacao($pdo, $col, $obraCod);
        if (!$obraId) $obraId = $so['radar_obra_id'] ?? null;
        $titulo = 'SC ' . $num . ' · ' . $nomeObra;
        $colidmov = trim((string)($rows[0]['colidmov'] ?? ''));   // ex.: "27-20628" (27 = coligada Legacy) — casa o PC CERTO (nº de SC não é único entre coligadas)
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO cotacao (obra_id, servico_id, titulo, categoria, tipo_servico, verba, descricao, num_solicitacao, solic_coligada, solic_obra_cod, solic_colidmov, status, aprovacao, criado_por, criado_nome, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?, 'aberta','aguardando', ?,?,?,?)")
            ->execute([$obraId ?: null, null, $titulo, '', '', null, 'Solicitação de compra nº '.$num.' — '.$col.($obraCod?(' (centro de custo '.$obraCod.')'):''), $num, $col, $obraCod, $colidmov ?: null, $me, $perms['nome'] ?? null, $now, $now]);
        $cid = (int)$pdo->lastInsertId();
        // FASE 2: carimba a origem por item (coligada/numero/colidmov/seq/codprd) p/ a cobertura por item e o casamento do PC
        $insI = $pdo->prepare("INSERT INTO cotacao_item (cotacao_id, descricao, unidade, quantidade, observacao, ordem, obra_id, solic_coligada, solic_numero, solic_colidmov, solic_seq, solic_codprd) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $o = 0; foreach ($rows as $r) $insI->execute([$cid, trim((string)($r['produto'] ?? '')), trim((string)($r['und'] ?? '')), ($r['qtd'] ?? null) !== null ? (float)$r['qtd'] : null, trim((string)($r['observacao'] ?? '')), $o++,
            $obraId ?: null, $col, $num, (trim((string)($r['colidmov'] ?? '')) ?: $colidmov) ?: null, (($r['seq'] ?? null) !== null && $r['seq'] !== '') ? (int)$r['seq'] : null, trim((string)($r['codprd'] ?? '')) ?: null]);
        // liga no overlay
        $ov = $pdo->prepare("SELECT id FROM solic_overlay WHERE coligada=? AND numero=?"); $ov->execute([$col,$num]); $ovid = (int)($ov->fetchColumn() ?: 0);
        if ($ovid) $pdo->prepare("UPDATE solic_overlay SET cotacao_id=?, status='em_cotacao', updated_by=?, updated_at=? WHERE id=?")->execute([$cid,$me,$now,$ovid]);
        else $pdo->prepare("INSERT INTO solic_overlay (coligada,numero,status,cotacao_id,updated_by,updated_at) VALUES (?,?, 'em_cotacao', ?,?,?)")->execute([$col,$num,$cid,$me,$now]);
        $pdo->commit();
        echo json_encode(['ok'=>true, 'cotacao_id'=>$cid], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'gerar_cotacao_multi') {   // cria UMA cotação juntando itens escolhidos de VÁRIAS solicitações (multi-obra)
        if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error'=>'Não autorizado.']); exit; }
        $itens = array_values(array_filter((array)($in['itens'] ?? []), fn($i)=>trim((string)($i['produto'] ?? '')) !== ''));
        if (!$itens) throw new Exception('selecione ao menos um item');
        $obras = []; $solics = []; $obraCache = []; $cmCache = [];
        foreach ($itens as &$it) {
            $col = trim((string)($it['coligada'] ?? '')); $numero = trim((string)($it['numero'] ?? '')); $ocod = trim((string)($it['obra_cod'] ?? '')); $k = $col . '|' . $numero;
            if (!array_key_exists($col . '|' . $ocod, $obraCache)) $obraCache[$col . '|' . $ocod] = obra_radar_de_solicitacao($pdo, $col, $ocod);
            $it['_obra_id'] = $obraCache[$col . '|' . $ocod];
            // colidmov (embute a coligada — casa o PC certo): usa o que veio do front, senão busca na fila
            $cm = trim((string)($it['colidmov'] ?? ''));
            if ($cm === '' && $col !== '' && $numero !== '') {
                if (!array_key_exists($k, $cmCache)) { $fr = solic_rest('select=colidmov&coligada=eq.' . rawurlencode($col) . '&numero=eq.' . rawurlencode($numero) . '&limit=1'); $cmCache[$k] = $fr ? trim((string)($fr[0]['colidmov'] ?? '')) : ''; }
                $cm = $cmCache[$k];
            }
            $it['_colidmov'] = $cm;
            if ($it['_obra_id']) $obras[$it['_obra_id']] = true;
            if ($col !== '' && $numero !== '') $solics[$k] = ['coligada'=>$col, 'numero'=>$numero, 'obra_cod'=>$ocod];
        }
        unset($it);
        $obraUnica = count($obras) === 1 ? (int)array_key_first($obras) : null;   // >1 obra = cotação mista (obra por item)
        $titulo = trim((string)($in['titulo'] ?? ''));
        if ($titulo === '') {
            // título automático pelos NÚMEROS das SCs (sem zeros à esquerda): "SC 1533" / "SCs 1533 e 1544" / "SCs 1533, 1544 e 1550"
            $scNums = array_values(array_unique(array_map(fn($s)=>ltrim((string)$s['numero'], '0') ?: (string)$s['numero'], $solics)));
            $titulo = sol_titulo_scs($scNums);
        }
        $descr = 'Cotação de ' . count($solics) . ' solicitação(ões): ' . implode(', ', array_map(fn($s)=>'SC ' . $s['numero'] . ' (' . $s['coligada'] . ')', $solics));
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO cotacao (obra_id, servico_id, titulo, categoria, tipo_servico, verba, descricao, status, aprovacao, criado_por, criado_nome, created_at, updated_at) VALUES (?,?,?,?,?,?,?, 'aberta','aguardando', ?,?,?,?)")
            ->execute([$obraUnica, null, $titulo, '', '', null, $descr, $me, $perms['nome'] ?? null, $now, $now]);
        $cid = (int)$pdo->lastInsertId();
        $insI = $pdo->prepare("INSERT INTO cotacao_item (cotacao_id, descricao, unidade, quantidade, observacao, ordem, obra_id, solic_coligada, solic_numero, solic_colidmov, solic_seq, solic_codprd) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $o = 0;
        foreach ($itens as $it) {
            $insI->execute([$cid, trim((string)$it['produto']), trim((string)($it['und'] ?? '')),
                ($it['qtd'] ?? null) !== null && $it['qtd'] !== '' ? (float)$it['qtd'] : null, trim((string)($it['observacao'] ?? '')), $o++,
                $it['_obra_id'] ?: null, trim((string)($it['coligada'] ?? '')), trim((string)($it['numero'] ?? '')), $it['_colidmov'] ?: null,
                (($it['seq'] ?? null) !== null && $it['seq'] !== '') ? (int)$it['seq'] : null, trim((string)($it['codprd'] ?? '')) ?: null]);
        }
        foreach ($solics as $s) {
            $ov = $pdo->prepare("SELECT id FROM solic_overlay WHERE coligada=? AND numero=?"); $ov->execute([$s['coligada'], $s['numero']]); $ovid = (int)($ov->fetchColumn() ?: 0);
            if ($ovid) $pdo->prepare("UPDATE solic_overlay SET cotacao_id=?, status='em_cotacao', updated_by=?, updated_at=? WHERE id=?")->execute([$cid, $me, $now, $ovid]);
            else $pdo->prepare("INSERT INTO solic_overlay (coligada,numero,status,cotacao_id,updated_by,updated_at) VALUES (?,?, 'em_cotacao', ?,?,?)")->execute([$s['coligada'], $s['numero'], $cid, $me, $now]);
        }
        $pdo->commit();
        echo json_encode(['ok'=>true, 'cotacao_id'=>$cid, 'itens'=>count($itens), 'solicitacoes'=>count($solics), 'obras'=>count($obras)], JSON_UNESCAPED_UNICODE); exit;
    }

    echo json_encode(['error'=>'ação desconhecida'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
