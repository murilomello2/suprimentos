<?php
/**
 * Leitura de PEDIDOS DE COMPRA (Supabase alimentado pelo Power Automate/TOTVS).
 * Tabela `pedidos_itens` — item a item, SOMENTE LEITURA. Mesmo projeto/chave da fila de solicitações.
 * Um pedido = agrupamento por pedido_numero (vários itens; fornecedor por item, normalmente 1).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php'; // reusa sb_http (curl genérico)

function ped_rest($query) {
    $url = SOLIC_SUPABASE_URL . '/rest/v1/pedidos_itens' . ($query !== '' ? ('?' . $query) : '');
    $headers = [
        'apikey: ' . SOLIC_SUPABASE_KEY,
        'Authorization: Bearer ' . SOLIC_SUPABASE_KEY,
        'Accept: application/json',
    ];
    [$code, $res, $err] = sb_http('GET', $url, $headers);
    if ($code !== 200 && $code !== 206) throw new Exception('Pedidos Supabase HTTP ' . $code . ' — ' . substr((string)($res ?: $err), 0, 200));
    return json_decode((string)$res, true) ?: [];
}

/** Um pedido pelo número (casa com/sem zeros à esquerda). Retorna a "fotinha": coligada, fornecedor(es), itens (preço unit/total) e total geral. */
function pedido_por_numero($numero) {
    $num = trim((string)$numero); if ($num === '') return null;
    $variantes = array_values(array_unique([$num, ltrim($num, '0'), str_pad(ltrim($num, '0'), 9, '0', STR_PAD_LEFT)]));
    $ors = array_map(fn($v) => 'pedido_numero.eq.' . rawurlencode($v), array_filter($variantes, fn($v) => $v !== ''));
    if (!$ors) return null;
    $rows = ped_rest('select=*&or=(' . implode(',', $ors) . ')&order=seq.asc&limit=500');
    if (!$rows) return null;
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
        'numero' => $r0['pedido_numero'] ?? $num, 'coligada' => $r0['coligada'] ?? '', 'coligada_cod' => $r0['coligada_cod'] ?? '',
        'ccusto_cod' => $r0['ccusto_cod'] ?? '', 'data' => $r0['pedido_data'] ?? '', 'status' => $r0['pedido_status'] ?? '',
        'fornecedores' => array_keys($forn), 'itens' => $itens, 'total' => round($total, 2), 'n_itens' => count($itens),
    ];
}
