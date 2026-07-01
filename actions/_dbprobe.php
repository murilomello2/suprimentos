<?php
/**
 * SONDA TEMPORÁRIA de conectividade MySQL (rodar do servidor, que alcança o banco).
 * Protegida por ?key=. Reporta só estrutura (nunca a senha). REMOVER após o diagnóstico.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

if (($_GET['key'] ?? '') !== 'mgr_7q2fk9zp') { http_response_code(403); echo json_encode(['error' => 'forbidden']); exit; }
if (!defined('MYSQL_PASS')) {
    $sf = __DIR__ . '/../includes/secrets.php'; $cf = __DIR__ . '/../includes/config.php';
    $sc = @file_get_contents($sf); $cc = @file_get_contents($cf);
    echo json_encode([
        'error' => 'MYSQL_* indefinido — diagnóstico:',
        'secrets_exists' => file_exists($sf),
        'secrets_size' => $sc === false ? null : strlen($sc),
        'secrets_has_MYSQL_PASS' => $sc !== false && strpos($sc, 'MYSQL_PASS') !== false,
        'secrets_first40' => $sc === false ? null : substr($sc, 0, 40),
        'config_size' => $cc === false ? null : strlen($cc),
        'config_tem_include_secrets' => $cc !== false && strpos($cc, 'secrets.php') !== false,
        'opcache_on' => function_exists('opcache_get_status') && @opcache_get_status() ? true : false,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$out = ['pdo_mysql' => extension_loaded('pdo_mysql'), 'attempts' => []];
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
        $a['current_db'] = $p->query("SELECT DATABASE()")->fetchColumn();
        try { $a['grants'] = $p->query("SHOW GRANTS FOR CURRENT_USER()")->fetchAll(PDO::FETCH_COLUMN); }
        catch (Throwable $e) { $a['grants_err'] = $e->getMessage(); }
        $a['tables'] = $p->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        // testes de privilégio, um a um (reusa _probe_test, que pode já existir de rodada anterior)
        $t = [];
        $try = function($sql) use ($p) { try { $p->exec($sql); return 'ok'; } catch (Throwable $e) { return $e->getMessage(); } };
        $t['create']       = $try("CREATE TABLE IF NOT EXISTS _probe_test (id INT PRIMARY KEY)");
        $t['insert']       = $try("INSERT INTO _probe_test (id) VALUES (1) ON DUPLICATE KEY UPDATE id=id");
        try { $t['select'] = 'ok(' . $p->query("SELECT COUNT(*) FROM _probe_test")->fetchColumn() . ')'; } catch (Throwable $e) { $t['select'] = $e->getMessage(); }
        $t['update']       = $try("UPDATE _probe_test SET id=1 WHERE id=1");
        $t['delete']       = $try("DELETE FROM _probe_test WHERE id=999");
        $t['alter_add']    = $try("ALTER TABLE _probe_test ADD COLUMN c2 INT");   // 'Duplicate column' tb = ALTER ok
        $t['create_index'] = $try("CREATE INDEX ix_probe2 ON _probe_test (id)");
        $t['truncate']     = $try("TRUNCATE _probe_test");
        $t['drop']         = $try("DROP TABLE _probe_test");
        $a['priv_tests'] = $t;
    } catch (Throwable $e) {
        $a['connected'] = false;
        $a['err'] = $e->getMessage();
    }
    $out['attempts'][] = $a;
    if (!empty($a['connected'])) break;
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
