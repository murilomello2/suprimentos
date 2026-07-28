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

    $sel = 'select=pedido_numero,pedido_data,pedido_status,coligada_cod,coligada,ccusto_cod,fornecedor_cod,fornecedor_nome,fornecedor_fantasia,produto,qtd,und,preco_unit,valor_total,solic_numeros,solic_colidmov,pedido_usuario,item_observacao,obra_efetiva_nome,obra_efetiva_fonte,obra_cod,ccusto_nome';
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
        $lista[] = $p;
    }

    // ---- ORDENAÇÃO por coluna, sobre a LISTA INTEIRA (não só a página) — depois é que pagina ----
    $cmp = [
        'numero'     => fn($a, $b) => ((int)ltrim($a['numero'], '0')) <=> ((int)ltrim($b['numero'], '0')),
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
        'periodo' => ['de' => $de, 'ate' => $ate]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
