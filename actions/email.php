<?php
/**
 * E-MAIL DE COTAÇÃO — Fase 2: COMPOSITOR (monta o corpo humanizado por cotação; NÃO envia).
 * GET ?compor=<cotacao_id>&me=..  -> {assunto, corpo, remetente, destinatarios[{fornecedor_nome,email,tem_email}], tem_carta, variante, configurada}
 * O disparo real (SMTP) e a leitura de respostas (IMAP) são fases seguintes — a credencial fica SÓ no servidor.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/caixa.php';
if (!function_exists('cot_pode_gerir')) { function cot_pode_gerir($pdo,$me,$cid){ $p=user_perms($pdo,$me); if(empty($p['autorizado']))return false; if(!empty($p['perm_admin'])||(($p['papel']??'')==='gerente'))return true; if($me===null||$me==='')return false; try{$r=$pdo->prepare('SELECT criado_por,colaboradores FROM cotacao WHERE id=?');$r->execute([(int)$cid]);$r=$r->fetch();}catch(Throwable $e){return false;} if(!$r)return false; if((string)($r['criado_por']??'')===(string)$me)return true; foreach((array)(json_decode((string)($r['colaboradores']??''),true)?:[]) as $b) if(trim((string)$b)===trim((string)$me))return true; return false; } }
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email_anexo.php';   // arquivos que o comprador anexa ao disparo (projeto, memorial, .zip)
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

/* ═══════════════ MODELO DO CORPO, COM CHAVES ═══════════════
   O texto do e-mail de cotação era código. Agora é configuração (Configurações › E-mail (disparo)),
   com campos entre {{chaves}} — as globais (obra, itens, comprador) já saem resolvidas na prévia, e
   as de FORNECEDOR ficam literais até o disparo, porque cada um recebe a sua. */
function email_chaves() {
    return [
        ['k' => 'fornecedor',     'd' => 'nome do fornecedor (varia a cada e-mail)',        'porforn' => 1],
        ['k' => 'contato',        'd' => 'nome de quem atende no fornecedor; sem cadastro, vira o nome da empresa', 'porforn' => 1],
        ['k' => 'item',           'd' => 'o que está sendo cotado'],
        ['k' => 'itens',          'd' => 'lista dos itens (ou o aviso da carta em anexo)'],
        ['k' => 'obra',           'd' => 'nome da obra'],
        ['k' => 'obra_endereco',  'd' => 'endereço da obra — o fornecedor precisa dele p/ calcular frete'],
        ['k' => 'obra_cnpj',      'd' => 'CNPJ da obra (faturamento)'],
        ['k' => 'comprador',      'd' => 'quem está enviando'],
        ['k' => 'comprador_fone', 'd' => 'WhatsApp de quem está enviando'],
    ];
}
function email_modelo_padrao() {
    return "Prezado {{contato}}, tudo bem?\n\n"
         . "A Caprem Construtora está cotando {{item}} para a obra {{obra}}.\n\n"
         . "Dados para faturamento e cálculo do frete:\n"
         . " • Obra: {{obra}}\n"
         . " • Endereço: {{obra_endereco}}\n"
         . " • CNPJ: {{obra_cnpj}}\n\n"
         . "{{itens}}\n\n"
         . "Por gentileza, informe: preço unitário por item, prazo de entrega, condição de pagamento e validade da proposta.\n"
         . "Qualquer dúvida, estou à disposição por e-mail ou WhatsApp.\n\n"
         . "Atenciosamente,";
}
function email_aplica($txt, $vals) {
    /* Troca as chaves e, SÓ quando um campo veio vazio, remove a linha que virou rótulo solto
       (" • CNPJ:" sem CNPJ). A remoção olha se ESTA linha tinha uma chave vazia — uma regra que
       apagasse toda linha terminada em ":" comeria cabeçalhos legítimos como "Itens a cotar:".
       Chave que não está em $vals fica LITERAL de propósito: é a vez dela no disparo, por fornecedor. */
    $out = [];
    foreach (preg_split('/\n/', (string)$txt) as $linha) {
        $vazio = false;
        $nova = preg_replace_callback('/\{\{([a-z_]+)\}\}/', function ($m) use ($vals, &$vazio) {
            if (!array_key_exists($m[1], $vals)) return $m[0];
            $v = (string)$vals[$m[1]];
            if (trim($v) === '') $vazio = true;
            return $v;
        }, $linha);
        if ($vazio && (trim($nova) === '' || preg_match('/^\s*•?\s*[^:]{0,30}:\s*$/u', $nova))) continue;
        $out[] = $nova;
    }
    return preg_replace("/\n{3,}/", "\n\n", implode("\n", $out));
}

/**
 * ENDEREÇO E CNPJ DA OBRA — o fornecedor pede os dois em toda cotação (frete e faturamento).
 * Vive em dois lugares por razões históricas: `obra_ficha` (módulo Obras, o mestre) e `solic_obra`
 * (de-para das solicitações do TOTVS). Procura na ficha primeiro, por vínculo com o radar e depois
 * por nome; cai no de-para das solicitações quando a cotação nasceu de uma SC.
 */
function email_obra_dados($pdo, $cot) {
    $r = ['endereco' => '', 'cnpj' => '', 'nome' => ''];
    $pega = function ($row) use (&$r) {
        if (!$row) return false;
        $r['endereco'] = trim((string)($row['endereco'] ?? ''));
        $r['cnpj'] = trim((string)($row['cnpj'] ?? ''));
        return ($r['endereco'] !== '' || $r['cnpj'] !== '');
    };
    try {
        if (!empty($cot['obra_id'])) {
            $q = $pdo->prepare("SELECT endereco, cnpj, cidade, estado FROM obra_ficha WHERE radar_obra_id=? LIMIT 1");
            $q->execute([(int)$cot['obra_id']]);
            if ($f = $q->fetch()) {
                $pega($f);
                if ($r['endereco'] === '' && trim((string)$f['cidade']) !== '')
                    $r['endereco'] = trim($f['cidade'] . (trim((string)$f['estado']) !== '' ? '/' . $f['estado'] : ''));
                if ($r['endereco'] !== '' || $r['cnpj'] !== '') return $r;
            }
        }
    } catch (Throwable $e) {}
    try {
        if (!empty($cot['solic_coligada'])) {
            $q = $pdo->prepare("SELECT endereco, cnpj FROM solic_obra WHERE coligada=? AND obra_cod=? LIMIT 1");
            $q->execute([(string)$cot['solic_coligada'], (string)($cot['solic_obra_cod'] ?? '')]);
            if ($pega($q->fetch())) return $r;
        }
    } catch (Throwable $e) {}
    try {
        if (!empty($cot['obra_nome'])) {
            $q = $pdo->prepare("SELECT endereco, cnpj, cidade, estado FROM obra_ficha WHERE LOWER(TRIM(nome))=LOWER(TRIM(?)) LIMIT 1");
            $q->execute([(string)$cot['obra_nome']]);
            if ($f = $q->fetch()) { $pega($f);
                if ($r['endereco'] === '' && trim((string)$f['cidade']) !== '')
                    $r['endereco'] = trim($f['cidade'] . (trim((string)$f['estado']) !== '' ? '/' . $f['estado'] : '')); }
        }
    } catch (Throwable $e) {}
    return $r;
}

/**
 * ASSINATURA de quem está disparando. Imagem PRÓPRIA (a que o comprador subiu em Configurações ›
 * E-mail do pedido › Assinaturas) vence tudo e vai EMBUTIDA por cid — imagem por URL é bloqueada
 * por padrão no Outlook/Gmail e a assinatura sumiria. Sem imagem, monta o bloco de texto de sempre.
 * -> ['html' => ..., 'anexo' => null|['nome','mime','conteudo','cid']]
 */
function email_assinatura($pdo, $bid, $nomeFallback) {
    $eh = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $bid = preg_replace('/\D+/', '', (string)$bid);
    $nome = trim((string)$nomeFallback); $cargo = ''; $fone = '';
    if ($bid !== '') {
        try {
            $q = $pdo->prepare("SELECT campo, valor FROM envio_config WHERE escopo='assinatura' AND ref=?");
            $q->execute([$bid]);
            foreach ($q as $x) {
                $v = trim((string)$x['valor']);
                if ($x['campo'] === 'nome' && $v !== '') $nome = $v;
                if ($x['campo'] === 'cargo') $cargo = $v;
                if ($x['campo'] === 'telefone') $fone = $v;
            }
        } catch (Throwable $e) {}
        foreach (['png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif'] as $ext => $mime) {
            $p = __DIR__ . '/../data/assinaturas/' . $bid . '.' . $ext;
            if (is_file($p) && ($bin = @file_get_contents($p)) !== false && $bin !== '') {
                return ['html' => '<p style="margin:6px 0 0"><img src="cid:assinatura" alt="' . $eh($nome)
                                . '" style="max-width:360px;height:auto;border:0"></p>',
                        'anexo' => ['nome' => 'assinatura.' . $ext, 'mime' => $mime, 'conteudo' => $bin, 'cid' => 'assinatura']];
            }
        }
    }
    if ($fone === '') $fone = email_fone($nome);
    if ($cargo === '') $cargo = 'Departamento de Suprimentos';
    $h = '<p style="margin:6px 0 0;line-height:1.45">'
       . '<b style="font-size:14px;color:#111">' . $eh($nome ?: 'Suprimentos') . '</b><br>'
       . '<span style="font-size:12px;color:#777">' . $eh($cargo) . ' · Caprem Construtora</span>'
       . ($fone !== '' ? '<br><span style="font-size:12px;color:#333">WhatsApp: ' . $eh($fone) . '</span>' : '')
       . '<br><span style="font-size:12px;color:#333">suprimentos@capremconstrutora.com.br</span></p>';
    return ['html' => $h, 'anexo' => null];
}

/** Texto que o comprador digitou -> HTML seguro (escapa e preserva as quebras) + assinatura. */
function email_html($corpoTexto, $assinaturaHtml) {
    $b = nl2br(htmlspecialchars((string)$corpoTexto, ENT_QUOTES, 'UTF-8'), false);
    return '<div style="font-family:Segoe UI,Arial,sans-serif;font-size:14px;color:#222;line-height:1.5">'
         . $b . $assinaturaHtml . '</div>';
}

/* Serve de BIBLIOTECA para a resposta à dúvida do fornecedor (actions/inbox.php): a conta de
   envio e o telefone da assinatura têm de ser os MESMOS do disparo da carta — duas cópias um dia
   discordariam, e o fornecedor receberia resposta assinada diferente do convite. */
if (defined('EMAIL_LIB_ONLY')) return;

try {
    $pdo = db();
    $meGet = $_GET['me'] ?? null;
    $perms = user_perms($pdo, $meGet);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }   // POST autentica pelo corpo (me no JSON)

    // ---- CONFIG da conta de envio (a SENHA nunca é devolvida) ----
    if (isset($_GET['config'])) {
        $cfg = email_cfg();
        $isAdmin = !empty($perms['perm_admin']);
        // token do cron da varredura automática (Fase 4) — gera na 1ª visita do admin e guarda server-side
        if ($isAdmin && empty($cfg['cron_token'])) { $cfg['cron_token'] = bin2hex(random_bytes(16)); @file_put_contents(EMAIL_CFG_FILE, json_encode($cfg)); @chmod(EMAIL_CFG_FILE, 0600); }
        echo json_encode(['ok' => true, 'configurada' => !empty($cfg['senha']),
            'host' => $cfg['host'] ?? 'mail.capremconstrutora.com.br', 'port' => (int)($cfg['port'] ?? 465),
            'imap_port' => (int)($cfg['imap_port'] ?? 993),
            'user' => $cfg['user'] ?? 'suprimentos@capremconstrutora.com.br', 'from' => $cfg['from'] ?? ($cfg['user'] ?? 'suprimentos@capremconstrutora.com.br'),
            'is_admin' => $isAdmin, 'cron_token' => $isAdmin ? ($cfg['cron_token'] ?? '') : '',
            // modelo do corpo + o vocabulário de chaves, p/ a tela de administração
            'modelo_corpo' => (string)($cfg['modelo_corpo'] ?? ''), 'modelo_padrao' => email_modelo_padrao(),
            'chaves' => email_chaves()], JSON_UNESCAPED_UNICODE); exit;
    }

    // ---- POST: salvar config (admin) / enviar ----
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $me = $in['me'] ?? null; $perms = user_perms($pdo, $me);
        if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }
        $acao = $in['acao'] ?? '';

        /* MODELO DO CORPO (admin) — o texto do e-mail de cotação deixou de ser código. */
        if ($acao === 'modelo') {
            if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error' => 'Só administradores mudam o modelo.']); exit; }
            $cfg = email_cfg();
            $novo = (string)($in['modelo_corpo'] ?? '');
            if (strlen($novo) > 8000) throw new Exception('modelo muito longo');
            // vazio = volta ao padrão de fábrica (e não "e-mail sem corpo")
            $cfg['modelo_corpo'] = trim($novo) === '' ? '' : $novo;
            @file_put_contents(EMAIL_CFG_FILE, json_encode($cfg)); @chmod(EMAIL_CFG_FILE, 0600);
            echo json_encode(['ok' => true, 'padrao' => $cfg['modelo_corpo'] === ''], JSON_UNESCAPED_UNICODE); exit;
        }

        if ($acao === 'config') {   // admin grava host/porta/usuário/senha (a senha vem do CAMPO do admin, nunca do código)
            if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error' => 'Só administradores configuram a conta.']); exit; }
            $cfg = email_cfg();
            if (array_key_exists('host', $in)) $cfg['host'] = trim((string)$in['host']);
            if (array_key_exists('port', $in)) $cfg['port'] = (int)$in['port'] ?: 465;
            if (array_key_exists('imap_port', $in)) $cfg['imap_port'] = (int)$in['imap_port'] ?: 993;
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
            if ($cid && !cot_pode_gerir($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error' => 'Sem permissão para enviar e-mail desta cotação (só admin, gerente, quem criou ou um colaborador).']); exit; }

            // carta em PDF (anexo __CARTA__, gerado no cliente ao "Salvar na cotação") — vai anexada em todo e-mail desta cotação
            $anexos = [];
            if ($cid) {
                $ca = $pdo->prepare("SELECT nome, arquivo, mime FROM cotacao_anexo WHERE cotacao_id=? AND fornecedor_nome='__CARTA__' ORDER BY id DESC LIMIT 1");
                $ca->execute([$cid]); $ca = $ca->fetch();
                if ($ca) { $p = __DIR__ . '/../data/anexos/' . basename((string)$ca['arquivo']);
                    if (is_file($p)) $anexos[] = ['nome' => 'Carta de cotacao.pdf', 'mime' => ($ca['mime'] ?: 'application/pdf'), 'conteudo' => file_get_contents($p)]; }
                /* ARQUIVOS DO COMPRADOR (projeto em DWG, memorial, .zip de pranchas). Lidos UMA vez,
                   fora do laço: a mesma leva reusa os bytes em vez de reler o disco por fornecedor. */
                foreach (emailanx_para_envio($pdo, 'disparo', $cid) as $a) $anexos[] = $a;
            }

            // ENVIO-TESTE: manda só para um endereço (o próprio comprador)
            $teste = trim((string)($in['teste'] ?? ''));
            if ($teste !== '') {
                /* O teste tem de ser IGUAL ao disparo — mesma assinatura, mesmo HTML, mesmas chaves
                   resolvidas. Um teste em texto puro esconderia justamente o que pode sair errado. */
                $assT = email_assinatura($pdo, $me, (string)($perms['nome'] ?? ''));
                $anexosT = $anexos; if ($assT['anexo']) $anexosT[] = $assT['anexo'];
                $corpoT = email_html(email_aplica($corpo, ['fornecedor' => 'Fornecedor Exemplo Ltda', 'contato' => 'Fornecedor Exemplo Ltda']), $assT['html']);
                [$ok, $msg] = smtp_send($cfg, $teste, '[TESTE] ' . $assunto, $corpoT, $anexosT, [], ['html' => true]);
                // o teste diz QUANTOS anexos foram junto: é a única forma de conferir antes do disparo
                echo json_encode($ok ? ['ok' => true, 'msg' => 'E-mail de teste enviado para ' . $teste
                        . (count($anexos) ? ' (com ' . count($anexos) . ' anexo(s))' : '')]
                    : ['error' => 'Falha: ' . $msg], JSON_UNESCAPED_UNICODE); exit;
            }

            // DISPARO real: individual por fornecedor convidado com e-mail
            if (!$cid) throw new Exception('cotacao_id obrigatório');
            /* SÓ PARA QUEM FOI ESCOLHIDO. Antes disparava para todo convidado da cotação: quem já
               tinha recebido recebia de novo, e não havia como tirar ninguém da leva. Agora a tela
               manda a lista de cotacao_fornecedor.id, e o servidor confere que cada um é DESTA
               cotação (o id vem do navegador — nunca é palavra final). */
            $destinos = array_values(array_unique(array_map('intval', (array)($in['destinos'] ?? []))));
            $filtro = ''; $args = [$cid];
            if ($destinos) { $filtro = ' AND cf.id IN (' . implode(',', array_fill(0, count($destinos), '?')) . ')';
                             $args = array_merge($args, $destinos); }
            $cf = $pdo->prepare("SELECT cf.id, cf.fornecedor_id, cf.fornecedor_nome, cf.enviado_em,
                                        f.email AS f_email, cf.email AS s_email, f.contato AS f_contato
                                 FROM cotacao_fornecedor cf LEFT JOIN cot_fornecedor f ON f.id=cf.fornecedor_id
                                 WHERE cf.cotacao_id=?" . $filtro);
            $cf->execute($args); $conv = $cf->fetchAll();
            if ($destinos && !$conv) throw new Exception('nenhum dos fornecedores escolhidos pertence a esta cotação');

            // assinatura de QUEM está disparando (imagem própria embutida, ou o bloco de texto)
            $ass = email_assinatura($pdo, $me, (string)($perms['nome'] ?? ''));
            if ($ass['anexo']) $anexos[] = $ass['anexo'];

            $enviados = 0; $falhas = []; $resultados = []; $now = date('c');
            $upd = $pdo->prepare("UPDATE cotacao_fornecedor SET enviado_em=?, enviado_canal='email', enviado_por=? WHERE id=?");
            // grava o Message-ID de cada disparo p/ casar EXATO a resposta depois (Fase 4 inbound, via In-Reply-To/References)
            $insOut = $pdo->prepare("INSERT INTO cotacao_email_out (cotacao_id,cotacao_fornecedor_id,fornecedor_id,fornecedor_nome,email,message_id,token,assunto,enviado_em) VALUES (?,?,?,?,?,?,?,?,?)");
            foreach ($conv as $c) {
                $em = ($c['f_email'] ?? '') !== '' ? $c['f_email'] : ($c['s_email'] ?? '');
                $res = ['id' => (int)$c['id'], 'nome' => $c['fornecedor_nome'], 'email' => $em];
                if (!filter_var($em, FILTER_VALIDATE_EMAIL)) {
                    $falhas[] = $c['fornecedor_nome'] . ' (sem e-mail)';
                    $resultados[] = $res + ['ok' => false, 'erro' => $em === '' ? 'sem e-mail cadastrado' : ('e-mail inválido: ' . $em)];
                    continue;
                }
                // personaliza POR FORNECEDOR: é aqui que {{fornecedor}}/{{contato}} viram gente
                $corpoF = email_aplica($corpo, [
                    'fornecedor' => (string)$c['fornecedor_nome'],
                    'contato' => trim((string)($c['f_contato'] ?? '')) ?: (string)$c['fornecedor_nome'],
                ]);
                $corpoF = email_html($corpoF, $ass['html']);
                $token = bin2hex(random_bytes(9));
                $msgid = '<cot-' . $cid . '-' . ((int)$c['id']) . '-' . $token . '@capremconstrutora.com.br>';
                [$ok, $msg, $raw] = smtp_send($cfg, $em, $assunto, $corpoF, $anexos, ['Message-ID' => $msgid], ['html' => true]);
                $resultados[] = $res + ['ok' => (bool)$ok, 'erro' => $ok ? '' : $msg];
                if ($ok) {
                    $upd->execute([$now, $me, (int)$c['id']]); $enviados++;
                    try { $insOut->execute([$cid, (int)$c['id'], $c['fornecedor_id'] ?: null, $c['fornecedor_nome'], $em, $msgid, $token, $assunto, $now]); } catch (Throwable $e) {}
                    // espelha na Caixa de E-mail: é o que liga a mensagem na pasta Enviados a QUEM disparou
                    caixa_log_saida($pdo, $msgid, 'cotacao', (string)$cid, $me, (string)($perms['nome'] ?? ''), $assunto, $em, $cid);
                    caixa_arquivar_enviado($cfg, $raw);   // sem isto o disparo não existe na pasta Enviados da conta
                } else $falhas[] = $c['fornecedor_nome'] . ': ' . $msg;
            }
            echo json_encode(['ok' => true, 'enviados' => $enviados, 'falhas' => $falhas,
                              'resultados' => $resultados], JSON_UNESCAPED_UNICODE); exit;
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
        /* enviado_em vem junto: o Murilo disparou uma vez e na segunda a tela trouxe TODOS de novo,
           inclusive os 16 que já tinham recebido. Quem já recebeu chega desmarcado, e dá para
           desmarcar qualquer um à mão — a segunda leva vai só para quem faltou. */
        $cf = $pdo->prepare("SELECT cf.id, cf.fornecedor_id, cf.fornecedor_nome, cf.enviado_em, cf.enviado_canal,
                                    f.email AS f_email, cf.email AS s_email, f.contato AS f_contato
                             FROM cotacao_fornecedor cf LEFT JOIN cot_fornecedor f ON f.id=cf.fornecedor_id
                             WHERE cf.cotacao_id=? ORDER BY cf.fornecedor_nome");
        $cf->execute([$cid]);
        $dest = []; foreach ($cf->fetchAll() as $r) {
            $em = ($r['f_email'] ?? '') !== '' ? $r['f_email'] : ($r['s_email'] ?? '');
            $dest[] = ['id' => (int)$r['id'], 'fornecedor_nome' => $r['fornecedor_nome'], 'email' => $em,
                       'tem_email' => $em !== '', 'contato' => trim((string)($r['f_contato'] ?? '')),
                       'enviado_em' => $r['enviado_em'] ?: null];
        }
        // tem_carta ⟺ existe o PDF __CARTA__ que será REALMENTE anexado (senão o corpo cita os itens, sem prometer anexo inexistente)
        $temCarta = (int)$pdo->query("SELECT COUNT(*) FROM cotacao_anexo WHERE cotacao_id=" . $cid . " AND fornecedor_nome='__CARTA__'")->fetchColumn() > 0;
        $isRadar = !empty($cot['servico_id']);
        $titulo = $cot['servico_nome'] ?: $cot['titulo'];
        $remNome = $perms['nome'] ?? ''; $remFone = email_fone($remNome);

        $assunto = 'Cotação — ' . $titulo . ($obraNome ? (' · ' . $obraNome) : '');

        // bloco {{itens}}: com carta em PDF o corpo não repete a lista — remete ao anexo
        if ($temCarta && $isRadar) $blocoItens = 'Os detalhes, o escopo e os quantitativos estão na CARTA DE COTAÇÃO em anexo.';
        else {
            $li = ['Itens a cotar:'];
            foreach ($itens as $it) $li[] = ' • ' . email_qtd($it['quantidade']) . ' ' . $it['unidade'] . ' — ' . $it['descricao']
                                          . ($it['observacao'] ? (' (' . $it['observacao'] . ')') : '');
            if ($temCarta) { $li[] = ''; $li[] = 'Segue em anexo a carta de cotação com os dados da obra.'; }
            $blocoItens = implode("\n", $li);
        }
        $od = email_obra_dados($pdo, $cot);
        $cfg = email_cfg();
        $modelo = trim((string)($cfg['modelo_corpo'] ?? '')) ?: email_modelo_padrao();
        // as chaves GLOBAIS já saem resolvidas; {{fornecedor}} e {{contato}} ficam literais até o
        // disparo, porque são o que muda de e-mail para e-mail
        $corpo = email_aplica($modelo, [
            'item' => $titulo, 'itens' => $blocoItens, 'obra' => $obraNome ?: '',
            'obra_endereco' => $od['endereco'], 'obra_cnpj' => $od['cnpj'],
            'comprador' => $remNome ?: 'Suprimentos', 'comprador_fone' => $remFone,
        ]);
        $configurada = !empty($cfg['senha']);
        echo json_encode(['ok' => true, 'assunto' => $assunto, 'corpo' => $corpo,
            'remetente' => 'suprimentos@capremconstrutora.com.br', 'remetente_nome' => $remNome,
            'destinatarios' => $dest, 'tem_carta' => $temCarta, 'variante' => $isRadar ? 'radar' : 'material',
            'obra' => ['nome' => $obraNome, 'endereco' => $od['endereco'], 'cnpj' => $od['cnpj']],
            'chaves_forn' => array_values(array_map(fn($c) => $c['k'], array_filter(email_chaves(), fn($c) => !empty($c['porforn'])))),
            // $meGet, não $me: no GET quem carrega a identidade é o $meGet (o $me só existe no POST)
            'assinatura_img' => !empty(email_assinatura($pdo, $meGet, $remNome)['anexo']),
            'configurada' => $configurada], JSON_UNESCAPED_UNICODE); exit;
    }

    echo json_encode(['error' => 'ação inválida'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
