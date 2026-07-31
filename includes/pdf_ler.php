<?php
/**
 * LEITOR MÍNIMO DE PDF — só o suficiente para CONFERIR um anexo.
 *
 * Enquanto o gerador não puder montar o PDF sozinho (faltam data de entrega e condição de pagamento
 * no export do TOTVS), o comprador anexa o arquivo à mão. E anexar à mão é exatamente o buraco da
 * regra 2: em 1.303 arquivos da pasta, 9 têm no NOME um número que não é o que está DENTRO.
 *
 * Então o anexo não é aceito no escuro: este leitor abre o PDF, acha o número do pedido e os CNPJs
 * lá dentro, e RECUSA o arquivo se não bater com o pedido que está sendo enviado.
 *
 * POR QUE NÃO BASTA PROCURAR O TEXTO CRU
 * O relatório do TOTVS escreve com fonte de subconjunto: no fluxo aparece <0036><0037><0024>… que
 * são IDs de glifo, não letras. Sem traduzir isso, o PDF parece "sem texto" e a conferência ficaria
 * sempre inconclusiva — que é o mesmo que não conferir. Por isso o leitor monta o mapa /ToUnicode
 * de cada fonte e traduz glifo -> caractere antes de procurar qualquer coisa.
 */

/** Objetos do PDF: número -> conteúdo bruto (dicionário + stream). */
function pdfl_objetos($bin) {
    $objs = [];
    if (preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)endobj/s', $bin, $m, PREG_SET_ORDER))
        foreach ($m as $o) $objs[(int)$o[1]] = $o[2];
    return $objs;
}

/** Conteúdo descomprimido do stream de um objeto (ou '' se não houver). */
function pdfl_stream($obj) {
    if (!preg_match('/stream\r?\n(.*?)endstream/s', $obj, $m)) return '';
    $s = $m[1];
    foreach (['gzuncompress', 'gzinflate'] as $f) { $d = @$f($s); if ($d !== false) return $d; }
    $d = @gzinflate(substr($s, 2));
    return $d !== false ? $d : $s;
}

/**
 * Mapa /ToUnicode de UM objeto de fonte: código do glifo -> texto.
 * Formato do CMap: blocos beginbfchar (<src> <dst>) e beginbfrange (<lo> <hi> <dst>|[<..>,<..>]).
 */
function pdfl_cmap($cmapTexto) {
    $map = [];
    $hex2str = function ($h) {
        $h = preg_replace('/[^0-9A-Fa-f]/', '', $h);
        $out = '';
        for ($i = 0; $i + 3 < strlen($h) + 1; $i += 4) {
            $cod = hexdec(substr($h, $i, 4));
            if ($cod === 0) continue;
            /* UTF-16 -> Latin-1 quando couber; o que não couber vira '?' e não atrapalha a busca
               por números, que é o que decide a conferência. */
            $out .= ($cod < 256) ? chr($cod) : (($cod >= 0x2000 && $cod <= 0x206F) ? ' ' : '?');
        }
        return $out;
    };
    if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmapTexto, $bc))
        foreach ($bc[1] as $bloco)
            if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $bloco, $pares, PREG_SET_ORDER))
                foreach ($pares as $p) $map[hexdec($p[1])] = $hex2str($p[2]);

    if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmapTexto, $br))
        foreach ($br[1] as $bloco) {
            // <lo> <hi> <dst>
            if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $bloco, $r, PREG_SET_ORDER))
                foreach ($r as $x) {
                    $lo = hexdec($x[1]); $hi = hexdec($x[2]); $base = hexdec($x[3]);
                    if ($hi - $lo > 5000) continue;                      // faixa absurda: ignora
                    for ($c = $lo; $c <= $hi; $c++) {
                        $cod = $base + ($c - $lo);
                        $map[$c] = ($cod < 256) ? chr($cod) : '?';
                    }
                }
            // <lo> <hi> [<d1> <d2> ...]
            if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*\[(.*?)\]/s', $bloco, $r2, PREG_SET_ORDER))
                foreach ($r2 as $x) {
                    $lo = hexdec($x[1]);
                    if (preg_match_all('/<([0-9A-Fa-f]+)>/', $x[3], $ds))
                        foreach ($ds[1] as $i => $h) $map[$lo + $i] = $hex2str($h);
                }
        }
    return $map;
}

/** Todos os mapas de fonte do documento: nome do recurso (/F1) -> mapa. */
function pdfl_fontes($bin, $objs) {
    $porNome = []; $porObj = [];
    foreach ($objs as $n => $o) {
        if (strpos($o, '/ToUnicode') === false) continue;
        if (!preg_match('/\/ToUnicode\s+(\d+)\s+\d+\s+R/', $o, $m)) continue;
        $alvo = (int)$m[1];
        if (!isset($objs[$alvo])) continue;
        $porObj[$n] = pdfl_cmap(pdfl_stream($objs[$alvo]));
    }
    // liga o nome do recurso (/F1 12 0 R) ao objeto da fonte
    foreach ($objs as $o) {
        if (preg_match_all('/\/(F\w+)\s+(\d+)\s+\d+\s+R/', $o, $mm, PREG_SET_ORDER))
            foreach ($mm as $x) {
                $on = (int)$x[2];
                if (isset($porObj[$on]) && !isset($porNome[$x[1]])) $porNome[$x[1]] = $porObj[$on];
            }
    }
    return ['nome' => $porNome, 'qualquer' => $porObj];
}

function pdfl_unesc($s) {
    $s = strtr($s, ['\\(' => '(', '\\)' => ')', '\\\\' => '\\', '\\n' => ' ', '\\r' => ' ', '\\t' => ' ']);
    return preg_replace_callback('/\\\\([0-7]{1,3})/', fn($m) => chr(octdec($m[1])), $s);
}

/** Traduz uma string hexadecimal de glifos usando o mapa da fonte corrente. */
function pdfl_hex($h, $map) {
    $h = preg_replace('/[^0-9A-Fa-f]/', '', $h);
    $out = '';
    for ($i = 0; $i + 4 <= strlen($h); $i += 4) {
        $c = hexdec(substr($h, $i, 4));
        $out .= $map[$c] ?? '';
    }
    return $out;
}

/** Texto do PDF inteiro, já traduzido pelos mapas de fonte. */
function pdfl_texto($bin) {
    $objs = pdfl_objetos($bin);
    if (!$objs) return '';
    $fontes = pdfl_fontes($bin, $objs);
    $unico = $fontes['qualquer'] ? reset($fontes['qualquer']) : [];
    $txt = '';

    foreach ($objs as $o) {
        $d = pdfl_stream($o);
        if ($d === '' || (strpos($d, 'Tj') === false && strpos($d, 'TJ') === false)) continue;
        $map = $unico;
        // percorre o fluxo trocando o mapa a cada /Fx ... Tf
        $partes = preg_split('/(\/(F\w+)\s+[\d.]+\s+Tf)/', $d, -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($i = 0; $i < count($partes); $i++) {
            if (preg_match('/^\/(F\w+)\s+[\d.]+\s+Tf$/', $partes[$i], $mf)) {
                if (isset($fontes['nome'][$mf[1]])) $map = $fontes['nome'][$mf[1]];
                $i++; continue;                     // o próximo item é o nome capturado
            }
            $bloco = $partes[$i];
            if (preg_match_all('/\[(.*?)\]\s*TJ|\(((?:\\\\.|[^\\\\()])*)\)\s*Tj|<([0-9A-Fa-f\s]+)>\s*Tj/s', $bloco, $t, PREG_SET_ORDER))
                foreach ($t as $x) {
                    if (!empty($x[1])) {                                  // [ ... ] TJ
                        if (preg_match_all('/<([0-9A-Fa-f\s]+)>|\(((?:\\\\.|[^\\\\()])*)\)/s', $x[1], $p, PREG_SET_ORDER))
                            foreach ($p as $q)
                                $txt .= !empty($q[1]) ? pdfl_hex($q[1], $map) : pdfl_unesc($q[2] ?? '');
                        $txt .= ' ';
                    } elseif (isset($x[2]) && $x[2] !== '') $txt .= pdfl_unesc($x[2]) . ' ';
                    elseif (!empty($x[3])) $txt .= pdfl_hex($x[3], $map) . ' ';
                }
        }
    }
    return trim(preg_replace('/\s+/', ' ', $txt));
}

function pdfl_dig($s) { return preg_replace('/\D+/', '', (string)$s); }

/**
 * Confere se o PDF é MESMO o pedido esperado.
 * ['ok'=>bool, 'motivo'=>string, 'pc_no_pdf'=>string, 'cnpjs'=>[], 'trecho'=>string]
 */
function pdf_conferir($bin, $numeroEsperado, $cnpjEmpresa = '', $cnpjFornecedor = '') {
    $t = pdfl_texto($bin);
    $vazio = ['pc_no_pdf' => '', 'cnpjs' => [], 'trecho' => substr($t, 0, 300)];
    if (strlen($t) < 40)
        return ['ok' => false, 'motivo' => 'Não consegui ler o texto deste PDF (pode ser digitalizado). Sem ler o número de dentro, não dá para garantir que é o pedido certo.'] + $vazio;

    $pc = '';
    /* SEM o modificador /u: o texto sai em Latin-1 (o CMap devolve chr() de 0..255), e um "ú" vira
       o byte ú, que nao e UTF-8 valido. Com /u o preg_match falha CALADO e devolve false — foi
       o que fez a conferencia dizer "nao achei o numero" em todos os seis PDFs de teste. */
    /* O TOTVS grava o número com NOVE dígitos ("000003266") e, logo em seguida, sem espaço, o id
       interno do movimento ("18460"). Ler "quantos dígitos vierem" devolvia 326618460 e reprovava
       todos os PDFs. São exatamente nove. */
    if (preg_match('/N.mero de pedido de compra:?\s*(\d{9})/i', $t, $m)) $pc = $m[1];
    elseif (preg_match('/pedido de compra:?\s*0*(\d{3,9})\b/i', $t, $m)) $pc = $m[1];

    $esp = ltrim(pdfl_dig($numeroEsperado), '0');
    if ($pc === '')
        return ['ok' => false, 'motivo' => 'Não achei "Número de pedido de compra" dentro do PDF. O arquivo pode não ser um pedido do TOTVS.'] + $vazio;

    $cnpjs = [];
    if (preg_match_all('/(\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2})/', $t, $mm))
        foreach ($mm[1] as $c) $cnpjs[] = pdfl_dig($c);
    $cnpjs = array_values(array_unique($cnpjs));
    $ctx = ['pc_no_pdf' => ltrim($pc, '0'), 'cnpjs' => $cnpjs, 'trecho' => ''];

    if (ltrim($pc, '0') !== $esp)
        return ['ok' => false, 'motivo' => 'Este PDF é do pedido ' . ltrim($pc, '0') . ', não do ' . $esp
            . '. Arquivo trocado — foi assim que 9 dos 1.303 PDFs da pasta ficaram com o nome errado.'] + $ctx;

    /* O número sozinho NÃO identifica um pedido: repete entre coligadas (381 casos medidos).
       O CNPJ da empresa é o que amarra o PDF à coligada certa. */
    $ce = pdfl_dig($cnpjEmpresa);
    if ($ce !== '' && $cnpjs && !in_array($ce, $cnpjs, true))
        return ['ok' => false, 'motivo' => 'O CNPJ da empresa (' . $cnpjEmpresa . ') não aparece neste PDF. '
            . 'O número bate, mas o pedido é de outra coligada — o número se repete entre elas.'] + $ctx;

    $cf = pdfl_dig($cnpjFornecedor);
    $aviso = ($cf !== '' && $cnpjs && !in_array($cf, $cnpjs, true))
        ? 'confira: o CNPJ do fornecedor não aparece no PDF' : '';
    return ['ok' => true, 'motivo' => $aviso] + $ctx;
}
