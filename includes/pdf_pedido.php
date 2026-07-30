<?php
/**
 * GERADOR DO PDF DO PEDIDO DE COMPRA — reproduz o relatório que o TOTVS imprime hoje.
 *
 * POR QUE GERAR EM VEZ DE LER DA PASTA
 * Hoje o comprador manda "imprimir" no TOTVS, salva o PDF numa pasta de rede e depois anexa à mão.
 * Isso quebra a regra 2 (nunca a obra errada) de um jeito que nenhuma trava minha alcança: medi
 * 1.303 arquivos e achei 9 em que o número no NOME não é o número que está DENTRO do documento.
 * Anexar por nome de arquivo é anexar no escuro. Gerando aqui, o anexo vem do mesmo registro que
 * decidiu a obra e o destinatário — não há como divergir.
 *
 * SEM BIBLIOTECA. Este servidor não tem FPDF, TCPDF nem mbstring. Então o PDF é escrito na mão:
 * objetos, xref e um fluxo de operadores de texto. As larguras da Helvetica estão na tabela abaixo
 * porque sem elas não dá para alinhar à direita nem quebrar linha no lugar certo — e uma coluna de
 * preço desalinhada é a primeira coisa que o fornecedor nota.
 *
 * ACENTO: o texto vai em WinAnsiEncoding (Latin-1). Converte-se UTF-8 -> CP1252 na saída; o que não
 * couber vira o caractere sem acento, nunca um quadradinho.
 */

/** Larguras da Helvetica em 1/1000 de ponto (código 32..126) — regular e negrito. */
function pdfp_larguras($negrito) {
    static $reg = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,
        556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,
        1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,
        667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,
        333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,
        556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
    static $neg = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,
        556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,
        975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,
        667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,
        333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,
        611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];
    return $negrito ? $neg : $reg;
}

/** Largura de um texto, em pontos, na fonte e tamanho dados. */
function pdfp_largura($txt, $tam, $negrito = false) {
    $w = pdfp_larguras($negrito); $s = pdfp_cp1252($txt); $t = 0;
    $n = strlen($s);
    for ($i = 0; $i < $n; $i++) {
        $c = ord($s[$i]);
        $t += ($c >= 32 && $c <= 126) ? $w[$c - 32] : 556;   // acentuados ~ largura média
    }
    return $t * $tam / 1000;
}

/** UTF-8 -> CP1252 (WinAnsi). Sem mbstring: tabela dos acentos que aparecem em português. */
function pdfp_cp1252($s) {
    $map = ['á'=>"\xE1",'à'=>"\xE0",'â'=>"\xE2",'ã'=>"\xE3",'ä'=>"\xE4",'é'=>"\xE9",'ê'=>"\xEA",
        'è'=>"\xE8",'ë'=>"\xEB",'í'=>"\xED",'î'=>"\xEE",'ì'=>"\xEC",'ï'=>"\xEF",'ó'=>"\xF3",
        'ô'=>"\xF4",'õ'=>"\xF5",'ò'=>"\xF2",'ö'=>"\xF6",'ú'=>"\xFA",'û'=>"\xFB",'ù'=>"\xF9",
        'ü'=>"\xFC",'ç'=>"\xE7",'ñ'=>"\xF1",'Á'=>"\xC1",'À'=>"\xC0",'Â'=>"\xC2",'Ã'=>"\xC3",
        'Ä'=>"\xC4",'É'=>"\xC9",'Ê'=>"\xCA",'È'=>"\xC8",'Í'=>"\xCD",'Î'=>"\xCE",'Ó'=>"\xD3",
        'Ô'=>"\xD4",'Õ'=>"\xD5",'Ö'=>"\xD6",'Ú'=>"\xDA",'Û'=>"\xDB",'Ü'=>"\xDC",'Ç'=>"\xC7",
        'Ñ'=>"\xD1",'º'=>"\xBA",'ª'=>"\xAA",'°'=>"\xB0",'–'=>'-','—'=>'-','“'=>'"','”'=>'"',
        '‘'=>"'",'’'=>"'",'…'=>'...','€'=>"\x80",'®'=>"\xAE",'©'=>"\xA9"];
    $s = strtr((string)$s, $map);
    /* Depois do mapa, cada acento nosso já é UM byte CP1252 (Ç=\xC7, Ã=\xC3). A limpeza tem de casar
       SÓ sequência UTF-8 que sobrou — byte-líder seguido de byte-de-continuação (\x80-\xBF). A versão
       anterior removia dois bytes altos QUAISQUER, e comia o "ÇÃ" de "INSCRIÇÃO". */
    return preg_replace('/[\xC2-\xF4][\x80-\xBF]+/', '', $s);
}

function pdfp_esc($s) { return strtr(pdfp_cp1252($s), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']); }

/** Quebra o texto em linhas que cabem em $larg pontos. */
function pdfp_quebrar($txt, $larg, $tam, $negrito = false) {
    $txt = trim(preg_replace('/\s+/', ' ', (string)$txt));
    if ($txt === '') return [];
    $out = []; $linha = '';
    foreach (explode(' ', $txt) as $p) {
        $tenta = $linha === '' ? $p : ($linha . ' ' . $p);
        if (pdfp_largura($tenta, $tam, $negrito) <= $larg) { $linha = $tenta; continue; }
        if ($linha !== '') $out[] = $linha;
        // palavra sozinha maior que a coluna: corta na força
        while (pdfp_largura($p, $tam, $negrito) > $larg && strlen($p) > 1) {
            $n = strlen($p);
            while ($n > 1 && pdfp_largura(substr($p, 0, $n), $tam, $negrito) > $larg) $n--;
            $out[] = substr($p, 0, $n); $p = substr($p, $n);
        }
        $linha = $p;
    }
    if ($linha !== '') $out[] = $linha;
    return $out;
}

/** Acumulador de páginas. */
class PdfPedido {
    public $paginas = [], $buf = '', $y = 0;
    const L = 595.32;   // A4: largura
    const A = 841.92;   // A4: altura
    public function novaPagina() { if ($this->buf !== '') $this->paginas[] = $this->buf; $this->buf = ''; $this->y = 800; }
    public function txt($x, $y, $s, $tam = 8, $neg = false) {
        if (trim((string)$s) === '') return;
        $this->buf .= 'BT /' . ($neg ? 'F2' : 'F1') . ' ' . $tam . ' Tf ' . round($x, 2) . ' ' . round($y, 2)
                    . ' Td (' . pdfp_esc($s) . ") Tj ET\n";
    }
    public function txtDir($xdir, $y, $s, $tam = 8, $neg = false) {
        $this->txt($xdir - pdfp_largura($s, $tam, $neg), $y, $s, $tam, $neg);
    }
    public function txtCentro($xc, $y, $s, $tam = 8, $neg = false) {
        $this->txt($xc - pdfp_largura($s, $tam, $neg) / 2, $y, $s, $tam, $neg);
    }
    public function linha($x1, $y1, $x2, $y2, $esp = 0.6, $cinza = 0.35) {
        $this->buf .= round($cinza, 2) . ' G ' . $esp . " w " . round($x1, 2) . ' ' . round($y1, 2)
                    . ' m ' . round($x2, 2) . ' ' . round($y2, 2) . " l S\n";
    }
    public function caixa($x, $y, $l, $a, $esp = 0.6, $cinza = 0.35) {
        $this->buf .= round($cinza, 2) . ' G ' . $esp . ' w ' . round($x, 2) . ' ' . round($y, 2)
                    . ' ' . round($l, 2) . ' ' . round($a, 2) . " re S\n";
    }
    public function fundo($x, $y, $l, $a, $cinza = 0.93) {
        $this->buf .= round($cinza, 2) . ' g ' . round($x, 2) . ' ' . round($y, 2) . ' '
                    . round($l, 2) . ' ' . round($a, 2) . " re f 0 g\n";
    }
    public function finalizar() {
        if ($this->buf !== '') { $this->paginas[] = $this->buf; $this->buf = ''; }
        $n = count($this->paginas);
        $objs = []; $id = 1;
        $catalogo = $id++; $pagesId = $id++;
        $pagIds = []; $contIds = [];
        for ($i = 0; $i < $n; $i++) { $pagIds[] = $id++; $contIds[] = $id++; }
        $f1 = $id++; $f2 = $id++;
        $objs[$catalogo] = "<< /Type /Catalog /Pages $pagesId 0 R >>";
        $objs[$pagesId] = "<< /Type /Pages /Kids [" . implode(' ', array_map(fn($p) => "$p 0 R", $pagIds))
                        . "] /Count $n >>";
        for ($i = 0; $i < $n; $i++) {
            $objs[$pagIds[$i]] = "<< /Type /Page /Parent $pagesId 0 R /MediaBox [0 0 595.32 841.92] "
                . "/Resources << /Font << /F1 $f1 0 R /F2 $f2 0 R >> >> /Contents {$contIds[$i]} 0 R >>";
            $c = $this->paginas[$i];
            $objs[$contIds[$i]] = "<< /Length " . strlen($c) . " >>\nstream\n" . $c . "endstream";
        }
        $objs[$f1] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objs[$f2] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        ksort($objs);
        $pdf = "%PDF-1.4\n"; $pos = [];
        foreach ($objs as $k => $o) { $pos[$k] = strlen($pdf); $pdf .= "$k 0 obj\n$o\nendobj\n"; }
        $xref = strlen($pdf); $max = max(array_keys($objs));
        $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($k = 1; $k <= $max; $k++) $pdf .= sprintf("%010d 00000 n \n", $pos[$k] ?? 0);
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root $catalogo 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }
}

function pdfp_num($v, $dec = 2) {
    return number_format((float)$v, $dec, ',', '.');
}
function pdfp_brl($v) { return 'R$ ' . pdfp_num($v); }

/**
 * $p = [
 *   'empresa' => ['razao','endereco','numero','bairro','cep','cidade','estado','cnpj','ie','fone','email'],
 *   'numero','idmov','data_entrega','cond_pagto','frete','comprador',
 *   'fornecedor' => ['nome','cod','endereco','numero','bairro','cidade','uf','cep','cnpj','fone','contato','email'],
 *   'itens' => [['cod','nome','descricao','und','qtd','preco','total'], ...],
 *   'rodape' => "texto livre (endereço de entrega, horário, contatos…)",
 *   'faltando' => ['data_entrega','cond_pagto']   // o que não veio da base
 * ]
 */
function pdf_pedido($p) {
    $d = new PdfPedido();
    $ML = 22; $MR = 573;                       // margens
    $emp = (array)($p['empresa'] ?? []);
    $f   = (array)($p['fornecedor'] ?? []);
    $itens = (array)($p['itens'] ?? []);

    // colunas da tabela de itens
    /* Bordas DIREITAS das colunas numéricas. "R$ 1.083,86" ocupa ~40pt a 7,5pt; sem folga o preço
       encostava no total e saía "R$ 886,45R$ 2.659,35". */
    $cItem = $ML + 4; $cDesc = $ML + 96;
    $cDescFim = 346; $cUM = 366; $cQtd = 430; $cPre = 502; $cTot = $MR - 4;
    $larguraDesc = $cDescFim - $cDesc;

    $cabecalho = function ($pag, $total) use ($d, $ML, $MR, $emp, $p, $f, $cItem, $cDesc, $cDescFim, $cUM, $cQtd, $cPre, $cTot) {
        $d->novaPagina();
        $y = 810;
        $d->txtCentro((PdfPedido::L) / 2, $y, (string)($emp['razao'] ?? ''), 9.5, true);
        $y -= 18;
        $d->caixa($ML, $y - 46, $MR - $ML, 50);
        $lin = function ($yy, $pares) use ($d) {
            foreach ($pares as $x => $par) { $d->txt($x, $yy, $par[0], 6.5, true); $d->txt($x + $par[2], $yy, $par[1], 7); }
        };
        $lin($y - 10, [ $ML + 6 => ['ENDEREÇO:', (string)($emp['endereco'] ?? ''), 52],
                        330 => ['N', (string)($emp['numero'] ?? ''), 12],
                        396 => ['BAIRRO:', (string)($emp['bairro'] ?? ''), 38] ]);
        $lin($y - 22, [ $ML + 6 => ['CEP:', (string)($emp['cep'] ?? ''), 26],
                        160 => ['CIDADE:', (string)($emp['cidade'] ?? ''), 38],
                        396 => ['ESTADO:', (string)($emp['estado'] ?? ''), 38] ]);
        $lin($y - 34, [ $ML + 6 => ['CNPJ:', (string)($emp['cnpj'] ?? ''), 30],
                        250 => ['INSCRIÇÃO ESTADUAL:', (string)($emp['ie'] ?? ''), 96] ]);
        $lin($y - 44, [ $ML + 6 => ['FONE/FAX:', (string)($emp['fone'] ?? ''), 48],
                        250 => ['E-MAIL:', (string)($emp['email'] ?? ''), 38] ]);
        $y -= 62;

        // faixa do número do pedido
        $d->fundo($ML, $y - 4, $MR - $ML, 16, 0.92);
        $d->caixa($ML, $y - 4, $MR - $ML, 16);
        $d->txt($ML + 6, $y, '1.1.10', 7);
        $d->txt(190, $y, 'Número de pedido de compra:', 8, true);
        $d->txt(320, $y, str_pad((string)($p['numero'] ?? ''), 9, '0', STR_PAD_LEFT), 9, true);
        if (!empty($p['idmov'])) $d->txtDir($MR - 6, $y, (string)$p['idmov'], 7);
        if ($total > 1) $d->txtDir($MR - 6, $y + 22, 'Folha ' . $pag . '/' . $total, 6.5);
        $y -= 22;

        // bloco do fornecedor
        $d->caixa($ML, $y - 74, $MR - $ML, 78);
        $ff = function ($yy, $pares) use ($d) {
            foreach ($pares as $x => $par) { $d->txt($x, $yy, $par[0], 6.5, true); $d->txt($x + $par[2], $yy, $par[1], 7.5); }
        };
        $d->txt($ML + 6, $y - 10, 'FORNECEDOR:', 6.5, true);
        $d->txt($ML + 66, $y - 10, (string)($f['nome'] ?? ''), 8, true);
        if (!empty($f['cod'])) $d->txtDir($MR - 6, $y - 10, str_pad((string)$f['cod'], 7, '0', STR_PAD_LEFT) . '-', 7);
        $ff($y - 24, [ $ML + 6 => ['ENDEREÇO:', (string)($f['endereco'] ?? ''), 52],
                       300 => ['', (string)($f['numero'] ?? ''), 0],
                       396 => ['BAIRRO:', (string)($f['bairro'] ?? ''), 38] ]);
        $ff($y - 38, [ $ML + 6 => ['CIDADE:', (string)($f['cidade'] ?? ''), 40],
                       300 => ['U.F:', (string)($f['uf'] ?? ''), 22],
                       396 => ['CEP:', (string)($f['cep'] ?? ''), 26] ]);
        $ff($y - 52, [ $ML + 6 => ['CGC:', (string)($f['cnpj'] ?? ''), 30],
                       250 => ['FONE:', (string)($f['fone'] ?? ''), 30],
                       450 => ['FAX:', '', 24] ]);
        $ff($y - 66, [ $ML + 6 => ['CONTATO:', (string)($f['contato'] ?? ''), 46],
                       250 => ['E-MAIL:', (string)($f['email'] ?? ''), 38] ]);
        $y -= 88;

        $d->caixa($ML, $y - 6, $MR - $ML, 18);
        $d->txt($ML + 6, $y, 'DATA ENTREGA:', 6.5, true);
        $de = trim((string)($p['data_entrega'] ?? ''));
        $d->txt($ML + 82, $y, $de !== '' ? $de : '— a informar —', 7.5, $de === '');
        $d->txt(300, $y, 'CONDIÇÃO PAGTO:', 6.5, true);
        $cp = trim((string)($p['cond_pagto'] ?? ''));
        $d->txt(392, $y, $cp !== '' ? $cp : '— a informar —', 7.5, $cp === '');
        $y -= 26;

        // cabeçalho da tabela
        $d->fundo($ML, $y - 4, $MR - $ML, 15, 0.90);
        $d->caixa($ML, $y - 4, $MR - $ML, 15);
        $d->txt($cItem, $y, 'ITEM', 6.5, true);
        $d->txtCentro(($cDesc + $cDescFim) / 2, $y, 'DESCRIÇÃO', 6.5, true);
        $d->txtCentro($cUM, $y, 'U.M', 6.5, true);
        $d->txtDir($cQtd, $y, 'QTD.', 6.5, true);
        $d->txtDir($cPre, $y, 'PREÇO UN.', 6.5, true);
        $d->txtDir($cTot, $y, 'TOTAL', 6.5, true);
        return $y - 16;
    };

    // ---- pagina/quebra: mede antes para saber quantas folhas ----
    $blocos = [];
    foreach ($itens as $it) {
        $linhas = [];
        $nome = trim((string)($it['nome'] ?? ''));
        if ($nome !== '') foreach (pdfp_quebrar($nome, $larguraDesc, 7.5, true) as $l) $linhas[] = [$l, true];
        $desc = trim((string)($it['descricao'] ?? ''));
        if ($desc !== '') foreach (pdfp_quebrar($desc, $larguraDesc, 7) as $l) $linhas[] = [$l, false];
        if (!$linhas) $linhas[] = ['(sem descrição)', false];
        $blocos[] = ['it' => $it, 'linhas' => $linhas, 'altura' => count($linhas) * 9 + 8];
    }
    $rodapeLinhas = pdfp_quebrar((string)($p['rodape'] ?? ''), $MR - $ML - 24, 7);
    $alturaRodape = count($rodapeLinhas) * 9 + 60;

    $LIMITE = 150;                    // abaixo disso, vira folha nova
    $paginasBlocos = [[]]; $h = 0;
    foreach ($blocos as $b) {
        if ($h + $b['altura'] > 420) { $paginasBlocos[] = []; $h = 0; }
        $paginasBlocos[count($paginasBlocos) - 1][] = $b; $h += $b['altura'];
    }
    $totalPag = count($paginasBlocos);

    $totalGeral = 0;
    foreach ($itens as $it) $totalGeral += (float)($it['total'] ?? 0);

    foreach ($paginasBlocos as $i => $lista) {
        $y = $cabecalho($i + 1, $totalPag);
        foreach ($lista as $b) {
            $it = $b['it'];
            $yTopo = $y;
            $d->txt($cItem, $y - 8, (string)($it['cod'] ?? ''), 7);
            $yy = $y - 8;
            foreach ($b['linhas'] as $ln) { $d->txt($cDesc, $yy, $ln[0], 7.5, $ln[1]); $yy -= 9; }
            $d->txtCentro($cUM, $y - 8, (string)($it['und'] ?? ''), 7.5);
            $d->txtDir($cQtd, $y - 8, pdfp_num($it['qtd'] ?? 0), 7.5);
            $d->txtDir($cPre, $y - 8, pdfp_brl($it['preco'] ?? 0), 7.5);
            $d->txtDir($cTot, $y - 8, pdfp_brl($it['total'] ?? 0), 7.5);
            $y = $yTopo - $b['altura'];
            $d->linha($ML, $y + 4, $MR, $y + 4, 0.3, 0.75);
        }

        if ($i === $totalPag - 1) {
            $y -= 8;
            $d->fundo($ML, $y - 6, $MR - $ML, 18, 0.92);
            $d->caixa($ML, $y - 6, $MR - $ML, 18);
            $d->txt($ML + 6, $y, 'VALOR FRETE R$', 7, true);
            $d->txt($ML + 92, $y, pdfp_num($p['frete'] ?? 0, 4), 7.5);
            $d->txtDir($cTot - 74, $y, 'VALOR TOTAL R$', 7.5, true);
            $d->txtDir($cTot, $y, pdfp_num($totalGeral), 8.5, true);
            $y -= 26;
            $d->txt($ML + 6, $y, 'Comprador:', 7, true);
            $d->txt($ML + 66, $y, (string)($p['comprador'] ?? ''), 8);
            $y -= 20;
            $d->txtCentro((PdfPedido::L) / 2, $y, 'Favor informar o número deste pedido em sua nota fiscal', 8, true);
            $y -= 18;
            foreach ($rodapeLinhas as $ln) {
                if ($y < 40) break;
                $d->txt($ML + 12, $y, $ln, 7);
                $y -= 9;
            }
            if (!empty($p['faltando'])) {
                $y -= 6;
                $d->txt($ML + 12, $y, 'Campos ainda não disponíveis nesta base: ' . implode(', ', $p['faltando']), 6.5, true);
            }
        }
    }
    return $d->finalizar();
}
