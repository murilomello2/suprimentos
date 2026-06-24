<?php
/**
 * Gestão dos GRUPOS do Radar (a "árvore" editável de organização).
 * Grupo é uma propriedade do catálogo (servico.grupo) + ordem (servico.grupo_ordem).
 * Não toca em radar_item — nenhum dado de curadoria é alterado aqui.
 *
 * GET                                   -> { grupos:[{grupo, ordem, n}] } (na ordem atual)
 * POST {acao:"rename", from, to}        -> renomeia um grupo inteiro
 * POST {acao:"reorder", ordem:[nomes]}  -> regrava grupo_ordem = posição (1..N) p/ cada grupo
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true);
        $acao = $in['acao'] ?? '';

        if ($acao === 'rename') {
            $from = trim($in['from'] ?? '');
            $to   = trim($in['to'] ?? '');
            if ($from === '' || $to === '') throw new Exception('from/to obrigatórios');
            // se o destino já existe (merge), herda a ordem dele p/ não deixar grupo_ordem inconsistente
            $d = $pdo->prepare("SELECT MIN(grupo_ordem) FROM servico WHERE grupo=?");
            $d->execute([$to]);
            $ord = $d->fetchColumn();
            if ($ord !== false && $ord !== null) {
                $pdo->prepare("UPDATE servico SET grupo=?, grupo_ordem=? WHERE grupo=?")->execute([$to, (int)$ord, $from]);
            } else {
                $pdo->prepare("UPDATE servico SET grupo=? WHERE grupo=?")->execute([$to, $from]);
            }
            echo json_encode(['ok'=>true]); exit;
        }

        if ($acao === 'reorder') {
            $ordem = $in['ordem'] ?? [];
            if (!is_array($ordem) || !$ordem) throw new Exception('ordem vazia');
            // anexa grupos do catálogo que não vieram na lista (evita colisão de grupo_ordem)
            $todos = $pdo->query("SELECT DISTINCT grupo FROM servico WHERE grupo IS NOT NULL AND grupo<>''")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($todos as $g) { if (!in_array($g, $ordem, true)) $ordem[] = $g; }
            $pdo->beginTransaction();
            $st = $pdo->prepare("UPDATE servico SET grupo_ordem=? WHERE grupo=?");
            $i = 0;
            foreach ($ordem as $g) { if ((string)$g === '—' || (string)$g === '') continue; $i++; $st->execute([$i, (string)$g]); }
            $pdo->commit();
            echo json_encode(['ok'=>true]); exit;
        }

        throw new Exception('ação inválida');
    }

    // GET: lista de grupos na ordem atual (com contagem)
    $gs = $pdo->query("SELECT grupo, MIN(grupo_ordem) ordem, COUNT(*) n
                       FROM servico GROUP BY grupo ORDER BY ordem, grupo")->fetchAll();
    echo json_encode(['grupos' => $gs], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
