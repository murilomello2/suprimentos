<?php
/**
 * BUSCA DE PEDIDOS DE COMPRA (consulta, só leitura) — base do TOTVS (Supabase pedidos_itens).
 * "Com quem a gente está comprando martelete?" → digita o item/fornecedor/nº e vê os PCs.
 *
 * GET ?q=<texto>&obra_id=<n>&periodo=30d|3m|ano|tudo&de=&ate=&ordem=recente|numero|obra&pagina=1&me=..
 *   -> { pedidos:[{numero, coligada, coligada_cod, obra, data, status, fornecedores[], n_itens, total, solic}],
 *        total, pagina, paginas, por_pagina, truncado }
 *
 * COMO A OBRA É RESOLVIDA (29/jul): o próprio TOTVS já entrega pronto, via DAX do Murilo —
 *   `obra_efetiva_nome`  = razão social da obra REAL do pedido
 *   `obra_efetiva_fonte` = COLIGADA (a obra é a própria coligada) | RATEIO_CAPRETZ (compra da CAPRETZ
 *                          rateada p/ uma obra — ex.: CAPRETZ comprando p/ o Cajá)
 *   `obra_cod`           = centro de custo da obra (vem da solicitação)
 * Aqui só traduzimos a razão social p/ o nome amigável do cockpit (obra_ficha.coligada_nome -> nome) e,
 * quando é rateio, mostramos "CAPRETZ/<obra>" — assim dá p/ distinguir compra DA CAPRETZ (sede) de
 * compra da CAPRETZ PARA uma obra. Nada de adivinhação por coligada: o dado é do TOTVS.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/coligadas.php';
require_once __DIR__ . '/../includes/supabase.php';
require_once __DIR__ . '/../includes/sb_pag.php';   // paginação/varredura do PostgREST (compartilhada com a Busca de Notas)

define('BP_MAX_LINHAS', 30000);  // teto de itens lidos por consulta (protege o Supabase e a memória)
define('BP_POR_PAGINA', 30);

/* A máquina de paginar (offsets em paralelo, contagem por Prefer: count=exact, leitura por callback)
   mudou-se para includes/sb_pag.php quando a Busca de NOTAS passou a precisar dela sobre outra
   tabela. Aqui ficam só os atalhos com a tabela já amarrada — a assinatura bp_varrer($query,$cb)
   continua a mesma porque o Envio de Pedidos e o PDF do pedido a usam como biblioteca. */
function bp_get($query)                  { return sbp_get('pedidos_itens', $query); }
function bp_varrer($query, callable $cb) { return sbp_varrer('pedidos_itens', $query, $cb, BP_MAX_LINHAS); }

/** Status do pedido no TOTVS -> texto legível (tabela oficial passada pelo Murilo, 28/jul). */
function bp_status_label($s) {
    static $M = ['A'=>'Pendente', 'B'=>'Baixado', 'C'=>'Cancelado', 'F'=>'Faturado', 'G'=>'Parcialmente faturado',
                 'N'=>'Normal', 'Q'=>'Quitado', 'R'=>'Em faturamento', 'U'=>'Em separação'];
    $k = strtoupper(trim((string)$s));
    return $M[$k] ?? ($k === '' ? '—' : 'Status não identificado');
}

/** Normaliza razão social p/ casar TOTVS × ficha (sem acento/pontuação, maiúsculo). */
function bp_nz($x) {
    $x = strtoupper(@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$x));
    return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9 ]/', ' ', $x)));
}

/** razão social (obra_efetiva_nome do TOTVS) -> nome amigável da obra no cockpit. */
function bp_mapa_razao($pdo) {
    $map = [];
    try {
        foreach ($pdo->query("SELECT nome, coligada_nome FROM obra_ficha WHERE coligada_nome IS NOT NULL AND coligada_nome<>''") as $o) {
            $k = bp_nz($o['coligada_nome']);
            if ($k !== '' && !isset($map[$k])) $map[$k] = $o['nome'];
        }
    } catch (Throwable $e) {}
    return $map;
}


/**
 * OBRA DO RATEIO DA CAPRETZ — pelo CENTRO DE CUSTO da solicitação, não pela razão social.
 *
 * O Murilo pegou o erro: o PC 33539 aparecia como "CAPRETZ/INSTITUTO CAPREM" e é da Prades; o 33511
 * aparecia como San Pietro e é da Licel. A razão social que o DAX entrega em obra_efetiva_nome é a
 * do CENTRO DE CUSTO onde a despesa caiu, que numa compra guarda-chuva da CAPRETZ não é a obra que
 * vai receber o material.
 *
 * O de-para certo já existia e é o mesmo das Solicitações: solic_obra liga (coligada, obra_cod) ao
 * nome comercial. Basta usá-lo — 032 é Licel, 042 é Prades, 040 é Cajá. Nada de heurística nova.
 *
 * O que a razão social continua decidindo: se é obra ou sede (via centro de custo 8.x × 6/7.x).
 */
function bp_mapa_obracod($pdo) {
    /* BASE ESTÁTICA. É a mesma tabela que solic_nome_default() usa nas Solicitações — copiada aqui
       de propósito, em vez de incluir includes/solic.php: aquele arquivo puxa config + supabase e a
       dependência estava falhando em silêncio dentro do try/catch, deixando o mapa vazio. Uma cópia
       de 11 linhas que não pode falhar vale mais que um include elegante que falha calado. */
    $map = ['001' => 'Comercial Americana', '010' => 'Sede', '015' => 'MKT', '020' => 'SAT',
            '032' => 'Licel', '033' => 'Obras SAT', '036' => 'Piamonte', '039' => 'Contrap. Piamonte',
            '040' => 'Cajá', '041' => 'Espazo', '042' => 'Prades'];
    $out = [];
    foreach ($map as $cod => $nome) $out[ltrim($cod, '0')] = $nome;

    /* O de-para salvo no banco SOBRESCREVE a base — é onde alguém corrige ou cadastra um centro de
       custo novo sem precisar de deploy. */
    try {
        foreach ($pdo->query("SELECT coligada, obra_cod, nome_comercial FROM solic_obra") as $r) {
            if (stripos((string)$r['coligada'], 'CAPRETZ') === false) continue;
            $cod = ltrim(trim((string)$r['obra_cod']), '0');
            $nome = trim((string)$r['nome_comercial']);
            if ($cod !== '' && $nome !== '') $out[$cod] = $nome;
        }
    } catch (Throwable $e) {}
    return $out;
}

/** Encurta a razão social quando a obra não tem ficha (tira o juridiquês). */
function bp_curto($razao) {
    $r = preg_replace('/\s+(EMPREENDIMENTOS?|EMPREEND\.?)\s+IMOB.*/iu', '', (string)$razao);
    $r = preg_replace('/\s+(SPE\s+)?LTDA\.?$/iu', '', $r);
    return trim($r) !== '' ? trim($r) : (string)$razao;
}

/** O centro de custo do grupo 8 é DE OBRA (8.03 Obra-Empreendimento, 8.06 Frota, 8.01 Decorado…);
 *  6.x e 7.x são estrutura da sede (Administrativo, Marketing, Comercial, TI, RH, SAT, Qualidade…). */
function bp_ccusto_de_obra($ccustoCod) {
    return substr(ltrim((string)$ccustoCod), 0, 1) === '8';
}

/** Nome da obra a exibir.
 *  O TOTVS já entrega obra_efetiva_nome/fonte prontos, MAS o rateio é dirigido pelo obra_cod da
 *  solicitação — e uma SC administrativa/marketing carrega um obra_cod que NÃO é canteiro (é a House,
 *  o stand). Por isso, na CAPRETZ (coligada 1) o centro de custo é quem decide:
 *    ccusto 8.x  -> compra de obra          => "CAPRETZ/<obra>"
 *    ccusto 6/7.x-> estrutura da sede       => "CAPRETZ · <área>"  (Administrativo, Marketing, …)
 *  Nas demais coligadas a obra é a própria coligada. */
function bp_obra_label($razao, $fonte, $mapaRazao, $coligadaCod = '', $ccustoCod = '', $ccustoNome = '',
                       $obraCod = '', $mapaObraCod = null) {
    $razao = trim((string)$razao);
    $amigavel = $razao === '' ? '' : ($mapaRazao[bp_nz($razao)] ?? bp_curto($razao));

    if (trim((string)$coligadaCod) === '1') {          // CAPRETZ: sede × rateio p/ obra
        if (bp_ccusto_de_obra($ccustoCod) && strtoupper(trim((string)$fonte)) === 'RATEIO_CAPRETZ') {
            /* O obra_cod da SOLICITAÇÃO manda. A razão social do centro de custo mentia:
               "INSTITUTO CAPREM" onde era Prades, "CPR7 CAMPINAS" onde era Licel. */
            $cod = ltrim(trim((string)$obraCod), '0');
            if ($cod !== '' && is_array($mapaObraCod) && isset($mapaObraCod[$cod]))
                return 'CAPRETZ/' . $mapaObraCod[$cod];
            if ($amigavel !== '' && stripos($amigavel, 'CAPRETZ') === false)
                return 'CAPRETZ/' . $amigavel . ($cod !== '' ? ' (cc ' . $cod . '?)' : '');
        }
        $area = trim((string)$ccustoNome);
        return 'CAPRETZ · ' . ($area !== '' ? $area : 'Sede');
    }
    return $amigavel;
}

/**
 * FLUXO DE APROVAÇÃO (ferramenta Fluig) — normaliza as três colunas que o TOTVS entrega.
 *
 * status_aprovacao: Aprovado | Pendente | Reprovado | Sem vinculo
 * etapa_aprovacao : quando Pendente, diz COM QUEM está parado ("Aguardando Diretor",
 *                   "Aguardando Suprimentos", "Aguardando Coordenador da Obra/Departamento",
 *                   "Aguardando Gestor de Obras"); nos demais casos só repete o status.
 * aprovador       : ⚠️ NÃO é o nome do aprovador. É o ÚLTIMO REGISTRO do Fluig, e vem de duas formas:
 *                   "Aprovado por fulano" (quem moveu a última etapa) OU um texto livre que, nos
 *                   reprovados, é a JUSTIFICATIVA ("pedido errado", "QUANTIDADE MUITO ALTA",
 *                   "Dividir o pedido em 2 partes"). Por isso separamos em `por` e `obs`: mostrar
 *                   "Aprovado por jonathas.vieira" como motivo de uma reprovação pareceria defeito.
 *                   Está preenchido em só 259 dos 9.128 pedidos (172 "Aprovado por" + 87 justificativas).
 */
function bp_aprov($status, $etapa, $aprovador) {
    $s = strtolower(trim((string)$status));
    $k = 'sem';
    if (strpos($s, 'aprovad') === 0)      $k = 'aprovado';
    elseif (strpos($s, 'reprov') === 0)   $k = 'reprovado';
    elseif (strpos($s, 'pend') === 0)     $k = 'pendente';

    $et = trim((string)$etapa);
    if (stripos($et, 'sem vinculo') === 0 || stripos($et, 'sem vínculo') === 0) $et = '';
    if (strcasecmp($et, 'Aprovado') === 0 || strcasecmp($et, 'Reprovado') === 0) $et = '';
    $et = trim(preg_replace('/^aguardando\s+/i', '', $et));   // a coluna já diz que está aguardando

    $a = trim((string)$aprovador); $por = ''; $obs = '';
    if ($a !== '' && $a !== '.') {
        if (preg_match('/^aprovado\s+por\s+(.+)$/i', $a, $m)) $por = trim($m[1]);
        else $obs = $a;
    }
    return ['k' => $k, 'etapa' => $et, 'por' => $por, 'obs' => $obs];
}
function bp_aprov_label($k, $etapa) {
    if ($k === 'aprovado')  return 'Aprovado';
    if ($k === 'reprovado') return 'Reprovado';
    if ($k === 'pendente')  return $etapa !== '' ? ('Aguardando ' . $etapa) : 'Aguardando aprovação';
    return 'Sem fluxo de aprovação';
}

/**
 * ATÉ QUANDO A BASE ESTÁ ATUALIZADA.
 *
 * A base de pedidos NÃO é escrita por este sistema: ela chega do TOTVS por fora (Power Automate).
 * Se esse fluxo parar, a tela continua respondendo — com dado velho e sem dizer nada. Foi por isso
 * que este carimbo existe: "nenhuma compra deste item" é uma frase MUITO diferente conforme a base
 * tenha parado ontem ou há duas semanas.
 *
 * O que dá para saber sem inventar: a data do pedido mais recente que existe na base. Não é a hora
 * da carga (o TOTVS não manda isso), é um piso — a carga é no mínimo tão nova quanto ele. Serve
 * exatamente para o alarme que interessa: parou de entrar pedido.
 *
 * `lte.hoje` porque pedido com data futura existe e congelaria o carimbo numa data que nunca chega.
 */
define('BP_BASE_CACHE', __DIR__ . '/../data/.bp_base_ate.json');
define('BP_BASE_TTL', 3600);       // 1 h: é um rodapé, não vale uma consulta por abertura de quadro

function bp_base_ate() {
    if (is_file(BP_BASE_CACHE) && (time() - filemtime(BP_BASE_CACHE)) < BP_BASE_TTL) {
        $j = @json_decode(@file_get_contents(BP_BASE_CACHE), true);
        if (is_array($j)) return $j;
    }
    $out = ['data' => '', 'dias' => null];
    try {
        $r = bp_get('select=pedido_data&pedido_data=lte.' . date('Y-m-d') . '&order=pedido_data.desc&limit=1');
        $d = substr((string)($r[0]['pedido_data'] ?? ''), 0, 10);
        if ($d === '') return $out;
        $out = ['data' => $d, 'dias' => (int)floor((time() - strtotime($d)) / 86400)];
    } catch (Throwable $e) {
        return $out;   // de propósito NÃO grava: cache de falha esconderia a base fora do ar por 1 h
    }
    @file_put_contents(BP_BASE_CACHE, json_encode($out), LOCK_EX);
    return $out;
}

/**
 * CACHE DOS ÚLTIMOS PREÇOS (server-side).
 *
 * O quadro abre um item por vez e cada abertura custava ~3s de Supabase — medido. Numa cotação de
 * vinte itens, com três pessoas olhando a mesma cotação, é a MESMA consulta repetida: o recorte
 * depende só do código do produto (ou do texto), nunca de quem pergunta. Cabia cache, e o cache do
 * navegador não resolvia porque morria no F5 e não era compartilhado entre as pessoas.
 *
 * TTL de 6h contra uma base que ganha carga ~diária: o pior atraso possível é ver um PC de hoje uma
 * tarde depois, e o botão "atualizar" do rodapé (&recarregar=1) fura o cache para quem não pode
 * esperar. O carimbo da base NÃO entra no arquivo — ele é colado fresco na resposta, senão o aviso
 * de "base parada" chegaria com 6h de atraso, justo o que ele existe para denunciar.
 *
 * Um arquivo por recorte (e não um JSON grande): sem corrida de leitura-e-regravação entre dois
 * compradores, e a expiração é o mtime do próprio arquivo.
 */
define('ULTP_CACHE_DIR', __DIR__ . '/../data/.ultp');
define('ULTP_TTL', 21600);         // 6 h
define('ULTP_MAX_ARQ', 400);       // teto de arquivos na pasta (cada um ~4 KB)

function ultp_cache_ler($chave) {
    $f = ULTP_CACHE_DIR . '/' . $chave . '.json';
    if (!is_file($f) || (time() - filemtime($f)) >= ULTP_TTL) return null;
    $j = @json_decode(@file_get_contents($f), true);
    return is_array($j) ? $j : null;
}

function ultp_cache_gravar($chave, array $payload) {
    if (!is_dir(ULTP_CACHE_DIR)) @mkdir(ULTP_CACHE_DIR, 0775, true);
    if (!is_dir(ULTP_CACHE_DIR)) return;                       // sem permissão de escrita: segue sem cache
    @file_put_contents(ULTP_CACHE_DIR . '/' . $chave . '.json',
                       json_encode($payload, JSON_UNESCAPED_UNICODE), LOCK_EX);
    /* Poda só na gravação (que já é o caminho lento). Apaga um BLOCO acima do teto — apagar um por
       vez faria a pasta viver no limite, varrendo a cada gravação. */
    $fs = @glob(ULTP_CACHE_DIR . '/*.json') ?: [];
    if (count($fs) <= ULTP_MAX_ARQ) return;
    $idade = [];
    foreach ($fs as $f) $idade[$f] = @filemtime($f) ?: 0;
    asort($idade);
    foreach (array_slice(array_keys($idade), 0, count($fs) - ULTP_MAX_ARQ + 50) as $f) @unlink($f);
}

/* Endpoint E biblioteca: o Envio de Pedidos reaproveita bp_varrer/bp_obra_label/bp_aprov em vez de
   copiá-los (duas cópias da regra da CAPRETZ acabariam divergindo, e é ela que decide a obra).
   Quem inclui como biblioteca define BP_LIB_ONLY e o bloco de resposta abaixo não roda. */
if (defined('BP_LIB_ONLY')) return;


/**
 * O QUE JÁ FOI ENVIADO — o livro-caixa do módulo Envio, lido aqui.
 *
 * Pedido do Murilo: "se eu enviar algum pedido, você vai marcar como e-mail já enviado e guardar a
 * data?". Sim — e a fonte é a MESMA tabela que impede o segundo envio (envio_registro). Ler dali,
 * em vez de manter uma marca própria, garante que as duas telas nunca discordem: se aparece
 * "enviado" aqui, aquele pedido não volta para a fila; se não aparece, ele volta.
 */
function bp_enviados($pdo) {
    $m = [];
    try {
        foreach ($pdo->query("SELECT coligada_cod, pedido_numero, destino, para, enviado_em, enviado_por_nome
                              FROM envio_registro ORDER BY enviado_em") as $r) {
            $k = trim((string)$r['coligada_cod']) . '|' . ltrim(trim((string)$r['pedido_numero']), '0');
            $m[$k] = ['em' => $r['enviado_em'], 'para' => $r['para'],
                      'destino' => $r['destino'], 'por' => $r['enviado_por_nome']];
        }
    } catch (Throwable $e) {}          // módulo Envio ainda não criou a tabela: segue sem a coluna
    return $m;
}

try {
    $pdo = db();
    $perms = user_perms($pdo, $_GET['me'] ?? null);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }

    /* ---- ?ultimos=<codprd> : ÚLTIMOS PREÇOS FECHADOS DE UM ITEM ----
       O quadro que abre dentro da cotação e da solicitação. Responde, na hora de cotar, a pergunta
       que hoje ninguém consegue responder sem sair da tela: "quanto a gente já pagou por ISTO, para
       quem, em que obra e quando?".

       A chave é o `codprd` — o mesmo código do TOTVS que a solicitação carrega para o item da
       cotação (cotacao_item.solic_codprd). Casamento exato, sem depender de bater descrição, que é
       onde esse tipo de consulta costuma morrer. Cotação criada do zero não tem código: aí vale o
       fallback por descrição (`termo`), que é aproximado e vem marcado como tal.

       DEDUP: o mesmo PC pode ter várias linhas do mesmo produto (cores/circuitos diferentes — é o
       caso do cabo flexível). Linhas do mesmo pedido com o MESMO preço e a MESMA unidade viram uma
       só, somando a quantidade; preço diferente no mesmo PC continua sendo linha própria, porque aí
       é informação de verdade.

       `recente` marca os preços de até 30 dias: é o status que o contrato dá ao "último preço de
       aquisição" na hierarquia do preço de referência. */
    if (isset($_GET['ultimos'])) {
        $cod   = trim((string)$_GET['ultimos']);
        $termo = trim((string)($_GET['termo'] ?? ''));
        $lim   = max(1, min(20, (int)($_GET['limit'] ?? 5)));
        if ($cod === '' && strlen($termo) < 3) { echo json_encode(['itens' => [], 'total' => 0, 'fonte' => '']); exit; }

        /* O carimbo da base vem sempre fresco (cache próprio de 1h), inclusive no acerto de cache
           abaixo — é ele que denuncia base parada, e denunciar com 6h de atraso não serve. */
        $base   = bp_base_ate();
        $chave  = md5($cod . '|' . $termo . '|' . $lim);
        $forcar = !empty($_GET['recarregar']);
        if (!$forcar && ($c = ultp_cache_ler($chave)) !== null) {
            $c['base_ate'] = $base['data']; $c['base_dias'] = $base['dias']; $c['cache'] = 1;
            echo json_encode($c, JSON_UNESCAPED_UNICODE); exit;
        }

        $sel = 'select=pedido_numero,pedido_data,pedido_status,coligada,coligada_cod,ccusto_cod,ccusto_nome,'
             . 'fornecedor_nome,fornecedor_fantasia,codprd,produto,qtd,und,preco_unit,valor_total,'
             . 'solic_numeros,item_observacao,obra_efetiva_nome,obra_efetiva_fonte,obra_cod';
        $fonte  = $cod !== '' ? 'codprd' : 'descricao';
        $filtro = $cod !== '' ? ('codprd=eq.' . rawurlencode($cod))
                              : ('produto=ilike.' . rawurlencode('*' . $termo . '*'));
        // pede folgado (120) porque o dedup abaixo encolhe o resultado antes de cortar no limite
        $rows = bp_get($sel . '&' . $filtro . '&order=pedido_data.desc,pedido_numero.desc&limit=120');

        $mapaRazao = bp_mapa_razao($pdo); $mapaObraCod = bp_mapa_obracod($pdo);
        $hoje = time(); $ag = [];
        foreach ($rows as $r) {
            $pu = ($r['preco_unit'] ?? null) !== null ? (float)$r['preco_unit'] : null;
            if ($pu === null || $pu <= 0) continue;   // linha sem preço não é referência de nada
            $k = trim((string)$r['coligada_cod']) . '|' . trim((string)$r['pedido_numero']) . '|' . $pu . '|' . trim((string)$r['und']);
            if (!isset($ag[$k])) {
                $data = substr((string)($r['pedido_data'] ?? ''), 0, 10);
                $dias = $data !== '' ? (int)floor(($hoje - strtotime($data)) / 86400) : null;
                $ag[$k] = [
                    'pedido'       => ltrim(trim((string)$r['pedido_numero']), '0') ?: trim((string)$r['pedido_numero']),
                    'pedido_bruto' => trim((string)$r['pedido_numero']),
                    'coligada'     => trim((string)$r['coligada']), 'coligada_cod' => trim((string)$r['coligada_cod']),
                    'data' => $data, 'dias' => $dias, 'recente' => ($dias !== null && $dias <= 30) ? 1 : 0,
                    'obra' => bp_obra_label($r['obra_efetiva_nome'] ?? '', $r['obra_efetiva_fonte'] ?? '', $mapaRazao,
                                            $r['coligada_cod'] ?? '', $r['ccusto_cod'] ?? '', $r['ccusto_nome'] ?? '',
                                            $r['obra_cod'] ?? '', $mapaObraCod),
                    'fornecedor'      => trim((string)($r['fornecedor_fantasia'] ?: $r['fornecedor_nome'])),
                    'fornecedor_nome' => trim((string)$r['fornecedor_nome']),
                    'codprd'  => trim((string)($r['codprd'] ?? '')), 'produto' => trim((string)$r['produto']),
                    'preco_unit' => $pu, 'und' => trim((string)$r['und']), 'qtd' => 0.0,
                    'status' => trim((string)($r['pedido_status'] ?? '')),
                    'solic'  => trim((string)($r['solic_numeros'] ?? '')), 'observacao' => '',
                ];
            }
            $ag[$k]['qtd'] += (float)($r['qtd'] ?? 0);
            /* Observação: junta as das linhas fundidas, mas com TETO. Em concreto, cada linha traz o
               romaneio inteiro ("Pilares do 12º ao 14º Pavimento — Torre 1…") e a concatenação virava
               um parágrafo que dominava a tabela. O texto completo continua no pedido (botão do PC). */
            $ob = trim((string)($r['item_observacao'] ?? ''));
            // strlen (bytes), não mb_strlen: a hospedagem não tem mbstring — aqui é só um teto, byte serve
            if ($ob !== '' && strpos($ag[$k]['observacao'], $ob) === false && strlen($ag[$k]['observacao']) < 400)
                $ag[$k]['observacao'] = $ag[$k]['observacao'] === '' ? $ob : ($ag[$k]['observacao'] . ' · ' . $ob);
        }
        $itens = array_values($ag);
        usort($itens, fn($a, $b) => strcmp((string)$b['data'], (string)$a['data']) ?: ($b['pedido'] <=> $a['pedido']));
        $payload = ['itens' => array_slice($itens, 0, $lim), 'total' => count($itens),
                    'fonte' => $fonte, 'codprd' => $cod];
        ultp_cache_gravar($chave, $payload);      // sem o carimbo da base: ele é colado na resposta
        $payload['base_ate'] = $base['data']; $payload['base_dias'] = $base['dias']; $payload['cache'] = 0;
        echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit;
    }

    // ---- ?obras=1 : lista pro filtro, montada a partir dos PRÓPRIOS PEDIDOS ----
    // Antes o dropdown vinha da ficha de obras e sumia com tudo que não tinha de-para (faltava obra).
    // Aqui aparece exatamente o que existe em pedido — inclusive as áreas da sede da CAPRETZ.
    if (isset($_GET['obras'])) {
        // v2 no nome: a chave do rateio mudou de rótulo p/ centro de custo — o cache velho tem
        // chaves que o filtro novo não entende. Varrer 15 mil linhas a cada abertura da tela é caro.
        $cache = __DIR__ . '/../data/.bp_obras2.json';
        if (empty($_GET['recarregar']) && is_file($cache) && (time() - filemtime($cache)) < 1800) {
            echo file_get_contents($cache); exit;
        }
        $mapaRazao   = bp_mapa_razao($pdo);
        $mapaObraCod = bp_mapa_obracod($pdo);
        $agg = [];
        bp_varrer('select=obra_efetiva_nome,obra_efetiva_fonte,coligada_cod,ccusto_cod,ccusto_nome,obra_cod'
                  . '&order=obra_efetiva_nome.asc,coligada_cod.asc,ccusto_cod.asc',
            function (array $lote) use (&$agg, $mapaRazao, $mapaObraCod) {
                foreach ($lote as $r) {
                    $cc    = trim((string)($r['coligada_cod'] ?? ''));
                    $razao = trim((string)($r['obra_efetiva_nome'] ?? ''));
                    $lbl   = bp_obra_label($razao, $r['obra_efetiva_fonte'] ?? '', $mapaRazao,
                                           $cc, $r['ccusto_cod'] ?? '', $r['ccusto_nome'] ?? '',
                                           $r['obra_cod'] ?? '', $mapaObraCod);
                    if ($lbl === '') continue;
                    if ($cc === '1' && strpos($lbl, 'CAPRETZ · ') === 0) {
                        $chave = 'C:' . trim((string)($r['ccusto_cod'] ?? ''));   // área da sede
                    } else {
                        // A obra entra pelo NOME LIMPO: o filtro por razão social traz a compra direta E o
                        // rateio da CAPRETZ, então rotular "CAPRETZ/San Pietro" aqui mentiria sobre o conjunto.
                        /* O rateio da CAPRETZ é resolvido pelo obra_cod, então a CHAVE do filtro é o
                           CENTRO DE CUSTO cru ("O:032") — só a razão social juntaria Prades e Licel num
                           item só, e o rótulo não volta ao TOTVS como filtro (a opção aparecia no
                           dropdown e escolher não filtrava nada). */
                        $codObra  = trim((string)($r['obra_cod'] ?? ''));
                        $ehRateio = ($cc === '1' && strpos($lbl, 'CAPRETZ/') === 0 && $codObra !== '');
                        $chave = $ehRateio ? ('O:' . $codObra) : ('R:' . $razao);
                        if (!$ehRateio) $lbl = bp_obra_label($razao, 'COLIGADA', $mapaRazao);
                    }
                    if (!isset($agg[$chave])) $agg[$chave] = ['chave' => $chave, 'label' => $lbl, 'n' => 0];
                    $agg[$chave]['n']++;
                }
            });
        $lista = array_values($agg);
        usort($lista, function ($a, $b) { return strcmp(bp_nz($a['label']), bp_nz($b['label'])); });
        $json = json_encode(['obras' => $lista], JSON_UNESCAPED_UNICODE);
        @file_put_contents($cache, $json);
        echo $json;
        exit;
    }

    $q       = trim((string)($_GET['q'] ?? ''));
    $obraKey = trim((string)($_GET['obra'] ?? ''));   // "R:<razão social>" | "C:<centro de custo CAPRETZ>"
    $obraId  = (int)($_GET['obra_id'] ?? 0);
    $periodo = (string)($_GET['periodo'] ?? '3m');
    $sort    = (string)($_GET['sort'] ?? 'data');     // coluna clicada: numero|obra|fornecedor|itens|data|valor|status
    $dir     = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
    $status  = strtoupper(trim((string)($_GET['status'] ?? '')));
    $usuario = trim((string)($_GET['usuario'] ?? ''));
    $pagina  = max(1, (int)($_GET['pagina'] ?? 1));

    // ---- filtros da query ao TOTVS ----
    $f = [];
    // período (default 3 meses — consulta ampla demais pesa e raramente serve no dia a dia)
    $de = trim((string)($_GET['de'] ?? '')); $ate = trim((string)($_GET['ate'] ?? ''));
    if ($de === '' && $ate === '') {
        if ($periodo === '30d')      $de = date('Y-m-d', strtotime('-30 days'));
        elseif ($periodo === '3m')   $de = date('Y-m-d', strtotime('-3 months'));
        elseif ($periodo === 'ano')  $de = date('Y') . '-01-01';
        // 'tudo' → sem corte
    }
    if ($de !== '')  $f[] = 'pedido_data=gte.' . rawurlencode($de);
    if ($ate !== '') $f[] = 'pedido_data=lte.' . rawurlencode($ate);
    if ($status !== '') $f[] = 'pedido_status=eq.' . rawurlencode($status);   // filtro de status (A/B/C/F/G/N/Q/R/U)
    // aprovação: o valor do filtro é a chave curta (aprovado/pendente/reprovado/sem); traduzimos p/ o
    // texto que o TOTVS grava. 'sem' casa por prefixo porque a coluna vem "Sem vinculo" (sem acento).
    $aprov = strtolower(trim((string)($_GET['aprovacao'] ?? '')));
    if ($aprov !== '') {
        $mapa = ['aprovado' => 'Aprovado', 'pendente' => 'Pendente', 'reprovado' => 'Reprovado', 'sem' => 'Sem vinculo'];
        if (isset($mapa[$aprov])) $f[] = 'status_aprovacao=eq.' . rawurlencode($mapa[$aprov]);
    }
    $etapaF = trim((string)($_GET['etapa'] ?? ''));   // "com quem está parado" (só faz sentido em Pendente)
    if ($etapaF !== '') $f[] = 'etapa_aprovacao=eq.' . rawurlencode($etapaF);
    if ($usuario !== '') $f[] = 'pedido_usuario=eq.' . rawurlencode($usuario);   // quem CRIOU o pedido no TOTVS

    // obra escolhida no filtro (chave vinda de ?obras=1)
    $soObraDeVerdade = false;   // "R:" não pode trazer a sede que só compartilha a razão social
    if ($obraKey !== '') {
        if (strpos($obraKey, 'C:') === 0) {          // área da sede da CAPRETZ (Administrativo, Marketing…)
            $f[] = 'coligada_cod=eq.1';
            $f[] = 'ccusto_cod=eq.' . rawurlencode(substr($obraKey, 2));
        } elseif (strpos($obraKey, 'O:') === 0) {    // obra que compra pela CAPRETZ — pelo centro de custo da SC
            $f[] = 'coligada_cod=eq.1';
            $f[] = 'obra_cod=eq.' . rawurlencode(substr($obraKey, 2));
        } elseif (strpos($obraKey, 'R:') === 0) {    // obra, pela razão social — pega direto e rateio
            $f[] = 'obra_efetiva_nome=eq.' . rawurlencode(substr($obraKey, 2));
            $soObraDeVerdade = true;
        }
    }
    // (compat) obra_id da ficha: casa pela RAZÃO SOCIAL que o TOTVS já resolve (obra_efetiva_nome)
    elseif ($obraId > 0) {
        // ⚠️ NUNCA "id=? OR radar_obra_id=?": os dois espaços de id colidem (ficha 9=Itaara × radar 9=Vitrius).
        $st = $pdo->prepare("SELECT nome, coligada_nome, coligada_cod, compra_coligada_cod FROM obra_ficha WHERE id=? LIMIT 1");
        $st->execute([$obraId]); $o = $st->fetch();
        if (!$o) { $st = $pdo->prepare("SELECT nome, coligada_nome, coligada_cod, compra_coligada_cod FROM obra_ficha WHERE radar_obra_id=? LIMIT 1"); $st->execute([$obraId]); $o = $st->fetch(); }
        if ($o) {
            $razao = trim((string)($o['coligada_nome'] ?? ''));
            if ($razao !== '') $f[] = 'obra_efetiva_nome=eq.' . rawurlencode($razao);
            else { $cc = trim((string)($o['compra_coligada_cod'] ?: $o['coligada_cod'])); if ($cc !== '') $f[] = 'coligada_cod=eq.' . rawurlencode($cc); }
        }
    }

    // busca ampla: nº do pedido OU fornecedor (razão/fantasia) OU descrição do item
    if ($q !== '') {
        $t = str_replace(['*', ',', '(', ')'], ' ', $q);
        $like = '*' . rawurlencode($t) . '*';
        $ors = ['produto.ilike.' . $like, 'item_observacao.ilike.' . $like,   // observação = descrição detalhada digitada à mão
                'fornecedor_nome.ilike.' . $like, 'fornecedor_fantasia.ilike.' . $like,
                'pedido_numero.ilike.' . $like, 'codprd.ilike.' . $like, 'pedido_usuario.ilike.' . $like];
        $f[] = 'or=(' . implode(',', $ors) . ')';
    }

    $sel = 'select=pedido_numero,pedido_data,pedido_status,coligada_cod,coligada,ccusto_cod,fornecedor_cod,fornecedor_nome,fornecedor_fantasia,produto,qtd,und,preco_unit,valor_total,solic_numeros,solic_colidmov,pedido_usuario,item_observacao,obra_efetiva_nome,obra_efetiva_fonte,obra_cod,ccusto_nome,status_aprovacao,etapa_aprovacao,aprovador';
    $mapaRazao   = bp_mapa_razao($pdo);
    $mapaObraCod = bp_mapa_obracod($pdo);

    // ---- agrega item → PEDIDO (chave: coligada + número; o nº se repete entre coligadas) ----
    // A agregação roda A CADA PÁGINA que chega: nada de segurar as 15 mil linhas cruas na memória.
    /* ⚠️ O use() PRECISA levar $mapaObraCod. Faltava — e como o PHP resolve variável não capturada
       como null, o bp_obra_label caía no fallback da razão social sem reclamar: o PC 33744 da Licel
       saía "CAPRETZ/San Pietro (cc 32?)" mesmo com o de-para certo carregado logo acima. O "(cc N?)"
       no rótulo é justamente o sinal de que o mapa não chegou (ou que o centro de custo é novo). */
    $ped = []; $uSet = [];
    $agrega = function (array $lote) use (&$ped, &$uSet, $mapaRazao, $mapaObraCod) {
        foreach ($lote as $r) {
            $u = trim((string)($r['pedido_usuario'] ?? ''));
            if ($u !== '') $uSet[$u] = true;   // alimenta o filtro "quem criou" da tela
            $cc = (string)($r['coligada_cod'] ?? ''); $pn = (string)($r['pedido_numero'] ?? '');
            if ($pn === '') continue;
            $k = $cc . '|' . $pn;
            if (!isset($ped[$k])) {
                $ped[$k] = ['numero' => $pn, 'coligada_cod' => $cc,
                    'coligada' => (trim((string)($r['coligada'] ?? '')) ?: coligada_nome($cc)),
                    'obra' => bp_obra_label($r['obra_efetiva_nome'] ?? '', $r['obra_efetiva_fonte'] ?? '', $mapaRazao,
                                            $cc, $r['ccusto_cod'] ?? '', $r['ccusto_nome'] ?? '',
                                            $r['obra_cod'] ?? '', $mapaObraCod),
                    'data' => (string)($r['pedido_data'] ?? ''), 'status' => (string)($r['pedido_status'] ?? ''),
                    'ccusto_cod' => (string)($r['ccusto_cod'] ?? ''), 'solic' => trim((string)($r['solic_numeros'] ?? '')),
                    'colidmov' => trim((string)($r['solic_colidmov'] ?? '')),
                    'centro_custo' => trim((string)($r['obra_cod'] ?? '')), 'obra_fonte' => trim((string)($r['obra_efetiva_fonte'] ?? '')),
                    'obra_razao' => trim((string)($r['obra_efetiva_nome'] ?? '')), 'ccusto_nome' => trim((string)($r['ccusto_nome'] ?? '')),
                    'usuario' => $u, 'obs' => [],
                    'aprov_raw' => trim((string)($r['status_aprovacao'] ?? '')),
                    'aprov_etapa_raw' => trim((string)($r['etapa_aprovacao'] ?? '')),
                    'aprov_reg' => trim((string)($r['aprovador'] ?? '')),
                    'fornecedores' => [], 'n_itens' => 0, 'total' => 0.0, 'amostra' => []];
            }
            // o colidmov pode vir só em ALGUNS itens do pedido — guarda o 1º que aparecer (é a chave p/ achar a SC)
            if (($ped[$k]['colidmov'] ?? '') === '' && trim((string)($r['solic_colidmov'] ?? '')) !== '') $ped[$k]['colidmov'] = trim((string)$r['solic_colidmov']);
            if (($ped[$k]['solic'] ?? '') === '' && trim((string)($r['solic_numeros'] ?? '')) !== '') $ped[$k]['solic'] = trim((string)$r['solic_numeros']);
            $pu = (float)($r['preco_unit'] ?? 0); $qt = (float)($r['qtd'] ?? 0); $vt = (float)($r['valor_total'] ?? 0);
            $ped[$k]['total'] += $vt > 0 ? $vt : ($pu * $qt);
            $ped[$k]['n_itens']++;
            $fn = trim((string)($r['fornecedor_fantasia'] ?? '')) ?: trim((string)($r['fornecedor_nome'] ?? ''));
            if ($fn !== '') $ped[$k]['fornecedores'][$fn] = true;
            if (count($ped[$k]['amostra']) < 3) $ped[$k]['amostra'][] = (string)($r['produto'] ?? '');
            $ob = trim((string)($r['item_observacao'] ?? ''));
            if ($ob !== '' && count($ped[$k]['obs']) < 3 && !in_array($ob, $ped[$k]['obs'], true)) $ped[$k]['obs'][] = $ob;
            if (($ped[$k]['usuario'] ?? '') === '' && $u !== '') $ped[$k]['usuario'] = $u;
        }
    };

    // ordem com desempate determinístico: paginar por offset com muitas datas repetidas embaralharia
    // as linhas entre as páginas (item duplicado numa, sumido noutra).
    $lidos = bp_varrer($sel . ($f ? '&' . implode('&', $f) : '') . '&order=pedido_data.desc,pedido_colidmov.desc,pedido_numero.desc,seq.asc', $agrega);
    $truncado = $lidos >= BP_MAX_LINHAS;

    $usuariosLista = array_keys($uSet); sort($usuariosLista, SORT_NATURAL | SORT_FLAG_CASE);
    $lista = [];
    foreach ($ped as $p) {
        // filtrando por OBRA: fora os pedidos da sede que só herdaram a razão social pelo rateio
        // (SC de Marketing/Administrativo apontando p/ a House não é compra da obra).
        if ($soObraDeVerdade && strpos((string)$p['obra'], 'CAPRETZ · ') === 0) continue;
        $p['fornecedores'] = array_keys($p['fornecedores']); $p['total'] = round($p['total'], 2);
        $p['status_label'] = bp_status_label($p['status']);
        $ap = bp_aprov($p['aprov_raw'], $p['aprov_etapa_raw'], $p['aprov_reg']);
        $p['aprovacao']        = $ap['k'];
        $p['aprovacao_label']  = bp_aprov_label($ap['k'], $ap['etapa']);
        $p['aprovacao_etapa']  = $ap['etapa'];
        $p['aprovado_por']     = $ap['por'];
        $p['aprovacao_obs']    = $ap['obs'];
        // guarda o texto CRU ("Aguardando Diretor"): e ele que o filtro ?etapa= compara no TOTVS.
        // O rotulo curto ("Diretor") serve so p/ exibir — filtrar por ele nao casaria nada.
        $p['aprovacao_etapa_raw'] = $p['aprov_etapa_raw'];
        unset($p['aprov_raw'], $p['aprov_etapa_raw'], $p['aprov_reg']);
        $lista[] = $p;
    }

    /* MESMA COMPRA REPARTIDA ENTRE OBRAS.
       Um serviço contratado uma vez e dividido entre obras vira N pedidos idênticos, um por obra
       (ex.: cerca de R$ 152.000 dividida entre as 7 obras do Vilas = 7 PCs de R$ 21.714,30). Sem
       marcação isso parece pedido duplicado.
       Chave = fornecedor + valor. A data NÃO entra na chave porque as obras não emitem no mesmo dia
       (o PC 311 do Vilas saiu em 28/07 e os outros cinco em 27/07 — pela data ele ficava de fora).
       Mas a data também não pode ser ignorada: o mesmo prestador cobra o mesmo valor de obras
       diferentes em MESES diferentes, e aí não é rateio. Então agrupamos por proximidade: pedidos da
       mesma chave separados por mais de JANELA dias viram grupos distintos.
       Só marcamos quando são OBRAS DIFERENTES — obra repetida seria repetição de verdade. */
    define('BP_RATEIO_JANELA_DIAS', 15);
    $porChave = [];
    foreach ($lista as $i => $p) {
        $forn = implode(',', (array)$p['fornecedores']);
        $dt   = substr((string)$p['data'], 0, 10);
        if ($forn === '' || !$p['total'] || $dt === '') continue;
        $porChave[$forn . '|' . number_format((float)$p['total'], 2, '.', '')][] = ['i' => $i, 't' => strtotime($dt)];
    }
    foreach ($porChave as $itens) {
        if (count($itens) < 2) continue;
        usort($itens, fn($a, $b) => $a['t'] <=> $b['t']);
        $bloco = [];
        $fechar = function ($bloco) use (&$lista) {
            $obras = [];
            foreach ($bloco as $x) $obras[(string)$lista[$x['i']]['obra']] = 1;
            if (count($bloco) < 2 || count($obras) < 2) return;
            $n = count($bloco); $pos = 0;
            foreach ($bloco as $x) {
                $lista[$x['i']]['repartido_n'] = $n;
                $lista[$x['i']]['repartido_i'] = ++$pos;
                $lista[$x['i']]['repartido_obras'] = array_keys($obras);
            }
        };
        foreach ($itens as $it) {
            if ($bloco && ($it['t'] - end($bloco)['t']) > BP_RATEIO_JANELA_DIAS * 86400) { $fechar($bloco); $bloco = []; }
            $bloco[] = $it;
        }
        $fechar($bloco);
    }

    // contagem por situação de aprovação do RECORTE INTEIRO (alimenta os chips-filtro da tela)
    $resumoAprov = ['aprovado'=>0, 'pendente'=>0, 'reprovado'=>0, 'sem'=>0];
    $etapasSet = [];
    foreach ($lista as $p) {
        $resumoAprov[$p['aprovacao']] = ($resumoAprov[$p['aprovacao']] ?? 0) + 1;
        if ($p['aprovacao'] === 'pendente' && $p['aprovacao_etapa'] !== '') {
            $ek = $p['aprovacao_etapa_raw'];
            if (!isset($etapasSet[$ek])) $etapasSet[$ek] = ['etapa'=>$p['aprovacao_etapa'], 'etapa_raw'=>$ek, 'n'=>0, 'valor'=>0.0];
            $etapasSet[$ek]['n']++; $etapasSet[$ek]['valor'] += (float)$p['total'];
        }
    }
    uasort($etapasSet, fn($a, $b) => $b['n'] <=> $a['n']);
    $etapasLista = array_values(array_map(fn($e) => $e + ['valor'=>round($e['valor'], 2)], $etapasSet));

    // ---- ORDENAÇÃO por coluna, sobre a LISTA INTEIRA (não só a página) — depois é que pagina ----
    $cmp = [
        'numero'     => fn($a, $b) => ((int)ltrim($a['numero'], '0')) <=> ((int)ltrim($b['numero'], '0')),
        // ordem de urgência, não alfabética: reprovado e parado vêm primeiro — é o que exige ação
        'aprovacao'  => function ($a, $b) { $o = ['reprovado'=>0, 'pendente'=>1, 'sem'=>2, 'aprovado'=>3];
                                            return ($o[$a['aprovacao']] ?? 9) <=> ($o[$b['aprovacao']] ?? 9); },
        'obra'       => fn($a, $b) => strcasecmp($a['obra'] ?: $a['coligada'], $b['obra'] ?: $b['coligada']),
        'fornecedor' => fn($a, $b) => strcasecmp($a['fornecedores'][0] ?? '', $b['fornecedores'][0] ?? ''),
        'itens'      => fn($a, $b) => $a['n_itens'] <=> $b['n_itens'],
        'data'       => fn($a, $b) => strcmp($a['data'], $b['data']) ?: (((int)ltrim($a['numero'], '0')) <=> ((int)ltrim($b['numero'], '0'))),
        'valor'      => fn($a, $b) => $a['total'] <=> $b['total'],
        'status'     => fn($a, $b) => strcasecmp($a['status_label'], $b['status_label']),
        'usuario'    => fn($a, $b) => strcasecmp($a['usuario'] ?? '', $b['usuario'] ?? ''),
    ];
    $fn = $cmp[$sort] ?? $cmp['data'];
    usort($lista, $dir === 'asc' ? $fn : fn($a, $b) => -$fn($a, $b));

    $total = count($lista);
    $paginas = max(1, (int)ceil($total / BP_POR_PAGINA));
    if ($pagina > $paginas) $pagina = $paginas;
    $page = array_slice($lista, ($pagina - 1) * BP_POR_PAGINA, BP_POR_PAGINA);

    /* Marca de ENVIADO — só na página que vai para a tela, para não custar nada nas outras.
       A fonte é o livro-caixa do módulo Envio: a mesma tabela que impede o segundo envio. */
    $enviados = bp_enviados($pdo);
    if ($enviados) foreach ($page as &$pp) {
        $k = trim((string)($pp['coligada_cod'] ?? '')) . '|' . ltrim((string)($pp['numero'] ?? ''), '0');
        if (isset($enviados[$k])) $pp['enviado'] = $enviados[$k];
    }
    unset($pp);

    echo json_encode(['ok' => true, 'pedidos' => $page, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas,
        'por_pagina' => BP_POR_PAGINA, 'itens_lidos' => $lidos, 'truncado' => $truncado,
        'sort' => $sort, 'dir' => $dir, 'status' => $status, 'usuario' => $usuario, 'usuarios' => $usuariosLista,
        'aprovacao' => $aprov, 'resumo_aprovacao' => $resumoAprov, 'etapas' => $etapasLista,
        'periodo' => ['de' => $de, 'ate' => $ate]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
