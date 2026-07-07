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

function cot_can_edit($pdo, $me, $obra) {
    $perms = user_perms($pdo, $me);
    if (!empty($perms['perm_admin'])) return $perms;
    if (can_edit_obra($perms, max(1, (int)$obra))) return $perms;
    return null;
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
    $iq = $pdo->prepare("SELECT * FROM cotacao_item WHERE cotacao_id=? ORDER BY ordem, id"); $iq->execute([$id]);
    $itens = $iq->fetchAll();
    $pq = $pdo->prepare("SELECT * FROM cotacao_proposta WHERE cotacao_id=? ORDER BY (total IS NULL), total, id"); $pq->execute([$id]);
    $propostas = $pq->fetchAll();
    if ($propostas) {
        $ids = implode(',', array_map(fn($p)=>(int)$p['id'], $propostas));
        $piq = $pdo->query("SELECT * FROM cotacao_proposta_item WHERE proposta_id IN ($ids)");
        $byp = [];
        foreach ($piq->fetchAll() as $r) $byp[(int)$r['proposta_id']][(int)$r['cotacao_item_id']] =
            ['preco_unit'=>$r['preco_unit']!==null?(float)$r['preco_unit']:null, 'preco_total'=>$r['preco_total']!==null?(float)$r['preco_total']:null, 'observacao'=>$r['observacao']];
        foreach ($propostas as &$p) { $p['total'] = $p['total']!==null?(float)$p['total']:null; $p['itens'] = $byp[(int)$p['id']] ?? []; }
        unset($p);
    }
    $anx = $pdo->prepare("SELECT id, proposta_id, nome, tamanho FROM cotacao_anexo WHERE cotacao_id=? ORDER BY id"); $anx->execute([$id]);
    // fornecedores CONVIDADOS (concorrência) + status respondeu (deriva de proposta com mesmo fornecedor)
    $cf = $pdo->prepare("SELECT * FROM cotacao_fornecedor WHERE cotacao_id=? ORDER BY fornecedor_nome"); $cf->execute([$id]);
    $convidados = $cf->fetchAll();
    foreach ($convidados as &$c) {
        $resp = null; $cn = strtolower(trim((string)$c['fornecedor_nome']));
        foreach ($propostas as $p) {
            if (($c['fornecedor_id'] && (int)$p['fornecedor_id'] === (int)$c['fornecedor_id'])
                || ($cn !== '' && strtolower(trim((string)$p['fornecedor_nome'])) === $cn)) { $resp = $p; break; }
        }
        $c['respondeu'] = $resp ? 1 : 0; $c['proposta_id'] = $resp['id'] ?? null; $c['proposta_total'] = $resp['total'] ?? null;
    }
    unset($c);
    return ['cotacao'=>$cot, 'itens'=>$itens, 'propostas'=>$propostas, 'anexos'=>$anx->fetchAll(),
            'convidados'=>$convidados, 'mapa'=>cot_mapa($itens, $propostas)];
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
                                   c.status, c.aprovacao, c.criado_nome, c.created_at, o.nome AS obra_nome,
                                   (SELECT COUNT(*) FROM cotacao_item ci WHERE ci.cotacao_id=c.id) AS n_itens,
                                   (SELECT COUNT(*) FROM cotacao_proposta cp WHERE cp.cotacao_id=c.id) AS n_propostas,
                                   (SELECT MIN(cp.total) FROM cotacao_proposta cp WHERE cp.cotacao_id=c.id AND cp.total>0) AS melhor_oferta
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
        $perms = cot_can_edit($pdo, $me, $obra ?: 1);
        if (!$perms) { http_response_code(403); echo json_encode(['error'=>'Sem permissão de edição.']); exit; }
        $titulo = trim((string)($in['titulo'] ?? '')); if ($titulo === '') throw new Exception('título obrigatório');
        $itens = array_values(array_filter((array)($in['itens'] ?? []), fn($i)=>trim((string)($i['descricao'] ?? '')) !== ''));
        if (!$itens) throw new Exception('inclua ao menos um item a cotar');
        $now = date('c');
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO cotacao (obra_id, servico_id, titulo, categoria, tipo_servico, verba, descricao, status, aprovacao, criado_por, criado_nome, created_at, updated_at)
                       VALUES (?,?,?,?,?,?,?, 'aberta', 'aguardando', ?,?,?,?)")
            ->execute([$obra ?: null, ($in['servico_id'] ?? null) ?: null, $titulo, trim((string)($in['categoria'] ?? '')),
                       trim((string)($in['tipo_servico'] ?? '')), (float)($in['verba'] ?? 0) ?: null, trim((string)($in['descricao'] ?? '')),
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
        $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
        if (!cot_can_edit($pdo, $me, $obra ?: 1)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão de edição.']); exit; }
        $sets = []; $args = [];
        if (isset($in['status']) && in_array($in['status'], ['aberta','aguardando','finalizada'], true)) { $sets[] = 'status=?'; $args[] = $in['status']; }
        if (isset($in['aprovacao']) && in_array($in['aprovacao'], ['aguardando','aprovada','reprovada'], true)) { $sets[] = 'aprovacao=?'; $args[] = $in['aprovacao']; }
        if (!$sets) throw new Exception('nada a atualizar');
        $sets[] = 'updated_at=?'; $args[] = date('c'); $args[] = $cid;
        $pdo->prepare("UPDATE cotacao SET " . implode(',', $sets) . " WHERE id=?")->execute($args);
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'excluir') {
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
        if (!cot_can_edit($pdo, $me, $obra ?: 1)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão de edição.']); exit; }
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM cotacao_proposta_item WHERE proposta_id IN (SELECT id FROM cotacao_proposta WHERE cotacao_id=$cid)");
        $pdo->prepare("DELETE FROM cotacao_proposta WHERE cotacao_id=?")->execute([$cid]);
        $pdo->prepare("DELETE FROM cotacao_item WHERE cotacao_id=?")->execute([$cid]);
        $pdo->prepare("DELETE FROM cotacao_fornecedor WHERE cotacao_id=?")->execute([$cid]);
        $pdo->prepare("DELETE FROM cotacao_anexo WHERE cotacao_id=?")->execute([$cid]);
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
