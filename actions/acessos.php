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

/** Telas conhecidas: rótulo p/ o relatório. Chave = o mesmo id usado no showView() do front.
 *
 * ATENÇÃO (18/08/2026) — esta lista já foi a causa de um relatório MENTIROSO. Ela nasceu com 14 das
 * 21 telas do showView(): faltavam envio, caixa, whats, fechamentos e as três telas de CONSULTA da
 * obra (ovradar/ovcot/ovsc). Quem só usa tela de fora da lista tinha TODO ping descartado e caía em
 * "nunca entraram" — foi o caso do papel 'obra' inteiro (Flávia, Beatriz, Cláudia, Guilherme), que
 * só tem telas ov_*. Prova: o log de bilhetes de identidade (data/.auth_ok.log) mostrava a Flávia
 * abrindo o app 12 vezes no mesmo período em que a aba Acessos dizia que ela nunca havia entrado.
 * Por isso o ping deixou de EXIGIR a lista: tela desconhecida é gravada com o próprio id (ver
 * acc_slug_ok). A lista virou só o dicionário de rótulos — esquecer de atualizar deixa o nome feio,
 * não apaga o dado. Ao criar tela nova no showView(), acrescente aqui. */
function acc_telas() {
    return ['dashboards'=>'Dashboards', 'radar'=>'Radar de Aquisições', 'matriz'=>'Matriz',
            'cotacoes'=>'Cotações', 'fechamentos'=>'Fechamentos', 'solicitacoes'=>'Solicitações',
            'envio'=>'Envio de Pedidos', 'buscaped'=>'Busca Pedidos', 'buscanf'=>'Buscar Notas',
            'obras'=>'Obras', 'top20'=>'Top 20', 'oportunidades'=>'Oportunidades',
            'caixa'=>'Caixa de E-mail', 'whats'=>'WhatsApp',
            'oraculo'=>'Radar IA', 'config'=>'Configurações', 'audit'=>'Auditoria', 'updates'=>'Atualizações',
            // telas de CONSULTA do papel 'obra' — as que estavam faltando
            'ovradar'=>'Obra: Status Curva A e B', 'ovcot'=>'Obra: Cotações', 'ovsc'=>'Obra: Solicitações Totvs'];
}
function acc_label($t) { $m = acc_telas(); return $m[$t] ?? $t; }
/** Aceita qualquer id de tela com cara de id (o showView() só usa minúsculas sem acento). */
function acc_slug_ok($t) { return (bool)preg_match('/^[a-z0-9_]{2,40}$/', $t); }

/** ENTRADAS NO APP — a segunda fonte, independente da lista de telas.
 *  Cada vez que alguém abre o cockpit pelo Bitrix, o includes/auth.php emite um bilhete assinado e
 *  anota em data/.auth_ok.log. Isso é "abriu o app", sem depender de tela nenhuma — é a contraprova
 *  que faltava para não repetir o erro de declarar "nunca entrou" quem entrou. O arquivo é reciclado
 *  ao passar de 512 KB (auth_registrar_ok), então isto é medição recente, não histórico completo. */
function acc_entradas($de) {
    $f = __DIR__ . '/../data/.auth_ok.log';
    $out = [];
    if (!is_file($f)) return $out;
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        $j = json_decode($l, true); if (!$j) continue;
        $q = (string)($j['q'] ?? ''); $dia = substr($q, 0, 10);
        if ($dia === '' || $dia < $de) continue;
        $id = trim((string)($j['id'] ?? '')); if ($id === '') continue;
        if (!isset($out[$id])) $out[$id] = ['n'=>0, 'dias'=>[], 'ultimo'=>''];
        $out[$id]['n']++; $out[$id]['dias'][$dia] = 1;
        if ($q > $out[$id]['ultimo']) $out[$id]['ultimo'] = $q;
    }
    return $out;
}

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

        // grava QUALQUER tela com id válido — inclusive as que não estão no dicionário de rótulos
        $tela = trim((string)($in['tela'] ?? ''));
        if ($tela === '' || !acc_slug_ok($tela)) { echo json_encode(['ok'=>true, 'ignorado'=>true]); exit; }

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

    /* Grão por (usuário × tela): além do contador, DIAS distintos e o último horário — é o que a linha
       expandida da pessoa mostra ("usa Cotações 113 vezes, em 14 dias, a última hoje 10:12"). Sai de
       graça: os dados já vinham por dia, era só não jogar o dia fora ao somar. */
    $porUsuario = []; $porTela = []; $porDia = [];
    foreach ($linhas as $l) {
        $b = trim((string)$l['bitrix_id']); $t = (string)$l['tela']; $n = (int)$l['n'];
        if (!isset($porUsuario[$b])) $porUsuario[$b] = ['bitrix_id'=>$b, 'nome'=>$l['usuario_nome'] ?: ($usuarios[$b]['nome'] ?? $b),
            'aberturas'=>0, 'dias'=>[], 'telas'=>[], 'ultimo_em'=>''];
        $u = &$porUsuario[$b];
        $u['aberturas'] += $n; $u['dias'][$l['dia']] = 1;
        if (!isset($u['telas'][$t])) $u['telas'][$t] = ['n'=>0, 'dias'=>[], 'ultimo'=>''];
        $u['telas'][$t]['n'] += $n; $u['telas'][$t]['dias'][$l['dia']] = 1;
        if ((string)$l['ultimo_em'] > $u['telas'][$t]['ultimo']) $u['telas'][$t]['ultimo'] = (string)$l['ultimo_em'];
        if ((string)$l['ultimo_em'] > $u['ultimo_em']) $u['ultimo_em'] = (string)$l['ultimo_em'];
        unset($u);
        $porTela[$t] = ($porTela[$t] ?? 0) + $n;
        if (!isset($porDia[$l['dia']])) $porDia[$l['dia']] = ['dia'=>$l['dia'], 'aberturas'=>0, 'pessoas'=>[]];
        $porDia[$l['dia']]['aberturas'] += $n; $porDia[$l['dia']]['pessoas'][$b] = 1;
    }

    $entradas = acc_entradas($de);          // abriu o app (bilhete) — independe de tela

    $out = [];
    foreach ($porUsuario as $b => $u) {
        uasort($u['telas'], fn($x, $y) => $y['n'] <=> $x['n']);
        $tot = max(1, $u['aberturas']);
        $e   = $entradas[$b] ?? null;
        $out[] = ['bitrix_id'=>(string)$b, 'nome'=>$u['nome'],
            'papel'=>$usuarios[$b]['papel'] ?? '', 'painel_atribuido'=>$usuarios[$b]['dashboard'] ?? '',
            'aberturas'=>$u['aberturas'], 'dias_ativos'=>count($u['dias']), 'ultimo_em'=>$u['ultimo_em'],
            'entradas_app'=>$e ? $e['n'] : 0, 'entrada_ultima'=>$e ? $e['ultimo'] : '',
            'telas'=>array_map(fn($k, $v)=>['tela'=>$k, 'label'=>acc_label($k), 'n'=>$v['n'],
                                            'dias'=>count($v['dias']), 'ultimo_em'=>$v['ultimo'],
                                            'conhecida'=>isset(acc_telas()[$k]) ? 1 : 0,
                                            'pct'=>round(100*$v['n']/$tot)],
                               array_keys($u['telas']), array_values($u['telas'])),
            'usa_dashboard'=>(int)($u['telas']['dashboards']['n'] ?? 0),
            'pct_dashboard'=>round(100 * (int)($u['telas']['dashboards']['n'] ?? 0) / $tot)];
    }
    /* Quem tem bilhete mas nenhuma tela: ENTROU. Aparece na tabela com 0 telas, em vez de virar
       "nunca entrou" — a mentira que esta tela contava até hoje. */
    foreach ($entradas as $b => $e) {
        if (isset($porUsuario[$b])) continue;
        if (!isset($usuarios[$b])) continue;                 // bilhete de quem não está no cadastro: ignora
        $out[] = ['bitrix_id'=>(string)$b, 'nome'=>$usuarios[$b]['nome'], 'papel'=>$usuarios[$b]['papel'],
            'painel_atribuido'=>$usuarios[$b]['dashboard'], 'aberturas'=>0, 'dias_ativos'=>count($e['dias']),
            'ultimo_em'=>$e['ultimo'], 'entradas_app'=>$e['n'], 'entrada_ultima'=>$e['ultimo'],
            'telas'=>[], 'usa_dashboard'=>0, 'pct_dashboard'=>0];
    }
    usort($out, fn($a, $b) => [$b['aberturas'], $b['entradas_app']] <=> [$a['aberturas'], $a['entradas_app']]);

    $nunca = [];
    foreach ($usuarios as $b => $u) if (!isset($porUsuario[$b]) && !isset($entradas[$b])) $nunca[] = $u;

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
        'total_entradas'=>array_sum(array_map(fn($e)=>$e['n'], $entradas)),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
