<?php
/**
 * CAMADA DE LLM — um só jeito de conversar com modelo, qualquer que seja o fornecedor.
 *
 * O cockpit já falava com a OpenAI direto (oracle.php, cotacao_ia.php), com a chave em
 * data/.oracle.json. Isso funciona enquanto só existe um uso e um fornecedor. A assistente de
 * WhatsApp muda o quadro: ela vai mandar MUITA mensagem, e o Murilo quer comparar custo ×
 * qualidade entre modelos sem reescrever código toda vez. Então o provedor vira configuração.
 *
 * Suportados: OpenAI, Anthropic, Google (Gemini) e qualquer endpoint COMPATÍVEL com a OpenAI
 * (OpenRouter, Groq, DeepSeek, Together, um vLLM local...). O compatível cobre o resto do mundo
 * sem eu ter de escrever um adaptador por marca.
 *
 * PERFIS: cada uso do sistema aponta para um perfil ('assistente' | 'oraculo' | 'extracao'), e o
 * perfil diz qual provedor/modelo usar. Assim dá para rodar a assistente num modelo barato e a
 * extração de proposta num caro, sem tocar em código.
 *
 * A chave NUNCA vai ao navegador. Fica em data/.llm.json (gitignored, 403 no .htaccess).
 */

define('LLM_CFG_FILE', __DIR__ . '/../data/.llm.json');

function llm_cfg() {
    $j = @json_decode(@file_get_contents(LLM_CFG_FILE), true);
    return is_array($j) ? $j : [];
}
function llm_cfg_salvar($cfg) {
    @file_put_contents(LLM_CFG_FILE, json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    @chmod(LLM_CFG_FILE, 0600);
}

/** Catálogo só para a TELA: rótulo, endpoint padrão e preço por milhão de tokens.
    Preço muda e não é contrato — serve para ESTIMAR o gasto, e a tela diz isso.
    Quem quiser um modelo que não está aqui digita o id na mão; o campo é livre. */
function llm_catalogo() {
    return [
        'openai' => ['nome' => 'OpenAI', 'url' => 'https://api.openai.com/v1/chat/completions',
            'modelos' => [
                'gpt-4o'      => ['nome' => 'GPT-4o',      'in' => 2.50, 'out' => 10.00],
                'gpt-4o-mini' => ['nome' => 'GPT-4o mini', 'in' => 0.15, 'out' => 0.60],
            ]],
        'anthropic' => ['nome' => 'Anthropic (Claude)', 'url' => 'https://api.anthropic.com/v1/messages',
            'modelos' => [
                'claude-sonnet-4-5' => ['nome' => 'Claude Sonnet 4.5', 'in' => 3.00, 'out' => 15.00],
                'claude-haiku-4-5'  => ['nome' => 'Claude Haiku 4.5',  'in' => 1.00, 'out' => 5.00],
            ]],
        'google' => ['nome' => 'Google (Gemini)', 'url' => 'https://generativelanguage.googleapis.com/v1beta/models',
            'modelos' => [
                'gemini-2.0-flash' => ['nome' => 'Gemini 2.0 Flash', 'in' => 0.10, 'out' => 0.40],
            ]],
        'compativel' => ['nome' => 'Outro (compatível com OpenAI)', 'url' => '',
            'modelos' => []],
    ];
}

/** Perfil resolvido: provedor, modelo, chave, url. Cai no 'padrao' quando o perfil não foi setado,
    e no .oracle.json quando o LLM novo ainda não foi configurado — para nada quebrar na migração. */
function llm_perfil($perfil = 'padrao') {
    $c = llm_cfg();
    $p = $c['perfis'][$perfil] ?? $c['perfis']['padrao'] ?? null;
    if (!$p || empty($p['provedor'])) {
        $o = @json_decode(@file_get_contents(__DIR__ . '/../data/.oracle.json'), true);
        if (is_array($o) && !empty($o['key']))
            return ['provedor' => 'openai', 'modelo' => $o['model'] ?? 'gpt-4o', 'chave' => $o['key'],
                    'url' => 'https://api.openai.com/v1/chat/completions', 'herdado' => true, 'perfil' => $perfil];
        return null;
    }
    $prov = $p['provedor'];
    $cat = llm_catalogo();
    $chave = $c['chaves'][$prov] ?? '';
    $url = trim((string)($p['url'] ?? '')) ?: ($cat[$prov]['url'] ?? '');
    if ($chave === '') return null;
    return ['provedor' => $prov, 'modelo' => $p['modelo'] ?? '', 'chave' => $chave, 'url' => $url,
            'temperatura' => $p['temperatura'] ?? 0.3, 'max_tokens' => (int)($p['max_tokens'] ?? 1200), 'perfil' => $perfil];
}

/**
 * CONTEÚDO MULTIMODAL num formato NEUTRO.
 *
 * O cadastro de fornecedor em lote lê o PRINT de uma lista (o comprador pesquisa na IA e cola a
 * tabela como imagem). Cada provedor embala imagem de um jeito diferente, e antes disto o
 * llm_chat() fazia (string)$content — o que jogava a imagem no lixo calado em Anthropic/Google.
 *
 * Então a mensagem pode chegar como array de partes:
 *   [['t'=>'texto','texto'=>'...'], ['t'=>'imagem','mime'=>'image/png','b64'=>'...']]
 * e aqui cada uma vira o formato do provedor. `content` string continua passando reto — nada do
 * que já funcionava muda de caminho.
 */
function llm_partes($partes, $prov) {
    $out = [];
    foreach ((array)$partes as $p) {
        $tipo = $p['t'] ?? 'texto';
        if ($tipo === 'imagem') {
            $mime = (string)($p['mime'] ?? 'image/png'); $b64 = (string)($p['b64'] ?? '');
            if ($b64 === '') continue;
            if ($prov === 'anthropic')   $out[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $b64]];
            elseif ($prov === 'google')  $out[] = ['inline_data' => ['mime_type' => $mime, 'data' => $b64]];
            else                         $out[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $mime . ';base64,' . $b64]];
            continue;
        }
        $txt = (string)($p['texto'] ?? '');
        if ($txt === '') continue;
        $out[] = ($prov === 'google') ? ['text' => $txt] : ['type' => 'text', 'text' => $txt];
    }
    return $out;
}

function llm_http($url, $headers, $payload, $timeout = 120) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        // JSON_INVALID_UTF8_SUBSTITUTE: texto vindo de e-mail/WhatsApp às vezes tem UTF-8 quebrado;
        // sem isto json_encode devolve false e o corpo vai vazio -> HTTP 400 sem explicação.
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0)),
        CURLOPT_TIMEOUT => $timeout, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $ca = ini_get('curl.cainfo'); if ($ca && is_file($ca)) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch);
    curl_close($ch);
    return [$code, $res, $err];
}

/**
 * Conversa com o modelo. $msgs = [['role'=>'system|user|assistant','content'=>'...'], ...]
 * -> ['ok'=>bool, 'texto'=>string, 'erro'=>string, 'in'=>tokens, 'out'=>tokens, 'custo'=>float,
 *     'provedor'=>, 'modelo'=>, 'ms'=>]
 *
 * $opts: json=true força resposta em JSON | temperatura | max_tokens
 */
function llm_chat($perfil, $msgs, $opts = []) {
    $p = llm_perfil($perfil);
    if (!$p) return ['ok' => false, 'erro' => 'Nenhum modelo configurado. Um administrador configura em Configuração › Inteligência Artificial.'];
    $t0 = microtime(true);
    $temp = $opts['temperatura'] ?? $p['temperatura'] ?? 0.3;
    $maxT = (int)($opts['max_tokens'] ?? $p['max_tokens'] ?? 1200);
    $prov = $p['provedor'];

    if ($prov === 'anthropic') {
        // A Anthropic separa o system do array de mensagens — juntar tudo em 'messages' dá 400.
        $sys = ''; $ms = [];
        foreach ($msgs as $m) {
            if (($m['role'] ?? '') === 'system') { $sys .= ($sys ? "\n\n" : '') . (string)$m['content']; continue; }
            $ms[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user',
                     'content' => is_array($m['content'] ?? null) ? llm_partes($m['content'], 'anthropic') : (string)$m['content']];
        }
        $body = ['model' => $p['modelo'], 'max_tokens' => $maxT, 'temperature' => $temp, 'messages' => $ms];
        if ($sys !== '') $body['system'] = $sys;
        [$code, $res, $err] = llm_http($p['url'], ['Content-Type: application/json',
            'x-api-key: ' . $p['chave'], 'anthropic-version: 2023-06-01'], $body);
        $j = json_decode((string)$res, true);
        if ($code !== 200) return llm_erro($code, $err, $j, $res, $p, $t0);
        $txt = '';
        foreach (($j['content'] ?? []) as $b) if (($b['type'] ?? '') === 'text') $txt .= $b['text'];
        return llm_ok($txt, (int)($j['usage']['input_tokens'] ?? 0), (int)($j['usage']['output_tokens'] ?? 0), $p, $t0);
    }

    if ($prov === 'google') {
        // Gemini: system à parte, 'contents' com 'parts', e o papel do assistente chama-se 'model'.
        $sys = ''; $conts = [];
        foreach ($msgs as $m) {
            if (($m['role'] ?? '') === 'system') { $sys .= ($sys ? "\n\n" : '') . (string)$m['content']; continue; }
            $conts[] = ['role' => ($m['role'] === 'assistant' ? 'model' : 'user'),
                        'parts' => is_array($m['content'] ?? null) ? llm_partes($m['content'], 'google') : [['text' => (string)$m['content']]]];
        }
        $body = ['contents' => $conts, 'generationConfig' => ['temperature' => $temp, 'maxOutputTokens' => $maxT]];
        if ($sys !== '') $body['systemInstruction'] = ['parts' => [['text' => $sys]]];
        if (!empty($opts['json'])) $body['generationConfig']['responseMimeType'] = 'application/json';
        $url = rtrim($p['url'], '/') . '/' . rawurlencode($p['modelo']) . ':generateContent?key=' . rawurlencode($p['chave']);
        [$code, $res, $err] = llm_http($url, ['Content-Type: application/json'], $body);
        $j = json_decode((string)$res, true);
        if ($code !== 200) return llm_erro($code, $err, $j, $res, $p, $t0);
        $txt = '';
        foreach (($j['candidates'][0]['content']['parts'] ?? []) as $pt) $txt .= (string)($pt['text'] ?? '');
        return llm_ok($txt, (int)($j['usageMetadata']['promptTokenCount'] ?? 0), (int)($j['usageMetadata']['candidatesTokenCount'] ?? 0), $p, $t0);
    }

    // openai + qualquer coisa compatível com ela
    $msgs = array_map(fn($m) => is_array($m['content'] ?? null)
        ? ['role' => $m['role'], 'content' => llm_partes($m['content'], 'openai')] : $m, $msgs);
    $body = ['model' => $p['modelo'], 'temperature' => $temp, 'max_tokens' => $maxT, 'messages' => $msgs];
    if (!empty($opts['json'])) $body['response_format'] = ['type' => 'json_object'];
    [$code, $res, $err] = llm_http($p['url'], ['Content-Type: application/json', 'Authorization: Bearer ' . $p['chave']], $body);
    $j = json_decode((string)$res, true);
    if ($code !== 200) return llm_erro($code, $err, $j, $res, $p, $t0);
    return llm_ok((string)($j['choices'][0]['message']['content'] ?? ''),
        (int)($j['usage']['prompt_tokens'] ?? 0), (int)($j['usage']['completion_tokens'] ?? 0), $p, $t0);
}

function llm_ok($texto, $in, $out, $p, $t0) {
    return ['ok' => true, 'texto' => trim((string)$texto), 'erro' => '', 'in' => $in, 'out' => $out,
        'custo' => llm_custo($p['provedor'], $p['modelo'], $in, $out),
        'provedor' => $p['provedor'], 'modelo' => $p['modelo'], 'perfil' => $p['perfil'],
        'ms' => (int)round((microtime(true) - $t0) * 1000)];
}
function llm_erro($code, $err, $j, $res, $p, $t0) {
    // a mensagem do provedor é a informação útil; "HTTP 400" sozinho manda a pessoa adivinhar
    $msg = $j['error']['message'] ?? $j['error']['msg'] ?? $j['message'] ?? ($err ?: substr((string)$res, 0, 300));
    return ['ok' => false, 'texto' => '', 'erro' => 'HTTP ' . $code . ': ' . $msg, 'in' => 0, 'out' => 0, 'custo' => 0,
        'provedor' => $p['provedor'], 'modelo' => $p['modelo'], 'perfil' => $p['perfil'],
        'ms' => (int)round((microtime(true) - $t0) * 1000)];
}

/** Estimativa em US$. Preço de tabela muda sem aviso — isto serve para COMPARAR modelos e prever
    ordem de grandeza, nunca para conciliar fatura. A tela diz isso em voz alta. */
function llm_custo($prov, $modelo, $in, $out) {
    $cat = llm_catalogo();
    $m = $cat[$prov]['modelos'][$modelo] ?? null;
    if (!$m) {
        $c = llm_cfg();
        $m = $c['precos'][$prov . '/' . $modelo] ?? null;   // preço digitado à mão p/ modelo fora do catálogo
        if (!$m) return 0.0;
    }
    return round(($in / 1000000) * (float)$m['in'] + ($out / 1000000) * (float)$m['out'], 6);
}

/** Registra cada chamada: sem isto não há como responder "qual modelo saiu mais barato". */
function llm_registrar($pdo, $r, $contexto = '', $ref = '') {
    try {
        $pdo->prepare("INSERT INTO llm_uso (quando,perfil,provedor,modelo,tokens_in,tokens_out,custo,ms,ok,erro,contexto,ref)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([date('c'), (string)($r['perfil'] ?? ''), (string)($r['provedor'] ?? ''), (string)($r['modelo'] ?? ''),
                (int)($r['in'] ?? 0), (int)($r['out'] ?? 0), (float)($r['custo'] ?? 0), (int)($r['ms'] ?? 0),
                !empty($r['ok']) ? 1 : 0, substr((string)($r['erro'] ?? ''), 0, 400), (string)$contexto, (string)$ref]);
    } catch (Throwable $e) {}
}
