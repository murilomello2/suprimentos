<?php
/**
 * Retorna a matriz do Radar de Aquisições (JSON) para o front.
 * Junta a base (serviço + radar_item) com as datas vivas do cronograma (Supabase).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/cronograma.php';

try {
    db_seed_if_empty();
    $pdo = db();
    $obra = $pdo->query("SELECT * FROM obra WHERE id=1")->fetch();

    $rows = $pdo->query("
        SELECT s.ordem, s.nome, s.fase, s.curva, s.unidade, s.forma_contratacao,
               s.lead_dias, s.marco_cronograma, s.termos_cronograma, s.quantitativo,
               r.status, r.responsavel, r.fornecedor, r.inicio_cotacao, r.fim_cotacao,
               r.verba_estim, r.observacoes, r.validado
        FROM servico s
        JOIN radar_item r ON r.servico_id = s.id AND r.obra_id = 1
        ORDER BY s.ordem
    ")->fetchAll();

    // datas vivas do cronograma (com cache); se falhar, segue sem datas
    $tasks = [];
    $crono_erro = null;
    if (!empty($obra['cronograma_id'])) {
        try { $tasks = crono_tasks($obra['cronograma_id']); }
        catch (Exception $e) { $crono_erro = $e->getMessage(); }
    }

    $itens = [];
    $verba_total = 0;
    foreach ($rows as $r) {
        $d = $tasks ? crono_resolver($r, $tasks)
                    : ['data_necessaria'=>null,'data_gatilho'=>null,'marco_casado'=>null,'confianca'=>'cronograma indisponível'];
        $verba_total += (float)$r['verba_estim'];
        $itens[] = array_merge($r, $d);
    }

    echo json_encode([
        'obra' => $obra,
        'itens' => $itens,
        'resumo' => [
            'total' => count($itens),
            'por_status' => array_count_values(array_map(fn($i)=>$i['status'] ?: 'Não Iniciado', $itens)),
            'verba_total' => $verba_total,
            'crono_erro' => $crono_erro,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
