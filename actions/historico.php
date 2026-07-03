<?php
/**
 * Histórico de alterações (read-only).
 * GET ?ordem=<servico_id>  -> alterações daquele item (mais recentes primeiro)
 * GET                      -> últimas alterações de tudo
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();
    $ordem = isset($_GET['ordem']) ? (int)$_GET['ordem'] : 0;
    $obra  = isset($_GET['obra']) ? (int)$_GET['obra'] : 0;   // multi-obra: o mesmo serviço existe em N obras
    if ($ordem) {
        if ($obra) { $st = $pdo->prepare("SELECT * FROM historico WHERE servico_id=? AND obra_id=? ORDER BY id DESC LIMIT 300"); $st->execute([$ordem, $obra]); }
        else       { $st = $pdo->prepare("SELECT * FROM historico WHERE servico_id=? ORDER BY id DESC LIMIT 300"); $st->execute([$ordem]); }
    } else {
        // feed global de atualizações: junta o GRUPO atual do item (p/ a tela "Atualizações")
        $st = $pdo->query("SELECT h.*, s.grupo FROM historico h LEFT JOIN servico s ON s.id=h.servico_id
                           ORDER BY h.id DESC LIMIT 300");
    }
    echo json_encode(['historico' => $st->fetchAll()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
