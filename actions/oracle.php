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

function oracle_persona() {
    return "Você é o RADAR IA, o oráculo estratégico de Suprimentos da Caprem Construtora. "
      . "Você ajuda compradores e gestores a se programarem: analisa aquisições, cotações, prazos e oportunidades das obras.\n"
      . "REGRAS:\n"
      . "- Responda SEMPRE em português do Brasil, claro, objetivo e ACIONÁVEL, usando markdown (títulos, listas, negrito, tabelas quando ajudar).\n"
      . "- Baseie-se EXCLUSIVAMENTE nos dados JSON fornecidos. Se algo não está nos dados, diga que não tem essa informação — NUNCA invente números, prazos, fornecedores ou valores.\n"
      . "- Valores em R\$ (formato brasileiro). Datas em dd/mm/aaaa.\n"
      . "- Quando perguntarem sobre 'minha programação' / 'minhas cotações', use 'minhas_aquisicoes' e 'prazos_de_cotacao_proximos_90d' (o usuário logado está em 'usuario_logado').\n"
      . "- Destaque o URGENTE (prazos vencendo, cotações sem proposta) e as OPORTUNIDADES (muitas contratações à frente, verba alta a definir).\n"
      . "- Seja conciso mas completo. Se útil, termine com 1-3 próximos passos sugeridos.";
}

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $cfg = oracle_cfg();
        echo json_encode(['configurado'=>!empty($cfg['key']), 'modelo'=>$cfg['model'] ?? 'gpt-4o']); exit;
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
        @file_put_contents(ORACLE_CFG_FILE, json_encode($cfg));
        @chmod(ORACLE_CFG_FILE, 0600);
        echo json_encode(['ok'=>true, 'configurado'=>!empty($cfg['key']), 'modelo'=>$cfg['model'] ?? 'gpt-4o']); exit;
    }

    // ---- perguntar ----
    $cfg = oracle_cfg(); $key = $cfg['key'] ?? ''; $model = $cfg['model'] ?? 'gpt-4o';
    if (!$key) { echo json_encode(['error'=>'O Radar IA ainda não foi configurado — falta a chave da OpenAI (peça a um admin em Configurações).']); exit; }
    $pergunta = trim((string)($in['pergunta'] ?? ''));
    if ($pergunta === '') { echo json_encode(['error'=>'Faça uma pergunta.']); exit; }

    $ctx = oracle_contexto($pdo, $perms);
    $sys = oracle_persona() . "\n\n=== DADOS DO COCKPIT (JSON — use só isto) ===\n" . json_encode($ctx, JSON_UNESCAPED_UNICODE);

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
    echo json_encode(['resposta'=>$resposta, 'modelo'=>$model], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
