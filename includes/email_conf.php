<?php
/**
 * CONTA DE E-MAIL (host, portas, usuário, SENHA, cron_token) — UM lugar só, e no BANCO.
 *
 * ═══ POR QUE ESTE ARQUIVO EXISTE (17/08/2026) ═══
 * A conta morava em `data/.email.json`, e esse arquivo estava RASTREADO NO GIT. O deploy sobe a
 * pasta inteira, então a cópia LOCAL — que só tem o `cron_token`, nunca a senha — ia por cima da
 * do servidor e a senha desaparecia. O sintoma era "Conta de e-mail não configurada" logo depois
 * de cada deploy, e a suspeita caía na senha ou na porta: ninguém olha para o deploy quando o erro
 * fala de credencial. O Murilo cadastrou a senha de novo duas vezes no mesmo dia por causa disso.
 *
 * O banco é o único lugar que o deploy não alcança: o FTP não sobe MySQL, e o `cockpit.sqlite` está
 * na lista de ignorados do deploy. Então a verdade passa a ser `meta['email_cfg']`.
 *
 * O arquivo continua sendo LIDO, como herança, e é MIGRADO para o banco na primeira leitura — mas
 * nunca mais é escrito. Um deploy que traga o arquivo velho não estraga nada: o banco vence campo
 * a campo.
 */
require_once __DIR__ . '/db.php';

define('EMAIL_CONF_META', 'email_cfg');
define('EMAIL_CONF_ARQUIVO_LEGADO', __DIR__ . '/../data/.email.json');

/** Cache por requisição — a conta é lida várias vezes num mesmo disparo (mailer, IMAP, caixa). */
function &email_conf_cache() { static $c = null; return $c; }

function email_conf_get() {
    $cache = &email_conf_cache();
    if ($cache !== null) return $cache;

    $doBanco = [];
    try {
        $q = db()->prepare("SELECT v FROM meta WHERE k=?"); $q->execute([EMAIL_CONF_META]);
        $v = $q->fetchColumn();
        if ($v !== false) { $j = json_decode((string)$v, true); if (is_array($j)) $doBanco = $j; }
    } catch (Throwable $e) {}

    $doArquivo = [];
    $j = @json_decode(@file_get_contents(EMAIL_CONF_ARQUIVO_LEGADO), true);
    if (is_array($j)) $doArquivo = $j;

    /* O banco vence CAMPO A CAMPO, e só com valor de verdade: um campo vazio no banco não pode
       apagar o que o arquivo antigo ainda tem (é o que faria a migração perder a senha). */
    $vivos = array_filter($doBanco, function ($v) { return $v !== null && $v !== '' && $v !== []; });
    $cfg = array_merge($doArquivo, $vivos);
    $cache = $cfg;                                   // ANTES do set: email_conf_set() lê o cache

    // MIGRAÇÃO (uma vez): o que só existia no arquivo passa a viver no banco
    if ($doArquivo && !$doBanco) email_conf_set([]);

    return $cache;
}

/** Grava um PEDAÇO da conta (merge). Campo ausente no $patch fica como está — vazio não apaga. */
function email_conf_set($patch) {
    $cache = &email_conf_cache();
    $atual = ($cache !== null) ? $cache : email_conf_get();
    $novo = array_merge($atual, is_array($patch) ? $patch : []);
    $cache = $novo;
    try {
        $json = json_encode($novo, JSON_UNESCAPED_UNICODE);
        $pdo = db();
        $u = $pdo->prepare("UPDATE meta SET v=? WHERE k=?"); $u->execute([$json, EMAIL_CONF_META]);
        if ($u->rowCount() === 0) {
            try { $pdo->prepare("INSERT INTO meta (k,v) VALUES (?,?)")->execute([EMAIL_CONF_META, $json]); }
            catch (Throwable $e) { $pdo->prepare("UPDATE meta SET v=? WHERE k=?")->execute([$json, EMAIL_CONF_META]); }
        }
        return true;
    } catch (Throwable $e) { return false; }
}

/** De onde a conta está vindo — para o admin ver na tela que o segredo já está a salvo do deploy. */
function email_conf_fonte() {
    try {
        $q = db()->prepare("SELECT v FROM meta WHERE k=?"); $q->execute([EMAIL_CONF_META]);
        $v = $q->fetchColumn();
        if ($v !== false) { $j = json_decode((string)$v, true); if (is_array($j) && trim((string)($j['senha'] ?? '')) !== '') return 'banco'; }
    } catch (Throwable $e) {}
    $j = @json_decode(@file_get_contents(EMAIL_CONF_ARQUIVO_LEGADO), true);
    if (is_array($j) && trim((string)($j['senha'] ?? '')) !== '') return 'arquivo';
    return 'nenhuma';
}
