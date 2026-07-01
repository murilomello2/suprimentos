<?php
/**
 * MIGRAÇÃO one-time SQLite -> MySQL (roda no servidor, que alcança os dois).
 * Cria o schema MySQL (db_schema_mysql) e COPIA todas as tabelas do cockpit.sqlite ONLINE
 * (com a curadoria viva) para o MySQL. Idempotente: limpa (DELETE) e recopia a cada execução.
 * NÃO altera o SQLite (fonte read-only). Protegido por ?key=. REMOVER após a migração.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

if (($_GET['key'] ?? '') !== 'mgr_7q2fk9zp') { http_response_code(403); echo json_encode(['error' => 'forbidden']); exit; }
if (!defined('MYSQL_PASS')) { echo json_encode(['error' => 'secrets.php ausente (MYSQL_* indefinido)']); exit; }

$dry = isset($_GET['dry']);   // ?dry=1 só cria schema e conta a fonte, sem copiar

try {
    // FONTE: SQLite online (a curadoria de verdade). Read-only.
    $sq = new PDO('sqlite:' . DB_PATH, null, null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    // DESTINO: MySQL
    $my = new PDO('mysql:host=' . MYSQL_HOST . ';port=' . MYSQL_PORT . ';dbname=' . MYSQL_DB . ';charset=' . MYSQL_CHARSET,
        MYSQL_USER, MYSQL_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    // 1) cria o schema no MySQL (create-only, idempotente)
    db_schema_mysql($my);

    // colunas reais de cada tabela no MySQL (pra copiar só a interseção e detectar mismatch)
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
            $my->exec("DELETE FROM `$t`");   // idempotência (DELETE liberado)
            if ($rows) {
                $myCols = $myColsOf($t);
                $cols = array_values(array_filter(array_keys($rows[0]), function ($c) use ($myCols) { return isset($myCols[$c]); }));
                $dropped = array_values(array_diff(array_keys($rows[0]), $cols));
                if ($dropped) $entry['colunas_ignoradas'] = $dropped;   // sinaliza mismatch de schema
                $collist = implode(',', array_map(function ($c) { return "`$c`"; }, $cols));
                $ph = implode(',', array_fill(0, count($cols), '?'));
                $ins = $my->prepare("INSERT INTO `$t` ($collist) VALUES ($ph)");
                $my->beginTransaction();
                foreach ($rows as $r) {
                    $vals = [];
                    foreach ($cols as $c) $vals[] = $r[$c];
                    $ins->execute($vals);
                }
                $my->commit();
            }
        }
        $dstN = (int)$my->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $entry['dst'] = $dstN;
        $entry['ok'] = $dry ? null : ($srcN === $dstN);
        $report[$t] = $entry;
    }

    // 2) realinha o AUTO_INCREMENT das tabelas com id automático (ids foram copiados explícitos)
    if (!$dry) {
        foreach (['radar_item','composicao_insumo','historico'] as $t) {
            $mx = (int)$my->query("SELECT COALESCE(MAX(id),0) FROM `$t`")->fetchColumn();
            $my->exec("ALTER TABLE `$t` AUTO_INCREMENT = " . ($mx + 1));
        }
    }

    // 3) verificação da CURADORIA (o que não pode se perder)
    $q = function ($pdo, $sql) { return (int)$pdo->query($sql)->fetchColumn(); };
    $curSql = "SELECT COUNT(*) FROM radar_item WHERE verba_curada=1";
    $refSql = "SELECT COUNT(*) FROM radar_item WHERE orcamento_refs IS NOT NULL AND orcamento_refs<>'' AND orcamento_refs<>'[]'";
    $selSql = "SELECT COUNT(*) FROM radar_item WHERE composicao_sel IS NOT NULL AND composicao_sel<>'' AND composicao_sel<>'[]'";
    $verba = "SELECT ROUND(SUM(CASE WHEN verba_override IS NOT NULL THEN verba_override ELSE verba_estim END)) FROM radar_item";
    $curadoria = [
        'verba_curada'      => ['src' => $q($sq, $curSql), 'dst' => $dry ? null : $q($my, $curSql)],
        'com_orcamento_refs'=> ['src' => $q($sq, $refSql), 'dst' => $dry ? null : $q($my, $refSql)],
        'com_composicao_sel'=> ['src' => $q($sq, $selSql), 'dst' => $dry ? null : $q($my, $selSql)],
        'soma_verba'        => ['src' => $q($sq, $verba),  'dst' => $dry ? null : $q($my, $verba)],
    ];

    $allOk = true;
    foreach ($report as $r) if ($r['ok'] === false) $allOk = false;

    echo json_encode([
        'ok' => $dry ? 'dry-run' : $allOk,
        'driver_atual' => defined('DB_DRIVER') ? DB_DRIVER : 'sqlite',
        'tabelas' => $report,
        'curadoria' => $curadoria,
        'nota' => 'Fonte SQLite intacta. Para virar a chave, defina DB_DRIVER=mysql em includes/secrets.php e faça deploy.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'linha' => $e->getLine(), 'arquivo' => basename($e->getFile())], JSON_UNESCAPED_UNICODE);
}
