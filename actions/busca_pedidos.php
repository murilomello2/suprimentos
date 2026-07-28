<?php
/**
 * BUSCA DE PEDIDOS DE COMPRA (consulta, só leitura) — base do TOTVS (Supabase pedidos_itens).
 * "Com quem a gente está comprando martelete?" → digita o item/fornecedor/nº e vê os PCs.
 *
 * GET ?q=<texto>&obra_id=<n>&periodo=30d|3m|ano|tudo&de=&ate=&ordem=recente|numero|obra&pagina=1&me=..
 *   -> { pedidos:[{numero, coligada, coligada_cod, obra, data, status, fornecedores[], n_itens, total, solic}],
 *        total, pagina, paginas, por_pagina, truncado }
 *
 * COMO A OBRA É RESOLVIDA: o PC do TOTVS traz a COLIGADA (não a obra). 16 obras têm coligada própria
 * (mapeamento 1:1 pela ficha). As que compram pela CAPREM (coligada 1) ficam agrupadas — o ccusto_cod do
 * PC é contábil (6.20.0001…), não o centro de custo da obra, então não dá p/ separá-las com o dado atual.
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

/** coligada_cod -> nome da OBRA (pela ficha). Coligada 1 = CAPREM (compra guarda-chuva de várias obras). */
function bp_mapa_obras($pdo) {
    $map = [];
    try {
        foreach ($pdo->query("SELECT nome, coligada_cod, compra_coligada_cod FROM obra_ficha") as $o) {
            $cc = trim((string)($o['compra_coligada_cod'] ?: $o['coligada_cod']));
            if ($cc === '' || $cc === '1') continue;              // 1 = CAPREM: várias obras, não identifica
            if (!isset($map[$cc])) $map[$cc] = $o['nome'];
        }
    } catch (Throwable $e) {}
    return $map;
}

try {
    $pdo = db();
    $perms = user_perms($pdo, $_GET['me'] ?? null);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }

    $q       = trim((string)($_GET['q'] ?? ''));
    $obraId  = (int)($_GET['obra_id'] ?? 0);
    $periodo = (string)($_GET['periodo'] ?? '3m');
    $ordem   = (string)($_GET['ordem'] ?? 'recente');
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

    // obra → coligada (a ficha manda: compra_coligada_cod, senão coligada_cod)
    $coligadaFiltro = '';
    if ($obraId > 0) {
        $st = $pdo->prepare("SELECT nome, coligada_cod, compra_coligada_cod FROM obra_ficha WHERE id=? OR radar_obra_id=? LIMIT 1");
        $st->execute([$obraId, $obraId]);
        if ($o = $st->fetch()) $coligadaFiltro = trim((string)($o['compra_coligada_cod'] ?: $o['coligada_cod']));
        if ($coligadaFiltro !== '') $f[] = 'coligada_cod=eq.' . rawurlencode($coligadaFiltro);
    }

    // busca ampla: nº do pedido OU fornecedor (razão/fantasia) OU descrição do item
    if ($q !== '') {
        $t = str_replace(['*', ',', '(', ')'], ' ', $q);
        $like = '*' . rawurlencode($t) . '*';
        $ors = ['produto.ilike.' . $like, 'fornecedor_nome.ilike.' . $like, 'fornecedor_fantasia.ilike.' . $like,
                'pedido_numero.ilike.' . $like, 'codprd.ilike.' . $like];
        $f[] = 'or=(' . implode(',', $ors) . ')';
    }

    $sel = 'select=pedido_numero,pedido_data,pedido_status,coligada_cod,coligada,ccusto_cod,fornecedor_cod,fornecedor_nome,fornecedor_fantasia,produto,qtd,und,preco_unit,valor_total,solic_numeros';
    $rows = bp_get($sel . ($f ? '&' . implode('&', $f) : '') . '&order=pedido_data.desc&limit=' . BP_MAX_LINHAS);
    $truncado = count($rows) >= BP_MAX_LINHAS;

    // ---- agrega item → PEDIDO (chave: coligada + número; o nº se repete entre coligadas) ----
    $mapaObras = bp_mapa_obras($pdo);
    $ped = [];
    foreach ($rows as $r) {
        $cc = (string)($r['coligada_cod'] ?? ''); $pn = (string)($r['pedido_numero'] ?? '');
        if ($pn === '') continue;
        $k = $cc . '|' . $pn;
        if (!isset($ped[$k])) {
            $obraNome = $mapaObras[$cc] ?? ($cc === '1' ? 'CAPREM (várias obras)' : '');
            $ped[$k] = ['numero' => $pn, 'coligada_cod' => $cc,
                'coligada' => (trim((string)($r['coligada'] ?? '')) ?: coligada_nome($cc)),
                'obra' => $obraNome, 'data' => (string)($r['pedido_data'] ?? ''), 'status' => (string)($r['pedido_status'] ?? ''),
                'ccusto_cod' => (string)($r['ccusto_cod'] ?? ''), 'solic' => trim((string)($r['solic_numeros'] ?? '')),
                'fornecedores' => [], 'n_itens' => 0, 'total' => 0.0, 'amostra' => []];
        }
        $pu = (float)($r['preco_unit'] ?? 0); $qt = (float)($r['qtd'] ?? 0); $vt = (float)($r['valor_total'] ?? 0);
        $ped[$k]['total'] += $vt > 0 ? $vt : ($pu * $qt);
        $ped[$k]['n_itens']++;
        $fn = trim((string)($r['fornecedor_fantasia'] ?? '')) ?: trim((string)($r['fornecedor_nome'] ?? ''));
        if ($fn !== '') $ped[$k]['fornecedores'][$fn] = true;
        if (count($ped[$k]['amostra']) < 3) $ped[$k]['amostra'][] = (string)($r['produto'] ?? '');
    }
    $lista = [];
    foreach ($ped as $p) { $p['fornecedores'] = array_keys($p['fornecedores']); $p['total'] = round($p['total'], 2); $lista[] = $p; }

    // ---- ordenação ----
    if ($ordem === 'numero')      usort($lista, fn($a, $b) => strcmp($b['numero'], $a['numero']));
    elseif ($ordem === 'obra')    usort($lista, fn($a, $b) => (strcasecmp($a['obra'] ?: 'zzz', $b['obra'] ?: 'zzz')) ?: strcmp($b['data'], $a['data']));
    elseif ($ordem === 'valor')   usort($lista, fn($a, $b) => $b['total'] <=> $a['total']);
    else                          usort($lista, fn($a, $b) => (strcmp($b['data'], $a['data'])) ?: strcmp($b['numero'], $a['numero']));

    $total = count($lista);
    $paginas = max(1, (int)ceil($total / BP_POR_PAGINA));
    if ($pagina > $paginas) $pagina = $paginas;
    $page = array_slice($lista, ($pagina - 1) * BP_POR_PAGINA, BP_POR_PAGINA);

    echo json_encode(['ok' => true, 'pedidos' => $page, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas,
        'por_pagina' => BP_POR_PAGINA, 'itens_lidos' => count($rows), 'truncado' => $truncado,
        'periodo' => ['de' => $de, 'ate' => $ate]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
