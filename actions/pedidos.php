<?php
/**
 * PEDIDOS DE COMPRA — leitura da base do TOTVS (Supabase pedidos_itens), só leitura.
 * GET ?numero=<pedido>&coligada_cod=<cod>&me=..  -> a "fotinha" do pedido daquela COLIGADA
 * GET ?solicitacao=<sc>&colidmov=..&me=..        -> pedidos que nasceram de uma solicitação
 *
 * ⚠️ REGRA DE OURO (bug 28/jul): o nº do PC **NÃO é único entre coligadas** — o PC 2856 existe na CPR1(29),
 * Legacy(27), Stanza(34) E na Polastri/Vitrius(19). Sem filtrar pela coligada da OBRA, a tela juntava os
 * itens das 4 obras num "pedido" só (18 itens, fornecedores misturados). Agora: com coligada → filtra;
 * SEM coligada e havendo mais de uma → devolve as OPÇÕES (nunca mistura).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
@include_once __DIR__ . '/../includes/pedidos.php';   // ped_sb_get/ped_rest/pedidos_por_solicitacao (opcional: guardas abaixo)
require_once __DIR__ . '/../includes/coligadas.php';

// --- guardas: se o includes/pedidos.php não estiver presente, este endpoint segue funcionando sozinho ---
if (!function_exists('ped_sb_get')) {
    function ped_sb_get($tabela, $query) {
        require_once __DIR__ . '/../includes/supabase.php';
        $url = SOLIC_SUPABASE_URL . '/rest/v1/' . $tabela . ($query !== '' ? ('?' . $query) : '');
        $headers = ['apikey: ' . SOLIC_SUPABASE_KEY, 'Authorization: Bearer ' . SOLIC_SUPABASE_KEY, 'Accept: application/json'];
        [$code, $res, $err] = sb_http('GET', $url, $headers);
        if ($code !== 200 && $code !== 206) throw new Exception('Supabase ' . $tabela . ' HTTP ' . $code . ' — ' . substr((string)($res ?: $err), 0, 200));
        return json_decode((string)$res, true) ?: [];
    }
}
if (!function_exists('ped_rest')) { function ped_rest($query) { return ped_sb_get('pedidos_itens', $query); } }
if (!function_exists('solic_colidmov_de')) {
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
}
if (!function_exists('pedidos_por_solicitacao')) {
    /** Pedidos que nasceram de uma SOLICITAÇÃO — vínculo EXATO por COLIDMOV (embute a coligada). */
    function pedidos_por_solicitacao($numSolic, $coligada = null, $colidmov = null) {
        $cm = trim((string)$colidmov);
        if ($cm === '') $cm = solic_colidmov_de($numSolic, $coligada);
        if ($cm === '') return [];   // sem colidmov não dá p/ casar com segurança (nº de SC se repete entre coligadas)
        $rows = ped_rest('select=pedido_numero,coligada,coligada_cod,ccusto_cod,pedido_status,pedido_data,fornecedor_cod,fornecedor_nome,fornecedor_fantasia,solic_numeros,solic_colidmov&solic_colidmov=eq.' . rawurlencode($cm) . '&limit=1000');
        $grp = [];
        foreach ($rows as $r) {
            $pn = (string)$r['pedido_numero']; if ($pn === '') continue;
            if (!isset($grp[$pn])) $grp[$pn] = ['pedido_numero' => $pn, 'coligada' => (trim((string)($r['coligada'] ?? '')) ?: coligada_nome($r['coligada_cod'] ?? '')),
                'coligada_cod' => $r['coligada_cod'] ?? '', 'ccusto_cod' => $r['ccusto_cod'] ?? '', 'status' => $r['pedido_status'] ?? '',
                'data' => $r['pedido_data'] ?? '', 'colidmov' => $cm, 'fornecedores' => [], 'n_itens' => 0];
            $grp[$pn]['n_itens']++;
            $f = trim((string)($r['fornecedor_fantasia'] ?? '')) ?: (trim((string)($r['fornecedor_nome'] ?? '')) ?: (string)($r['fornecedor_cod'] ?? ''));
            if ($f !== '') $grp[$pn]['fornecedores'][$f] = true;
        }
        $out = []; foreach ($grp as $g) { $g['fornecedores'] = array_keys($g['fornecedores']); $out[] = $g; }
        usort($out, fn($a, $b) => strcmp($a['pedido_numero'], $b['pedido_numero']));
        return $out;
    }
}

/** Resumo por coligada de um mesmo nº de PC (p/ desambiguar sem misturar). */
function ped_opcoes($porCol) {
    $out = [];
    foreach ($porCol as $cc => $rs) {
        $tot = 0.0; $fn = [];
        foreach ($rs as $r) {
            $pu = (float)($r['preco_unit'] ?? 0); $qt = (float)($r['qtd'] ?? 0); $vt = (float)($r['valor_total'] ?? 0);
            $tot += $vt > 0 ? $vt : ($pu * $qt);
            $f = trim((string)($r['fornecedor_fantasia'] ?? '')) ?: trim((string)($r['fornecedor_nome'] ?? ''));
            if ($f !== '') $fn[$f] = true;
        }
        $r0 = $rs[0];
        $out[] = ['coligada_cod' => (string)$cc, 'coligada' => (trim((string)($r0['coligada'] ?? '')) ?: coligada_nome($cc)),
                  'n_itens' => count($rs), 'total' => round($tot, 2), 'fornecedores' => array_keys($fn),
                  'ccusto_cod' => (string)($r0['ccusto_cod'] ?? '')];
    }
    usort($out, fn($a, $b) => $b['total'] <=> $a['total']);
    return $out;
}

/** A "fotinha" do PC — SEMPRE de UMA coligada só. Traz o NOME do fornecedor (campos novos do TOTVS). */
function ped_pedido($numero, $coligadaCod = null) {
    $num = trim((string)$numero); if ($num === '') return null;
    $variantes = array_values(array_unique([$num, ltrim($num, '0'), str_pad(ltrim($num, '0'), 9, '0', STR_PAD_LEFT)]));
    $ors = array_map(fn($v) => 'pedido_numero.eq.' . rawurlencode($v), array_filter($variantes, fn($v) => $v !== ''));
    if (!$ors) return null;
    $rows = ped_rest('select=*&or=(' . implode(',', $ors) . ')&order=seq.asc&limit=500');
    if (!$rows) return null;
    $porCol = [];
    foreach ($rows as $r) $porCol[(string)($r['coligada_cod'] ?? '')][] = $r;
    $cc = ($coligadaCod !== null) ? trim((string)$coligadaCod) : '';
    if ($cc !== '') {
        if (!isset($porCol[$cc])) return ['nao_encontrado_na_coligada' => true, 'numero' => $num,
            'coligada_cod' => $cc, 'coligada' => coligada_nome($cc), 'opcoes' => ped_opcoes($porCol)];
        $rows = $porCol[$cc];
    } elseif (count($porCol) > 1) {
        return ['ambiguo' => true, 'numero' => $num, 'opcoes' => ped_opcoes($porCol)];   // NUNCA mistura coligadas
    }
    $r0 = $rows[0]; $itens = []; $total = 0.0; $forn = [];
    foreach ($rows as $r) {
        $pu = (float)($r['preco_unit'] ?? 0); $qt = (float)($r['qtd'] ?? 0);
        $vt = (float)($r['valor_total'] ?? 0); $lt = $vt > 0 ? $vt : ($pu * $qt);   // valor_total pode vir 0 no TOTVS
        $total += $lt;
        $fnome = trim((string)($r['fornecedor_nome'] ?? '')); $ffant = trim((string)($r['fornecedor_fantasia'] ?? ''));
        $itens[] = ['seq' => (int)($r['seq'] ?? 0), 'codprd' => $r['codprd'] ?? '', 'produto' => $r['produto'] ?? '',
            'qtd' => $qt, 'und' => $r['und'] ?? '', 'preco_unit' => $pu, 'total' => round($lt, 2),
            'observacao' => trim((string)($r['item_observacao'] ?? '')),   // descrição detalhada digitada à mão
            'fornecedor_cod' => $r['fornecedor_cod'] ?? '', 'fornecedor_nome' => $fnome,
            'fornecedor_fantasia' => $ffant, 'fornecedor_cnpj' => trim((string)($r['fornecedor_cnpj'] ?? ''))];
        if (!empty($r['fornecedor_cod'])) $forn[(string)$r['fornecedor_cod']] = $ffant ?: ($fnome ?: (string)$r['fornecedor_cod']);
    }
    return [
        'numero' => $r0['pedido_numero'] ?? $num,
        'coligada' => (trim((string)($r0['coligada'] ?? '')) ?: coligada_nome($r0['coligada_cod'] ?? '')),
        'coligada_cod' => $r0['coligada_cod'] ?? '', 'ccusto_cod' => $r0['ccusto_cod'] ?? '',
        'data' => $r0['pedido_data'] ?? '', 'status' => $r0['pedido_status'] ?? '',
        'solic_numeros' => trim((string)($r0['solic_numeros'] ?? '')), 'usuario' => trim((string)($r0['pedido_usuario'] ?? '')),
        'fornecedores' => array_values($forn), 'fornecedores_cod' => array_keys($forn),
        'itens' => $itens, 'total' => round($total, 2), 'n_itens' => count($itens),
    ];
}

try {
    $pdo = db();
    $perms = user_perms($pdo, $_GET['me'] ?? null);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }

    if (isset($_GET['numero'])) {
        $cc = $_GET['coligada_cod'] ?? null;
        // sem coligada explícita, dá p/ derivar da OBRA da cotação (obra_ficha.compra_coligada_cod / coligada_cod)
        if (($cc === null || trim((string)$cc) === '') && !empty($_GET['obra_id'])) {
            try {
                $q = $pdo->prepare("SELECT compra_coligada_cod, coligada_cod FROM obra_ficha WHERE radar_obra_id=? LIMIT 1");
                $q->execute([(int)$_GET['obra_id']]);
                if ($f = $q->fetch()) $cc = trim((string)($f['compra_coligada_cod'] ?: $f['coligada_cod'])) ?: null;
            } catch (Throwable $e) {}
        }
        $p = ped_pedido($_GET['numero'], $cc);
        if (!$p) { echo json_encode(['error' => 'Pedido não encontrado na base do TOTVS.'], JSON_UNESCAPED_UNICODE); exit; }
        if (!empty($p['ambiguo']) || !empty($p['nao_encontrado_na_coligada'])) { echo json_encode(['ok' => true, 'desambiguar' => $p], JSON_UNESCAPED_UNICODE); exit; }
        echo json_encode(['ok' => true, 'pedido' => $p], JSON_UNESCAPED_UNICODE); exit;
    }
    if (isset($_GET['solicitacao'])) {   // pedidos que nasceram de uma solicitação (vínculo EXATO por colidmov)
        if (!function_exists('pedidos_por_solicitacao')) { echo json_encode(['ok' => true, 'pedidos' => []], JSON_UNESCAPED_UNICODE); exit; }
        $peds = pedidos_por_solicitacao($_GET['solicitacao'], $_GET['coligada'] ?? null, $_GET['colidmov'] ?? null);
        echo json_encode(['ok' => true, 'pedidos' => $peds], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['error' => 'informe o número do pedido ou da solicitação'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
