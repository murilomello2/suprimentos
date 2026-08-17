<?php
/**
 * CONFIGURAÇÃO DO E-MAIL DE PEDIDO DE COMPRA.
 *
 * GET  ?me=..                    -> tudo (global + avisos + cidades + obras) para a tela de config
 * GET  ?resolver=<obra_ficha_id> -> o valor EFETIVO de cada campo para aquela obra, já resolvido
 * POST {acao:'salvar', escopo, ref, campos:{...}}     (ADMIN)
 * POST {acao:'aviso_salvar'|'aviso_excluir', ...}     (ADMIN)
 *
 * POR QUE QUATRO CAMADAS
 * Os e-mails que os compradores mandam hoje misturam coisas de naturezas diferentes, e tratar tudo como
 * "um texto" faria a manutenção virar copiar-e-colar (que é justamente de onde vieram os defeitos que
 * achamos: parágrafo de faturamento duplicado e link do ISSQN colado com o prefixo do Chrome).
 *
 *   1. GLOBAL   — vale para todas as obras: saudação, regras de faturamento, prazo de atraso,
 *                 horário padrão de recebimento, assinatura.
 *   2. AVISO    — texto com VIGÊNCIA, que entra e sai sozinho na data certa. É o caso do bloqueio
 *                 contábil ("emitir NF só entre os dias 1 e 24"): ninguém precisa lembrar de tirar.
 *   3. CIDADE   — vale para as obras de um município: a alíquota de ISSQN de Rio Claro, por exemplo.
 *   4. OBRA     — o que é só daquele canteiro: CNO, endereço, contatos, Maps, e-mail de NF, cópias.
 *
 * A RESOLUÇÃO é obra > cidade > global. Campo vazio na obra HERDA o de cima — é isso que permite
 * "mudar para todos" (mexe no global) e "mudar só nessa obra" (preenche na obra) sem duplicar texto.
 */
header('Content-Type: application/json; charset=utf-8');
@date_default_timezone_set('America/Sao_Paulo');   // servidor em UTC; a vigencia dos avisos e por data local
require_once __DIR__ . '/../includes/db.php';

/** Campos por escopo. O rótulo e a ajuda vão para a tela — a config se descreve sozinha. */
function ec_campos() {
    return [
        'global' => [
            'assunto_forn'   => ['Assunto (fornecedor)', 'Pedido de Compra - {sigla} - {obra} - {pcs}'],
            'assunto_obra'   => ['Assunto (obra/lançamento)', 'Pedido de Compra - {sigla} - {obra} - {pcs}'],
            'saudacao'       => ['Saudação', 'Bom dia'],
            'abertura_forn'  => ['Abertura p/ o fornecedor', 'Segue em anexo o Pedido de Compra aprovado para a obra {obra}.'],
            'abertura_obra'  => ['Abertura p/ a obra', 'Segue para controle e lançamento'],
            'combinar'       => ['Combinar entrega', 'Favor sempre combinar a entrega com a obra previamente através do contato {almox_nome}, no telefone {almox_fone}.'],
            'faturamento'    => ['Regras de faturamento', "O faturamento deverá ser exatamente igual ao pedido de compra, com os mesmos valores, CNPJ's e condições de pagamento.\nDaremos andamento no lançamento da NF somente com a via física entregue em obra no momento da entrega do material."],
            'linha_cno'      => ['Linha do CNO', 'Favor sempre incluir na nota fiscal o número de CNO da obra {cno}'],
            'copia_nf'       => ['Cópia da NF', 'Uma cópia da NF deverá sempre ser enviada para o e-mail {email_nf} e fiscal@caprem.com.br para arquivo.'],
            'horario'        => ['Horário de recebimento (padrão)', 'Segunda a Sexta-feira das 7:00h às 11:00h e das 13:00h às 16:00h.'],
            'atraso'         => ['Atraso na entrega', 'Os atrasos na entrega maiores que 02 (dois) dias da data acordada com a obra deverão ser justificados pelo fornecedor.'],
            'cc_fixo'        => ['Cópia fixa (todas as obras)', ''],
            'assinatura_img' => ['Imagem da assinatura (URL ou base64)', ''],
        ],
        'cidade' => [
            'aviso_cidade' => ['Aviso desta cidade', 'ex.: alíquota de ISSQN do município, com o link da lei'],
        ],
        /* Uma linha por USUÁRIO DO BITRIX. Quem assina é quem está enviando — não o "responsável
           pela obra". O e-mail sai da mão de alguém, e é o nome dessa pessoa que o fornecedor vai
           procurar para responder. A chave é o bitrix_id. */
        'assinatura' => [
            'nome'     => ['Nome como aparece na assinatura', 'ex.: Gabriel B. Souza'],
            'cargo'    => ['Cargo', 'Suprimentos'],
            'telefone' => ['Telefone / WhatsApp', 'ex.: (19) 97413-3339'],
            'imagem'   => ['Imagem da assinatura (URL ou upload)', 'quando houver, substitui o texto'],
        ],
        'obra' => [
            'cno'          => ['CNO da obra', ''],
            'endereco'     => ['Endereço de entrega', ''],
            'complemento'  => ['Complemento / referência', 'ex.: divisa de muro com o Condomínio X, entrada pela estrada velha'],
            'almox_nome'   => ['Contato do almoxarifado', ''],
            'almox_fone'   => ['Telefone do almoxarifado', ''],
            'eng_nome'     => ['Contato do engenheiro', ''],
            'eng_fone'     => ['Telefone do engenheiro', ''],
            'horario'      => ['Horário de recebimento (só nesta obra)', 'vazio = usa o padrão global'],
            'maps'         => ['Localização (link do Maps)', ''],
            'email_nf'     => ['E-mail de NF da obra', 'ex.: nf.cpr4@capremconstrutora.com.br'],
            'cc_obra'      => ['Cópia desta obra', 'separe por vírgula'],
        ],
    ];
}

/**
 * COLISAO DE CHAVE — o primeiro teste real revelou.
 *
 * O global tinha um campo 'cno' (o TEXTO "Favor sempre incluir ... da obra {cno}") e a obra tem um
 * campo 'cno' (o NUMERO). Como a resolucao parte do global e a obra so sobrescreve, uma obra sem CNO
 * herdava a FRASE no lugar do numero — e o e-mail saiu com a frase escrita duas vezes e um {cno}
 * literal no fim. Chave de texto e chave de valor nao podem ter o mesmo nome.
 */
function ec_migrar_cno($pdo) {
    try {
        $tem = $pdo->query("SELECT COUNT(*) FROM envio_config WHERE escopo='global' AND campo='linha_cno'")->fetchColumn();
        if (!$tem) $pdo->exec("UPDATE envio_config SET campo='linha_cno' WHERE escopo='global' AND campo='cno'");
        else $pdo->exec("DELETE FROM envio_config WHERE escopo='global' AND campo='cno'");
    } catch (Throwable $e) {}
}

function ec_schema($pdo) {
    static $ok = false; if ($ok) return; $ok = true;
    $mysql = defined('DB_DRIVER') && DB_DRIVER === 'mysql';
    try {
        if ($mysql) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS envio_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                escopo VARCHAR(16) NOT NULL, ref VARCHAR(120) NOT NULL DEFAULT '',
                campo VARCHAR(40) NOT NULL, valor MEDIUMTEXT,
                updated_by VARCHAR(64), updated_at VARCHAR(40),
                UNIQUE KEY uq_ec (escopo, ref, campo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS envio_aviso (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titulo VARCHAR(160), texto MEDIUMTEXT,
                de VARCHAR(10), ate VARCHAR(10),
                destaque VARCHAR(16) DEFAULT 'normal',
                ativo INT DEFAULT 1, updated_by VARCHAR(64), updated_at VARCHAR(40)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS envio_config (id INTEGER PRIMARY KEY AUTOINCREMENT, escopo TEXT NOT NULL, ref TEXT NOT NULL DEFAULT '', campo TEXT NOT NULL, valor TEXT, updated_by TEXT, updated_at TEXT)");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_ec ON envio_config (escopo, ref, campo)");
            $pdo->exec("CREATE TABLE IF NOT EXISTS envio_aviso (id INTEGER PRIMARY KEY AUTOINCREMENT, titulo TEXT, texto TEXT, de TEXT, ate TEXT, destaque TEXT DEFAULT 'normal', ativo INTEGER DEFAULT 1, updated_by TEXT, updated_at TEXT)");
        }
    } catch (Throwable $e) {}
    ec_migrar_cno($pdo);
}

/** Semeia o GLOBAL com o texto que os compradores já usam — a config nasce funcionando. */
function ec_seed($pdo) {
    $n = (int)$pdo->query("SELECT COUNT(*) FROM envio_config WHERE escopo='global'")->fetchColumn();
    if ($n > 0) return;
    $ins = $pdo->prepare("INSERT INTO envio_config (escopo,ref,campo,valor,updated_by,updated_at) VALUES ('global','',?,?,'seed',?)");
    foreach (ec_campos()['global'] as $k => $v) $ins->execute([$k, $v[1], date('c')]);
}

function ec_tudo($pdo) {
    /* A fila de envio resolve a config de CENTENAS de pedidos numa requisição só; sem memória isso
       viraria uma varredura da tabela por pedido. Invalida no POST (ec_esquecer). */
    static $memo = null; if ($memo !== null) return $memo;
    $out = ['global' => [], 'cidade' => [], 'obra' => [], 'assinatura' => []];
    foreach ($pdo->query("SELECT escopo, ref, campo, valor FROM envio_config") as $r) {
        if ($r['escopo'] === 'global') $out['global'][$r['campo']] = $r['valor'];
        else $out[$r['escopo']][$r['ref']][$r['campo']] = $r['valor'];
    }
    $memo = $out;
    return $out;
}

/** Valor efetivo de cada campo para UMA obra: obra > cidade > global. Vazio herda. */
function ec_resolver($pdo, $fichaId) {
    static $cache = [];
    if (isset($cache[(int)$fichaId])) return $cache[(int)$fichaId];
    $t = ec_tudo($pdo);
    $st = $pdo->prepare("SELECT id, nome, cidade, estado, coligada_nome, endereco, link_local, comprador_nome FROM obra_ficha WHERE id=? LIMIT 1");
    $st->execute([(int)$fichaId]); $o = $st->fetch();
    if (!$o) return null;
    $cid = trim((string)$o['cidade']);
    $ef = $t['global'];
    foreach (($t['cidade'][$cid] ?? []) as $k => $v) if (trim((string)$v) !== '') $ef[$k] = $v;
    /* A FICHA DA OBRA é a dona do endereço e do mapa — guardar isso em dois lugares é exatamente
       como se manda pedido pra obra errada. A config só entra quando a ENTREGA difere do cadastro
       (acontece: portaria de serviço em outra rua), e aí sobrescreve na linha de baixo. */
    if (trim((string)$o['endereco']) !== '')   $ef['endereco'] = $o['endereco'];
    if (trim((string)$o['link_local']) !== '') $ef['maps'] = $o['link_local'];
    foreach (($t['obra'][(string)$o['id']] ?? []) as $k => $v) if (trim((string)$v) !== '') $ef[$k] = $v;
    // avisos vigentes hoje
    $hoje = date('Y-m-d'); $avisos = [];
    foreach ($pdo->query("SELECT * FROM envio_aviso WHERE ativo=1 ORDER BY id") as $a) {
        if (!empty($a['de']) && $a['de'] > $hoje) continue;
        if (!empty($a['ate']) && $a['ate'] < $hoje) continue;
        $avisos[] = $a;
    }
    $cache[(int)$fichaId] = ['obra' => $o, 'efetivo' => $ef, 'avisos' => $avisos,
            'da_obra' => array_keys($t['obra'][(string)$o['id']] ?? []),
            'da_cidade' => array_keys($t['cidade'][$cid] ?? [])];
    return $cache[(int)$fichaId];
}


/**
 * CONTA DE ENVIO DOS PEDIDOS — separada da conta das cotacoes, de proposito.
 *
 * A aba "E-mail (disparo)" guarda suprimentos@capremconstrutora.com.br, que dispara COTACAO. Pedido
 * de compra sai de pedidos@caprem.com.br e sempre saiu: o fornecedor conhece esse remetente, as
 * respostas dele (confirmacao, nota, prazo) precisam voltar para a caixa certa, e o historico de
 * 3.083 e-mails de pedido esta la. Misturar as duas trocaria o remetente na cara do fornecedor.
 *
 * A senha vive num arquivo dot-prefixado em data/ (403 pelo .htaccess), nunca no banco e nunca no
 * JSON que vai para o navegador — a leitura devolve so o host, a porta, o usuario e se ha senha.
 */
define('EC_CONTA_FILE', __DIR__ . '/../data/.email_pedidos.json');

function ec_conta() {
    $j = @json_decode(@file_get_contents(EC_CONTA_FILE), true);
    return is_array($j) ? $j : [];
}

/** A conta EFETIVA do disparo de pedidos: a propria; sem ela, cai na conta geral. */
function ec_conta_efetiva() {
    $c = ec_conta();
    if (trim((string)($c['user'] ?? '')) !== '' && trim((string)($c['senha'] ?? '')) !== '') {
        $c['fonte'] = 'pedidos'; return $c;
    }
    require_once __DIR__ . '/../includes/email_conf.php';   // conta geral: no banco, não mais no arquivo do deploy
    $g = email_conf_get();
    if ($g) { $g['fonte'] = 'geral'; return $g; }
    return ['fonte' => 'nenhuma'];
}

/**
 * TESTAR A CONEXÃO — responde "por onde este servidor consegue mandar e-mail?".
 *
 * Existe porque o sintoma engana: quando a saída está bloqueada, o erro que chega é "Connection
 * timed out (110)", que soa como porta errada e manda conferir os números — que estavam certos.
 * O que decide é medir de dentro do servidor, e três medidas bastam:
 *   1. o host configurado, do jeito exato que o mailer conecta;
 *   2. o MTA local, que o bloqueio de saída quase sempre deixa passar;
 *   3. um host externo qualquer, que separa "a hospedagem não deixa sair" de "este host não responde".
 *
 * Não autentica e não envia: só abre o socket, lê o banner e desliga.
 */
function ec_testar_conexao($conta = null) {
    $c = is_array($conta) ? $conta : ec_conta_efetiva();
    $host = trim((string)($c['host'] ?? '')) ?: 'mail.caprem.com.br';
    $port = (int)($c['port'] ?? 465) ?: 465;

    $abrir = function ($tr, $h, $p, $to = 5) {
        $t0 = microtime(true);
        $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $en = 0; $es = '';
        $fp = @stream_socket_client("$tr://$h:$p", $en, $es, $to, STREAM_CLIENT_CONNECT, $ctx);
        $ms = (int)round((microtime(true) - $t0) * 1000);
        if (!$fp) return ['ok' => false, 'ms' => $ms, 'errno' => (int)$en, 'erro' => trim((string)$es)];
        stream_set_timeout($fp, $to);
        $b = @fgets($fp, 512);
        @fwrite($fp, "QUIT\r\n"); @fclose($fp);
        return ['ok' => true, 'ms' => $ms, 'banner' => substr(trim((string)$b), 0, 120) ?: null];
    };

    $t = [];
    $t['configurado'] = ['alvo' => "ssl://$host:$port", 'o_que' => 'o servidor da conta, do jeito que o envio conecta']
                      + $abrir('ssl', $host, $port);
    $t['local']       = ['alvo' => 'ssl://127.0.0.1:465', 'o_que' => 'o servidor de e-mail desta propria maquina']
                      + $abrir('ssl', '127.0.0.1', 465);
    $t['externo']     = ['alvo' => 'ssl://smtp.gmail.com:465', 'o_que' => 'referencia: a hospedagem deixa sair?']
                      + $abrir('ssl', 'smtp.gmail.com', 465);

    if (!empty($t['configurado']['ok'])) {
        $v = ['nivel' => 'ok', 'texto' => 'A conexão com ' . $host . ':' . $port . ' funciona. Se o envio ainda falhar, '
              . 'aí sim é usuário ou senha.'];
    } elseif (empty($t['externo']['ok']) && !empty($t['local']['ok'])) {
        $v = ['nivel' => 'bloqueio', 'texto' => 'Esta hospedagem bloqueia SMTP de saída: nem ' . $host . ' nem um servidor '
              . 'externo qualquer respondem, mas o servidor de e-mail desta própria máquina responde na hora. '
              . 'Não é senha nem porta — nenhum ajuste aqui resolve. É preciso liberar a saída na porta ' . $port
              . ' para o servidor de ' . $host . ', ou usar uma conta hospedada nesta mesma máquina.'];
    } elseif (empty($t['configurado']['ok']) && !empty($t['externo']['ok'])) {
        $v = ['nivel' => 'host', 'texto' => 'A saída funciona (um servidor externo respondeu), mas ' . $host . ':' . $port
              . ' não responde. O endereço do servidor está errado, ou ele está barrando esta máquina.'];
    } else {
        $v = ['nivel' => 'nada', 'texto' => 'Nenhuma conexão de e-mail funciona a partir deste servidor, nem para a própria '
              . 'máquina. Isso é a hospedagem, não a configuração.'];
    }
    return ['conta' => ['user' => (string)($c['user'] ?? ''), 'fonte' => (string)($c['fonte'] ?? ''),
                        'host' => $host, 'port' => $port],
            'testes' => array_values($t), 'veredito' => $v];
}

function ec_conta_publica() {
    $c = ec_conta(); $g = ec_conta_efetiva();
    return ['host' => $c['host'] ?? 'mail.caprem.com.br', 'port' => (int)($c['port'] ?? 465),
            'imap_port' => (int)($c['imap_port'] ?? 993), 'user' => $c['user'] ?? '',
            'nome' => $c['nome'] ?? 'Caprem - Suprimentos',
            'configurada' => (trim((string)($c['user'] ?? '')) !== '' && trim((string)($c['senha'] ?? '')) !== ''),
            'fonte' => $g['fonte'], 'fonte_user' => $g['user'] ?? ''];
}

/**
 * BLOCO DE ASSINATURA — o mesmo desenho para todos, só mudam nome e telefone.
 *
 * O Murilo mandou o modelo: nome em negrito, "Suprimentos" abaixo em cinza, telefone e site numa
 * linha, e o logo à direita separado por um filete. Montado em HTML (tabela), não como imagem
 * única: assim o telefone de cada comprador entra sem alguém ter de editar um PNG, e quem recebe
 * consegue copiar o número. O logo é a única imagem, e só entra se estiver configurado.
 */
function ec_assinatura($pdo, $bitrixId, $logoGlobal = '') {
    $bid = trim((string)$bitrixId);
    $a = [];
    if ($bid !== '') { $t = ec_tudo($pdo); $a = $t['assinatura'][$bid] ?? []; }

    /* O nome vem do cadastro de usuários (que é o do Bitrix); a configuração só sobrescreve se
       alguém quiser um nome mais curto na assinatura ("Gabriel B. Souza"). */
    $nome = trim((string)($a['nome'] ?? ''));
    $cargo = trim((string)($a['cargo'] ?? ''));
    if ($nome === '' || $cargo === '') {
        try {
            $st = $pdo->prepare("SELECT nome, cargo FROM usuario WHERE bitrix_id=? LIMIT 1");
            $st->execute([$bid]);
            if ($u = $st->fetch()) {
                if ($nome === '')  $nome  = trim((string)$u['nome']);
                if ($cargo === '') $cargo = trim((string)$u['cargo']);
            }
        } catch (Throwable $e) {}
    }
    if ($cargo === '') $cargo = 'Suprimentos';
    $fone = trim((string)($a['telefone'] ?? ''));

    /* Imagem PRÓPRIA do comprador vence tudo: se ele subiu a assinatura dele, é ela que vai. */
    $propria = trim((string)($a['imagem'] ?? ''));
    if ($propria !== '') {
        return '<p style="margin:6px 0 0"><img src="' . htmlspecialchars($propria, ENT_QUOTES, 'UTF-8')
             . '" alt="' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '" style="max-width:360px;height:auto;border:0"></p>';
    }
    $imagem = $logoGlobal;
    if ($nome === '') return '';
    $eh = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $img = trim((string)$imagem);

    $esq = '<div style="font-size:15px;font-weight:700;color:#111;line-height:1.3">' . $eh($nome) . '</div>'
         . '<div style="font-size:12px;color:#777;margin-top:1px">' . $eh($cargo) . '</div>'
         . '<div style="font-size:12px;color:#333;margin-top:8px">'
         . ($fone !== '' ? ($eh($fone) . '&nbsp;&nbsp;&nbsp;') : '')
         . '<a href="https://caprem.com.br" style="color:#333;text-decoration:none">caprem.com.br</a></div>';

    $h = '<table cellpadding="0" cellspacing="0" style="margin-top:6px;border-collapse:collapse"><tr>'
       . '<td style="padding:0 18px 0 0;vertical-align:middle">' . $esq . '</td>';
    if ($img !== '')
        $h .= '<td style="padding:0 0 0 18px;border-left:1px solid #d8d8d8;vertical-align:middle">'
            . '<img src="' . $eh($img) . '" alt="Caprem Construtora" style="max-height:56px;height:auto;border:0"></td>';
    return $h . '</tr></table>';
}

/**
 * COMPÕE o e-mail de um pedido. Vive AQUI, no servidor, e não no JavaScript da tela, de propósito:
 * a prévia que o administrador vê precisa ser literalmente o mesmo texto que o disparo vai mandar.
 * Dois compositores (um pra ver, outro pra enviar) é como nascem os defeitos que já achamos nos
 * e-mails atuais — parágrafo repetido, link quebrado, obra trocada.
 *
 * $ctx: ['pcs'=>['1234','1235'], 'fornecedor'=>'...', 'sigla'=>'CPR4', 'comprador'=>'Paloma']
 */
function ec_compor($pdo, $fichaId, $tipo, $ctx = []) {
    $r = ec_resolver($pdo, $fichaId);
    if (!$r) return null;
    $ef = $r['efetivo']; $o = $r['obra'];
    $pcs = array_values(array_filter((array)($ctx['pcs'] ?? [])));
    $vars = [
        '{obra}'       => (string)$o['nome'],
        '{sigla}'      => (string)($ctx['sigla'] ?? ''),
        '{pcs}'        => implode(' / ', $pcs),
        '{fornecedor}' => (string)($ctx['fornecedor'] ?? ''),
        '{comprador}'  => (string)($ctx['comprador'] ?? ''),
        '{cno}'        => (string)($ef['cno'] ?? ''),
        '{endereco}'   => (string)($ef['endereco'] ?? ''),
        '{complemento}'=> (string)($ef['complemento'] ?? ''),
        '{almox_nome}' => (string)($ef['almox_nome'] ?? ''),
        '{almox_fone}' => (string)($ef['almox_fone'] ?? ''),
        '{eng_nome}'   => (string)($ef['eng_nome'] ?? ''),
        '{eng_fone}'   => (string)($ef['eng_fone'] ?? ''),
        '{email_nf}'   => (string)($ef['email_nf'] ?? ''),
        '{horario}'    => (string)($ef['horario'] ?? ''),
        '{maps}'       => (string)($ef['maps'] ?? ''),
    ];
    /* Quem assina NÃO se configura aqui: é o comprador responsável pela obra, que já existe no
       de-para das solicitações. Duplicar isso significaria a Paloma sair do e-mail da Diamond
       por esquecimento de alguém mexer em duas telas. */
    if (trim((string)($ctx['comprador'] ?? '')) === '') $vars['{comprador}'] = trim((string)$o['comprador_nome']);
    $ctx['comprador'] = $vars['{comprador}'];
    $sub = function ($t) use ($vars) { return trim(strtr((string)$t, $vars)); };
    /* Uma linha só some quando o campo que ela usa está VAZIO. É o que evita mandar
       "incluir na nota o CNO da obra " sem número — falha que o texto fixo de hoje permite. */
    $linha = function ($chaveTexto, $dep) use ($ef, $sub) {
        foreach ((array)$dep as $d) if (trim((string)($ef[$d] ?? '')) === '') return '';
        return $sub($ef[$chaveTexto] ?? '');
    };
    $eh = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $p  = fn($s, $st = '') => trim((string)$s) === '' ? '' : '<p style="margin:0 0 11px' . $st . '">' . nl2br($eh($s)) . '</p>';

    $forn = ($tipo !== 'obra');
    $h  = '<div style="font-family:Aptos,Calibri,Arial,sans-serif;font-size:14px;color:#111;line-height:1.45">';
    $h .= $p($sub($ef['saudacao'] ?? '') . ',');
    $h .= $p($sub($ef[$forn ? 'abertura_forn' : 'abertura_obra'] ?? ''));

    /* Avisos vigentes vêm ANTES do corpo fixo: é o que o leitor precisa ver primeiro (bloqueio contábil). */
    foreach ($r['avisos'] as $a) {
        $forte = ($a['destaque'] === 'forte');
        $h .= '<div style="margin:0 0 12px;padding:9px 12px;border-left:4px solid ' . ($forte ? '#c0392b' : '#c9a227') . ';background:' . ($forte ? '#fdf1ef' : '#fdf9ec') . '">'
            . (trim((string)$a['titulo']) !== '' ? '<b>' . $eh($a['titulo']) . '</b><br>' : '')
            . nl2br($eh($sub($a['texto']))) . '</div>';
    }

    if ($forn) {
        $h .= $p($linha('combinar', ['almox_nome']));
        $h .= $p($sub($ef['faturamento'] ?? ''), ';font-weight:600');
        $h .= $p($linha('linha_cno', ['cno']));
        $h .= $p($linha('copia_nf', ['email_nf']));
        $h .= $p($sub($ef['atraso'] ?? ''));
    }

    $ent = [];
    if (trim((string)($ef['endereco'] ?? '')) !== '')    $ent[] = ['Endereço de entrega', $sub($ef['endereco'])];
    if (trim((string)($ef['complemento'] ?? '')) !== '') $ent[] = ['Complemento', $sub($ef['complemento'])];
    if (trim((string)($ef['horario'] ?? '')) !== '')     $ent[] = ['Horário de recebimento', $sub($ef['horario'])];
    if (trim((string)($ef['almox_nome'] ?? '')) !== '')  $ent[] = ['Almoxarifado', trim($sub($ef['almox_nome']) . ' — ' . $sub($ef['almox_fone'] ?? ''), ' —')];
    if (trim((string)($ef['eng_nome'] ?? '')) !== '')    $ent[] = ['Engenheiro', trim($sub($ef['eng_nome']) . ' — ' . $sub($ef['eng_fone'] ?? ''), ' —')];
    if ($ent) {
        $h .= '<table cellpadding="0" cellspacing="0" style="margin:0 0 12px;font-size:14px">';
        foreach ($ent as $e) $h .= '<tr><td style="padding:1px 10px 1px 0;color:#555;white-space:nowrap">' . $eh($e[0]) . ':</td><td style="padding:1px 0"><b>' . $eh($e[1]) . '</b></td></tr>';
        $h .= '</table>';
    }
    if (trim((string)($ef['maps'] ?? '')) !== '') {
        $u = trim($sub($ef['maps']));
        /* O link do ISSQN sai quebrado hoje com o prefixo do leitor de PDF do Chrome colado na frente.
           Limpar aqui é mais seguro do que confiar em quem digita. */
        $u = preg_replace('#^chrome-extension://[a-z]+/#i', '', $u);
        if ($u !== '' && !preg_match('#^https?://#i', $u)) $u = 'https://' . $u;
        $h .= '<p style="margin:0 0 12px">📍 <a href="' . $eh($u) . '" style="color:#1a6b3c">Localização da obra no mapa</a></p>';
    }
    if ($ci = trim((string)($ef['aviso_cidade'] ?? ''))) {
        $ci = preg_replace('#chrome-extension://[a-z]+/#i', '', $ci);
        $ci = preg_replace('#(https?://\S+)#i', '<a href="$1" style="color:#1a6b3c">$1</a>', $eh($ci));
        $h .= '<p style="margin:0 0 12px;font-size:13px;color:#444">' . nl2br($ci) . '</p>';
    }
    $h .= '<p style="margin:16px 0 6px">Atenciosamente,</p>';
    /* Assina QUEM ENVIA (bitrix_id de quem clicou), não o responsável pela obra. */
    $assin = ec_assinatura($pdo, (string)($ctx['assina_bid'] ?? ''), (string)($ef['assinatura_img'] ?? ''));
    if ($assin !== '') $h .= $assin;
    elseif (trim((string)($ctx['comprador'] ?? '')) !== '')
        $h .= '<p style="margin:0 0 6px"><b>' . $eh($ctx['comprador']) . '</b></p>';
    $h .= '</div>';

    $cc = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;,\s]+/',
          trim((string)($ef['cc_fixo'] ?? '')) . ' ' . trim((string)($ef['cc_obra'] ?? '')))))));
    return ['assunto' => $sub($ef[$forn ? 'assunto_forn' : 'assunto_obra'] ?? ''),
            'html' => $h, 'cc' => $cc, 'obra' => $o['nome'], 'faltando' => ec_faltando($ef)];
}

/** O que ainda falta preencher para esta obra poder receber envio automático. */
function ec_faltando($ef) {
    $f = [];
    foreach (['endereco'=>'endereço de entrega', 'cno'=>'CNO', 'almox_nome'=>'contato do almoxarifado',
              'email_nf'=>'e-mail de NF'] as $k => $r)
        if (trim((string)($ef[$k] ?? '')) === '') $f[] = $r;
    return $f;
}

/* Este arquivo é endpoint E biblioteca (o envio.php reaproveita ec_resolver/ec_compor). Quem inclui
   como biblioteca define EC_LIB_ONLY e o bloco abaixo — que responde JSON — não roda. */
if (defined('EC_LIB_ONLY')) return;

try {
    $pdo = db();
    ec_schema($pdo); ec_seed($pdo);
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($metodo === 'GET') {
        $perms = user_perms($pdo, $_GET['me'] ?? null);
        if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error'=>'Não autorizado.']); exit; }
        if (isset($_GET['conta'])) { echo json_encode(ec_conta_publica(), JSON_UNESCAPED_UNICODE); exit; }
        /* A imagem de assinatura é servida por aqui: data/ é 403 no .htaccess, e o e-mail precisa de
           uma URL pública para o <img>. Só entrega arquivo de assinatura, nada mais. */
        if (isset($_GET['assinatura'])) {
            $bid = preg_replace('/\D+/', '', (string)$_GET['assinatura']);
            foreach (['png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif'] as $e2 => $mime) {
                $cam = __DIR__ . '/../data/assinaturas/' . $bid . '.' . $e2;
                if (is_file($cam)) {
                    header_remove('Content-Type'); header('Content-Type: ' . $mime);
                    header('Cache-Control: public, max-age=86400');
                    readfile($cam); exit;
                }
            }
            http_response_code(404); echo json_encode(['error' => 'sem assinatura']); exit;
        }
        if (isset($_GET['testar_conexao'])) {
            $perms = user_perms($pdo, $_GET['me'] ?? null);
            if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error' => 'Apenas administradores.']); exit; }
            echo json_encode(ec_testar_conexao(), JSON_UNESCAPED_UNICODE); exit;
        }
        if (isset($_GET['previa'])) {
            /* A prévia da tela de Envio manda os PCs e o fornecedor DE VERDADE; a de Configurações
               não manda nada e cai no exemplo. Mesmo compositor nos dois casos. */
            $pcs = array_values(array_filter(array_map('trim', explode(',', (string)($_GET['pcs'] ?? '')))));
            $c = ec_compor($pdo, (int)$_GET['previa'], ($_GET['tipo'] ?? 'fornecedor'), [
                'pcs' => $pcs ?: ['1234', '1235'],
                'sigla' => trim((string)($_GET['sigla'] ?? 'CPR4')),
                'fornecedor' => trim((string)($_GET['fornecedor'] ?? '')) ?: 'Fornecedor Exemplo Ltda',
                'comprador' => trim((string)($_GET['comprador'] ?? '')),
                'assina_bid' => trim((string)($_GET['me'] ?? '')),
            ]);
            echo json_encode($c ?: ['error' => 'obra não encontrada'], JSON_UNESCAPED_UNICODE); exit;
        }
        if (isset($_GET['resolver'])) {
            $r = ec_resolver($pdo, (int)$_GET['resolver']);
            if (!$r) { echo json_encode(['error'=>'obra não encontrada']); exit; }
            echo json_encode($r, JSON_UNESCAPED_UNICODE); exit;
        }
        $obras = $pdo->query("SELECT id, nome, cidade, estado FROM obra_ficha ORDER BY nome")->fetchAll();
        /* Quem assina hoje: os compradores que estão na ficha das obras. É a lista que a tela mostra
           para configurar telefone — não adianta cadastrar assinatura de quem não assina obra nenhuma. */
        /* Quem pode assinar = usuário do cockpit (que vem do Bitrix). Não é "o responsável pela
           obra": o e-mail sai da mão de alguém, e é essa pessoa que o fornecedor vai procurar. */
        $assinantes = [];
        try {
            foreach ($pdo->query("SELECT bitrix_id, nome, cargo, papel FROM usuario
                                  WHERE (ativo=1 OR ativo IS NULL) ORDER BY nome") as $r)
                $assinantes[] = ['bitrix_id' => (string)$r['bitrix_id'], 'nome' => trim((string)$r['nome']),
                                 'cargo' => trim((string)$r['cargo']), 'papel' => (string)$r['papel']];
        } catch (Throwable $e) {}
        $avisos = $pdo->query("SELECT * FROM envio_aviso ORDER BY (ativo=0), id DESC")->fetchAll();
        echo json_encode(['campos'=>ec_campos(), 'config'=>ec_tudo($pdo), 'obras'=>$obras, 'assinantes'=>$assinantes,
                          'cidades'=>array_values(array_unique(array_filter(array_map(fn($o)=>trim((string)$o['cidade']), $obras)))),
                          'avisos'=>$avisos, 'hoje'=>date('Y-m-d')], JSON_UNESCAPED_UNICODE); exit;
    }

    /* Upload de imagem chega como multipart/form-data, e nesse formato php://input vem VAZIO — o
       'me' nao chegava e o admin era tratado como anonimo ("Apenas administradores"). */
    $in = !empty($_POST) ? $_POST : (json_decode(file_get_contents('php://input'), true) ?: []);
    $perms = user_perms($pdo, $in['me'] ?? null);
    if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Apenas administradores.']); exit; }
    $acao = $in['acao'] ?? ''; $now = date('c'); $quem = (string)($in['me'] ?? '');

    if ($acao === 'salvar') {
        $escopo = (string)($in['escopo'] ?? ''); $ref = trim((string)($in['ref'] ?? ''));
        if (!isset(ec_campos()[$escopo])) throw new Exception('escopo inválido');
        $ok = ec_campos()[$escopo];
        $up = $pdo->prepare("UPDATE envio_config SET valor=?, updated_by=?, updated_at=? WHERE escopo=? AND ref=? AND campo=?");
        $ins = $pdo->prepare("INSERT INTO envio_config (escopo,ref,campo,valor,updated_by,updated_at) VALUES (?,?,?,?,?,?)");
        $n = 0;
        foreach ((array)($in['campos'] ?? []) as $k => $v) {
            if (!isset($ok[$k])) continue;
            $up->execute([(string)$v, $quem, $now, $escopo, $ref, $k]);
            if (!$up->rowCount()) { try { $ins->execute([$escopo, $ref, $k, (string)$v, $quem, $now]); } catch (Throwable $e) {} }
            $n++;
        }
        echo json_encode(['ok'=>true, 'salvos'=>$n]); exit;
    }

    /* Upload da imagem de assinatura de UM usuário. Fica em data/assinaturas/ e é servida por
       este mesmo arquivo (?assinatura=<bitrix_id>), para não depender de hospedagem externa. */
    if ($acao === 'assinatura_img') {
        $bid = preg_replace('/\D+/', '', (string)($in['bitrix_id'] ?? ''));
        if ($bid === '') throw new Exception('usuário não identificado');
        if (empty($_FILES['img']['tmp_name'])) throw new Exception('nenhuma imagem recebida');
        if ((int)$_FILES['img']['size'] > 2 * 1024 * 1024) throw new Exception('imagem acima de 2 MB');
        $bin = @file_get_contents($_FILES['img']['tmp_name']);
        $tipo = @getimagesizefromstring($bin);
        if (!$tipo || !in_array($tipo[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF], true))
            throw new Exception('envie uma imagem PNG, JPG ou GIF');
        $dir = __DIR__ . '/../data/assinaturas';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $ext = $tipo[2] === IMAGETYPE_PNG ? 'png' : ($tipo[2] === IMAGETYPE_JPEG ? 'jpg' : 'gif');
        foreach (['png', 'jpg', 'gif'] as $e2) @unlink($dir . '/' . $bid . '.' . $e2);
        if (@file_put_contents($dir . '/' . $bid . '.' . $ext, $bin) === false)
            throw new Exception('não consegui gravar a imagem');
        $url = 'actions/envio_config.php?assinatura=' . $bid;
        $up = $pdo->prepare("UPDATE envio_config SET valor=?, updated_by=?, updated_at=? WHERE escopo='assinatura' AND ref=? AND campo='imagem'");
        $up->execute([$url, (string)($in['me'] ?? ''), date('c'), $bid]);
        if (!$up->rowCount())
            $pdo->prepare("INSERT INTO envio_config (escopo,ref,campo,valor,updated_by,updated_at) VALUES ('assinatura',?,'imagem',?,?,?)")
                ->execute([$bid, $url, (string)($in['me'] ?? ''), date('c')]);
        echo json_encode(['ok' => true, 'url' => $url, 'bytes' => strlen($bin)]); exit;
    }
    if ($acao === 'assinatura_img_remover') {
        $bid = preg_replace('/\D+/', '', (string)($in['bitrix_id'] ?? ''));
        foreach (['png', 'jpg', 'gif'] as $e2) @unlink(__DIR__ . '/../data/assinaturas/' . $bid . '.' . $e2);
        $pdo->prepare("DELETE FROM envio_config WHERE escopo='assinatura' AND ref=? AND campo='imagem'")->execute([$bid]);
        echo json_encode(['ok' => true]); exit;
    }

    if ($acao === 'conta') {
        $c = ec_conta();
        $c['host'] = trim((string)($in['host'] ?? '')) ?: 'mail.caprem.com.br';
        $c['port'] = (int)($in['port'] ?? 465) ?: 465;
        $c['imap_port'] = (int)($in['imap_port'] ?? 993) ?: 993;
        $c['user'] = trim((string)($in['user'] ?? ''));
        $c['nome'] = trim((string)($in['nome'] ?? '')) ?: 'Caprem - Suprimentos';
        $nova = (string)($in['senha'] ?? '');
        if ($nova !== '') $c['senha'] = $nova;          // vazio MANTEM a atual
        @file_put_contents(EC_CONTA_FILE, json_encode($c, JSON_UNESCAPED_UNICODE));
        @chmod(EC_CONTA_FILE, 0600);
        echo json_encode(['ok' => true] + ec_conta_publica()); exit;
    }

    if ($acao === 'aviso_salvar') {
        $id = (int)($in['id'] ?? 0);
        $a = [trim((string)($in['titulo'] ?? '')), (string)($in['texto'] ?? ''),
              trim((string)($in['de'] ?? '')), trim((string)($in['ate'] ?? '')),
              (($in['destaque'] ?? '') === 'forte' ? 'forte' : 'normal'),
              (int)!empty($in['ativo']), $quem, $now];
        if ($id) { $a[] = $id; $pdo->prepare("UPDATE envio_aviso SET titulo=?,texto=?,de=?,ate=?,destaque=?,ativo=?,updated_by=?,updated_at=? WHERE id=?")->execute($a); }
        else { $pdo->prepare("INSERT INTO envio_aviso (titulo,texto,de,ate,destaque,ativo,updated_by,updated_at) VALUES (?,?,?,?,?,?,?,?)")->execute($a); $id = (int)$pdo->lastInsertId(); }
        echo json_encode(['ok'=>true, 'id'=>$id]); exit;
    }
    if ($acao === 'aviso_excluir') {
        $pdo->prepare("DELETE FROM envio_aviso WHERE id=?")->execute([(int)($in['id'] ?? 0)]);
        echo json_encode(['ok'=>true]); exit;
    }
    throw new Exception('ação inválida: ' . $acao);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
