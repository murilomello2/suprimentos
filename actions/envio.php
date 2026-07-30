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
require_once __DIR__ . '/../includes/db.php';
define('BP_LIB_ONLY', 1); require_once __DIR__ . '/busca_pedidos.php';  // bp_varrer / bp_obra_label / bp_nz
define('EC_LIB_ONLY', 1); require_once __DIR__ . '/envio_config.php';   // ec_resolver / ec_compor / ec_faltando

define('ENV_ATRASO_DIAS', 3);      // aprovado e parado há mais que isso = atrasado (regra 4)
define('ENV_JANELA_DIAS', 120);    // até onde a fila olha para trás

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

function env_dias($data) {
    $t = strtotime((string)$data); if (!$t) return null;
    return (int)floor((time() - $t) / 86400);
}

/** Mapa fornecedor_cod (CODCFO) -> e-mail do nosso cadastro. Foi o CODCFO que casou 1.160/1.160. */
function env_forn_email($pdo) {
    $m = [];
    try {
        foreach ($pdo->query("SELECT totvs_cod, nome, email FROM fornecedores WHERE totvs_cod IS NOT NULL AND totvs_cod<>''") as $f) {
            $c = ltrim(trim((string)$f['totvs_cod']), '0');
            if ($c === '' || isset($m[$c])) continue;
            $e = trim((string)$f['email']);
            $m[$c] = ['email' => $e, 'nome' => trim((string)$f['nome'])];
        }
    } catch (Throwable $e) {}
    return $m;
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

    $desde = date('Y-m-d', strtotime('-' . ENV_JANELA_DIAS . ' days'));
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

    $fila = []; $bloq = []; $atrasados = 0; $segurados = [];
    foreach ($peds as $k => $p) {
        if ($filtroObra !== '' && $p['obra'] !== $filtroObra) continue;

        $p['dias'] = env_dias($p['data']);
        $p['regulariza'] = env_sinal_regularizacao($p['obs'] . ' ' . implode(' ', $p['produtos']));

        $dec = $decisoes[$k] ?? null;
        if ($dec && $dec['decisao'] === 'segurar') {
            $p['motivo'] = $dec['motivo']; $p['por'] = $dec['por_nome'];
            $segurados[] = $p; continue;
        }
        /* Destino: quem foi marcado como regularização NUNCA vai ao fornecedor. */
        $destino = ($dec && $dec['decisao'] === 'so_obra') ? 'obra' : 'fornecedor';

        // ---- REGRA 3: já saiu? sai da fila (fica no histórico) ----
        if (isset($jaEnviado[$k][$destino])) continue;

        // ---- REGRA 2: obra tem que estar resolvida e com ficha ----
        $chaveObra = bp_nz($p['obra']);
        $f = $fichas[$chaveObra] ?? null;
        if ($p['obra'] === '' || !$f) {
            $p['bloqueio'] = 'obra'; $p['bloqueio_txt'] = $p['obra'] === ''
                ? 'O TOTVS não resolveu a obra deste pedido.'
                : 'A obra "' . $p['obra'] . '" não tem ficha no cockpit — sem ficha não há endereço de entrega.';
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
        $fm = $fornMail[$p['forn_cod']] ?? null;
        $p['para'] = $destino === 'obra' ? trim((string)($res['efetivo']['email_nf'] ?? '')) : trim((string)($fm['email'] ?? ''));
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

    return ['envelopes' => $env, 'bloqueados' => $bloq, 'segurados' => $segurados,
            'contadores' => [
                'envelopes' => count($env), 'pedidos' => count($fila),
                'atrasados' => $atrasados, 'bloqueados' => count($bloq),
                'segurados' => count($segurados),
                'valor' => array_sum(array_map(fn($e) => $e['valor'], $env)),
                'com_alerta' => count(array_filter($env, fn($e) => $e['alerta'])),
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
        if (!in_array($d, ['segurar', 'so_obra', 'liberar'], true)) throw new Exception('decisão inválida');
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

    throw new Exception('ação inválida: ' . $acao);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
