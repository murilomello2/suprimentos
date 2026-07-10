<?php
/**
 * DE-PARA das COLIGADAS do TOTVS (planilha do Murilo, 10/jul/2026): CODCOLIGADA -> nome legal, nome fantasia, CNPJ.
 * Usado p/ o vínculo SC->PC (o número de PC/SC NÃO é único entre coligadas; casa-se por colidmov que embute o código)
 * e p/ exibir o nome da coligada quando pedidos_itens.coligada (nome) vem NULL mas coligada_cod está presente.
 */

function coligadas_map() {
    return [
        1 => ['nome'=>'CAPRETZ EMPREENDIMENTOS IMOBILIARIOS LTDA', 'fantasia'=>'CAPREM', 'cnpj'=>'05.637.287/0001-89'],
        4 => ['nome'=>'BARC EMPREENDIMENTO IMOBILIÁRIO SPE LTDA', 'fantasia'=>'RESIDENCIAL BELLAS ARTES', 'cnpj'=>'21.997.379/0001-26'],
        8 => ['nome'=>'CLUB CIDADE JARDIM EMPREENDIMENTO IMOBILIÁRIO SPE LTDA', 'fantasia'=>'CLUB CIDADE JARDIM', 'cnpj'=>'21.538.900/0001-67'],
        9 => ['nome'=>'ARACAR EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'ARACAR EMPREENDIMENTO - VILLA DO CAMPO', 'cnpj'=>'29.012.356/0001-27'],
        10 => ['nome'=>'BOA VISTA AMERICANA EMPREENDIMENTO IMOB. SPE LTDA.', 'fantasia'=>'BOA VISTA', 'cnpj'=>'20.446.003/0001-60'],
        11 => ['nome'=>'MS INCORPORADORA E EMPREENDIMENTOS IMOBILIÁRIOS LTDA.', 'fantasia'=>'MS INCORPORADORA', 'cnpj'=>'12.517.080/0001-36'],
        12 => ['nome'=>'L.V. RESIDENCE EMPREENDIMENTO IMOBILIÁRIO SPE LTDA', 'fantasia'=>'LEVEN', 'cnpj'=>'23.623.004/0001-21'],
        15 => ['nome'=>'PIAMONTE EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'PIAMONTE', 'cnpj'=>'27.817.437/0001-79'],
        16 => ['nome'=>'BOULEVARD EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'BOULEVARD DE ROSÉ', 'cnpj'=>'20.346.349/0001-97'],
        18 => ['nome'=>'FAENZA EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'FAENZA', 'cnpj'=>'27.817.418/0001-42'],
        19 => ['nome'=>'F.A. POLASTRI EMPREENDIMENTO IMOBILIÁRIO SPE LTDA', 'fantasia'=>'F.A. POLASTRI', 'cnpj'=>'24.584.197/0001-11'],
        21 => ['nome'=>'LICEL EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'LICEL', 'cnpj'=>'12.979.821/0001-09'],
        22 => ['nome'=>'CONDOMINIO LAGUNE RESIDENCE EMPREENDIMENTO IMOBILIARIO SPE', 'fantasia'=>'CONDOMINIO LAGUNE', 'cnpj'=>'43.681.622/0001-35'],
        23 => ['nome'=>'ITAARA EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'ITAARA - VILLA DAS FLORES', 'cnpj'=>'29.251.379/0001-94'],
        24 => ['nome'=>'PEDRA AZUL EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'PEDRA AZUL - DIAMOND', 'cnpj'=>'37.998.738/0001-08'],
        25 => ['nome'=>'CPR5 RIO CLARO EMPREENDIMENTO IMOBILIÁRIO SPE LTDA.', 'fantasia'=>'CPR5 RIO CLARO - CURTUME (AL. BELA VISTA)', 'cnpj'=>'44.555.473/0001-20'],
        26 => ['nome'=>'CPR6 RIO CLARO EMPREENDIMENTO IMOBILIÁRIO SPE LTDA.', 'fantasia'=>'CPR6 RIO CLARO - MALL', 'cnpj'=>'44.463.212/0001-80'],
        27 => ['nome'=>'LEGACY RESIDENCE EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'LEGACY(KOELLE) RESIDENCE', 'cnpj'=>'44.351.665/0001-15'],
        28 => ['nome'=>'CPR2 AMERICANA EMPREENDIMENTO IMOBILIÁRIO SPE LTDA.', 'fantasia'=>'CPR2 AMERICANA - PRAIA DOS NAMORADOS', 'cnpj'=>'44.153.220/0001-20'],
        29 => ['nome'=>'CPR1 AMERICANA EMPREENDIMENTO IMOBILIÁRIO SPE LTDA.', 'fantasia'=>'CPR1 AMERICANA - DISTRAL (SIGNA)', 'cnpj'=>'44.206.107/0001-66'],
        30 => ['nome'=>'CPR3 AMERICANA EMPREENDIMENTO IMOBILIÁRIO SPE LTDA.', 'fantasia'=>'CPR3 AMERICANA - CAL', 'cnpj'=>'44.207.092/0001-50'],
        31 => ['nome'=>'CPR4 SANTA BARBARA DO OESTE EMPREENDIMENTO IMOBILIÁRIO SPE LTDA.', 'fantasia'=>'CPR4 SANTA BARBARA DO OESTE - ADARA - ROMI', 'cnpj'=>'44.207.416/0001-50'],
        32 => ['nome'=>'CPR7 CAMPINAS EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR7 CAMPINAS - MANSOES - SAN PIETRO', 'cnpj'=>'46.794.229/0001-46'],
        33 => ['nome'=>'CPR8 SANTA BARBARA D\'OESTE EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR8 SANTA BARBARA DO OESTE', 'cnpj'=>'47.012.909/0001-23'],
        34 => ['nome'=>'STANZA EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'STANZA - VS7 - VILLA DAS PALMEIRAS', 'cnpj'=>'29.251.390/0001-54'],
        35 => ['nome'=>'CPR9 RIO CLARO EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR9 RIO CLARO - JACARANDAS', 'cnpj'=>'48.803.427/0001-54'],
        36 => ['nome'=>'ESPAZO EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'ESPAZO', 'cnpj'=>'29.000.321/0001-78'],
        37 => ['nome'=>'PRADES EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'PRADES', 'cnpj'=>'29.012.372/0001-10'],
        38 => ['nome'=>'RESIDENCIAL VILAS DO SOBRADO EMPREENDIMENTO IMOB SPE LTDA', 'fantasia'=>'RESIDENCIAL VILAS DO SOBRADO', 'cnpj'=>'21.416.133/0001-13'],
        40 => ['nome'=>'CAJA EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CAJA', 'cnpj'=>'29.056.940/0001-84'],
        41 => ['nome'=>'RESIDENCIAL MANGATA LOTEAMENTO SPE LTDA', 'fantasia'=>'MANGATA  - ENCERRADA', 'cnpj'=>'34.990.328/0001-14'],
        42 => ['nome'=>'INSTITUTO CAPREM', 'fantasia'=>'INSTITUTO CAPREM', 'cnpj'=>'48.236.971/0001-61'],
        43 => ['nome'=>'CPR10 CORDEIROPOLIS EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR10 CORDEIROPOLIS EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'cnpj'=>'50.599.961/0001-32'],
        44 => ['nome'=>'CONSORCIO RESIDENCIAL PARQUE DOS JACARANDAS', 'fantasia'=>'CONSORCIO JACARANDAS', 'cnpj'=>'50.839.474/0001-08'],
        45 => ['nome'=>'CPR15 MOGI MIRIM EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR15 MOGI MIRIM', 'cnpj'=>'52.359.169/0001-36'],
        46 => ['nome'=>'CPR16 RIO CLARO EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR16 RIO CLARO', 'cnpj'=>'52.746.102/0001-54'],
        47 => ['nome'=>'CPR14 RIO CLARO EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR14 RIO CLARO', 'cnpj'=>'52.814.541/0001-57'],
        48 => ['nome'=>'CPR13 SAO JOSE DO RIO PRETO EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR13 SAO JOSE DO RIO PRETO - ENCERRADA', 'cnpj'=>'53.770.872/0001-03'],
        49 => ['nome'=>'CPR11 ARACATUBA EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR11 ARACATUBA', 'cnpj'=>'53.851.971/0001-01'],
        50 => ['nome'=>'MORRO GRANDE EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'MORRO GRANDE', 'cnpj'=>'26.461.805/0001-26'],
        51 => ['nome'=>'CPR12 RIO CLARO EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR12 RIO CLARO', 'cnpj'=>'54.310.088/0001-77'],
        52 => ['nome'=>'CPR17 RIO CLARO EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR17 RIO CLARO', 'cnpj'=>'55.199.358/0001-87'],
        53 => ['nome'=>'CPR18 RIO CLARO EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR18 RIO CLARO', 'cnpj'=>'55.209.193/0001-87'],
        54 => ['nome'=>'CPR21 AMERICANA EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR21 AMERICANA EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'cnpj'=>'59.425.519/0001-64'],
        55 => ['nome'=>'CPR22 AMERICANA EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR22 AMERICANA EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'cnpj'=>'59.428.460/0001-68'],
        56 => ['nome'=>'CPR20 IPEUNA EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR20 IPEUNA EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'cnpj'=>'59.647.967/0001-02'],
        57 => ['nome'=>'ECN84 ENGENHARIA E CONSTRUCAO LTDA', 'fantasia'=>'ECN84 ENGENHARIA E CONSTRUCAO LTDA', 'cnpj'=>'58.817.166/0001-85'],
        58 => ['nome'=>'CPR19 RIO CLARO EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR19 RIO CLARO EMPREENDIMENTO IMOBILIARIO SPE LTDA - ENCERRADA', 'cnpj'=>'60.790.876/0001-00'],
        59 => ['nome'=>'MOOCA I EMPREENDIMENTOS SPE LTDA', 'fantasia'=>'MOOCA I EMPREENDIMENTOS SPE LTDA - BAIXADA', 'cnpj'=>'51.302.684/0001-17'],
        60 => ['nome'=>'ASSOCIACAO DOS MORADORES E PROPRIETARIOS DO VILA DO SOBRADO', 'fantasia'=>'ASSOCIACAO DOS MORADORES E PROPRIETARIOS DO VILA DO SOBRADO', 'cnpj'=>'41.051.353/0001-06'],
        61 => ['nome'=>'GDF EMPREITEIRA LTDA', 'fantasia'=>'GDF EMPREITEIRA LTDA', 'cnpj'=>'61.317.454/0001-85'],
        62 => ['nome'=>'CPR23 ITU EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR23 ITU EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'cnpj'=>'61.777.755/0001-91'],
        63 => ['nome'=>'CPR24 MOGI MIRIM EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR24 MOGI MIRIM EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'cnpj'=>'62.445.532/0001-90'],
        64 => ['nome'=>'CPR25 SAO JOSE DO RIO PRETO EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'fantasia'=>'CPR25 SAO JOSE DO RIO PRETO EMPREENDIMENTO IMOBILIARIO SPE LTDA', 'cnpj'=>'63.737.937/0001-64'],
    ];
}
function coligada_nome($cod) { $m = coligadas_map(); $c = (int)$cod; return isset($m[$c]) ? $m[$c]['nome'] : ''; }
function coligada_fantasia($cod) { $m = coligadas_map(); $c = (int)$cod; return isset($m[$c]) ? $m[$c]['fantasia'] : ''; }
function coligada_cnpj($cod) { $m = coligadas_map(); $c = (int)$cod; return isset($m[$c]) ? $m[$c]['cnpj'] : ''; }
// normaliza p/ casar (sem acento, byte-based — o prod não tem mbstring)
function ob_norm($s) {
    $s = strtolower(trim((string)$s));
    $s = strtr($s, ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','í'=>'i','ï'=>'i','ó'=>'o','õ'=>'o','ô'=>'o','ö'=>'o','ú'=>'u','ü'=>'u','ç'=>'c']);
    $s = preg_replace('/[^a-z0-9 ]/', ' ', $s); return trim(preg_replace('/\s+/', ' ', $s));
}
// casa o NOME de uma obra (ex.: "Diamond") com a coligada do TOTVS pelo nome/fantasia (a fantasia costuma ter o codinome:
// "PEDRA AZUL - DIAMOND", "CPR4 ... ADARA - ROMI", "LEGACY(KOELLE) RESIDENCE"...). -> {cod,nome,fantasia,cnpj,score} | null
function coligada_match_obra($nome) {
    static $STOP = ['empreendimento','empreendimentos','imobiliario','imobiliaria','spe','ltda','residence','residencial','condominio','consorcio','construcao','loteamento','emp','imob','encerrada','de','do','da',
        'rio','claro','americana','santa','barbara','oeste','campinas','mogi','mirim','sao','jose','preto','itu','aracatuba','cordeiropolis','ipeuna','morro','grande','jaguariuna','gertrudes','guacu'];
    $n = ob_norm($nome); if ($n === '') return null;
    $toks = array_values(array_filter(explode(' ', $n), fn($t) => strlen($t) >= 3 && !in_array($t, $STOP, true)));
    $best = null; $bestScore = 0;
    foreach (coligadas_map() as $cod => $d) {
        $hay = ob_norm($d['nome'] . ' ' . $d['fantasia']);
        $score = 0;
        foreach ($toks as $t) if (strpos(' ' . $hay . ' ', ' ' . $t . ' ') !== false) $score++;
        if ($n !== '' && strpos($hay, $n) !== false) $score += 3;   // nome inteiro da obra aparece → forte
        if ($score > $bestScore) { $bestScore = $score; $best = array_merge(['cod' => $cod], $d, ['score' => $score]); }
    }
    return ($bestScore >= 2) ? $best : null;   // >=2 evita falso positivo por 1 token fraco (ex.: "vista")
}
// código da coligada a partir do NOME (legal ou fantasia), normalizado; 0 se não achar
function coligada_cod_de_nome($nome) { $n = strtolower(trim((string)$nome)); if ($n==='') return 0;
    foreach (coligadas_map() as $cod=>$d) { if (strtolower($d['nome'])===$n || strtolower($d['fantasia'])===$n) return $cod; }
    foreach (coligadas_map() as $cod=>$d) { if ($n!=='' && (strpos(strtolower($d['nome']),$n)!==false || strpos($n,strtolower($d['nome']))!==false)) return $cod; }
    return 0; }
