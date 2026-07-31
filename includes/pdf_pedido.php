<?php
/**
 * PDF DO PEDIDO DE COMPRA — mesma informação do relatório do TOTVS, apresentação nova.
 *
 * POR QUE GERAR EM VEZ DE LER DA PASTA
 * Hoje o comprador manda "imprimir" no TOTVS, salva numa pasta de rede e anexa à mão. Isso fura a
 * regra 2 de um jeito que nenhuma trava alcança: em 1.303 arquivos medidos, 9 têm no NOME um número
 * que não é o que está DENTRO. Gerado aqui, o anexo nasce do mesmo registro que decidiu a obra e o
 * destinatário — não há como divergir.
 *
 * DECISÕES DE DESENHO (pedido do Murilo: "mais moderno, mas nunca fundo escuro — as pessoas imprimem")
 *  - Fundo SEMPRE branco. Os únicos preenchimentos são cinzas muito claros (0.96–0.98) e uma tarja
 *    de cabeçalho clara. Nada que gaste toner nem que apague no fax da obra.
 *  - Hierarquia por TAMANHO e PESO, não por cor: o número do pedido a 22pt é a primeira coisa que
 *    se lê; rótulos ficam a 6pt em cinza. É o que dá ar moderno sem depender de fundo colorido.
 *  - Cada bloco é um cartão de cantos arredondados. Some a grade de tabela do TOTVS, que é o que
 *    faz o documento parecer de 1998.
 *  - Verde e dourado da Caprem só em filetes e rótulos — impressos em preto e branco viram cinza
 *    claro e o documento continua legível.
 *  - A DATA DE ENTREGA é POR ITEM (o Murilo frisou): ela é coluna da tabela, não campo do cabeçalho.
 *
 * SEM BIBLIOTECA. Este servidor não tem FPDF/TCPDF/mbstring. O PDF é escrito na mão. A tabela de
 * larguras da Helvetica está aqui porque sem ela não há alinhamento à direita nem quebra de linha
 * no lugar certo — e coluna de preço torta é a primeira coisa que o fornecedor nota.
 * Acentos vão em WinAnsiEncoding (Latin-1).
 */

/** Larguras da Helvetica em 1/1000 pt (códigos 32..126) — regular e negrito. */
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

/** UTF-8 -> CP1252 (WinAnsi). Sem mbstring: mapa dos acentos do português. */
function pdfp_cp1252($s) {
    static $map = ['á'=>"\xE1",'à'=>"\xE0",'â'=>"\xE2",'ã'=>"\xE3",'ä'=>"\xE4",'é'=>"\xE9",'ê'=>"\xEA",
        'è'=>"\xE8",'ë'=>"\xEB",'í'=>"\xED",'î'=>"\xEE",'ì'=>"\xEC",'ï'=>"\xEF",'ó'=>"\xF3",
        'ô'=>"\xF4",'õ'=>"\xF5",'ò'=>"\xF2",'ö'=>"\xF6",'ú'=>"\xFA",'û'=>"\xFB",'ù'=>"\xF9",
        'ü'=>"\xFC",'ç'=>"\xE7",'ñ'=>"\xF1",'Á'=>"\xC1",'À'=>"\xC0",'Â'=>"\xC2",'Ã'=>"\xC3",
        'Ä'=>"\xC4",'É'=>"\xC9",'Ê'=>"\xCA",'È'=>"\xC8",'Í'=>"\xCD",'Î'=>"\xCE",'Ó'=>"\xD3",
        'Ô'=>"\xD4",'Õ'=>"\xD5",'Ö'=>"\xD6",'Ú'=>"\xDA",'Û'=>"\xDB",'Ü'=>"\xDC",'Ç'=>"\xC7",
        'Ñ'=>"\xD1",'º'=>"\xBA",'ª'=>"\xAA",'°'=>"\xB0",'–'=>'-','—'=>'-','“'=>'"','”'=>'"',
        '‘'=>"'",'’'=>"'",'…'=>'...','®'=>"\xAE",'©'=>"\xA9",'•'=>"\x95"];
    $s = strtr((string)$s, $map);
    /* Cada acento nosso já é UM byte CP1252 depois do mapa. A limpeza casa SÓ sequência UTF-8 que
       sobrou (líder + continuação); remover "dois bytes altos quaisquer" comeria o "ÇÃ" de INSCRIÇÃO. */
    return preg_replace('/[\xC2-\xF4][\x80-\xBF]+/', '', $s);
}

function pdfp_largura($txt, $tam, $negrito = false) {
    $w = pdfp_larguras($negrito); $s = pdfp_cp1252($txt); $t = 0;
    for ($i = 0, $n = strlen($s); $i < $n; $i++) {
        $c = ord($s[$i]);
        $t += ($c >= 32 && $c <= 126) ? $w[$c - 32] : 556;
    }
    return $t * $tam / 1000;
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

class PdfPedido {
    const L = 595.32, A = 841.92;
    public $paginas = [], $buf = '';

    public function novaPagina() { if ($this->buf !== '') $this->paginas[] = $this->buf; $this->buf = ''; }

    private function cor($c) {           // [r,g,b] em 0..255
        return round($c[0] / 255, 3) . ' ' . round($c[1] / 255, 3) . ' ' . round($c[2] / 255, 3);
    }
    public function txt($x, $y, $s, $tam = 8, $neg = false, $c = [17, 17, 17], $espaco = 0) {
        if (trim((string)$s) === '') return;
        $this->buf .= 'BT ' . $this->cor($c) . " rg /" . ($neg ? 'F2' : 'F1') . ' ' . $tam . ' Tf '
                    . ($espaco ? ($espaco . ' Tc ') : '')
                    . round($x, 2) . ' ' . round($y, 2) . ' Td (' . pdfp_esc($s) . ") Tj ET\n";
        if ($espaco) $this->buf .= "BT 0 Tc ET\n";
    }
    public function txtDir($xd, $y, $s, $tam = 8, $neg = false, $c = [17, 17, 17]) {
        $this->txt($xd - pdfp_largura($s, $tam, $neg), $y, $s, $tam, $neg, $c);
    }
    public function txtCentro($xc, $y, $s, $tam = 8, $neg = false, $c = [17, 17, 17], $esp = 0) {
        $w = pdfp_largura($s, $tam, $neg) + $esp * max(0, strlen($s) - 1);
        $this->txt($xc - $w / 2, $y, $s, $tam, $neg, $c, $esp);
    }
    public function retangulo($x, $y, $l, $a, $preench = null, $borda = null, $esp = 0.6) {
        $ops = '';
        if ($preench) $ops .= $this->cor($preench) . " rg\n";
        if ($borda)   $ops .= $this->cor($borda) . ' RG ' . $esp . " w\n";
        $modo = $preench && $borda ? 'B' : ($preench ? 'f' : 'S');
        $this->buf .= $ops . round($x, 2) . ' ' . round($y, 2) . ' ' . round($l, 2) . ' '
                    . round($a, 2) . " re $modo\n";
    }
    /** Cartão de cantos arredondados — é o que tira a cara de formulário antigo. */
    public function cartao($x, $y, $l, $a, $r = 5, $preench = [255, 255, 255], $borda = [225, 230, 227], $esp = 0.7) {
        $k = 0.5523 * $r;
        $x2 = $x + $l; $y2 = $y + $a;
        $p = round($x + $r, 2) . ' ' . round($y, 2) . " m\n"
           . round($x2 - $r, 2) . ' ' . round($y, 2) . " l\n"
           . round($x2 - $r + $k, 2) . ' ' . round($y, 2) . ' ' . round($x2, 2) . ' ' . round($y + $r - $k, 2) . ' ' . round($x2, 2) . ' ' . round($y + $r, 2) . " c\n"
           . round($x2, 2) . ' ' . round($y2 - $r, 2) . " l\n"
           . round($x2, 2) . ' ' . round($y2 - $r + $k, 2) . ' ' . round($x2 - $r + $k, 2) . ' ' . round($y2, 2) . ' ' . round($x2 - $r, 2) . ' ' . round($y2, 2) . " c\n"
           . round($x + $r, 2) . ' ' . round($y2, 2) . " l\n"
           . round($x + $r - $k, 2) . ' ' . round($y2, 2) . ' ' . round($x, 2) . ' ' . round($y2 - $r + $k, 2) . ' ' . round($x, 2) . ' ' . round($y2 - $r, 2) . " c\n"
           . round($x, 2) . ' ' . round($y + $r, 2) . " l\n"
           . round($x, 2) . ' ' . round($y + $r - $k, 2) . ' ' . round($x + $r - $k, 2) . ' ' . round($y, 2) . ' ' . round($x + $r, 2) . ' ' . round($y, 2) . " c\n";
        $ops = '';
        if ($preench) $ops .= $this->cor($preench) . " rg\n";
        if ($borda)   $ops .= $this->cor($borda) . ' RG ' . $esp . " w\n";
        $modo = $preench && $borda ? 'B' : ($preench ? 'f' : 'S');
        $this->buf .= $ops . $p . "$modo\n";
    }
    public function filete($x1, $y, $x2, $esp = 0.8, $c = [201, 162, 39]) {
        $this->buf .= $this->cor($c) . ' RG ' . $esp . ' w ' . round($x1, 2) . ' ' . round($y, 2)
                    . ' m ' . round($x2, 2) . ' ' . round($y, 2) . " l S\n";
    }

    public function finalizar() {
        if ($this->buf !== '') { $this->paginas[] = $this->buf; $this->buf = ''; }
        $n = count($this->paginas);
        $objs = []; $id = 1;
        $cat = $id++; $pgs = $id++;
        $pagIds = []; $contIds = [];
        for ($i = 0; $i < $n; $i++) { $pagIds[] = $id++; $contIds[] = $id++; }
        $f1 = $id++; $f2 = $id++;
        $objs[$cat] = "<< /Type /Catalog /Pages $pgs 0 R >>";
        $objs[$pgs] = "<< /Type /Pages /Kids [" . implode(' ', array_map(fn($p) => "$p 0 R", $pagIds)) . "] /Count $n >>";
        for ($i = 0; $i < $n; $i++) {
            $objs[$pagIds[$i]] = "<< /Type /Page /Parent $pgs 0 R /MediaBox [0 0 595.32 841.92] "
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
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root $cat 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }
}

function pdfp_num($v, $dec = 2) { return number_format((float)$v, $dec, ',', '.'); }
function pdfp_brl($v) { return 'R$ ' . pdfp_num($v); }
function pdfp_data($s) {
    $s = trim((string)$s); if ($s === '') return '';
    $t = strtotime($s); return $t ? date('d/m/Y', $t) : $s;
}

/**
 * $p = [
 *   'empresa'    => [razao, endereco, numero, bairro, cep, cidade, estado, cnpj, ie, fone, email],
 *   'numero','data','aprovado_em','cond_pagto','frete','comprador','obra',
 *   'fornecedor' => [nome, cod, cnpj, cidade, uf, fone, contato, email, endereco],
 *   'itens'      => [[cod, nome, descricao, und, qtd, preco, total, entrega], ...],
 *   'rodape'     => "texto livre (endereço de entrega, horário, contatos…)",
 *   'aviso'      => "faixa de alerta no topo (usado no TESTE)",
 * ]
 */
function pdf_pedido($p) {
    $VERDE  = [26, 107, 60];
    $ESCURO = [22, 62, 40];
    $OURO   = [201, 162, 39];
    $CINZA  = [110, 122, 115];
    $TINTA  = [24, 28, 26];
    $LINHA  = [223, 230, 226];
    $FUNDO  = [247, 250, 248];
    $ZEBRA  = [251, 252, 251];

    $ML = 34; $MR = PdfPedido::L - 34; $LARG = $MR - $ML;
    $d = new PdfPedido();
    $emp = (array)($p['empresa'] ?? []);
    $f   = (array)($p['fornecedor'] ?? []);
    $itens = (array)($p['itens'] ?? []);
    $temEntrega = false;
    foreach ($itens as $it) if (trim((string)($it['entrega'] ?? '')) !== '') { $temEntrega = true; break; }

    // ---- colunas da tabela ----
    /* Bordas DIREITAS das colunas numéricas, medidas pelo pior caso: "R$ 1.083,86" ocupa ~45pt a
       8,5pt e "R$ 4.335,44" ~48pt a 9pt em negrito. Sem essa folga o preço encosta no total e sai
       "R$ 1.083,8R$ 4.335,44" — foi o que aconteceu nas duas primeiras versões. */
    $cCod = $ML + 10;
    $cDesc = $ML + 92;
    $cDescFim = $temEntrega ? 336 : 380;
    $cEnt = $temEntrega ? 372 : 0;          // centro da coluna ENTREGA
    $cUn  = $temEntrega ? 410 : 400;
    $cQtd = $temEntrega ? 445 : 440;
    $cPre = 496;
    $cTot = $MR - 10;
    $wDesc = $cDescFim - $cDesc;

    $totalGeral = 0;
    foreach ($itens as $it) $totalGeral += (float)($it['total'] ?? 0);
    $frete = (float)($p['frete'] ?? 0);

    // ---- mede os itens ----
    $blocos = [];
    foreach ($itens as $it) {
        $ln = [];
        $nome = trim((string)($it['nome'] ?? ''));
        if ($nome !== '') foreach (pdfp_quebrar($nome, $wDesc, 8.5, true) as $l) $ln[] = [$l, true];
        $desc = trim((string)($it['descricao'] ?? ''));
        if ($desc !== '') foreach (pdfp_quebrar($desc, $wDesc, 7.5) as $l) $ln[] = [$l, false];
        if (!$ln) $ln[] = ['(sem descrição)', false];
        $blocos[] = ['it' => $it, 'linhas' => $ln, 'altura' => count($ln) * 10.5 + 12];
    }

    $rodapeLinhas = pdfp_quebrar((string)($p['rodape'] ?? ''), $LARG - 34, 7.5);

    /* ---------------- cabeçalho de cada página ---------------- */
    /* $compacto: nas folhas 2+ o cabecalho vira uma faixa de uma linha. O documento continua se
       identificando em cada folha impressa (como o do TOTVS faz), mas sem gastar 320pt por pagina —
       era isso que deixava metade da folha 1 em branco. */
    $cabecalho = function ($pag, $total, $compacto = false) use ($d, $ML, $MR, $LARG, $emp, $f, $p, $VERDE, $ESCURO, $OURO,
                                              $CINZA, $TINTA, $LINHA, $FUNDO, $temEntrega,
                                              $cCod, $cDesc, $cDescFim, $cEnt, $cUn, $cQtd, $cPre, $cTot) {
        $d->novaPagina();
        if ($compacto) {
            $d->retangulo(0, PdfPedido::A - 6, PdfPedido::L, 6, $VERDE);
            $y = PdfPedido::A - 30;
            $d->txt($ML, $y, (string)($emp['razao'] ?? ''), 9, true, $ESCURO);
            $d->txt($ML, $y - 11, trim((string)($f['nome'] ?? '')) . (!empty($p['obra']) ? '   ·   obra ' . $p['obra'] : ''), 8, false, $CINZA);
            $d->txtDir($MR, $y, 'PEDIDO Nº ' . str_pad((string)($p['numero'] ?? ''), 6, '0', STR_PAD_LEFT), 12, true, $ESCURO);
            $d->txtDir($MR, $y - 11, 'folha ' . $pag . ' de ' . $total, 7, false, $CINZA);
            $y -= 22;
            $d->filete($ML, $y, $MR, 1.2, $OURO);
            $y -= 14;
            $d->retangulo($ML, $y - 17, $LARG, 17, [240, 245, 242]);
            $d->filete($ML, $y - 17, $MR, 0.7, $LINHA);
            $yy = $y - 12;
            $d->txt($cCod, $yy, 'CÓDIGO', 6.5, true, $ESCURO, 0.6);
            $d->txt($cDesc, $yy, 'DESCRIÇÃO', 6.5, true, $ESCURO, 0.6);
            if ($temEntrega) $d->txtCentro($cEnt, $yy, 'ENTREGA', 6.5, true, $ESCURO, 0.6);
            $d->txtCentro($cUn, $yy, 'UN', 6.5, true, $ESCURO, 0.6);
            $d->txtDir($cQtd, $yy, 'QTD', 6.5, true, $ESCURO, 0.6);
            $d->txtDir($cPre, $yy, 'PREÇO UN.', 6.5, true, $ESCURO, 0.6);
            $d->txtDir($cTot, $yy, 'TOTAL', 6.5, true, $ESCURO, 0.6);
            return $y - 17;
        }
        // tarja superior fina — o único "colorido", e some bem no preto e branco
        $d->retangulo(0, PdfPedido::A - 6, PdfPedido::L, 6, $VERDE);

        $y = PdfPedido::A - 34;
        $d->txt($ML, $y, (string)($emp['razao'] ?? ''), 12.5, true, $ESCURO);
        $y -= 13;
        $end = trim(trim((string)($emp['endereco'] ?? '')) . ' ' . trim((string)($emp['numero'] ?? '')), ' ');
        $l1 = trim($end . ($emp['bairro'] ?? '' ? ' — ' . $emp['bairro'] : ''), ' —');
        $l2 = trim(trim((string)($emp['cidade'] ?? '')) . (!empty($emp['estado']) ? '/' . $emp['estado'] : '')
                 . (!empty($emp['cep']) ? '  CEP ' . $emp['cep'] : ''), ' ');
        $d->txt($ML, $y, trim($l1 . ($l1 && $l2 ? '  ·  ' : '') . $l2), 7.5, false, $CINZA);
        $y -= 10;
        $d->txt($ML, $y, trim('CNPJ ' . ($emp['cnpj'] ?? '') . '   ·   ' . ($emp['fone'] ?? '')
              . '   ·   ' . ($emp['email'] ?? ''), ' ·'), 7.5, false, $CINZA);

        // bloco do número, à direita
        $yn = PdfPedido::A - 32;
        $d->txtDir($MR, $yn, 'PEDIDO DE COMPRA', 7.5, true, $CINZA, 1.1);
        $d->txtDir($MR, $yn - 24, 'Nº ' . str_pad((string)($p['numero'] ?? ''), 6, '0', STR_PAD_LEFT), 22, true, $ESCURO);
        if ($total > 1) $d->txtDir($MR, $yn - 36, 'folha ' . $pag . ' de ' . $total, 7, false, $CINZA);

        $y = PdfPedido::A - 84;
        $d->filete($ML, $y, $MR, 1.4, $OURO);
        $y -= 12;

        // aviso (só no teste)
        if (trim((string)($p['aviso'] ?? '')) !== '') {
            $lin = pdfp_quebrar((string)$p['aviso'], $LARG - 24, 8);
            $alt = count($lin) * 10 + 12;
            $d->cartao($ML, $y - $alt, $LARG, $alt, 4, [253, 246, 236], [222, 184, 110]);
            $yy = $y - 14;
            foreach ($lin as $l) { $d->txt($ML + 12, $yy, $l, 8, true, [150, 90, 10]); $yy -= 10; }
            $y -= $alt + 10;
        }

        /* ---- cartão do fornecedor (o dado que o comprador confere primeiro) ---- */
        $alt = 62;
        $d->cartao($ML, $y - $alt, $LARG, $alt, 5, $FUNDO, $LINHA);
        $d->txt($ML + 14, $y - 15, 'FORNECEDOR', 6.5, true, $OURO, 0.9);
        $d->txt($ML + 14, $y - 30, (string)($f['nome'] ?? ''), 11.5, true, $TINTA);
        $li = [];
        if (!empty($f['cnpj']))    $li[] = 'CNPJ ' . $f['cnpj'];
        if (!empty($f['cod']))     $li[] = 'cód. TOTVS ' . $f['cod'];
        if (!empty($f['cidade']))  $li[] = trim($f['cidade'] . (!empty($f['uf']) ? '/' . $f['uf'] : ''));
        $d->txt($ML + 14, $y - 43, implode('   ·   ', $li), 8, false, $CINZA);
        $li2 = [];
        if (!empty($f['contato'])) $li2[] = 'Contato: ' . $f['contato'];
        if (!empty($f['fone']))    $li2[] = $f['fone'];
        if (!empty($f['email']))   $li2[] = $f['email'];
        $d->txt($ML + 14, $y - 54, implode('   ·   ', $li2), 8, false, $CINZA);
        // obra, no canto direito do cartão
        if (!empty($p['obra'])) {
            $d->txtDir($MR - 14, $y - 15, 'OBRA', 6.5, true, $OURO, 0.9);
            $d->txtDir($MR - 14, $y - 30, (string)$p['obra'], 10.5, true, $ESCURO);
        }
        $y -= $alt + 12;

        /* ---- faixa de chips: emissão / pagamento / aprovado / frete ---- */
        $chips = [];
        $chips[] = ['EMISSÃO', pdfp_data($p['data'] ?? '') ?: '—'];
        $chips[] = ['CONDIÇÃO DE PAGAMENTO', trim((string)($p['cond_pagto'] ?? '')) ?: '—'];
        $chips[] = ['APROVADO EM', pdfp_data($p['aprovado_em'] ?? '') ?: '—'];
        $chips[] = ['FRETE', pdfp_brl($p['frete'] ?? 0)];
        $n = count($chips); $gap = 8; $wc = ($LARG - $gap * ($n - 1)) / $n; $hc = 34;
        $d->cartao($ML, $y - $hc, $LARG, $hc, 5, [255, 255, 255], $LINHA);
        for ($i = 0; $i < $n; $i++) {
            $xc = $ML + $i * ($wc + $gap);
            if ($i > 0) {
                $d->buf .= round($LINHA[0] / 255, 3) . ' ' . round($LINHA[1] / 255, 3) . ' ' . round($LINHA[2] / 255, 3)
                        . ' RG 0.6 w ' . round($xc - $gap / 2, 2) . ' ' . round($y - $hc + 7, 2) . ' m '
                        . round($xc - $gap / 2, 2) . ' ' . round($y - 7, 2) . " l S\n";
            }
            $d->txt($xc + 12, $y - 13, $chips[$i][0], 6, true, $CINZA, 0.7);
            $d->txt($xc + 12, $y - 26, $chips[$i][1], 9.5, true, $TINTA);
        }
        $y -= $hc + 14;

        /* ---- cabeçalho da tabela de itens ---- */
        $d->retangulo($ML, $y - 17, $LARG, 17, [240, 245, 242]);
        $d->filete($ML, $y - 17, $MR, 0.7, $LINHA);
        $yy = $y - 12;
        $d->txt($cCod, $yy, 'CÓDIGO', 6.5, true, $ESCURO, 0.6);
        $d->txt($cDesc, $yy, 'DESCRIÇÃO', 6.5, true, $ESCURO, 0.6);
        if ($temEntrega) $d->txtCentro($cEnt, $yy, 'ENTREGA', 6.5, true, $ESCURO, 0.6);
        $d->txtCentro($cUn, $yy, 'UN', 6.5, true, $ESCURO, 0.6);
        $d->txtDir($cQtd, $yy, 'QTD', 6.5, true, $ESCURO, 0.6);
        $d->txtDir($cPre, $yy, 'PREÇO UN.', 6.5, true, $ESCURO, 0.6);
        $d->txtDir($cTot, $yy, 'TOTAL', 6.5, true, $ESCURO, 0.6);
        return $y - 17;
    };

    /* ---------------- desenho, em um passe só ----------------
       Em vez de "medir tudo e depois desenhar", coloca item por item e vira a folha quando o
       proximo nao couber. E o unico jeito de a ultima folha reservar espaco para os totais sem
       deixar meia pagina em branco nas anteriores. */
    $PISO = 56;                     // margem inferior util
    $altTotais = ($frete > 0 ? 48 : 34) + 16 + 24 + 12;
    $altRodape = $rodapeLinhas ? (count($rodapeLinhas) * 9.5 + 26 + 8) : 0;
    $reserva = $altTotais + $altRodape;

    // quantas folhas? simula rapidinho para poder escrever "folha 1 de N" ja na primeira
    $simY = PdfPedido::A - 300; $folhas = 1;
    foreach ($blocos as $i => $b) {
        if ($simY - $b['altura'] < $PISO) { $folhas++; $simY = PdfPedido::A - 120; }
        $simY -= $b['altura'];
    }
    if ($simY - $reserva < $PISO) $folhas++;

    $pag = 1;
    $y = $cabecalho($pag, $folhas, false);
    $zebra = false;
    foreach ($blocos as $b) {
        if ($y - $b['altura'] < $PISO) { $pag++; $y = $cabecalho($pag, $folhas, true); $zebra = false; }
        $it = $b['it']; $alt = $b['altura'];
        if ($zebra) $d->retangulo($ML, $y - $alt, $LARG, $alt, $ZEBRA);
        $zebra = !$zebra;
        $yy = $y - 13;
        $d->txt($cCod, $yy, (string)($it['cod'] ?? ''), 7.5, false, $CINZA);
        foreach ($b['linhas'] as $l) {
            $d->txt($cDesc, $yy, $l[0], $l[1] ? 8.5 : 7.5, $l[1], $l[1] ? $TINTA : $CINZA);
            $yy -= 10.5;
        }
        $yl = $y - 13;
        if ($temEntrega) $d->txtCentro($cEnt, $yl, pdfp_data($it['entrega'] ?? '') ?: '—', 8, true, $TINTA);
        $d->txtCentro($cUn, $yl, (string)($it['und'] ?? ''), 8, false, $CINZA);
        $d->txtDir($cQtd, $yl, pdfp_num($it['qtd'] ?? 0), 8.5, false, $TINTA);
        $d->txtDir($cPre, $yl, pdfp_brl($it['preco'] ?? 0), 8.5, false, $TINTA);
        $d->txtDir($cTot, $yl, pdfp_brl($it['total'] ?? 0), 9, true, $TINTA);
        $y -= $alt;
        $d->filete($ML, $y, $MR, 0.4, $LINHA);
    }

    if ($y - $reserva < $PISO) { $pag++; $y = $cabecalho($pag, $folhas, true); }

    /* ---- totais ---- */
    $y -= 12;
    $wT = 250; $hT = $frete > 0 ? 48 : 34;
    $d->cartao($MR - $wT, $y - $hT, $wT, $hT, 5, [244, 249, 246], [205, 224, 213]);
    if ($frete > 0) {
        $d->txt($MR - $wT + 14, $y - 16, 'Frete', 8, false, $CINZA);
        $d->txtDir($MR - 14, $y - 16, pdfp_brl($frete), 8.5, false, $TINTA);
        $d->txt($MR - $wT + 14, $y - 34, 'VALOR TOTAL', 8, true, $ESCURO);
        $d->txtDir($MR - 14, $y - 36, pdfp_brl($totalGeral + $frete), 13, true, $ESCURO);
    } else {
        $d->txt($MR - $wT + 14, $y - 22, 'VALOR TOTAL', 8, true, $ESCURO);
        $d->txtDir($MR - 14, $y - 24, pdfp_brl($totalGeral), 14, true, $ESCURO);
    }
    $d->txt($ML + 2, $y - 20, 'Comprador', 6.5, true, $CINZA, 0.7);
    $d->txt($ML + 2, $y - 32, (string)($p['comprador'] ?? ''), 9.5, true, $TINTA);
    $y -= $hT + 16;

    $d->cartao($ML, $y - 24, $LARG, 24, 4, [252, 248, 235], [222, 199, 130]);
    $d->txtCentro(PdfPedido::L / 2, $y - 16, 'Favor informar o número deste pedido em sua nota fiscal', 9.5, true, [120, 88, 12]);
    $y -= 36;

    if ($rodapeLinhas) {
        $alt = count($rodapeLinhas) * 9.5 + 26;
        $d->cartao($ML, $y - $alt, $LARG, $alt, 5, [255, 255, 255], $LINHA);
        $d->txt($ML + 14, $y - 15, 'ENTREGA E CONDIÇÕES', 6.5, true, $OURO, 0.9);
        $yy = $y - 28;
        foreach ($rodapeLinhas as $l) { $d->txt($ML + 14, $yy, $l, 7.5, false, $TINTA); $yy -= 9.5; }
    }
    $d->txtCentro(PdfPedido::L / 2, 26, 'Documento gerado pelo Cockpit de Suprimentos · Caprem Construtora', 6.5, false, $CINZA);

    return $d->finalizar();
}
