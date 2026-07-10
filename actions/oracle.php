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
require_once __DIR__ . '/../includes/verba.php';

define('ORACLE_CFG_FILE', __DIR__ . '/../data/.oracle.json');
function oracle_cfg() { $j = @json_decode(@file_get_contents(ORACLE_CFG_FILE), true); return is_array($j) ? $j : []; }

function oracle_post($url, $key, $payload) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
        // JSON_INVALID_UTF8_SUBSTITUTE (7.2+): evita json_encode()==false se algum texto tiver UTF-8 quebrado
        // (ex.: histórico cortado por substr no meio de um caractere) — sem ele, o corpo iria vazio → HTTP 400.
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0)),
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
FERRAMENTA `detalhar_verba(item, obra)`: quando perguntarem o VALOR ou o DETALHE da verba de um item específico (ex.: "qual a verba dos elevadores definitivos da Trinity?"), CHAME esta ferramenta — ela devolve método (composição/analítico), verba total, material, mão de obra e as composições/insumos que geraram o valor. Traga a resposta DETALHADA: total, split material×MO, método, e a lista de composições/insumos com seus valores (tabela ajuda). NUNCA responda "verba não definida" sem antes chamar a ferramenta; só diga que não há verba se ela retornar metodo "não definida". Se retornar encontrado=false, aí sim diga que não achou o item e confirme o nome/obra.

FERRAMENTA `agregar_verba(agrupar_por, top, obra?)`: para qualquer pergunta de RANKING, SOMA ou AGRUPAMENTO de verba — ex.: "15 maiores itens por verba de todas as obras", "verba total por grupo/curva/responsável", "maiores itens da obra X" — CHAME esta ferramenta. É PROIBIDO somar, agrupar ou ordenar o "catalogo" na mão (você erra as contas): a ferramenta já faz o GROUP BY + SUM + ORDER no servidor e devolve `ranking` = lista JÁ ORDENADA do maior total pro menor, cada linha com `chave` (o item/grupo/etc.), `por_obra` (a verba de CADA obra) e `total`. Regras ao apresentar: (1) use EXATAMENTE os números e a ordem que a ferramenta devolveu — não recalcule nem reordene; (2) para "maiores itens", chame com agrupar_por="item" (isso já junta o MESMO item de TODAS as obras numa linha só — não repita o item); (3) mostre como lista numerada 1..N, e sob cada item liste a verba de cada obra em `por_obra` + o **Total**; (4) se uma obra não aparece no `por_obra` de um item, é porque aquele item não tem verba definida naquela obra — não invente valor.

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
- Fale como um analista humano. NUNCA cite os nomes internos da estrutura de dados (catalogo, JSON, colunas, "listas") nem mande a pessoa "consultar o catalogo" — isso não existe na tela. Se faltar algo ou quiser indicar onde conferir, aponte o MÓDULO/ABA real (Radar de Aquisições, Matriz, Mapa de Cotações, ou o modal do item → aba Cronograma/Orçamento/Quantitativo).
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

// ================= FERRAMENTA: detalhar verba (function-calling da IA) =================
// Resolve nome do item + nome da obra -> [servico_id, obra_id, nome_real, erro].
// erro: 'item_vazio' | 'obra_nao_encontrada' | null. NUNCA cai numa obra diferente quando a obra
// foi especificada mas não casou (senão reportaria a verba da obra ERRADA silenciosamente).
function oracle_resolve_item($pdo, $item, $obra) {
    $item = trim((string)$item); $obra = trim((string)$obra);
    if ($item === '') return [null, null, null, 'item_vazio'];
    $obra_id = null;
    if ($obra !== '') {
        $q = $pdo->prepare("SELECT id FROM obra WHERE nome = ? LIMIT 1"); $q->execute([$obra]); $obra_id = $q->fetchColumn();
        if ($obra_id === false) { $q = $pdo->prepare("SELECT id FROM obra WHERE nome LIKE ? ORDER BY id LIMIT 1"); $q->execute(['%'.$obra.'%']); $obra_id = $q->fetchColumn(); }
        if ($obra_id === false) return [null, null, null, 'obra_nao_encontrada'];  // obra citada mas inexistente
    }
    if (!$obra_id) { $obra_id = $pdo->query("SELECT id FROM obra ORDER BY id LIMIT 1")->fetchColumn(); }  // só quando obra veio vazia
    $obra_id = (int)$obra_id;
    $q = $pdo->prepare("SELECT s.id, s.nome FROM servico s JOIN radar_item r ON r.servico_id=s.id AND r.obra_id=? WHERE s.nome = ? LIMIT 1");
    $q->execute([$obra_id, $item]); $row = $q->fetch();
    if (!$row) { $q = $pdo->prepare("SELECT s.id, s.nome FROM servico s JOIN radar_item r ON r.servico_id=s.id AND r.obra_id=? WHERE s.nome LIKE ? ORDER BY s.id LIMIT 1"); $q->execute([$obra_id, '%'.$item.'%']); $row = $q->fetch(); }
    return [$row ? (int)$row['id'] : null, $obra_id, $row ? $row['nome'] : null, null];
}

function oracle_tool_detalhar_verba($pdo, $item, $obra) {
    [$sid, $obra_id, $snome, $erro] = oracle_resolve_item($pdo, $item, $obra);
    if ($erro === 'item_vazio') return ['encontrado'=>false, 'motivo'=>'Informe o nome do item para eu detalhar a verba.'];
    if ($erro === 'obra_nao_encontrada') return ['encontrado'=>false, 'motivo'=>'Obra "'.$obra.'" não encontrada. Confirme o nome da obra (não vou assumir outra obra).'];
    $onome = ''; if ($obra_id) { $q = $pdo->prepare("SELECT nome FROM obra WHERE id=?"); $q->execute([$obra_id]); $onome = (string)$q->fetchColumn(); }
    if (!$sid) return ['encontrado'=>false, 'motivo'=>'Item "'.$item.'" não encontrado no radar da obra '.($onome ?: $obra).'.'];
    $bd = verba_breakdown_data($pdo, $sid, $obra_id);
    if (isset($bd['error'])) return ['encontrado'=>false, 'motivo'=>$bd['error']];
    // Sem composição/analítico vinculado: cai no valor denormalizado (override/material+mo/estim).
    if ($bd['metodo'] === 'nenhum') {
        $q = $pdo->prepare("SELECT verba_override, verba_material, verba_mo, verba_estim FROM radar_item WHERE servico_id=? AND obra_id=?");
        $q->execute([$sid, $obra_id]); $rr = $q->fetch(); $v = $rr ? o_verba($rr) : null;
        return ['encontrado'=>true, 'item'=>$snome, 'obra'=>$onome,
                'metodo'=> $v !== null ? 'valor definido diretamente (sem quebra por insumo)' : 'não definida',
                'verba_total'=> $v !== null ? round($v) : 0,
                'material'=> $rr && o_num($rr['verba_material']) !== null ? round(o_num($rr['verba_material'])) : null,
                'mao_de_obra'=> $rr && o_num($rr['verba_mo']) !== null ? round(o_num($rr['verba_mo'])) : null,
                'composicoes'=>[], 'insumos'=>[]];
    }
    $metNome = ['analitico'=>'orçamento analítico', 'composicao'=>'composição de insumos'][$bd['metodo']] ?? $bd['metodo'];
    $comps = array_map(function($l){ return ['descricao'=>$l['descricao'], 'valor'=>round($l['valor'])]; }, $bd['linhas']);
    $ins = [];
    foreach (['material','mo','mat_mo','equip'] as $t) foreach (($bd['por_tipo'][$t] ?? []) as $x)
        $ins[] = ['desc'=>$x['desc'], 'tipo'=>$t, 'qtde'=>round($x['qtde'],2), 'unidade'=>$x['unidade'], 'valor'=>round($x['valor'])];
    usort($ins, function($a,$b){ return $b['valor'] <=> $a['valor']; });
    $tp = $bd['tot_por_tipo'];
    return ['encontrado'=>true, 'item'=>$snome, 'obra'=>$onome, 'metodo'=>$metNome,
            'verba_total'=>round($bd['total']), 'material'=>round($tp['material']), 'mao_de_obra'=>round($tp['mo']),
            'mat_mo'=>round($tp['mat_mo']), 'equipamento'=>round($tp['equip']),
            'composicoes'=>$comps, 'insumos'=>array_slice($ins, 0, 20)];
}

// AGREGA a verba do radar por uma dimensão (item/grupo/curva/responsavel/obra) — SOMA + quebra por obra + ordena,
// tudo no SERVIDOR (exato). É o jeito CERTO de responder ranking/soma; o LLM não deve somar centenas de linhas na mão.
function oracle_tool_agregar_verba($pdo, $agrupar, $top, $obra = null) {
    $dims = ['item','grupo','curva','responsavel','obra'];
    $agrupar = strtolower(trim((string)$agrupar)); if (!in_array($agrupar, $dims, true)) $agrupar = 'item';
    $top = (int)$top; if ($top <= 0) $top = 15; $top = min(60, $top);
    $obraId = null; $obraNome = '';
    if ($obra !== null && trim((string)$obra) !== '') {
        $ob = trim((string)$obra);
        $q = $pdo->prepare("SELECT id, nome FROM obra WHERE nome = ? LIMIT 1"); $q->execute([$ob]); $row = $q->fetch();
        if (!$row) { $q = $pdo->prepare("SELECT id, nome FROM obra WHERE nome LIKE ? ORDER BY id LIMIT 1"); $q->execute(['%'.$ob.'%']); $row = $q->fetch(); }
        if (!$row) return ['erro'=>'Obra "'.$ob.'" não encontrada.'];
        $obraId = (int)$row['id']; $obraNome = $row['nome'];
    }
    $sql = "SELECT s.nome AS item, s.grupo AS grupo, s.curva AS curva, o.nome AS obra, r.responsavel AS responsavel,
                   r.verba_override, r.verba_material, r.verba_mo, r.verba_estim
            FROM servico s JOIN radar_item r ON r.servico_id = s.id JOIN obra o ON o.id = r.obra_id";
    $args = []; if ($obraId) { $sql .= " WHERE r.obra_id = ?"; $args[] = $obraId; }
    $st = $pdo->prepare($sql); $st->execute($args);
    $grp = [];
    foreach ($st->fetchAll() as $r) {
        $v = o_verba($r); if ($v === null || $v <= 0) continue;
        $key = trim((string)$r[$agrupar]);
        if ($key === '') $key = ($agrupar === 'responsavel') ? '(sem responsável)' : ('(sem ' . $agrupar . ')');
        if (!isset($grp[$key])) $grp[$key] = ['chave'=>$key, 'total'=>0.0, 'por_obra'=>[]];
        $grp[$key]['total'] += $v;
        $ob = $r['obra']; $grp[$key]['por_obra'][$ob] = round(($grp[$key]['por_obra'][$ob] ?? 0) + $v);
    }
    foreach ($grp as &$g) { $g['total'] = round($g['total']); $g['n_obras'] = count($g['por_obra']); } unset($g);
    $arr = array_values($grp);
    usort($arr, function($a, $b) { return $b['total'] <=> $a['total']; });
    return ['agrupado_por'=>$agrupar, 'obra'=>$obraNome ?: 'todas as obras', 'top'=>$top,
            'total_de_grupos'=>count($grp), 'ranking'=>array_slice($arr, 0, $top)];
}

function oracle_tools() {
    return [
        ['type'=>'function', 'function'=>[
            'name'=>'detalhar_verba',
            'description'=>'Retorna a QUEBRA da verba de um item do radar: método (composição de insumos / orçamento analítico), verba total, material, mão de obra, e as composições e insumos que geraram o valor. Chame SEMPRE que perguntarem o VALOR ou o DETALHE/COMPOSIÇÃO da verba de um item específico — não responda "não definida" sem antes tentar esta ferramenta.',
            'parameters'=>['type'=>'object', 'properties'=>[
                'item'=>['type'=>'string', 'description'=>'Nome do item/serviço do radar. Ex.: "Elevadores Definitivos".'],
                'obra'=>['type'=>'string', 'description'=>'Nome da obra. Ex.: "Trinity".'],
            ], 'required'=>['item','obra']],
        ]],
        ['type'=>'function', 'function'=>[
            'name'=>'agregar_verba',
            'description'=>'AGREGA (soma) a verba do radar por uma dimensão, já com a quebra por obra e o TOTAL, ordenado do maior pro menor. Use SEMPRE para RANKING/SOMA/AGRUPAMENTO — ex.: "15 maiores itens por verba de todas as obras", "verba total por grupo", "quanto por curva", "maiores itens da obra X". NUNCA some/agrupe/ordene o catálogo na mão: chame esta ferramenta e só formate o resultado.',
            'parameters'=>['type'=>'object', 'properties'=>[
                'agrupar_por'=>['type'=>'string', 'enum'=>['item','grupo','curva','responsavel','obra'], 'description'=>'Dimensão do agrupamento. Para "maiores itens" use "item" (agrupa o mesmo item de TODAS as obras).'],
                'top'=>['type'=>'integer', 'description'=>'Quantos retornar (padrão 15).'],
                'obra'=>['type'=>'string', 'description'=>'Opcional: filtra por uma obra. Vazio/omresso = TODAS as obras.'],
            ], 'required'=>['agrupar_por']],
        ]],
    ];
}

// Permite carregar só as funções (testes/CLI) sem executar o endpoint.
// Prompt PADRÃO do motor de extração de propostas (IA lê PDF/Excel/imagem) — editável pelo admin (cfg['prompt_extracao']).
function oracle_extracao_default_prompt() {
    return "Você é um assistente de suprimentos da construtora Caprem. Recebe a PROPOSTA de um fornecedor "
        . "(pode vir como PDF, planilha Excel, ou FOTO/PRINT de tela — muitas vezes bagunçada, manuscrita ou informal, "
        . "tirada do WhatsApp) e a LISTA DE ITENS que estamos cotando.\n"
        . "Tarefa: extrair o PREÇO UNITÁRIO de cada item, casando a proposta do fornecedor com os itens da nossa lista "
        . "pela descrição, unidade e contexto. Seja tolerante a diferenças de redação, abreviações e ordem.\n"
        . "REGRAS:\n"
        . "- Para cada item da nossa lista, devolva o preço unitário que encontrar; se não achar, use null.\n"
        . "- Números em formato brasileiro: '1.234,56' vale 1234.56. Devolva SEMPRE número com ponto decimal "
        . "(ex.: 1234.56), sem 'R\$' e sem separador de milhar.\n"
        . "- Custos que o fornecedor cobrou mas que NÃO têm item na nossa lista (FRETE, IMPOSTO, taxas, mobilização, etc.) "
        . "vão em 'extras' — não invente item na lista.\n"
        . "- Condição específica de um item (marca, prazo, quantidade mínima) vai na 'observacao' daquele item.\n"
        . "- Capture também prazo de entrega, condição de pagamento e validade da proposta, se aparecerem.\n"
        . "- Preencha os PONTOS DE EQUALIZAÇÃO (condições comerciais a comparar): SEMPRE 'Frete', 'Condição de pagamento' e 'Descarregamento'; e CRIE pontos NOVOS quando o fornecedor destacar algo relevante (ex.: Imposto/ICMS, Mobilização, Garantia, Prazo de faturamento). Devolva em 'equalizacao' como {ponto, valor} — valor curto e objetivo (ex.: 'incluso', 'CIF', '30 dias', 'ICMS 12%'). Se não achar, deixe valor vazio.\n"
        . "- NUNCA invente preços. Na dúvida, use null e explique em observacao_geral.\n"
        . "Responda SOMENTE com um JSON válido no formato pedido.";
}

if (defined('ORACLE_LIB_ONLY')) return;

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $cfg = oracle_cfg();
        echo json_encode(['configurado'=>!empty($cfg['key']), 'modelo'=>$cfg['model'] ?? 'gpt-4o',
            'limite_dia'=>(int)($cfg['limit_dia'] ?? 2),
            'prompt'=>oracle_persona($cfg), 'prompt_custom'=>trim((string)($cfg['prompt'] ?? '')) !== '',
            'prompt_padrao'=>oracle_default_prompt(),
            'modelo_extracao'=>trim((string)($cfg['model_extracao'] ?? '')) ?: 'gpt-4o',
            'prompt_extracao'=>trim((string)($cfg['prompt_extracao'] ?? '')),
            'prompt_extracao_custom'=>trim((string)($cfg['prompt_extracao'] ?? '')) !== '',
            'prompt_extracao_padrao'=>oracle_extracao_default_prompt()], JSON_UNESCAPED_UNICODE); exit;
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
        if (array_key_exists('prompt_extracao', $in)) { $pe = trim((string)$in['prompt_extracao']); if ($pe === '') unset($cfg['prompt_extracao']); else $cfg['prompt_extracao'] = $pe; } // prompt do motor de IA (lê anexo)
        if (isset($in['model_extracao']) && trim((string)$in['model_extracao']) !== '') $cfg['model_extracao'] = trim((string)$in['model_extracao']);
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

    // Loop de function-calling: a IA pode chamar detalhar_verba (quebra da verba) antes de responder.
    $base = ['model'=>$model, 'temperature'=>0.3, 'max_tokens'=>1500, 'tools'=>oracle_tools()];
    $ROUNDS = 3; $resposta = null; $respondeu = false;
    for ($round = 0; $round < $ROUNDS; $round++) {
        $payload = array_merge($base, ['messages'=>$messages]);
        if ($round === $ROUNDS - 1) $payload['tool_choice'] = 'none';   // último round: proíbe tools → força resposta em texto
        [$code, $res, $err] = oracle_post('https://api.openai.com/v1/chat/completions', $key, $payload);
        if ($code !== 200) {
            $msg = 'Falha ao consultar a IA (HTTP ' . $code . ')';
            $ej = json_decode((string)$res, true);
            if (!empty($ej['error']['message'])) $msg .= ': ' . $ej['error']['message'];
            elseif ($err) $msg .= ': ' . $err;
            echo json_encode(['error'=>$msg]); exit;
        }
        $j = json_decode($res, true);
        $m = $j['choices'][0]['message'] ?? [];
        $calls = $m['tool_calls'] ?? null;
        if (empty($calls)) { $resposta = $m['content'] ?? '(a IA não retornou resposta)'; $respondeu = true; break; }
        $messages[] = $m;   // mensagem do assistant COM os tool_calls (obrigatória antes das respostas tool)
        foreach ($calls as $tc) {
            $fn = $tc['function']['name'] ?? '';
            $args = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
            $out = ($fn === 'detalhar_verba')
                 ? oracle_tool_detalhar_verba($pdo, $args['item'] ?? '', $args['obra'] ?? '')
                 : (($fn === 'agregar_verba')
                    ? oracle_tool_agregar_verba($pdo, $args['agrupar_por'] ?? 'item', $args['top'] ?? 15, $args['obra'] ?? null)
                    : ['error'=>'ferramenta desconhecida: ' . $fn]);
            $messages[] = ['role'=>'tool', 'tool_call_id'=>$tc['id'] ?? '', 'content'=>json_encode($out, JSON_UNESCAPED_UNICODE)];
        }
    }
    if ($resposta === null) $resposta = '(não consegui concluir a resposta — tente reformular a pergunta)';
    // conta 1 uso do dia (admin não conta) — SÓ quando a IA de fato respondeu (não cobra por falha/round esgotado)
    if (!$isAdmin && $limite > 0 && $respondeu) {
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
