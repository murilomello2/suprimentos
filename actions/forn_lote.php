<?php
/**
 * CADASTRO DE FORNECEDOR EM LOTE — a lista que o comprador achou de uma vez, não um por um.
 *
 * O comprador pesquisa fornecedor NA IA ("laboratório de controle tecnológico no interior de SP")
 * e volta com uma tabela de 5, 10, 20 empresas. Cadastrar isso à mão, campo por campo, é onde a
 * pesquisa morria: dava trabalho, então ninguém cadastrava, e no mês seguinte alguém pesquisava a
 * mesma coisa de novo. Aqui a lista entra inteira, em UM lugar, com conferência humana no meio.
 *
 * DOIS CAMINHOS, o mesmo destino:
 *   1. IA  — cola o PRINT da tabela (ou o texto) + diz em uma linha o que essa gente fornece.
 *            A IA transcreve e CLASSIFICA dentro das nossas categorias e no campo `itens`, que é
 *            o que a busca de fornecedor casa depois.
 *   2. MÁSCARA — planilha/CSV com as colunas fixas (GET ?modelo=1 baixa o modelo, ?prompt=1 dá o
 *            texto pronto para pedir à IA já no formato certo). Nenhuma IA envolvida na leitura.
 *
 * O que NUNCA acontece: gravar sem alguém olhar. `ler` só devolve um rascunho anotado (o que é
 * novo, o que já existe, que telefone é celular, que categoria não existe na base); quem grava é
 * o `gravar`, com as linhas como o comprador deixou na tela.
 *
 * FONTE: nasce 'ia' (é de lá que veio), e é editável linha por linha — se na verdade foi
 * indicação, troca ali e escreve quem indicou. A observação da linha cai em `fonte_detalhe`, que
 * é o campo que a ficha do fornecedor já mostra como "Detalhe da fonte".
 *
 * GET  ?modelo=1&me=          -> CSV modelo (a máscara, com uma linha de exemplo)
 * GET  ?prompt=1&me=          -> {prompt, colunas, categorias, tipos}  (máscara em texto p/ colar na IA)
 * POST {acao:'ler', me, origem:'ia'|'tabela', texto?, imagens?:[dataURL], contexto?}
 *        -> {ok, linhas:[...], modelo, custo, avisos}
 * POST {acao:'gravar', me, linhas:[...], contexto?}
 *        -> {ok, criados, complementados, pulados, resultados:[...]}
 */
header('Content-Type: application/json; charset=utf-8');
set_time_limit(300);
define('FORN_LIB_ONLY', 1);
require_once __DIR__ . '/fornecedores.php';   // $pdo + forn_editor/forn_fontes/forn_add_categoria/forn_sem_acento
require_once __DIR__ . '/../includes/llm.php';
require_once __DIR__ . '/../includes/fone.php';

const FORN_LOTE_MAX = 80;          // teto por envio — lista de pesquisa não tem 300 linhas; se tiver, é import
const FORN_LOTE_IMG_MAX = 4;       // prints por leitura
const FORN_LOTE_IMG_BYTES = 6 * 1024 * 1024;

/** As colunas da máscara, na ORDEM. `lbl` vai no CSV; `alias` é como as pessoas escrevem na vida real. */
function forn_lote_cols() {
    return [
        'nome'         => ['lbl' => 'Nome',        'alias' => ['nome', 'empresa', 'fornecedor', 'nome fantasia', 'fantasia', 'razao social', 'razao'], 'obrig' => 1],
        'cnpj'         => ['lbl' => 'CNPJ',        'alias' => ['cnpj', 'cpf', 'cpf/cnpj', 'cnpj/cpf', 'documento']],
        'contato'      => ['lbl' => 'Contato',     'alias' => ['contato', 'nome do contato', 'nome de contato', 'responsavel', 'representante', 'vendedor', 'falar com']],
        'email'        => ['lbl' => 'E-mail',      'alias' => ['email', 'e-mail', 'e mail', 'mail', 'email de contato', 'e-mail de contato']],
        'telefone'     => ['lbl' => 'Telefone',    'alias' => ['telefone', 'fone', 'tel', 'telefones', 'fixo']],
        'whatsapp'     => ['lbl' => 'WhatsApp',    'alias' => ['whatsapp', 'whats', 'wpp', 'zap', 'celular', 'cel']],
        'cidade'       => ['lbl' => 'Cidade',      'alias' => ['cidade', 'cidade/uf', 'localizacao', 'localização', 'local', 'municipio', 'praca']],
        'categoria'    => ['lbl' => 'Categoria',   'alias' => ['categoria', 'segmento', 'ramo']],
        'tipo'         => ['lbl' => 'Tipo',        'alias' => ['tipo']],
        'itens'        => ['lbl' => 'Itens que fornece', 'alias' => ['itens', 'itens que fornece', 'produtos', 'servicos', 'serviços', 'o que fornece', 'escopo', 'fornece']],
        'observacao'   => ['lbl' => 'Observação',  'alias' => ['observacao', 'observação', 'obs', 'observacoes', 'notas', 'nota', 'detalhe']],
        'fonte'        => ['lbl' => 'Fonte',       'alias' => ['fonte', 'origem']],
        'indicado_por' => ['lbl' => 'Quem indicou','alias' => ['quem indicou', 'indicado por', 'indicacao de']],
    ];
}
function forn_lote_tipos() { return ['Fabricante', 'M.O.', 'Atacadista', 'Varejista', 'Locadora', 'Distribuidor', 'Prestador']; }

/* A HOSPEDAGEM NÃO TEM mbstring (medido em 14/08/2026: mb_strtolower() undefined em produção —
   o cotacoes.php já convivia com isso). Então nada de mb_* aqui.
   - chave: forn_sem_acento() já derruba tudo para ASCII, e aí strtolower() basta;
   - corte: substr() cru partiria um caractere UTF-8 em dois e o JSON sairia inválido — o corte
     por PCRE com /u respeita o caractere. */
function forn_lote_chave($s) { return preg_replace('/\s+/', ' ', trim(strtolower(forn_sem_acento((string)$s)))); }
function forn_lote_corta($s, $n) {
    $s = (string)$s; $n = (int)$n;
    if (strlen($s) <= $n) return $s;                       // bytes ≤ n ⇒ caracteres ≤ n
    if (preg_match('/^.{0,' . $n . '}/us', $s, $m)) return $m[0];
    return rtrim(substr($s, 0, $n), "\x80..\xBF");         // UTF-8 já quebrado na entrada: tira o rabo partido
}

/**
 * A MÁSCARA EM TEXTO — é isto que o comprador cola na IA (ChatGPT, Gemini, no que ele usa) para a
 * resposta já voltar no formato que a tela lê sem tradução. Vive aqui, e não no JavaScript, porque
 * quem manda no vocabulário (categorias, tipos, colunas) é o servidor: mudou a lista de
 * categorias, o prompt entregue muda no mesmo instante.
 */
function forn_lote_prompt_externo($cats) {
    $cols = array_map(fn($c) => $c['lbl'], forn_lote_cols());
    return "Preciso de uma lista de fornecedores para cadastrar no nosso sistema de suprimentos.\n\n"
        . "PESQUISE e me devolva SOMENTE uma tabela, separada por PONTO E VÍRGULA (;), com esta primeira "
        . "linha de cabeçalho e uma linha por fornecedor:\n\n"
        . implode(';', $cols) . "\n\n"
        . "REGRAS:\n"
        . "- não invente nada: campo que você não achou vai VAZIO (e não 'N/A', 'não informado' ou '-');\n"
        . "- E-mail é o campo mais importante: procure o e-mail comercial/de contato de cada um;\n"
        . "- Telefone e WhatsApp com DDD; se for o mesmo número, repita nos dois;\n"
        . "- Cidade no formato Cidade/UF;\n"
        . "- Categoria: escolha UMA desta lista fechada (se nenhuma servir, deixe vazio):\n  "
        . implode(' | ', $cats) . "\n"
        . "- Tipo: um destes — " . implode(', ', forn_lote_tipos()) . ";\n"
        . "- 'Itens que fornece': o que a empresa vende ou executa, em palavras-chave separadas por vírgula "
        . "(é por aqui que vamos procurar o fornecedor depois);\n"
        . "- Observação: certificações, acreditações, restrições, o que mais for relevante;\n"
        . "- Fonte: deixe 'IA'.\n\n"
        . "MINHA BUSCA: (descreva aqui o que você precisa — ex.: laboratórios de controle tecnológico "
        . "de concreto no interior de SP, com extração de testemunho e acreditação ISO 17025)";
}

/** Instrução da leitura pela NOSSA IA (print/texto colado). Devolve JSON — a tela nunca lê texto solto. */
function forn_lote_prompt_interno($cats, $contexto) {
    $t = "Você é o cadastro de fornecedores de uma construtora. Recebe uma LISTA DE FORNECEDORES "
        . "(print de tabela, texto colado, e-mail ou resposta de outra IA) e transcreve para cadastro.\n\n"
        . "Para CADA fornecedor da lista devolva: nome, razao_social, cnpj, contato, email, telefone, "
        . "whatsapp, cidade, categoria, tipo, itens, observacao.\n\n"
        . "REGRAS QUE NÃO SE NEGOCIAM:\n"
        . "1. NÃO INVENTE. Campo que não está na fonte vai como \"\" (string vazia). Nunca escreva "
        . "'não informado', 'N/A', 'consultar' num campo de dado — isso é observação.\n"
        . "2. Não crie fornecedor que não está na lista, e não repita o mesmo fornecedor.\n"
        . "3. Ignore linha de cabeçalho, numeração de ordem, totais e comentários.\n"
        . "4. cnpj só se aparecer escrito. Telefone/WhatsApp com DDD, como estiverem escritos.\n"
        . "5. cidade no formato Cidade/UF quando der.\n"
        . "6. Se no lugar do e-mail estiver escrito algo como 'solicitar pelo site' ou 'formulário', "
        . "email fica \"\" e isso vai para observacao.\n"
        . "7. categoria: escolha UMA da LISTA FECHADA abaixo. Se nenhuma servir de verdade, devolva \"\" — "
        . "chutar categoria errada é pior que deixar vazio.\n"
        . "8. tipo: um de " . implode(', ', forn_lote_tipos()) . ". Laboratório, projetista, transportadora e "
        . "serviço em geral são 'Prestador'; quem só aluga equipamento é 'Locadora'; mão de obra empreitada é 'M.O.'.\n"
        . "9. itens: o que a empresa VENDE OU EXECUTA, em palavras-chave separadas por vírgula, específicas "
        . "e no vocabulário de obra. É o campo que a busca do sistema casa — se estiver pobre, ninguém "
        . "encontra o fornecedor depois. Use o que está na lista E o contexto que o comprador deu.\n"
        . "10. observacao: tudo que é relevante e não cabe em campo — certificação, acreditação e número "
        . "dela, escopo, restrição, 'confirmar X', data de validade de certificado.\n\n"
        . "CATEGORIAS DISPONÍVEIS (lista fechada):\n" . implode(' | ', $cats) . "\n\n";
    if (trim((string)$contexto) !== '')
        $t .= "CONTEXTO DO COMPRADOR — o que essa gente fornece / por que ele está pesquisando "
            . "(vale para TODOS os fornecedores da lista, use na categoria e nos itens):\n"
            . trim((string)$contexto) . "\n\n";
    return $t . "FORMATO DA RESPOSTA (JSON, e nada além dele):\n"
        . '{"fornecedores":[{"nome":"","razao_social":"","cnpj":"","contato":"","email":"","telefone":"",'
        . '"whatsapp":"","cidade":"","categoria":"","tipo":"","itens":"","observacao":""}],"aviso":""}';
}

/** JSON da IA pode vir cercado de ```json … ``` ou de uma frase. Pega o primeiro objeto e pronto. */
function forn_lote_json($txt) {
    $t = trim((string)$txt);
    if ($t === '') return null;
    $d = json_decode($t, true);
    if (is_array($d)) return $d;
    if (preg_match('/\{.*\}/s', $t, $m)) { $d = json_decode($m[0], true); if (is_array($d)) return $d; }
    return null;
}

/**
 * TABELA COLADA -> linhas. Aceita o que sai do Excel (TAB), o que sai de CSV pt-BR (;), tabela de
 * markdown (|) e o cabeçalho escrito de qualquer jeito (mapeia por ALIAS, sem acento). Sem
 * cabeçalho reconhecível, cai na ordem da máscara — que é o que o modelo baixado tem.
 */
function forn_lote_parse_tabela($texto) {
    $cols = forn_lote_cols(); $chaves = array_keys($cols);
    $linhas = preg_split('/\r\n|\r|\n/', (string)$texto);
    $linhas = array_values(array_filter(array_map('trim', $linhas), fn($l) => $l !== ''));
    if (!$linhas) return [[], []];
    // separador: o que mais aparece na primeira linha (TAB ganha de ; porque Excel cola com TAB)
    $sep = "\t";
    $c = ['\t' => substr_count($linhas[0], "\t"), ';' => substr_count($linhas[0], ';'), '|' => substr_count($linhas[0], '|')];
    if ($c[';'] > $c['\t'] && $c[';'] >= $c['|']) $sep = ';';
    elseif ($c['|'] > $c['\t'] && $c['|'] > $c[';']) $sep = '|';
    $corta = function ($l) use ($sep) {
        if ($sep === '|') $l = trim($l, "| \t");
        $p = ($sep === ';') ? str_getcsv($l, ';') : explode($sep, $l);
        return array_map(fn($x) => trim(trim((string)$x), " \t\"'"), $p);
    };
    // cabeçalho?
    $mapa = null; $ini = 0;
    $prim = $corta($linhas[0]);
    $alias = [];
    foreach ($cols as $k => $def) foreach ($def['alias'] as $a) $alias[forn_lote_chave($a)] = $k;
    $achou = 0; $cand = [];
    foreach ($prim as $i => $h) { $k = $alias[forn_lote_chave($h)] ?? null; $cand[$i] = $k; if ($k) $achou++; }
    if ($achou >= 2) { $mapa = $cand; $ini = 1; }
    $out = []; $avisos = [];
    for ($i = $ini; $i < count($linhas); $i++) {
        $p = $corta($linhas[$i]);
        if (!$p || implode('', $p) === '') continue;
        if ($sep === '|' && preg_match('/^[\s:|-]+$/', $linhas[$i])) continue;   // régua da tabela markdown
        $r = []; $sobra = [];
        foreach ($p as $j => $v) {
            $k = $mapa ? ($mapa[$j] ?? null) : ($chaves[$j] ?? null);
            /* COLUNA QUE NÃO É NOSSA não se perde: vira observação com o nome do cabeçalho na frente.
               O print que o comprador cola tem colunas próprias da pesquisa dele ("Extração de
               testemunho: Sim", "Inmetro/ISO 17025: CRL 0098") — é exatamente o que ele quer ver
               depois na ficha, e jogar fora calado seria a pior das opções. */
            if (!$k && $mapa && $v !== '') {
                $h = forn_lote_chave($prim[$j] ?? '');
                if ($h !== '' && !preg_match('/^(ordem|item|id|n|no|num|numero|#|\d+)$/', $h)) $sobra[] = ($prim[$j] . ': ' . $v);
                continue;
            }
            if (!$k || $v === '') continue;
            $r[$k] = isset($r[$k]) ? ($r[$k] . ' ' . $v) : $v;
        }
        if ($sobra) $r['observacao'] = trim(($r['observacao'] ?? '') . ' · ' . implode(' · ', $sobra), ' ·');
        // linha sem cabeçalho pode começar com a coluna "Ordem" (1, 2, 3...) do print — nome não é número
        if (!$mapa && isset($r['nome']) && preg_match('/^\d{1,3}$/', $r['nome'])) {
            array_shift($p);
            $r = []; foreach ($p as $j => $v) { $k = $chaves[$j] ?? null; if ($k && $v !== '') $r[$k] = $v; }
        }
        if (trim((string)($r['nome'] ?? '')) === '') { $avisos[] = 'linha ' . ($i + 1) . ' ignorada (sem nome)'; continue; }
        $out[] = $r;
        if (count($out) >= FORN_LOTE_MAX) { $avisos[] = 'a lista foi cortada em ' . FORN_LOTE_MAX . ' linhas'; break; }
    }
    return [$out, $avisos];
}

/**
 * NORMALIZA E ANOTA uma linha do rascunho. Não grava nada — só deixa a tela capaz de mostrar a
 * verdade antes do clique: se o fornecedor já existe (por CNPJ ou por nome), que campos o cadastro
 * antigo ganharia, se o telefone é celular de verdade, se a categoria existe na nossa base.
 */
function forn_lote_norm($pdo, $l, $catMapa, $fontesOK) {
    $g = fn($k) => preg_replace('/\s+/', ' ', trim((string)($l[$k] ?? '')));
    $r = [];
    foreach (array_keys(forn_lote_cols()) as $k) $r[$k] = $g($k);
    $r['razao_social'] = $g('razao_social');
    $r['avisos'] = [];

    // lixo que a IA às vezes escreve num campo de dado em vez de deixar vazio
    foreach (['cnpj', 'contato', 'email', 'telefone', 'whatsapp', 'cidade'] as $k)
        if (preg_match('/^(n\/?a|nao informado|não informado|nao consta|não consta|-{1,3}|\?+|null|vazio)$/i', $r[$k])) $r[$k] = '';

    // CNPJ: guarda formatado (é como a base inteira está), mas compara por dígito
    $dig = preg_replace('/\D/', '', $r['cnpj']);
    if ($dig !== '' && strlen($dig) !== 14 && strlen($dig) !== 11) { $r['avisos'][] = 'CNPJ com ' . strlen($dig) . ' dígitos — confira'; }
    if (strlen($dig) === 14) $r['cnpj'] = preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $dig);
    elseif (strlen($dig) === 11) $r['cnpj'] = preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $dig);
    $cnpjOK = strlen($dig) >= 11 && !preg_match('/^(\d)\1+$/', $dig);

    // e-mail: minúsculo e validado. É o campo que faz o fornecedor servir para cotação.
    $r['email'] = strtolower($r['email']);
    if ($r['email'] !== '' && strpos($r['email'], ',') !== false) $r['email'] = trim(explode(',', $r['email'])[0]);
    if ($r['email'] !== '' && !filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
        /* "Solicitar pelo site", "formulário no site", "consultar" — a coluna de e-mail do print às
           vezes traz uma INSTRUÇÃO, não um endereço. Isso é observação; deixar no campo de e-mail
           faria a fila de Envio tentar mandar cotação para um texto. */
        if (strpos($r['email'], '@') === false) {
            $r['observacao'] = trim($r['observacao'] . ' · e-mail: ' . $r['email'], ' ·');
            $r['email'] = '';
        } else { $r['avisos'][] = 'e-mail inválido'; $r['email_ruim'] = 1; }
    }
    if ($r['email'] === '') $r['avisos'][] = 'sem e-mail — dá para cadastrar, mas não dá para cotar';

    /* WhatsApp: mesma regra do normalizar_whatsapp — só CELULAR vira número de disparo. Se a linha
       veio sem WhatsApp e o telefone é celular, propõe (marcado como proposta, não como fato). */
    $r['wa_e164'] = ''; $r['wa_tipo'] = ''; $r['wa_do_telefone'] = 0;
    $fonteWa = $r['whatsapp'] !== '' ? $r['whatsapp'] : $r['telefone'];
    if ($fonteWa !== '') {
        $w = fone_melhor_whatsapp($fonteWa);
        $r['wa_tipo'] = $w['tipo'] ?? '';
        if (($w['tipo'] ?? '') === 'celular' && !empty($w['e164'])) {
            $r['wa_e164'] = $w['e164'];
            $bonito = fone_bonito($w['e164']);
            if ($r['whatsapp'] === '') { $r['whatsapp'] = $bonito; $r['wa_do_telefone'] = 1; }
            else $r['whatsapp'] = $bonito;
            if (!empty($w['suspeito'])) $r['avisos'][] = 'WhatsApp: ' . ($w['nota'] ?? 'número antigo, confira');
        } elseif ($r['whatsapp'] !== '') {
            $r['avisos'][] = 'o número no campo WhatsApp não é celular (' . ($w['tipo'] ?? '?') . ')';
        }
    }
    if ($r['telefone'] === '' && $r['whatsapp'] !== '') $r['telefone'] = $r['whatsapp'];

    // categoria: casa com a nossa lista sem depender de acento/caixa; o que não casa fica visível
    $r['categoria_nova'] = 0;
    if ($r['categoria'] !== '') {
        $k = forn_lote_chave($r['categoria']);
        if (isset($catMapa[$k])) $r['categoria'] = $catMapa[$k];
        else $r['categoria_nova'] = 1;
    }
    if (!in_array($r['tipo'], forn_lote_tipos(), true)) {
        $achou = '';
        foreach (forn_lote_tipos() as $t) if (forn_lote_chave($t) === forn_lote_chave($r['tipo'])) $achou = $t;
        $r['tipo'] = $achou;
    }

    // FONTE: nasce 'ia' (é de onde a lista veio) e a tela deixa trocar linha por linha
    $f = forn_lote_chave($r['fonte']);
    $r['fonte'] = in_array($r['fonte'], $fontesOK, true) ? $r['fonte']
        : (($f === 'ia' || $f === 'pesquisa por ia' || $f === '') ? 'ia'
        : (in_array($f, ['indicacao', 'indicação'], true) ? 'indicacao' : 'ia'));
    if ($r['fonte'] !== 'indicacao') $r['indicado_por'] = '';

    /* JÁ EXISTE? mesma ordem do fornecedor_salvar: CNPJ real primeiro, nome depois. Aqui a resposta
       é informativa — a tela mostra o cadastro antigo e QUAIS campos ele ganharia, porque
       complementar cadastro velho é metade do valor deste lote. */
    $r['existe'] = null;
    $dupe = null;
    if ($cnpjOK) {
        $q = $pdo->prepare("SELECT * FROM cot_fornecedor WHERE REPLACE(REPLACE(REPLACE(REPLACE(cnpj,'.',''),'/',''),'-',''),' ','')=? AND (ativo=1 OR ativo IS NULL) ORDER BY id LIMIT 1");
        $q->execute([$dig]); $dupe = $q->fetch() ?: null;
        if ($dupe) $r['existe_por'] = 'cnpj';
    }
    if (!$dupe && $r['nome'] !== '') {
        $q = $pdo->prepare("SELECT * FROM cot_fornecedor WHERE LOWER(TRIM(nome))=LOWER(TRIM(?)) AND (ativo=1 OR ativo IS NULL) ORDER BY id LIMIT 1");
        $q->execute([$r['nome']]); $dupe = $q->fetch() ?: null;
        if ($dupe) $r['existe_por'] = 'nome';
    }
    if ($dupe) {
        $ganha = [];
        foreach (['cnpj', 'email', 'telefone', 'whatsapp', 'contato', 'cidade', 'categoria', 'tipo', 'itens', 'razao_social'] as $c)
            if ($r[$c] !== '' && trim((string)($dupe[$c] ?? '')) === '') $ganha[] = $c;
        $r['existe'] = ['id' => (int)$dupe['id'], 'nome' => $dupe['nome'], 'email' => $dupe['email'],
                        'telefone' => $dupe['telefone'], 'categoria' => $dupe['categoria'], 'cidade' => $dupe['cidade'],
                        'itens' => (string)$dupe['itens'], 'ganha' => $ganha];
        $r['acao'] = $ganha ? 'complementar' : 'pular';   // nada a acrescentar = não mexe
    } else {
        $r['acao'] = 'criar';
    }
    return $r;
}

try {
    // $pdo veio do fornecedores.php (FORN_LIB_ONLY), já com as colunas de fonte garantidas
    $me = $_GET['me'] ?? null;
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') { $in = json_decode(file_get_contents('php://input'), true) ?: []; $me = $in['me'] ?? null; }
    $perms = forn_editor($pdo, $me);
    if (!$perms) { http_response_code(403); echo json_encode(['error' => 'Sem permissão para cadastrar fornecedor.'], JSON_UNESCAPED_UNICODE); exit; }

    $cats = array_column($pdo->query("SELECT nome FROM cot_categoria ORDER BY nome")->fetchAll(), 'nome');
    $catMapa = []; foreach ($cats as $c) $catMapa[forn_lote_chave($c)] = $c;
    $fontesOK = array_column(forn_fontes(), 'v');

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // MODELO (a máscara) — CSV com ; e BOM, que é o que o Excel pt-BR abre certo
        if (isset($_GET['modelo'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="modelo-fornecedores-em-lote.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, array_map(fn($c) => $c['lbl'], forn_lote_cols()), ';');
            fputcsv($out, ['CETECLins / FPTE', '', 'Atendimento', 'atendimento@exemplo.com.br', '(14) 3532-0000',
                '(14) 99999-0000', 'Lins/SP', 'Controle Técnologica Laboratórios', 'Prestador',
                'controle tecnologico de concreto, rompimento de corpo de prova, extracao de testemunho',
                'acreditado ISO 17025 - CRL 0098', 'IA', ''], ';');
            fclose($out); exit;
        }
        // A MÁSCARA EM TEXTO, para colar na IA que o comprador usa
        echo json_encode(['ok' => true, 'prompt' => forn_lote_prompt_externo($cats),
            'colunas' => array_values(array_map(fn($c) => $c['lbl'], forn_lote_cols())),
            'categorias' => $cats, 'tipos' => forn_lote_tipos(), 'fontes' => forn_fontes(),
            'max' => FORN_LOTE_MAX], JSON_UNESCAPED_UNICODE); exit;
    }

    $acao = $in['acao'] ?? '';

    /* ---------- LER: virar rascunho conferível (pela IA ou pela máscara) ---------- */
    if ($acao === 'ler') {
        $origem = ($in['origem'] ?? 'ia') === 'tabela' ? 'tabela' : 'ia';
        $texto = trim((string)($in['texto'] ?? ''));
        $contexto = trim((string)($in['contexto'] ?? ''));
        $avisos = []; $modelo = ''; $custo = 0; $brutas = [];

        if ($origem === 'tabela') {
            if ($texto === '') throw new Exception('cole a tabela (ou use a leitura por IA)');
            [$brutas, $avisos] = forn_lote_parse_tabela($texto);
            if (!$brutas) throw new Exception('não achei nenhuma linha com nome de fornecedor nessa tabela');
        } else {
            // imagens: data URL de PNG/JPG, validadas pelo cabeçalho real dos bytes (não pelo que o nome diz)
            $partes = []; $imgs = 0; $bytesTot = 0;
            foreach ((array)($in['imagens'] ?? []) as $du) {
                if ($imgs >= FORN_LOTE_IMG_MAX) { $avisos[] = 'só as ' . FORN_LOTE_IMG_MAX . ' primeiras imagens foram lidas'; break; }
                if (!preg_match('#^data:image/(png|jpe?g);base64,#i', (string)$du, $m)) { $avisos[] = 'uma imagem foi ignorada (só PNG ou JPG)'; continue; }
                $b64 = substr((string)$du, strpos((string)$du, ',') + 1);
                $bin = base64_decode($b64, true);
                if ($bin === false || $bin === '') { $avisos[] = 'uma imagem veio corrompida'; continue; }
                $bytesTot += strlen($bin);
                if ($bytesTot > FORN_LOTE_IMG_BYTES) { $avisos[] = 'imagens acima de 6 MB no total foram descartadas'; break; }
                $head = substr($bin, 0, 8);
                $mime = (strncmp($head, "\x89PNG\x0d\x0a\x1a\x0a", 8) === 0) ? 'image/png'
                      : ((strncmp($head, "\xFF\xD8\xFF", 3) === 0) ? 'image/jpeg' : '');
                if ($mime === '') { $avisos[] = 'uma imagem não é PNG/JPG de verdade'; continue; }
                $partes[] = ['t' => 'imagem', 'mime' => $mime, 'b64' => base64_encode($bin)];
                $imgs++;
            }
            if ($texto !== '') $partes[] = ['t' => 'texto', 'texto' => "LISTA EM TEXTO:\n" . forn_lote_corta($texto, 20000)];
            if (!$partes) throw new Exception('cole o print da lista (Ctrl+V) ou o texto dela');
            array_unshift($partes, ['t' => 'texto', 'texto' => forn_lote_prompt_interno($cats, $contexto)]);

            $r = llm_chat('extracao', [['role' => 'user', 'content' => $partes]],
                          ['json' => true, 'temperatura' => 0.1, 'max_tokens' => 4000]);
            llm_registrar($pdo, $r, 'forn_lote', (string)$me);
            if (empty($r['ok'])) throw new Exception('IA: ' . ($r['erro'] ?: 'falhou sem dizer por quê'));
            $d = forn_lote_json($r['texto']);
            if (!is_array($d)) throw new Exception('a IA não devolveu JSON — tente de novo ou use a máscara de planilha');
            $brutas = (array)($d['fornecedores'] ?? $d['linhas'] ?? []);
            if (!$brutas) throw new Exception('a IA não achou nenhum fornecedor nessa imagem/texto');
            if (trim((string)($d['aviso'] ?? '')) !== '') $avisos[] = trim((string)$d['aviso']);
            if (count($brutas) > FORN_LOTE_MAX) { $brutas = array_slice($brutas, 0, FORN_LOTE_MAX); $avisos[] = 'a lista foi cortada em ' . FORN_LOTE_MAX . ' fornecedores'; }
            $modelo = ($r['provedor'] ?? '') . '/' . ($r['modelo'] ?? ''); $custo = $r['custo'] ?? 0;
        }

        $linhas = []; $vistos = [];
        foreach ($brutas as $b) {
            if (!is_array($b)) continue;
            $n = forn_lote_norm($pdo, $b, $catMapa, $fontesOK);
            if ($n['nome'] === '') continue;
            $k = forn_lote_chave($n['nome']);
            if (isset($vistos[$k])) { $avisos[] = '"' . $n['nome'] . '" apareceu duas vezes na lista — deixei uma'; continue; }
            $vistos[$k] = 1;
            // itens vazio é fornecedor que a busca nunca acha: na falta de algo melhor, vale o
            // contexto que o comprador escreveu (é o que ele foi pesquisar, afinal)
            if ($contexto !== '' && $n['itens'] === '') $n['itens'] = forn_lote_corta($contexto, 240);
            $linhas[] = $n;
        }
        if (!$linhas) throw new Exception('nenhuma linha aproveitável — confira se o print mostra a tabela inteira');
        echo json_encode(['ok' => true, 'linhas' => $linhas, 'modelo' => $modelo, 'custo' => $custo,
            'avisos' => $avisos, 'categorias' => $cats, 'tipos' => forn_lote_tipos(),
            'fontes' => forn_fontes()], JSON_UNESCAPED_UNICODE); exit;
    }

    /* ---------- GRAVAR: o rascunho como o comprador deixou na tela ---------- */
    if ($acao === 'gravar') {
        $linhas = (array)($in['linhas'] ?? []);
        if (!$linhas) throw new Exception('nada para gravar');
        if (count($linhas) > FORN_LOTE_MAX) throw new Exception('máximo de ' . FORN_LOTE_MAX . ' fornecedores por vez');
        $hoje = date('Y-m-d'); $agora = date('c');
        $cols = ['nome', 'razao_social', 'cnpj', 'cidade', 'contato', 'telefone', 'whatsapp', 'email', 'itens',
                 'tipo', 'categoria', 'fonte', 'fonte_data', 'fonte_detalhe', 'fonte_indicado_por',
                 'wa_e164', 'wa_tipo', 'wa_origem'];
        $ins = $pdo->prepare("INSERT INTO cot_fornecedor (" . implode(',', $cols) . ",ativo,created_at) VALUES ("
                             . implode(',', array_fill(0, count($cols), '?')) . ",1,?)");
        $res = ['ok' => true, 'criados' => 0, 'complementados' => 0, 'pulados' => 0, 'resultados' => []];
        $novasCats = [];
        $pdo->beginTransaction();
        foreach ($linhas as $l) {
            $n = forn_lote_norm($pdo, $l, $catMapa, $fontesOK);
            $quer = (string)($l['acao'] ?? $n['acao']);
            if ($n['nome'] === '' || $quer === 'pular') {
                $res['pulados']++;
                $res['resultados'][] = ['nome' => $n['nome'] ?: '(sem nome)', 'acao' => 'pulado'];
                continue;
            }
            $det = trim((string)($l['observacao'] ?? $n['observacao']));
            $vals = ['nome' => $n['nome'], 'razao_social' => $n['razao_social'], 'cnpj' => $n['cnpj'],
                     'cidade' => $n['cidade'], 'contato' => $n['contato'], 'telefone' => $n['telefone'],
                     'whatsapp' => $n['whatsapp'], 'email' => $n['email'], 'itens' => $n['itens'],
                     'tipo' => $n['tipo'], 'categoria' => $n['categoria'], 'fonte' => $n['fonte'],
                     'fonte_data' => $hoje, 'fonte_detalhe' => forn_lote_corta($det, 250),
                     'fonte_indicado_por' => ($n['fonte'] === 'indicacao' ? forn_lote_corta($n['indicado_por'], 150) : ''),
                     'wa_e164' => $n['wa_e164'], 'wa_tipo' => $n['wa_tipo'],
                     'wa_origem' => $n['wa_e164'] !== '' ? ($n['wa_do_telefone'] ? 'telefone' : 'whatsapp') : ''];
            if ($vals['categoria'] !== '' && !isset($catMapa[forn_lote_chave($vals['categoria'])])) $novasCats[$vals['categoria']] = 1;

            /* JÁ EXISTE — nunca cria o segundo cadastro do mesmo fornecedor, aconteça o que
               acontecer na tela (o servidor reconfere: o rascunho pode ter envelhecido enquanto o
               comprador editava, e alguém pode ter cadastrado esse fornecedor no meio). E só
               escreve em campo VAZIO: a regra de ouro do enriquecer_totvs vale aqui também —
               dado antigo curado por gente não é sobrescrito por lista de pesquisa. */
            if ($n['existe']) {
                $id = (int)$n['existe']['id'];
                $sets = []; $args = []; $feitos = [];
                foreach (['cnpj', 'razao_social', 'cidade', 'contato', 'telefone', 'whatsapp', 'email',
                          'itens', 'tipo', 'categoria'] as $c) {
                    if ($vals[$c] === '' || !in_array($c, $n['existe']['ganha'], true)) continue;
                    $sets[] = "$c=?"; $args[] = $vals[$c]; $feitos[] = $c;
                }
                // os campos técnicos do WhatsApp acompanham o número: só entram se o número entrou agora
                if (in_array('whatsapp', $feitos, true) && $vals['wa_e164'] !== '')
                    foreach (['wa_e164', 'wa_tipo', 'wa_origem'] as $c) { $sets[] = "$c=?"; $args[] = $vals[$c]; }
                if (!$sets) { $res['pulados']++; $res['resultados'][] = ['nome' => $n['nome'], 'id' => $id, 'acao' => 'nada a acrescentar']; continue; }
                $args[] = $id;
                $pdo->prepare("UPDATE cot_fornecedor SET " . implode(',', $sets) . " WHERE id=?")->execute($args);
                $res['complementados']++;
                $res['resultados'][] = ['nome' => $n['nome'], 'id' => $id, 'acao' => 'complementado', 'campos' => $feitos];
                continue;
            }
            $ins->execute(array_merge(array_map(fn($c) => $vals[$c], $cols), [$agora]));
            $res['criados']++;
            $res['resultados'][] = ['nome' => $n['nome'], 'id' => (int)$pdo->lastInsertId(), 'acao' => 'criado'];
        }
        $pdo->commit();
        foreach (array_keys($novasCats) as $c) forn_add_categoria($pdo, $c);
        $res['categorias_criadas'] = array_keys($novasCats);
        echo json_encode($res, JSON_UNESCAPED_UNICODE); exit;
    }

    throw new Exception('ação inválida');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
