<?php
/**
 * Leitura da fila de SOLICITAÇÕES DE COMPRA (Supabase alimentado pelo Power Automate/TOTVS).
 * Tabela `solicitacoes_fila` — item a item, SOMENTE LEITURA (anon/RLS). A chave nunca vai ao front.
 * Solicitação = agrupamento por (coligada, numero). Só há PENDENTES na fila.
 *
 * A COBERTURA de cotação por item mora aqui (solic_cobertura/solic_item_cobertura) porque a tela de
 * Solicitações e a API de leitura (actions/api.php) precisam da mesma resposta — não pode divergir.
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

/** Nome comercial PADRÃO de uma obra da SC (o que o de-para pré-preenche quando ninguém digitou nada). */
if (!function_exists('solic_nome_default')) {
    function solic_nome_default($coligada, $obraCod) {
        static $CC = ['001'=>'Comercial Americana','010'=>'Sede','015'=>'MKT','020'=>'SAT','032'=>'Licel',
                      '033'=>'Obras SAT','036'=>'Piamonte','039'=>'Contrap. Piamonte','040'=>'Cajá','041'=>'Espazo','042'=>'Prades'];
        if (stripos((string)$coligada, 'CAPRETZ') !== false && isset($CC[$obraCod])) return $CC[$obraCod];
        $n = preg_replace('/\s+(EMPREENDIMENTO|EMPREENDIMENTOS).*/i', '', (string)$coligada);
        return trim($n) ?: (string)$coligada;
    }
}

/**
 * De-para (coligada|centro de custo) -> dados da obra, JÁ COM O NOME RECONCILIADO COM O RADAR.
 *
 * Duas coisas que o Murilo apontou e que esta função resolve:
 *  (1) A MESMA obra aparecia com nomes diferentes conforme a tela — "PEDRA AZUL" nas Solicitações e
 *      "Diamond" no Radar. O de-para existe e aponta certo (radar_obra_id), só que o nome_comercial
 *      tinha sido salvo com o valor auto-preenchido (a razão social sem o juridiquês). Agora, quando
 *      o nome_comercial é EXATAMENTE o valor padrão (ninguém personalizou), o nome do RADAR vence.
 *      Se alguém digitou um nome próprio, esse nome é respeitado.
 *  (2) A mesma obra aparece em VÁRIAS linhas porque o TOTVS emite SC em mais de um centro de custo da
 *      mesma coligada (PEDRA AZUL 001 e 002, LEGACY 001 e 002...). Aí só uma das linhas costuma ter o
 *      vínculo com o radar. Fora da CAPRETZ a coligada JÁ É a obra, então as irmãs herdam o vínculo.
 *      ⚠️ Na CAPRETZ (coligada 1) NÃO herda: lá é o centro de custo que separa Cajá, Prades, Licel, Sede.
 */
function solic_obra_map($pdo) {
    $radar = [];
    try { foreach ($pdo->query("SELECT id, nome FROM obra") as $o) $radar[(int)$o['id']] = $o['nome']; } catch (Throwable $e) {}

    $linhas = [];
    try { foreach ($pdo->query("SELECT * FROM solic_obra") as $o) $linhas[] = $o; } catch (Throwable $e) { return []; }

    // vínculo de radar por COLIGADA (só p/ quem não é CAPRETZ), p/ as linhas irmãs herdarem
    $porColigada = [];
    foreach ($linhas as $o) {
        $col = trim((string)$o['coligada']);
        if ($col === '' || stripos($col, 'CAPRETZ') !== false) continue;
        $rid = (int)($o['radar_obra_id'] ?? 0);
        if ($rid && isset($radar[$rid]) && !isset($porColigada[$col])) $porColigada[$col] = $rid;
    }

    $map = [];
    foreach ($linhas as $o) {
        $col = trim((string)$o['coligada']); $cc = (string)$o['obra_cod'];
        $rid = (int)($o['radar_obra_id'] ?? 0);
        $herdado = false;
        if ((!$rid || !isset($radar[$rid])) && isset($porColigada[$col])) { $rid = $porColigada[$col]; $herdado = true; }

        $nome    = trim((string)($o['nome_comercial'] ?? ''));
        $padrao  = solic_nome_default($col, $cc);
        $usaRadar = $rid && isset($radar[$rid]) && ($nome === '' || $nome === $padrao);
        if ($usaRadar) $nome = $radar[$rid];

        $o['nome_comercial']  = $nome ?: $padrao;
        $o['radar_obra_id']   = $rid ?: null;
        $o['radar_herdado']   = $herdado;          // veio de uma linha irmã da mesma coligada
        $o['nome_do_radar']   = $usaRadar;         // o nome exibido é o do cadastro da obra
        $map[$col . '|' . $cc] = $o;
    }

    // Entrada CORINGA por coligada ("<coligada>|*"). Serve para os centros de custo que aparecem na fila
    // do TOTVS mas NÃO têm linha salva no de-para (é o caso do 002/003 de quase toda obra): sem isso a
    // SC ficava órfã, sem obra e sem comprador. Fora da CAPRETZ a coligada já é a obra, então dá p/ herdar.
    foreach ($porColigada as $col => $rid) {
        if (!isset($radar[$rid])) continue;
        $base = null;
        foreach ($map as $k => $v) if (strpos($k, $col . '|') === 0 && (int)($v['radar_obra_id'] ?? 0) === $rid) { $base = $v; break; }
        $map[$col . '|*'] = [
            'coligada' => $col, 'obra_cod' => '', 'nome_comercial' => $radar[$rid],
            'cnpj' => $base['cnpj'] ?? '', 'endereco' => $base['endereco'] ?? '',
            'comprador_id' => $base['comprador_id'] ?? '', 'comprador_nome' => $base['comprador_nome'] ?? '',
            'radar_obra_id' => $rid, 'radar_herdado' => true, 'nome_do_radar' => true, 'coringa' => true,
        ];
    }
    return $map;
}

/** Resolve o de-para de um par (coligada, centro de custo), caindo no coringa da coligada quando não há
 *  linha própria. Use SEMPRE isto em vez de $map[$col.'|'.$cc] direto. */
function solic_obra_de($map, $coligada, $obraCod) {
    $col = trim((string)$coligada);
    return $map[$col . '|' . $obraCod] ?? $map[$col . '|*'] ?? null;
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
