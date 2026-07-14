<?php
/**
 * MAPA DE COTAÇÕES (reconstruído no cockpit / MySQL). Núcleo: cotação → itens → propostas de
 * fornecedores (preço por item) → MAPA comparativo (melhor preço por item + totais).
 * Vínculo opcional a um item do radar (servico_id) e a uma obra (obra_id). Standalone é permitido.
 *
 * GET  ?id=N                      -> cotação completa (header + itens + propostas + mapa computado)
 * GET  (?obra=N opcional)         -> lista de cotações (+ resumo por cotação: n_propostas, melhor_oferta)
 * POST {acao:'criar', me, obra_id?, servico_id?, titulo, categoria, tipo_servico, verba, descricao, itens[]}
 * POST {acao:'proposta', me, cotacao_id, proposta_id?, fornecedor_id?, fornecedor_nome, prazo, observacoes, itens[]}
 * POST {acao:'status', me, cotacao_id, status?, aprovacao?}
 * POST {acao:'excluir', me, cotacao_id}
 * POST {acao:'excluir_proposta', me, proposta_id}
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/obra_registry.php';   // cadastro único: resolver/promover obra
require_once __DIR__ . '/../includes/coligadas.php';       // FASE 2: coligada_cod_de_nome p/ agrupar PC por coligada

function cot_can_edit($pdo, $me, $obra) {
    $perms = user_perms($pdo, $me);
    if (!empty($perms['perm_admin'])) return $perms;
    if (can_edit_obra($perms, max(1, (int)$obra))) return $perms;
    return null;
}
// Gerir a cotação (excluir / editar itens): ADMIN, ou quem CRIOU, ou quem edita a obra dela.
function cot_can_manage($pdo, $me, $cid) {
    $q = $pdo->prepare("SELECT COALESCE(obra_id,1) AS obra, criado_por FROM cotacao WHERE id=?"); $q->execute([(int)$cid]); $r = $q->fetch();
    if (!$r) return false;
    $perms = user_perms($pdo, $me);
    if (!empty($perms['perm_admin'])) return true;
    if ($me !== null && $me !== '' && (string)$r['criado_por'] === (string)$me) return true;
    return (bool)cot_can_edit($pdo, $me, (int)$r['obra'] ?: 1);
}
// insere fornecedores CONVIDADOS na concorrência (dedup por fornecedor_id/nome)
function cot_insert_convidados($pdo, $cid, $lista) {
    $ins = $pdo->prepare("INSERT INTO cotacao_fornecedor (cotacao_id, fornecedor_id, fornecedor_nome, categoria, contato, email, telefone, created_at) VALUES (?,?,?,?,?,?,?,?)");
    $ex = $pdo->prepare("SELECT fornecedor_id, fornecedor_nome FROM cotacao_fornecedor WHERE cotacao_id=?"); $ex->execute([$cid]);
    $seen = [];
    foreach ($ex->fetchAll() as $r) { $seen['n:'.strtolower(trim((string)$r['fornecedor_nome']))] = 1; if ($r['fornecedor_id']) $seen['i:'.(int)$r['fornecedor_id']] = 1; }
    $now = date('c'); $n = 0;
    foreach ((array)$lista as $f) {
        $nome = trim((string)($f['nome'] ?? $f['fornecedor_nome'] ?? '')); if ($nome === '') continue;
        $fid = (int)($f['id'] ?? $f['fornecedor_id'] ?? 0) ?: null;
        if (($fid && isset($seen['i:'.$fid])) || isset($seen['n:'.strtolower($nome)])) continue;
        $ins->execute([$cid, $fid, $nome, trim((string)($f['categoria'] ?? '')), trim((string)($f['contato'] ?? '')), trim((string)($f['email'] ?? '')), trim((string)($f['telefone'] ?? '')), $now]);
        $seen['n:'.strtolower($nome)] = 1; if ($fid) $seen['i:'.$fid] = 1; $n++;
    }
    return $n;
}
// mapa comparativo a partir das propostas: melhor (menor) preço por item + total ótimo + melhor fornecedor único
function cot_mapa($itens, $propostas) {
    $melhor = [];        // item_id => ['proposta_id','fornecedor','preco_unit','preco_total']
    foreach ($itens as $it) {
        $best = null;
        foreach ($propostas as $p) {
            $pi = $p['itens'][$it['id']] ?? null;
            if (!$pi) continue;
            $pt = $pi['preco_total'];
            if ($pt === null || $pt <= 0) continue;
            if ($best === null || $pt < $best['preco_total'])
                $best = ['proposta_id'=>$p['id'], 'fornecedor'=>$p['fornecedor_nome'], 'preco_unit'=>$pi['preco_unit'], 'preco_total'=>$pt];
        }
        if ($best) $melhor[$it['id']] = $best;
    }
    $melhor_total = 0.0; foreach ($melhor as $b) $melhor_total += (float)$b['preco_total'];
    // melhor fornecedor ÚNICO (menor total entre quem respondeu com valor)
    $melhor_oferta = null; $fornecedor_destaque = null;
    foreach ($propostas as $p) {
        if (($p['total'] ?? 0) <= 0) continue;
        if ($melhor_oferta === null || $p['total'] < $melhor_oferta) { $melhor_oferta = (float)$p['total']; $fornecedor_destaque = $p['fornecedor_nome']; }
    }
    return ['melhor_por_item'=>$melhor, 'melhor_total'=>round($melhor_total, 2),
            'melhor_oferta'=>$melhor_oferta !== null ? round($melhor_oferta, 2) : null,
            'fornecedor_destaque'=>$fornecedor_destaque];
}
function cot_get_full($pdo, $id) {
    $c = $pdo->prepare("SELECT c.*, o.nome AS obra_nome, s.nome AS servico_nome
                        FROM cotacao c LEFT JOIN obra o ON o.id=c.obra_id LEFT JOIN servico s ON s.id=c.servico_id
                        WHERE c.id=?");
    $c->execute([$id]); $cot = $c->fetch();
    if (!$cot) return null;
    // obra: se não há obra do radar vinculada mas a cotação nasceu de uma solicitação, mostra o nome comercial do de-para
    if (empty($cot['obra_nome']) && !empty($cot['solic_coligada'])) {
        $so = $pdo->prepare("SELECT nome_comercial FROM solic_obra WHERE coligada=? AND obra_cod=?");
        $so->execute([$cot['solic_coligada'], (string)($cot['solic_obra_cod'] ?? '')]);
        $nc = (string)$so->fetchColumn(); if ($nc !== '') $cot['obra_nome'] = $nc;
    }
    if (empty($cot['obra_nome']) && !empty($cot['obra_livre'])) $cot['obra_nome'] = $cot['obra_livre'];   // cotação importada (obra por texto)
    $iq = $pdo->prepare("SELECT * FROM cotacao_item WHERE cotacao_id=? ORDER BY ordem, id"); $iq->execute([$id]);
    $itens = $iq->fetchAll();
    // obra por item (cotação MULTI-OBRA): resolve o nome p/ o front agrupar/rotular no mapa
    $obraNomes = []; foreach ($pdo->query("SELECT id, nome FROM obra") as $o) $obraNomes[(int)$o['id']] = $o['nome'];
    $obrasNoItens = [];
    foreach ($itens as &$it) { $oid = (int)($it['obra_id'] ?? 0); $it['obra_nome'] = $oid ? ($obraNomes[$oid] ?? '') : ''; if ($oid) $obrasNoItens[$oid] = $it['obra_nome']; }
    unset($it);
    $cot['multi_obra'] = count($obrasNoItens) > 1;
    $cot['obras_itens'] = $obrasNoItens;
    // FASE 2 — agrupa os itens por COLIGADA (multi-PC): cada coligada tem seu Nº de Pedido de Compra próprio.
    // coligada_cod: 1º do prefixo do colidmov ("27-20628" → 27); senão, pelo nome. num_pedido: da tabela cotacao_pedido.
    $colItens = [];
    foreach ($itens as $it) {
        $col = trim((string)($it['solic_coligada'] ?? '')); if ($col === '') continue;
        if (!isset($colItens[$col])) $colItens[$col] = ['coligada'=>$col, 'coligada_cod'=>null, 'colidmov'=>'', 'n'=>0, 'num_pedido'=>'', 'status'=>'', 'numeros'=>[]];
        $colItens[$col]['n']++;
        $num = trim((string)($it['solic_numero'] ?? ''));   // Nº da SOLICITAÇÃO (SC) — pode haver mais de uma por coligada
        if ($num !== '' && !in_array($num, $colItens[$col]['numeros'], true)) $colItens[$col]['numeros'][] = $num;
        $cm = trim((string)($it['solic_colidmov'] ?? ''));
        if ($cm !== '' && $colItens[$col]['colidmov'] === '') {
            $colItens[$col]['colidmov'] = $cm;
            if (strpos($cm, '-') !== false) { $cc = (int)substr($cm, 0, strpos($cm, '-')); if ($cc > 0) $colItens[$col]['coligada_cod'] = $cc; }
        }
    }
    foreach ($colItens as $cn => &$ci) { if (empty($ci['coligada_cod']) && function_exists('coligada_cod_de_nome')) { $cc = coligada_cod_de_nome($cn); if ($cc > 0) $ci['coligada_cod'] = $cc; } }
    unset($ci);
    // injeta o PC salvo por coligada (cotacao_pedido) — casa por coligada_cod (preferido) ou nome
    try {
        $pp = $pdo->prepare("SELECT * FROM cotacao_pedido WHERE cotacao_id=?"); $pp->execute([$id]);
        foreach ($pp->fetchAll() as $row) {
            foreach ($colItens as $cn => &$ci) {
                if ((!empty($row['coligada_cod']) && (int)$row['coligada_cod'] === (int)$ci['coligada_cod'])
                    || (empty($row['coligada_cod']) && trim((string)$row['coligada']) === $cn)) {
                    $ci['num_pedido'] = (string)$row['num_pedido']; $ci['status'] = (string)($row['status'] ?? ''); break;
                }
            }
            unset($ci);
        }
    } catch (Throwable $e) {}
    $cot['multi_coligada'] = count($colItens) > 1;
    $cot['coligadas_itens'] = array_values($colItens);
    $pq = $pdo->prepare("SELECT * FROM cotacao_proposta WHERE cotacao_id=? ORDER BY (total IS NULL), total, id"); $pq->execute([$id]);
    $propostas = $pq->fetchAll();
    if ($propostas) {
        $ids = implode(',', array_map(fn($p)=>(int)$p['id'], $propostas));
        $piq = $pdo->query("SELECT * FROM cotacao_proposta_item WHERE proposta_id IN ($ids)");
        $byp = [];
        foreach ($piq->fetchAll() as $r) $byp[(int)$r['proposta_id']][(int)$r['cotacao_item_id']] =
            ['preco_unit'=>$r['preco_unit']!==null?(float)$r['preco_unit']:null, 'preco_total'=>$r['preco_total']!==null?(float)$r['preco_total']:null, 'observacao'=>$r['observacao']];
        foreach ($propostas as &$p) { $p['total'] = $p['total']!==null?(float)$p['total']:null; $p['itens'] = $byp[(int)$p['id']] ?? [];
            $p['equaliza'] = !empty($p['equaliza'] ?? '') ? (json_decode($p['equaliza'], true) ?: []) : []; }
        unset($p);
    }
    $anx = $pdo->prepare("SELECT id, proposta_id, fornecedor_id, fornecedor_nome, nome, tamanho, mime, url FROM cotacao_anexo WHERE cotacao_id=? AND (fornecedor_nome IS NULL OR fornecedor_nome<>'__CARTA__') ORDER BY id"); $anx->execute([$id]);
    // fornecedores CONVIDADOS (concorrência) + status respondeu (deriva de proposta com mesmo fornecedor)
    $cf = $pdo->prepare("SELECT cf.*, f.email AS f_email, f.telefone AS f_telefone, f.whatsapp AS f_whatsapp, f.contatos_at AS f_contatos_at
                         FROM cotacao_fornecedor cf LEFT JOIN cot_fornecedor f ON f.id=cf.fornecedor_id WHERE cf.cotacao_id=? ORDER BY cf.fornecedor_nome"); $cf->execute([$id]);
    $convidados = $cf->fetchAll();
    foreach ($convidados as &$c) {
        $resp = null; $cn = strtolower(trim((string)$c['fornecedor_nome']));
        foreach ($propostas as $p) {
            if (($c['fornecedor_id'] && (int)$p['fornecedor_id'] === (int)$c['fornecedor_id'])
                || ($cn !== '' && strtolower(trim((string)$p['fornecedor_nome'])) === $cn)) { $resp = $p; break; }
        }
        $c['respondeu'] = $resp ? 1 : 0; $c['proposta_id'] = $resp['id'] ?? null; $c['proposta_total'] = $resp['total'] ?? null;
        // contatos p/ a conferência (mestre cot_fornecedor quando há vínculo; senão o snapshot do convite)
        $c['email'] = ($c['f_email'] ?? '') !== '' ? $c['f_email'] : ($c['email'] ?? '');
        $c['telefone'] = ($c['f_telefone'] ?? '') !== '' ? $c['f_telefone'] : ($c['telefone'] ?? '');
        $c['whatsapp'] = $c['f_whatsapp'] ?? '';
        $c['contatos_at'] = !empty($c['f_contatos_at']) ? (json_decode($c['f_contatos_at'], true) ?: null) : null;
        unset($c['f_email'], $c['f_telefone'], $c['f_whatsapp'], $c['f_contatos_at']);
    }
    unset($c);
    $ger = $pdo->prepare("SELECT id, titulo, criado_nome, created_at FROM carta_gerada WHERE cotacao_id=? ORDER BY id DESC"); $ger->execute([$id]);
    return ['cotacao'=>$cot, 'itens'=>$itens, 'propostas'=>$propostas, 'anexos'=>$anx->fetchAll(),
            'convidados'=>$convidados, 'mapa'=>cot_mapa($itens, $propostas), 'cartas_geradas'=>$ger->fetchAll()];
}

try {
    $pdo = db();

    // ---------- GET ----------
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['dicionario'])) {   // itens-padrão a cotar do serviço (aprendizado de cotação)
            $sid = (int)$_GET['dicionario'];
            $q = $pdo->prepare("SELECT id, descricao, unidade, nota FROM cot_dicionario WHERE servico_id=? ORDER BY ordem, id"); $q->execute([$sid]);
            $sv = $pdo->prepare("SELECT nome, grupo FROM servico WHERE id=?"); $sv->execute([$sid]); $sv = $sv->fetch();
            echo json_encode(['servico'=>$sv, 'itens'=>$q->fetchAll()], JSON_UNESCAPED_UNICODE); exit;
        }
        if (isset($_GET['id'])) {
            $full = cot_get_full($pdo, (int)$_GET['id']);
            if (!$full) { http_response_code(404); echo json_encode(['error'=>'cotação não encontrada']); exit; }
            echo json_encode($full, JSON_UNESCAPED_UNICODE); exit;
        }
        // lista
        $where = ''; $args = [];
        if (isset($_GET['obra']) && $_GET['obra'] !== '') { $where = 'WHERE c.obra_id=?'; $args[] = (int)$_GET['obra']; }
        $q = $pdo->prepare("SELECT c.id, c.obra_id, c.servico_id, c.titulo, c.categoria, c.tipo_servico, c.verba,
                                   c.num_solicitacao, c.num_pedido,
                                   c.status, c.aprovacao, c.criado_nome, c.created_at, COALESCE(NULLIF(o.nome,''), c.obra_livre) AS obra_nome,
                                   (SELECT COUNT(*) FROM cotacao_item ci WHERE ci.cotacao_id=c.id) AS n_itens,
                                   (SELECT COUNT(*) FROM cotacao_proposta cp WHERE cp.cotacao_id=c.id) AS n_propostas,
                                   (SELECT COUNT(*) FROM cotacao_fornecedor cf WHERE cf.cotacao_id=c.id) AS n_convidados,
                                   (SELECT MIN(cp.total) FROM cotacao_proposta cp WHERE cp.cotacao_id=c.id AND cp.total>0) AS melhor_oferta,
                                   (SELECT COUNT(*) FROM cotacao_email_in ei WHERE ei.cotacao_id=c.id AND ei.status='novo') AS n_inbound_novo
                            FROM cotacao c LEFT JOIN obra o ON o.id=c.obra_id
                            $where ORDER BY c.id DESC LIMIT 500");
        $q->execute($args);
        echo json_encode(['cotacoes'=>$q->fetchAll()], JSON_UNESCAPED_UNICODE); exit;
    }

    // ---------- POST ----------
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $acao = $in['acao'] ?? '';
    $me = $in['me'] ?? null;

    if ($acao === 'criar') {
        $obra = (int)($in['obra_id'] ?? 0);
        // cadastro único: se veio obra_ficha_id, resolve (e PROMOVE ao radar se preciso) o obra_id
        if (!empty($in['obra_ficha_id'])) {
            require_once __DIR__ . '/../includes/obra_registry.php';
            $rid = obra_radar_id($pdo, (int)$in['obra_ficha_id']);
            if ($rid) $obra = $rid;
        }
        $perms = cot_can_edit($pdo, $me, $obra ?: 1);
        if (!$perms) { http_response_code(403); echo json_encode(['error'=>'Sem permissão de edição.']); exit; }
        $titulo = trim((string)($in['titulo'] ?? '')); if ($titulo === '') throw new Exception('título obrigatório');
        $itens = array_values(array_filter((array)($in['itens'] ?? []), fn($i)=>trim((string)($i['descricao'] ?? '')) !== ''));
        if (!$itens) throw new Exception('inclua ao menos um item a cotar');
        $now = date('c');
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO cotacao (obra_id, servico_id, titulo, categoria, tipo_servico, verba, verba_origem, descricao, equalizacao, num_solicitacao, num_pedido, status, aprovacao, criado_por, criado_nome, created_at, updated_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?, 'aberta', 'aguardando', ?,?,?,?)")
            ->execute([$obra ?: null, ($in['servico_id'] ?? null) ?: null, $titulo, trim((string)($in['categoria'] ?? '')),
                       trim((string)($in['tipo_servico'] ?? '')), (float)($in['verba'] ?? 0) ?: null, trim((string)($in['verba_origem'] ?? '')),
                       trim((string)($in['descricao'] ?? '')), trim((string)($in['equalizacao'] ?? '')),
                       trim((string)($in['num_solicitacao'] ?? '')) ?: null, trim((string)($in['num_pedido'] ?? '')) ?: null,
                       $me, $perms['nome'] ?? null, $now, $now]);
        $cid = (int)$pdo->lastInsertId();
        $insI = $pdo->prepare("INSERT INTO cotacao_item (cotacao_id, descricao, unidade, quantidade, observacao, ordem) VALUES (?,?,?,?,?,?)");
        $o = 0;
        foreach ($itens as $it) $insI->execute([$cid, trim((string)$it['descricao']), trim((string)($it['unidade'] ?? '')),
                        ($it['quantidade'] ?? null) !== null && $it['quantidade'] !== '' ? (float)$it['quantidade'] : null,
                        trim((string)($it['observacao'] ?? '')), $o++]);
        cot_insert_convidados($pdo, $cid, $in['convidados'] ?? []);   // fornecedores convidados p/ a concorrência
        $pdo->commit();
        echo json_encode(['ok'=>true, 'id'=>$cid], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'set_obra') {   // define/corrige a obra de uma cotação (cadastro único: obra_ficha_id → promove)
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só admin ou quem criou a cotação pode mudar a obra.']); exit; }
        $obra = 0;
        if (!empty($in['obra_ficha_id'])) $obra = (int)obra_radar_id($pdo, (int)$in['obra_ficha_id']);
        elseif (isset($in['obra_id'])) $obra = (int)$in['obra_id'];   // 0 = limpar
        $pdo->prepare("UPDATE cotacao SET obra_id=?, updated_at=? WHERE id=?")->execute([$obra ?: null, date('c'), $cid]);
        echo json_encode(['ok'=>true, 'cotacao_id'=>$cid, 'obra_id'=>$obra ?: null], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'reprocessar_obras') {   // ADMIN: preenche a obra das cotações antigas sem obra, pela solicitação vinculada
        $perms = user_perms($pdo, $me);
        if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Apenas administradores.']); exit; }
        $rows = $pdo->query("SELECT id, solic_coligada, solic_obra_cod FROM cotacao WHERE (obra_id IS NULL OR obra_id=0) AND solic_coligada IS NOT NULL AND solic_coligada<>''")->fetchAll();
        $ok = 0; $skip = 0;
        foreach ($rows as $r) {
            $rid = obra_radar_de_solicitacao($pdo, (string)$r['solic_coligada'], (string)$r['solic_obra_cod']);
            if ($rid) { $pdo->prepare("UPDATE cotacao SET obra_id=?, updated_at=? WHERE id=?")->execute([$rid, date('c'), (int)$r['id']]); $ok++; }
            else $skip++;
        }
        echo json_encode(['ok'=>true, 'resolvidas'=>$ok, 'nao_resolvidas'=>$skip, 'total'=>count($rows)], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'convidar') {   // adiciona fornecedores convidados a uma cotação existente
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
        if (!cot_can_edit($pdo, $me, $obra ?: 1)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão de edição.']); exit; }
        $pdo->beginTransaction();
        $n = cot_insert_convidados($pdo, $cid, $in['convidados'] ?? $in['fornecedores'] ?? []);
        $pdo->commit();
        echo json_encode(['ok'=>true, 'n'=>$n], JSON_UNESCAPED_UNICODE); exit;
    }
    if ($acao === 'desconvidar') {
        $id = (int)($in['id'] ?? 0); if (!$id) throw new Exception('id obrigatório');
        $row = $pdo->prepare("SELECT c.obra_id FROM cotacao_fornecedor cf JOIN cotacao c ON c.id=cf.cotacao_id WHERE cf.id=?"); $row->execute([$id]);
        $obra = (int)($row->fetchColumn() ?: 1);
        if (!cot_can_edit($pdo, $me, $obra ?: 1)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão.']); exit; }
        $pdo->prepare("DELETE FROM cotacao_fornecedor WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'verba_salvar') {   // edita/puxa a verba prevista da cotação
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
        if (!cot_can_edit($pdo, $me, $obra ?: 1)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão de edição.']); exit; }
        $verba = (float)($in['verba'] ?? 0);
        $pdo->prepare("UPDATE cotacao SET verba=?, verba_origem=?, updated_at=? WHERE id=?")
            ->execute([$verba ?: null, trim((string)($in['verba_origem'] ?? 'manual')), date('c'), $cid]);
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'numeros_salvar') {   // nº da SOLICITAÇÃO de compra (SC) e/ou nº do PEDIDO de compra (PC)
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
        if (!cot_can_edit($pdo, $me, $obra ?: 1)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão de edição.']); exit; }
        $sets = []; $args = [];
        if (array_key_exists('num_solicitacao', $in)) { $sets[] = 'num_solicitacao=?'; $args[] = trim((string)$in['num_solicitacao']) ?: null; }
        if (array_key_exists('num_pedido', $in))      { $sets[] = 'num_pedido=?';      $args[] = trim((string)$in['num_pedido']) ?: null; }
        if (!$sets) throw new Exception('nada a atualizar');
        $sets[] = 'updated_at=?'; $args[] = date('c'); $args[] = $cid;
        $pdo->prepare("UPDATE cotacao SET " . implode(',', $sets) . " WHERE id=?")->execute($args);
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'pedido_coligada_salvar') {   // FASE 2 — Nº do PEDIDO de compra de UMA coligada (multi-PC por coligada)
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
        if (!cot_can_edit($pdo, $me, $obra ?: 1)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão de edição.']); exit; }
        $col = trim((string)($in['coligada'] ?? '')); $cod = (int)($in['coligada_cod'] ?? 0);
        $cm  = trim((string)($in['colidmov'] ?? '')); $pc = trim((string)($in['num_pedido'] ?? ''));
        if ($col === '' && $cod === 0) throw new Exception('coligada obrigatória');
        $now = date('c');
        // upsert por (cotacao_id, coligada_cod) — ou por nome, quando não há código
        $sel = $cod ? $pdo->prepare("SELECT id FROM cotacao_pedido WHERE cotacao_id=? AND coligada_cod=?")
                    : $pdo->prepare("SELECT id FROM cotacao_pedido WHERE cotacao_id=? AND coligada=?");
        $sel->execute($cod ? [$cid, $cod] : [$cid, $col]);
        $rid = (int)($sel->fetchColumn() ?: 0);
        if ($rid) $pdo->prepare("UPDATE cotacao_pedido SET coligada=?, coligada_cod=?, colidmov=?, num_pedido=?, updated_by=?, updated_at=? WHERE id=?")
                        ->execute([$col, $cod ?: null, $cm ?: null, $pc ?: null, $me, $now, $rid]);
        else $pdo->prepare("INSERT INTO cotacao_pedido (cotacao_id, coligada, coligada_cod, colidmov, num_pedido, updated_by, updated_at) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$cid, $col, $cod ?: null, $cm ?: null, $pc ?: null, $me, $now]);
        // denormaliza p/ o campo header num_pedido (usado na LISTA de cotações): junta os PCs distintos
        $all = $pdo->prepare("SELECT num_pedido FROM cotacao_pedido WHERE cotacao_id=? AND num_pedido IS NOT NULL AND num_pedido<>''"); $all->execute([$cid]);
        $nums = array_values(array_unique(array_filter(array_map(fn($x)=>trim((string)$x), $all->fetchAll(PDO::FETCH_COLUMN)))));
        $pdo->prepare("UPDATE cotacao SET num_pedido=?, updated_at=? WHERE id=?")->execute([$nums ? implode(', ', $nums) : null, $now, $cid]);
        echo json_encode(['ok'=>true, 'num_pedido_resumo'=>implode(', ', $nums)], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'equaliza_salvar') {   // pontos de equalização da cotação e/ou os valores por proposta
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
        if (!cot_can_edit($pdo, $me, $obra ?: 1)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão de edição.']); exit; }
        // 1) lista de pontos a conferir (texto livre, 1 por linha) — no header da cotação
        if (array_key_exists('equalizacao', $in)) {
            $pdo->prepare("UPDATE cotacao SET equalizacao=?, updated_at=? WHERE id=?")->execute([trim((string)$in['equalizacao']), date('c'), $cid]);
        }
        // 2) valores da equalização de UMA proposta (JSON ponto->valor) — valida que a proposta é desta cotação
        if (!empty($in['proposta_id'])) {
            $pid = (int)$in['proposta_id'];
            $ok = $pdo->prepare("SELECT 1 FROM cotacao_proposta WHERE id=? AND cotacao_id=?"); $ok->execute([$pid, $cid]);
            if (!$ok->fetch()) throw new Exception('proposta não pertence a esta cotação');
            $val = isset($in['equaliza']) ? json_encode((object)$in['equaliza'], JSON_UNESCAPED_UNICODE) : null;
            $pdo->prepare("UPDATE cotacao_proposta SET equaliza=? WHERE id=?")->execute([$val, $pid]);
        }
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'dicionario_salvar') {   // grava os itens-padrão a cotar do serviço (template global)
        $perms = user_perms($pdo, $me);
        if (empty($perms['perm_admin']) && ($perms['editar_escopo'] ?? '') !== 'todas') { http_response_code(403); echo json_encode(['error'=>'Dicionário de cotação é mudança global — só admin ou quem edita todas as obras.']); exit; }
        $sid = (int)($in['servico_id'] ?? 0); if (!$sid) throw new Exception('servico_id obrigatório');
        $itens = array_values(array_filter((array)($in['itens'] ?? []), fn($i)=>trim((string)($i['descricao'] ?? '')) !== ''));
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM cot_dicionario WHERE servico_id=?")->execute([$sid]);
        $insD = $pdo->prepare("INSERT INTO cot_dicionario (servico_id, descricao, unidade, ordem, nota, created_at) VALUES (?,?,?,?,?,?)");
        $o = 0; $now = date('c');
        foreach ($itens as $it) $insD->execute([$sid, trim((string)$it['descricao']), trim((string)($it['unidade'] ?? '')), $o++, trim((string)($it['nota'] ?? '')), $now]);
        $pdo->commit();
        echo json_encode(['ok'=>true, 'n'=>count($itens)], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'proposta') {
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
        if (!cot_can_edit($pdo, $me, $obra ?: 1)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão de edição.']); exit; }
        $forn = trim((string)($in['fornecedor_nome'] ?? '')); if ($forn === '') throw new Exception('fornecedor obrigatório');
        $itens = (array)($in['itens'] ?? []);
        $total = 0.0;
        foreach ($itens as $it) $total += (float)($it['preco_total'] ?? 0);
        $now = date('c');
        $pdo->beginTransaction();
        $pid = (int)($in['proposta_id'] ?? 0);
        if ($pid) {
            $pdo->prepare("UPDATE cotacao_proposta SET fornecedor_id=?, fornecedor_nome=?, prazo=?, observacoes=?, total=? WHERE id=? AND cotacao_id=?")
                ->execute([($in['fornecedor_id'] ?? null) ?: null, $forn, trim((string)($in['prazo'] ?? '')), trim((string)($in['observacoes'] ?? '')), $total ?: null, $pid, $cid]);
            $pdo->prepare("DELETE FROM cotacao_proposta_item WHERE proposta_id=?")->execute([$pid]);
        } else {
            $pdo->prepare("INSERT INTO cotacao_proposta (cotacao_id, fornecedor_id, fornecedor_nome, prazo, observacoes, data_resposta, total, created_at) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$cid, ($in['fornecedor_id'] ?? null) ?: null, $forn, trim((string)($in['prazo'] ?? '')), trim((string)($in['observacoes'] ?? '')), $now, $total ?: null, $now]);
            $pid = (int)$pdo->lastInsertId();
        }
        $insPI = $pdo->prepare("INSERT INTO cotacao_proposta_item (proposta_id, cotacao_item_id, preco_unit, preco_total, observacao) VALUES (?,?,?,?,?)");
        foreach ($itens as $it) {
            $ciid = (int)($it['cotacao_item_id'] ?? 0); if (!$ciid) continue;
            $pu = ($it['preco_unit'] ?? '') !== '' ? (float)$it['preco_unit'] : null;
            $pt = ($it['preco_total'] ?? '') !== '' ? (float)$it['preco_total'] : null;
            $insPI->execute([$pid, $ciid, $pu, $pt, trim((string)($it['observacao'] ?? ''))]);
        }
        $pdo->prepare("UPDATE cotacao SET status=CASE WHEN status='aberta' THEN 'aguardando' ELSE status END, updated_at=? WHERE id=?")->execute([$now, $cid]);
        $pdo->commit();
        echo json_encode(['ok'=>true, 'proposta_id'=>$pid, 'total'=>round($total, 2)], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'status') {
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $row = $pdo->prepare("SELECT COALESCE(obra_id,1) AS obra, servico_id, num_pedido FROM cotacao WHERE id=?"); $row->execute([$cid]);
        $cot = $row->fetch(); if (!$cot) throw new Exception('cotação não encontrada');
        $obra = (int)$cot['obra'];
        if (!cot_can_edit($pdo, $me, $obra ?: 1)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão de edição.']); exit; }
        $sets = []; $args = [];
        // se veio o nº do pedido junto, grava e usa ele na trava
        $pc = trim((string)($cot['num_pedido'] ?? ''));
        if (array_key_exists('num_pedido', $in)) { $pc = trim((string)$in['num_pedido']); $sets[] = 'num_pedido=?'; $args[] = $pc ?: null; }
        if (isset($in['status']) && in_array($in['status'], ['aberta','aguardando','finalizada'], true)) {
            // TRAVA: cotação AVULSA (sem vínculo ao radar) só finaliza com nº do PEDIDO DE COMPRA — exceto admin (exceção).
            // FASE 2: se atravessa VÁRIAS coligadas, exige 1 PC POR COLIGADA (cada coligada tem seu pedido).
            if ($in['status'] === 'finalizada' && empty($cot['servico_id'])) {
                $perms = user_perms($pdo, $me);
                if (empty($perms['perm_admin'])) {
                    $need = [];   // coligada => coligada_cod (coligadas presentes nos itens)
                    foreach ($pdo->query("SELECT DISTINCT solic_coligada, solic_colidmov FROM cotacao_item WHERE cotacao_id=$cid AND solic_coligada IS NOT NULL AND solic_coligada<>''") as $r2) {
                        $cn = trim((string)$r2['solic_coligada']); $cm = trim((string)$r2['solic_colidmov']);
                        $cc = (strpos($cm, '-') !== false) ? (int)substr($cm, 0, strpos($cm, '-')) : 0;
                        if (!$cc && function_exists('coligada_cod_de_nome')) $cc = coligada_cod_de_nome($cn);
                        $need[$cn] = $cc;
                    }
                    if (count($need) > 1) {   // MULTI-COLIGADA: cada uma precisa do seu PC (cotacao_pedido)
                        $have = [];
                        foreach ($pdo->query("SELECT coligada, coligada_cod, num_pedido FROM cotacao_pedido WHERE cotacao_id=$cid") as $r3)
                            if (trim((string)$r3['num_pedido']) !== '') { $have['c:'.(int)$r3['coligada_cod']] = 1; $have['n:'.trim((string)$r3['coligada'])] = 1; }
                        $faltam = [];
                        foreach ($need as $cn => $cc) if (!isset($have['c:'.$cc]) && !isset($have['n:'.$cn])) $faltam[] = preg_replace('/\s+(EMPREENDIMENTO|EMPREENDIMENTOS).*/i', '', $cn);
                        if ($faltam) { echo json_encode(['error'=>'Informe o nº do PEDIDO DE COMPRA de cada coligada para finalizar. Faltam: '.implode(', ', $faltam).'.', 'precisa_pedido'=>true, 'multi_coligada'=>true], JSON_UNESCAPED_UNICODE); exit; }
                    } elseif ($pc === '') {   // coligada única (ou avulsa sem origem): mantém a regra do PC único
                        echo json_encode(['error'=>'Informe o nº do PEDIDO DE COMPRA para finalizar esta cotação avulsa (sem vínculo ao radar).', 'precisa_pedido'=>true], JSON_UNESCAPED_UNICODE); exit;
                    }
                }
            }
            $sets[] = 'status=?'; $args[] = $in['status'];
        }
        if (isset($in['aprovacao']) && in_array($in['aprovacao'], ['aguardando','aprovada','reprovada'], true)) { $sets[] = 'aprovacao=?'; $args[] = $in['aprovacao']; }
        if (!$sets) throw new Exception('nada a atualizar');
        $sets[] = 'updated_at=?'; $args[] = date('c'); $args[] = $cid;
        $pdo->prepare("UPDATE cotacao SET " . implode(',', $sets) . " WHERE id=?")->execute($args);
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'itens_salvar') {   // add/editar/excluir itens a cotar (preserva IDs; remove os tirados + suas propostas)
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só o administrador ou quem criou a cotação pode editar os itens.']); exit; }
        $itens = (array)($in['itens'] ?? []); $now = date('c');
        $pdo->beginTransaction();
        $existing = []; foreach ($pdo->query("SELECT id FROM cotacao_item WHERE cotacao_id=$cid") as $r) $existing[(int)$r['id']] = true;
        $keep = []; $o = 0;
        $ins = $pdo->prepare("INSERT INTO cotacao_item (cotacao_id, descricao, unidade, quantidade, observacao, ordem) VALUES (?,?,?,?,?,?)");
        $upd = $pdo->prepare("UPDATE cotacao_item SET descricao=?, unidade=?, quantidade=?, observacao=?, ordem=? WHERE id=? AND cotacao_id=?");
        foreach ($itens as $it) {
            $desc = trim((string)($it['descricao'] ?? '')); if ($desc === '') continue;
            $q = (($it['quantidade'] ?? null) !== null && $it['quantidade'] !== '') ? (float)$it['quantidade'] : null;
            $id = (int)($it['id'] ?? 0);
            if ($id && isset($existing[$id])) { $upd->execute([$desc, trim((string)($it['unidade'] ?? '')), $q, trim((string)($it['observacao'] ?? '')), $o++, $id, $cid]); $keep[$id] = true; }
            else { $ins->execute([$cid, $desc, trim((string)($it['unidade'] ?? '')), $q, trim((string)($it['observacao'] ?? '')), $o++]); }
        }
        foreach ($existing as $id => $_) if (empty($keep[$id])) { $pdo->prepare("DELETE FROM cotacao_proposta_item WHERE cotacao_item_id=?")->execute([$id]); $pdo->prepare("DELETE FROM cotacao_item WHERE id=?")->execute([$id]); }
        $pdo->prepare("UPDATE cotacao SET updated_at=? WHERE id=?")->execute([$now, $cid]);
        $pdo->commit();
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'excluir') {
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só o administrador ou quem criou a cotação pode excluí-la.']); exit; }
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM cotacao_proposta_item WHERE proposta_id IN (SELECT id FROM cotacao_proposta WHERE cotacao_id=$cid)");
        $pdo->prepare("DELETE FROM cotacao_proposta WHERE cotacao_id=?")->execute([$cid]);
        $pdo->prepare("DELETE FROM cotacao_item WHERE cotacao_id=?")->execute([$cid]);
        $pdo->prepare("DELETE FROM cotacao_fornecedor WHERE cotacao_id=?")->execute([$cid]);
        $pdo->prepare("DELETE FROM cotacao_anexo WHERE cotacao_id=?")->execute([$cid]);
        $pdo->prepare("DELETE FROM carta_gerada WHERE cotacao_id=?")->execute([$cid]);
        // desvincula a solicitação de compra que apontava p/ esta cotação (evita "marcação órfã" na fila de Solicitações)
        // e reverte o status automático 'em_cotacao' -> 'pendente' (a solicitação volta a precisar de cotação)
        try { $pdo->prepare("UPDATE solic_overlay SET cotacao_id=NULL, status=CASE WHEN status='em_cotacao' THEN 'pendente' ELSE status END WHERE cotacao_id=?")->execute([$cid]); } catch (Throwable $e) {}
        $pdo->prepare("DELETE FROM cotacao WHERE id=?")->execute([$cid]);
        $pdo->commit();
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'excluir_proposta') {
        $pid = (int)($in['proposta_id'] ?? 0); if (!$pid) throw new Exception('proposta_id obrigatório');
        $row = $pdo->prepare("SELECT c.obra_id FROM cotacao_proposta p JOIN cotacao c ON c.id=p.cotacao_id WHERE p.id=?"); $row->execute([$pid]);
        $obra = (int)($row->fetchColumn() ?: 1);
        if (!cot_can_edit($pdo, $me, $obra ?: 1)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão de edição.']); exit; }
        $pdo->prepare("DELETE FROM cotacao_proposta_item WHERE proposta_id=?")->execute([$pid]);
        $pdo->prepare("DELETE FROM cotacao_proposta WHERE id=?")->execute([$pid]);
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    throw new Exception('ação inválida');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
