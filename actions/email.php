<?php
/**
 * E-MAIL DE COTAÇÃO — Fase 2: COMPOSITOR (monta o corpo humanizado por cotação; NÃO envia).
 * GET ?compor=<cotacao_id>&me=..  -> {assunto, corpo, remetente, destinatarios[{fornecedor_nome,email,tem_email}], tem_carta, variante, configurada}
 * O disparo real (SMTP) e a leitura de respostas (IMAP) são fases seguintes — a credencial fica SÓ no servidor.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
define('EMAIL_CFG_FILE', __DIR__ . '/../data/.email.json');
function email_cfg() { $j = @json_decode(@file_get_contents(EMAIL_CFG_FILE), true); return is_array($j) ? $j : []; }

// telefone da assinatura por comprador (o Murilo passou; casar pelo nome do usuário logado)
function email_fone($nome) {
    $n = strtolower(trim((string)$nome));   // byte-based (o prod não tem mbstring); as chaves cobrem variantes com/sem acento
    $map = [
        'anselmo' => '(19) 99331-1588', 'gabriel borges' => '(19) 97413-3339', 'gabriel souza' => '(19) 97413-3339',
        'gabriel machado' => '(19) 99688-8181', 'paloma' => '(19) 97118-8464', 'natalia' => '(19) 99816-7057',
        'natália' => '(19) 99816-7057', 'alex' => '(19) 99789-3994', 'joão nogueira' => '(19) 98802-9682', 'joao nogueira' => '(19) 98802-9682',
    ];
    foreach ($map as $k => $v) if (strpos($n, $k) !== false) return $v;
    return '';
}
function email_qtd($q) { if ($q === null || $q === '') return ''; return rtrim(rtrim(number_format((float)$q, 2, ',', '.'), '0'), ','); }

try {
    $pdo = db();
    $meGet = $_GET['me'] ?? null;
    $perms = user_perms($pdo, $meGet);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }   // POST autentica pelo corpo (me no JSON)

    // ---- CONFIG da conta de envio (a SENHA nunca é devolvida) ----
    if (isset($_GET['config'])) {
        $cfg = email_cfg();
        echo json_encode(['ok' => true, 'configurada' => !empty($cfg['senha']),
            'host' => $cfg['host'] ?? 'mail.capremconstrutora.com.br', 'port' => (int)($cfg['port'] ?? 465),
            'user' => $cfg['user'] ?? 'suprimentos@capremconstrutora.com.br', 'from' => $cfg['from'] ?? ($cfg['user'] ?? 'suprimentos@capremconstrutora.com.br'),
            'is_admin' => !empty($perms['perm_admin'])], JSON_UNESCAPED_UNICODE); exit;
    }

    // ---- POST: salvar config (admin) / enviar ----
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $me = $in['me'] ?? null; $perms = user_perms($pdo, $me);
        if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }
        $acao = $in['acao'] ?? '';

        if ($acao === 'config') {   // admin grava host/porta/usuário/senha (a senha vem do CAMPO do admin, nunca do código)
            if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error' => 'Só administradores configuram a conta.']); exit; }
            $cfg = email_cfg();
            if (array_key_exists('host', $in)) $cfg['host'] = trim((string)$in['host']);
            if (array_key_exists('port', $in)) $cfg['port'] = (int)$in['port'] ?: 465;
            if (array_key_exists('user', $in)) $cfg['user'] = trim((string)$in['user']);
            if (array_key_exists('from', $in)) $cfg['from'] = trim((string)$in['from']);
            if (array_key_exists('senha', $in) && trim((string)$in['senha']) !== '') $cfg['senha'] = (string)$in['senha']; // vazio mantém a atual
            $cfg['from_name'] = $cfg['from_name'] ?? 'Departamento de Suprimentos · Caprem';
            @file_put_contents(EMAIL_CFG_FILE, json_encode($cfg)); @chmod(EMAIL_CFG_FILE, 0600);
            echo json_encode(['ok' => true, 'configurada' => !empty($cfg['senha'])], JSON_UNESCAPED_UNICODE); exit;
        }

        if ($acao === 'enviar') {
            $cfg = email_cfg();
            if (empty($cfg['senha'])) { echo json_encode(['error' => 'Conta de e-mail não configurada — o admin precisa cadastrar a senha.']); exit; }
            $cid = (int)($in['cotacao_id'] ?? 0);
            $assunto = trim((string)($in['assunto'] ?? '')); $corpo = (string)($in['corpo'] ?? '');
            if ($assunto === '' || trim($corpo) === '') throw new Exception('assunto e corpo obrigatórios');
            // permissão de edição na obra da cotação
            if ($cid) { $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
                if (!can_edit_obra($perms, max(1, $obra))) { http_response_code(403); echo json_encode(['error' => 'Sem permissão de edição.']); exit; } }

            // carta em PDF (anexo __CARTA__, gerado no cliente ao "Salvar na cotação") — vai anexada em todo e-mail desta cotação
            $anexos = [];
            if ($cid) {
                $ca = $pdo->prepare("SELECT nome, arquivo, mime FROM cotacao_anexo WHERE cotacao_id=? AND fornecedor_nome='__CARTA__' ORDER BY id DESC LIMIT 1");
                $ca->execute([$cid]); $ca = $ca->fetch();
                if ($ca) { $p = __DIR__ . '/../data/anexos/' . basename((string)$ca['arquivo']);
                    if (is_file($p)) $anexos[] = ['nome' => 'Carta de cotacao.pdf', 'mime' => ($ca['mime'] ?: 'application/pdf'), 'conteudo' => file_get_contents($p)]; }
            }

            // ENVIO-TESTE: manda só para um endereço (o próprio comprador)
            $teste = trim((string)($in['teste'] ?? ''));
            if ($teste !== '') {
                [$ok, $msg] = smtp_send($cfg, $teste, '[TESTE] ' . $assunto, $corpo, $anexos);
                echo json_encode($ok ? ['ok' => true, 'msg' => 'E-mail de teste enviado para ' . $teste . ($anexos ? ' (com a carta em PDF)' : '')] : ['error' => 'Falha: ' . $msg], JSON_UNESCAPED_UNICODE); exit;
            }

            // DISPARO real: individual por fornecedor convidado com e-mail
            if (!$cid) throw new Exception('cotacao_id obrigatório');
            $cf = $pdo->prepare("SELECT cf.id, cf.fornecedor_nome, f.email AS f_email, cf.email AS s_email
                                 FROM cotacao_fornecedor cf LEFT JOIN cot_fornecedor f ON f.id=cf.fornecedor_id WHERE cf.cotacao_id=?");
            $cf->execute([$cid]); $conv = $cf->fetchAll();
            $enviados = 0; $falhas = []; $now = date('c');
            $upd = $pdo->prepare("UPDATE cotacao_fornecedor SET enviado_em=?, enviado_canal='email', enviado_por=? WHERE id=?");
            foreach ($conv as $c) {
                $em = ($c['f_email'] ?? '') !== '' ? $c['f_email'] : ($c['s_email'] ?? '');
                if (!filter_var($em, FILTER_VALIDATE_EMAIL)) { $falhas[] = $c['fornecedor_nome'] . ' (sem e-mail)'; continue; }
                [$ok, $msg] = smtp_send($cfg, $em, $assunto, $corpo, $anexos);
                if ($ok) { $upd->execute([$now, $me, (int)$c['id']]); $enviados++; }
                else $falhas[] = $c['fornecedor_nome'] . ': ' . $msg;
            }
            echo json_encode(['ok' => true, 'enviados' => $enviados, 'falhas' => $falhas], JSON_UNESCAPED_UNICODE); exit;
        }
        echo json_encode(['error' => 'ação inválida'], JSON_UNESCAPED_UNICODE); exit;
    }

    if (isset($_GET['diag'])) {   // feasibilidade do módulo de e-mail no servidor do app (admin)
        if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Só admin.']); exit; }
        $exts = ['openssl'=>extension_loaded('openssl'), 'imap'=>extension_loaded('imap'), 'mbstring'=>extension_loaded('mbstring'),
                 'curl'=>extension_loaded('curl'), 'gd'=>extension_loaded('gd'), 'zip'=>class_exists('ZipArchive')];
        $probe = function($hostport) { $e=null; $err=''; $t=microtime(true);
            $fp = @stream_socket_client($hostport, $en, $es, 6, STREAM_CLIENT_CONNECT, stream_context_create(['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]));
            if (!$fp) return ['ok'=>false, 'erro'=>$es.' '.$en];
            $banner = @fgets($fp, 256); @fclose($fp);
            return ['ok'=>true, 'ms'=>round((microtime(true)-$t)*1000), 'banner'=>trim((string)$banner)];
        };
        echo json_encode(['ok'=>true, 'extensoes'=>$exts,
            'smtp_465'=>$probe('ssl://mail.capremconstrutora.com.br:465'),
            'imap_993'=>$probe('ssl://mail.capremconstrutora.com.br:993'),
            'allow_url_fopen'=>(bool)ini_get('allow_url_fopen')], JSON_UNESCAPED_UNICODE); exit;
    }

    if (isset($_GET['compor'])) {
        $cid = (int)$_GET['compor'];
        $c = $pdo->prepare("SELECT c.*, o.nome AS obra_nome, s.nome AS servico_nome FROM cotacao c LEFT JOIN obra o ON o.id=c.obra_id LEFT JOIN servico s ON s.id=c.servico_id WHERE c.id=?");
        $c->execute([$cid]); $cot = $c->fetch();
        if (!$cot) { echo json_encode(['error' => 'cotação não encontrada']); exit; }
        $obraNome = $cot['obra_nome'];
        if (empty($obraNome) && !empty($cot['solic_coligada'])) {
            $so = $pdo->prepare("SELECT nome_comercial FROM solic_obra WHERE coligada=? AND obra_cod=?");
            $so->execute([$cot['solic_coligada'], (string)($cot['solic_obra_cod'] ?? '')]); $nc = (string)$so->fetchColumn();
            if ($nc !== '') $obraNome = $nc;
        }
        $iq = $pdo->prepare("SELECT descricao, unidade, quantidade, observacao FROM cotacao_item WHERE cotacao_id=? ORDER BY ordem, id");
        $iq->execute([$cid]); $itens = $iq->fetchAll();
        $cf = $pdo->prepare("SELECT cf.fornecedor_id, cf.fornecedor_nome, f.email AS f_email, cf.email AS s_email
                             FROM cotacao_fornecedor cf LEFT JOIN cot_fornecedor f ON f.id=cf.fornecedor_id WHERE cf.cotacao_id=? ORDER BY cf.fornecedor_nome");
        $cf->execute([$cid]);
        $dest = []; foreach ($cf->fetchAll() as $r) {
            $em = ($r['f_email'] ?? '') !== '' ? $r['f_email'] : ($r['s_email'] ?? '');
            $dest[] = ['fornecedor_nome' => $r['fornecedor_nome'], 'email' => $em, 'tem_email' => $em !== ''];
        }
        // tem_carta ⟺ existe o PDF __CARTA__ que será REALMENTE anexado (senão o corpo cita os itens, sem prometer anexo inexistente)
        $temCarta = (int)$pdo->query("SELECT COUNT(*) FROM cotacao_anexo WHERE cotacao_id=" . $cid . " AND fornecedor_nome='__CARTA__'")->fetchColumn() > 0;
        $isRadar = !empty($cot['servico_id']);
        $titulo = $cot['servico_nome'] ?: $cot['titulo'];
        $remNome = $perms['nome'] ?? ''; $remFone = email_fone($remNome);

        $assunto = 'Cotação — ' . $titulo . ($obraNome ? (' · ' . $obraNome) : '');
        $L = ['Prezado fornecedor, tudo bem?', ''];
        if ($isRadar) {
            $L[] = 'A Caprem Construtora está cotando ' . $titulo . ($obraNome ? (' para a obra ' . $obraNome) : '') . '.';
            if ($temCarta) $L[] = 'Os detalhes, o escopo e os quantitativos estão na CARTA DE COTAÇÃO em anexo.';
            else { $L[] = ''; $L[] = 'Itens a cotar:'; foreach ($itens as $it) $L[] = ' • ' . email_qtd($it['quantidade']) . ' ' . $it['unidade'] . ' — ' . $it['descricao'] . ($it['observacao'] ? (' (' . $it['observacao'] . ')') : ''); }
        } else {
            $L[] = 'A Caprem Construtora solicita a sua cotação para os itens abaixo' . ($obraNome ? (' — obra ' . $obraNome) : '') . ':'; $L[] = '';
            foreach ($itens as $it) $L[] = ' • ' . email_qtd($it['quantidade']) . ' ' . $it['unidade'] . ' — ' . $it['descricao'] . ($it['observacao'] ? (' (' . $it['observacao'] . ')') : '');
            if ($temCarta) { $L[] = ''; $L[] = 'Segue em anexo a carta de cotação com os dados da obra e demais informações.'; }
        }
        $L[] = ''; $L[] = 'Por gentileza, informe: preço unitário por item, prazo de entrega, condição de pagamento e validade da proposta.';
        $L[] = 'Qualquer dúvida, estou à disposição por e-mail ou WhatsApp.';
        $L[] = ''; $L[] = 'Atenciosamente,';
        $L[] = ($remNome ?: 'Suprimentos') . ' — Departamento de Suprimentos · Caprem Construtora';
        if ($remFone) $L[] = 'WhatsApp: ' . $remFone;
        $L[] = 'suprimentos@capremconstrutora.com.br';

        $cfg = @json_decode(@file_get_contents(__DIR__ . '/../data/.email.json'), true);
        $configurada = is_array($cfg) && !empty($cfg['senha']);
        echo json_encode(['ok' => true, 'assunto' => $assunto, 'corpo' => implode("\n", $L),
            'remetente' => 'suprimentos@capremconstrutora.com.br', 'remetente_nome' => $remNome,
            'destinatarios' => $dest, 'tem_carta' => $temCarta, 'variante' => $isRadar ? 'radar' : 'material',
            'configurada' => $configurada], JSON_UNESCAPED_UNICODE); exit;
    }

    echo json_encode(['error' => 'ação inválida'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
