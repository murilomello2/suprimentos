<?php
/**
 * RADAR IA — oráculo de Suprimentos. Proxy SERVIDOR → OpenAI (a chave NUNCA vai ao navegador).
 * A chave/modelo ficam em data/.oracle.json (gitignored, 403 no prod via .htaccess); só admin seta.
 *
 * GET                              -> {configurado, modelo}  (a chave NUNCA é devolvida)
 * POST {acao:'set_key', me, key?, model?}     (ADMIN) grava a chave/modelo
 * POST {acao:'perguntar', me, pergunta, historico?[{role,content}]} -> {resposta}
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

define('ORACLE_CFG_FILE', __DIR__ . '/../data/.oracle.json');
function oracle_cfg() { $j = @json_decode(@file_get_contents(ORACLE_CFG_FILE), true); return is_array($j) ? $j : []; }

function oracle_post($url, $key, $payload) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $ca = ini_get('curl.cainfo'); if ($ca && is_file($ca)) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch);
    curl_close($ch);
    return [$code, $res, $err];
}

function o_num($x) { return ($x !== null && $x !== '') ? (float)$x : null; }
function o_verba($r) { // verba "definitiva" do item (override > material+mo > estimada)
    if (o_num($r['verba_override']) !== null) return o_num($r['verba_override']);
    $m = o_num($r['verba_material']); $o = o_num($r['verba_mo']);
    if ($m !== null || $o !== null) return (float)$m + (float)$o;
    return o_num($r['verba_estim']);
}

// Monta o CONTEXTO (dados do cockpit) que a IA vai enxergar — filtra/destaca pelo usuário logado.
function oracle_contexto($pdo, $perms) {
    $nome = trim((string)($perms['nome'] ?? ''));
    $obras = []; foreach ($pdo->query("SELECT id, nome FROM obra ORDER BY id")->fetchAll() as $o) $obras[(int)$o['id']] = $o['nome'];
    $rows = $pdo->query("SELECT r.obra_id, r.status, r.responsavel, r.fornecedor, r.inicio_cotacao, r.fim_cotacao,
                r.verba_override, r.verba_material, r.verba_mo, r.verba_estim,
                r.quantitativo_valor, r.quantitativo_unidade, s.nome AS item, s.grupo, s.curva
              FROM radar_item r JOIN servico s ON s.id = r.servico_id")->fetchAll();
    $hoje = date('Y-m-d'); $lim = date('Y-m-d', strtotime('+90 days'));
    $meus = []; $prazos = []; $porObra = []; $porStatus = []; $porResp = []; $verbaAberta = 0.0;
    foreach ($rows as $r) {
        $on = $obras[(int)$r['obra_id']] ?? ('obra ' . $r['obra_id']);
        $v = o_verba($r); $st = $r['status'] ?: 'Não Iniciado'; $resp = trim((string)$r['responsavel']);
        $fim = $r['fim_cotacao'] ?: '';
        $porObra[$on] = ($porObra[$on] ?? 0) + 1;
        $porStatus[$st] = ($porStatus[$st] ?? 0) + 1;
        if ($resp !== '') $porResp[$resp] = ($porResp[$resp] ?? 0) + 1;
        if ($st !== 'Finalizado' && $v !== null) $verbaAberta += (float)$v;
        $rec = ['item'=>$r['item'], 'obra'=>$on, 'grupo'=>$r['grupo'], 'curva'=>$r['curva'], 'status'=>$st,
                'responsavel'=>$resp ?: null, 'fornecedor'=>$r['fornecedor'] ?: null,
                'verba'=>$v !== null ? round($v) : null,
                'inicio_cotacao'=>$r['inicio_cotacao'] ?: null, 'fim_cotacao'=>$fim ?: null];
        if ($nome !== '' && strcasecmp($resp, $nome) === 0) $meus[] = $rec;
        if ($fim && $fim >= $hoje && $fim <= $lim && $st !== 'Finalizado') $prazos[] = $rec;
    }
    usort($prazos, function($a, $b) { return strcmp((string)$a['fim_cotacao'], (string)$b['fim_cotacao']); });
    $cots = $pdo->query("SELECT c.id, c.titulo, c.categoria, c.tipo_servico, c.status, c.verba, c.created_at, o.nome AS obra,
                (SELECT COUNT(*) FROM cotacao_proposta cp WHERE cp.cotacao_id=c.id) AS propostas,
                (SELECT COUNT(*) FROM cotacao_fornecedor cf WHERE cf.cotacao_id=c.id) AS convidados,
                (SELECT MIN(cp.total) FROM cotacao_proposta cp WHERE cp.cotacao_id=c.id AND cp.total>0) AS melhor
              FROM cotacao c LEFT JOIN obra o ON o.id=c.obra_id ORDER BY c.id DESC LIMIT 60")->fetchAll();
    $cotacoes = array_map(function($c) {
        return ['titulo'=>$c['titulo'], 'obra'=>$c['obra'], 'categoria'=>$c['categoria'], 'tipo'=>$c['tipo_servico'],
                'status'=>$c['status'], 'verba'=>o_num($c['verba']), 'propostas'=>(int)$c['propostas'],
                'convidados'=>(int)$c['convidados'], 'melhor_oferta'=>o_num($c['melhor']),
                'criada'=>substr((string)$c['created_at'], 0, 10)];
    }, $cots);
    return [
        'usuario_logado' => $nome ?: '(desconhecido)',
        'is_admin' => !empty($perms['perm_admin']),
        'hoje' => $hoje,
        'obras' => array_values($obras),
        'resumo' => ['itens_por_obra'=>$porObra, 'itens_por_status'=>$porStatus, 'itens_por_responsavel'=>$porResp,
                     'verba_total_em_aberto'=>round($verbaAberta)],
        'minhas_aquisicoes' => array_slice($meus, 0, 60),
        'prazos_de_cotacao_proximos_90d' => array_slice($prazos, 0, 50),
        'cotacoes' => $cotacoes,
    ];
}

// Prompt-base PADRÃO — ensina o sistema à IA (editável no admin; sobrescreve isto quando setado).
function oracle_default_prompt() {
    return <<<TXT
Você é o RADAR IA, o oráculo estratégico de Suprimentos da Caprem Construtora, dentro do "Cockpit de Suprimentos". Você ajuda compradores e gestores a se programarem e a decidir, analisando aquisições, cotações, prazos, verbas e oportunidades das obras.

=== COMO O SISTEMA FUNCIONA (para você orientar a pessoa ONDE ver/conferir cada coisa) ===
Módulos (menu à esquerda):
• RADAR DE AQUISIÇÕES — a lista de serviços/itens a comprar por obra. Cada item tem: Curva (A/B/C = importância pelo valor), Responsável (comprador), Verba (R\$), Quantitativo, Data em obra (vinda do cronograma), Início e Fim da cotação, Status e Mapa (se já existe cotação). Clicar no item abre o MODAL com abas.
• MATRIZ — visão serviços × obras (a cor da célula = status; a data no centro = FIM DA COTAÇÃO). Dá pra expandir cada serviço e ver quantitativo/verba/responsável/status/fornecedor por obra.
• MAPA DE COTAÇÕES — monta a concorrência: itens a cotar → convidar fornecedores → receber propostas → mapa comparativo (melhor preço por item) → equalização.
• OPORTUNIDADES (Curva ABC) — grandes itens do orçamento que o radar ainda NÃO cobre (potenciais contratações futuras).
• DASHBOARDS — indicadores. • RADAR IA — este chat.

No MODAL do item (clique no item do Radar ou numa célula da Matriz) as abas são:
• Resumo — responsável, status, fornecedor, observações, lead.
• Cronograma — o VÍNCULO com a tarefa-âncora do cronograma vivo. É AQUI que se define/confere a "data necessária em obra" e o gatilho da cotação. (Pergunta "onde vejo o cronograma?" → responda: no modal do item, aba Cronograma.)
• Orçamento — de ONDE vem a VERBA (linhas do orçamento / composição vinculada).
• Quantitativo — a metragem/quantidade a cotar.
• Dicionário — escopo, variáveis a cotar (equalização), lições, documentos por serviço.
• Mapa de cotação — a cotação vinculada àquele item.
• Histórico — quem mudou o quê e quando (auditoria).

CURADORIA (importante): cada dado pode estar CURADO (✓ verde = confirmado manualmente por uma pessoa) ou SUGERIDO PELO AUTO-VÍNCULO (🤖 = a receita preencheu automaticamente e AINDA precisa ser conferido e salvo). Aparece nos selos ao lado da VERBA, do QUANTITATIVO e da DATA (na Matriz há legenda). Se algo estiver 🤖, oriente a pessoa a abrir o item e confirmar. (Pergunta "como confiro a verba?" → responda: abra o item, veja o selo ✓/🤖 ao lado da verba; na aba Orçamento vê a origem; clicando no 🤖 abre pra confirmar.)

VERBA: o valor "definitivo" do item é a verba curada (override) ou a soma material+MO ou a estimativa. "Verba a definir / R\$ 0" = ainda não definida (sinalize isso).

=== COMO RESPONDER ===
- Use SOMENTE os dados do JSON do cockpit fornecido abaixo. Se a info não estiver lá, diga que não tem esse dado no seu contexto e ORIENTE onde a pessoa acha no sistema (menu/aba).
- NUNCA invente números, datas, fornecedores ou valores.
- Português do Brasil, claro e ACIONÁVEL. Markdown (títulos, listas, negrito, tabelas quando ajudar). Valores em R\$ (formato brasileiro), datas dd/mm/aaaa.
- "Minha programação/minhas cotações" → use minhas_aquisicoes e prazos_de_cotacao_proximos_90d (o usuário logado está em usuario_logado).
- Destaque o URGENTE (prazos vencendo, cotações sem proposta, verbas 🤖 não conferidas) e as OPORTUNIDADES.
- Quando fizer sentido, diga ONDE no sistema a pessoa vê/edita aquilo (ex.: "confira no modal do item → aba Cronograma"), pra ela agir.
- Termine, quando útil, com 1-3 próximos passos sugeridos.
TXT;
}
function oracle_persona($cfg) {
    $p = trim((string)($cfg['prompt'] ?? ''));
    return $p !== '' ? $p : oracle_default_prompt();
}

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $cfg = oracle_cfg();
        echo json_encode(['configurado'=>!empty($cfg['key']), 'modelo'=>$cfg['model'] ?? 'gpt-4o',
            'limite_dia'=>(int)($cfg['limit_dia'] ?? 2),
            'prompt'=>oracle_persona($cfg), 'prompt_custom'=>trim((string)($cfg['prompt'] ?? '')) !== '',
            'prompt_padrao'=>oracle_default_prompt()], JSON_UNESCAPED_UNICODE); exit;
    }

    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $acao = $in['acao'] ?? 'perguntar';
    $perms = user_perms($pdo, $in['me'] ?? null);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error'=>'Não autorizado.']); exit; }

    if ($acao === 'set_key') {
        if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Apenas administradores.']); exit; }
        $cfg = oracle_cfg();
        if (isset($in['key'])   && trim((string)$in['key'])   !== '') $cfg['key']   = trim((string)$in['key']);
        if (isset($in['model']) && trim((string)$in['model']) !== '') $cfg['model'] = trim((string)$in['model']);
        if (array_key_exists('prompt', $in))    { $p = trim((string)$in['prompt']); if ($p === '') unset($cfg['prompt']); else $cfg['prompt'] = $p; } // vazio = volta ao padrão
        if (array_key_exists('limit_dia', $in)) $cfg['limit_dia'] = max(0, (int)$in['limit_dia']);
        @file_put_contents(ORACLE_CFG_FILE, json_encode($cfg));
        @chmod(ORACLE_CFG_FILE, 0600);
        echo json_encode(['ok'=>true, 'configurado'=>!empty($cfg['key']), 'modelo'=>$cfg['model'] ?? 'gpt-4o',
            'limite_dia'=>(int)($cfg['limit_dia'] ?? 2)], JSON_UNESCAPED_UNICODE); exit;
    }

    // ---- perguntar ----
    $cfg = oracle_cfg(); $key = $cfg['key'] ?? ''; $model = $cfg['model'] ?? 'gpt-4o';
    if (!$key) { echo json_encode(['error'=>'O Radar IA ainda não foi configurado — falta a chave da OpenAI (peça a um admin em Configurações).']); exit; }
    $pergunta = trim((string)($in['pergunta'] ?? ''));
    if ($pergunta === '') { echo json_encode(['error'=>'Faça uma pergunta.']); exit; }

    // LIMITE de perguntas por dia (editável no admin; admin não conta)
    $hoje = date('Y-m-d'); $limite = (int)($cfg['limit_dia'] ?? 2);
    $isAdmin = !empty($perms['perm_admin']); $bid = trim((string)($in['me'] ?? ''));
    $usadas = 0;
    if (!$isAdmin && $limite > 0) {
        $q = $pdo->prepare("SELECT n FROM oracle_uso WHERE bitrix_id=? AND dia=?"); $q->execute([$bid, $hoje]);
        $usadas = (int)($q->fetchColumn() ?: 0);
        if ($usadas >= $limite) { echo json_encode(['error'=>"Você atingiu o limite de $limite pergunta(s) por dia ao Radar IA. Tente novamente amanhã.", 'usadas'=>$usadas, 'limite'=>$limite, 'limite_atingido'=>true], JSON_UNESCAPED_UNICODE); exit; }
    }

    $ctx = oracle_contexto($pdo, $perms);
    $sys = oracle_persona($cfg) . "\n\n=== DADOS DO COCKPIT (JSON — use só isto) ===\n" . json_encode($ctx, JSON_UNESCAPED_UNICODE);

    $messages = [['role'=>'system', 'content'=>$sys]];
    foreach ((array)($in['historico'] ?? []) as $h) {
        $role = (($h['role'] ?? '') === 'assistant') ? 'assistant' : 'user';
        $c = substr((string)($h['content'] ?? ''), 0, 4000);
        if ($c !== '') $messages[] = ['role'=>$role, 'content'=>$c];
    }
    $messages[] = ['role'=>'user', 'content'=>$pergunta];

    [$code, $res, $err] = oracle_post('https://api.openai.com/v1/chat/completions', $key,
        ['model'=>$model, 'messages'=>$messages, 'temperature'=>0.3, 'max_tokens'=>1400]);
    if ($code !== 200) {
        $msg = 'Falha ao consultar a IA (HTTP ' . $code . ')';
        $ej = json_decode((string)$res, true);
        if (!empty($ej['error']['message'])) $msg .= ': ' . $ej['error']['message'];
        elseif ($err) $msg .= ': ' . $err;
        echo json_encode(['error'=>$msg]); exit;
    }
    $j = json_decode($res, true);
    $resposta = $j['choices'][0]['message']['content'] ?? '(a IA não retornou resposta)';
    // conta 1 uso do dia (admin não conta) — só depois da resposta OK
    if (!$isAdmin && $limite > 0) {
        $up = $pdo->prepare("UPDATE oracle_uso SET n=n+1 WHERE bitrix_id=? AND dia=?"); $up->execute([$bid, $hoje]);
        if ($up->rowCount() === 0) {
            try { $pdo->prepare("INSERT INTO oracle_uso (bitrix_id, dia, n) VALUES (?,?,1)")->execute([$bid, $hoje]); }
            catch (Throwable $e) { $pdo->prepare("UPDATE oracle_uso SET n=n+1 WHERE bitrix_id=? AND dia=?")->execute([$bid, $hoje]); }
        }
        $usadas++;
    }
    echo json_encode(['resposta'=>$resposta, 'modelo'=>$model, 'usadas'=>$usadas, 'limite'=>$limite, 'ilimitado'=>$isAdmin], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
