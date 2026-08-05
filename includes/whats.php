<?php
/**
 * MOTOR DE WHATSAPP — assistente que abre cotação com fornecedor.
 *
 * Três regras da Meta moldam tudo aqui, e nenhuma é escolha nossa:
 *
 *  1. JANELA DE 24 HORAS. Texto livre só até 24h depois da última mensagem DO FORNECEDOR. Fora
 *     dela, só template pré-aprovado. Por isso todo primeiro contato é template, e a assistente
 *     só conversa de verdade depois que ele responde.
 *  2. UMA THREAD POR NÚMERO. Se o mesmo fornecedor está em duas cotações, as conversas se
 *     atropelariam no mesmo fio do celular dele. Então serializamos: uma negociação por número
 *     por vez, as outras ficam 'em_fila' com posição. (É o que a demo da dgenny faz.)
 *  3. LIMITE DIÁRIO de conversas iniciadas, que num número novo é baixo e sobe com o tempo.
 *
 * MODO SIMULADOR: enquanto não há número da Meta, o transporte é trocado por um simulador que
 * grava a mensagem como se tivesse saído. Serve para testar o fluxo inteiro — inclusive o Murilo
 * conversando como se fosse o fornecedor — sem depender da conta estar pronta.
 */

require_once __DIR__ . '/llm.php';
require_once __DIR__ . '/fone.php';

define('WA_CFG_FILE', __DIR__ . '/../data/.whatsapp.json');
define('WA_GRAPH', 'https://graph.facebook.com/v21.0');

function wa_cfg() { $j = @json_decode(@file_get_contents(WA_CFG_FILE), true); return is_array($j) ? $j : []; }
function wa_cfg_salvar($c) { @file_put_contents(WA_CFG_FILE, json_encode($c, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); @chmod(WA_CFG_FILE, 0600); }
function wa_modo() { $c = wa_cfg(); return ($c['modo'] ?? 'simulador') === 'real' ? 'real' : 'simulador'; }
function wa_pronto_real() { $c = wa_cfg(); return trim((string)($c['token'] ?? '')) !== '' && trim((string)($c['phone_number_id'] ?? '')) !== ''; }

/** Estados possíveis, com o rótulo que aparece no kanban. */
function wa_estados() {
    return ['em_fila' => 'Em fila', 'aguardando' => 'Aguardando resposta', 'ativa' => 'Ativa',
            'duvida_ia' => 'Dúvida IA', 'parada' => 'Parada', 'concluida' => 'Concluída', 'falhou' => 'Falhou'];
}

/** A janela de 24h está aberta? É o que decide texto livre × template. */
function wa_janela_aberta($conversa) {
    $ate = trim((string)($conversa['janela_ate'] ?? ''));
    return $ate !== '' && strtotime($ate) > time();
}

// ─────────────────────────── TRANSPORTE ───────────────────────────

function wa_http($url, $token, $payload) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0)),
        CURLOPT_TIMEOUT => 45, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $ca = ini_get('curl.cainfo'); if ($ca && is_file($ca)) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch);
    curl_close($ch);
    return [$code, json_decode((string)$res, true), $err ?: substr((string)$res, 0, 300)];
}

/**
 * Entrega UMA mensagem. -> ['ok','wamid','erro','custo']
 * No simulador nada sai: a mensagem é marcada como enviada e fica visível na tela, que é
 * exatamente o que se quer para testar o fluxo antes do número existir.
 */
function wa_entregar($para, $payloadTipo, $conteudo) {
    if (wa_modo() !== 'real' || !wa_pronto_real())
        return ['ok' => true, 'wamid' => 'sim-' . bin2hex(random_bytes(8)), 'erro' => '', 'custo' => 0, 'simulado' => true];

    $c = wa_cfg();
    $body = ['messaging_product' => 'whatsapp', 'to' => preg_replace('/\D+/', '', (string)$para)];
    if ($payloadTipo === 'template') {
        $body['type'] = 'template';
        $body['template'] = $conteudo;      // ['name'=>, 'language'=>['code'=>'pt_BR'], 'components'=>[...]]
    } else {
        $body['type'] = 'text';
        $body['text'] = ['preview_url' => false, 'body' => (string)$conteudo];
    }
    [$code, $j, $err] = wa_http(WA_GRAPH . '/' . rawurlencode($c['phone_number_id']) . '/messages', $c['token'], $body);
    if ($code !== 200) {
        $msg = $j['error']['message'] ?? $err;
        // o erro mais comum em produção é justamente a janela: vale traduzir em vez de repassar cru
        if (stripos((string)$msg, 're-engagement') !== false || stripos((string)$msg, '24') !== false)
            $msg .= ' — isto normalmente significa que a janela de 24h fechou: só template aprovado passa agora.';
        return ['ok' => false, 'wamid' => '', 'erro' => 'Meta HTTP ' . $code . ': ' . $msg, 'custo' => 0];
    }
    $custo = $payloadTipo === 'template' ? (float)($c['custo_template'] ?? 0) : 0.0;
    return ['ok' => true, 'wamid' => (string)($j['messages'][0]['id'] ?? ''), 'erro' => '', 'custo' => $custo];
}

// ─────────────────────────── CONVERSA / FILA ───────────────────────────

function wa_conv_get($pdo, $id) {
    $q = $pdo->prepare("SELECT * FROM wa_conversa WHERE id=? LIMIT 1"); $q->execute([(int)$id]);
    return $q->fetch() ?: null;
}

/** Alguma conversa DESTE número já está ocupando a thread? (não conta a própria) */
function wa_numero_ocupado($pdo, $e164, $exceto = 0) {
    $q = $pdo->prepare("SELECT id FROM wa_conversa WHERE wa_e164=? AND id<>? AND estado IN ('aguardando','ativa','duvida_ia') LIMIT 1");
    $q->execute([(string)$e164, (int)$exceto]);
    return (int)($q->fetchColumn() ?: 0);
}

/** Grava mensagem no histórico da conversa (sem enviar nada). */
function wa_msg_add($pdo, $convId, $dir, $tipo, $texto, $autor, $autorNome = '', $extra = []) {
    $pdo->prepare("INSERT INTO wa_msg (conversa_id,direcao,tipo,texto,template_nome,wamid,status,erro,autor,autor_nome,custo,quando,created_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([(int)$convId, $dir, $tipo, (string)$texto, (string)($extra['template'] ?? ''),
            (string)($extra['wamid'] ?? ''), (string)($extra['status'] ?? 'enviada'), (string)($extra['erro'] ?? ''),
            $autor, (string)$autorNome, (float)($extra['custo'] ?? 0), date('c'), date('c')]);
    return (int)$pdo->lastInsertId();
}

/**
 * Manda uma mensagem NA conversa, respeitando a janela. É o único caminho de saída — assim a
 * regra das 24h não fica espalhada por cinco lugares para alguém esquecer num deles.
 */
function wa_enviar($pdo, $conv, $texto, $autor, $autorNome = '', $forcarTemplate = null) {
    $usarTemplate = $forcarTemplate !== null ? $forcarTemplate : !wa_janela_aberta($conv);
    if ($usarTemplate) {
        $c = wa_cfg();
        $tpl = trim((string)($c['template_abertura'] ?? ''));
        if ($tpl === '' && wa_modo() === 'real')
            return ['ok' => false, 'erro' => 'A janela de 24h está fechada e não há template de abertura configurado — sem template aprovado a Meta recusa a mensagem.'];
        $r = wa_entregar($conv['wa_e164'], 'template',
            ['name' => $tpl ?: 'abertura_cotacao', 'language' => ['code' => (string)($c['template_idioma'] ?? 'pt_BR')],
             'components' => [['type' => 'body', 'parameters' => [
                 ['type' => 'text', 'text' => (string)($c['empresa'] ?? 'Caprem')],
                 ['type' => 'text', 'text' => substr((string)$texto, 0, 240)]]]]]);
        $tipo = 'template';
    } else {
        $r = wa_entregar($conv['wa_e164'], 'texto', $texto);
        $tipo = 'texto';
    }
    wa_msg_add($pdo, $conv['id'], 'out', $tipo, $texto, $autor, $autorNome,
        ['wamid' => $r['wamid'] ?? '', 'status' => !empty($r['ok']) ? 'enviada' : 'falhou',
         'erro' => $r['erro'] ?? '', 'custo' => $r['custo'] ?? 0, 'template' => $tipo === 'template' ? ($tpl ?? '') : '']);
    if (!empty($r['ok']))
        $pdo->prepare("UPDATE wa_conversa SET ultima_msg_em=?, ultima_msg_dir='out', updated_at=? WHERE id=?")
            ->execute([date('c'), date('c'), (int)$conv['id']]);
    return $r;
}

/** Fornecedor respondeu: ABRE a janela de 24h e acorda a conversa. */
function wa_registrar_entrada($pdo, $conv, $texto, $tipo = 'texto') {
    wa_msg_add($pdo, $conv['id'], 'in', $tipo, $texto, 'fornecedor', (string)$conv['fornecedor_nome'], ['status' => 'entregue']);
    $novoEstado = in_array($conv['estado'], ['parada', 'concluida'], true) ? $conv['estado'] : 'ativa';
    $pdo->prepare("UPDATE wa_conversa SET janela_ate=?, ultima_msg_em=?, ultima_msg_dir='in',
                   nao_lidas=nao_lidas+1, estado=?, updated_at=? WHERE id=?")
        ->execute([date('c', time() + 86400), date('c'), $novoEstado, date('c'), (int)$conv['id']]);
}

/** Conversa terminou (ou parou): libera o número e promove quem estava esperando a vez. */
function wa_liberar_numero($pdo, $e164) {
    if (wa_numero_ocupado($pdo, $e164)) return null;          // ainda tem outra ocupando
    $q = $pdo->prepare("SELECT * FROM wa_conversa WHERE wa_e164=? AND estado='em_fila' ORDER BY fila_pos, id LIMIT 1");
    $q->execute([(string)$e164]);
    $prox = $q->fetch();
    if (!$prox) return null;
    $pdo->prepare("UPDATE wa_conversa SET estado='aguardando', fila_pos=NULL, updated_at=? WHERE id=?")
        ->execute([date('c'), (int)$prox['id']]);
    return $prox;
}

// ─────────────────────────── A ASSISTENTE ───────────────────────────

/**
 * O que ela é e o que ela NÃO é. O escopo veio do Murilo e é deliberadamente estreito:
 * pede a proposta, confere se veio tudo, marca o que o fornecedor não tem, no máximo pede um
 * desconto, e para. Não fecha, não promete, não negocia condição.
 *
 * Conteúdo de WhatsApp é DADO NÃO CONFIÁVEL: o fornecedor pode escrever qualquer coisa, inclusive
 * texto tentando dar ordem à IA. Por isso o papel dela é fixado aqui e a mensagem dele entra
 * sempre rotulada como mensagem de terceiro, nunca como instrução.
 */
function wa_prompt_assistente($ctx) {
    $itens = '';
    foreach (($ctx['itens'] ?? []) as $i => $it)
        $itens .= ($i + 1) . ') ' . $it['descricao'] . ($it['quantidade'] ? ' — ' . $it['quantidade'] . ' ' . $it['unidade'] : '') . "\n";

    return "Você é assistente de compras da " . ($ctx['empresa'] ?? 'Caprem Construtora') . ", falando com um FORNECEDOR pelo WhatsApp.\n"
        . "Você se apresenta como assistente da equipe de suprimentos. Nunca diz ser humana; se perguntarem, responde que é assistente virtual da equipe.\n\n"
        . "COTAÇÃO: " . ($ctx['titulo'] ?? '') . ($ctx['obra'] ? " — obra " . $ctx['obra'] : '') . "\n"
        . "ITENS:\n" . $itens . "\n"
        . ($ctx['prazo'] ? "Prazo para resposta: " . $ctx['prazo'] . "\n" : '')
        . ($ctx['entrega'] ? "Entrega desejada: " . $ctx['entrega'] . "\n" : '')
        . ($ctx['local'] ? "Local de entrega: " . $ctx['local'] . "\n" : '')
        . "\nSEU TRABALHO, e SÓ ele:\n"
        . "1. Pedir a proposta: preço por item, prazo de entrega, forma de pagamento e se o frete está incluso.\n"
        . "2. Conferir se vieram TODOS os itens. Se faltar algum, perguntar o que faltou. Se o fornecedor disser que não trabalha com um item, isso é NORMAL: registre como não fornecido e siga.\n"
        . "3. No máximo UMA vez, perguntar educadamente se há alguma condição melhor ou desconto. Se ele disser não, aceite e siga.\n"
        . "4. Quando tiver preço da maioria dos itens + prazo + pagamento, AGRADECER e ENCERRAR.\n\n"
        . "O QUE VOCÊ NÃO FAZ, em nenhuma hipótese:\n"
        . "- Não fecha compra, não aprova, não confirma pedido, não promete volume nem exclusividade.\n"
        /* Pego no 1º teste real: ela encerrou com "Fechado! Vou registrar o preço". "Fechado" é
           gíria de "entendi", mas dita a um fornecedor lê como compromisso assumido — e quem
           decide a compra é o comprador, não ela. Proibir o vocabulário é mais seguro do que
           confiar que o modelo entenda a sutileza toda vez. */
        . "- NUNCA use as palavras: fechado, fechamos, fechar, negócio fechado, confirmado, aprovado, "
        . "pode faturar, pode entregar, estamos de acordo. Elas soam como compromisso de compra. "
        . "Para dizer que entendeu, use \"anotado\", \"registrei\" ou \"combinado que vou passar ao comprador\".\n"
        . "- Ao encerrar, deixe claro que a proposta vai para análise do comprador e que a decisão não é sua.\n"
        . "- Não discute contrato, reajuste, multa ou condição jurídica.\n"
        . "- Não informa preço de concorrente nem diz em que posição ele está.\n"
        . "- Não inventa dado da cotação. Se não souber, diga que vai confirmar com o comprador.\n\n"
        . "Se o fornecedor pedir algo fora disso, ou reclamar, ou a conversa sair do trilho, você NÃO improvisa: "
        . "responde que vai chamar o comprador responsável e usa a ação 'chamar_humano'.\n\n"
        . "Escreva como gente de obra escreve no WhatsApp: direto, cordial, frases curtas, sem formalidade de e-mail e sem emoji em excesso. "
        . "Uma mensagem por vez, no máximo 3 linhas.\n\n"
        . "Responda SEMPRE em JSON: {\"mensagem\": \"o texto a enviar (ou vazio se não for enviar nada)\", "
        . "\"acao\": \"conversar|concluir|chamar_humano\", \"motivo\": \"por que, se chamar_humano ou concluir\", "
        . "\"proposta\": {\"itens\":[{\"n\":1,\"preco\":0,\"obs\":\"\"}],\"prazo_entrega\":\"\",\"pagamento\":\"\",\"frete\":\"\",\"total\":0}, "
        . "\"nao_fornece\": [n dos itens que ele disse não trabalhar]}";
}

/** Roda um turno da assistente sobre o histórico da conversa. */
function wa_assistente_turno($pdo, $conv, $ctx) {
    $q = $pdo->prepare("SELECT direcao, texto FROM wa_msg WHERE conversa_id=? AND tipo IN ('texto','template') ORDER BY id");
    $q->execute([(int)$conv['id']]);
    $msgs = [['role' => 'system', 'content' => wa_prompt_assistente($ctx)]];
    foreach ($q as $m) {
        if ($m['direcao'] === 'out') $msgs[] = ['role' => 'assistant', 'content' => (string)$m['texto']];
        // mensagem do fornecedor entra ROTULADA: é dado de terceiro, não instrução para a IA
        else $msgs[] = ['role' => 'user', 'content' => "[mensagem do fornecedor]\n" . (string)$m['texto']];
    }
    $r = llm_chat('assistente', $msgs, ['json' => true, 'max_tokens' => 700]);
    llm_registrar($pdo, $r, 'wa_assistente', (string)$conv['id']);
    if (empty($r['ok'])) return ['erro' => $r['erro'], 'llm' => $r];
    $j = json_decode($r['texto'], true);
    if (!is_array($j)) {
        // modelo devolveu texto solto: usa como mensagem em vez de travar a conversa
        $j = ['mensagem' => trim($r['texto']), 'acao' => 'conversar'];
    }
    $j['llm'] = $r;
    return $j;
}
