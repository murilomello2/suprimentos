<?php
/**
 * DIAGNÓSTICO DE IDENTIDADE (admin). Responde à única pergunta que importa antes de virar a
 * chave para o modo estrito: **as pessoas de verdade estão chegando com bilhete?**
 *
 * Virar para estrito sem saber isso tranca o time inteiro para fora. Então primeiro o sistema
 * roda em auditoria — funciona como antes, mas anota toda chamada SEM bilhete — e só depois,
 * com o log limpo, a chave vira.
 *
 * GET  ?me=..            -> estado atual + resumo do que foi auditado
 * POST {acao:'modo', modo:'auditoria'|'estrito', me}  -> vira a chave
 * POST {acao:'limpar', me}                            -> zera o log (antes de uma nova medição)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

define('AUTH_LOG', __DIR__ . '/../data/.auth_audit.log');

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'];
    $in = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];
    $me = $method === 'POST' ? ($in['me'] ?? null) : ($_GET['me'] ?? null);
    $perms = user_perms($pdo, $me);
    if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error' => 'Apenas administradores.']); exit; }

    if (($in['acao'] ?? '') === 'modo') {
        $m = ($in['modo'] ?? '') === 'estrito' ? 'estrito' : 'auditoria';
        /* Trava de segurança: só deixa ir para estrito se QUEM ESTÁ PEDINDO chegou com bilhete
           válido. Se nem o admin tem bilhete, ninguém tem, e virar a chave tranca todo mundo —
           inclusive quem viraria de volta. */
        if ($m === 'estrito' && auth_id_verificada() === null) {
            echo json_encode(['error' => 'Você mesmo está sem bilhete de identidade agora. Se eu ligar o modo estrito, ninguém entra — nem você para desligar. Abra o cockpit pelo menu do app dentro do Bitrix e tente de novo.'], JSON_UNESCAPED_UNICODE); exit;
        }
        auth_modo_set($m);
        echo json_encode(['ok' => true, 'modo' => auth_modo()], JSON_UNESCAPED_UNICODE); exit;
    }

    if (($in['acao'] ?? '') === 'limpar') { @unlink(AUTH_LOG); echo json_encode(['ok' => true]); exit; }

    // ── estado ──
    $linhas = []; $porMe = []; $porEnd = []; $n = 0; $primeira = ''; $ultima = '';
    if (is_file(AUTH_LOG)) {
        foreach (file(AUTH_LOG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
            $j = json_decode($l, true); if (!$j) continue;
            $n++;
            if ($primeira === '') $primeira = $j['q'] ?? '';
            $ultima = $j['q'] ?? '';
            $k = (string)($j['me'] ?? ''); $porMe[$k] = ($porMe[$k] ?? 0) + 1;
            $e = (string)($j['end'] ?? ''); $porEnd[$e] = ($porEnd[$e] ?? 0) + 1;
            if (count($linhas) < 20) $linhas[] = $j;
        }
    }
    arsort($porMe); arsort($porEnd);
    // nome de quem aparece no log — "me=74 sem bilhete" diz pouco; "Paloma sem bilhete" diz tudo
    $nomes = [];
    foreach (array_keys($porMe) as $bid) {
        if ($bid === '') continue;
        try { $q = $pdo->prepare("SELECT nome FROM usuario WHERE TRIM(bitrix_id)=? LIMIT 1"); $q->execute([$bid]);
              $nomes[$bid] = (string)($q->fetchColumn() ?: ''); } catch (Throwable $e) {}
    }
    echo json_encode(['ok' => true,
        'modo' => auth_modo(),
        'voce_tem_bilhete' => auth_id_verificada() !== null,
        'sua_id_verificada' => auth_id_verificada(),
        'sem_bilhete' => ['total' => $n, 'primeira' => $primeira, 'ultima' => $ultima,
                          'por_usuario' => array_slice($porMe, 0, 15, true),
                          'nomes' => $nomes,
                          'por_endpoint' => array_slice($porEnd, 0, 10, true),
                          'amostra' => $linhas],
    ], JSON_UNESCAPED_UNICODE); exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
