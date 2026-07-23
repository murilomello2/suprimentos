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
        case 'gerente':     return ['ver_escopo'=>'todas','editar_escopo'=>'todas','menus'=>['dashboard','radar','matriz','cotacoes','solicitacoes','obras','oportunidades','top20'],'perm_admin'=>0];
        case 'comprador':   return ['ver_escopo'=>'todas','editar_escopo'=>'todas','menus'=>['radar','matriz','cotacoes'],'perm_admin'=>0];
        case 'coordenador': return ['ver_escopo'=>'sel','editar_escopo'=>'nenhuma','menus'=>['radar','matriz'],'perm_admin'=>0];
        default:            return $base;
    }
}

function jrow($r) {
    foreach (['obras_ver','obras_editar','menus'] as $k) $r[$k] = $r[$k] ? json_decode($r[$k], true) : [];
    foreach (['perm_admin','ativo','perm_crono','perm_orcamento','perm_quant','perm_dicionario','perm_responsaveis'] as $k) $r[$k] = (int)($r[$k] ?? 0);
    return $r;
}

try {
    $pdo = db();

    // resiliência a DEPLOY PARCIAL: garante as colunas de permissão granular (db.php pode chegar depois no FTP)
    try {
        $ucols = [];
        foreach ($pdo->query("PRAGMA table_info(usuario)") as $c) $ucols[$c['name']] = true;
        foreach (['perm_crono','perm_orcamento','perm_quant','perm_dicionario','perm_responsaveis'] as $pc) {
            if (!isset($ucols[$pc])) { try { $pdo->exec("ALTER TABLE usuario ADD COLUMN $pc INTEGER DEFAULT 0"); } catch (Throwable $e) {} }
        }
        if (!isset($ucols['dashboard'])) { try { $pdo->exec("ALTER TABLE usuario ADD COLUMN dashboard TEXT DEFAULT ''"); } catch (Throwable $e) {} }
    } catch (Throwable $e) {}

    // lista de RESPONSÁVEIS possíveis p/ o Radar = usuários ativos com o papel 'comprador'
    // (rotulado "Suprimentos" na UI). Sem cargo — só id + nome.
    if (isset($_GET['responsaveis'])) {
        $rs = $pdo->query("SELECT bitrix_id, nome FROM usuario
                           WHERE ativo=1 AND papel='comprador' AND TRIM(COALESCE(nome,''))<>'' ORDER BY nome")->fetchAll();
        echo json_encode(['responsaveis' => $rs], JSON_UNESCAPED_UNICODE); exit;
    }

    // permissões efetivas de um usuário (enforcement). Não cadastrado => nega tudo.
    if (isset($_GET['me'])) {
        $st = $pdo->prepare("SELECT * FROM usuario WHERE TRIM(bitrix_id)=? AND ativo=1");
        $st->execute([trim((string)$_GET['me'])]);
        $u = $st->fetch();
        if (!$u) { echo json_encode(['autorizado'=>false,'menus'=>[],'perm_admin'=>0]); exit; }
        echo json_encode(['autorizado'=>true] + jrow($u), JSON_UNESCAPED_UNICODE); exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true);
        // ENFORCEMENT: só administrador gerencia permissões (evita auto-promoção via POST direto)
        $caller = user_perms($pdo, $in['me'] ?? null);
        if (empty($caller['perm_admin'])) {
            http_response_code(403);
            echo json_encode(['error'=>'Apenas administradores gerenciam permissões.',
                'debug'=>['me_recebido'=>($in['me'] ?? null), 'tipo'=>gettype($in['me'] ?? null),
                          'autorizado'=>(bool)($caller['autorizado'] ?? false)]], JSON_UNESCAPED_UNICODE); exit;
        }
        $acao = $in['acao'] ?? 'save';

        // ---- CONFIGURAÇÃO EM LOTE (admin): aplica um PACOTE de campos a um grupo (papel ou lista de ids). ----
        // Só os campos PRESENTES em `campos` são atualizados (UPDATE parcial — não é REPLACE; o resto do
        // cadastro de cada usuário fica intacto). `papel` e `perm_admin` ficam DE FORA por segurança
        // (mudança de papel/admin é individual — evita se auto-rebaixar ou promover em massa sem querer).
        // POST {acao:'save_lote', me, papel_alvo? | bitrix_ids?[], campos:{menus?,ver_escopo?,obras_ver?,editar_escopo?,obras_editar?,perm_*?,ativo?}}
        if ($acao === 'save_lote') {
            $campos = is_array($in['campos'] ?? null) ? $in['campos'] : [];
            if (!$campos) throw new Exception('campos vazio — marque ao menos uma seção');
            $JSONF = ['obras_ver','obras_editar','menus'];
            $INTF  = ['perm_crono','perm_orcamento','perm_quant','perm_dicionario','perm_responsaveis','ativo'];
            $STRF  = ['ver_escopo','editar_escopo','dashboard'];
            $set = []; $vals = [];
            foreach (array_merge($JSONF, $INTF, $STRF) as $k) {
                if (!array_key_exists($k, $campos)) continue;
                if (in_array($k, $JSONF, true))     { $set[] = "$k=?"; $vals[] = json_encode(is_array($campos[$k]) ? array_values($campos[$k]) : []); }
                elseif (in_array($k, $INTF, true))  { $set[] = "$k=?"; $vals[] = (int)$campos[$k]; }
                else                                { $set[] = "$k=?"; $vals[] = (string)$campos[$k]; }
            }
            if (!$set) throw new Exception('nenhum campo aplicável no pacote');
            $ids = array_values(array_filter(array_map(fn($v) => trim((string)$v), (array)($in['bitrix_ids'] ?? [])), fn($v) => $v !== ''));
            $papelAlvo = trim((string)($in['papel_alvo'] ?? ''));
            if ($ids)                { $where = 'bitrix_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'; $wv = $ids; }
            elseif ($papelAlvo !== ''){ $where = 'papel=? AND ativo=1'; $wv = [$papelAlvo]; }
            else throw new Exception('alvo vazio (papel_alvo ou bitrix_ids)');
            $set[] = 'updated_at=?'; $vals[] = date('c');
            $st = $pdo->prepare("UPDATE usuario SET " . implode(',', $set) . " WHERE $where");
            $st->execute(array_merge($vals, $wv));
            echo json_encode(['ok'=>true, 'afetados'=>$st->rowCount()], JSON_UNESCAPED_UNICODE); exit;
        }

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
            'perm_crono'      => (int)($in['perm_crono'] ?? 0),
            'perm_orcamento'  => (int)($in['perm_orcamento'] ?? 0),
            'perm_quant'      => (int)($in['perm_quant'] ?? 0),
            'perm_dicionario' => (int)($in['perm_dicionario'] ?? 0),
            'perm_responsaveis' => (int)($in['perm_responsaveis'] ?? 0),
            'dashboard'     => (string)($in['dashboard'] ?? ''),
            'ativo'         => (int)($in['ativo'] ?? 1),
            'updated_at'    => date('c'),
        ];
        $cols = implode(',', array_keys($rec));
        $ph   = implode(',', array_fill(0, count($rec), '?'));
        // REPLACE INTO: cross-compatível SQLite + MySQL (INSERT OR REPLACE é SQLite-only e dá erro no MySQL)
        $pdo->prepare("REPLACE INTO usuario ($cols) VALUES ($ph)")->execute(array_values($rec));
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
