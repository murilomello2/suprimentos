<?php
/**
 * FERRAMENTA TEMPORÁRIA de migração/diagnóstico MySQL (roda no servidor, que alcança o banco).
 * Protegida por ?key=. Modos:
 *   (padrão)      → sonda de privilégios + checagem de schema/adaptador
 *   ?do=migrate   → copia o cockpit.sqlite ONLINE -> MySQL (idempotente). ?dry=1 = só schema + contagens.
 * Nunca vaza a senha. REMOVER após a migração.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (($_GET['key'] ?? '') !== 'mgr_7q2fk9zp') { http_response_code(403); echo json_encode(['error' => 'forbidden']); exit; }
if (!defined('MYSQL_PASS')) { echo json_encode(['error' => 'secrets.php ausente (MYSQL_* indefinido)']); exit; }

function _mysqlPdo() {
    return new PDO('mysql:host=' . MYSQL_HOST . ';port=' . MYSQL_PORT . ';dbname=' . MYSQL_DB . ';charset=' . MYSQL_CHARSET,
        MYSQL_USER, MYSQL_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}

/** Cria o schema MySQL. Usa db_schema_mysql() de db.php se já subiu; senão, DDL inline idêntico
 *  (o FTP às vezes não sobe includes/db.php — assim a migração roda mesmo assim). */
function _ensure_schema_mysql($my) {
    if (function_exists('db_schema_mysql')) { db_schema_mysql($my); return 'db.php'; }
    $E = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $my->exec("CREATE TABLE IF NOT EXISTS meta (k VARCHAR(191) NOT NULL, v TEXT, PRIMARY KEY (k)) $E");
    $my->exec("CREATE TABLE IF NOT EXISTS servico (
        id INT NOT NULL, ordem INT, nome VARCHAR(255) NOT NULL, slug VARCHAR(191), fase VARCHAR(120), grupo VARCHAR(191),
        grupo_ordem INT, curva VARCHAR(8), forma_contratacao VARCHAR(120), unidade VARCHAR(40),
        quantitativo TEXT, lead_dias INT, marco_cronograma VARCHAR(255),
        termos_orcamento TEXT, termos_cronograma TEXT, responsavel_padrao VARCHAR(191),
        escopo TEXT, variaveis_cotar TEXT, licoes TEXT, documentos TEXT, verba_linhas TEXT, PRIMARY KEY (id)) $E");
    $my->exec("CREATE TABLE IF NOT EXISTS obra (
        id INT NOT NULL, nome VARCHAR(255) NOT NULL, slug VARCHAR(191), codinome VARCHAR(191),
        `local` VARCHAR(255), cronograma_id VARCHAR(100), orcamento_total DOUBLE, cobertura_orcamento DOUBLE,
        PRIMARY KEY (id), UNIQUE KEY uq_obra_slug (slug)) $E");
    $my->exec("CREATE TABLE IF NOT EXISTS radar_item (
        id INT NOT NULL AUTO_INCREMENT, obra_id INT NOT NULL, servico_id INT NOT NULL, status VARCHAR(64),
        responsavel VARCHAR(191), fornecedor VARCHAR(255), inicio_cotacao VARCHAR(40), fim_cotacao VARCHAR(40),
        verba_estim DOUBLE, confianca VARCHAR(40), observacoes TEXT, validado INT DEFAULT 0, tipo VARCHAR(64),
        verba_metodo VARCHAR(40), verba_material DOUBLE, verba_mo DOUBLE, composicao_id INT, area_base DOUBLE,
        verba_override DOUBLE, lead_override INT, crono_marco_override VARCHAR(255), data_necessaria_override VARCHAR(40),
        orcamento_refs TEXT, quantitativo_valor DOUBLE, quantitativo_unidade VARCHAR(40), quantitativo_refs TEXT,
        quantitativo_fonte VARCHAR(64), updated_at VARCHAR(40), composicao_sel TEXT, verba_curada INT DEFAULT 0,
        quant_comp_sel TEXT, quant_curada INT DEFAULT 0,
        PRIMARY KEY (id), UNIQUE KEY uq_obra_servico (obra_id, servico_id)) $E");
    $my->exec("CREATE TABLE IF NOT EXISTS orcamento_linha (
        id INT NOT NULL, obra_id INT NOT NULL DEFAULT 1, codigo VARCHAR(64), parent VARCHAR(64),
        depth INT, nivel INT, descricao TEXT, path_str TEXT, unidade VARCHAR(40), qtde DOUBLE, valor DOUBLE, folha INT,
        PRIMARY KEY (id), KEY idx_orc_parent (parent)) $E");
    $my->exec("CREATE TABLE IF NOT EXISTS composicao (
        id INT NOT NULL, obra_id INT DEFAULT 1, descricao TEXT, unidade VARCHAR(40),
        qtde_total DOUBLE, rs_unit DOUBLE, rs_total DOUBLE, PRIMARY KEY (id)) $E");
    $my->exec("CREATE TABLE IF NOT EXISTS composicao_insumo (
        id INT NOT NULL AUTO_INCREMENT, composicao_id INT, descricao TEXT, unidade VARCHAR(40),
        coef DOUBLE, rs_unit DOUBLE, rs_total DOUBLE, tipo VARCHAR(40), tipo_orig VARCHAR(40),
        PRIMARY KEY (id), KEY idx_ci_comp (composicao_id)) $E");
    $my->exec("CREATE TABLE IF NOT EXISTS usuario (
        bitrix_id VARCHAR(64) NOT NULL, nome VARCHAR(191), cargo VARCHAR(191), papel VARCHAR(40),
        ver_escopo VARCHAR(16), editar_escopo VARCHAR(16), obras_ver TEXT, obras_editar TEXT, menus TEXT,
        perm_admin INT DEFAULT 0, ativo INT DEFAULT 1, updated_at VARCHAR(40),
        perm_crono INT DEFAULT 0, perm_orcamento INT DEFAULT 0, perm_quant INT DEFAULT 0, perm_dicionario INT DEFAULT 0,
        PRIMARY KEY (bitrix_id)) $E");
    $my->exec("CREATE TABLE IF NOT EXISTS historico (
        id INT NOT NULL AUTO_INCREMENT, obra_id INT, servico_id INT, item_nome VARCHAR(255),
        bitrix_id VARCHAR(64), usuario_nome VARCHAR(191), campo VARCHAR(120), valor_antes TEXT, valor_depois TEXT,
        created_at VARCHAR(40), PRIMARY KEY (id), KEY idx_hist_serv (servico_id)) $E");
    return 'inline';
}

$mode = $_GET['do'] ?? 'probe';

// ===================== MODO MIGRAÇÃO =====================
if ($mode === 'migrate') {
    $dry = isset($_GET['dry']);
    try {
        $sq = new PDO('sqlite:' . DB_PATH, null, null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $my = _mysqlPdo();
        $schema_src = _ensure_schema_mysql($my);   // db.php se subiu, senão DDL inline; create-only, idempotente

        $myColsOf = function ($t) use ($my) {
            $st = $my->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
            $st->execute([$t]);
            return array_flip($st->fetchAll(PDO::FETCH_COLUMN));
        };
        $tables = ['meta','servico','obra','radar_item','orcamento_linha','composicao','composicao_insumo','usuario','historico'];
        $report = [];
        foreach ($tables as $t) {
            $rows = $sq->query("SELECT * FROM $t")->fetchAll();
            $srcN = count($rows);
            $entry = ['src' => $srcN];
            if (!$dry) {
                $my->exec("DELETE FROM `$t`");
                if ($rows) {
                    $myCols = $myColsOf($t);
                    $cols = array_values(array_filter(array_keys($rows[0]), function ($c) use ($myCols) { return isset($myCols[$c]); }));
                    $dropped = array_values(array_diff(array_keys($rows[0]), $cols));
                    if ($dropped) $entry['colunas_ignoradas'] = $dropped;
                    $collist = implode(',', array_map(function ($c) { return "`$c`"; }, $cols));
                    $ph = implode(',', array_fill(0, count($cols), '?'));
                    $ins = $my->prepare("INSERT INTO `$t` ($collist) VALUES ($ph)");
                    $my->beginTransaction();
                    foreach ($rows as $r) { $v = []; foreach ($cols as $c) $v[] = $r[$c]; $ins->execute($v); }
                    $my->commit();
                }
            }
            $dstN = (int)$my->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            $entry['dst'] = $dstN;
            $entry['ok'] = $dry ? null : ($srcN === $dstN);
            $report[$t] = $entry;
        }
        if (!$dry) {
            foreach (['radar_item','composicao_insumo','historico'] as $t) {
                $mx = (int)$my->query("SELECT COALESCE(MAX(id),0) FROM `$t`")->fetchColumn();
                $my->exec("ALTER TABLE `$t` AUTO_INCREMENT = " . ($mx + 1));
            }
        }
        $q = function ($pdo, $sql) { return (int)$pdo->query($sql)->fetchColumn(); };
        $curSql = "SELECT COUNT(*) FROM radar_item WHERE verba_curada=1";
        $refSql = "SELECT COUNT(*) FROM radar_item WHERE orcamento_refs IS NOT NULL AND orcamento_refs<>'' AND orcamento_refs<>'[]'";
        $selSql = "SELECT COUNT(*) FROM radar_item WHERE composicao_sel IS NOT NULL AND composicao_sel<>'' AND composicao_sel<>'[]'";
        $vbSql  = "SELECT ROUND(SUM(CASE WHEN verba_override IS NOT NULL THEN verba_override ELSE verba_estim END)) FROM radar_item";
        $curadoria = [
            'verba_curada'       => ['src' => $q($sq, $curSql), 'dst' => $dry ? null : $q($my, $curSql)],
            'com_orcamento_refs' => ['src' => $q($sq, $refSql), 'dst' => $dry ? null : $q($my, $refSql)],
            'com_composicao_sel' => ['src' => $q($sq, $selSql), 'dst' => $dry ? null : $q($my, $selSql)],
            'soma_verba'         => ['src' => $q($sq, $vbSql),  'dst' => $dry ? null : $q($my, $vbSql)],
        ];
        $allOk = true; foreach ($report as $r) if ($r['ok'] === false) $allOk = false;
        echo json_encode(['ok' => $dry ? 'dry-run' : $allOk, 'schema_via' => $schema_src, 'tabelas' => $report, 'curadoria' => $curadoria,
            'nota' => 'Fonte SQLite intacta. Virar a chave = DB_DRIVER=mysql em secrets.php + deploy.'],
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage(), 'linha' => $e->getLine(), 'arquivo' => basename($e->getFile())], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ===================== MODO SONDA (padrão) =====================
$out = [
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'db_schema_mysql_existe' => function_exists('db_schema_mysql'),   // confirma que includes/db.php novo subiu
    'db_driver_atual' => defined('DB_DRIVER') ? DB_DRIVER : '(indef)',
    'attempts' => [],
];
$hosts = array_values(array_unique(['localhost', '127.0.0.1', MYSQL_HOST]));
foreach ($hosts as $h) {
    $a = ['host' => $h, 'port' => MYSQL_PORT, 'db' => MYSQL_DB];
    try {
        $dsn = "mysql:host=$h;port=" . MYSQL_PORT . ";dbname=" . MYSQL_DB . ";charset=" . MYSQL_CHARSET;
        $t0 = microtime(true);
        $p = new PDO($dsn, MYSQL_USER, MYSQL_PASS, [PDO::ATTR_TIMEOUT => 6, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $a['connected'] = true;
        $a['ms'] = round((microtime(true) - $t0) * 1000);
        $a['version'] = $p->query("SELECT VERSION()")->fetchColumn();
        try { $a['grants'] = $p->query("SHOW GRANTS FOR CURRENT_USER()")->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) { $a['grants_err'] = $e->getMessage(); }
        $a['tables'] = $p->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $a['connected'] = false; $a['err'] = $e->getMessage();
    }
    $out['attempts'][] = $a;
    if (!empty($a['connected'])) break;
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
