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

// ---- CRONOGRAMA AO VIVO (Supabase do Planejamento) --------------------------
// O app já lê o Supabase autenticado como usuário de serviço (sb_get passa pela RLS),
// então não precisa de snapshot: puxamos obra_cronogramas ao vivo (cache 30 min).
define('CRONO_OBRAS_TTL', 1800);
define('CRONO_OBRAS_CACHE', __DIR__ . '/../data/.crono_obras.json');

/** Mapa obra_id(Planejamento) -> cronograma ativo. Retorna [ $byId, $fresh(bool) ]. */
function obras_crono_live() {
    if (is_file(CRONO_OBRAS_CACHE) && (time() - filemtime(CRONO_OBRAS_CACHE)) < CRONO_OBRAS_TTL) {
        $d = json_decode(@file_get_contents(CRONO_OBRAS_CACHE), true);
        if (is_array($d)) return [$d, false];
    }
    $byId = [];
    try {
        require_once __DIR__ . '/../includes/supabase.php';
        $rows = sb_get('obra_cronogramas?is_active=eq.true&select=obra_id,project_name,nome,percent_complete,project_start,project_finish,status_date,total_tasks,updated_at&order=updated_at.desc');
        foreach ((array)$rows as $r) {
            $oid = (string)($r['obra_id'] ?? ''); if ($oid === '') continue;
            if (!isset($byId[$oid])) $byId[$oid] = $r;   // 1º = mais recente (order desc)
        }
        if ($byId) @file_put_contents(CRONO_OBRAS_CACHE, json_encode($byId));
    } catch (Throwable $e) {
        return [[], false];   // Supabase fora do ar: cai no snapshot já gravado no banco
    }
    return [$byId, true];
}

/** Casa o NOME de uma obra ao project_name do cronograma por tokens distintivos (>=4 letras). -> obra_id | null */
function obras_crono_match($obraNome, $byId) {
    $toks = array_values(array_filter(explode(' ', ob_norm($obraNome)), fn($t) => strlen($t) >= 4 && !ctype_digit($t)));
    if (!$toks) return null;
    $best = null; $bestScore = 0;
    foreach ($byId as $oid => $r) {
        $ptext = ' ' . ob_norm(((string)($r['project_name'] ?? '')) . ' ' . ((string)($r['nome'] ?? ''))) . ' ';
        $score = 0; foreach ($toks as $t) if (strpos($ptext, ' ' . $t . ' ') !== false) $score++;
        if ($score > 0 && $score > $bestScore) { $bestScore = $score; $best = $oid; }
    }
    return $best;
}

/** Preenche % físico + datas AO VIVO em cada ficha (com fallback no snapshot do banco). */
function obras_aplicar_crono($pdo, &$obras) {
    [$crBy, $fresh] = obras_crono_live();
    if (!$crBy) { foreach ($obras as &$o) { $o['crono_live'] = false; } return; }
    foreach ($obras as &$o) {
        $cr = null; $oid = (string)($o['crono_obra_id'] ?? '');
        if ($oid !== '' && isset($crBy[$oid])) $cr = $crBy[$oid];
        elseif (($m = obras_crono_match((string)($o['nome'] ?? ''), $crBy))) { $oid = $m; $cr = $crBy[$m]; }
        if (!$cr) { $o['crono_live'] = false; continue; }
        $o['pct_fisico']      = $cr['percent_complete'] !== null ? (float)$cr['percent_complete'] : ($o['pct_fisico'] ?? null);
        $o['crono_inicio']    = (string)($cr['project_start']  ?? ($o['crono_inicio']  ?? ''));
        $o['crono_fim']       = (string)($cr['project_finish'] ?? ($o['crono_fim']     ?? ''));
        $o['crono_medicao']   = (string)($cr['status_date']    ?? ($o['crono_medicao'] ?? ''));
        $o['cronograma_nome'] = (string)($cr['project_name']   ?? ($cr['nome'] ?? ($o['cronograma_nome'] ?? '')));
        $o['crono_obra_id']   = $oid;
        $o['crono_live']      = true;
        $o['crono_updated']   = (string)($cr['updated_at'] ?? '');
        if ($fresh) {   // grava o snapshot (fallback fica fresco) + o vínculo por ID — 1x a cada 30 min
            try { $pdo->prepare("UPDATE obra_ficha SET pct_fisico=?, crono_inicio=?, crono_fim=?, crono_medicao=?, cronograma_nome=?, cronograma_at=?, crono_obra_id=? WHERE id=?")
                ->execute([$o['pct_fisico'], $o['crono_inicio'], $o['crono_fim'], $o['crono_medicao'], $o['cronograma_nome'], date('c'), $oid, (int)$o['id']]); } catch (Throwable $e) {}
        }
    }
}

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

    if ($method === 'GET' && isset($_GET['cronogramas'])) {   // lista os cronogramas ativos p/ o admin ligar na mão os ambíguos (VS2/VS4/...)
        [$crBy, ] = obras_crono_live();
        $out = [];
        foreach ($crBy as $oid => $r) $out[] = ['obra_id' => $oid, 'nome' => (string)($r['project_name'] ?? ($r['nome'] ?? '')),
            'pct' => $r['percent_complete'], 'fim' => (string)($r['project_finish'] ?? ''), 'medicao' => (string)($r['status_date'] ?? '')];
        usort($out, fn($a, $b) => strcasecmp($a['nome'], $b['nome']));
        echo json_encode(['ok' => true, 'cronogramas' => $out], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($method === 'GET' && isset($_GET['lista'])) {
        $obras = $pdo->query("SELECT * FROM obra_ficha ORDER BY (status='Finalizada'), nome")->fetchAll();
        // resolve o nome da coligada p/ exibição quando só há o código
        foreach ($obras as &$o) { if (empty($o['coligada_nome']) && !empty($o['coligada_cod'])) $o['coligada_nome'] = coligada_nome($o['coligada_cod']); }
        unset($o);
        obras_aplicar_crono($pdo, $obras);   // % físico + datas AO VIVO (Supabase do Planejamento)
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
                   'torres','pavimentos','subsolos','unidades','tipologias','metodo_construtivo','areas_comuns','padrao','observacoes','link_cronograma','link_projetos','link_local','de_para_ok','radar_obra_id','crono_obra_id'];
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
