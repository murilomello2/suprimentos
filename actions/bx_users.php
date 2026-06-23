<?php
/**
 * Busca usuários do Bitrix24 (via webhook) para a aba Configuração.
 * Lista todos os ativos (cache em arquivo ~1h) e filtra por nome/cargo.
 * GET: q (termo). Retorna [{id, nome, cargo}].
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/bitrix.php';

define('USERS_CACHE', __DIR__ . '/../data/.users_cache.json');
define('USERS_TTL', 3600);

function carregar_usuarios() {
    if (is_file(USERS_CACHE) && (time() - filemtime(USERS_CACHE)) < USERS_TTL) {
        $d = json_decode(@file_get_contents(USERS_CACHE), true);
        if (is_array($d)) return $d;
    }
    $todos = []; $start = 0;
    for ($i = 0; $i < 12; $i++) { // até ~600 usuários
        $r = bx_call('user.get', ['FILTER' => ['ACTIVE' => true], 'start' => $start]);
        if (empty($r['result'])) break;
        foreach ($r['result'] as $u) {
            $todos[] = [
                'id'    => $u['ID'],
                'nome'  => trim(($u['NAME'] ?? '') . ' ' . ($u['LAST_NAME'] ?? '')),
                'cargo' => $u['WORK_POSITION'] ?? '',
            ];
        }
        if (!isset($r['next'])) break;
        $start = (int)$r['next'];
    }
    if ($todos) @file_put_contents(USERS_CACHE, json_encode($todos));
    return $todos;
}

try {
    $q = strtolower(trim($_GET['q'] ?? ''));
    $todos = carregar_usuarios();
    if ($q === '') { $out = array_slice($todos, 0, 40); }
    else {
        $out = array_values(array_filter($todos, function ($u) use ($q) {
            return strpos(strtolower($u['nome'] . ' ' . $u['cargo'] . ' ' . $u['id']), $q) !== false;
        }));
        $out = array_slice($out, 0, 40);
    }
    echo json_encode(['usuarios' => $out, 'total' => count($todos)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
