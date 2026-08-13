<?php
/**
 * BUSCA DE NOTAS FISCAIS (consulta, só leitura) — tabela `apropriacoes_compras` do Supabase,
 * alimentada pelo Power Automate a partir do painel APROPS (TOTVS).
 *
 * É a irmã da Busca de Pedidos, um elo adiante na cadeia. Enquanto lá a pergunta é "com quem a
 * gente COMPRA isto?", aqui é "o que a gente RECEBEU e PAGOU — e bateu com o que foi pedido?".
 * A tabela é justamente o costurado da cadeia, linha a linha de apropriação:
 *
 *      SOLICITAÇÃO  →  PEDIDO DE COMPRA  →  NOTA FISCAL  →  apropriação (obra × tarefa)
 *
 * O QUE A BASE JÁ ENTREGA MASTIGADO (e por isso não recalculamos nada aqui):
 *   rastreabilidade           "3. Completa (Solic - Pedido - NF)" | "2. Pedido sem solicitacao" | "1. Sem pedido vinculado"
 *   status_divergencia_preco  preço da NF × preço do pedido, em faixas (até 5% / acima de 5%)
 *   situacao_entrega          Quantidade integral | Entrega parcial | Nota acima do pedido | Sem pedido
 *   dias_solic_pedido / dias_pedido_nf / dias_solic_nf   o tempo de cada perna da cadeia
 *   impacto_preco             quanto R$ a divergência custou (ou devolveu) naquela linha
 *
 * ⚠️ O GRÃO DA TABELA É A APROPRIAÇÃO, NÃO A NOTA. Consequências que o código respeita:
 *
 *  (1) A CHAVE DA NOTA é `nf_colidmov` ("22-8417" — a coligada vem embutida). O `nf_numero` NÃO
 *      serve de chave: numa amostra de 10 mil linhas, 309 números de nota apareciam em mais de um
 *      colidmov (fornecedores diferentes emitem a nota nº 40 no mesmo ano). Conferido: um colidmov
 *      tem sempre 1 fornecedor, 1 número e 1 coligada — é a nota mesmo.
 *
 *  (2) O VALOR DA NOTA NÃO É A SOMA DAS LINHAS. Uma linha de NF pode ser apropriada em VÁRIAS
 *      tarefas/obras (828 de 8.595 itens da amostra), e aí `nf_valor_item` se REPETE em cada
 *      pedaço enquanto `valor_apropriado` é que se divide. Somar as linhas cruas inflaria a nota
 *      (a NF 74685 da BLB viraria R$ 83.510 no lugar de R$ 70.000 — 19% a mais).
 *      Por isso somamos `nf_valor_item` uma vez por (colidmov, item_seq) — o set `seqs`.
 *
 *  (3) Uma nota pode atender VÁRIOS pedidos (248 de 4.260 na amostra) e cair em VÁRIAS obras (102).
 *      Por isso pedidos/obras/solicitações são conjuntos, não campos.
 *
 * GET  (busca)     ?q=&obra=&periodo=30d|3m|ano|tudo&de=&ate=&tipo=&rastro=&diverg=&entrega=&contrato=
 *                  &sort=data|numero|fornecedor|obra|valor|itens|diverg&dir=&pagina=&me=
 * GET  ?obras=1    lista de obras p/ o filtro (montada da PRÓPRIA base, com cache de 30min)
 * GET  ?nf=<colidmov>   a nota inteira: cabeçalho + linhas + a cadeia SC→PC→NF de cada item
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/coligadas.php';
require_once __DIR__ . '/../includes/sb_pag.php';
/* Biblioteca da Busca de Pedidos: bp_status_label (a tabela oficial de status do TOTVS, passada
   pelo Murilo) e bp_nz (normalização p/ ordenar sem acento). Copiar as duas seria começar a
   divergir logo na primeira correção. */
define('BP_LIB_ONLY', 1); require_once __DIR__ . '/busca_pedidos.php';

define('BN_TABELA', 'apropriacoes_compras');
define('BN_MAX_LINHAS', 30000);   // teto de linhas lidas por consulta (a base inteira tem ~45 mil)
define('BN_POR_PAGINA', 30);

function bn_get($query)                  { return sbp_get(BN_TABELA, $query); }
function bn_varrer($query, callable $cb) { return sbp_varrer(BN_TABELA, $query, $cb, BN_MAX_LINHAS); }

/* ---------------------------------------------------------------------------
   Traduções. Os textos crus vêm do DAX do Murilo, sem acento e às vezes longos
   demais p/ caber numa célula; aqui viram chave curta (filtro) + rótulo (tela).
   --------------------------------------------------------------------------- */

/** Tipo da nota pelo prefixo do `nf_tipo` ("NFE - Entrada NF Compra de Materiais (CP/EST)"). */
function bn_tipo($nfTipo) {
    $p = strtoupper(trim(explode(' ', trim((string)$nfTipo) . ' ')[0]));
    static $M = ['NFE' => ['material', 'Material'], 'NES' => ['servico', 'Serviço'],
                 'FAT' => ['locacao', 'Locação'],   'ALUG' => ['locacao', 'Locação'],
                 'CTRC' => ['frete', 'Frete']];
    return $M[$p] ?? ['outro', ($p !== '' ? $p : '—')];
}
/** Prefixo do TOTVS a filtrar, por chave curta (o filtro vai NA QUERY, não na memória). */
function bn_tipo_prefixos($k) {
    static $M = ['material' => ['NFE'], 'servico' => ['NES'], 'locacao' => ['FAT', 'ALUG'], 'frete' => ['CTRC']];
    return $M[$k] ?? [];
}

/** "3. Completa (Solic - Pedido - NF)" -> ['completa', 'Completa (SC → PC → NF)'] */
function bn_rastro($txt) {
    $t = strtolower((string)$txt);
    if (strpos($t, '3.') === 0) return ['completa',   'Completa (SC → PC → NF)'];
    if (strpos($t, '2.') === 0) return ['sem_solic',  'Pedido sem solicitação'];
    if (strpos($t, '1.') === 0) return ['sem_pedido', 'Nota sem pedido'];
    return ['outro', trim((string)$txt) !== '' ? (string)$txt : '—'];
}
function bn_rastro_filtro($k) {
    static $M = ['completa' => '3.', 'sem_solic' => '2.', 'sem_pedido' => '1.'];
    return $M[$k] ?? '';
}

/**
 * SUSPEITA DE UNIDADE / LANÇAMENTO — diferença de preço grande demais para ser negociação.
 *
 * Nasceu de um número que não podia estar certo: o impacto de 3 meses dava −R$ 659 mil e **80% disso
 * era UMA nota** — a NF 6122 (TOPGESSO, obra Polastri), com o pedido cotado por SACO (R$ 27,50) e a
 * nota lançada por outra unidade (R$ 1,4489 × 20.360). O DAX lê como "−94,7% de economia"; não é
 * economia nenhuma, é unidade de medida diferente entre o PC e a NF. Outro flagrante do mesmo tipo:
 * GESSO COLA, pedido 20 × R$ 60,00 e nota 60 × R$ 5,00.
 *
 * O corte de 70% não é chute: na base inteira (45 mil linhas) existem só 26 linhas acima dele, e
 * todas com essa cara. Ninguém negocia 70% no preço unitário de um item já pedido.
 *
 * O rótulo PERGUNTA, não acusa ("confira a unidade") — parte dos casos pode ser medição parcial
 * lançada de outro jeito, e quem sabe é a obra. E o R$ dessas notas fica FORA do impacto do
 * recorte, contado à parte: senão um erro de digitação vira "ganho" no número que a diretoria olha.
 */
define('BN_DIF_SUSPEITA', 70.0);
function bn_suspeita($pct) { return $pct !== null && abs((float)$pct) >= BN_DIF_SUSPEITA; }

/** Divergência de preço NF × pedido. */
function bn_diverg($txt) {
    $t = strtolower((string)$txt);
    // ">" só existe na faixa "(> 5%)"; a outra é "(ate 5%)" — é o que separa o alerta do ruído
    if (strpos($t, 'acima') === 0)  return [strpos($t, '>') !== false ? 'acima5' : 'acima', (string)$txt];
    if (strpos($t, 'abaixo') === 0) return [strpos($t, '>') !== false ? 'abaixo5' : 'abaixo', (string)$txt];
    if (strpos($t, 'sem preco') === 0 || strpos($t, 'sem preço') === 0) return ['sem_preco', 'Sem preço no pedido'];
    if (strpos($t, 'mantido') !== false) return ['mantido', 'Preço mantido'];
    // texto novo vindo do DAX: mostra como veio, em vez de rotular errado
    return [$t === '' ? '' : 'outro', $t === '' ? '—' : (string)$txt];
}
/** Gravidade: a nota herda a PIOR linha, e é por ela que a coluna ordena.
 *  `suspeita` vem no topo porque é a única que pede conferência ANTES de acreditar no número. */
function bn_diverg_peso($k) {
    static $P = ['suspeita' => 6, 'acima5' => 5, 'acima' => 4, 'sem_preco' => 3, 'abaixo5' => 2,
                 'abaixo' => 1, 'outro' => 1, 'mantido' => 0];
    return $P[$k] ?? -1;   // '' (nada visto ainda) perde até p/ "mantido" — senão a nota certinha ficava sem rótulo
}

/** Situação de entrega (quantidade da NF × quantidade do pedido). */
function bn_entrega($txt) {
    $t = strtolower((string)$txt);
    if (strpos($t, 'integral') !== false)   return ['integral',   'Quantidade integral'];
    if (strpos($t, 'parcial') !== false)    return ['parcial',    'Entrega parcial'];
    if (strpos($t, 'acima') !== false)      return ['acima',      'Nota acima do pedido'];
    if (strpos($t, 'sem pedido') !== false) return ['sem_pedido', 'Sem pedido'];
    return [$t === '' ? '' : 'outro', $t === '' ? '—' : (string)$txt];
}
function bn_entrega_peso($k) {
    static $P = ['acima' => 3, 'sem_pedido' => 2, 'parcial' => 1, 'outro' => 1, 'integral' => 0];
    return $P[$k] ?? -1;
}

/** Número do TOTVS sem os zeros à esquerda ("000000317" -> "317"). */
function bn_curto($n) { $n = trim((string)$n); return ltrim($n, '0') !== '' ? ltrim($n, '0') : $n; }

try {
    $pdo = db();
    $perms = user_perms($pdo, $_GET['me'] ?? null);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }

    /* ---- ?obras=1 : lista pro filtro, montada da PRÓPRIA base ----
       Mesma escolha da Busca de Pedidos: o dropdown mostra exatamente o que existe em nota — nada
       de sair da ficha de obras e deixar de fora tudo que não tem de-para. Aqui é ainda melhor,
       porque o APROPS já entrega a OBRA da apropriação (não a coligada): "Obra - Lagune",
       "Sede - Caprem - R01", "Serviço de Assistência Técnica - Leven". */
    if (isset($_GET['obras'])) {
        $cache = __DIR__ . '/../data/.bn_obras.json';
        if (empty($_GET['recarregar']) && is_file($cache) && (time() - filemtime($cache)) < 1800) {
            echo file_get_contents($cache); exit;
        }
        $agg = [];
        bn_varrer('select=obra_nome&order=obra_nome.asc,nf_colidmov.asc',
            function (array $lote) use (&$agg) {
                foreach ($lote as $r) {
                    $nome = trim((string)($r['obra_nome'] ?? '')); if ($nome === '') continue;
                    if (!isset($agg[$nome])) $agg[$nome] = ['chave' => $nome, 'label' => $nome, 'n' => 0];
                    $agg[$nome]['n']++;
                }
            });
        $lista = array_values($agg);
        usort($lista, fn($a, $b) => strcmp(bp_nz($a['label']), bp_nz($b['label'])));
        $json = json_encode(['obras' => $lista], JSON_UNESCAPED_UNICODE);
        @file_put_contents($cache, $json);
        echo $json; exit;
    }

    /* ---- ?nf=<colidmov> : A NOTA INTEIRA ----
       O detalhe é onde a cadeia aparece por extenso: cada linha da nota com a sua tarefa, o pedido
       que a originou, a solicitação que originou o pedido, e a comparação de preço e quantidade.
       Consulta direta pelo colidmov — sem varredura, sem teto. */
    if (isset($_GET['nf'])) {
        $cm = trim((string)$_GET['nf']);
        if ($cm === '') { echo json_encode(['error' => 'Nota não informada.']); exit; }
        $rows = bn_get('select=*&nf_colidmov=eq.' . rawurlencode($cm) . '&order=nf_item_seq.asc,tarefa_cod.asc&limit=500');
        if (!$rows) { http_response_code(404); echo json_encode(['error' => 'Nota não encontrada.']); exit; }

        $r0 = $rows[0]; $itens = []; $seqs = []; $valor = 0.0; $peds = []; $sols = [];
        foreach ($rows as $r) {
            $seq = (int)($r['nf_item_seq'] ?? 0);
            $novo = !isset($seqs[$seq]);
            if ($novo) { $seqs[$seq] = 1; $valor += (float)($r['nf_valor_item'] ?? 0); }
            [$dk, $dl] = bn_diverg($r['status_divergencia_preco'] ?? '');
            if (bn_suspeita($r['dif_preco_pct'] ?? null)) { $dk = 'suspeita'; $dl = 'Confira a unidade — preço muito fora do pedido'; }
            [$ek, $el] = bn_entrega($r['situacao_entrega'] ?? '');
            $itens[] = [
                'seq' => $seq, 'produto_cod' => $r['produto_cod'] ?? '', 'produto' => $r['produto_nome'] ?? '',
                'unidade' => $r['unidade'] ?? '', 'tarefa_cod' => $r['tarefa_cod'] ?? '', 'tarefa' => $r['tarefa_nome'] ?? '',
                'obra' => $r['obra_nome'] ?? '', 'obra_cod' => $r['obra_cod'] ?? '',
                'nf_qtd' => (float)($r['nf_qtd'] ?? 0), 'nf_preco' => (float)($r['nf_preco_unit'] ?? 0),
                'nf_valor' => (float)($r['nf_valor_item'] ?? 0),
                // linha de RATEIO: 2º (ou 3º…) pedaço do mesmo item da nota, apropriado em outra tarefa/obra
                'rateada' => $novo ? 0 : 1,
                'qtd_apropriada' => (float)($r['qtd_apropriada'] ?? 0), 'valor_apropriado' => (float)($r['valor_apropriado'] ?? 0),
                'pedido' => $r['pedido_numero'] ?? '', 'pedido_seq' => (int)($r['pedido_item_seq'] ?? 0),
                'pedido_preco' => ($r['pedido_preco_unit'] ?? null) !== null ? (float)$r['pedido_preco_unit'] : null,
                'pedido_qtd' => ($r['pedido_qtd_original'] ?? null) !== null ? (float)$r['pedido_qtd_original'] : null,
                'pedido_saldo' => ($r['pedido_qtd_saldo'] ?? null) !== null ? (float)$r['pedido_qtd_saldo'] : null,
                'solic' => $r['solic_numero'] ?? '', 'solic_data' => substr((string)($r['solic_data'] ?? ''), 0, 10),
                'dif_pct' => ($r['dif_preco_pct'] ?? null) !== null ? (float)$r['dif_preco_pct'] : null,
                'impacto' => ($r['impacto_preco'] ?? null) !== null ? (float)$r['impacto_preco'] : null,
                'diverg' => $dk, 'diverg_label' => $dl, 'entrega' => $ek, 'entrega_label' => $el,
                'base' => $r['base_comparavel'] ?? '',
            ];
            $pn = trim((string)($r['pedido_numero'] ?? ''));
            if ($pn !== '' && !isset($peds[$pn])) $peds[$pn] = ['numero' => $pn, 'curto' => bn_curto($pn),
                'data' => substr((string)($r['pedido_data'] ?? ''), 0, 10), 'status' => (string)($r['pedido_status'] ?? ''),
                'status_label' => bp_status_label($r['pedido_status'] ?? ''), 'tipo' => (string)($r['pedido_tipo'] ?? ''),
                'colidmov' => (string)($r['pedido_colidmov'] ?? '')];
            $sn = trim((string)($r['solic_numero'] ?? ''));
            if ($sn !== '') $sols[$sn] = ['numero' => $sn, 'curto' => bn_curto($sn),
                'data' => substr((string)($r['solic_data'] ?? ''), 0, 10), 'colidmov' => (string)($r['solic_colidmov'] ?? '')];
        }
        [$tk, $tl] = bn_tipo($r0['nf_tipo'] ?? '');
        [$rk, $rl] = bn_rastro($r0['rastreabilidade'] ?? '');
        $nota = [
            'colidmov' => $cm, 'numero' => (string)($r0['nf_numero'] ?? ''), 'curto' => bn_curto($r0['nf_numero'] ?? ''),
            'serie' => (string)($r0['nf_serie'] ?? ''), 'data' => substr((string)($r0['nf_data'] ?? ''), 0, 10),
            'status' => (string)($r0['nf_status'] ?? ''), 'status_label' => bp_status_label($r0['nf_status'] ?? ''),
            'tipo' => $tk, 'tipo_label' => $tl, 'tipo_totvs' => (string)($r0['nf_tipo'] ?? ''),
            'coligada' => (string)($r0['coligada_nome'] ?? ''), 'coligada_cod' => (string)($r0['coligada_cod'] ?? ''),
            'fornecedor' => (string)($r0['fornecedor_nome'] ?? ''), 'fornecedor_cod' => (string)($r0['fornecedor_cod'] ?? ''),
            'cnpj' => (string)($r0['fornecedor_cnpj'] ?? ''),
            'rastro' => $rk, 'rastro_label' => $rl,
            'valor' => round($valor, 2), 'n_itens' => count($seqs), 'n_linhas' => count($rows),
            'competencia' => sprintf('%02d/%04d', (int)($r0['mes_referencia'] ?? 0), (int)($r0['ano_referencia'] ?? 0)),
            'data_apropriacao' => substr((string)($r0['data_apropriacao'] ?? ''), 0, 10),
            'dias_solic_pedido' => $r0['dias_solic_pedido'], 'dias_pedido_nf' => $r0['dias_pedido_nf'],
            'dias_solic_nf' => $r0['dias_solic_nf'],
            'contrato' => (int)($r0['eh_contrato'] ?? 0), 'contrato_valor' => $r0['contrato_valor'],
            'atualizado_em' => $r0['atualizado_em'] ?? '',
        ];
        echo json_encode(['ok' => true, 'nota' => $nota, 'itens' => $itens,
            'pedidos' => array_values($peds), 'solicitacoes' => array_values($sols)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ================================ BUSCA ================================ */
    $q       = trim((string)($_GET['q'] ?? ''));
    $obra    = trim((string)($_GET['obra'] ?? ''));
    $periodo = (string)($_GET['periodo'] ?? '3m');
    $tipo    = strtolower(trim((string)($_GET['tipo'] ?? '')));
    $rastro  = strtolower(trim((string)($_GET['rastro'] ?? '')));
    $diverg  = strtolower(trim((string)($_GET['diverg'] ?? '')));
    $entrega = strtolower(trim((string)($_GET['entrega'] ?? '')));
    $contrato = trim((string)($_GET['contrato'] ?? ''));
    $sort    = (string)($_GET['sort'] ?? 'data');
    $dir     = strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
    $pagina  = max(1, (int)($_GET['pagina'] ?? 1));

    $f = [];
    // período pela DATA DA NOTA (default 3 meses — a base inteira são ~45 mil linhas)
    $de = trim((string)($_GET['de'] ?? '')); $ate = trim((string)($_GET['ate'] ?? ''));
    if ($de === '' && $ate === '') {
        if ($periodo === '30d')     $de = date('Y-m-d', strtotime('-30 days'));
        elseif ($periodo === '3m')  $de = date('Y-m-d', strtotime('-3 months'));
        elseif ($periodo === 'ano') $de = date('Y') . '-01-01';
        // 'tudo' → sem corte
    }
    if ($de !== '')   $f[] = 'nf_data=gte.' . rawurlencode($de);
    if ($ate !== '')  $f[] = 'nf_data=lte.' . rawurlencode($ate);
    if ($obra !== '') $f[] = 'obra_nome=eq.' . rawurlencode($obra);

    $pref = bn_tipo_prefixos($tipo);
    if (count($pref) === 1) $f[] = 'nf_tipo=like.' . rawurlencode($pref[0] . '*');
    elseif ($pref)          $f[] = 'or=(' . implode(',', array_map(fn($p) => 'nf_tipo.like.' . rawurlencode($p . '*'), $pref)) . ')';

    $rf = bn_rastro_filtro($rastro);
    if ($rf !== '') $f[] = 'rastreabilidade=like.' . rawurlencode($rf . '*');

    if ($diverg === 'suspeita') {
        // aqui o filtro é pelo NÚMERO, não pelo texto do DAX — a suspeita é derivada nossa
        $f[] = 'or=(dif_preco_pct.lte.-' . BN_DIF_SUSPEITA . ',dif_preco_pct.gte.' . BN_DIF_SUSPEITA . ')';
    } elseif ($diverg !== '') {
        // "acima" (a categoria) pega as duas faixas; "acima5" só a de mais de 5%
        $mapa = ['acima' => 'Acima do pedido*', 'acima5' => 'Acima do pedido (> 5%)',
                 'abaixo' => 'Abaixo do pedido*', 'abaixo5' => 'Abaixo do pedido (> 5%)',
                 'mantido' => 'Preco mantido', 'sem_preco' => 'Sem preco no pedido'];
        if (isset($mapa[$diverg])) {
            $v = $mapa[$diverg];
            $f[] = 'status_divergencia_preco=' . (substr($v, -1) === '*' ? 'like.' : 'eq.') . rawurlencode($v);
        }
    }
    if ($entrega !== '') {
        $mapa = ['integral' => 'Quantidade integral', 'parcial' => 'Entrega parcial',
                 'acima' => 'Nota acima do pedido', 'sem_pedido' => 'Sem pedido'];
        if (isset($mapa[$entrega])) $f[] = 'situacao_entrega=eq.' . rawurlencode($mapa[$entrega]);
    }
    if ($contrato === '1')      $f[] = 'eh_contrato=eq.1';
    elseif ($contrato === '0')  $f[] = 'eh_contrato=eq.0';

    /* Busca ampla. Cobre as três pontas da cadeia (nota, pedido, solicitação) mais o fornecedor, o
       produto e a tarefa da apropriação — que é como a pessoa lembra do gasto ("aquela nota da
       Metroform", "a NF do pedido 4267", "gesso"). O CNPJ entra porque é o que se tem em mãos
       quando a dúvida vem do financeiro. */
    if ($q !== '') {
        $t = str_replace(['*', ',', '(', ')'], ' ', $q);
        $like = '*' . rawurlencode($t) . '*';
        $f[] = 'or=(' . implode(',', [
            'nf_numero.ilike.' . $like, 'fornecedor_nome.ilike.' . $like, 'fornecedor_cnpj.ilike.' . $like,
            'pedido_numero.ilike.' . $like, 'solic_numero.ilike.' . $like,
            'produto_nome.ilike.' . $like, 'produto_cod.ilike.' . $like, 'tarefa_nome.ilike.' . $like,
        ]) . ')';
    }

    /* SÓ o que a LINHA DA TABELA usa. A varredura pode ler 30 mil linhas, e cada coluna a mais
       custa ~1 MB de download: `coligada_nome` (razão social) sai porque o de-para por código já
       existe em includes/coligadas.php, e `tarefa_nome` sai porque a tarefa da apropriação só
       aparece no detalhe da nota — que é consulta direta, sem varredura. */
    $sel = 'select=nf_colidmov,nf_numero,nf_serie,nf_data,nf_status,nf_tipo,nf_item_seq,nf_valor_item,'
         . 'coligada_cod,obra_nome,fornecedor_nome,fornecedor_cnpj,produto_nome,pedido_numero,solic_numero,'
         . 'rastreabilidade,status_divergencia_preco,situacao_entrega,impacto_preco,dif_preco_pct,eh_contrato,'
         . 'dias_pedido_nf,dias_solic_nf';

    /* ---- agrega LINHA DE APROPRIAÇÃO → NOTA (chave: nf_colidmov) ----
       Roda a cada página que chega: nada de segurar 30 mil linhas cruas na memória. */
    $nf = [];
    $agrega = function (array $lote) use (&$nf) {
        foreach ($lote as $r) {
            $cm = trim((string)($r['nf_colidmov'] ?? '')); if ($cm === '') continue;
            if (!isset($nf[$cm])) {
                [$tk, $tl] = bn_tipo($r['nf_tipo'] ?? '');
                [$rk, $rl] = bn_rastro($r['rastreabilidade'] ?? '');
                $nf[$cm] = ['colidmov' => $cm, 'numero' => (string)($r['nf_numero'] ?? ''),
                    'curto' => bn_curto($r['nf_numero'] ?? ''), 'serie' => (string)($r['nf_serie'] ?? ''),
                    'data' => substr((string)($r['nf_data'] ?? ''), 0, 10), 'status' => (string)($r['nf_status'] ?? ''),
                    'tipo' => $tk, 'tipo_label' => $tl, 'rastro' => $rk, 'rastro_label' => $rl,
                    'coligada_cod' => (string)($r['coligada_cod'] ?? ''),
                    'fornecedor' => trim((string)($r['fornecedor_nome'] ?? '')), 'cnpj' => (string)($r['fornecedor_cnpj'] ?? ''),
                    'contrato' => (int)($r['eh_contrato'] ?? 0),
                    'dias_pedido_nf' => $r['dias_pedido_nf'], 'dias_solic_nf' => $r['dias_solic_nf'],
                    'valor' => 0.0, 'seqs' => [], 'obras' => [], 'peds' => [], 'sols' => [], 'produtos' => [],
                    'diverg' => '', 'diverg_label' => '—', 'impacto' => 0.0, 'impacto_susp' => 0.0,
                    'entrega' => '', 'entrega_label' => '—'];
            }
            $g = &$nf[$cm];
            /* (2) do cabeçalho: valor e impacto somam UMA VEZ por item da nota; o rateio entre
               tarefas REPETE a linha. Vale para o `impacto_preco` também — ele é do ITEM inteiro
               (qtde da nota × diferença de preço), e vem repetido em cada pedaço: no item 9 da NF
               74685 os dois pedaços trazem −6,76, que é o impacto do item, não de cada metade.
               Somar linha a linha dobrava o impacto do recorte. */
            $seq = (int)($r['nf_item_seq'] ?? 0);
            $susp = bn_suspeita($r['dif_preco_pct'] ?? null);
            if (!isset($g['seqs'][$seq])) {
                $g['seqs'][$seq] = 1;
                $g['valor'] += (float)($r['nf_valor_item'] ?? 0);
                // impacto de item suspeito vai para o balde separado — não entra na conta do recorte
                $g[$susp ? 'impacto_susp' : 'impacto'] += (float)($r['impacto_preco'] ?? 0);
            }
            $ob = trim((string)($r['obra_nome'] ?? ''));     if ($ob !== '') $g['obras'][$ob] = 1;
            $pn = trim((string)($r['pedido_numero'] ?? '')); if ($pn !== '') $g['peds'][$pn] = 1;
            $sn = trim((string)($r['solic_numero'] ?? ''));  if ($sn !== '') $g['sols'][$sn] = 1;
            $pr = trim((string)($r['produto_nome'] ?? ''));  if ($pr !== '' && count($g['produtos']) < 3) $g['produtos'][$pr] = 1;
            // a nota herda a PIOR divergência e a PIOR situação de entrega das suas linhas
            [$dk, $dl] = bn_diverg($r['status_divergencia_preco'] ?? '');
            if ($susp) { $dk = 'suspeita'; $dl = 'Confira a unidade — preço muito fora do pedido'; }
            if (bn_diverg_peso($dk) > bn_diverg_peso($g['diverg'])) { $g['diverg'] = $dk; $g['diverg_label'] = $dl; }
            [$ek, $el] = bn_entrega($r['situacao_entrega'] ?? '');
            if (bn_entrega_peso($ek) > bn_entrega_peso($g['entrega'])) { $g['entrega'] = $ek; $g['entrega_label'] = $el; }
            unset($g);
        }
    };

    /* Ordem com desempate determinístico: paginar por offset com muitas datas repetidas
       embaralharia as linhas entre as páginas (linha duplicada numa, sumida noutra). */
    $lidos = bn_varrer($sel . ($f ? '&' . implode('&', $f) : '')
        . '&order=nf_data.desc,nf_colidmov.desc,nf_item_seq.asc', $agrega);
    $truncado = $lidos >= BN_MAX_LINHAS;

    $lista = []; $resumo = ['completa' => 0, 'sem_solic' => 0, 'sem_pedido' => 0, 'outro' => 0];
    $alerta = ['acima' => 0, 'abaixo' => 0, 'parcial' => 0, 'sem_preco' => 0, 'suspeita' => 0];
    $valorTotal = 0.0; $impactoTotal = 0.0; $impactoSusp = 0.0;
    foreach ($nf as $g) {
        $g['n_itens'] = count($g['seqs']); unset($g['seqs']);
        $g['obras'] = array_keys($g['obras']); $g['obra'] = $g['obras'][0] ?? '';
        $g['pedidos'] = array_map('bn_curto', array_keys($g['peds'])); unset($g['peds']);
        $g['solicitacoes'] = array_map('bn_curto', array_keys($g['sols'])); unset($g['sols']);
        $g['produtos'] = array_keys($g['produtos']);
        $g['coligada'] = coligada_nome($g['coligada_cod']);
        $g['valor'] = round($g['valor'], 2);
        $g['impacto'] = round($g['impacto'], 2); $g['impacto_susp'] = round($g['impacto_susp'], 2);
        $g['status_label'] = bp_status_label($g['status']);
        $resumo[$g['rastro']] = ($resumo[$g['rastro']] ?? 0) + 1;
        if ($g['diverg'] === 'suspeita') $alerta['suspeita']++;
        if ($g['diverg'] === 'acima'  || $g['diverg'] === 'acima5')  $alerta['acima']++;
        if ($g['diverg'] === 'abaixo' || $g['diverg'] === 'abaixo5') $alerta['abaixo']++;
        if ($g['diverg'] === 'sem_preco') $alerta['sem_preco']++;
        if ($g['entrega'] === 'parcial')  $alerta['parcial']++;
        $valorTotal += $g['valor']; $impactoTotal += $g['impacto']; $impactoSusp += $g['impacto_susp'];
        $lista[] = $g;
    }

    $cmp = [
        'numero'     => fn($a, $b) => ((int)ltrim($a['numero'], '0')) <=> ((int)ltrim($b['numero'], '0')),
        'data'       => fn($a, $b) => strcmp($a['data'], $b['data']) ?: strcmp($a['colidmov'], $b['colidmov']),
        'fornecedor' => fn($a, $b) => strcasecmp($a['fornecedor'], $b['fornecedor']),
        'obra'       => fn($a, $b) => strcasecmp($a['obra'], $b['obra']),
        'valor'      => fn($a, $b) => $a['valor'] <=> $b['valor'],
        'itens'      => fn($a, $b) => $a['n_itens'] <=> $b['n_itens'],
        // divergência ordena por GRAVIDADE e desempata pelo impacto em R$ — é o que muda a conversa
        'diverg'     => fn($a, $b) => (bn_diverg_peso($a['diverg']) <=> bn_diverg_peso($b['diverg']))
                                      ?: (abs($a['impacto']) <=> abs($b['impacto'])),
        'pedido'     => fn($a, $b) => strcmp($a['pedidos'][0] ?? '', $b['pedidos'][0] ?? ''),
    ];
    $fn = $cmp[$sort] ?? $cmp['data'];
    usort($lista, $dir === 'asc' ? $fn : fn($a, $b) => -$fn($a, $b));

    $total = count($lista);
    $paginas = max(1, (int)ceil($total / BN_POR_PAGINA));
    if ($pagina > $paginas) $pagina = $paginas;
    $page = array_slice($lista, ($pagina - 1) * BN_POR_PAGINA, BN_POR_PAGINA);

    echo json_encode(['ok' => true, 'notas' => $page, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas,
        'por_pagina' => BN_POR_PAGINA, 'linhas_lidas' => $lidos, 'truncado' => $truncado,
        'sort' => $sort, 'dir' => $dir, 'resumo' => $resumo, 'alerta' => $alerta,
        'valor_total' => round($valorTotal, 2), 'impacto_total' => round($impactoTotal, 2),
        'impacto_suspeito' => round($impactoSusp, 2), 'corte_suspeita' => BN_DIF_SUSPEITA,
        'filtros' => ['q' => $q, 'obra' => $obra, 'periodo' => $periodo, 'tipo' => $tipo, 'rastro' => $rastro,
                      'diverg' => $diverg, 'entrega' => $entrega, 'contrato' => $contrato],
        'periodo' => ['de' => $de, 'ate' => $ate]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
