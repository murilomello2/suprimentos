<?php
/**
 * Leitura da fila de SOLICITAÇÕES DE COMPRA (Supabase alimentado pelo Power Automate/TOTVS).
 * Tabela `solicitacoes_fila` — item a item, SOMENTE LEITURA (anon/RLS). A chave nunca vai ao front.
 * Solicitação = agrupamento por (coligada, numero). Só há PENDENTES na fila.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php'; // reusa sb_http (curl genérico)

/** GET no PostgREST da fila. $query = querystring PostgREST (ex.: 'select=*&limit=5'). Retorna [rows, headers]. */
function solic_rest($query, $wantCount = false) {
    $url = SOLIC_SUPABASE_URL . '/rest/v1/solicitacoes_fila' . ($query !== '' ? ('?' . $query) : '');
    $headers = [
        'apikey: ' . SOLIC_SUPABASE_KEY,
        'Authorization: Bearer ' . SOLIC_SUPABASE_KEY,
        'Accept: application/json',
    ];
    if ($wantCount) $headers[] = 'Prefer: count=exact';
    [$code, $res, $err] = sb_http('GET', $url, $headers);
    if ($code !== 200 && $code !== 206) throw new Exception('Solicitações Supabase HTTP ' . $code . ' — ' . substr((string)($res ?: $err), 0, 200));
    return json_decode((string)$res, true) ?: [];
}

/** Puxa TODA a fila (paginado em blocos de 1000 por causa do limite do PostgREST). */
function solic_fila_all($extra = '') {
    $all = []; $off = 0; $step = 1000;
    while (true) {
        $q = 'select=*&order=coligada.asc,numero.asc,seq.asc&limit=' . $step . '&offset=' . $off . ($extra ? ('&' . $extra) : '');
        $rows = solic_rest($q);
        if (!$rows) break;
        $all = array_merge($all, $rows);
        if (count($rows) < $step) break;
        $off += $step;
        if ($off > 50000) break; // trava de segurança
    }
    return $all;
}

/** Normaliza descrição p/ casar item-da-SC ↔ cotacao_item quando não há codprd (fallback do casamento exato). */
if (!function_exists('sol_norm')) {
    function sol_norm($s) {
        $s = strtr((string)$s, ['Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e','Í'=>'i','Ó'=>'o','Ô'=>'o','Õ'=>'o','Ú'=>'u','Ç'=>'c','á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);
        return preg_replace('/\s+/', ' ', strtolower(trim($s)));
    }
}

/**
 * COBERTURA DE COTAÇÃO POR ITEM DA SOLICITAÇÃO.
 * Devolve [ "coligada|numero" => [ matchkey => ['status'=>'cotando'|'coberto', 'cid'=>, 'ctit'=>] ] ].
 *
 * Mora aqui (e não na action) porque DOIS consumidores dependem dela — a tela de Solicitações e a API
 * de leitura — e o casamento é sutil demais para viver duplicado:
 *   matchkey por prioridade  's:'<seq>  >  'c:'<codprd>  >  'p:'<produto normalizado>
 *   'coberto' = cotação finalizada OU a COLIGADA DAQUELE item já tem nº de PC. Em cotação multi-coligada
 *   o num_pedido do cabeçalho NÃO vale (é um agregado "PC1, PC2"), só o PC da própria coligada.
 *   'coberto' vence 'cotando' quando duas cotações tocam o mesmo item.
 * ⚠️ Quem usa precisa aplicar o fallback por NOME apenas se o nome for único dentro da SC (senão dois
 *    itens iguais na mesma SC casam com a mesma cotação) — ver solic_item_cobertura().
 */
function solic_cobertura($pdo) {
    $cov = [];
    $add = function ($col, $num, $seq, $codprd, $prod, $status, $cid, $ctit) use (&$cov) {
        $col = trim((string)$col); $num = trim((string)$num);
        if ($col === '' || $num === '') return;
        $seq = trim((string)$seq); $codprd = trim((string)$codprd);
        $k  = $col . '|' . $num;
        $mk = $seq !== '' ? ('s:' . $seq) : ($codprd !== '' ? ('c:' . $codprd) : ('p:' . sol_norm($prod)));
        if (!isset($cov[$k])) $cov[$k] = [];
        $cur = $cov[$k][$mk] ?? null;
        if ($cur === null || ($cur['status'] !== 'coberto' && $status === 'coberto'))
            $cov[$k][$mk] = ['status' => $status, 'cid' => (int)$cid, 'ctit' => $ctit];
    };
    try {
        $cotPed = [];   // cotacao_id => [coligada => num_pedido]
        foreach ($pdo->query("SELECT cotacao_id, coligada, num_pedido FROM cotacao_pedido WHERE num_pedido IS NOT NULL AND num_pedido<>''") as $r)
            $cotPed[(int)$r['cotacao_id']][trim((string)$r['coligada'])] = trim((string)$r['num_pedido']);
        $cotNCol = [];  // cotacao_id => nº de coligadas distintas
        foreach ($pdo->query("SELECT cotacao_id, COUNT(DISTINCT solic_coligada) n FROM cotacao_item WHERE solic_coligada IS NOT NULL AND solic_coligada<>'' GROUP BY cotacao_id") as $r)
            $cotNCol[(int)$r['cotacao_id']] = (int)$r['n'];
        // (a) itens carimbados com a origem (fluxo atual)
        foreach ($pdo->query("SELECT ci.cotacao_id cid, ci.solic_coligada col, ci.solic_numero num, ci.solic_seq seq, ci.solic_codprd codprd, ci.descricao prod, c.status st, c.num_pedido hdr, c.titulo ctit
                              FROM cotacao_item ci JOIN cotacao c ON c.id=ci.cotacao_id
                              WHERE ci.solic_coligada IS NOT NULL AND ci.solic_coligada<>'' AND ci.solic_numero IS NOT NULL AND ci.solic_numero<>''") as $r) {
            $cid = (int)$r['cid']; $colPc = $cotPed[$cid][trim((string)$r['col'])] ?? '';
            $isMulti = ($cotNCol[$cid] ?? 1) > 1;
            $effPc = $colPc !== '' ? $colPc : ($isMulti ? '' : trim((string)$r['hdr']));
            $status = (($r['st'] === 'finalizada') || $effPc !== '') ? 'coberto' : 'cotando';
            $add($r['col'], $r['num'], $r['seq'], $r['codprd'], $r['prod'], $status, $cid, $r['ctit']);
        }
        // (b) cotações antigas (itens sem carimbo de origem): liga pela SC via overlay e casa por produto
        foreach ($pdo->query("SELECT c.id cid, o.coligada col, o.numero num, ci.descricao prod, c.status st, c.num_pedido pc, c.titulo ctit
                              FROM solic_overlay o JOIN cotacao c ON c.id=o.cotacao_id JOIN cotacao_item ci ON ci.cotacao_id=c.id
                              WHERE o.cotacao_id IS NOT NULL AND (ci.solic_coligada IS NULL OR ci.solic_coligada='')") as $r) {
            $status = (($r['st'] === 'finalizada') || trim((string)$r['pc']) !== '') ? 'coberto' : 'cotando';
            $add($r['col'], $r['num'], '', '', $r['prod'], $status, (int)$r['cid'], $r['ctit']);
        }
    } catch (Throwable $e) { return []; }
    return $cov;
}

/**
 * Carimba a cobertura em cada item de UMA solicitação. Muda $itens por referência e devolve
 * ['cobertura'=>'vazio'|'parcial'|'total', 'n_cobertos'=>, 'n_tocados'=>, 'cotacoes'=>[{id,titulo}]].
 */
function solic_item_cobertura(array &$itens, array $cmap) {
    // nomes que se REPETEM nesta SC: neles o fallback por nome é ambíguo e não pode ser usado
    $nameCount = [];
    foreach ($itens as $c) { $nn = sol_norm($c['produto'] ?? ''); $nameCount[$nn] = ($nameCount[$nn] ?? 0) + 1; }
    $nCob = 0; $nAny = 0; $cots = [];
    foreach ($itens as &$it) {
        $sq = trim((string)($it['seq'] ?? '')); $cp = trim((string)($it['codprd'] ?? '')); $nn = sol_norm($it['produto'] ?? '');
        $m = ($sq !== '') ? ($cmap['s:' . $sq] ?? null) : null;
        if ($m === null && $cp !== '') $m = $cmap['c:' . $cp] ?? null;
        if ($m === null && ($nameCount[$nn] ?? 0) <= 1) $m = $cmap['p:' . $nn] ?? null;
        if ($m) {
            $it['cot'] = $m['status']; $it['cot_cid'] = $m['cid']; $it['cot_ctit'] = $m['ctit'];
            if (!empty($m['cid'])) $cots[$m['cid']] = $m['ctit'];
        } else { $it['cot'] = 'vazio'; }
        if ($it['cot'] === 'coberto') $nCob++;
        if ($it['cot'] !== 'vazio')   $nAny++;
    }
    unset($it);
    $nI = count($itens);
    $lista = []; foreach ($cots as $cid => $tit) $lista[] = ['id' => $cid, 'titulo' => $tit];
    return ['cobertura' => ($nI > 0 && $nCob === $nI) ? 'total' : ($nAny > 0 ? 'parcial' : 'vazio'),
            'n_cobertos' => $nCob, 'n_tocados' => $nAny, 'cotacoes' => $lista];
}

/** Agrupa a fila (linhas item-a-item) em SOLICITAÇÕES por (coligada, numero). */
function solic_agrupar($rows) {
    $sol = [];
    foreach ($rows as $r) {
        $chave = ($r['coligada'] ?? '') . '|' . ($r['numero'] ?? '');
        if (!isset($sol[$chave])) $sol[$chave] = [
            'coligada' => $r['coligada'] ?? '', 'numero' => $r['numero'] ?? '', 'obra_cod' => $r['obra'] ?? '',
            'emissao' => $r['emissao'] ?? null, 'atualizado_em' => $r['atualizado_em'] ?? null, 'itens' => [],
        ];
        $sol[$chave]['itens'][] = [
            'colidmov' => $r['colidmov'] ?? '', 'seq' => (int)($r['seq'] ?? 0), 'codprd' => $r['codprd'] ?? '',
            'produto' => $r['produto'] ?? '', 'qtd' => $r['qtd'] ?? null, 'und' => $r['und'] ?? '', 'observacao' => $r['observacao'] ?? '',
        ];
        // usa a emissão mais antiga do grupo
        if (!empty($r['emissao']) && ($sol[$chave]['emissao'] === null || $r['emissao'] < $sol[$chave]['emissao'])) $sol[$chave]['emissao'] = $r['emissao'];
    }
    return array_values($sol);
}
