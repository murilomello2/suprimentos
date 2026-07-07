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
require_once __DIR__ . '/../includes/supabase.php';
require_once __DIR__ . '/../includes/cronograma.php';

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

// Monta o CONTEXTO que a IA enxerga. Datas de cotação calculadas do cronograma vivo — MESMA lógica da matriz.php
// (crono_resolver → data necessária; FIM = data − lead; INÍCIO = fim − 30d). A coluna crua fim_cotacao é obsoleta.
// ATRASADO = (FIM já passou e o item não está Finalizado) OU (INÍCIO já passou e a cotação nem foi disparada — "Não Iniciado").
function oracle_contexto($pdo, $perms) {
    $nome = trim((string)($perms['nome'] ?? ''));
    $obras = $pdo->query("SELECT id, nome, cronograma_id FROM obra ORDER BY id")->fetchAll();
    $obrasNome = []; foreach ($obras as $o) $obrasNome[(int)$o['id']] = $o['nome'];
    $hoje = date('Y-m-d'); $fim_mes = date('Y-m-t');
    $meus=[]; $atrasadas=[]; $fecharMes=[]; $catalogo=[]; $porObra=[]; $porStatus=[]; $porResp=[]; $atrResp=[]; $verbaAberta=0.0; $ok=false;
    $q = $pdo->prepare("SELECT s.nome, s.grupo, s.curva, s.lead_dias, s.termos_cronograma,
                r.status, r.responsavel, r.fornecedor, r.verba_estim, r.verba_override, r.verba_material, r.verba_mo,
                r.lead_override, r.data_necessaria_override
              FROM servico s JOIN radar_item r ON r.servico_id = s.id AND r.obra_id = ?
              ORDER BY s.grupo_ordem, s.ordem");
    foreach ($obras as $o) {
        $onome = $o['nome'];
        $tasks = [];
        if (!empty($o['cronograma_id'])) { try { $tasks = crono_tasks($o['cronograma_id']); if ($tasks) $ok = true; } catch (Throwable $e) {} }
        $q->execute([(int)$o['id']]);
        foreach ($q->fetchAll() as $r) {
            // MESMA conta da matriz.php: data necessária (override ou cronograma) − lead = FIM; − 30d = INÍCIO
            $auto = $tasks ? crono_resolver($r, $tasks) : ['data_necessaria'=>null];
            $lead = ($r['lead_override'] !== null && $r['lead_override'] !== '') ? (int)$r['lead_override'] : 60;
            $data_nec = $r['data_necessaria_override'] ?: ($auto['data_necessaria'] ?? null);
            $fim = $data_nec ? date('Y-m-d', strtotime($data_nec . ' -' . $lead . ' days')) : '';
            $ini = $fim ? date('Y-m-d', strtotime($fim . ' -30 days')) : '';
            $v = o_verba($r);
            $st = (($r['status'] ?? '') !== '') ? $r['status'] : 'Não Iniciado';
            $resp = trim((string)$r['responsavel']);
            $porObra[$onome] = ($porObra[$onome] ?? 0) + 1;
            $porStatus[$st] = ($porStatus[$st] ?? 0) + 1;
            if ($resp !== '') $porResp[$resp] = ($porResp[$resp] ?? 0) + 1;
            if ($st !== 'Finalizado' && $v) $verbaAberta += (float)$v;
            $fimAtras = ($fim !== '' && $fim < $hoje && $st !== 'Finalizado');
            $iniAtras = ($ini !== '' && $ini < $hoje && ($st === 'Não Iniciado' || $st === ''));
            $atras = $fimAtras || $iniAtras;
            $motivo = $fimAtras ? ('o FIM da cotação venceu em ' . $fim . ' e o item não está Finalizado')
                    : ($iniAtras ? ('passou o INÍCIO da cotação em ' . $ini . ' e a cotação ainda não foi disparada (status "' . $st . '")') : null);
            // CATÁLOGO COMPLETO (linha compacta; colunar no return) — TODOS os itens de TODAS as obras,
            // inclusive finalizados/sem responsável. Fonte de verdade p/ consultas factuais
            // ("qual fornecedor/status/responsável/verba de X na obra Y"). Ordem = colunas do return.
            $catalogo[] = [$r['nome'], $onome, ($r['curva'] ?? '') ?: '', $st, $resp ?: '',
                           ($r['fornecedor'] ?? '') ?: '', $v !== null ? round($v) : '', $atras ? 1 : 0];
            // Agenda (compacta): só o que precisa de DATA — o resto (fornecedor, verba, curva…) vem do catálogo.
            $recA = ['item'=>$r['nome'], 'obra'=>$onome, 'responsavel'=>$resp ?: null,
                     'inicio'=>$ini ?: null, 'fim'=>$fim ?: null, 'atrasado'=>$atras, 'motivo'=>$motivo];
            if ($nome !== '' && strcasecmp($resp, $nome) === 0) $meus[] = $recA;
            if ($atras) { $atrasadas[] = $recA; if ($resp !== '') $atrResp[$resp] = ($atrResp[$resp] ?? 0) + 1; }
            if ($fim !== '' && $fim <= $fim_mes && $st !== 'Finalizado') $fecharMes[] = $recA;
        }
    }
    usort($atrasadas, function($a,$b){ return strcmp((string)$a['fim'], (string)$b['fim']); });
    usort($fecharMes, function($a,$b){ return strcmp((string)$a['fim'], (string)$b['fim']); });
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
        'hoje' => $hoje, 'fim_do_mes' => $fim_mes,
        'obras' => array_values($obras),
        'fonte_das_datas' => $ok ? 'cronograma ao vivo (via matriz)' : 'INDISPONÍVEL — não consegui ler o cronograma agora',
        'resumo' => ['itens_por_obra'=>$porObra, 'itens_por_status'=>$porStatus, 'itens_por_responsavel'=>$porResp,
                     'total_atrasadas'=>count($atrasadas), 'atrasadas_por_responsavel'=>$atrResp,
                     'itens_a_fechar_este_mes'=>count($fecharMes), 'verba_total_em_aberto'=>round($verbaAberta)],
        'catalogo' => ['colunas'=>['item','obra','curva','status','responsavel','fornecedor','verba','atrasado'],
                       'itens'=>array_slice($catalogo, 0, 2000)],
        'catalogo_truncado' => count($catalogo) > 2000,
        'minhas_aquisicoes' => array_slice($meus, 0, 60),
        'aquisicoes_atrasadas' => array_slice($atrasadas, 0, 60),
        'a_fechar_este_mes' => array_slice($fecharMes, 0, 60),
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

=== ATRASOS E PRAZOS (o CORAÇÃO do radar — o principal problema a evitar) ===
Cada item do radar tem INÍCIO e FIM da cotação (datas calculadas do cronograma). Um item está ATRASADO quando:
1) o FIM da cotação já passou (fim_cotacao < hoje) E o item NÃO está "Finalizado"; OU
2) o INÍCIO da cotação já passou E a cotação nem foi disparada (status ainda "Não Iniciado").
As listas de agenda (aquisicoes_atrasadas / a_fechar_este_mes / minhas_aquisicoes) trazem cada item com: item, obra, responsavel, inicio e fim (datas de cotação), atrasado (true/false) e motivo (texto pronto explicando o atraso). CONFIE nesses campos (não recalcule datas na mão). Use as listas prontas:
• "aquisicoes_atrasadas" = TODOS os itens atrasados (ordenados do mais antigo). Para "o que está atrasado com o Fulano", filtre por responsavel = Fulano.
• "a_fechar_este_mes" = itens cujo FIM da cotação cai até o fim deste mês e não estão Finalizados (inclui os já vencidos). É o que a pessoa "precisa fechar este mês" — são ITENS DO RADAR, não só as cotações do Mapa de Cotações.
• "minhas_aquisicoes" = os itens do usuário logado (cada um com "atrasado"). "resumo.total_atrasadas" e "resumo.atrasadas_por_responsavel" dão os números.
Quando perguntarem "o que tenho que fechar este mês / o que está atrasado", responda pelos ITENS DO RADAR (aquisicoes_atrasadas / a_fechar_este_mes), destacando os ATRASADOS primeiro e o motivo de cada um. O objetivo do time é ZERO atrasos.
Se "fonte_das_datas" for INDISPONÍVEL, avise que não conseguiu ler o cronograma agora e peça pra tentar de novo.

=== CONSULTAS FACTUAIS (qual fornecedor/status/responsável/verba de um item) ===
Para responder sobre QUALQUER item específico — ex.: "qual fornecedor fechamos para Sondagem na Trinity?", "qual o status de X?", "quem é o responsável por Y?", "quanto de verba tem Z?" — consulte SEMPRE o "catalogo". Ele traz TODOS os itens do radar de TODAS as obras (inclusive FINALIZADOS e sem responsável), em formato COLUNAR para economizar espaço:
• catalogo.colunas = ["item","obra","curva","status","responsavel","fornecedor","verba","atrasado"]
• catalogo.itens = lista de LINHAS; cada linha é um array na MESMA ordem das colunas.
Ex.: a linha ["Sondagem","Trinity","C","Finalizado","","Marcel moretti",160,0] quer dizer que o fornecedor de Sondagem na Trinity é "Marcel moretti". Convenções: verba "" ou 0 = não definida; atrasado 1 = sim, 0 = não; responsavel "" = sem responsável.
Ache a linha pelo item + obra e leia a coluna pedida. Só diga "não tenho esse dado" se realmente não houver a linha no catalogo. As listas de agenda (minhas_aquisicoes/aquisicoes_atrasadas/a_fechar_este_mes) são recortes; o "catalogo" é a fonte de verdade completa.

=== COMO RESPONDER ===
- Use SOMENTE os dados do JSON do cockpit fornecido abaixo. Se a info não estiver lá (nem no "catalogo"), diga que não tem esse dado no seu contexto e ORIENTE onde a pessoa acha no sistema (menu/aba).
- NUNCA invente números, datas, fornecedores ou valores.
- Português do Brasil, claro e ACIONÁVEL. Markdown (títulos, listas, negrito, tabelas quando ajudar). Valores em R\$ (formato brasileiro), datas dd/mm/aaaa.
- "Minha programação/minhas cotações" → use minhas_aquisicoes e a_fechar_este_mes (o usuário logado está em usuario_logado).
- Destaque o URGENTE (prazos vencendo, cotações sem proposta, verbas 🤖 não conferidas) e as OPORTUNIDADES.
- Quando fizer sentido, diga ONDE no sistema a pessoa vê/edita aquilo (ex.: "confira no modal do item → aba Cronograma"), pra ela agir.
- Termine, quando útil, com 1-3 próximos passos sugeridos.
TXT;
}
function oracle_persona($cfg) {
    $p = trim((string)($cfg['prompt'] ?? ''));
    return $p !== '' ? $p : oracle_default_prompt();
}

// Permite carregar só as funções (testes/CLI) sem executar o endpoint.
if (defined('ORACLE_LIB_ONLY')) return;

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
