<?php
/**
 * PDF DO PEDIDO — monta a partir da base do TOTVS e devolve o arquivo.
 *
 * GET ?pdf=<coligada>|<numero>&me=..            -> exibe o PDF no navegador
 * GET ?pdf=..&baixar=1                          -> força download
 *
 * DE ONDE VEM CADA COISA
 *   TOTVS (pedidos_itens)  número, data, coligada, fornecedor (cod/CNPJ/nome), itens, quantidades,
 *                          preços, observação, DATA DE ENTREGA POR ITEM, condição de pagamento,
 *                          frete e data de aprovação.
 *   Cadastro do cockpit    contato, telefone, e-mail e cidade do fornecedor — o export do TOTVS que
 *                          temos hoje não traz endereço; quando a tabela do Supabase existir, é só
 *                          trocar a fonte aqui.
 *   Configuração de envio  o bloco de entrega (endereço, horário, contatos, e-mail de NF). É o mesmo
 *                          texto do e-mail, então os dois nunca divergem — hoje o PDF do TOTVS diz
 *                          "8:00h" na Diamond enquanto o e-mail da obra diz "7:00h".
 */
header('Content-Type: application/json; charset=utf-8');
@date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../includes/db.php';
define('BP_LIB_ONLY', 1); require_once __DIR__ . '/busca_pedidos.php';
define('EC_LIB_ONLY', 1); require_once __DIR__ . '/envio_config.php';
require_once __DIR__ . '/../includes/pdf_pedido.php';

/**
 * Dados do fornecedor para o PDF. Duas fontes, nesta ordem:
 *   1. cot_fornecedor  — o NOSSO cadastro, que tem contato e telefone e é o mais atualizado
 *                        (o Murilo avisou: "muitos dos e-mails do TOTVS estão desatualizados");
 *   2. totvs_fornecedor — o espelho oficial, chaveado por CODCFO. É a chave exata: não depende de
 *                        como alguém digitou o nome nem de o CNPJ estar formatado igual.
 * Nenhuma das duas tem endereço de rua hoje; fica para a tabela do Supabase.
 */
function pp_fornecedor($pdo, $cnpj, $cod, $nome) {
    $cn = preg_replace('/\D+/', '', (string)$cnpj);
    $cc = ltrim(preg_replace('/\D+/', '', (string)$cod), '0');
    $r = ['contato' => '', 'fone' => '', 'email' => '', 'cidade' => '', 'uf' => '', 'endereco' => ''];
    try {
        $st = $pdo->prepare("SELECT contato, telefone, email, cidade FROM cot_fornecedor
                             WHERE REPLACE(REPLACE(REPLACE(REPLACE(cnpj,'.',''),'/',''),'-',''),' ','')=? LIMIT 1");
        $st->execute([$cn]);
        if ($x = $st->fetch()) {
            $r['contato'] = trim((string)$x['contato']); $r['fone'] = trim((string)$x['telefone']);
            $r['email'] = trim((string)$x['email']);     $r['cidade'] = trim((string)$x['cidade']);
        }
    } catch (Throwable $e) {}
    try {
        $st = $cc !== ''
            ? $pdo->prepare("SELECT cidade, uf, email FROM totvs_fornecedor WHERE codcfo=? LIMIT 1")
            : $pdo->prepare("SELECT cidade, uf, email FROM totvs_fornecedor
                             WHERE REPLACE(REPLACE(REPLACE(REPLACE(cnpj,'.',''),'/',''),'-',''),' ','')=? LIMIT 1");
        $st->execute([$cc !== '' ? $cc : $cn]);
        if ($x = $st->fetch())
            foreach (['cidade', 'uf', 'email'] as $k)
                if ($r[$k] === '' && trim((string)$x[$k]) !== '') $r[$k] = trim((string)$x[$k]);
    } catch (Throwable $e) {}
    return $r;
}

/** Cabeçalho da empresa (coligada). A ficha da obra é a fonte; a razão vem do próprio TOTVS. */
function pp_empresa($pdo, $razaoTotvs, $fichaId) {
    $e = ['razao' => trim((string)$razaoTotvs), 'endereco' => '', 'numero' => '', 'bairro' => '',
          'cep' => '', 'cidade' => '', 'estado' => '', 'cnpj' => '', 'ie' => '',
          'fone' => '(19) 3531-6600', 'email' => 'compras1@caprem.com.br'];
    if (!$fichaId) return $e;
    try {
        $st = $pdo->prepare("SELECT nome, cnpj, cidade, estado, endereco FROM obra_ficha WHERE id=? LIMIT 1");
        $st->execute([(int)$fichaId]);
        if ($o = $st->fetch()) {
            if (trim((string)$o['cnpj']) !== '')   $e['cnpj'] = trim((string)$o['cnpj']);
            if (trim((string)$o['cidade']) !== '') $e['cidade'] = trim((string)$o['cidade']);
            if (trim((string)$o['estado']) !== '') $e['estado'] = trim((string)$o['estado']);
        }
    } catch (Throwable $e2) {}
    return $e;
}

/** O bloco de entrega do PDF é o MESMO da configuração do e-mail — uma fonte só. */
function pp_rodape($pdo, $fichaId, $soObra = false) {
    $r = $fichaId ? ec_resolver($pdo, $fichaId) : null;
    if (!$r) return 'No corpo da nota deverá constar o número deste pedido, a CEI e o endereço. '
                  . 'Para envio de XML e NF-e digital: fiscal@caprem.com.br';
    $ef = $r['efetivo']; $t = [];
    if ($soObra) {   /* regularização: material já entregue, não há entrega a combinar */
        $t[] = 'Pedido de regularização — o material já foi entregue. Não há nova entrega a combinar.';
    } else {
        if (!empty($ef['endereco']))   $t[] = 'Endereço de entrega: ' . $ef['endereco']
                                            . (!empty($ef['complemento']) ? ' (' . $ef['complemento'] . ')' : '') . '.';
        if (!empty($ef['horario']))    $t[] = 'Horário de recebimento: ' . rtrim($ef['horario'], '.') . '.';
        if (!empty($ef['almox_nome'])) $t[] = 'Contato do almoxarifado: ' . trim($ef['almox_nome'] . ' ' . ($ef['almox_fone'] ?? '')) . '.';
        if (!empty($ef['eng_nome']))   $t[] = 'Engenheiro: ' . trim($ef['eng_nome'] . ' ' . ($ef['eng_fone'] ?? '')) . '.';
    }
    if (!empty($ef['cno']))      $t[] = 'Incluir na nota fiscal o CNO da obra: ' . $ef['cno'] . '.';
    if (!empty($ef['email_nf'])) $t[] = 'Cópia da NF para ' . $ef['email_nf'] . ' e fiscal@caprem.com.br.';
    $t[] = 'No corpo da nota deverá constar o número deste pedido, a CEI e o endereço. '
         . 'Caso alguma condição acima não seja cumprida, fica o cliente autorizado a suspender o '
         . 'recebimento e o pagamento até que as irregularidades sejam sanadas.';
    return implode(' ', $t);
}

/**
 * Monta o array do gerador para UM pedido. Devolve null se não achar.
 * $ctx: ['ficha_id'=>int, 'obra'=>string, 'so_obra'=>bool, 'aviso'=>string]
 */
function pp_montar($pdo, $coligada, $numero, $ctx = []) {
    $col = trim((string)$coligada);
    $num = ltrim(preg_replace('/\D+/', '', (string)$numero), '0');
    if ($col === '' || $num === '') return null;

    $linhas = [];
    bp_varrer('select=pedido_numero,pedido_data,coligada,coligada_nome,coligada_cod,codprd,produto,qtd,und,'
            . 'preco_unit,valor_total,item_observacao,fornecedor_cod,fornecedor_cnpj,fornecedor_nome,'
            . 'fornecedor_fantasia,pedido_usuario,data_entrega,cond_pagamento,valor_frete,data_aprovacao,seq'
            . '&coligada_cod=eq.' . rawurlencode($col)
            /* O TOTVS guarda o número com zeros à esquerda ("000002638"). */
            . '&pedido_numero=eq.' . rawurlencode(str_pad($num, 9, '0', STR_PAD_LEFT))
            . '&order=seq.asc',
        function ($lote) use (&$linhas) { foreach ($lote as $l) $linhas[] = $l; });
    if (!$linhas) return null;
    $c = $linhas[0];

    /* ============================ CONFERÊNCIA DE VALOR ============================
       O export traz valor_total ZERADO em parte dos itens, com qtd e preço corretos. Medido na
       janela desde maio: 620 de 9.000 itens (6,9%), em 191 pedidos, somando R$ 1,81 milhão que
       simplesmente sumiria do documento. Um pedido dizendo "R$ 0,00" numa tinta de R$ 559,90 é pior
       do que pedido nenhum — o fornecedor fatura errado ou liga. Então o PDF NÃO é gerado: o pedido
       vai para bloqueados até o dado ser corrigido na origem. Não calculo qtd×preço por conta
       própria porque isso ESCONDERIA o defeito e ainda erraria onde houver desconto. */
    $divs = [];
    foreach ($linhas as $l) {
        $calc = (float)($l['qtd'] ?? 0) * (float)($l['preco_unit'] ?? 0);
        if ((float)($l['valor_total'] ?? 0) == 0.0 && $calc > 0.009)
            $divs[] = trim((string)($l['produto'] ?? 'item')) . ' (qtd ' . rtrim(rtrim(number_format($l['qtd'], 2, ',', '.'), '0'), ',')
                    . ' × R$ ' . number_format($l['preco_unit'], 2, ',', '.') . ' mas o total veio zerado)';
    }

    $itens = [];
    foreach ($linhas as $l) {
        $itens[] = [
            'cod' => (string)($l['codprd'] ?? ''),
            'nome' => (string)($l['produto'] ?? ''),
            'descricao' => trim((string)($l['item_observacao'] ?? '')),
            'und' => (string)($l['und'] ?? ''),
            'qtd' => (float)($l['qtd'] ?? 0),
            'preco' => (float)($l['preco_unit'] ?? 0),
            'total' => (float)($l['valor_total'] ?? 0),
            /* A DATA DE ENTREGA é por ITEM — o Murilo frisou. Vira coluna, não campo do cabeçalho. */
            'entrega' => (string)($l['data_entrega'] ?? ''),
        ];
    }

    $fichaId = (int)($ctx['ficha_id'] ?? 0);
    $forn = pp_fornecedor($pdo, $c['fornecedor_cnpj'] ?? '', $c['fornecedor_cod'] ?? '', $c['fornecedor_nome'] ?? '');

    return [
        'divergencias' => $divs,
        'empresa' => pp_empresa($pdo, $c['coligada_nome'] ?? $c['coligada'] ?? '', $fichaId),
        'numero' => $num,
        'data' => (string)($c['pedido_data'] ?? ''),
        'aprovado_em' => (string)($c['data_aprovacao'] ?? ''),
        'cond_pagto' => trim((string)($c['cond_pagamento'] ?? '')),
        'frete' => (float)($c['valor_frete'] ?? 0),
        'comprador' => trim((string)($c['pedido_usuario'] ?? '')),
        'obra' => (string)($ctx['obra'] ?? ''),
        'fornecedor' => [
            'nome' => trim((string)($c['fornecedor_nome'] ?? '')) ?: trim((string)($c['fornecedor_fantasia'] ?? '')),
            'cod' => ltrim(trim((string)($c['fornecedor_cod'] ?? '')), '0'),
            'cnpj' => trim((string)($c['fornecedor_cnpj'] ?? '')),
        ] + $forn,
        'itens' => $itens,
        'rodape' => pp_rodape($pdo, $fichaId, !empty($ctx['so_obra'])),
        'aviso' => (string)($ctx['aviso'] ?? ''),
    ];
}

if (defined('PP_LIB_ONLY')) return;

try {
    $pdo = db();
    $perms = user_perms($pdo, $_GET['me'] ?? null);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }
    $p = explode('|', (string)($_GET['pdf'] ?? ''));
    $fichaId = (int)($_GET['ficha_id'] ?? 0);

    // sem ficha informada: acha pela razão social da coligada
    $obraNome = trim((string)($_GET['obra'] ?? ''));
    $dados = pp_montar($pdo, $p[0] ?? '', $p[1] ?? '', ['ficha_id' => $fichaId, 'obra' => $obraNome]);
    if (!$dados) { http_response_code(404); echo json_encode(['error' => 'pedido não encontrado']); exit; }
    if (!empty($dados['divergencias']) && empty($_GET['ignorar_valores'])) {
        http_response_code(409);
        echo json_encode(['error' => 'Os valores deste pedido não fecham na base do TOTVS, então o PDF não foi gerado.',
                          'divergencias' => $dados['divergencias']], JSON_UNESCAPED_UNICODE); exit;
    }

    $bin = pdf_pedido($dados);
    header_remove('Content-Type');
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (empty($_GET['baixar']) ? 'inline' : 'attachment')
         . '; filename="PC ' . str_pad(preg_replace('/\D+/', '', $p[1] ?? ''), 6, '0', STR_PAD_LEFT) . '.pdf"');
    header('Content-Length: ' . strlen($bin));
    echo $bin;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
