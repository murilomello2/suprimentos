<?php
/**
 * CONTROLE DE ACESSOS — quem entra no cockpit e quais telas usa.
 *
 * POST {acao:'ping', me, tela}    -> registra que o usuário abriu aquela tela (qualquer usuário ativo)
 * GET  ?relatorio=1&dias=30&me=.. -> painel de uso (ADMIN)
 *
 * POR QUE AGREGADO E NÃO UM REGISTRO POR CLIQUE
 * Uma linha por navegação viraria dezenas de milhares de linhas por mês e não responderia nada a mais.
 * A granularidade aqui é (usuário × tela × DIA) com contador + primeiro/último horário: isso já responde
 * "quem entrou", "quando foi a última vez", "quais telas usa" e "o dashboard está sendo usado" — e a
 * tabela fica pequena o bastante para nunca virar problema.
 *
 * É registro de USO DE TELA (abriu a tela X no dia Y), não rastreamento fino de atividade. A leitura é
 * restrita a admin, como o resto da aba de Configurações.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

define('ACC_RETENCAO_DIAS', 180);   // além disso a linha é descartada — não guardamos histórico eterno

/** Telas conhecidas: rótulo p/ o relatório. Chave = o mesmo id usado no showView() do front. */
function acc_telas() {
    return ['dashboards'=>'Dashboards', 'radar'=>'Radar de Aquisições', 'matriz'=>'Matriz',
            'cotacoes'=>'Cotações', 'solicitacoes'=>'Solicitações', 'buscaped'=>'Busca Pedidos',
            'buscanf'=>'Buscar Notas',
            'obras'=>'Obras', 'top20'=>'Top 20', 'oportunidades'=>'Oportunidades',
            'oraculo'=>'Radar IA', 'config'=>'Configurações', 'audit'=>'Auditoria', 'updates'=>'Atualizações'];
}
function acc_label($t) { $m = acc_telas(); return $m[$t] ?? $t; }

/** Cria a tabela na primeira chamada (o projeto não tem migration runner; cada módulo se vira). */
function acc_schema($pdo) {
    static $ok = false; if ($ok) return; $ok = true;
    $mysql = defined('DB_DRIVER') && DB_DRIVER === 'mysql';
    try {
        if ($mysql) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS acesso_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bitrix_id VARCHAR(64) NOT NULL,
                usuario_nome VARCHAR(191),
                tela VARCHAR(40) NOT NULL,
                dia VARCHAR(10) NOT NULL,
                n INT DEFAULT 0,
                primeiro_em VARCHAR(40),
                ultimo_em VARCHAR(40),
                UNIQUE KEY uq_acesso (bitrix_id, tela, dia),
                KEY idx_acesso_dia (dia)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS acesso_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT, bitrix_id TEXT NOT NULL, usuario_nome TEXT,
                tela TEXT NOT NULL, dia TEXT NOT NULL, n INTEGER DEFAULT 0,
                primeiro_em TEXT, ultimo_em TEXT)");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_acesso ON acesso_log (bitrix_id, tela, dia)");
        }
    } catch (Throwable $e) { /* sem log é melhor que derrubar a tela do usuário */ }
}

try {
    $pdo = db();
    acc_schema($pdo);
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    /* ---------- PING: o front avisa que abriu uma tela ---------- */
    if ($metodo === 'POST') {
        $in    = json_decode(file_get_contents('php://input'), true) ?: [];
        $perms = user_perms($pdo, $in['me'] ?? null);
        // não autorizado não registra — e devolve ok assim mesmo: o ping NUNCA pode atrapalhar a navegação
        if (empty($perms['autorizado'])) { echo json_encode(['ok'=>true, 'ignorado'=>true]); exit; }

        $tela = trim((string)($in['tela'] ?? ''));
        if ($tela === '' || !isset(acc_telas()[$tela])) { echo json_encode(['ok'=>true, 'ignorado'=>true]); exit; }

        $bid = trim((string)($in['me'] ?? ''));
        $nome = trim((string)($perms['nome'] ?? ''));
        $dia = date('Y-m-d'); $agora = date('c');
        try {
            $st = $pdo->prepare("SELECT id, n FROM acesso_log WHERE bitrix_id=? AND tela=? AND dia=? LIMIT 1");
            $st->execute([$bid, $tela, $dia]); $r = $st->fetch();
            if ($r) $pdo->prepare("UPDATE acesso_log SET n=n+1, ultimo_em=?, usuario_nome=? WHERE id=?")
                        ->execute([$agora, $nome, (int)$r['id']]);
            else    $pdo->prepare("INSERT INTO acesso_log (bitrix_id,usuario_nome,tela,dia,n,primeiro_em,ultimo_em) VALUES (?,?,?,?,1,?,?)")
                        ->execute([$bid, $nome, $tela, $dia, $agora, $agora]);
            // limpeza de retenção: 1 vez a cada ~200 pings, p/ não pesar em toda navegação
            if (random_int(1, 200) === 1)
                $pdo->prepare("DELETE FROM acesso_log WHERE dia < ?")->execute([date('Y-m-d', strtotime('-' . ACC_RETENCAO_DIAS . ' days'))]);
        } catch (Throwable $e) { /* idem: nunca derruba a navegação */ }
        echo json_encode(['ok'=>true]); exit;
    }

    /* ---------- RELATÓRIO (admin) ---------- */
    $perms = user_perms($pdo, $_GET['me'] ?? null);
    if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Apenas administradores.']); exit; }

    $dias = max(1, min(365, (int)($_GET['dias'] ?? 30)));
    $de   = date('Y-m-d', strtotime('-' . ($dias - 1) . ' days'));

    // usuários cadastrados — a lista de "quem NUNCA entrou" sai da diferença entre isto e o log
    $usuarios = [];
    foreach ($pdo->query("SELECT bitrix_id, nome, papel, dashboard, ativo FROM usuario ORDER BY nome") as $u)
        $usuarios[trim((string)$u['bitrix_id'])] = ['bitrix_id'=>trim((string)$u['bitrix_id']), 'nome'=>$u['nome'],
            'papel'=>$u['papel'] ?: '', 'dashboard'=>$u['dashboard'] ?: '', 'ativo'=>(int)$u['ativo']];

    $linhas = [];
    try {
        $st = $pdo->prepare("SELECT bitrix_id, usuario_nome, tela, dia, n, ultimo_em FROM acesso_log WHERE dia >= ? ORDER BY dia DESC");
        $st->execute([$de]); $linhas = $st->fetchAll();
    } catch (Throwable $e) { $linhas = []; }

    $porUsuario = []; $porTela = []; $porDia = [];
    foreach ($linhas as $l) {
        $b = trim((string)$l['bitrix_id']); $t = (string)$l['tela']; $n = (int)$l['n'];
        if (!isset($porUsuario[$b])) $porUsuario[$b] = ['bitrix_id'=>$b, 'nome'=>$l['usuario_nome'] ?: ($usuarios[$b]['nome'] ?? $b),
            'aberturas'=>0, 'dias'=>[], 'telas'=>[], 'ultimo_em'=>''];
        $u = &$porUsuario[$b];
        $u['aberturas'] += $n; $u['dias'][$l['dia']] = 1;
        $u['telas'][$t] = ($u['telas'][$t] ?? 0) + $n;
        if ((string)$l['ultimo_em'] > $u['ultimo_em']) $u['ultimo_em'] = (string)$l['ultimo_em'];
        unset($u);
        $porTela[$t] = ($porTela[$t] ?? 0) + $n;
        if (!isset($porDia[$l['dia']])) $porDia[$l['dia']] = ['dia'=>$l['dia'], 'aberturas'=>0, 'pessoas'=>[]];
        $porDia[$l['dia']]['aberturas'] += $n; $porDia[$l['dia']]['pessoas'][$b] = 1;
    }

    $out = [];
    foreach ($porUsuario as $b => $u) {
        arsort($u['telas']);
        $tot = max(1, $u['aberturas']);
        $out[] = ['bitrix_id'=>$b, 'nome'=>$u['nome'],
            'papel'=>$usuarios[$b]['papel'] ?? '', 'painel_atribuido'=>$usuarios[$b]['dashboard'] ?? '',
            'aberturas'=>$u['aberturas'], 'dias_ativos'=>count($u['dias']), 'ultimo_em'=>$u['ultimo_em'],
            'telas'=>array_map(fn($k, $v)=>['tela'=>$k, 'label'=>acc_label($k), 'n'=>$v, 'pct'=>round(100*$v/$tot)],
                               array_keys($u['telas']), array_values($u['telas'])),
            'usa_dashboard'=>(int)($u['telas']['dashboards'] ?? 0),
            'pct_dashboard'=>round(100 * (int)($u['telas']['dashboards'] ?? 0) / $tot)];
    }
    usort($out, fn($a, $b) => $b['aberturas'] <=> $a['aberturas']);

    $nunca = [];
    foreach ($usuarios as $b => $u) if (!isset($porUsuario[$b])) $nunca[] = $u;

    arsort($porTela);
    $totT = max(1, array_sum($porTela));
    $telas = array_map(fn($k, $v)=>['tela'=>$k, 'label'=>acc_label($k), 'n'=>$v, 'pct'=>round(100*$v/$totT)],
                       array_keys($porTela), array_values($porTela));
    ksort($porDia);
    $dias_serie = array_map(fn($d)=>['dia'=>$d['dia'], 'aberturas'=>$d['aberturas'], 'pessoas'=>count($d['pessoas'])], array_values($porDia));

    // desde quando existe medição (antes disso o cockpit simplesmente não registrava nada)
    $desde = null;
    try { $desde = $pdo->query("SELECT MIN(dia) FROM acesso_log")->fetchColumn() ?: null; } catch (Throwable $e) {}

    echo json_encode([
        'ok'=>true, 'dias'=>$dias, 'de'=>$de, 'ate'=>date('Y-m-d'), 'medindo_desde'=>$desde,
        'usuarios'=>$out, 'nunca_entraram'=>$nunca, 'telas'=>$telas, 'serie'=>$dias_serie,
        'total_aberturas'=>array_sum($porTela), 'pessoas_ativas'=>count($out), 'cadastrados'=>count($usuarios),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
