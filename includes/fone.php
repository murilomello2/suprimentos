<?php
/**
 * TELEFONE BRASILEIRO -> E.164, que é o formato que a API da Meta exige (5511987654321).
 *
 * A base tem 53 telefones em formato livre — "(19) 3524-1234", "19 99999-8888 / 3524-1234",
 * "0800 707 1234". Copiar isso cru para o campo de WhatsApp criaria disparo fadado a falhar, e
 * pior: falhar em cima de um número de CLIENTE errado, porque celular e fixo se distinguem só
 * pelo formato. Então aqui a gente normaliza E classifica, e quem for fixo não vira WhatsApp.
 *
 * Regras que importam:
 *  - celular brasileiro tem 9 dígitos depois do DDD e começa com 9; fixo tem 8 e começa 2–5.
 *  - número antigo de celular (8 dígitos começando com 8/9) existe muito em cadastro velho:
 *    dá para recuperar inserindo o 9, mas isso é PALPITE — marcamos como 'suspeito', não certo.
 *  - 0300/0500/0800 nunca são WhatsApp.
 *  - o campo costuma ter DOIS números ("celular / fixo"): pegamos todos e escolhemos o melhor.
 */

/** Todos os candidatos numéricos de um texto livre, já só com dígitos. */
function fone_candidatos($raw) {
    $s = (string)$raw;
    if (trim($s) === '') return [];
    // separadores comuns entre dois números no mesmo campo
    $partes = preg_split('/[\/;]|\se\s|\sou\s|,/i', $s);
    $out = [];
    foreach ($partes as $p) {
        $d = preg_replace('/\D+/', '', $p);
        if ($d === '') continue;
        // um campo às vezes tem os dois números colados sem separador (ex.: 1935241234199999888)
        if (strlen($d) > 13) {
            foreach (str_split($d, 11) as $ped) if (strlen($ped) >= 10) $out[] = $ped;
        } else $out[] = $d;
    }
    return $out;
}

/**
 * Classifica UM candidato. -> ['e164','ddd','numero','tipo'=>celular|fixo|especial|invalido','nota']
 * 'nota' explica em português por que caiu no que caiu — quem for arrumar a base depois precisa
 * saber o motivo, não só o veredito.
 */
function fone_classificar($digitos) {
    $d = preg_replace('/\D+/', '', (string)$digitos);
    if ($d === '') return ['tipo' => 'invalido', 'nota' => 'vazio'];

    if (strpos($d, '55') === 0 && strlen($d) >= 12) $d = substr($d, 2);   // já vinha com o país
    $d = ltrim($d, '0');                                                   // 0xx operadora

    if (preg_match('/^(0?[3458]00)/', $digitos) || preg_match('/^(300|500|800|400)/', $d))
        return ['tipo' => 'especial', 'nota' => 'número 0800/0300/4004 — não recebe WhatsApp'];

    if (strlen($d) < 10) return ['tipo' => 'invalido', 'nota' => 'curto demais (' . strlen($d) . ' dígitos) — provavelmente falta o DDD'];
    if (strlen($d) > 11) return ['tipo' => 'invalido', 'nota' => 'longo demais (' . strlen($d) . ' dígitos)'];

    $ddd = substr($d, 0, 2); $num = substr($d, 2);
    if ((int)$ddd < 11 || (int)$ddd > 99) return ['tipo' => 'invalido', 'nota' => 'DDD ' . $ddd . ' não existe'];

    if (strlen($num) === 9) {
        if ($num[0] !== '9') return ['tipo' => 'invalido', 'nota' => '9 dígitos mas não começa com 9'];
        return ['e164' => '55' . $ddd . $num, 'ddd' => $ddd, 'numero' => $num, 'tipo' => 'celular', 'nota' => ''];
    }
    // 8 dígitos: fixo (2–5) ou celular ANTIGO (8/9) de cadastro velho
    if ($num[0] >= '2' && $num[0] <= '5')
        return ['e164' => '55' . $ddd . $num, 'ddd' => $ddd, 'numero' => $num, 'tipo' => 'fixo', 'nota' => 'telefone fixo'];
    return ['e164' => '55' . $ddd . '9' . $num, 'ddd' => $ddd, 'numero' => '9' . $num, 'tipo' => 'celular',
            'nota' => 'celular antigo de 8 dígitos — inseri o 9 na frente, CONFIRA antes de disparar', 'suspeito' => true];
}

/**
 * Melhor candidato a WhatsApp de um texto livre. Prefere celular; se só houver fixo, devolve o
 * fixo MARCADO como fixo — quem chama decide. Devolver "nada" quando existe um fixo esconderia
 * que o cadastro tem contato, e alguém iria procurar de novo à toa.
 */
function fone_melhor_whatsapp($raw) {
    $melhor = null;
    foreach (fone_candidatos($raw) as $c) {
        $r = fone_classificar($c);
        if ($r['tipo'] === 'celular' && empty($r['suspeito'])) return $r;      // certeza: para aqui
        if ($r['tipo'] === 'celular') { $melhor = $melhor ?: $r; continue; }   // suspeito: guarda
        if (!$melhor && in_array($r['tipo'], ['fixo', 'especial'], true)) $melhor = $r;
        if (!$melhor) $melhor = $r;
    }
    return $melhor ?: ['tipo' => 'invalido', 'nota' => 'sem número reconhecível'];
}

/** 5519998887766 -> (19) 99888-7766, para exibir. */
function fone_bonito($e164) {
    $d = preg_replace('/\D+/', '', (string)$e164);
    if (strpos($d, '55') === 0) $d = substr($d, 2);
    if (strlen($d) === 11) return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7);
    if (strlen($d) === 10) return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
    return (string)$e164;
}
