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

define('BP_MAX_LINHAS', 30000);  // teto de itens lidos por consulta (protege o Supabase e a memória)
define('BP_PAGINA_API', 1000);   // teto por resposta do PostgREST (max-rows) — pedir mais não adianta
define('BP_PARALELO', 8);        // páginas buscadas ao mesmo tempo (16 páginas em série levavam 20s)
define('BP_POR_PAGINA', 30);

function bp_url($query) { return SOLIC_SUPABASE_URL . '/rest/v1/pedidos_itens?' . $query; }
function bp_headers() {
    return ['apikey: ' . SOLIC_SUPABASE_KEY, 'Authorization: Bearer ' . SOLIC_SUPABASE_KEY, 'Accept: application/json'];
}

function bp_get($query) {
    [$code, $res, $err] = sb_http('GET', bp_url($query), bp_headers());
    if ($code !== 200 && $code !== 206) throw new Exception('TOTVS HTTP ' . $code . ' — ' . substr((string)($res ?: $err), 0, 160));
    return json_decode((string)$res, true) ?: [];
}

/** Quantas linhas o recorte tem, sem baixar nada (Prefer: count=exact -> "content-range: 0-0/15363"). */
function bp_contar($query) {
    $ch = curl_init(bp_url($query . '&limit=1'));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_HEADER => 1, CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => array_merge(bp_headers(), ['Prefer: count=exact'])]);
    $r = curl_exec($ch); curl_close($ch);
    if (is_string($r) && preg_match('#content-range:\s*\S+/(\d+)#i', $r, $m)) return (int)$m[1];
    return -1;   // servidor não informou: cai no modo sequencial
}

/** Lê o recorte INTEIRO, entregando página por página a um callback. Devolve quantas linhas passaram.
 *  Sem isso "todas as obras" vinha cortado em 1000 itens e a contagem mentia.
 *  ⚠️ O PostgREST tem teto próprio por resposta e ignora limit maior — daí paginar de 1000 em 1000.
 *  Duas razões p/ não juntar tudo num array: 16 idas em série levavam 20s (agora vão em paralelo),
 *  e segurar 15 mil linhas cruas custava ~54MB de RAM — agregando por página o pico cai muito. */
function bp_varrer($query, callable $cb) {
    $total = bp_contar($query);
    if ($total === 0) return 0;

    if ($total < 0) {   // servidor não informou a contagem: sequencial, para quando a página vier incompleta
        $n = 0;
        for ($off = 0; $off < BP_MAX_LINHAS; $off += BP_PAGINA_API) {
            $lote = bp_get($query . '&limit=' . BP_PAGINA_API . '&offset=' . $off);
            $n += count($lote); $cb($lote);
            if (count($lote) < BP_PAGINA_API) break;
        }
        return $n;
    }

    $alvo = min($total, BP_MAX_LINHAS);
    $offs = [];
    for ($off = 0; $off < $alvo; $off += BP_PAGINA_API) $offs[] = $off;

    $n = 0;
    foreach (array_chunk($offs, BP_PARALELO) as $bloco) {
        $mh = curl_multi_init(); $hs = [];
        foreach ($bloco as $off) {
            $ch = curl_init(bp_url($query . '&limit=' . BP_PAGINA_API . '&offset=' . $off));
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 40, CURLOPT_HTTPHEADER => bp_headers()]);
            curl_multi_add_handle($mh, $ch); $hs[$off] = $ch;
        }
        do { $st = curl_multi_exec($mh, $rodando); if ($rodando) curl_multi_select($mh, 1.0); }
        while ($rodando && $st === CURLM_OK);

        foreach ($hs as $off => $ch) {
            $body = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch); curl_close($ch);
            if ($code !== 200 && $code !== 206) throw new Exception('TOTVS HTTP ' . $code . ' na página ' . $off);
            $lote = json_decode((string)$body, true);
            if (is_array($lote)) { $n += count($lote); $cb($lote); }
            unset($body, $lote);
        }
        curl_multi_close($mh);
    }
    return $n;
}

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
function bp_obra_label($razao, $fonte, $mapaRazao, $coligadaCod = '', $ccustoCod = '', $ccustoNome = '') {
    $razao = trim((string)$razao);
    $amigavel = $razao === '' ? '' : ($mapaRazao[bp_nz($razao)] ?? bp_curto($razao));

    if (trim((string)$coligadaCod) === '1') {          // CAPRETZ: sede × rateio p/ obra
        if (bp_ccusto_de_obra($ccustoCod) && strtoupper(trim((string)$fonte)) === 'RATEIO_CAPRETZ'
            && $amigavel !== '' && stripos($amigavel, 'CAPRETZ') === false) {
            return 'CAPRETZ/' . $amigavel;
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

/* Endpoint E biblioteca: o Envio de Pedidos reaproveita bp_varrer/bp_obra_label/bp_aprov em vez de
   copiá-los (duas cópias da regra da CAPRETZ acabariam divergindo, e é ela que decide a obra).
   Quem inclui como biblioteca define BP_LIB_ONLY e o bloco de resposta abaixo não roda. */
if (defined('BP_LIB_ONLY')) return;

try {
    $pdo = db();
    $perms = user_perms($pdo, $_GET['me'] ?? null);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }

    // ---- ?obras=1 : lista pro filtro, montada a partir dos PRÓPRIOS PEDIDOS ----
    // Antes o dropdown vinha da ficha de obras e sumia com tudo que não tinha de-para (faltava obra).
    // Aqui aparece exatamente o que existe em pedido — inclusive as áreas da sede da CAPRETZ.
    if (isset($_GET['obras'])) {
        $cache = __DIR__ . '/../data/.bp_obras.json';   // varrer 15 mil linhas a cada abertura da tela é caro
        if (empty($_GET['recarregar']) && is_file($cache) && (time() - filemtime($cache)) < 1800) {
            echo file_get_contents($cache); exit;
        }
        $mapaRazao = bp_mapa_razao($pdo);
        $agg = [];
        bp_varrer('select=obra_efetiva_nome,obra_efetiva_fonte,coligada_cod,ccusto_cod,ccusto_nome'
                  . '&order=obra_efetiva_nome.asc,coligada_cod.asc,ccusto_cod.asc',
            function (array $lote) use (&$agg, $mapaRazao) {
                foreach ($lote as $r) {
                    $cc    = trim((string)($r['coligada_cod'] ?? ''));
                    $razao = trim((string)($r['obra_efetiva_nome'] ?? ''));
                    $lbl   = bp_obra_label($razao, $r['obra_efetiva_fonte'] ?? '', $mapaRazao,
                                           $cc, $r['ccusto_cod'] ?? '', $r['ccusto_nome'] ?? '');
                    if ($lbl === '') continue;
                    if ($cc === '1' && strpos($lbl, 'CAPRETZ · ') === 0) {
                        $chave = 'C:' . trim((string)($r['ccusto_cod'] ?? ''));   // área da sede
                    } else {
                        // A obra entra pelo NOME LIMPO: o filtro por razão social traz a compra direta E o
                        // rateio da CAPRETZ, então rotular "CAPRETZ/San Pietro" aqui mentiria sobre o conjunto.
                        $chave = 'R:' . $razao;
                        $lbl   = bp_obra_label($razao, 'COLIGADA', $mapaRazao);
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
    $mapaRazao = bp_mapa_razao($pdo);

    // ---- agrega item → PEDIDO (chave: coligada + número; o nº se repete entre coligadas) ----
    // A agregação roda A CADA PÁGINA que chega: nada de segurar as 15 mil linhas cruas na memória.
    $ped = []; $uSet = [];
    $agrega = function (array $lote) use (&$ped, &$uSet, $mapaRazao) {
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
                                            $cc, $r['ccusto_cod'] ?? '', $r['ccusto_nome'] ?? ''),
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

    echo json_encode(['ok' => true, 'pedidos' => $page, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas,
        'por_pagina' => BP_POR_PAGINA, 'itens_lidos' => $lidos, 'truncado' => $truncado,
        'sort' => $sort, 'dir' => $dir, 'status' => $status, 'usuario' => $usuario, 'usuarios' => $usuariosLista,
        'aprovacao' => $aprov, 'resumo_aprovacao' => $resumoAprov, 'etapas' => $etapasLista,
        'periodo' => ['de' => $de, 'ate' => $ate]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
