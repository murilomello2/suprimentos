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

define('BP_MAX_LINHAS', 6000);   // teto de itens lidos por consulta (protege o Supabase e a memória)
define('BP_POR_PAGINA', 30);

function bp_get($query) {
    $url = SOLIC_SUPABASE_URL . '/rest/v1/pedidos_itens?' . $query;
    $headers = ['apikey: ' . SOLIC_SUPABASE_KEY, 'Authorization: Bearer ' . SOLIC_SUPABASE_KEY, 'Accept: application/json'];
    [$code, $res, $err] = sb_http('GET', $url, $headers);
    if ($code !== 200 && $code !== 206) throw new Exception('TOTVS HTTP ' . $code . ' — ' . substr((string)($res ?: $err), 0, 160));
    return json_decode((string)$res, true) ?: [];
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
    $r = preg_replace('/\s+(EMPREENDIMENTOS?|EMPREEND\.?)\s+IMOBILI.*/iu', '', (string)$razao);
    $r = preg_replace('/\s+(SPE\s+)?LTDA\.?$/iu', '', $r);
    return trim($r) !== '' ? trim($r) : (string)$razao;
}

/** Nome da obra a exibir: usa o obra_efetiva_nome do TOTVS (já resolve o rateio da CAPRETZ no DAX).
 *  RATEIO_CAPRETZ => "CAPRETZ/<obra>" (compra da CAPRETZ rateada p/ a obra); senão o nome da obra. */
function bp_obra_label($razao, $fonte, $mapaRazao) {
    $razao = trim((string)$razao);
    if ($razao === '') return '';
    $amigavel = $mapaRazao[bp_nz($razao)] ?? bp_curto($razao);
    if (strtoupper(trim((string)$fonte)) === 'RATEIO_CAPRETZ') return 'CAPRETZ/' . $amigavel;
    return $amigavel;
}

try {
    $pdo = db();
    $perms = user_perms($pdo, $_GET['me'] ?? null);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }

    $q       = trim((string)($_GET['q'] ?? ''));
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

    // obra: casa pela RAZÃO SOCIAL que o TOTVS já resolve (obra_efetiva_nome) — cobre inclusive o
    // rateio da CAPRETZ (pedido da CAPRETZ p/ o Cajá vira "CAJA EMPREENDIMENTO…", não "CAPRETZ").
    if ($obraId > 0) {
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
    $rows = bp_get($sel . ($f ? '&' . implode('&', $f) : '') . '&order=pedido_data.desc&limit=' . BP_MAX_LINHAS);
    $truncado = count($rows) >= BP_MAX_LINHAS;

    $mapaRazao = bp_mapa_razao($pdo);
    // usuários presentes no recorte (alimenta o filtro "quem criou" da tela)
    $uSet = [];
    foreach ($rows as $r) { $u = trim((string)($r['pedido_usuario'] ?? '')); if ($u !== '') $uSet[$u] = true; }
    $usuariosLista = array_keys($uSet); sort($usuariosLista, SORT_NATURAL | SORT_FLAG_CASE);

    // ---- agrega item → PEDIDO (chave: coligada + número; o nº se repete entre coligadas) ----
    $ped = [];
    foreach ($rows as $r) {
        $cc = (string)($r['coligada_cod'] ?? ''); $pn = (string)($r['pedido_numero'] ?? '');
        if ($pn === '') continue;
        $k = $cc . '|' . $pn;
        if (!isset($ped[$k])) {
            $obraNome = bp_obra_label($r['obra_efetiva_nome'] ?? '', $r['obra_efetiva_fonte'] ?? '', $mapaRazao);
            $ped[$k] = ['numero' => $pn, 'coligada_cod' => $cc,
                'coligada' => (trim((string)($r['coligada'] ?? '')) ?: coligada_nome($cc)),
                'obra' => $obraNome, 'data' => (string)($r['pedido_data'] ?? ''), 'status' => (string)($r['pedido_status'] ?? ''),
                'ccusto_cod' => (string)($r['ccusto_cod'] ?? ''), 'solic' => trim((string)($r['solic_numeros'] ?? '')),
                'colidmov' => trim((string)($r['solic_colidmov'] ?? '')),
                'centro_custo' => trim((string)($r['obra_cod'] ?? '')), 'obra_fonte' => trim((string)($r['obra_efetiva_fonte'] ?? '')),
                'obra_razao' => trim((string)($r['obra_efetiva_nome'] ?? '')), 'ccusto_nome' => trim((string)($r['ccusto_nome'] ?? '')),
                'usuario' => trim((string)($r['pedido_usuario'] ?? '')), 'obs' => [],
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
        if (($ped[$k]['usuario'] ?? '') === '' && trim((string)($r['pedido_usuario'] ?? '')) !== '') $ped[$k]['usuario'] = trim((string)$r['pedido_usuario']);
    }
    $lista = [];
    foreach ($ped as $p) {
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
        'por_pagina' => BP_POR_PAGINA, 'itens_lidos' => count($rows), 'truncado' => $truncado,
        'sort' => $sort, 'dir' => $dir, 'status' => $status, 'usuario' => $usuario, 'usuarios' => $usuariosLista,
        'periodo' => ['de' => $de, 'ate' => $ate]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
