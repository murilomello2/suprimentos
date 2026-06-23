<?php
/**
 * Permissões de usuário (atreladas ao Bitrix). Persistente (tabela usuario).
 * GET                       -> { usuarios:[...], obras:[...] }
 * GET ?me=<bitrix_id>       -> permissões efetivas do usuário (enforcement). Default = NEGA tudo.
 * POST {acao:"save", ...}   -> upsert de um registro
 * POST {acao:"delete", bitrix_id}
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

// presets de papel (defaults; o admin pode sobrescrever campo a campo)
function preset($papel) {
    $base = ['ver_escopo'=>'sel','editar_escopo'=>'nenhuma','menus'=>['radar','matriz'],'perm_admin'=>0];
    switch ($papel) {
        case 'admin':       return ['ver_escopo'=>'todas','editar_escopo'=>'todas','menus'=>['dashboard','radar','matriz','cotacoes','config'],'perm_admin'=>1];
        case 'diretor':     return ['ver_escopo'=>'todas','editar_escopo'=>'nenhuma','menus'=>['dashboard','radar','matriz','cotacoes'],'perm_admin'=>0];
        case 'comprador':   return ['ver_escopo'=>'todas','editar_escopo'=>'sel','menus'=>['radar','matriz','cotacoes'],'perm_admin'=>0];
        case 'coordenador': return ['ver_escopo'=>'sel','editar_escopo'=>'nenhuma','menus'=>['radar','matriz'],'perm_admin'=>0];
        default:            return $base;
    }
}

function jrow($r) {
    foreach (['obras_ver','obras_editar','menus'] as $k) $r[$k] = $r[$k] ? json_decode($r[$k], true) : [];
    $r['perm_admin'] = (int)$r['perm_admin']; $r['ativo'] = (int)$r['ativo'];
    return $r;
}

try {
    $pdo = db();

    // permissões efetivas de um usuário (enforcement). Não cadastrado => nega tudo.
    if (isset($_GET['me'])) {
        $st = $pdo->prepare("SELECT * FROM usuario WHERE bitrix_id=? AND ativo=1");
        $st->execute([$_GET['me']]);
        $u = $st->fetch();
        if (!$u) { echo json_encode(['autorizado'=>false,'menus'=>[],'perm_admin'=>0]); exit; }
        echo json_encode(['autorizado'=>true] + jrow($u), JSON_UNESCAPED_UNICODE); exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true);
        $acao = $in['acao'] ?? 'save';
        $bid = (string)($in['bitrix_id'] ?? '');
        if ($bid === '') throw new Exception('bitrix_id obrigatório');

        if ($acao === 'delete') {
            $pdo->prepare("DELETE FROM usuario WHERE bitrix_id=?")->execute([$bid]);
            echo json_encode(['ok'=>true]); exit;
        }

        $papel = $in['papel'] ?? 'coordenador';
        $p = preset($papel);
        $rec = [
            'bitrix_id'     => $bid,
            'nome'          => $in['nome'] ?? '',
            'cargo'         => $in['cargo'] ?? '',
            'papel'         => $papel,
            'ver_escopo'    => $in['ver_escopo']    ?? $p['ver_escopo'],
            'editar_escopo' => $in['editar_escopo'] ?? $p['editar_escopo'],
            'obras_ver'     => json_encode($in['obras_ver']    ?? []),
            'obras_editar'  => json_encode($in['obras_editar'] ?? []),
            'menus'         => json_encode($in['menus']        ?? $p['menus']),
            'perm_admin'    => (int)($in['perm_admin'] ?? $p['perm_admin']),
            'ativo'         => (int)($in['ativo'] ?? 1),
            'updated_at'    => date('c'),
        ];
        $cols = implode(',', array_keys($rec));
        $ph   = implode(',', array_fill(0, count($rec), '?'));
        $pdo->prepare("INSERT OR REPLACE INTO usuario ($cols) VALUES ($ph)")->execute(array_values($rec));
        echo json_encode(['ok'=>true]); exit;
    }

    // lista
    $us = array_map('jrow', $pdo->query("SELECT * FROM usuario ORDER BY nome")->fetchAll());
    $obras = $pdo->query("SELECT id, nome FROM obra ORDER BY nome")->fetchAll();
    echo json_encode(['usuarios'=>$us, 'obras'=>$obras], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
