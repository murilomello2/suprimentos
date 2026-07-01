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

$mode = $_GET['do'] ?? 'probe';

// ===================== MODO MIGRAÇÃO =====================
if ($mode === 'migrate') {
    $dry = isset($_GET['dry']);
    try {
        if (!function_exists('db_schema_mysql')) {
            echo json_encode(['error' => 'db.php online ainda NÃO tem db_schema_mysql (includes/ não subiu) — redeploy antes de migrar']); exit;
        }
        $sq = new PDO('sqlite:' . DB_PATH, null, null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $my = _mysqlPdo();
        db_schema_mysql($my);   // create-only, idempotente

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
        echo json_encode(['ok' => $dry ? 'dry-run' : $allOk, 'tabelas' => $report, 'curadoria' => $curadoria,
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
