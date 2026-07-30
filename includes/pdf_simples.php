<?php
/**
 * GERADOR DE PDF MÍNIMO — sem biblioteca nenhuma.
 *
 * Este servidor não tem FPDF, TCPDF nem mbstring, e instalar dependência aqui é caro. Mas para
 * PROVAR o caminho do anexo (compõe -> anexa -> SMTP -> chega na caixa) basta um PDF de uma página
 * com texto. É o que isto faz: escreve o objeto PDF na mão, com a tabela xref correta.
 *
 * NÃO é o gerador do pedido de compra de verdade. O PDF real precisa ser fiel ao que o TOTVS
 * imprime hoje, e essa é uma decisão à parte (gerar aqui × ler o PDF da pasta). Aqui o objetivo é
 * só um: se este anexo chegar bem no Outlook, o encanamento inteiro está de pé.
 */

/** Escapa o que o PDF trata como sintaxe e derruba acento (Helvetica base não tem UTF-8). */
function pdfs_txt($s) {
    $s = strtr((string)$s, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ú'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n','Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','É'=>'E','Ê'=>'E','Í'=>'I','Ó'=>'O',
        'Ô'=>'O','Õ'=>'O','Ú'=>'U','Ç'=>'C','–'=>'-','—'=>'-','“'=>'"','”'=>'"','’'=>"'",'º'=>'o','ª'=>'a']);
    $s = preg_replace('/[^\x20-\x7E]/', '', $s);
    return strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
}

/**
 * $linhas: lista de ['texto', tamanho, negrito(bool)]  — ou string simples.
 * Devolve os bytes do PDF.
 */
function pdf_simples($titulo, $linhas) {
    $y = 780; $fluxo = '';
    $fluxo .= "BT /F2 16 Tf 56 $y Td (" . pdfs_txt($titulo) . ") Tj ET\n";
    $y -= 8;
    $fluxo .= "0.78 0.65 0.15 RG 2 w 56 $y m 539 $y l S\n";   // régua dourada
    $y -= 26;
    foreach ($linhas as $l) {
        if (!is_array($l)) $l = [$l, 11, false];
        $txt = (string)($l[0] ?? ''); $tam = (int)($l[1] ?? 11); $neg = !empty($l[2]);
        if ($txt === '') { $y -= 9; continue; }
        if ($y < 60) break;                                   // uma página basta para o teste
        $fluxo .= 'BT /' . ($neg ? 'F2' : 'F1') . " $tam Tf 56 $y Td (" . pdfs_txt($txt) . ") Tj ET\n";
        $y -= $tam + 6;
    }

    $objs = [];
    $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objs[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objs[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] "
             . "/Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>";
    $objs[4] = "<< /Length " . strlen($fluxo) . " >>\nstream\n" . $fluxo . "endstream";
    $objs[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $objs[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

    $pdf = "%PDF-1.4\n"; $pos = [];
    foreach ($objs as $n => $o) { $pos[$n] = strlen($pdf); $pdf .= "$n 0 obj\n$o\nendobj\n"; }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($objs as $n => $o) $pdf .= sprintf("%010d 00000 n \n", $pos[$n]);
    $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    return $pdf;
}
