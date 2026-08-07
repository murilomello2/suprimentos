<?php
/**
 * CLASSIFICAÇÃO AUTOMÁTICA DO TIPO DO ITEM (material × mão de obra × empreitada × locação).
 *
 * Por que existe: 1.657 itens do radar estavam sem tipo (86% da base). Classificar na mão é
 * inviável, e o Murilo pediu automático. A boa notícia é que a Trinity já foi curada por gente —
 * 144 nomes com tipo decidido — e as outras obras herdaram os MESMOS itens do dicionário de
 * receitas. Então o trabalho é propagar, não adivinhar.
 *
 * ORDEM DE PRECEDÊNCIA, do mais forte para o mais fraco:
 *   1. TRINITY — mesmo nome já classificado por uma pessoa. Cobre 92,9%. Não é inferência.
 *   2. DECISÃO DO MURILO — os 5 casos que ele resolveu na conversa de 06/08/2026.
 *   3. SUFIXO DO NOME — "(MAT)", "(EMP)", "(MAT + MO)", "MO para ...". O próprio nome carrega o
 *      tipo; é mais confiável que a verba, que às vezes está lançada só numa linha.
 *   4. VERBA — 100% material ou 100% MO. Só quando nada acima resolve.
 * O que sobrar fica SEM TIPO e aparece na lista, em vez de receber um chute.
 *
 * GET  ?simular&me=[&obra=N]  -> o que faria, sem gravar nada
 * POST {acao:'aplicar', me[, obra]}
 */
header('Content-Type: application/json; charset=utf-8');
set_time_limit(300);
require_once __DIR__ . '/../includes/db.php';

define('CLS_OBRA_MODELO', 1);   // Trinity

function cls_norm($s) {
    $s = strtolower(strtr((string)$s, ['Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e','Í'=>'i',
        'Ó'=>'o','Ô'=>'o','Õ'=>'o','Ú'=>'u','Ç'=>'c','á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e',
        'ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c','–'=>'-','—'=>'-']));
    $s = preg_replace('/[^a-z0-9 ]/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

/** Decisões que o Murilo tomou na conversa de 06/08/2026 — nome normalizado => [tipo, por quê]. */
function cls_decisoes() {
    return [
        'perfis para cravacao'                   => ['Material',    'decisão do Murilo (06/08)'],
        'painel dupla face cortina de contencao' => ['Material',    'decisão do Murilo (06/08)'],
        'materiais para tirantes'                => ['Material',    'decisão do Murilo (06/08)'],
        'tirantes mo para execucao'              => ['Mão de obra', 'decisão do Murilo (06/08)'],
        /* Drywall: hoje se compra o material e contrata-se a MO separada, mas só ALGUNS orçamentos
           foram corrigidos. Onde o item "(EMP)" ainda existe, o orçamento é o antigo e a verba vem
           misturada — fica Empreitada e o comprador resolve a verba. Confirmado pelos dados: a
           Trinity (corrigida) NÃO tem este item, e San Pietro já estava marcada Empreitada. */
        'fechamento drywall paredes e shaft emp' => ['Empreitada',  'orçamento não corrigido — verba ainda misturada'],
        // irmão "Transporte Vertical (Cremalheiras)" é Locação na Trinity
        'transporte vertical grua'               => ['Locação',     'irmão Cremalheiras é Locação na Trinity'],
        /* Estes dois a verba resolve na maioria das obras (100% material), mas em algumas ela está
           zerada e a regra não dispara — o item ficaria sem tipo por falta de orçamento lançado,
           não por dúvida. Bloco de concreto e graute são material em qualquer leitura. */
        'alvenaria estrutural blocos de concreto' => ['Material',   'bloco é material (a verba confirma onde existe)'],
        'graute a conferir alvenaria estrutural'  => ['Material',   'graute é material (a verba confirma onde existe)'],
    ];
}

/** Itens de teste — nunca classificar, nunca contar. */
function cls_ignorar($nomeNorm) {
    return $nomeNorm === 'teste' || strpos($nomeNorm, 'item de teste') === 0;
}

/** Sufixo/prefixo que o próprio nome carrega. -> [tipo, motivo] | null */
function cls_por_nome($nome) {
    $n = ' ' . cls_norm($nome) . ' ';
    if (strpos($n, ' mat mo ') !== false)                      return ['Material + MO', 'sufixo "(MAT + MO)" no nome'];
    if (preg_match('/ mao de obra empreitada /', $n))          return ['Empreitada',    'nome diz "Mão de Obra (Empreitada)"'];
    if (strpos($n, ' emp ') !== false)                         return ['Empreitada',    'sufixo "(EMP)" no nome'];
    if (strpos($n, ' mat ') !== false)                         return ['Material',      'sufixo "(MAT)" no nome'];
    if (preg_match('/^ (mo|m o) para /', $n))                  return ['Mão de obra',   'nome começa com "MO para"'];
    return null;
}

/** Último recurso: a verba diz de que lado o item está. */
function cls_por_verba($mat, $mo) {
    $mat = (float)$mat; $mo = (float)$mo;
    if ($mat <= 0 && $mo <= 0) return null;
    if ($mo  <= 0) return ['Material',      'toda a verba está em material'];
    if ($mat <= 0) return ['Mão de obra',   'toda a verba está em mão de obra'];
    $p = 100 * $mat / ($mat + $mo);
    if ($p >= 90) return ['Material',       sprintf('%.0f%% da verba em material', $p)];
    if ($p <= 10) return ['Mão de obra',    sprintf('%.0f%% da verba em mão de obra', 100 - $p)];
    return ['Material + MO', sprintf('verba dividida: %.0f%% material / %.0f%% MO', $p, 100 - $p)];
}

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'];
    $in = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];
    $me = $method === 'POST' ? ($in['me'] ?? null) : ($_GET['me'] ?? null);
    $perms = user_perms($pdo, $me);
    if (empty($perms['perm_admin'])) { http_response_code(403);
        echo json_encode(['error' => 'Apenas administradores.'], JSON_UNESCAPED_UNICODE); exit; }

    $aplicar = ($in['acao'] ?? '') === 'aplicar';
    $obraFiltro = (int)($in['obra'] ?? $_GET['obra'] ?? 0);

    // ── 1. o modelo: o que a Trinity já tem classificado, por NOME ──
    $mod = $pdo->prepare("SELECT COALESCE(NULLIF(r.nome_override,''), s.nome) AS nome, r.tipo
                          FROM radar_item r JOIN servico s ON s.id = r.servico_id
                          WHERE r.obra_id = ? AND r.tipo IS NOT NULL AND TRIM(r.tipo) <> ''");
    $mod->execute([CLS_OBRA_MODELO]);
    $modelo = [];
    foreach ($mod as $m) $modelo[cls_norm($m['nome'])] = trim((string)$m['tipo']);

    // ── 2. todos os itens SEM tipo ──
    $sql = "SELECT r.obra_id, r.servico_id, o.nome AS obra,
                   COALESCE(NULLIF(r.nome_override,''), s.nome) AS nome,
                   r.verba_material, r.verba_mo
            FROM radar_item r JOIN servico s ON s.id = r.servico_id
            JOIN obra o ON o.id = r.obra_id
            WHERE (r.tipo IS NULL OR TRIM(r.tipo) = '')";
    $args = [];
    if ($obraFiltro) { $sql .= " AND r.obra_id = ?"; $args[] = $obraFiltro; }
    $sql .= " ORDER BY o.nome, s.ordem";
    $q = $pdo->prepare($sql); $q->execute($args);

    $dec = cls_decisoes();
    $upd = $pdo->prepare("UPDATE radar_item SET tipo=?, updated_at=? WHERE obra_id=? AND servico_id=?");
    $res = ['ok' => true, 'aplicado' => $aplicar, 'total' => 0, 'classificados' => 0,
            'por_fonte' => [], 'por_tipo' => [], 'por_obra' => [], 'ignorados' => 0,
            'sem_solucao' => [], 'amostra' => []];

    foreach ($q as $r) {
        $nome = (string)$r['nome']; $nn = cls_norm($nome);
        if (cls_ignorar($nn)) { $res['ignorados']++; continue; }
        $res['total']++;

        $tipo = null; $fonte = null; $motivo = '';
        if (isset($modelo[$nn]))          { $tipo = $modelo[$nn];  $fonte = 'trinity';  $motivo = 'mesmo nome já classificado na Trinity'; }
        elseif (isset($dec[$nn]))         { [$tipo, $motivo] = $dec[$nn]; $fonte = 'decisao'; }
        elseif ($x = cls_por_nome($nome)) { [$tipo, $motivo] = $x; $fonte = 'nome'; }
        elseif ($x = cls_por_verba($r['verba_material'], $r['verba_mo'])) { [$tipo, $motivo] = $x; $fonte = 'verba'; }

        if ($tipo === null) {
            $k = $nome;
            if (!isset($res['sem_solucao'][$k])) $res['sem_solucao'][$k] = ['item' => $nome, 'obras' => []];
            $res['sem_solucao'][$k]['obras'][] = $r['obra'];
            continue;
        }

        $res['classificados']++;
        $res['por_fonte'][$fonte] = ($res['por_fonte'][$fonte] ?? 0) + 1;
        $res['por_tipo'][$tipo]   = ($res['por_tipo'][$tipo] ?? 0) + 1;
        $res['por_obra'][$r['obra']] = ($res['por_obra'][$r['obra']] ?? 0) + 1;
        if ($fonte !== 'trinity' && count($res['amostra']) < 40)
            $res['amostra'][] = ['obra' => $r['obra'], 'item' => $nome, 'tipo' => $tipo, 'fonte' => $fonte, 'motivo' => $motivo];

        if ($aplicar) {
            $upd->execute([$tipo, date('c'), (int)$r['obra_id'], (int)$r['servico_id']]);
            // no histórico do item, com o motivo — dá pra entender depois e dá pra desfazer
            try { log_historico($pdo, (int)$r['obra_id'], (int)$r['servico_id'], $nome, $me,
                  (string)($perms['nome'] ?? ''), 'Tipo do item', '', $tipo . ' — ' . $motivo); } catch (Throwable $e) {}
        }
    }
    $res['sem_solucao'] = array_values($res['sem_solucao']);
    arsort($res['por_tipo']); arsort($res['por_fonte']);
    echo json_encode($res, JSON_UNESCAPED_UNICODE); exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
