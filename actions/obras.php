<?php
/**
 * MÓDULO OBRAS — ficha das obras (características curadas) + DE-PARA entre sistemas
 * (conector/atividades ↔ radar ↔ TOTVS/coligada ↔ solicitações).
 *
 * GET  ?lista&me=..                         -> {obras:[...]}  (todas as fichas)
 * POST {acao:'seed', me, obras:[{conector_id,nome,cidade,estado,status}]}  (ADMIN) semeia/resolve de-para (insere as novas)
 * POST {acao:'salvar', me, ficha:{id,...campos...}}                         (edita a ficha — quem edita a obra/curadoria)
 * POST {acao:'reresolver', me, id}                                         (refaz o de-para automático de UMA obra)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/coligadas.php';

// resolve o DE-PARA de uma obra pelo nome: coligada (TOTVS) + solic_obra (endereço/comprador) + radar
function obra_resolver_depara($pdo, $nome) {
    $out = ['coligada_cod' => null, 'coligada_nome' => '', 'cnpj' => '', 'solic_nome' => '', 'solic_coligada' => '', 'solic_obra_cod' => '', 'endereco' => '', 'comprador_nome' => '', 'radar_obra_id' => null];
    $m = coligada_match_obra($nome);
    if ($m) { $out['coligada_cod'] = (int)$m['cod']; $out['coligada_nome'] = $m['nome']; $out['cnpj'] = $m['cnpj']; }
    // solic_obra: por coligada (nome legal) OU por nome_comercial parecido com a obra
    $n = ob_norm($nome);
    try {
        $so = null;
        if ($out['coligada_nome'] !== '') { $q = $pdo->prepare("SELECT * FROM solic_obra WHERE coligada=? ORDER BY id LIMIT 1"); $q->execute([$out['coligada_nome']]); $so = $q->fetch(); }
        if (!$so && $n !== '') { $q = $pdo->prepare("SELECT * FROM solic_obra WHERE LOWER(nome_comercial) LIKE ? OR LOWER(coligada) LIKE ? ORDER BY id LIMIT 1"); $q->execute(['%' . $n . '%', '%' . $n . '%']); $so = $q->fetch(); }
        if ($so) {
            $out['solic_nome'] = (string)($so['nome_comercial'] ?? ''); $out['solic_coligada'] = (string)($so['coligada'] ?? '');
            $out['solic_obra_cod'] = (string)($so['obra_cod'] ?? ''); $out['endereco'] = (string)($so['endereco'] ?? '');
            $out['comprador_nome'] = (string)($so['comprador_nome'] ?? '');
            if (!empty($so['radar_obra_id'])) $out['radar_obra_id'] = (int)$so['radar_obra_id'];
            if ($out['cnpj'] === '' && !empty($so['cnpj'])) $out['cnpj'] = (string)$so['cnpj'];
        }
    } catch (Throwable $e) {}
    // radar obra por nome
    if (!$out['radar_obra_id']) {
        try { $q = $pdo->prepare("SELECT id FROM obra WHERE LOWER(nome)=? OR LOWER(nome) LIKE ? ORDER BY id LIMIT 1"); $q->execute([$n, '%' . $n . '%']); $rid = $q->fetchColumn(); if ($rid) $out['radar_obra_id'] = (int)$rid; } catch (Throwable $e) {}
    }
    return $out;
}

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'];
    $in = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];
    $me = $method === 'POST' ? ($in['me'] ?? null) : ($_GET['me'] ?? null);
    $perms = user_perms($pdo, $me);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }

    if ($method === 'GET' && isset($_GET['lista'])) {
        $obras = $pdo->query("SELECT * FROM obra_ficha ORDER BY (status='Finalizada'), nome")->fetchAll();
        // resolve o nome da coligada p/ exibição quando só há o código
        foreach ($obras as &$o) { if (empty($o['coligada_nome']) && !empty($o['coligada_cod'])) $o['coligada_nome'] = coligada_nome($o['coligada_cod']); }
        echo json_encode(['ok' => true, 'obras' => $obras, 'is_admin' => !empty($perms['perm_admin'])], JSON_UNESCAPED_UNICODE); exit;
    }

    $acao = $in['acao'] ?? '';

    if ($acao === 'seed') {   // semeia as obras do conector, resolvendo o de-para (só insere as novas; não mexe nas já curadas)
        if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error' => 'Apenas administradores semeiam.']); exit; }
        $lista = $in['obras'] ?? []; $now = date('c'); $novas = 0; $exist = 0;
        $ins = $pdo->prepare("INSERT INTO obra_ficha (slug, nome, cidade, estado, status, conector_obra_id, radar_obra_id, coligada_cod, coligada_nome, cnpj, solic_nome, solic_coligada, solic_obra_cod, endereco, comprador_nome, created_at, updated_at, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $chk = $pdo->prepare("SELECT id FROM obra_ficha WHERE slug=? LIMIT 1");
        foreach ($lista as $ob) {
            $nome = trim((string)($ob['nome'] ?? '')); if ($nome === '') continue;
            $slug = ob_norm($nome); if ($slug === '') continue;
            $chk->execute([$slug]); if ($chk->fetchColumn()) { $exist++; continue; }
            $dp = obra_resolver_depara($pdo, $nome);
            $ins->execute([$slug, $nome, (string)($ob['cidade'] ?? ''), (string)($ob['estado'] ?? ''), (string)($ob['status'] ?? ''),
                (string)($ob['conector_id'] ?? ''), $dp['radar_obra_id'], $dp['coligada_cod'], $dp['coligada_nome'], $dp['cnpj'],
                $dp['solic_nome'], $dp['solic_coligada'], $dp['solic_obra_cod'], $dp['endereco'], $dp['comprador_nome'], $now, $now, (string)$me]);
            $novas++;
        }
        echo json_encode(['ok' => true, 'novas' => $novas, 'ja_existiam' => $exist], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'reresolver') {   // refaz o de-para automático de UMA obra (mantém as características)
        $id = (int)($in['id'] ?? 0); if (!$id) throw new Exception('id obrigatório');
        $q = $pdo->prepare("SELECT nome FROM obra_ficha WHERE id=?"); $q->execute([$id]); $nome = (string)$q->fetchColumn();
        if ($nome === '') { echo json_encode(['error' => 'obra não encontrada']); exit; }
        $dp = obra_resolver_depara($pdo, $nome);
        $pdo->prepare("UPDATE obra_ficha SET radar_obra_id=?, coligada_cod=?, coligada_nome=?, cnpj=?, solic_nome=?, solic_coligada=?, solic_obra_cod=?, endereco=?, comprador_nome=?, updated_at=?, updated_by=? WHERE id=?")
            ->execute([$dp['radar_obra_id'], $dp['coligada_cod'], $dp['coligada_nome'], $dp['cnpj'], $dp['solic_nome'], $dp['solic_coligada'], $dp['solic_obra_cod'], $dp['endereco'], $dp['comprador_nome'], date('c'), (string)$me, $id]);
        echo json_encode(['ok' => true, 'depara' => $dp], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'cronograma') {   // snapshot do cronograma (% físico + datas), casando por NOME (obra_cronogramas tem RLS)
        if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error' => 'Apenas administradores.']); exit; }
        $lista = $in['itens'] ?? []; $now = date('c'); $atualizadas = 0; $nao = [];
        $upd = $pdo->prepare("UPDATE obra_ficha SET pct_fisico=?, crono_inicio=?, crono_fim=?, crono_medicao=?, cronograma_nome=?, cronograma_at=?, updated_at=? WHERE slug=?");
        foreach ($lista as $it) {
            $slug = ob_norm((string)($it['obra'] ?? '')); if ($slug === '') continue;
            $upd->execute([($it['pct_fisico'] ?? null) !== null ? (float)$it['pct_fisico'] : null,
                (string)($it['inicio'] ?? ''), (string)($it['fim'] ?? ''), (string)($it['medicao'] ?? ''),
                (string)($it['cronograma_nome'] ?? ''), $now, $now, $slug]);
            if ($upd->rowCount() > 0) $atualizadas++; else $nao[] = (string)($it['obra'] ?? $slug);
        }
        echo json_encode(['ok' => true, 'atualizadas' => $atualizadas, 'nao_casaram' => $nao], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'salvar') {   // edita a ficha (características + de-para confirmado)
        $f = $in['ficha'] ?? []; $id = (int)($f['id'] ?? 0);
        $now = date('c');
        $campos = ['nome','cidade','estado','status','coligada_cod','coligada_nome','cnpj','solic_nome','solic_coligada','solic_obra_cod','endereco','comprador_nome',
                   'torres','pavimentos','subsolos','unidades','tipologias','metodo_construtivo','areas_comuns','padrao','observacoes','link_cronograma','link_projetos','link_local','de_para_ok','radar_obra_id'];
        $intCampos = ['coligada_cod','torres','pavimentos','subsolos','unidades','de_para_ok','radar_obra_id'];
        $set = []; $vals = [];
        foreach ($campos as $k) { if (array_key_exists($k, $f)) { $set[] = "$k=?"; $v = $f[$k];
            $vals[] = in_array($k, $intCampos, true) ? ($v === '' || $v === null ? null : (int)$v) : (string)$v; } }
        if ($id) {   // update
            if (!$set) { echo json_encode(['ok' => true, 'id' => $id]); exit; }
            $vals[] = $now; $vals[] = (string)$me; $vals[] = $id;
            $pdo->prepare("UPDATE obra_ficha SET " . implode(',', $set) . ", updated_at=?, updated_by=? WHERE id=?")->execute($vals);
            echo json_encode(['ok' => true, 'id' => $id], JSON_UNESCAPED_UNICODE); exit;
        } else {   // insert manual (obra nova sem conector)
            $nome = trim((string)($f['nome'] ?? '')); if ($nome === '') throw new Exception('nome obrigatório');
            $slug = ob_norm($nome) . '-' . substr(md5($nome . $now), 0, 5);
            $pdo->prepare("INSERT INTO obra_ficha (slug, nome, created_at, updated_at, updated_by) VALUES (?,?,?,?,?)")->execute([$slug, $nome, $now, $now, (string)$me]);
            $nid = (int)$pdo->lastInsertId();
            if ($set) { $vals[] = $now; $vals[] = (string)$me; $vals[] = $nid; $pdo->prepare("UPDATE obra_ficha SET " . implode(',', $set) . ", updated_at=?, updated_by=? WHERE id=?")->execute($vals); }
            echo json_encode(['ok' => true, 'id' => $nid], JSON_UNESCAPED_UNICODE); exit;
        }
    }

    echo json_encode(['error' => 'ação inválida'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
