<?php
/**
 * Ferramenta admin protegida por ?key=. Modo ?do=reimport_orc recarrega orcamento_linha a partir do
 * seed (data/seed/orcamento_trinity.json) já com os MULTIPLICADORES propagados (qtde/valor efetivos),
 * PRESERVANDO os IDs (a curadoria continua válida). Idempotente. Fora desse modo: 410 (desativado).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (($_GET['key'] ?? '') !== 'mgr_7q2fk9zp') { http_response_code(403); echo json_encode(['error' => 'forbidden']); exit; }

$mode = $_GET['do'] ?? '';

if ($mode === 'reimport_orc') {
    $pdo = null;
    try {
        $orc = json_decode(@file_get_contents(SEED_DIR . '/orcamento_trinity.json'), true);
        if (!is_array($orc) || empty($orc['linhas'])) { echo json_encode(['error' => 'seed orcamento_trinity.json inválido/ausente']); exit; }

        $pdo = db();   // no servidor = MySQL
        $cnt = function($sql) use ($pdo) { return (int)$pdo->query($sql)->fetchColumn(); };
        $sum = function($sql) use ($pdo) { return round((float)$pdo->query($sql)->fetchColumn(), 2); };
        $before   = $cnt("SELECT COUNT(*) FROM orcamento_linha");
        $totBefore = $sum("SELECT COALESCE(SUM(valor),0) FROM orcamento_linha WHERE folha=1");

        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM orcamento_linha");
        $ins = $pdo->prepare("INSERT INTO orcamento_linha (id,obra_id,codigo,parent,depth,nivel,descricao,path_str,unidade,qtde,valor,folha)
                              VALUES (?,1,?,?,?,?,?,?,?,?,?,?)");
        foreach ($orc['linhas'] as $l) {
            $ins->execute([$l['id'], $l['codigo'], $l['parent'], $l['depth'], $l['nivel'],
                           $l['descricao'], $l['path_str'], $l['unidade'], $l['qtde'], $l['valor'], $l['folha']]);
        }
        $pdo->commit();

        $after    = $cnt("SELECT COUNT(*) FROM orcamento_linha");
        $totAfter = $sum("SELECT COALESCE(SUM(valor),0) FROM orcamento_linha WHERE folha=1");
        $el = $pdo->query("SELECT id, qtde, valor, path_str FROM orcamento_linha
                           WHERE descricao LIKE 'Elevador social com porta%' AND folha=1 ORDER BY id")->fetchAll();

        echo json_encode([
            'ok' => true,
            'antes'  => ['linhas' => $before, 'total_folha' => $totBefore],
            'depois' => ['linhas' => $after,  'total_folha' => $totAfter],
            'esperado_total' => 114063134.43,
            'elevador' => $el,
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
