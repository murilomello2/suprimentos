<?php
/**
 * WHATSAPP — assistente de cotação. Configuração, kanban, conversa e simulador.
 *
 * GET  ?config&me=            -> config (SEM token/chave) + estado do LLM
 * GET  ?kanban&me=[&q=]       -> conversas agrupadas por estado
 * GET  ?conversa&id=&me=      -> cabeçalho + mensagens
 * GET  ?custos&me=[&dias=]    -> gasto por modelo/provedor + projeção
 * POST {acao:'salvar_cfg'|'salvar_llm'|'testar_llm'|'iniciar'|'responder'|'simular_entrada'
 *              |'parar'|'retomar'|'concluir'|'assumir'|'devolver_ia'}
 *
 * Configuração é de ADMIN (mexe em token e em custo). Usar é de quem cota.
 * O token da Meta e a chave do LLM NUNCA voltam para o navegador — só o "está configurado".
 */
header('Content-Type: application/json; charset=utf-8');
set_time_limit(180);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/whats.php';

function wa_pode_usar($p) {
    return !empty($p['perm_admin']) || in_array(($p['papel'] ?? ''), ['gerente', 'comprador'], true);
}

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'];
    $in = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];
    $me = $method === 'POST' ? ($in['me'] ?? null) : ($_GET['me'] ?? null);
    $perms = user_perms($pdo, $me);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }
    $acao = (string)($in['acao'] ?? '');
    $admin = !empty($perms['perm_admin']);
    $nome = (string)($perms['nome'] ?? '');

    // ─────────────── CONFIGURAÇÃO ───────────────
    if ($method === 'GET' && isset($_GET['config'])) {
        $c = wa_cfg(); $l = llm_cfg();
        $perfis = [];
        foreach (['assistente', 'oraculo', 'extracao', 'padrao'] as $pf) {
            $r = llm_perfil($pf);
            $perfis[$pf] = $r ? ['provedor' => $r['provedor'], 'modelo' => $r['modelo'], 'herdado' => !empty($r['herdado'])] : null;
        }
        echo json_encode(['ok' => true,
            'wa' => ['modo' => wa_modo(), 'numero' => (string)($c['numero'] ?? ''), 'empresa' => (string)($c['empresa'] ?? 'Caprem'),
                     'phone_number_id' => (string)($c['phone_number_id'] ?? ''), 'waba_id' => (string)($c['waba_id'] ?? ''),
                     'token_ok' => trim((string)($c['token'] ?? '')) !== '', 'verify_token' => (string)($c['verify_token'] ?? ''),
                     'limite_dia' => (int)($c['limite_dia'] ?? 250), 'custo_template' => (float)($c['custo_template'] ?? 0),
                     'template_abertura' => (string)($c['template_abertura'] ?? ''), 'template_idioma' => (string)($c['template_idioma'] ?? 'pt_BR'),
                     'horario_ini' => (string)($c['horario_ini'] ?? '08:00'), 'horario_fim' => (string)($c['horario_fim'] ?? '18:00'),
                     'pronto_real' => wa_pronto_real()],
            'llm' => ['perfis' => $perfis, 'chaves_ok' => array_map(fn($v) => trim((string)$v) !== '', (array)($l['chaves'] ?? [])),
                      'catalogo' => llm_catalogo(), 'precos' => (array)($l['precos'] ?? [])],
            'webhook_url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '')
                             . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') . '/wa_webhook.php',
        ], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'salvar_cfg') {
        if (!$admin) { http_response_code(403); echo json_encode(['error' => 'Apenas administradores.']); exit; }
        $c = wa_cfg();
        foreach (['numero', 'empresa', 'phone_number_id', 'waba_id', 'template_abertura', 'template_idioma',
                  'horario_ini', 'horario_fim', 'modo'] as $k)
            if (array_key_exists($k, $in)) $c[$k] = trim((string)$in[$k]);
        if (array_key_exists('limite_dia', $in))     $c['limite_dia'] = max(0, (int)$in['limite_dia']);
        if (array_key_exists('custo_template', $in)) $c['custo_template'] = (float)$in['custo_template'];
        // token só é gravado quando vem preenchido: campo vazio na tela significa "não mexa"
        if (!empty($in['token'])) $c['token'] = trim((string)$in['token']);
        if (empty($c['verify_token'])) $c['verify_token'] = bin2hex(random_bytes(12));   // usado no handshake da Meta
        if (($c['modo'] ?? '') === 'real' && !wa_pronto_real()) {
            echo json_encode(['error' => 'Para ligar o modo real falta o token e o phone_number_id da Meta.'], JSON_UNESCAPED_UNICODE); exit;
        }
        wa_cfg_salvar($c);
        echo json_encode(['ok' => true, 'modo' => wa_modo(), 'verify_token' => $c['verify_token']], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'salvar_llm') {
        if (!$admin) { http_response_code(403); echo json_encode(['error' => 'Apenas administradores.']); exit; }
        $l = llm_cfg();
        if (!empty($in['provedor']) && !empty($in['chave']))            // chave em branco = não mexe
            $l['chaves'][trim((string)$in['provedor'])] = trim((string)$in['chave']);
        if (!empty($in['perfil'])) {
            $pf = trim((string)$in['perfil']);
            $l['perfis'][$pf] = [
                'provedor' => trim((string)($in['perfil_provedor'] ?? '')),
                'modelo' => trim((string)($in['perfil_modelo'] ?? '')),
                'url' => trim((string)($in['perfil_url'] ?? '')),
                'temperatura' => (float)($in['temperatura'] ?? 0.3),
                'max_tokens' => (int)($in['max_tokens'] ?? 1200)];
        }
        // preço digitado à mão p/ modelo fora do catálogo (US$ por milhão de tokens)
        if (!empty($in['preco_chave']))
            $l['precos'][trim((string)$in['preco_chave'])] = ['in' => (float)($in['preco_in'] ?? 0), 'out' => (float)($in['preco_out'] ?? 0)];
        llm_cfg_salvar($l);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'testar_llm') {
        if (!$admin) { http_response_code(403); echo json_encode(['error' => 'Apenas administradores.']); exit; }
        $pf = trim((string)($in['perfil'] ?? 'assistente'));
        $r = llm_chat($pf, [['role' => 'user', 'content' => 'Responda só com: ok']], ['max_tokens' => 20]);
        llm_registrar($pdo, $r, 'teste', $pf);
        echo json_encode(['ok' => !empty($r['ok']), 'erro' => $r['erro'] ?? '', 'texto' => $r['texto'] ?? '',
            'provedor' => $r['provedor'] ?? '', 'modelo' => $r['modelo'] ?? '',
            'tokens' => (int)($r['in'] ?? 0) + (int)($r['out'] ?? 0), 'custo' => $r['custo'] ?? 0,
            'ms' => $r['ms'] ?? 0], JSON_UNESCAPED_UNICODE); exit;
    }

    if (!wa_pode_usar($perms)) { http_response_code(403);
        echo json_encode(['error' => 'A caixa da assistente é de quem cota (comprador, gerente ou admin).'], JSON_UNESCAPED_UNICODE); exit; }

    // ─────────────── INICIAR: cria a conversa de cada fornecedor convidado ───────────────
    if ($acao === 'iniciar') {
        $cid = (int)($in['cotacao_id'] ?? 0);
        if (!$cid) throw new Exception('informe cotacao_id');
        $cot = $pdo->prepare("SELECT c.*, o.nome AS obra_nome FROM cotacao c LEFT JOIN obra o ON o.id=c.obra_id WHERE c.id=? LIMIT 1");
        $cot->execute([$cid]); $cot = $cot->fetch();
        if (!$cot) throw new Exception('cotação não encontrada');

        $alvo = array_map('intval', (array)($in['fornecedores'] ?? []));
        $q = $pdo->prepare("SELECT cf.fornecedor_id, cf.fornecedor_nome, f.wa_e164, f.wa_tipo
                            FROM cotacao_fornecedor cf LEFT JOIN cot_fornecedor f ON f.id=cf.fornecedor_id
                            WHERE cf.cotacao_id=?");
        $q->execute([$cid]);
        $criadas = []; $puladas = [];
        $ins = $pdo->prepare("INSERT INTO wa_conversa (cotacao_id,fornecedor_id,fornecedor_nome,wa_e164,estado,dono,fila_pos,criado_por,criado_nome,created_at,updated_at)
                              VALUES (?,?,?,?,?,'ia',?,?,?,?,?)");
        $ja = $pdo->prepare("SELECT id FROM wa_conversa WHERE cotacao_id=? AND fornecedor_id=? LIMIT 1");
        foreach ($q as $f) {
            $fid = (int)$f['fornecedor_id'];
            if ($alvo && !in_array($fid, $alvo, true)) continue;
            $num = trim((string)$f['wa_e164']);
            if ($num === '' || ($f['wa_tipo'] ?? '') !== 'celular') {
                $puladas[] = ['nome' => $f['fornecedor_nome'], 'motivo' => $num === '' ? 'sem número de celular cadastrado' : 'o número cadastrado é ' . $f['wa_tipo']];
                continue;
            }
            $ja->execute([$cid, $fid]); if ($ja->fetchColumn()) { $puladas[] = ['nome' => $f['fornecedor_nome'], 'motivo' => 'já existe conversa desta cotação com ele']; continue; }
            // UMA thread por número: se ele já está em outra negociação, esta entra na fila
            $ocupado = wa_numero_ocupado($pdo, $num);
            $pos = null;
            if ($ocupado) {
                $c2 = $pdo->prepare("SELECT COALESCE(MAX(fila_pos),0)+1 FROM wa_conversa WHERE wa_e164=? AND estado='em_fila'");
                $c2->execute([$num]); $pos = (int)$c2->fetchColumn();
            }
            $ins->execute([$cid, $fid, (string)$f['fornecedor_nome'], $num, $ocupado ? 'em_fila' : 'aguardando', $pos,
                           (string)$me, $nome, date('c'), date('c')]);
            $convId = (int)$pdo->lastInsertId();
            $criadas[] = ['id' => $convId, 'nome' => $f['fornecedor_nome'], 'numero' => fone_bonito($num), 'fila' => $pos];

            if (!$ocupado) {   // abre agora: primeiro contato é SEMPRE template (janela fechada)
                $conv = wa_conv_get($pdo, $convId);
                $abertura = 'Cotação ' . ($cot['titulo'] ?? '') . ($cot['obra_nome'] ? ' — obra ' . $cot['obra_nome'] : '');
                wa_enviar($pdo, $conv, $abertura, 'ia', 'Assistente', true);
            }
        }
        echo json_encode(['ok' => true, 'criadas' => $criadas, 'puladas' => $puladas, 'modo' => wa_modo()], JSON_UNESCAPED_UNICODE); exit;
    }

    // ─────────────── KANBAN ───────────────
    if ($method === 'GET' && isset($_GET['kanban'])) {
        $w = []; $a = [];
        if (($q = trim((string)($_GET['q'] ?? ''))) !== '') {
            $w[] = "(v.fornecedor_nome LIKE ? OR v.wa_e164 LIKE ? OR c.titulo LIKE ?)";
            $a[] = "%$q%"; $a[] = "%$q%"; $a[] = "%$q%";
        }
        $where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';
        $st = $pdo->prepare("SELECT v.*, c.titulo AS cotacao_titulo, o.nome AS obra_nome
                             FROM wa_conversa v LEFT JOIN cotacao c ON c.id=v.cotacao_id
                             LEFT JOIN obra o ON o.id=c.obra_id $where
                             ORDER BY v.updated_at DESC, v.id DESC");
        $st->execute($a);
        $col = []; foreach (array_keys(wa_estados()) as $e) $col[$e] = [];
        $naoLidas = 0;
        foreach ($st as $r) {
            $e = $r['estado'] ?: 'em_fila';
            if (!isset($col[$e])) $col[$e] = [];
            if ((int)$r['nao_lidas'] > 0) $naoLidas++;
            $col[$e][] = ['id' => (int)$r['id'], 'fornecedor' => $r['fornecedor_nome'],
                'numero' => fone_bonito((string)$r['wa_e164']), 'cotacao_id' => (int)$r['cotacao_id'],
                'cotacao' => $r['cotacao_titulo'], 'obra' => $r['obra_nome'],
                'nao_lidas' => (int)$r['nao_lidas'], 'fila_pos' => $r['fila_pos'] !== null ? (int)$r['fila_pos'] : null,
                'dono' => $r['dono'], 'janela_aberta' => wa_janela_aberta($r),
                'motivo' => $r['motivo_duvida'], 'ultima' => $r['ultima_msg_em'], 'ultima_dir' => $r['ultima_msg_dir']];
        }
        echo json_encode(['ok' => true, 'colunas' => $col, 'rotulos' => wa_estados(),
            'modo' => wa_modo(), 'nao_lidas' => $naoLidas], JSON_UNESCAPED_UNICODE); exit;
    }

    // ─────────────── CONVERSA ───────────────
    if ($method === 'GET' && isset($_GET['conversa'])) {
        $conv = wa_conv_get($pdo, (int)$_GET['conversa']);
        if (!$conv) { http_response_code(404); echo json_encode(['error' => 'conversa não encontrada']); exit; }
        $pdo->prepare("UPDATE wa_conversa SET nao_lidas=0 WHERE id=?")->execute([(int)$conv['id']]);
        $m = $pdo->prepare("SELECT id,direcao,tipo,texto,template_nome,status,erro,autor,autor_nome,custo,quando FROM wa_msg WHERE conversa_id=? ORDER BY id");
        $m->execute([(int)$conv['id']]);
        $cot = $pdo->prepare("SELECT c.titulo, c.id, o.nome AS obra FROM cotacao c LEFT JOIN obra o ON o.id=c.obra_id WHERE c.id=?");
        $cot->execute([(int)$conv['cotacao_id']]); $cot = $cot->fetch() ?: [];
        echo json_encode(['ok' => true,
            'conversa' => ['id' => (int)$conv['id'], 'fornecedor' => $conv['fornecedor_nome'],
                'numero' => fone_bonito((string)$conv['wa_e164']), 'e164' => $conv['wa_e164'],
                'estado' => $conv['estado'], 'dono' => $conv['dono'], 'fila_pos' => $conv['fila_pos'],
                'janela_aberta' => wa_janela_aberta($conv), 'janela_ate' => $conv['janela_ate'],
                'motivo' => $conv['motivo_duvida'], 'resumo' => $conv['resumo'],
                'proposta' => $conv['proposta_json'] ? json_decode($conv['proposta_json'], true) : null,
                'cotacao_id' => (int)$conv['cotacao_id'], 'cotacao' => $cot['titulo'] ?? '', 'obra' => $cot['obra'] ?? ''],
            'mensagens' => $m->fetchAll(), 'modo' => wa_modo()], JSON_UNESCAPED_UNICODE); exit;
    }

    // ─────────────── AÇÕES NA CONVERSA ───────────────
    if (in_array($acao, ['responder', 'simular_entrada', 'parar', 'retomar', 'concluir', 'assumir', 'devolver_ia'], true)) {
        $conv = wa_conv_get($pdo, (int)($in['id'] ?? 0));
        if (!$conv) throw new Exception('conversa não encontrada');
        $agora = date('c');

        if ($acao === 'assumir' || $acao === 'devolver_ia') {
            $novo = $acao === 'assumir' ? 'humano' : 'ia';
            $pdo->prepare("UPDATE wa_conversa SET dono=?, estado=CASE WHEN estado='duvida_ia' THEN 'ativa' ELSE estado END, motivo_duvida=NULL, updated_at=? WHERE id=?")
                ->execute([$novo, $agora, (int)$conv['id']]);
            wa_msg_add($pdo, $conv['id'], 'out', 'sistema',
                $acao === 'assumir' ? ($nome . ' assumiu a conversa — a assistente parou de responder.') : ('Conversa devolvida para a assistente por ' . $nome . '.'),
                'sistema', $nome, ['status' => 'entregue']);
            echo json_encode(['ok' => true, 'dono' => $novo], JSON_UNESCAPED_UNICODE); exit;
        }

        if ($acao === 'parar' || $acao === 'concluir') {
            $est = $acao === 'parar' ? 'parada' : 'concluida';
            $pdo->prepare("UPDATE wa_conversa SET estado=?, updated_at=? WHERE id=?")->execute([$est, $agora, (int)$conv['id']]);
            wa_msg_add($pdo, $conv['id'], 'out', 'sistema', ($acao === 'parar' ? 'Conversa parada por ' : 'Conversa encerrada por ') . $nome, 'sistema', $nome, ['status' => 'entregue']);
            $prox = wa_liberar_numero($pdo, (string)$conv['wa_e164']);   // libera a thread para quem esperava
            echo json_encode(['ok' => true, 'estado' => $est, 'promovida' => $prox ? (int)$prox['id'] : null], JSON_UNESCAPED_UNICODE); exit;
        }

        if ($acao === 'retomar') {
            $pdo->prepare("UPDATE wa_conversa SET estado='ativa', updated_at=? WHERE id=?")->execute([$agora, (int)$conv['id']]);
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE); exit;
        }

        if ($acao === 'responder') {   // um humano escreve na conversa
            $txt = trim((string)($in['texto'] ?? ''));
            if ($txt === '') throw new Exception('mensagem vazia');
            $r = wa_enviar($pdo, $conv, $txt, 'humano', $nome);
            echo json_encode(['ok' => !empty($r['ok']), 'erro' => $r['erro'] ?? '', 'simulado' => !empty($r['simulado'])], JSON_UNESCAPED_UNICODE); exit;
        }

        /* SIMULAR ENTRADA: o Murilo (ou quem estiver testando) escreve COMO SE FOSSE o fornecedor.
           É o que permite testar a assistente ponta a ponta antes de a Meta liberar o número —
           e continua útil depois, para treinar prompt sem incomodar fornecedor de verdade. */
        if ($acao === 'simular_entrada') {
            $txt = trim((string)($in['texto'] ?? ''));
            if ($txt === '') throw new Exception('mensagem vazia');
            wa_registrar_entrada($pdo, $conv, $txt);
            $conv = wa_conv_get($pdo, (int)$conv['id']);
            $resp = null;
            if ($conv['dono'] === 'ia' && !in_array($conv['estado'], ['parada', 'concluida'], true))
                $resp = wa_responder_com_ia($pdo, $conv, $me, $nome);
            echo json_encode(['ok' => true, 'assistente' => $resp], JSON_UNESCAPED_UNICODE); exit;
        }
    }

    // ─────────────── CUSTOS ───────────────
    if ($method === 'GET' && isset($_GET['custos'])) {
        $dias = max(1, (int)($_GET['dias'] ?? 30));
        $desde = date('c', time() - $dias * 86400);
        $q = $pdo->prepare("SELECT provedor, modelo, COUNT(*) n, SUM(tokens_in) ti, SUM(tokens_out) tok,
                                   SUM(custo) c, AVG(ms) ms, SUM(CASE WHEN ok=1 THEN 0 ELSE 1 END) falhas
                            FROM llm_uso WHERE quando >= ? GROUP BY provedor, modelo ORDER BY c DESC");
        $q->execute([$desde]);
        $mod = $q->fetchAll();
        $tpl = $pdo->prepare("SELECT COUNT(*) n, SUM(custo) c FROM wa_msg WHERE tipo='template' AND quando >= ?");
        $tpl->execute([$desde]); $t = $tpl->fetch() ?: ['n' => 0, 'c' => 0];
        $conv = (int)$pdo->query("SELECT COUNT(*) FROM wa_conversa")->fetchColumn();
        echo json_encode(['ok' => true, 'dias' => $dias, 'modelos' => $mod,
            'templates' => ['n' => (int)$t['n'], 'custo' => (float)$t['c'], 'preco_unit' => (float)(wa_cfg()['custo_template'] ?? 0)],
            'conversas' => $conv], JSON_UNESCAPED_UNICODE); exit;
    }

    throw new Exception('ação inválida');

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

/** Roda a assistente e aplica o que ela decidiu. Fica no fim porque é usado só pelo fluxo acima. */
function wa_responder_com_ia($pdo, $conv, $me, $nome) {
    $cot = $pdo->prepare("SELECT c.titulo, o.nome AS obra FROM cotacao c LEFT JOIN obra o ON o.id=c.obra_id WHERE c.id=?");
    $cot->execute([(int)$conv['cotacao_id']]); $cot = $cot->fetch() ?: [];
    $it = $pdo->prepare("SELECT descricao, quantidade, unidade FROM cotacao_item WHERE cotacao_id=? ORDER BY id");
    $it->execute([(int)$conv['cotacao_id']]);
    $ctx = ['empresa' => (string)(wa_cfg()['empresa'] ?? 'Caprem Construtora'),
            'titulo' => $cot['titulo'] ?? '', 'obra' => $cot['obra'] ?? '', 'itens' => $it->fetchAll()];

    $r = wa_assistente_turno($pdo, $conv, $ctx);
    if (!empty($r['erro'])) {
        $pdo->prepare("UPDATE wa_conversa SET estado='duvida_ia', motivo_duvida=?, updated_at=? WHERE id=?")
            ->execute(['A IA falhou: ' . substr($r['erro'], 0, 300), date('c'), (int)$conv['id']]);
        return ['erro' => $r['erro']];
    }
    $msg = trim((string)($r['mensagem'] ?? ''));
    $acao = (string)($r['acao'] ?? 'conversar');

    if (!empty($r['proposta']) || !empty($r['nao_fornece']))
        $pdo->prepare("UPDATE wa_conversa SET proposta_json=?, itens_faltantes=?, updated_at=? WHERE id=?")
            ->execute([json_encode($r['proposta'] ?? null, JSON_UNESCAPED_UNICODE),
                       json_encode($r['nao_fornece'] ?? [], JSON_UNESCAPED_UNICODE), date('c'), (int)$conv['id']]);

    if ($msg !== '') wa_enviar($pdo, $conv, $msg, 'ia', 'Assistente');

    if ($acao === 'chamar_humano') {
        $pdo->prepare("UPDATE wa_conversa SET estado='duvida_ia', motivo_duvida=?, updated_at=? WHERE id=?")
            ->execute([substr((string)($r['motivo'] ?? 'a assistente pediu ajuda'), 0, 380), date('c'), (int)$conv['id']]);
    } elseif ($acao === 'concluir') {
        $pdo->prepare("UPDATE wa_conversa SET estado='concluida', resumo=?, updated_at=? WHERE id=?")
            ->execute([substr((string)($r['motivo'] ?? 'proposta coletada'), 0, 400), date('c'), (int)$conv['id']]);
        wa_liberar_numero($pdo, (string)$conv['wa_e164']);
    }
    return ['mensagem' => $msg, 'acao' => $acao, 'motivo' => $r['motivo'] ?? '',
            'proposta' => $r['proposta'] ?? null, 'nao_fornece' => $r['nao_fornece'] ?? [],
            'custo' => $r['llm']['custo'] ?? 0, 'modelo' => $r['llm']['modelo'] ?? '', 'ms' => $r['llm']['ms'] ?? 0];
}
