<?php
/**
 * ENVIO DE PEDIDOS DE COMPRA — a fila e as travas.
 *
 * ============================ AS QUATRO REGRAS ============================
 * O Murilo colocou quatro regras absolutas. Elas NÃO viraram "validações" — cada uma virou um
 * mecanismo diferente, porque validação a gente esquece de chamar:
 *
 *  1. NUNCA enviar pedido não aprovado.
 *     -> Não é um "if". A fila é CONSTRUÍDA a partir de status_aprovacao = Aprovado. O que não está
 *        aprovado não existe aqui. Não há caminho de código que envie um pendente.
 *
 *  2. NUNCA enviar para a obra errada.
 *     -> A identidade de um pedido é (COLIGADA + NÚMERO), nunca o número sozinho: medimos 381 números
 *        repetidos entre coligadas na pasta de PDFs, e foi isso que fez 2.067 dos 2.651 e-mails
 *        antigos não casarem. Além disso a obra precisa estar identificada E ter ficha vinculada;
 *        obra que o TOTVS não resolveu vai para BLOQUEADOS, nunca para a fila com um palpite.
 *
 *  3. NUNCA enviar duas vezes.
 *     -> Livro-caixa imutável (envio_registro) com UNIQUE em (coligada, numero, destino). Já
 *        aconteceu 52 vezes na caixa atual. O registro é gravado ANTES do disparo: se o SMTP cair no
 *        meio, o pior caso é um pedido não enviado (visível na fila de atrasados) e não um enviado
 *        duas vezes, que é irreversível na cabeça do fornecedor.
 *
 *  4. NUNCA deixar de enviar um pedido aprovado.
 *     -> Essa é a que falha calada hoje. Por isso o número de ATRASADOS é o primeiro da tela, e nada
 *        some da fila por decurso de prazo: só sai daqui quem foi enviado ou quem alguém segurou
 *        DE PROPÓSITO, com nome e motivo registrados.
 *
 * ============================ O CASO "(LANÇAR)" ============================
 * Boa parte dos pedidos existe só para regularizar material que já chegou. Se isso vai para o
 * fornecedor, ele entrega de novo. Já vazou 6 vezes. Procurar a palavra no texto não resolve
 * (aparece em 0,1% dos casos), mas sinais de nota/saldo aparecem em 10,3% — então o sistema AVISA
 * e a pessoa decide; a decisão fica gravada por pedido e não se repete.
 */
header('Content-Type: application/json; charset=utf-8');
/* O servidor roda em UTC; sem isto o carimbo do e-mail sai 3 horas adiantado. */
@date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../includes/db.php';
define('BP_LIB_ONLY', 1); require_once __DIR__ . '/busca_pedidos.php';  // bp_varrer / bp_obra_label / bp_nz
define('EC_LIB_ONLY', 1); require_once __DIR__ . '/envio_config.php';   // ec_resolver / ec_compor / ec_faltando
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/pdf_simples.php';
require_once __DIR__ . '/../includes/pdf_ler.php';      // confere o PDF anexado a mao

define('ENV_ATRASO_DIAS', 3);      // aprovado e parado há mais que isso = atrasado (regra 4)
define('ENV_JANELA_DIAS', 120);    // até onde a fila olha para trás
define('ENV_AMOSTRA', 120);        // bloqueados enviados por motivo (a contagem vai cheia — ver env_fila)

/* ============================ ANEXO MANUAL, CONFERIDO ============================
   Enquanto faltarem data de entrega e condicao de pagamento no export do TOTVS, o PDF vem da mao
   do comprador. Mas nao entra no escuro: pdf_conferir abre o arquivo, le o numero DENTRO dele e
   confere o CNPJ da empresa. PDF trocado e RECUSADO — nao e aviso amarelo, e recusa.
   Testado nos 14 modelos do Murilo: 14/14 aceitos, e os dois casos ruins (arquivo de outro pedido,
   e numero certo com coligada errada) recusados. */
define('ENV_PDF_DIR', __DIR__ . '/../data/pedidos_pdf');

function env_pdf_caminho($coligada, $numero) {
    return ENV_PDF_DIR . '/' . preg_replace('/\W+/', '', (string)$coligada) . '_'
         . ltrim(preg_replace('/\D+/', '', (string)$numero), '0') . '.pdf';
}
function env_pdf_tem($coligada, $numero) { return is_file(env_pdf_caminho($coligada, $numero)); }

/** CNPJ da empresa daquele pedido — vem da ficha da obra, quando houver. */
function env_cnpj_empresa($pdo, $fichaId) {
    if (!$fichaId) return '';
    try {
        $st = $pdo->prepare("SELECT cnpj FROM obra_ficha WHERE id=? LIMIT 1");
        $st->execute([(int)$fichaId]);
        return trim((string)$st->fetchColumn());
    } catch (Throwable $e) { return ''; }
}

function env_schema($pdo) {
    static $ok = false; if ($ok) return; $ok = true;
    $mysql = defined('DB_DRIVER') && DB_DRIVER === 'mysql';
    try {
        if ($mysql) {
            /* LIVRO-CAIXA. Não tem UPDATE em lugar nenhum do código — só INSERT e leitura.
               O UNIQUE é a trava real da regra 3: mesmo com dois cliques simultâneos, o banco recusa. */
            $pdo->exec("CREATE TABLE IF NOT EXISTS envio_registro (
                id INT AUTO_INCREMENT PRIMARY KEY,
                coligada_cod VARCHAR(10) NOT NULL, pedido_numero VARCHAR(40) NOT NULL,
                destino VARCHAR(16) NOT NULL,
                obra_nome VARCHAR(200), obra_ficha_id INT,
                fornecedor_cod VARCHAR(40), fornecedor_nome VARCHAR(200),
                para MEDIUMTEXT, cc MEDIUMTEXT, assunto VARCHAR(400),
                anexos MEDIUMTEXT, valor DOUBLE,
                enviado_em VARCHAR(40), enviado_por VARCHAR(64), enviado_por_nome VARCHAR(120),
                resultado VARCHAR(16) DEFAULT 'ok', erro MEDIUMTEXT, reenvio_motivo MEDIUMTEXT,
                UNIQUE KEY uq_env (coligada_cod, pedido_numero, destino),
                KEY idx_env_dt (enviado_em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            /* Decisão HUMANA por pedido: segurar, ou marcar que é regularização (não vai ao fornecedor). */
            $pdo->exec("CREATE TABLE IF NOT EXISTS envio_decisao (
                id INT AUTO_INCREMENT PRIMARY KEY,
                coligada_cod VARCHAR(10) NOT NULL, pedido_numero VARCHAR(40) NOT NULL,
                decisao VARCHAR(20) NOT NULL, motivo MEDIUMTEXT,
                por VARCHAR(64), por_nome VARCHAR(120), em VARCHAR(40),
                UNIQUE KEY uq_dec (coligada_cod, pedido_numero)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS envio_registro (id INTEGER PRIMARY KEY AUTOINCREMENT, coligada_cod TEXT NOT NULL, pedido_numero TEXT NOT NULL, destino TEXT NOT NULL, obra_nome TEXT, obra_ficha_id INTEGER, fornecedor_cod TEXT, fornecedor_nome TEXT, para TEXT, cc TEXT, assunto TEXT, anexos TEXT, valor REAL, enviado_em TEXT, enviado_por TEXT, enviado_por_nome TEXT, resultado TEXT DEFAULT 'ok', erro TEXT, reenvio_motivo TEXT)");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_env ON envio_registro (coligada_cod, pedido_numero, destino)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS envio_decisao (id INTEGER PRIMARY KEY AUTOINCREMENT, coligada_cod TEXT NOT NULL, pedido_numero TEXT NOT NULL, decisao TEXT NOT NULL, motivo TEXT, por TEXT, por_nome TEXT, em TEXT)");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_dec ON envio_decisao (coligada_cod, pedido_numero)");
        }
    } catch (Throwable $e) {}
}

/** A CHAVE de um pedido. Nunca use o número sozinho — ele se repete entre coligadas. */
function env_chave($coligada, $numero) { return trim((string)$coligada) . '|' . ltrim(trim((string)$numero), '0'); }

/**
 * Normaliza para procurar frase: MAIÚSCULA, sem acento, só letras e números.
 *
 * NÃO usa bp_nz aqui. O bp_nz depende de iconv //TRANSLIT, cujo resultado varia com o locale: nesta
 * máquina "Já" vira "J A" (com espaço no meio) e no servidor de produção o "ç" some. Para casar
 * NOME DE OBRA isso é tolerável; para decidir se um pedido é regularização, não é — a frase
 * "Já está sendo utilizado" simplesmente não casaria. Mapa fixo é feio e é previsível.
 */
function env_norm($s) {
    $s = strtr((string)$s, [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','ë'=>'e',
        'í'=>'i','î'=>'i','ì'=>'i','ï'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ò'=>'o','ö'=>'o',
        'ú'=>'u','û'=>'u','ù'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
        'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','É'=>'E','Ê'=>'E','È'=>'E','Ë'=>'E',
        'Í'=>'I','Î'=>'I','Ì'=>'I','Ï'=>'I','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ò'=>'O','Ö'=>'O',
        'Ú'=>'U','Û'=>'U','Ù'=>'U','Ü'=>'U','Ç'=>'C','Ñ'=>'N']);
    $s = strtoupper($s);
    $s = preg_replace('/[^A-Z0-9]+/', ' ', $s);
    return ' ' . trim(preg_replace('/\s+/', ' ', $s)) . ' ';
}

/**
  * Sinais de que o pedido é REGULARIZAÇÃO de algo que já está na obra — o caso "(lançar)".
  *
  * O Murilo apontou o PC 1703 (locação da ALUGTEC): a observação diz "Já está sendo utilizado na
  * obra - 2 meses". Isso é o sinal mais claro que existe, e eu não pegava — faltavam as frases de
  * USO, só havia as de ENTREGA. Equipamento locado não é "entregue", é "está em uso".
  *
  * Medido em 2.302 pedidos desde junho: o conjunto abaixo marca 1,1% (25 pedidos). É a ordem de
  * grandeza certa para uma trava — pega o caso real sem segurar a fila inteira.
  */
function env_sinal_regularizacao($txt) {
    $t = env_norm($txt);          // env_norm -> bp_nz: MAIÚSCULA sem acento, então nada de /u aqui
    foreach (['LANCAR', 'LANCAMENTO', 'JA ENTREGUE', 'JA RECEBIDO', 'REGULARIZA', 'SALDO DE',
              'COMPLEMENTO DE NF', 'MATERIAL ENTREGUE', 'ENTREGA REALIZADA', 'JA FOI ENTREGUE',
              'JA COMPRADO', 'MATERIAL JA',
              // locação e serviço em andamento: "já está sendo utilizado", "já em uso na obra"
              'JA ESTA SENDO UTILIZAD', 'JA ESTA SENDO US', 'JA ESTA UTILIZAD', 'JA ESTA EM USO',
              'JA VEM SENDO', 'JA SE ENCONTRA', 'UTILIZADO NA OBRA', 'EM USO NA OBRA',
              'JA ESTA INSTALAD', 'JA ESTA ALOCAD', 'JA FOI EXECUTAD', 'SERVICO JA'] as $s)
        if (strpos($t, $s) !== false) return true;
    return false;
}

/**
 * Sinal FRACO: a observação traz o CNPJ do fornecedor ("Obrigatoriamente: X - CNPJ: ...").
 *
 * O Murilo pediu para tratar isso como "manda só para a obra". Medi antes de implementar: aparece em
 * 28,7% dos pedidos (661 de 2.302) — quase um terço. Travar por aqui seguraria centenas de compras
 * legítimas, o que quebra a regra 4 (nunca deixar de enviar um aprovado) para consertar a 3.
 *
 * Então NÃO bloqueia: vira uma marca visível no cartão, para o comprador bater o olho e decidir.
 * Quando ele decide, a decisão fica gravada e não se repete.
 */
function env_sinal_cnpj_na_obs($txt) {
    return (bool)preg_match('/\bCNPJ\b/i', (string)$txt);
}

/**
 * MARCO ZERO — a trava que os números pediram.
 *
 * O livro-caixa nasce vazio, mas os pedidos NÃO: só nos últimos 120 dias existem 4.049 aprovados,
 * e quase todos já foram enviados à mão pelos compradores. Sem um corte, ligar o disparo mandaria
 * tudo de novo — a violação mais cara da regra 3, e irreversível na cabeça do fornecedor.
 *
 * Reconciliar o passado pelo e-mail não resolve: o número do PC se repete entre coligadas, e por
 * isso 2.067 dos 2.651 e-mails colhidos não casaram com pedido nenhum. Então o corte é por DATA e
 * é explícito: o que foi aprovado antes do marco é do processo manual e nunca entra na fila.
 */
function env_marco($pdo) {
    try {
        $st = $pdo->prepare("SELECT valor FROM envio_config WHERE escopo='global' AND ref='' AND campo='marco_zero'");
        $st->execute(); $v = trim((string)$st->fetchColumn());
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    } catch (Throwable $e) { return ''; }
}

function env_dias($data) {
    $t = strtotime((string)$data); if (!$t) return null;
    return (int)floor((time() - $t) / 86400);
}

/**
 * Acha o e-mail do fornecedor. TRES chaves, nesta ordem de confiabilidade:
 *
 *   1) CNPJ         — a unica chave que nao depende de como alguem digitou o nome.
 *   2) CODCFO       — exata, mas so 330 dos nossos cadastros tem esse codigo preenchido.
 *   3) NOME sem a forma juridica — o TOTVS grava "COMERCIAL ARARENSE" e o nosso cadastro
 *      "Comercial Ararense Ltda". Comparar os dois crus nao casa nunca; foi o que deixou a
 *      Comercial Ararense (5 pedidos) bloqueada mesmo tendo e-mail em DOIS cadastros nossos.
 *
 * Continua sem NADA de aproximado. Numa tentativa anterior a normalizacao agressiva casou "Gerdau"
 * com "E R CONSTRUCOES"; aqui so ha igualdade exata depois de tirar acento, caixa, pontuacao e o
 * sufixo societario. E chave ambigua (dois cadastros, mesmo nome, e-mails DIFERENTES) e descartada:
 * bloquear e alguem resolver e melhor do que mandar o pedido para o e-mail errado.
 */
function env_nome_forn($s) {
    $k = bp_nz($s);                                   // MAIUSCULA, sem acento, sem pontuacao
    $k = preg_replace('/\b(LTDA|EIRELI|EPP|MEI|SPE|CIA|S A|SA|ME)\b/', ' ', $k);
    $k = trim(preg_replace('/\s+/', ' ', $k));
    // uma palavra curta e generica demais para ser chave ("TIGRE", "ALUGTEC" ainda passam por tamanho)
    $tok = $k === '' ? [] : explode(' ', $k);
    if (count($tok) < 2 && strlen($k) < 8) return '';
    return $k;
}

function env_cnpj14($s) {
    $d = preg_replace('/\D+/', '', (string)$s);
    return strlen($d) === 14 ? $d : '';
}

function env_forn_email($pdo) {
    $ix = ['cnpj' => [], 'cod' => [], 'nome' => []];
    $amb = ['cnpj' => [], 'nome' => []];
    try {
        /* A tabela chama-se cot_fornecedor. Escrevi "fornecedores" e o try/catch abaixo engoliu o
           erro em silencio: o mapa vinha VAZIO, e por isso TODO fornecedor caia em "sem e-mail" —
           inclusive a Comercial Ararense, que tem e-mail em dois cadastros nossos. */
        foreach ($pdo->query("SELECT totvs_cod, nome, razao_social, cnpj, email FROM cot_fornecedor") as $f) {
            $e = trim((string)$f['email']);
            if ($e === '') continue;
            $reg = ['email' => $e, 'nome' => trim((string)$f['nome'])];

            $c = ltrim(trim((string)($f['totvs_cod'] ?? '')), '0');
            if ($c !== '' && !isset($ix['cod'][$c])) $ix['cod'][$c] = $reg;

            $cn = env_cnpj14($f['cnpj'] ?? '');
            if ($cn !== '') {
                if (isset($ix['cnpj'][$cn]) && strcasecmp($ix['cnpj'][$cn]['email'], $e) !== 0) $amb['cnpj'][$cn] = true;
                else $ix['cnpj'][$cn] = $reg;
            }
            foreach ([$f['nome'], $f['razao_social']] as $n) {
                $k = env_nome_forn((string)$n);
                if ($k === '') continue;
                if (isset($ix['nome'][$k]) && strcasecmp($ix['nome'][$k]['email'], $e) !== 0) $amb['nome'][$k] = true;
                else $ix['nome'][$k] = $reg;
            }
        }
        /* Espelho do TOTVS por CODCFO — 13.290 códigos, 9.044 com e-mail. Entra DEPOIS do nosso
           cadastro (o Murilo avisou que muitos e-mails do TOTVS estão desatualizados), mas é a
           chave exata do pedido: nada de depender de como o nome foi digitado. */
        foreach ($pdo->query("SELECT codcfo, cnpj, email FROM totvs_fornecedor WHERE email IS NOT NULL AND email<>''") as $t) {
            $e = trim((string)$t['email']);
            if ($e === '' || strpos($e, '@') === false) continue;
            $c = ltrim(trim((string)$t['codcfo']), '0');
            if ($c !== '' && !isset($ix['cod'][$c])) $ix['cod'][$c] = ['email' => $e, 'nome' => '', 'totvs' => 1];
            $cn = env_cnpj14($t['cnpj'] ?? '');
            if ($cn !== '' && !isset($ix['cnpj'][$cn]) && !isset($amb['cnpj'][$cn]))
                $ix['cnpj'][$cn] = ['email' => $e, 'nome' => '', 'totvs' => 1];
        }
        foreach (array_keys($amb['cnpj']) as $k) unset($ix['cnpj'][$k]);
        foreach (array_keys($amb['nome']) as $k) unset($ix['nome'][$k]);
    } catch (Throwable $e) { $ix['erro'] = $e->getMessage(); }
    /* Mapa vazio nao e "ninguem tem e-mail": e sinal de que a consulta falhou. Sem isto o erro
       aparece como 31 fornecedores sem e-mail, que e um sintoma plausivel e completamente errado. */
    if (!$ix['cnpj'] && !$ix['cod'] && !$ix['nome'] && empty($ix['erro']))
        $ix['erro'] = 'nenhum fornecedor com e-mail no cadastro';
    return $ix;
}

function env_forn_acha($mapa, $cod, $cnpj, $nome, $razao = '') {
    $cn = env_cnpj14($cnpj);
    if ($cn !== '' && isset($mapa['cnpj'][$cn])) return $mapa['cnpj'][$cn] + ['via' => 'CNPJ'];
    $c = ltrim(trim((string)$cod), '0');
    if ($c !== '' && isset($mapa['cod'][$c])) return $mapa['cod'][$c] + ['via' => 'código TOTVS'];
    foreach ([$nome, $razao] as $n) {
        $k = env_nome_forn((string)$n);
        if ($k !== '' && isset($mapa['nome'][$k])) return $mapa['nome'][$k] + ['via' => 'nome'];
    }
    return null;
}

/** Obra (nome que a Busca Pedidos mostra) -> ficha. Sem ficha não há endereço: não pode sair. */
function env_ficha_por_nome($pdo) {
    $m = [];
    try {
        foreach ($pdo->query("SELECT id, nome, coligada_nome, solic_nome FROM obra_ficha") as $o) {
            foreach ([$o['nome'], $o['solic_nome'], $o['coligada_nome']] as $n) {
                $k = bp_nz((string)$n);
                if ($k !== '' && !isset($m[$k])) $m[$k] = ['id' => (int)$o['id'], 'nome' => $o['nome']];
            }
        }
    } catch (Throwable $e) {}
    return $m;
}

/**
 * A CAPRETZ produz DOIS rótulos que não são nome de obra, e cada um pede um caminho diferente:
 *   "CAPRETZ/San Pietro"      -> compra rateada PARA a obra: o prefixo é ruído, a obra é San Pietro.
 *   "CAPRETZ · Administrativo"-> compra da SEDE: não tem canteiro, não tem CNO, não tem almoxarifado.
 * Tratar os dois como "obra sem ficha" escondia 791 pedidos reais atrás de um bloqueio errado.
 */
function env_desmembra_obra($label) {
    $l = trim((string)$label);
    if ($l === '') return ['tipo' => 'vazio', 'nome' => ''];
    if (preg_match('#^CAPRETZ\s*/\s*(.+)$#iu', $l, $m)) return ['tipo' => 'obra', 'nome' => trim($m[1])];
    /* O separador é o "·" (U+00B7); comparar por bytes evita depender de mbstring. */
    if (stripos($l, 'CAPRETZ') === 0 && strpos($l, "\xC2\xB7") !== false)
        return ['tipo' => 'sede', 'nome' => trim(substr($l, strpos($l, "\xC2\xB7") + 2))];
    return ['tipo' => 'obra', 'nome' => $l];
}


/**
 * DETALHE DE UM PEDIDO — todos os itens, com quantidade, unidade, preco e a observacao inteira.
 *
 * A fila carrega so um resumo (4 produtos, 500 caracteres de observacao) porque sao milhares de
 * linhas. Mas para DECIDIR ("isso e regularizacao?", "isso ainda vale?") o comprador precisa ver o
 * pedido inteiro — e a observacao longa e justamente onde mora o "(lancar)". Entao o detalhe e uma
 * consulta sob demanda, de um pedido so.
 */
function env_pedido_detalhe($pdo, $coligada, $numero) {
    $col = trim((string)$coligada); $num = ltrim(trim((string)$numero), '0');
    if ($col === '' || $num === '') return null;
    $itens = []; $cab = null;
    $q = 'select=pedido_numero,pedido_data,pedido_status,coligada_cod,coligada,ccusto_cod,ccusto_nome,'
       . 'fornecedor_cod,fornecedor_cnpj,fornecedor_nome,fornecedor_fantasia,produto,qtd,und,preco_unit,valor_total,'
       . 'item_observacao,solic_numeros,pedido_usuario,obra_efetiva_nome,obra_efetiva_fonte,obra_cod,data_entrega,'
       . 'status_aprovacao,etapa_aprovacao,aprovador'
       . '&coligada_cod=eq.' . rawurlencode($col)
       /* O TOTVS guarda o numero com zeros a esquerda ("000002638"). Consultar sem eles nao
          devolve linha nenhuma — o botao "Ver pedido" acharia sempre vazio. */
       . '&pedido_numero=eq.' . rawurlencode(str_pad($num, 9, '0', STR_PAD_LEFT));
    bp_varrer($q, function ($linhas) use (&$itens, &$cab) {
        foreach ($linhas as $l) {
            if ($cab === null) $cab = $l;
            $itens[] = ['produto' => (string)($l['produto'] ?? ''), 'qtd' => (float)($l['qtd'] ?? 0),
                        'und' => (string)($l['und'] ?? ''), 'preco' => (float)($l['preco_unit'] ?? 0),
                        'total' => (float)($l['valor_total'] ?? 0),
                        'obs' => trim((string)($l['item_observacao'] ?? '')),
                        /* A entrega e POR ITEM — a tela mostra em coluna, igual ao PDF. */
                        'entrega' => (string)($l['data_entrega'] ?? ''),
                        'sc' => trim((string)($l['solic_numeros'] ?? ''))];
        }
    });
    if ($cab === null) return null;
    $mapaRazao = bp_mapa_razao($pdo);
    $ap = bp_aprov($cab['status_aprovacao'] ?? '', $cab['etapa_aprovacao'] ?? '', $cab['aprovador'] ?? '');
    $obs = implode(' | ', array_values(array_unique(array_filter(array_map(fn($i) => $i['obs'], $itens)))));
    return [
        'numero' => $num, 'coligada_cod' => $col, 'coligada' => (string)($cab['coligada'] ?? ''),
        'data' => (string)($cab['pedido_data'] ?? ''), 'status' => (string)($cab['pedido_status'] ?? ''),
        'obra' => bp_obra_label($cab['obra_efetiva_nome'] ?? '', $cab['obra_efetiva_fonte'] ?? '', $mapaRazao,
                                $cab['coligada_cod'] ?? '', $cab['ccusto_cod'] ?? '', $cab['ccusto_nome'] ?? '',
                                $cab['obra_cod'] ?? '', bp_mapa_obracod($pdo)),
        'ccusto' => trim(((string)($cab['ccusto_cod'] ?? '')) . ' ' . ((string)($cab['ccusto_nome'] ?? ''))),
        'fornecedor' => trim((string)($cab['fornecedor_fantasia'] ?? '')) ?: trim((string)($cab['fornecedor_nome'] ?? '')),
        'fornecedor_razao' => trim((string)($cab['fornecedor_nome'] ?? '')),
        'fornecedor_cod' => ltrim(trim((string)($cab['fornecedor_cod'] ?? '')), '0'),
        'fornecedor_cnpj' => trim((string)($cab['fornecedor_cnpj'] ?? '')),
        'comprador' => trim((string)($cab['pedido_usuario'] ?? '')),
        'aprovacao' => bp_aprov_label($ap['k'], $ap['etapa']), 'aprov_k' => $ap['k'],
        'aprov_por' => $ap['por'], 'aprov_obs' => $ap['obs'],
        'scs' => implode(', ', array_values(array_unique(array_filter(array_map(fn($i) => $i['sc'], $itens))))),
        'observacao' => $obs,
        'regulariza' => env_sinal_regularizacao($obs . ' ' . implode(' ', array_map(fn($i) => $i['produto'], $itens))),
        'itens' => $itens,
        'valor' => array_sum(array_map(fn($i) => $i['total'], $itens)),
    ];
}

/**
 * Monta a fila. Devolve ENVELOPES (= e-mails que vão sair), não pedidos soltos: o comprador manda
 * "3 anexos se for a mesma obra", então a unidade de trabalho da tela é o e-mail, não o PC.
 */
function env_fila($pdo, $filtroObra = '') {
    $mapaRazao = bp_mapa_razao($pdo);
    $mapaObraCod = bp_mapa_obracod($pdo);   // rateio da CAPRETZ: a obra vem do obra_cod da SC
    $fichas    = env_ficha_por_nome($pdo);
    $fornMail  = env_forn_email($pdo);

    $jaEnviado = [];
    /* 'enviando' é o estado de dúvida (o processo morreu entre reservar e confirmar): o pedido não
       volta à fila, porque pode ter saído. Fica para alguém confirmar. */
    foreach ($pdo->query("SELECT coligada_cod, pedido_numero, destino, enviado_em, resultado FROM envio_registro") as $r)
        $jaEnviado[env_chave($r['coligada_cod'], $r['pedido_numero'])][$r['destino']] = $r['enviado_em'];
    $decisoes = [];
    foreach ($pdo->query("SELECT coligada_cod, pedido_numero, decisao, motivo, por_nome FROM envio_decisao") as $d)
        $decisoes[env_chave($d['coligada_cod'], $d['pedido_numero'])] = $d;

    $marco = env_marco($pdo);
    $desde = date('Y-m-d', strtotime('-' . ENV_JANELA_DIAS . ' days'));
    if ($marco !== '' && $marco > $desde) $desde = $marco;   // o marco manda quando é mais recente
    $peds = [];   // chave -> pedido agregado

    /* SÓ APROVADO ENTRA. Este filtro é a regra 1 — não existe outro caminho para a fila. */
    $q = 'select=pedido_numero,pedido_data,pedido_status,coligada_cod,coligada,ccusto_cod,ccusto_nome,'
       . 'fornecedor_cod,fornecedor_cnpj,fornecedor_nome,fornecedor_fantasia,produto,qtd,und,valor_total,item_observacao,'
       . 'obra_efetiva_nome,obra_efetiva_fonte,obra_cod,pedido_usuario,status_aprovacao,etapa_aprovacao,aprovador'
       . '&status_aprovacao=ilike.aprovado*&pedido_data=gte.' . $desde
       . '&order=pedido_data.desc,pedido_numero.desc';

    bp_varrer($q, function ($linhas) use (&$peds, $mapaRazao, $mapaObraCod) {
        foreach ($linhas as $l) {
            $k = env_chave($l['coligada_cod'] ?? '', $l['pedido_numero'] ?? '');
            if (!isset($peds[$k])) {
                $peds[$k] = [
                    'chave' => $k,
                    'coligada_cod' => (string)($l['coligada_cod'] ?? ''),
                    /* A base traz coligada_cod sempre e o NOME quase nunca (nos 32 pedidos da fila,
                       zero tinham). Sem o de-para o assunto saia "Pedido de Compra -  - Diamond -
                       1706", com o buraco no lugar da empresa que esta comprando — em todo e-mail.
                       Mesmo fallback que a Busca de Pedidos ja usava. */
                    'coligada' => (trim((string)($l['coligada'] ?? '')) ?: coligada_nome((int)($l['coligada_cod'] ?? 0))),
                    'coligada_sigla' => coligada_sigla((int)($l['coligada_cod'] ?? 0)),
                    'numero' => ltrim((string)($l['pedido_numero'] ?? ''), '0'),
                    'data' => (string)($l['pedido_data'] ?? ''),
                    'obra' => bp_obra_label($l['obra_efetiva_nome'] ?? '', $l['obra_efetiva_fonte'] ?? '',
                                            $mapaRazao, $l['coligada_cod'] ?? '', $l['ccusto_cod'] ?? '', $l['ccusto_nome'] ?? '',
                                            $l['obra_cod'] ?? '', $mapaObraCod),
                    'forn_cod' => ltrim(trim((string)($l['fornecedor_cod'] ?? '')), '0'),
                    'forn_nome' => trim((string)($l['fornecedor_fantasia'] ?? '')) !== ''
                                   ? trim((string)$l['fornecedor_fantasia']) : trim((string)($l['fornecedor_nome'] ?? '')),
                    'forn_razao' => trim((string)($l['fornecedor_nome'] ?? '')),
                    'forn_cnpj' => trim((string)($l['fornecedor_cnpj'] ?? '')),
                    'comprador' => trim((string)($l['pedido_usuario'] ?? '')),
                    /* Status do pedido no TOTVS (A/B/C/F/G/N/Q/R/U). É campo do PEDIDO, replicado em
                       cada linha — medido em 3.973 aprovados de 120 dias: ZERO variam entre as linhas
                       do mesmo pedido. Por isso a 1ª linha basta. Hoje é SÓ EXIBIÇÃO: 59% da fila está
                       'F' (faturado), ou seja, a nota já foi lançada e o pedido já morreu fora daqui. */
                    'status' => strtoupper(trim((string)($l['pedido_status'] ?? ''))),
                    'valor' => 0.0, 'itens' => 0, 'produtos' => [], 'obs' => '',
                ];
            }
            $p = &$peds[$k];
            $p['valor'] += (float)($l['valor_total'] ?? 0);
            $p['itens']++;
            if (count($p['produtos']) < 4) $p['produtos'][] = trim((string)($l['produto'] ?? ''));
            $o = trim((string)($l['item_observacao'] ?? ''));
            if ($o !== '' && strlen($p['obs']) < 500) $p['obs'] .= ($p['obs'] === '' ? '' : ' | ') . $o;
            unset($p);
        }
    });

    $fila = []; $bloq = []; $atrasados = 0; $segurados = []; $arquivados = 0;
    foreach ($peds as $k => $p) {
        if ($filtroObra !== '' && $p['obra'] !== $filtroObra) continue;

        $p['dias'] = env_dias($p['data']);
        $p['regulariza'] = env_sinal_regularizacao($p['obs'] . ' ' . implode(' ', $p['produtos']));
        $p['forn_travado'] = env_sinal_cnpj_na_obs($p['obs']);   // fornecedor fixado na SC — só informa

        $dec = $decisoes[$k] ?? null;
        /* ARQUIVADO sai da tela por completo — nem fila, nem bloqueado, nem contagem. É o "isso aqui
           não é mais para enviar" do pedido antigo. Não apaga nada: continua no TOTVS e volta com
           um clique em Arquivados. Diferente de SEGURADO, que fica à vista de propósito. */
        if ($dec && $dec['decisao'] === 'arquivado') { $arquivados++; continue; }
        if ($dec && $dec['decisao'] === 'segurar') {
            $p['motivo'] = $dec['motivo']; $p['por'] = $dec['por_nome'];
            $segurados[] = $p; continue;
        }
        /* Destino: quem foi marcado como regularização NUNCA vai ao fornecedor. */
        $destino = ($dec && $dec['decisao'] === 'so_obra') ? 'obra' : 'fornecedor';

        // ---- REGRA 3: já saiu? sai da fila (fica no histórico) ----
        if (isset($jaEnviado[$k][$destino])) continue;

        // ---- REGRA 2: obra tem que estar resolvida e com ficha ----
        $des = env_desmembra_obra($p['obra']);
        if ($des['tipo'] === 'sede') {
            $p['bloqueio'] = 'sede'; $p['area'] = $des['nome'];
            $p['bloqueio_txt'] = 'Compra da sede (' . $des['nome'] . ') — não tem canteiro, então não tem endereço de entrega nem CNO.';
            $bloq[] = $p; continue;
        }
        $f = $fichas[bp_nz($des['nome'])] ?? null;
        if ($des['tipo'] === 'vazio' || !$f) {
            $p['bloqueio'] = 'obra'; $p['bloqueio_txt'] = $des['tipo'] === 'vazio'
                ? 'O TOTVS não resolveu a obra deste pedido.'
                : 'A obra "' . $des['nome'] . '" não tem ficha no cockpit — sem ficha não há endereço de entrega.';
            $bloq[] = $p; continue;
        }
        $p['ficha_id'] = $f['id'];

        $res = ec_resolver($pdo, $f['id']);
        $faltando = $res ? ec_faltando($res['efetivo']) : ['configuração de envio'];
        if ($faltando) {
            $p['bloqueio'] = 'config';
            $p['bloqueio_txt'] = 'A obra ' . $f['nome'] . ' ainda não tem: ' . implode(', ', $faltando) . '.';
            $bloq[] = $p; continue;
        }

        // ---- destinatário ----
        $fm = env_forn_acha($fornMail, $p['forn_cod'], $p['forn_cnpj'] ?? '', $p['forn_nome'], $p['forn_razao'] ?? '');
        $p['para'] = $destino === 'obra' ? trim((string)($res['efetivo']['email_nf'] ?? '')) : trim((string)($fm['email'] ?? ''));
        $p['email_via'] = $fm['via'] ?? '';
        if ($destino === 'fornecedor' && $p['para'] === '') {
            $p['bloqueio'] = 'email';
            $p['forn_cnpj_fmt'] = trim((string)($p['forn_cnpj'] ?? ''));
            $p['bloqueio_txt'] = 'Não temos e-mail de ' . ($p['forn_nome'] ?: 'fornecedor')
                . ' — procurei por CNPJ ' . (env_cnpj14($p['forn_cnpj'] ?? '') ?: '(não informado)')
                . ', código TOTVS ' . ($p['forn_cod'] ?: '—') . ' e pelo nome.';
            $bloq[] = $p; continue;
        }
        $p['destino'] = $destino;
        $p['tem_pdf'] = env_pdf_tem($p['coligada_cod'], $p['numero']);
        $p['obra_ficha'] = $f['nome'];
        $p['assina'] = trim((string)($res['obra']['comprador_nome'] ?? ''));
        if ($p['dias'] !== null && $p['dias'] > ENV_ATRASO_DIAS) $atrasados++;
        $fila[] = $p;
    }

    /* ENVELOPES: um e-mail por (obra × fornecedor × destino). */
    $env = [];
    foreach ($fila as $p) {
        $ek = $p['ficha_id'] . '|' . $p['destino'] . '|' . ($p['destino'] === 'obra' ? '' : $p['forn_cod']);
        if (!isset($env[$ek])) $env[$ek] = [
            'chave' => $ek, 'destino' => $p['destino'], 'obra' => $p['obra_ficha'], 'ficha_id' => $p['ficha_id'],
            'forn_nome' => $p['destino'] === 'obra' ? 'Obra / lançamento' : $p['forn_nome'],
            'forn_cod' => $p['forn_cod'], 'para' => $p['para'], 'assina' => $p['assina'],
            'pedidos' => [], 'valor' => 0.0, 'dias' => 0, 'alerta' => false, 'sem_pdf' => 0,
            'n_faturado' => 0, 'n_parcial' => 0,
        ];
        $env[$ek]['pedidos'][] = $p;
        $env[$ek]['valor'] += $p['valor'];
        $env[$ek]['dias'] = max($env[$ek]['dias'], (int)$p['dias']);
        /* FATURAMENTO (só contagem, ainda não decide nada). F=faturado, Q=quitado, B=baixado — o
           pedido já terminou o ciclo no TOTVS. G=parcialmente faturado é o caso ambíguo: parte veio,
           parte não; por isso conta separado e nunca se mistura com o resto. */
        if (in_array($p['status'] ?? '', ['F', 'Q', 'B'], true)) $env[$ek]['n_faturado']++;
        elseif (($p['status'] ?? '') === 'G')                    $env[$ek]['n_parcial']++;
        if ($p['regulariza']) $env[$ek]['alerta'] = true;
        if (!empty($p['forn_travado'])) $env[$ek]['forn_travado'] = true;
        if (empty($p['tem_pdf'])) $env[$ek]['sem_pdf'] = ($env[$ek]['sem_pdf'] ?? 0) + 1;
    }
    $env = array_values($env);
    usort($env, fn($a, $b) => ($b['dias'] <=> $a['dias']) ?: strcmp($a['obra'], $b['obra']));
    usort($bloq, fn($a, $b) => strcmp($a['bloqueio'], $b['bloqueio']) ?: ($b['dias'] <=> $a['dias']));

    /* Enquanto a configuração das obras não estiver preenchida, um balde sozinho tem 3.275 pedidos —
       mandar isso inteiro faz 2,8 MB de JSON e trava a tela. Manda-se uma AMOSTRA por motivo, mas a
       CONTAGEM continua cheia e a tela diz quantos ficaram de fora: teto escondido vira "está tudo ok". */
    $porMotivo = []; $amostra = [];
    foreach ($bloq as $b) {
        $m = $b['bloqueio'];
        $porMotivo[$m] = ($porMotivo[$m] ?? 0) + 1;
        if ($porMotivo[$m] <= ENV_AMOSTRA) $amostra[] = $b;
    }
    $resumo = [];
    foreach ($porMotivo as $m => $n)
        $resumo[$m] = ['total' => $n, 'mostrando' => min($n, ENV_AMOSTRA),
                       'valor' => array_sum(array_map(fn($b) => $b['bloqueio'] === $m ? $b['valor'] : 0, $bloq))];

    return ['envelopes' => $env, 'bloqueados' => $amostra, 'bloq_resumo' => $resumo, 'segurados' => $segurados,
            'aviso_cadastro' => $fornMail['erro'] ?? '',
            'marco' => $marco, 'desde' => $desde,
            'contadores' => [
                'envelopes' => count($env), 'pedidos' => count($fila),
                'atrasados' => $atrasados, 'bloqueados' => count($bloq),
                'segurados' => count($segurados),
                'valor' => array_sum(array_map(fn($e) => $e['valor'], $env)),
                'com_alerta' => count(array_filter($env, fn($e) => $e['alerta'])),
                'sede' => count(array_filter($bloq, fn($b) => $b['bloqueio'] === 'sede')),
                'arquivados' => $arquivados,
                /* FATURADOS — por ora só medição, para conferir o número antes de deixar isto decidir
                   qualquer coisa. 'envelopes_faturados' = e-mail em que TODOS os pedidos já foram
                   faturados: é o candidato a não ir ao fornecedor de jeito nenhum. */
                'pedidos_faturados' => count(array_filter($fila, fn($p) => in_array($p['status'] ?? '', ['F', 'Q', 'B'], true))),
                'pedidos_parciais'  => count(array_filter($fila, fn($p) => ($p['status'] ?? '') === 'G')),
                'envelopes_faturados' => count(array_filter($env, fn($e) => $e['n_faturado'] > 0 && $e['n_faturado'] === count($e['pedidos']))),
            ]];
}

try {
    $pdo = db();
    env_schema($pdo);
    /* O envio_config.php entrou como BIBLIOTECA, então o bloco dele que cria/semeia as tabelas não
       roda. Sem isto, a primeira abertura da fila quebra em "Table envio_config doesn't exist". */
    ec_schema($pdo); ec_seed($pdo);
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($metodo === 'GET') {
        $perms = user_perms($pdo, $_GET['me'] ?? null);
        if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }

        if (isset($_GET['baixar_pdf'])) {
            $p = explode('|', (string)$_GET['baixar_pdf']);
            $c = env_pdf_caminho($p[0] ?? '', $p[1] ?? '');
            if (!is_file($c)) { http_response_code(404); echo json_encode(['error' => 'sem anexo']); exit; }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="PC ' . preg_replace('/\D+/', '', $p[1] ?? '') . '.pdf"');
            readfile($c); exit;
        }
        if (isset($_GET['pedido'])) {
            $p = explode('|', (string)$_GET['pedido']);
            $d = env_pedido_detalhe($pdo, $p[0] ?? '', $p[1] ?? '');
            if ($d) $d['tem_pdf'] = env_pdf_tem($p[0] ?? '', $p[1] ?? '');
            echo json_encode($d ?: ['error' => 'pedido nao encontrado'], JSON_UNESCAPED_UNICODE); exit;
        }
        if (isset($_GET['historico'])) {
            $lim = max(1, min(500, (int)($_GET['limite'] ?? 100)));
            $h = $pdo->query("SELECT * FROM envio_registro ORDER BY enviado_em DESC LIMIT $lim")->fetchAll();
            $tot = (int)$pdo->query("SELECT COUNT(*) FROM envio_registro")->fetchColumn();
            echo json_encode(['itens' => $h, 'total' => $tot], JSON_UNESCAPED_UNICODE); exit;
        }
        $r = env_fila($pdo, trim((string)($_GET['obra'] ?? '')));
        $r['atraso_dias'] = ENV_ATRASO_DIAS;
        $r['janela_dias'] = ENV_JANELA_DIAS;
        /* De qual endereço isto vai sair. A tela precisa saber ANTES do clique: o fornecedor conhece
           pedidos@caprem.com.br, e um lote saindo da conta das cotações vira "quem é esse remetente?"
           multiplicado por quantos e-mails o comprador marcou. */
        $ce = ec_conta_efetiva();
        $r['conta'] = ['de' => (string)($ce['user'] ?? ''), 'fonte' => (string)($ce['fonte'] ?? ''),
                       'e_pedidos' => (($ce['fonte'] ?? '') === 'pedidos'),
                       'configurada' => !empty($ce['user']) && !empty($ce['senha'])];
        echo json_encode($r, JSON_UNESCAPED_UNICODE); exit;
    }

    /* Upload chega como multipart; o resto como JSON no corpo. */
    $in = !empty($_POST) ? $_POST : (json_decode(file_get_contents('php://input'), true) ?: []);
    $perms = user_perms($pdo, $in['me'] ?? null);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }
    $acao = $in['acao'] ?? '';



    /**
     * GERAR O PDF do pedido a partir do TOTVS e guardá-lo como anexo.
     *
     * Este é o caminho normal — o anexo manual existe só como escape. Gerado aqui, o PDF nasce do
     * MESMO registro que decidiu a obra e o destinatário, então não há como o arquivo divergir do
     * pedido (que é o buraco de anexar da pasta: 9 dos 1.303 arquivos têm no nome um número que não
     * é o de dentro).
     *
     * Se os valores do pedido não fecham na base, NÃO gera: devolve a lista de itens divergentes.
     */
    if ($acao === 'gerar_pdf') {
        $col = trim((string)($in['coligada'] ?? '')); $num = ltrim((string)($in['numero'] ?? ''), '0');
        if ($col === '' || $num === '') throw new Exception('pedido não identificado');
        if (!defined('PP_LIB_ONLY')) define('PP_LIB_ONLY', 1);
        require_once __DIR__ . '/pedido_pdf.php';
        require_once __DIR__ . '/../includes/pdf_pedido.php';

        $d = pp_montar($pdo, $col, $num, [
            'ficha_id' => (int)($in['ficha_id'] ?? 0),
            'obra'     => trim((string)($in['obra'] ?? '')),
            'so_obra'  => !empty($in['so_obra']),
        ]);
        if (!$d) throw new Exception('pedido não encontrado no TOTVS');
        if (!empty($d['divergencias'])) {
            echo json_encode(['error' => 'Os valores deste pedido não fecham na base do TOTVS — o PDF não foi gerado.',
                              'divergencias' => $d['divergencias']], JSON_UNESCAPED_UNICODE); exit;
        }
        $bin = pdf_pedido($d);
        if (!is_dir(ENV_PDF_DIR)) @mkdir(ENV_PDF_DIR, 0755, true);
        if (@file_put_contents(env_pdf_caminho($col, $num), $bin) === false)
            throw new Exception('não consegui gravar o PDF no servidor');
        echo json_encode(['ok' => true, 'bytes' => strlen($bin), 'itens' => count($d['itens'])]); exit;
    }

    /** Gera de uma vez todos os pedidos de um envelope. */
    if ($acao === 'gerar_pdf_lote') {
        if (!defined('PP_LIB_ONLY')) define('PP_LIB_ONLY', 1);
        require_once __DIR__ . '/pedido_pdf.php';
        require_once __DIR__ . '/../includes/pdf_pedido.php';
        $ok = 0; $falhas = [];
        foreach ((array)($in['pedidos'] ?? []) as $p) {
            $col = trim((string)($p['coligada'] ?? '')); $num = ltrim((string)($p['numero'] ?? ''), '0');
            if ($col === '' || $num === '') continue;
            try {
                $d = pp_montar($pdo, $col, $num, ['ficha_id' => (int)($in['ficha_id'] ?? 0),
                                                  'obra' => trim((string)($in['obra'] ?? '')),
                                                  'so_obra' => !empty($in['so_obra'])]);
                if (!$d) { $falhas[] = ['numero' => $num, 'motivo' => 'não encontrado no TOTVS']; continue; }
                if (!empty($d['divergencias'])) {
                    $falhas[] = ['numero' => $num, 'motivo' => 'valores não fecham: ' . implode('; ', array_slice($d['divergencias'], 0, 3))];
                    continue;
                }
                if (!is_dir(ENV_PDF_DIR)) @mkdir(ENV_PDF_DIR, 0755, true);
                @file_put_contents(env_pdf_caminho($col, $num), pdf_pedido($d));
                $ok++;
            } catch (Throwable $e) { $falhas[] = ['numero' => $num, 'motivo' => $e->getMessage()]; }
        }
        echo json_encode(['ok' => true, 'gerados' => $ok, 'falhas' => $falhas], JSON_UNESCAPED_UNICODE); exit;
    }

    /* Upload do PDF de UM pedido. multipart/form-data: pdf + coligada + numero. */
    if ($acao === 'anexo') {
        $col = trim((string)($in['coligada'] ?? '')); $num = ltrim((string)($in['numero'] ?? ''), '0');
        if ($col === '' || $num === '') throw new Exception('pedido nao identificado');
        if (empty($_FILES['pdf']['tmp_name'])) throw new Exception('nenhum arquivo recebido');
        if ((int)$_FILES['pdf']['size'] > 8 * 1024 * 1024) throw new Exception('arquivo acima de 8 MB');
        $bin = @file_get_contents($_FILES['pdf']['tmp_name']);
        if (!$bin || substr($bin, 0, 5) !== '%PDF-') throw new Exception('o arquivo nao e um PDF');

        $fichaId = (int)($in['ficha_id'] ?? 0);
        $conf = pdf_conferir($bin, $num, env_cnpj_empresa($pdo, $fichaId), (string)($in['cnpj_forn'] ?? ''));
        if (!$conf['ok']) { echo json_encode(['error' => $conf['motivo'], 'conferencia' => $conf], JSON_UNESCAPED_UNICODE); exit; }

        if (!is_dir(ENV_PDF_DIR)) @mkdir(ENV_PDF_DIR, 0755, true);
        if (@file_put_contents(env_pdf_caminho($col, $num), $bin) === false)
            throw new Exception('nao consegui gravar o arquivo no servidor');
        echo json_encode(['ok' => true, 'aviso' => $conf['motivo'], 'pc_no_pdf' => $conf['pc_no_pdf'],
                          'bytes' => strlen($bin)], JSON_UNESCAPED_UNICODE); exit;
    }
    if ($acao === 'anexo_remover') {
        $c = env_pdf_caminho((string)($in['coligada'] ?? ''), (string)($in['numero'] ?? ''));
        if (is_file($c)) @unlink($c);
        echo json_encode(['ok' => true]); exit;
    }

    /* Segurar / liberar / marcar como regularização. Toda decisão fica com nome e motivo:
       "por que este pedido aprovado não saiu?" precisa ter resposta em qualquer dia do ano. */
    if ($acao === 'decidir') {
        $d = (string)($in['decisao'] ?? '');
        if (!in_array($d, ['segurar', 'so_obra', 'arquivado', 'liberar'], true)) throw new Exception('decisão inválida');
        $col = (string)($in['coligada'] ?? ''); $num = ltrim((string)($in['numero'] ?? ''), '0');
        if ($col === '' || $num === '') throw new Exception('pedido não identificado');
        if ($d === 'liberar') {
            $pdo->prepare("DELETE FROM envio_decisao WHERE coligada_cod=? AND pedido_numero=?")->execute([$col, $num]);
            echo json_encode(['ok' => true]); exit;
        }
        $motivo = trim((string)($in['motivo'] ?? ''));
        if ($motivo === '') throw new Exception('Escreva o motivo — ele fica registrado.');
        $args = [$col, $num, $d, $motivo, (string)($in['me'] ?? ''), (string)($in['me_nome'] ?? ''), date('c')];
        $up = $pdo->prepare("UPDATE envio_decisao SET decisao=?,motivo=?,por=?,por_nome=?,em=? WHERE coligada_cod=? AND pedido_numero=?");
        $up->execute([$d, $motivo, $args[4], $args[5], $args[6], $col, $num]);
        if (!$up->rowCount()) $pdo->prepare("INSERT INTO envio_decisao (coligada_cod,pedido_numero,decisao,motivo,por,por_nome,em) VALUES (?,?,?,?,?,?,?)")->execute($args);
        echo json_encode(['ok' => true]); exit;
    }


    /**
     * ARQUIVAR EM LOTE. Pedido antigo aprovado nao e para enviar mais, mas apagar da fila um a um
     * seria milhares de cliques — e apagar de verdade destruiria a resposta de "por que este pedido
     * aprovado nunca saiu?". Entao arquiva-se por CRITERIO (data limite + obra opcional), com motivo,
     * e o lote inteiro volta com um clique.
     */
    if ($acao === 'arquivar_lote') {
        $ate = trim((string)($in['ate'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) throw new Exception('Escolha a data limite.');
        $obra = trim((string)($in['obra'] ?? ''));
        $motivo = trim((string)($in['motivo'] ?? ''));
        if ($motivo === '') throw new Exception('Escreva o motivo — ele fica registrado.');

        $ja = [];
        foreach ($pdo->query("SELECT coligada_cod, pedido_numero FROM envio_decisao") as $d)
            $ja[env_chave($d['coligada_cod'], $d['pedido_numero'])] = true;
        foreach ($pdo->query("SELECT coligada_cod, pedido_numero FROM envio_registro") as $r)
            $ja[env_chave($r['coligada_cod'], $r['pedido_numero'])] = true;

        $mapaRazao = bp_mapa_razao($pdo);
        $desde = date('Y-m-d', strtotime('-' . ENV_JANELA_DIAS . ' days'));
        $alvo = [];
        $mapaObraCod = bp_mapa_obracod($pdo);
        bp_varrer('select=pedido_numero,coligada_cod,ccusto_cod,ccusto_nome,obra_efetiva_nome,obra_efetiva_fonte,obra_cod'
                  . '&status_aprovacao=ilike.aprovado*&pedido_data=gte.' . $desde . '&pedido_data=lte.' . $ate
                  . '&order=pedido_numero.asc',
            function ($linhas) use (&$alvo, $mapaRazao, $mapaObraCod, $obra, $ja) {
                foreach ($linhas as $l) {
                    $k = env_chave($l['coligada_cod'] ?? '', $l['pedido_numero'] ?? '');
                    if (isset($ja[$k]) || isset($alvo[$k])) continue;
                    if ($obra !== '') {
                        $nome = bp_obra_label($l['obra_efetiva_nome'] ?? '', $l['obra_efetiva_fonte'] ?? '',
                                              $mapaRazao, $l['coligada_cod'] ?? '', $l['ccusto_cod'] ?? '', $l['ccusto_nome'] ?? '',
                                              $l['obra_cod'] ?? '', $mapaObraCod);
                        if (bp_nz($nome) !== bp_nz($obra)) continue;
                    }
                    $alvo[$k] = [(string)($l['coligada_cod'] ?? ''), ltrim((string)($l['pedido_numero'] ?? ''), '0')];
                }
            });

        $ins = $pdo->prepare("INSERT INTO envio_decisao (coligada_cod,pedido_numero,decisao,motivo,por,por_nome,em) VALUES (?,?,'arquivado',?,?,?,?)");
        $n = 0; $now = date('c');
        $pdo->beginTransaction();
        foreach ($alvo as $a) {
            try { $ins->execute([$a[0], $a[1], $motivo, (string)($in['me'] ?? ''), (string)($in['me_nome'] ?? ''), $now]); $n++; }
            catch (Throwable $e) {}   // corrida com outra decisão: o UNIQUE recusa e está certo
        }
        $pdo->commit();
        echo json_encode(['ok' => true, 'arquivados' => $n]); exit;
    }

    /** Desfaz o lote inteiro de uma vez (o motivo identifica o lote). */
    if ($acao === 'desarquivar_lote') {
        $m = trim((string)($in['motivo'] ?? ''));
        if ($m === '') throw new Exception('lote não identificado');
        $q = $pdo->prepare("DELETE FROM envio_decisao WHERE decisao='arquivado' AND motivo=?");
        $q->execute([$m]);
        echo json_encode(['ok' => true, 'devolvidos' => $q->rowCount()]); exit;
    }

    /** Lotes arquivados, agrupados pelo motivo — é assim que se desfaz. */
    if ($acao === 'arquivados') {
        $r = $pdo->query("SELECT motivo, por_nome, MIN(em) em, COUNT(*) n FROM envio_decisao WHERE decisao='arquivado' GROUP BY motivo, por_nome ORDER BY MIN(em) DESC")->fetchAll();
        echo json_encode(['lotes' => $r], JSON_UNESCAPED_UNICODE); exit;
    }


    /**
     * ENVIO DE TESTE — o unico caminho deste arquivo que realmente dispara um e-mail.
     *
     * Existe para provar o encanamento inteiro (compor -> anexar -> SMTP -> caixa de entrada) SEM
     * chegar perto de um fornecedor. Tres cercas, e nenhuma delas e opcional:
     *
     *   a) o destinatario e DIGITADO na hora, nunca escolhido de uma lista de fornecedores. Nao ha
     *      como o teste cair num fornecedor real por um clique errado;
     *   b) NAO grava em envio_registro. O livro-caixa e so de envio de verdade — se o teste
     *      gravasse, ele "queimaria" aquele pedido e a regra 4 quebraria calada depois;
     *   c) o assunto e o corpo saem carimbados como TESTE, para que ninguem que receba por engano
     *      trate aquilo como pedido.
     *
     * O anexo e um PDF gerado aqui, de uma pagina. NAO e o pedido de compra definitivo — e a prova
     * de que o anexo atravessa. O PDF real ainda e uma decisao em aberto (gerar aqui x ler da pasta).
     */
    if ($acao === 'teste') {
        if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error' => 'Apenas administradores.']); exit; }
        $para = trim((string)($in['para'] ?? ''));
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) throw new Exception('Digite um e-mail valido para o teste.');
        $fichaId = (int)($in['obra_id'] ?? 0);
        if (!$fichaId) throw new Exception('Escolha a obra.');
        $tipo = ($in['tipo'] ?? 'fornecedor') === 'obra' ? 'obra' : 'fornecedor';

        /* Pedido sai de pedidos@caprem.com.br, nao da conta das cotacoes. */
        $cfg = ec_conta_efetiva();
        if (empty($cfg['user']) || empty($cfg['senha']))
            throw new Exception('A conta de envio de PEDIDOS ainda nao esta configurada (Configuracoes > E-mail do pedido > Conta de envio).');

        $pcs = array_values(array_filter(array_map('trim', explode(',', (string)($in['pcs'] ?? '9001,9002')))));
        $c = ec_compor($pdo, $fichaId, $tipo, [
            'pcs' => $pcs,
            'fornecedor' => trim((string)($in['fornecedor'] ?? '')) ?: 'Fornecedor de teste',
            'sigla' => trim((string)($in['sigla'] ?? 'CPR')),
        ]);
        if (!$c) throw new Exception('Obra nao encontrada.');

        $res = ec_resolver($pdo, $fichaId); $ef = $res['efetivo'];
        $anexos = [];
        if (!empty($in['com_anexo'])) {
            foreach ($pcs as $pc) {
                $linhas = [
                    ['ESTE DOCUMENTO E UM TESTE DO COCKPIT DE SUPRIMENTOS', 11, true], '',
                    'Nao e um pedido de compra. Nao produz efeito comercial.', '',
                    ['Pedido de compra n. ' . $pc, 13, true],
                    'Obra: ' . $c['obra'],
                    'Fornecedor: ' . (trim((string)($in['fornecedor'] ?? '')) ?: 'Fornecedor de teste'),
                    'Emitido em: ' . date('d/m/Y H:i'), '',
                    ['Dados de entrega que o sistema resolveu para esta obra', 12, true],
                    'CNO: ' . (trim((string)($ef['cno'] ?? '')) ?: '(nao preenchido)'),
                    'Endereco: ' . (trim((string)($ef['endereco'] ?? '')) ?: '(nao preenchido)'),
                    'Complemento: ' . (trim((string)($ef['complemento'] ?? '')) ?: '-'),
                    'Almoxarifado: ' . (trim((string)($ef['almox_nome'] ?? '')) ?: '(nao preenchido)')
                        . ' ' . trim((string)($ef['almox_fone'] ?? '')),
                    'Horario: ' . (trim((string)($ef['horario'] ?? '')) ?: '(nao preenchido)'),
                    'E-mail de NF: ' . (trim((string)($ef['email_nf'] ?? '')) ?: '(nao preenchido)'), '',
                    ['Se estes dados estao certos, a configuracao da obra esta certa.', 10, false],
                ];
                $anexos[] = ['nome' => 'TESTE PC ' . $pc . '.pdf', 'mime' => 'application/pdf',
                             'conteudo' => pdf_simples('PEDIDO DE COMPRA (TESTE)', $linhas)];
            }
        }

        $selo = '<div style="font-family:Arial,sans-serif;font-size:13px;background:#fdf1ef;border-left:4px solid #c0392b;'
              . 'padding:10px 13px;margin-bottom:16px"><b>TESTE DO COCKPIT DE SUPRIMENTOS.</b><br>'
              . 'Este e-mail nao e um pedido de compra e nao produz efeito comercial. '
              . 'Obra escolhida: <b>' . htmlspecialchars($c['obra'], ENT_QUOTES, 'UTF-8') . '</b>. '
              . 'Enviado por ' . htmlspecialchars((string)($in['me_nome'] ?? 'administrador'), ENT_QUOTES, 'UTF-8')
              . ' em ' . date('d/m/Y H:i') . '.</div>';

        $cfgS = ['host' => $cfg['host'] ?? '', 'port' => (int)($cfg['port'] ?? 465),
                 'user' => $cfg['user'], 'senha' => $cfg['senha'],
                 'from' => $cfg['user'], 'from_name' => (trim((string)($cfg['nome'] ?? '')) ?: 'Caprem - Suprimentos') . ' (teste)'];
        /* smtp_send devolve um PAR [ok, mensagem] — tratar como booleano faria todo envio parecer
           bem-sucedido, porque array nao-vazio e truthy. */
        $ok = false; $erro = '';
        try { list($ok, $erro) = smtp_send($cfgS, $para, '[TESTE] ' . $c['assunto'], $selo . $c['html'],
                                           $anexos, [], ['html' => true, 'cc' => []]); }
        catch (Throwable $e) { $erro = $e->getMessage(); }
        if (!$ok && trim((string)$erro) === '') $erro = 'o servidor de e-mail recusou o envio';

        /* De proposito NAO grava em envio_registro: o livro-caixa e so de envio real. */
        echo json_encode(['ok' => (bool)$ok, 'erro' => $erro, 'para' => $para,
                          'de' => $cfg['user'], 'conta' => $cfg['fonte'],
                          'assunto' => '[TESTE] ' . $c['assunto'], 'anexos' => count($anexos),
                          'faltando' => $c['faltando']], JSON_UNESCAPED_UNICODE); exit;
    }


    /**
     * ============================ O DISPARO ============================
     *
     * ORDEM DOS PASSOS, e por quê:
     *
     *  1. Reconfere TODAS as travas AQUI, do zero. Nada do que o navegador mandou é aceito como
     *     verdade — nem a obra, nem o destinatário, nem o "está aprovado". O cliente diz apenas
     *     QUAL envelope; o servidor decide se ele pode sair.
     *  2. GRAVA no livro-caixa com resultado='enviando' ANTES de falar com o SMTP. O UNIQUE
     *     (coligada, número, destino) é o que impede o segundo envio mesmo com dois cliques ao
     *     mesmo tempo — em duas abas, em dois computadores, no mesmo segundo.
     *  3. Dispara.
     *  4. Marca 'ok' — ou apaga a marca, se o SMTP recusou, para o pedido voltar à fila.
     *
     * Se o processo morrer entre 2 e 4, a linha fica em 'enviando': o pedido NÃO volta à fila (pode
     * ter saído) e NÃO conta como enviado (pode não ter saído). Fica visível como "confirmar" para
     * alguém decidir. É o único estado seguro para uma dúvida — as duas alternativas automáticas
     * quebrariam a regra 3 ou a 4.
     */
    if ($acao === 'enviar') {
        $chave = trim((string)($in['envelope'] ?? ''));
        if ($chave === '') throw new Exception('envelope não identificado');

        /* Recalcula a fila do zero: é a única forma de garantir que o que vai sair é o que as
           travas aprovam AGORA, e não o que a tela mostrava há dez minutos. */
        $fila = env_fila($pdo);
        $env = null;
        foreach ($fila['envelopes'] as $e) if ($e['chave'] === $chave) { $env = $e; break; }
        if (!$env) throw new Exception('Este e-mail não está mais na fila — pode já ter sido enviado, arquivado ou bloqueado. Recarregue a tela.');

        if (!empty($env['sem_pdf']))
            throw new Exception('Faltam ' . $env['sem_pdf'] . ' PDF(s). Gere-os antes de enviar.');

        $cfg = ec_conta_efetiva();
        if (empty($cfg['user']) || empty($cfg['senha']))
            throw new Exception('A conta de envio não está configurada (Configurações › E-mail do pedido › Conta de envio).');
        if (($cfg['fonte'] ?? '') !== 'pedidos' && empty($in['aceito_conta_geral']))
            throw new Exception('CONTA_GERAL:O remetente seria ' . $cfg['user'] . ', que é a conta das cotações. '
                . 'Os fornecedores conhecem pedidos@caprem.com.br. Configure a conta dos pedidos, ou confirme para enviar assim mesmo.');

        /* ---- campos que a tela de conferencia deixou editar ----
           O destinatario e a copia podem ser ajustados na hora (o contato do fornecedor mudou, quer
           incluir o engenheiro nesta compra). Mas o que vale e o que SAIU: o livro-caixa grava o
           endereco efetivamente usado, nao o que estava configurado — senao "para quem foi este
           pedido?" teria duas respostas diferentes. */
        $paraEdit = trim((string)($in['para'] ?? ''));
        $ccEdit   = $in['cc'] ?? null;          // null = usa o configurado; array/string = substitui
        $assEdit  = trim((string)($in['assunto'] ?? ''));

        $tipo = $env['destino'] === 'obra' ? 'obra' : 'fornecedor';
        $pcs  = array_map(fn($p) => $p['numero'], $env['pedidos']);
        $c = ec_compor($pdo, (int)$env['ficha_id'], $tipo, [
            'pcs' => $pcs, 'fornecedor' => $env['forn_nome'],
            /* Sai do CÓDIGO, que a base sempre traz — não do nome, que ela quase nunca traz. */
            'sigla' => coligada_sigla((int)($env['pedidos'][0]['coligada_cod'] ?? 0)),
            'comprador' => $env['assina'],
            /* Assina QUEM ESTA ENVIANDO. O fornecedor responde para quem mandou — nao para o
               "responsavel pela obra", que pode nem estar na mesa hoje. */
            'assina_bid' => (string)($in['me'] ?? ''),
        ]);
        if (!$c) throw new Exception('não consegui montar o e-mail desta obra');
        if (!empty($c['faltando']))
            throw new Exception('A obra ' . $env['obra'] . ' ainda não tem: ' . implode(', ', $c['faltando']) . '.');

        /* Aplica as edicoes DEPOIS de compor, e valida cada endereco. Um e-mail invalido aqui
           derruba o envio inteiro — melhor do que mandar para um endereco que nao existe. */
        $paraFinal = $paraEdit !== '' ? $paraEdit : $env['para'];
        if (!filter_var($paraFinal, FILTER_VALIDATE_EMAIL))
            throw new Exception('Destinatário inválido: ' . $paraFinal);
        $trocouDestino = (strcasecmp($paraFinal, (string)$env['para']) !== 0);

        $ccFinal = $c['cc'];
        if ($ccEdit !== null) {
            $bruto = is_array($ccEdit) ? $ccEdit : preg_split('/[;,\s]+/', (string)$ccEdit);
            $ccFinal = [];
            foreach ((array)$bruto as $e) {
                $e = trim((string)$e);
                if ($e === '') continue;
                if (!filter_var($e, FILTER_VALIDATE_EMAIL)) throw new Exception('Cópia inválida: ' . $e);
                if (strcasecmp($e, $paraFinal) !== 0) $ccFinal[] = $e;
            }
            $ccFinal = array_values(array_unique($ccFinal));
        }
        $assuntoFinal = $assEdit !== '' ? $assEdit : $c['assunto'];

        $anexos = [];
        foreach ($env['pedidos'] as $p) {
            $cam = env_pdf_caminho($p['coligada_cod'], $p['numero']);
            if (!is_file($cam)) throw new Exception('o PDF do pedido ' . $p['numero'] . ' sumiu do servidor');
            $anexos[] = ['nome' => 'PC ' . str_pad($p['numero'], 6, '0', STR_PAD_LEFT) . '.pdf',
                         'mime' => 'application/pdf', 'conteudo' => file_get_contents($cam)];
        }

        $quem = (string)($in['me'] ?? ''); $quemNome = (string)($in['me_nome'] ?? '');
        $agora = date('c');
        $ins = $pdo->prepare("INSERT INTO envio_registro
            (coligada_cod,pedido_numero,destino,obra_nome,obra_ficha_id,fornecedor_cod,fornecedor_nome,
             para,cc,assunto,anexos,valor,enviado_em,enviado_por,enviado_por_nome,resultado)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'enviando')");

        // ---- 2. reserva no livro-caixa. Se o UNIQUE recusar, alguém já está enviando ou já enviou.
        $reservados = [];
        foreach ($env['pedidos'] as $p) {
            try {
                $ins->execute([$p['coligada_cod'], $p['numero'], $env['destino'], $env['obra'], (int)$env['ficha_id'],
                               $env['forn_cod'], $env['forn_nome'], $paraFinal, implode(', ', $ccFinal),
                               $assuntoFinal . ($trocouDestino ? '  [destinatário alterado no envio]' : ''),
                               implode(', ', array_map(fn($a) => $a['nome'], $anexos)),
                               (float)$p['valor'], $agora, $quem, $quemNome]);
                $reservados[] = $p;
            } catch (Throwable $e) {
                foreach ($reservados as $r)
                    $pdo->prepare("DELETE FROM envio_registro WHERE coligada_cod=? AND pedido_numero=? AND destino=? AND resultado='enviando'")
                        ->execute([$r['coligada_cod'], $r['numero'], $env['destino']]);
                throw new Exception('O pedido ' . $p['numero'] . ' já está registrado como enviado. Nada foi disparado.');
            }
        }

        // ---- 3. dispara
        $cfgS = ['host' => $cfg['host'] ?? 'mail.caprem.com.br', 'port' => (int)($cfg['port'] ?? 465),
                 'user' => $cfg['user'], 'senha' => $cfg['senha'], 'from' => $cfg['user'],
                 'from_name' => trim((string)($cfg['nome'] ?? '')) ?: 'Caprem - Suprimentos'];
        $ok = false; $erro = '';
        /* Message-ID PRÓPRIO: sem ele o servidor gera um id que nunca vemos, e na Caixa de E-mail
           o pedido fica indistinguível de um e-mail escrito à mão no webmail — some a resposta de
           "quem disparou este pedido". Com ele, o sync casa a mensagem com quem apertou o botão. */
        require_once __DIR__ . '/../includes/caixa.php';
        $msgidPed = caixa_msgid($cfgS);
        try { list($ok, $erro) = smtp_send($cfgS, $paraFinal, $assuntoFinal, $c['html'], $anexos,
                                           ['Message-ID' => $msgidPed], ['html' => true, 'cc' => $ccFinal]); }
        catch (Throwable $e) { $erro = $e->getMessage(); }

        // ---- 4. confirma, ou desfaz para o pedido voltar à fila
        foreach ($env['pedidos'] as $p) {
            if ($ok) $pdo->prepare("UPDATE envio_registro SET resultado='ok', enviado_em=? WHERE coligada_cod=? AND pedido_numero=? AND destino=?")
                         ->execute([date('c'), $p['coligada_cod'], $p['numero'], $env['destino']]);
            else $pdo->prepare("DELETE FROM envio_registro WHERE coligada_cod=? AND pedido_numero=? AND destino=? AND resultado='enviando'")
                     ->execute([$p['coligada_cod'], $p['numero'], $env['destino']]);
        }
        if ($ok) caixa_log_saida($pdo, $msgidPed, 'pedido',
            implode(', ', array_map(fn($p) => (string)$p['numero'], $env['pedidos'])),
            $quem, $quemNome, $assuntoFinal, $paraFinal);
        if (!$ok) throw new Exception('O servidor de e-mail recusou: ' . ($erro ?: 'motivo não informado') . '. Nada foi enviado e os pedidos continuam na fila.');

        echo json_encode(['ok' => true, 'para' => $paraFinal, 'cc' => count($ccFinal),
                          'pedidos' => count($env['pedidos']), 'anexos' => count($anexos),
                          'de' => $cfg['user'], 'conta' => $cfg['fonte']], JSON_UNESCAPED_UNICODE); exit;
    }

    /* Definir o marco zero é decisão de administrador e muda o que a automação enxerga. */
    if ($acao === 'marco') {
        if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error' => 'Apenas administradores.']); exit; }
        $v = trim((string)($in['data'] ?? ''));
        if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) throw new Exception('data inválida');
        $q = $pdo->prepare("UPDATE envio_config SET valor=?, updated_by=?, updated_at=? WHERE escopo='global' AND ref='' AND campo='marco_zero'");
        $q->execute([$v, (string)($in['me'] ?? ''), date('c')]);
        if (!$q->rowCount())
            $pdo->prepare("INSERT INTO envio_config (escopo,ref,campo,valor,updated_by,updated_at) VALUES ('global','','marco_zero',?,?,?)")
                ->execute([$v, (string)($in['me'] ?? ''), date('c')]);
        echo json_encode(['ok' => true, 'marco' => $v]); exit;
    }

    throw new Exception('ação inválida: ' . $acao);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
