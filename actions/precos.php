<?php
/**
 * PREÇOS TABELADOS — tabelas (contrato do fornecedor, com validade + PDF) → itens (ligados ao ITEM CANÔNICO, p/ dedup).
 * GET ?buscar=<q>        -> consulta por insumo: grupos por item canônico, cada um com os fornecedores/preços (ordenado)
 * GET ?insumos=<q>       -> autocomplete de itens canônicos (id, nome, unidade)
 * GET ?tabela=<id>       -> tabela completa + itens
 * GET                    -> lista de tabelas (fornecedor, validade, n_itens, vigente)
 * POST {acao:'salvar_tabela', me, tabela{...}, itens:[{insumo_id?,insumo_nome?,descricao_original,unidade,preco,frete_incluso,observacao}]}
 * POST {acao:'salvar_insumo', me, insumo{id?,nome,unidade,sinonimos[],servico_id}}
 * POST {acao:'excluir_tabela', me, id}
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

function preco_hoje() { return date('Y-m-d'); }
function preco_vigente($fim) { $f = trim((string)$fim); return $f === '' || $f >= preco_hoje(); }
function preco_lower($s) {
    $map = ['Á'=>'á','À'=>'à','Â'=>'â','Ã'=>'ã','Ä'=>'ä','É'=>'é','È'=>'è','Ê'=>'ê','Ë'=>'ë','Í'=>'í','Ì'=>'ì','Î'=>'î','Ó'=>'ó','Ò'=>'ò','Ô'=>'ô','Õ'=>'õ','Ö'=>'ö','Ú'=>'ú','Ü'=>'ü','Ç'=>'ç'];
    return strtolower(strtr((string)$s, $map));
}
// acha o item canônico por nome exato ou por sinônimo (1 por linha)
function preco_match_insumo($pdo, $nome) {
    $nome = trim((string)$nome); if ($nome === '') return null;
    $q = $pdo->prepare("SELECT id FROM preco_insumo WHERE nome=? LIMIT 1"); $q->execute([$nome]);
    if ($id = $q->fetchColumn()) return (int)$id;
    $q = $pdo->prepare("SELECT id, sinonimos FROM preco_insumo");
    foreach ($q->fetchAll() as $r) {
        foreach (preg_split('/\r?\n/', (string)$r['sinonimos']) as $sin) if (trim($sin) !== '' && preco_lower($sin) === preco_lower($nome)) return (int)$r['id'];
    }
    return null;
}
function preco_add_sinonimo($pdo, $insumo_id, $texto) {
    $texto = trim((string)$texto); if ($texto === '' || !$insumo_id) return;
    $q = $pdo->prepare("SELECT nome, sinonimos FROM preco_insumo WHERE id=?"); $q->execute([(int)$insumo_id]); $r = $q->fetch();
    if (!$r) return;
    if (preco_lower($r['nome']) === preco_lower($texto)) return;
    $sins = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string)$r['sinonimos']))));
    foreach ($sins as $s) if (preco_lower($s) === preco_lower($texto)) return;
    $sins[] = $texto;
    $pdo->prepare("UPDATE preco_insumo SET sinonimos=?, updated_at=? WHERE id=?")->execute([implode("\n", $sins), date('c'), (int)$insumo_id]);
}

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['insumos'])) {
            $q = trim((string)$_GET['insumos']);
            if ($q === '') { $rows = $pdo->query("SELECT id, nome, unidade FROM preco_insumo ORDER BY nome LIMIT 40")->fetchAll(); }
            else { $st = $pdo->prepare("SELECT id, nome, unidade FROM preco_insumo WHERE nome LIKE ? OR sinonimos LIKE ? ORDER BY nome LIMIT 40"); $st->execute(['%'.$q.'%', '%'.$q.'%']); $rows = $st->fetchAll(); }
            echo json_encode(['insumos' => $rows], JSON_UNESCAPED_UNICODE); exit;
        }
        if (isset($_GET['tabela'])) {
            $id = (int)$_GET['tabela'];
            $t = $pdo->prepare("SELECT * FROM preco_tabela WHERE id=?"); $t->execute([$id]); $tab = $t->fetch();
            if (!$tab) { echo json_encode(['error' => 'tabela não encontrada']); exit; }
            $it = $pdo->prepare("SELECT pi.*, ins.nome AS insumo_nome FROM preco_item pi LEFT JOIN preco_insumo ins ON ins.id=pi.insumo_id WHERE pi.tabela_id=? ORDER BY pi.id"); $it->execute([$id]);
            $tab['vigente'] = preco_vigente($tab['validade_fim']);
            echo json_encode(['tabela' => $tab, 'itens' => $it->fetchAll()], JSON_UNESCAPED_UNICODE); exit;
        }
        if (isset($_GET['buscar'])) {
            $q = trim((string)$_GET['buscar']); $like = '%' . $q . '%';
            $sql = "SELECT pi.id, pi.insumo_id, pi.descricao_original, pi.unidade, pi.preco, pi.frete_incluso, pi.observacao AS item_obs,
                           pt.id AS tabela_id, pt.fornecedor_nome, pt.titulo AS tab_titulo, pt.validade_inicio, pt.validade_fim, pt.observacao AS tab_obs,
                           ins.nome AS insumo_nome, ins.unidade AS insumo_unidade
                    FROM preco_item pi JOIN preco_tabela pt ON pt.id=pi.tabela_id LEFT JOIN preco_insumo ins ON ins.id=pi.insumo_id";
            $args = [];
            if ($q !== '') { $sql .= " WHERE (ins.nome LIKE ? OR ins.sinonimos LIKE ? OR pi.descricao_original LIKE ?)"; $args = [$like, $like, $like]; }
            $sql .= " ORDER BY pi.preco IS NULL, pi.preco";
            $st = $pdo->prepare($sql); $st->execute($args); $rows = $st->fetchAll();
            // agrupa por item canônico (ou pela descrição quando sem canônico)
            $grupos = [];
            foreach ($rows as $r) {
                $chave = $r['insumo_id'] ? ('c' . $r['insumo_id']) : ('d:' . preco_lower($r['descricao_original']));
                if (!isset($grupos[$chave])) $grupos[$chave] = [
                    'insumo_id' => $r['insumo_id'] ? (int)$r['insumo_id'] : null,
                    'nome' => $r['insumo_nome'] ?: $r['descricao_original'],
                    'unidade' => $r['insumo_unidade'] ?: $r['unidade'], 'ofertas' => [],
                ];
                $grupos[$chave]['ofertas'][] = [
                    'fornecedor' => $r['fornecedor_nome'], 'preco' => $r['preco'] !== null ? (float)$r['preco'] : null,
                    'unidade' => $r['unidade'], 'frete_incluso' => (int)$r['frete_incluso'], 'obs' => $r['item_obs'] ?: $r['tab_obs'],
                    'descricao_original' => $r['descricao_original'], 'validade_fim' => $r['validade_fim'],
                    'vigente' => preco_vigente($r['validade_fim']), 'tabela_id' => (int)$r['tabela_id'], 'tab_titulo' => $r['tab_titulo'],
                ];
            }
            echo json_encode(['grupos' => array_values($grupos), 'total_ofertas' => count($rows)], JSON_UNESCAPED_UNICODE); exit;
        }
        // lista de tabelas
        $tabs = $pdo->query("SELECT pt.*, (SELECT COUNT(*) FROM preco_item pi WHERE pi.tabela_id=pt.id) AS n_itens FROM preco_tabela pt ORDER BY pt.id DESC")->fetchAll();
        foreach ($tabs as &$t) $t['vigente'] = preco_vigente($t['validade_fim']); unset($t);
        echo json_encode(['tabelas' => $tabs], JSON_UNESCAPED_UNICODE); exit;
    }

    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $me = $in['me'] ?? null; $perms = user_perms($pdo, $me);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }
    $pode = !empty($perms['perm_admin']) || (($perms['editar_escopo'] ?? '') !== 'nenhuma' && ($perms['editar_escopo'] ?? '') !== '');
    if (!$pode) { http_response_code(403); echo json_encode(['error' => 'Sem permissão para cadastrar preços.']); exit; }
    $acao = $in['acao'] ?? '';
    $now = date('c');

    if ($acao === 'salvar_insumo') {
        $s = (array)($in['insumo'] ?? []); $nome = trim((string)($s['nome'] ?? '')); if ($nome === '') throw new Exception('nome obrigatório');
        $sin = implode("\n", array_values(array_filter(array_map('trim', (array)($s['sinonimos'] ?? [])))));
        $id = (int)($s['id'] ?? 0);
        if ($id) $pdo->prepare("UPDATE preco_insumo SET nome=?, unidade=?, sinonimos=?, servico_id=?, categoria=?, updated_at=? WHERE id=?")
            ->execute([$nome, trim((string)($s['unidade'] ?? '')), $sin, ($s['servico_id'] ?? null) ?: null, trim((string)($s['categoria'] ?? '')), $now, $id]);
        else { $pdo->prepare("INSERT INTO preco_insumo (nome, unidade, sinonimos, servico_id, categoria, created_at, updated_at) VALUES (?,?,?,?,?,?,?)")
            ->execute([$nome, trim((string)($s['unidade'] ?? '')), $sin, ($s['servico_id'] ?? null) ?: null, trim((string)($s['categoria'] ?? '')), $now, $now]); $id = (int)$pdo->lastInsertId(); }
        echo json_encode(['ok' => true, 'id' => $id], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'salvar_tabela') {
        $t = (array)($in['tabela'] ?? []);
        $tid = (int)($t['id'] ?? 0);
        $vals = [
            ($t['fornecedor_id'] ?? null) ?: null, trim((string)($t['fornecedor_nome'] ?? '')), trim((string)($t['titulo'] ?? '')),
            trim((string)($t['validade_inicio'] ?? '')) ?: null, trim((string)($t['validade_fim'] ?? '')) ?: null,
            trim((string)($t['observacao'] ?? '')), ($t['anexo_id'] ?? null) ?: null,
        ];
        if (trim((string)($t['fornecedor_nome'] ?? '')) === '') throw new Exception('informe o fornecedor');
        $pdo->beginTransaction();
        if ($tid) { $pdo->prepare("UPDATE preco_tabela SET fornecedor_id=?, fornecedor_nome=?, titulo=?, validade_inicio=?, validade_fim=?, observacao=?, anexo_id=?, updated_at=? WHERE id=?")
            ->execute(array_merge($vals, [$now, $tid])); $pdo->prepare("DELETE FROM preco_item WHERE tabela_id=?")->execute([$tid]); }
        else { $pdo->prepare("INSERT INTO preco_tabela (fornecedor_id, fornecedor_nome, titulo, validade_inicio, validade_fim, observacao, anexo_id, criado_por, criado_nome, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute(array_merge($vals, [$me, $perms['nome'] ?? null, $now, $now])); $tid = (int)$pdo->lastInsertId(); }
        $insItem = $pdo->prepare("INSERT INTO preco_item (tabela_id, insumo_id, descricao_original, unidade, preco, frete_incluso, observacao, created_at) VALUES (?,?,?,?,?,?,?,?)");
        foreach ((array)($in['itens'] ?? []) as $it) {
            $desc = trim((string)($it['descricao_original'] ?? '')); if ($desc === '') continue;
            $iid = (int)($it['insumo_id'] ?? 0) ?: null;
            $inome = trim((string)($it['insumo_nome'] ?? ''));
            if (!$iid && $inome !== '') { $iid = preco_match_insumo($pdo, $inome);
                if (!$iid) { $pdo->prepare("INSERT INTO preco_insumo (nome, unidade, sinonimos, created_at, updated_at) VALUES (?,?,?,?,?)")
                    ->execute([$inome, trim((string)($it['unidade'] ?? '')), '', $now, $now]); $iid = (int)$pdo->lastInsertId(); } }
            if (!$iid && $desc !== '') { $iid = preco_match_insumo($pdo, $desc); }   // tenta casar pela própria descrição
            if ($iid) preco_add_sinonimo($pdo, $iid, $desc);                          // aprende a redação do fornecedor
            $insItem->execute([$tid, $iid, $desc, trim((string)($it['unidade'] ?? '')),
                ($it['preco'] ?? null) !== null && $it['preco'] !== '' ? (float)$it['preco'] : null,
                !empty($it['frete_incluso']) ? 1 : 0, trim((string)($it['observacao'] ?? '')), $now]);
        }
        $pdo->commit();
        echo json_encode(['ok' => true, 'id' => $tid], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'excluir_tabela') {
        $id = (int)($in['id'] ?? 0); if (!$id) throw new Exception('id obrigatório');
        $pdo->prepare("DELETE FROM preco_item WHERE tabela_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM preco_tabela WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE); exit;
    }

    echo json_encode(['error' => 'ação desconhecida'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
