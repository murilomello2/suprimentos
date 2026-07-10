<?php
/**
 * Leitura de PEDIDOS DE COMPRA (Supabase alimentado pelo Power Automate/TOTVS).
 * Tabela `pedidos_itens` — item a item, SOMENTE LEITURA. Mesmo projeto/chave da fila de solicitações.
 * Um pedido = agrupamento por pedido_numero (vários itens; fornecedor por item, normalmente 1).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php'; // reusa sb_http (curl genérico)
require_once __DIR__ . '/coligadas.php'; // de-para código<->nome da coligada (TOTVS)

function ped_sb_get($tabela, $query) {
    $url = SOLIC_SUPABASE_URL . '/rest/v1/' . $tabela . ($query !== '' ? ('?' . $query) : '');
    $headers = ['apikey: ' . SOLIC_SUPABASE_KEY, 'Authorization: Bearer ' . SOLIC_SUPABASE_KEY, 'Accept: application/json'];
    [$code, $res, $err] = sb_http('GET', $url, $headers);
    if ($code !== 200 && $code !== 206) throw new Exception('Supabase ' . $tabela . ' HTTP ' . $code . ' — ' . substr((string)($res ?: $err), 0, 200));
    return json_decode((string)$res, true) ?: [];
}
function ped_rest($query) { return ped_sb_get('pedidos_itens', $query); }

// colidmov da SC (ex.: "27-20628", 27 = coligada) a partir de (numero[, coligada]). Vazio se não achar.
// É a CHAVE do vínculo: o nº da SC NÃO é único entre coligadas, mas o colidmov embute o código.
function solic_colidmov_de($numero, $coligada = null) {
    $num = trim((string)$numero); if ($num === '') return '';
    $variantes = array_values(array_unique([$num, ltrim($num, '0'), str_pad(ltrim($num, '0'), 9, '0', STR_PAD_LEFT)]));
    $ors = array_map(fn($v) => 'numero.eq.' . rawurlencode($v), array_filter($variantes, fn($v) => $v !== ''));
    if (!$ors) return '';
    $q = 'select=colidmov,coligada,numero&or=(' . implode(',', $ors) . ')';
    if (trim((string)$coligada) !== '') $q .= '&coligada=eq.' . rawurlencode(trim((string)$coligada));
    $q .= '&limit=1';
    try { $rows = ped_sb_get('solicitacoes_fila', $q); } catch (Throwable $e) { return ''; }
    return $rows ? trim((string)($rows[0]['colidmov'] ?? '')) : '';
}

/** Um pedido pelo número (casa com/sem zeros à esquerda). Retorna a "fotinha": coligada, fornecedor(es), itens (preço unit/total) e total geral. */
function pedido_por_numero($numero, $coligadaCod = null) {
    $num = trim((string)$numero); if ($num === '') return null;
    $variantes = array_values(array_unique([$num, ltrim($num, '0'), str_pad(ltrim($num, '0'), 9, '0', STR_PAD_LEFT)]));
    $ors = array_map(fn($v) => 'pedido_numero.eq.' . rawurlencode($v), array_filter($variantes, fn($v) => $v !== ''));
    if (!$ors) return null;
    $rows = ped_rest('select=*&or=(' . implode(',', $ors) . ')&order=seq.asc&limit=500');
    if (!$rows) return null;
    // o nº de PC NÃO é único entre coligadas — se veio a coligada, filtra p/ ela (senão mostraria o pedido de outra coligada)
    if ($coligadaCod !== null && trim((string)$coligadaCod) !== '') {
        $f = array_values(array_filter($rows, fn($r) => (string)($r['coligada_cod'] ?? '') === (string)$coligadaCod));
        if ($f) $rows = $f;
    }
    $r0 = $rows[0]; $itens = []; $total = 0.0; $forn = [];
    foreach ($rows as $r) {
        $pu = (float)($r['preco_unit'] ?? 0); $qt = (float)($r['qtd'] ?? 0);
        $vt = (float)($r['valor_total'] ?? 0); $lt = $vt > 0 ? $vt : ($pu * $qt);   // valor_total pode vir 0 no TOTVS
        $total += $lt;
        $itens[] = ['seq' => (int)($r['seq'] ?? 0), 'codprd' => $r['codprd'] ?? '', 'produto' => $r['produto'] ?? '',
            'qtd' => $qt, 'und' => $r['und'] ?? '', 'preco_unit' => $pu, 'total' => round($lt, 2),
            'fornecedor_cod' => $r['fornecedor_cod'] ?? ''];
        if (!empty($r['fornecedor_cod'])) $forn[$r['fornecedor_cod']] = true;
    }
    return [
        'numero' => $r0['pedido_numero'] ?? $num, 'coligada' => ($r0['coligada'] ?: coligada_nome($r0['coligada_cod'] ?? '')), 'coligada_cod' => $r0['coligada_cod'] ?? '',
        'ccusto_cod' => $r0['ccusto_cod'] ?? '', 'data' => $r0['pedido_data'] ?? '', 'status' => $r0['pedido_status'] ?? '',
        'fornecedores' => array_keys($forn), 'itens' => $itens, 'total' => round($total, 2), 'n_itens' => count($itens),
    ];
}

/** Pedidos que nasceram de uma SOLICITAÇÃO — vínculo EXATO por COLIDMOV (que embute a coligada: "27-...").
 *  O número da SC sozinho NÃO é único entre coligadas (a SC 2795 existe na Legacy E na Stanza), então casar por
 *  solic_numeros casava o PC ERRADO. Aqui casamos pedidos_itens.solic_colidmov == colidmov da SC.
 *  $colidmov é passado direto (cotações novas guardam) ou derivado de (numero+coligada) na fila (cotações antigas).
 *  Uma solicitação pode virar VÁRIOS pedidos (split). */
function pedidos_por_solicitacao($numSolic, $coligada = null, $colidmov = null) {
    $cm = trim((string)$colidmov);
    if ($cm === '') $cm = solic_colidmov_de($numSolic, $coligada);   // deriva p/ cotações antigas (sem colidmov gravado)
    if ($cm === '') return [];   // sem colidmov não dá p/ casar com segurança — melhor NÃO mostrar do que mostrar o PC de outra coligada
    $rows = ped_rest('select=pedido_numero,coligada,coligada_cod,ccusto_cod,pedido_status,pedido_data,fornecedor_cod,solic_numeros,solic_colidmov&solic_colidmov=eq.' . rawurlencode($cm) . '&limit=1000');
    $grp = [];
    foreach ($rows as $r) {
        $pn = (string)$r['pedido_numero']; if ($pn === '') continue;
        if (!isset($grp[$pn])) $grp[$pn] = ['pedido_numero' => $pn, 'coligada' => ($r['coligada'] ?: coligada_nome($r['coligada_cod'] ?? '')), 'coligada_cod' => $r['coligada_cod'] ?? '',
            'ccusto_cod' => $r['ccusto_cod'], 'status' => $r['pedido_status'], 'data' => $r['pedido_data'], 'colidmov' => $cm, 'fornecedores' => [], 'n_itens' => 0];
        $grp[$pn]['n_itens']++;
        if (!empty($r['fornecedor_cod'])) $grp[$pn]['fornecedores'][$r['fornecedor_cod']] = true;
    }
    $out = []; foreach ($grp as $g) { $g['fornecedores'] = array_keys($g['fornecedores']); $out[] = $g; }
    usort($out, fn($a, $b) => strcmp($a['pedido_numero'], $b['pedido_numero']));
    return $out;
}
