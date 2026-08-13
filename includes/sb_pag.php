<?php
/**
 * VARREDURA PAGINADA DE UMA TABELA DO SUPABASE (PostgREST).
 *
 * Nasceu dentro do actions/busca_pedidos.php e virou include quando a Busca de NOTAS precisou da
 * mesma máquina sobre outra tabela. É a parte chata da leitura, e é chata pelos motivos abaixo —
 * duas cópias divergiriam em silêncio, cada uma com um bug próprio:
 *
 *  - o PostgREST tem TETO PRÓPRIO por resposta (1000 linhas) e IGNORA um limit maior; sem paginar,
 *    "todas as obras" vinha cortado e a contagem mentia;
 *  - 16 idas em série levavam ~20s → as páginas vão em blocos PARALELOS (curl_multi);
 *  - segurar 15 mil linhas cruas custava ~54MB de RAM → aqui a leitura é por CALLBACK, agregando
 *    página por página, e o pico cai muito;
 *  - `Prefer: count=exact` diz o tamanho do recorte ANTES de baixar, o que permite montar todos os
 *    offsets de uma vez; quando o servidor não informa, cai no modo sequencial (para quando a
 *    página vem incompleta).
 *
 * ⚠️ Quem chama precisa mandar uma ORDEM com desempate determinístico. Paginar por offset com
 * muitas datas repetidas embaralha as linhas entre as páginas (item duplicado numa, sumido noutra).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';   // sb_http (curl genérico)

define('SBP_PAGINA_API', 1000);   // teto por resposta do PostgREST — pedir mais não adianta
define('SBP_PARALELO',   8);      // páginas buscadas ao mesmo tempo

function sbp_url($tabela, $query) { return SOLIC_SUPABASE_URL . '/rest/v1/' . $tabela . '?' . $query; }
function sbp_headers() {
    return ['apikey: ' . SOLIC_SUPABASE_KEY, 'Authorization: Bearer ' . SOLIC_SUPABASE_KEY, 'Accept: application/json'];
}

/** Uma leitura simples (sem paginar). Lança em erro de HTTP. */
function sbp_get($tabela, $query) {
    [$code, $res, $err] = sb_http('GET', sbp_url($tabela, $query), sbp_headers());
    if ($code !== 200 && $code !== 206) throw new Exception('TOTVS HTTP ' . $code . ' — ' . substr((string)($res ?: $err), 0, 160));
    return json_decode((string)$res, true) ?: [];
}

/** Quantas linhas o recorte tem, sem baixar nada ("content-range: 0-0/15363"). -1 = não informado. */
function sbp_contar($tabela, $query) {
    $ch = curl_init(sbp_url($tabela, $query . '&limit=1'));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_HEADER => 1, CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => array_merge(sbp_headers(), ['Prefer: count=exact'])]);
    $r = curl_exec($ch); curl_close($ch);
    if (is_string($r) && preg_match('#content-range:\s*\S+/(\d+)#i', $r, $m)) return (int)$m[1];
    return -1;
}

/** Lê o recorte INTEIRO entregando página por página ao callback. Devolve quantas linhas passaram. */
function sbp_varrer($tabela, $query, callable $cb, $max) {
    $total = sbp_contar($tabela, $query);
    if ($total === 0) return 0;

    if ($total < 0) {   // servidor não informou a contagem: sequencial, para quando a página vier incompleta
        $n = 0;
        for ($off = 0; $off < $max; $off += SBP_PAGINA_API) {
            $lote = sbp_get($tabela, $query . '&limit=' . SBP_PAGINA_API . '&offset=' . $off);
            $n += count($lote); $cb($lote);
            if (count($lote) < SBP_PAGINA_API) break;
        }
        return $n;
    }

    $alvo = min($total, $max);
    $offs = [];
    for ($off = 0; $off < $alvo; $off += SBP_PAGINA_API) $offs[] = $off;

    $n = 0;
    foreach (array_chunk($offs, SBP_PARALELO) as $bloco) {
        $mh = curl_multi_init(); $hs = [];
        foreach ($bloco as $off) {
            $ch = curl_init(sbp_url($tabela, $query . '&limit=' . SBP_PAGINA_API . '&offset=' . $off));
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 40, CURLOPT_HTTPHEADER => sbp_headers()]);
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
