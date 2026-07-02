<?php
/**
 * Ferramenta admin protegida por ?key=. Modo ?do=reset_curada zera SÓ a flag verba_curada
 * de todos os itens da obra 1 (re-curadoria), PRESERVANDO todas as seleções (orcamento_refs,
 * composicao_sel, verba_override, orcamento_excl, quantitativo). Auto-trava depois de rodar
 * (meta reset_curada_done) — só roda de novo com &force=1. Fora desse modo: 410 (desativado).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (($_GET['key'] ?? '') !== 'mgr_7q2fk9zp') { http_response_code(403); echo json_encode(['error' => 'forbidden']); exit; }

$mode = $_GET['do'] ?? '';

if ($mode === 'reset_curada') {
    try {
        $pdo = db();
        $cnt = function($sql) use ($pdo) { return (int)$pdo->query($sql)->fetchColumn(); };
        $before = $cnt("SELECT COUNT(*) FROM radar_item WHERE obra_id=1 AND verba_curada=1");
        // preservação (medida antes e depois — tem que bater)
        $refsB = $cnt("SELECT COUNT(*) FROM radar_item WHERE obra_id=1 AND orcamento_refs IS NOT NULL AND orcamento_refs<>'' AND orcamento_refs<>'[]'");
        $selB  = $cnt("SELECT COUNT(*) FROM radar_item WHERE obra_id=1 AND composicao_sel IS NOT NULL AND composicao_sel<>'' AND composicao_sel<>'[]'");

        $done = $pdo->query("SELECT v FROM meta WHERE k='reset_curada_done'")->fetchColumn();
        if ($done !== false && !isset($_GET['force'])) {
            echo json_encode(['ja_rodado_em' => $done, 'curadas_agora' => $before,
                'nota' => 'Já foi resetado. Use &force=1 só se quiser zerar de novo (cuidado: apaga o "curado" que você refez).'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $st = $pdo->prepare("UPDATE radar_item SET verba_curada=0 WHERE obra_id=1 AND verba_curada=1");
        $st->execute();
        $zeradas = $st->rowCount();
        $pdo->prepare("REPLACE INTO meta (k,v) VALUES ('reset_curada_done', ?)")->execute([date('c')]);

        $after = $cnt("SELECT COUNT(*) FROM radar_item WHERE obra_id=1 AND verba_curada=1");
        $refsA = $cnt("SELECT COUNT(*) FROM radar_item WHERE obra_id=1 AND orcamento_refs IS NOT NULL AND orcamento_refs<>'' AND orcamento_refs<>'[]'");
        $selA  = $cnt("SELECT COUNT(*) FROM radar_item WHERE obra_id=1 AND composicao_sel IS NOT NULL AND composicao_sel<>'' AND composicao_sel<>'[]'");

        echo json_encode([
            'ok' => true,
            'curadas_antes' => $before, 'zeradas' => $zeradas, 'curadas_depois' => $after,
            'preservado' => [
                'com_orcamento_refs' => ['antes' => $refsB, 'depois' => $refsA, 'intacto' => $refsB === $refsA],
                'com_composicao_sel' => ['antes' => $selB, 'depois' => $selA, 'intacto' => $selB === $selA],
            ],
            'nota' => 'Só a flag verba_curada foi zerada. Nenhuma seleção foi apagada.',
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage(), 'linha' => $e->getLine()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(410);
echo json_encode(['error' => 'gone', 'nota' => 'ferramenta desativada']);
