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
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/solic.php';

function sol_dias($emissao) { if (!$emissao) return null; $d = (int)(new DateTime(substr($emissao,0,10)))->diff(new DateTime('today'))->format('%r%a'); return $d; }
// nomes de centro de custo da CAPRETZ (do sistema antigo) — pré-preenche o nome comercial
$GLOBALS['CAPRETZ_CC'] = ['001'=>'Comercial Americana','010'=>'Sede','015'=>'MKT','020'=>'SAT','032'=>'Licel','033'=>'Obras SAT','036'=>'Piamonte','039'=>'Contrap. Piamonte','040'=>'Cajá','041'=>'Espazo','042'=>'Prades'];
function sol_nome_default($coligada, $obra_cod) {
    if (stripos($coligada, 'CAPRETZ') !== false && isset($GLOBALS['CAPRETZ_CC'][$obra_cod])) return $GLOBALS['CAPRETZ_CC'][$obra_cod];
    // nome comercial curto = a coligada sem o "EMPREENDIMENTO...SPE LTDA"
    $n = preg_replace('/\s+(EMPREENDIMENTO|EMPREENDIMENTOS).*/i', '', $coligada);
    return trim($n) ?: $coligada;
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
            $lista[] = ['coligada'=>$s['coligada'],'numero'=>$s['numero'],'obra_cod'=>$s['obra_cod'],'nome_obra'=>$nomeObra,
                'comprador_id'=>$compId,'comprador_nome'=>$compNome,'emissao'=>$s['emissao'],'dias'=>$dias,'bucket'=>$bk,
                'status'=>$status,'observacoes'=>$ov['observacoes'] ?? '','cotacao_id'=>$ov['cotacao_id'] ?? null,
                'n_itens'=>count($s['itens']),'primeiro'=>$s['itens'][0]['produto'] ?? '','itens'=>$s['itens']];
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
        $obraId = $so['radar_obra_id'] ?? null;
        $titulo = 'SC ' . $num . ' · ' . $nomeObra;
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO cotacao (obra_id, servico_id, titulo, categoria, tipo_servico, verba, descricao, num_solicitacao, solic_coligada, solic_obra_cod, status, aprovacao, criado_por, criado_nome, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?, 'aberta','aguardando', ?,?,?,?)")
            ->execute([$obraId ?: null, null, $titulo, '', '', null, 'Solicitação de compra nº '.$num.' — '.$col.($obraCod?(' (centro de custo '.$obraCod.')'):''), $num, $col, $obraCod, $me, $perms['nome'] ?? null, $now, $now]);
        $cid = (int)$pdo->lastInsertId();
        $insI = $pdo->prepare("INSERT INTO cotacao_item (cotacao_id, descricao, unidade, quantidade, observacao, ordem) VALUES (?,?,?,?,?,?)");
        $o = 0; foreach ($rows as $r) $insI->execute([$cid, trim((string)($r['produto'] ?? '')), trim((string)($r['und'] ?? '')), ($r['qtd'] ?? null) !== null ? (float)$r['qtd'] : null, trim((string)($r['observacao'] ?? '')), $o++]);
        // liga no overlay
        $ov = $pdo->prepare("SELECT id FROM solic_overlay WHERE coligada=? AND numero=?"); $ov->execute([$col,$num]); $ovid = (int)($ov->fetchColumn() ?: 0);
        if ($ovid) $pdo->prepare("UPDATE solic_overlay SET cotacao_id=?, status='em_cotacao', updated_by=?, updated_at=? WHERE id=?")->execute([$cid,$me,$now,$ovid]);
        else $pdo->prepare("INSERT INTO solic_overlay (coligada,numero,status,cotacao_id,updated_by,updated_at) VALUES (?,?, 'em_cotacao', ?,?,?)")->execute([$col,$num,$cid,$me,$now]);
        $pdo->commit();
        echo json_encode(['ok'=>true, 'cotacao_id'=>$cid], JSON_UNESCAPED_UNICODE); exit;
    }

    echo json_encode(['error'=>'ação desconhecida'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
