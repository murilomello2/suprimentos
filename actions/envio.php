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

define('ENV_ATRASO_DIAS', 3);      // aprovado e parado há mais que isso = atrasado (regra 4)
define('ENV_JANELA_DIAS', 120);    // até onde a fila olha para trás
define('ENV_AMOSTRA', 120);        // bloqueados enviados por motivo (a contagem vai cheia — ver env_fila)

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

/** Este servidor NÃO tem mbstring (nem PHPMailer) — nada de mb_*. bp_nz já derruba acento e caixa. */
function env_norm($s) { return ' ' . bp_nz($s) . ' '; }

/** Sinais de que o pedido é regularização de material já entregue (o caso "(lançar)"). */
function env_sinal_regularizacao($txt) {
    $t = env_norm($txt);
    foreach (['LANCAR', 'LANCAMENTO', 'JA ENTREGUE', 'JA RECEBIDO', 'REGULARIZA', 'SALDO DE',
              'COMPLEMENTO DE NF', 'MATERIAL ENTREGUE', 'ENTREGA REALIZADA', 'JA FOI ENTREGUE',
              'JA COMPRADO', 'MATERIAL JA'] as $s)
        if (strpos($t, $s) !== false) return true;
    return false;
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
 * Mapa para achar o e-mail do fornecedor. DOIS índices, nessa ordem:
 *   1) CODCFO (totvs_cod) — a chave exata; foi ela que casou 1.160/1.160 no enriquecimento.
 *   2) nome normalizado EXATO — só 330 dos nossos cadastros têm CODCFO, então sem esta segunda
 *      volta um fornecedor que a gente tem e-mail fica bloqueado à toa.
 *
 * O casamento por nome é EXATO depois de normalizar (bp_nz derruba acento, caixa e pontuação).
 * Nada de aproximado: numa tentativa anterior a normalização agressiva casou "Gerdau" com
 * "E R CONSTRUCOES". Aqui, nome ambíguo (dois cadastros com o mesmo nome normalizado e e-mails
 * diferentes) é descartado — melhor bloquear e alguém resolver do que mandar para o e-mail errado.
 */
function env_forn_email($pdo) {
    $porCod = []; $porNome = []; $ambiguo = [];
    try {
        foreach ($pdo->query("SELECT totvs_cod, nome, razao_social, email FROM fornecedores") as $f) {
            $e = trim((string)$f['email']);
            if ($e === '') continue;
            $c = ltrim(trim((string)($f['totvs_cod'] ?? '')), '0');
            if ($c !== '' && !isset($porCod[$c])) $porCod[$c] = ['email' => $e, 'nome' => trim((string)$f['nome'])];
            foreach ([$f['nome'], $f['razao_social']] as $n) {
                $k = bp_nz((string)$n);
                if ($k === '' || strlen($k) < 6) continue;
                if (isset($porNome[$k]) && strcasecmp($porNome[$k]['email'], $e) !== 0) { $ambiguo[$k] = true; continue; }
                $porNome[$k] = ['email' => $e, 'nome' => trim((string)$f['nome'])];
            }
        }
        foreach (array_keys($ambiguo) as $k) unset($porNome[$k]);
    } catch (Throwable $e) {}
    return ['cod' => $porCod, 'nome' => $porNome];
}

/** Acha o e-mail: código TOTVS primeiro, nome exato depois. */
function env_forn_acha($mapa, $cod, $nome, $razao = '') {
    $c = ltrim(trim((string)$cod), '0');
    if ($c !== '' && isset($mapa['cod'][$c])) return $mapa['cod'][$c] + ['via' => 'código TOTVS'];
    foreach ([$nome, $razao] as $n) {
        $k = bp_nz((string)$n);
        if ($k !== '' && isset($mapa['nome'][$k])) return $mapa['nome'][$k] + ['via' => 'nome exato'];
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
 * Monta a fila. Devolve ENVELOPES (= e-mails que vão sair), não pedidos soltos: o comprador manda
 * "3 anexos se for a mesma obra", então a unidade de trabalho da tela é o e-mail, não o PC.
 */
function env_fila($pdo, $filtroObra = '') {
    $mapaRazao = bp_mapa_razao($pdo);
    $fichas    = env_ficha_por_nome($pdo);
    $fornMail  = env_forn_email($pdo);

    $jaEnviado = [];
    foreach ($pdo->query("SELECT coligada_cod, pedido_numero, destino, enviado_em FROM envio_registro") as $r)
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
       . 'fornecedor_cod,fornecedor_nome,fornecedor_fantasia,produto,qtd,und,valor_total,item_observacao,'
       . 'obra_efetiva_nome,obra_efetiva_fonte,pedido_usuario,status_aprovacao,etapa_aprovacao,aprovador'
       . '&status_aprovacao=ilike.aprovado*&pedido_data=gte.' . $desde
       . '&order=pedido_data.desc,pedido_numero.desc';

    bp_varrer($q, function ($linhas) use (&$peds, $mapaRazao) {
        foreach ($linhas as $l) {
            $k = env_chave($l['coligada_cod'] ?? '', $l['pedido_numero'] ?? '');
            if (!isset($peds[$k])) {
                $peds[$k] = [
                    'chave' => $k,
                    'coligada_cod' => (string)($l['coligada_cod'] ?? ''),
                    'coligada' => (string)($l['coligada'] ?? ''),
                    'numero' => ltrim((string)($l['pedido_numero'] ?? ''), '0'),
                    'data' => (string)($l['pedido_data'] ?? ''),
                    'obra' => bp_obra_label($l['obra_efetiva_nome'] ?? '', $l['obra_efetiva_fonte'] ?? '',
                                            $mapaRazao, $l['coligada_cod'] ?? '', $l['ccusto_cod'] ?? '', $l['ccusto_nome'] ?? ''),
                    'forn_cod' => ltrim(trim((string)($l['fornecedor_cod'] ?? '')), '0'),
                    'forn_nome' => trim((string)($l['fornecedor_fantasia'] ?? '')) !== ''
                                   ? trim((string)$l['fornecedor_fantasia']) : trim((string)($l['fornecedor_nome'] ?? '')),
                    'forn_razao' => trim((string)($l['fornecedor_nome'] ?? '')),
                    'comprador' => trim((string)($l['pedido_usuario'] ?? '')),
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
        $fm = env_forn_acha($fornMail, $p['forn_cod'], $p['forn_nome'], $p['forn_razao'] ?? '');
        $p['para'] = $destino === 'obra' ? trim((string)($res['efetivo']['email_nf'] ?? '')) : trim((string)($fm['email'] ?? ''));
        $p['email_via'] = $fm['via'] ?? '';
        if ($destino === 'fornecedor' && $p['para'] === '') {
            $p['bloqueio'] = 'email';
            $p['bloqueio_txt'] = 'Não temos e-mail de ' . ($p['forn_nome'] ?: 'fornecedor') . ' (código TOTVS ' . ($p['forn_cod'] ?: '—') . ').';
            $bloq[] = $p; continue;
        }
        $p['destino'] = $destino;
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
            'pedidos' => [], 'valor' => 0.0, 'dias' => 0, 'alerta' => false,
        ];
        $env[$ek]['pedidos'][] = $p;
        $env[$ek]['valor'] += $p['valor'];
        $env[$ek]['dias'] = max($env[$ek]['dias'], (int)$p['dias']);
        if ($p['regulariza']) $env[$ek]['alerta'] = true;
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
            'marco' => $marco, 'desde' => $desde,
            'contadores' => [
                'envelopes' => count($env), 'pedidos' => count($fila),
                'atrasados' => $atrasados, 'bloqueados' => count($bloq),
                'segurados' => count($segurados),
                'valor' => array_sum(array_map(fn($e) => $e['valor'], $env)),
                'com_alerta' => count(array_filter($env, fn($e) => $e['alerta'])),
                'sede' => count(array_filter($bloq, fn($b) => $b['bloqueio'] === 'sede')),
                'arquivados' => $arquivados,
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

        if (isset($_GET['historico'])) {
            $lim = max(1, min(500, (int)($_GET['limite'] ?? 100)));
            $h = $pdo->query("SELECT * FROM envio_registro ORDER BY enviado_em DESC LIMIT $lim")->fetchAll();
            $tot = (int)$pdo->query("SELECT COUNT(*) FROM envio_registro")->fetchColumn();
            echo json_encode(['itens' => $h, 'total' => $tot], JSON_UNESCAPED_UNICODE); exit;
        }
        $r = env_fila($pdo, trim((string)($_GET['obra'] ?? '')));
        $r['atraso_dias'] = ENV_ATRASO_DIAS;
        $r['janela_dias'] = ENV_JANELA_DIAS;
        echo json_encode($r, JSON_UNESCAPED_UNICODE); exit;
    }

    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $perms = user_perms($pdo, $in['me'] ?? null);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }
    $acao = $in['acao'] ?? '';

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
        bp_varrer('select=pedido_numero,coligada_cod,ccusto_cod,ccusto_nome,obra_efetiva_nome,obra_efetiva_fonte'
                  . '&status_aprovacao=ilike.aprovado*&pedido_data=gte.' . $desde . '&pedido_data=lte.' . $ate
                  . '&order=pedido_numero.asc',
            function ($linhas) use (&$alvo, $mapaRazao, $obra, $ja) {
                foreach ($linhas as $l) {
                    $k = env_chave($l['coligada_cod'] ?? '', $l['pedido_numero'] ?? '');
                    if (isset($ja[$k]) || isset($alvo[$k])) continue;
                    if ($obra !== '') {
                        $nome = bp_obra_label($l['obra_efetiva_nome'] ?? '', $l['obra_efetiva_fonte'] ?? '',
                                              $mapaRazao, $l['coligada_cod'] ?? '', $l['ccusto_cod'] ?? '', $l['ccusto_nome'] ?? '');
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

        $cfg = @json_decode(@file_get_contents(__DIR__ . '/../data/.email.json'), true);
        if (!is_array($cfg) || empty($cfg['user']) || empty($cfg['senha']))
            throw new Exception('A conta de envio ainda nao esta configurada (Configuracoes > E-mail (disparo)).');

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
                 'from' => $cfg['user'], 'from_name' => 'Caprem - Suprimentos (teste)'];
        /* smtp_send devolve um PAR [ok, mensagem] — tratar como booleano faria todo envio parecer
           bem-sucedido, porque array nao-vazio e truthy. */
        $ok = false; $erro = '';
        try { list($ok, $erro) = smtp_send($cfgS, $para, '[TESTE] ' . $c['assunto'], $selo . $c['html'],
                                           $anexos, [], ['html' => true, 'cc' => []]); }
        catch (Throwable $e) { $erro = $e->getMessage(); }
        if (!$ok && trim((string)$erro) === '') $erro = 'o servidor de e-mail recusou o envio';

        /* De proposito NAO grava em envio_registro: o livro-caixa e so de envio real. */
        echo json_encode(['ok' => (bool)$ok, 'erro' => $erro, 'para' => $para,
                          'assunto' => '[TESTE] ' . $c['assunto'], 'anexos' => count($anexos),
                          'faltando' => $c['faltando']], JSON_UNESCAPED_UNICODE); exit;
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
