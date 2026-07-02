<?php
/**
 * Ferramenta admin protegida por ?key=. ?do=reimport_orc recarrega orcamento_linha do seed
 * (data/seed/orcamento_trinity.json — já com multiplicadores propagados e nós limpos),
 * PRESERVANDO os IDs. Idempotente. Fora desse modo: 410.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (($_GET['key'] ?? '') !== 'mgr_7q2fk9zp') { http_response_code(403); echo json_encode(['error' => 'forbidden']); exit; }

if (($_GET['do'] ?? '') === 'reimport_orc') {
    $pdo = null;
    try {
        $orc = json_decode(@file_get_contents(SEED_DIR . '/orcamento_trinity.json'), true);
        if (!is_array($orc) || empty($orc['linhas'])) { echo json_encode(['error' => 'seed inválido/ausente']); exit; }
        $pdo = db();
        $sum = function($sql) use ($pdo) { return round((float)$pdo->query($sql)->fetchColumn(), 2); };
        $muriloBefore = (int)$pdo->query("SELECT COUNT(*) FROM orcamento_linha WHERE path_str LIKE '%Murilo%'")->fetchColumn();
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM orcamento_linha");
        $ins = $pdo->prepare("INSERT INTO orcamento_linha (id,obra_id,codigo,parent,depth,nivel,descricao,path_str,unidade,qtde,valor,folha)
                              VALUES (?,1,?,?,?,?,?,?,?,?,?,?)");
        foreach ($orc['linhas'] as $l) {
            $ins->execute([$l['id'], $l['codigo'], $l['parent'], $l['depth'], $l['nivel'],
                           $l['descricao'], $l['path_str'], $l['unidade'], $l['qtde'], $l['valor'], $l['folha']]);
        }
        $pdo->commit();
        echo json_encode([
            'ok' => true,
            'linhas' => (int)$pdo->query("SELECT COUNT(*) FROM orcamento_linha")->fetchColumn(),
            'total_folha' => $sum("SELECT COALESCE(SUM(valor),0) FROM orcamento_linha WHERE folha=1"),
            'com_murilo_antes' => $muriloBefore,
            'com_murilo_depois' => (int)$pdo->query("SELECT COUNT(*) FROM orcamento_linha WHERE path_str LIKE '%Murilo%'")->fetchColumn(),
            'topo' => $pdo->query("SELECT descricao FROM orcamento_linha WHERE depth=1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN),
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage(), 'linha' => $e->getLine()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(410);
echo json_encode(['error' => 'gone', 'nota' => 'ferramenta administrativa desativada']);
