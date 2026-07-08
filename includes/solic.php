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
