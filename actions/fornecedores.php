<?php
/**
 * FORNECEDORES + CATEGORIAS do Mapa de Cotações (cockpit / MySQL).
 * Categorias = classificação do fornecedor (M.O. Gesso, Concreto, ...) e da cotação.
 * Fornecedores carregados por import em lote (Excel do sistema antigo — o conector não expõe list_fornecedores).
 *
 * GET ?categorias=1                        -> lista de categorias
 * GET (fornecedores) ?nome=&categoria=&tipo=&itens=&cidade=&limit=&offset=  -> {fornecedores, total, categorias}
 * POST {acao:'fornecedor_salvar', me, id?, nome, categoria, cidade, contato, telefone, whatsapp, email, itens, tipo, cnpj}
 * POST {acao:'fornecedor_excluir', me, id}
 * POST {acao:'categoria_add', me, nome}   /  {acao:'categoria_excluir', me, id}
 * POST {acao:'importar_categorias', me}                 -> lê data/seed/categorias.json (admin)
 * POST {acao:'importar_fornecedores', me, fornecedores[]}-> bulk upsert por (nome) + cria categorias faltantes (admin)
 */
header('Content-Type: application/json; charset=utf-8');
set_time_limit(300);
require_once __DIR__ . '/../includes/db.php';

function forn_editor($pdo, $me) {
    $p = user_perms($pdo, $me);
    if (empty($p['autorizado'])) return null;
    if (!empty($p['perm_admin']) || (($p['editar_escopo'] ?? 'nenhuma') !== 'nenhuma')) return $p;
    return null;
}
function forn_add_categoria($pdo, $nome) {
    $nome = trim((string)$nome); if ($nome === '') return;
    $q = $pdo->prepare("SELECT id FROM cot_categoria WHERE nome=?"); $q->execute([$nome]);
    if (!$q->fetch()) $pdo->prepare("INSERT INTO cot_categoria (nome, created_at) VALUES (?,?)")->execute([$nome, date('c')]);
}

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $cats = $pdo->query("SELECT id, nome FROM cot_categoria ORDER BY nome")->fetchAll();
        if (isset($_GET['categorias'])) { echo json_encode(['categorias'=>$cats], JSON_UNESCAPED_UNICODE); exit; }
        // lista de fornecedores com filtros
        $w = []; $a = [];
        // busca AMPLA (usada pelas sugestões de convite/proposta): casa nome OU itens OU categoria OU cidade.
        // Evita o bug do filtro categoria=AND rígido — a categoria do fornecedor (livre/importada) raramente bate
        // a categoria da cotação, então categoria NUNCA deve zerar uma busca por nome.
        if (trim((string)($_GET['q'] ?? '')) !== '') {
            $t = '%'.trim($_GET['q']).'%';
            $w[] = '(nome LIKE ? OR itens LIKE ? OR categoria LIKE ? OR cidade LIKE ?)';
            array_push($a, $t, $t, $t, $t);
        }
        if (($_GET['nome'] ?? '') !== '')      { $w[] = 'nome LIKE ?';      $a[] = '%'.$_GET['nome'].'%'; }
        if (($_GET['categoria'] ?? '') !== '') { $w[] = 'categoria = ?';    $a[] = $_GET['categoria']; }
        if (($_GET['tipo'] ?? '') !== '')      { $w[] = 'tipo = ?';         $a[] = $_GET['tipo']; }
        if (($_GET['itens'] ?? '') !== '')     { $w[] = 'itens LIKE ?';     $a[] = '%'.$_GET['itens'].'%'; }
        if (($_GET['cidade'] ?? '') !== '')    { $w[] = 'cidade LIKE ?';    $a[] = '%'.$_GET['cidade'].'%'; }
        $where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';
        $tot = $pdo->prepare("SELECT COUNT(*) FROM cot_fornecedor $where"); $tot->execute($a); $total = (int)$tot->fetchColumn();
        $limit = min(500, max(1, (int)($_GET['limit'] ?? 60))); $offset = max(0, (int)($_GET['offset'] ?? 0));
        $q = $pdo->prepare("SELECT * FROM cot_fornecedor $where ORDER BY nome LIMIT $limit OFFSET $offset"); $q->execute($a);
        echo json_encode(['fornecedores'=>$q->fetchAll(), 'total'=>$total, 'categorias'=>$cats,
            'tipos'=>['Fabricante','M.O.','Atacadista','Varejista','Locadora','Distribuidor','Prestador']], JSON_UNESCAPED_UNICODE); exit;
    }

    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $acao = $in['acao'] ?? '';
    $perms = forn_editor($pdo, $in['me'] ?? null);
    if (!$perms) { http_response_code(403); echo json_encode(['error'=>'Sem permissão de edição.']); exit; }

    if ($acao === 'fornecedor_salvar') {
        $nome = trim((string)($in['nome'] ?? '')); if ($nome === '') throw new Exception('nome obrigatório');
        $cols = ['nome','categoria','cidade','contato','telefone','whatsapp','email','itens','tipo','cnpj'];
        $vals = []; foreach ($cols as $c) $vals[$c] = trim((string)($in[$c] ?? ''));
        if ($vals['categoria'] !== '') forn_add_categoria($pdo, $vals['categoria']);
        $id = (int)($in['id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE cot_fornecedor SET nome=?,categoria=?,cidade=?,contato=?,telefone=?,whatsapp=?,email=?,itens=?,tipo=?,cnpj=? WHERE id=?")
                ->execute([$vals['nome'],$vals['categoria'],$vals['cidade'],$vals['contato'],$vals['telefone'],$vals['whatsapp'],$vals['email'],$vals['itens'],$vals['tipo'],$vals['cnpj'],$id]);
        } else {
            $pdo->prepare("INSERT INTO cot_fornecedor (nome,categoria,cidade,contato,telefone,whatsapp,email,itens,tipo,cnpj,ativo,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,1,?)")
                ->execute([$vals['nome'],$vals['categoria'],$vals['cidade'],$vals['contato'],$vals['telefone'],$vals['whatsapp'],$vals['email'],$vals['itens'],$vals['tipo'],$vals['cnpj'],date('c')]);
            $id = (int)$pdo->lastInsertId();
        }
        echo json_encode(['ok'=>true, 'id'=>$id], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'contato_salvar') {   // conferência de contatos (email/telefone/whatsapp) — carimba a "última atualização" do campo que mudou
        $id = (int)($in['id'] ?? 0); if (!$id) throw new Exception('id obrigatório');
        $cur = $pdo->prepare("SELECT email, telefone, whatsapp, contatos_at FROM cot_fornecedor WHERE id=?"); $cur->execute([$id]); $c = $cur->fetch();
        if (!$c) throw new Exception('fornecedor não encontrado');
        $at = json_decode((string)($c['contatos_at'] ?? ''), true); if (!is_array($at)) $at = [];
        $now = date('c'); $sets = []; $args = [];
        foreach (['email','telefone','whatsapp'] as $f) {
            if (array_key_exists($f, $in)) { $v = trim((string)$in[$f]); $sets[] = "$f=?"; $args[] = $v;
                if ($v !== trim((string)($c[$f] ?? ''))) $at[$f] = $now; }   // carimba só quando o valor muda
        }
        if (!$sets) throw new Exception('nada a salvar');
        $sets[] = 'contatos_at=?'; $args[] = json_encode($at); $args[] = $id;
        $pdo->prepare("UPDATE cot_fornecedor SET " . implode(',', $sets) . " WHERE id=?")->execute($args);
        echo json_encode(['ok'=>true, 'contatos_at'=>$at], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'fornecedor_excluir') {
        $id = (int)($in['id'] ?? 0); if (!$id) throw new Exception('id obrigatório');
        $pdo->prepare("DELETE FROM cot_fornecedor WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'categoria_add') { forn_add_categoria($pdo, $in['nome'] ?? ''); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit; }
    if ($acao === 'categoria_excluir') {
        $id = (int)($in['id'] ?? 0); if (!$id) throw new Exception('id obrigatório');
        $pdo->prepare("DELETE FROM cot_categoria WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'importar_categorias') {
        if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Import é só admin.']); exit; }
        $seed = json_decode(@file_get_contents(SEED_DIR . '/categorias.json'), true);
        if (!is_array($seed)) throw new Exception('seed categorias.json ausente');
        $n = 0; $pdo->beginTransaction();
        foreach ($seed as $nome) { $nome = trim((string)$nome); if ($nome === '') continue;
            $q = $pdo->prepare("SELECT id FROM cot_categoria WHERE nome=?"); $q->execute([$nome]);
            if (!$q->fetch()) { $pdo->prepare("INSERT INTO cot_categoria (nome, created_at) VALUES (?,?)")->execute([$nome, date('c')]); $n++; }
        }
        $pdo->commit();
        echo json_encode(['ok'=>true, 'inseridas'=>$n, 'total'=>(int)$pdo->query("SELECT COUNT(*) FROM cot_categoria")->fetchColumn()], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'importar_fornecedores') {
        if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Import é só admin.']); exit; }
        $lista = (array)($in['fornecedores'] ?? []);
        if (!$lista) throw new Exception('nenhum fornecedor recebido');
        $n = 0; $upd = 0; $cats = [];
        $pdo->beginTransaction();
        $sel = $pdo->prepare("SELECT id FROM cot_fornecedor WHERE nome=? LIMIT 1");
        $ins = $pdo->prepare("INSERT INTO cot_fornecedor (nome,categoria,cidade,contato,telefone,whatsapp,email,itens,tipo,cnpj,ativo,ext_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,1,?,?)");
        $up  = $pdo->prepare("UPDATE cot_fornecedor SET categoria=?,cidade=?,contato=?,telefone=?,whatsapp=?,email=?,itens=?,tipo=?,cnpj=? WHERE id=?");
        foreach ($lista as $f) {
            $nome = trim((string)($f['nome'] ?? '')); if ($nome === '') continue;
            $g = fn($k)=>trim((string)($f[$k] ?? ''));
            if ($g('categoria') !== '') $cats[$g('categoria')] = 1;
            $sel->execute([$nome]); $ex = $sel->fetchColumn();
            if ($ex) { $up->execute([$g('categoria'),$g('cidade'),$g('contato'),$g('telefone'),$g('whatsapp'),$g('email'),$g('itens'),$g('tipo'),$g('cnpj'),(int)$ex]); $upd++; }
            else { $ins->execute([$nome,$g('categoria'),$g('cidade'),$g('contato'),$g('telefone'),$g('whatsapp'),$g('email'),$g('itens'),$g('tipo'),$g('cnpj'),$g('ext_id'),date('c')]); $n++; }
        }
        foreach (array_keys($cats) as $cn) forn_add_categoria($pdo, $cn);
        $pdo->commit();
        echo json_encode(['ok'=>true, 'inseridos'=>$n, 'atualizados'=>$upd, 'total'=>(int)$pdo->query("SELECT COUNT(*) FROM cot_fornecedor")->fetchColumn()], JSON_UNESCAPED_UNICODE); exit;
    }

    throw new Exception('ação inválida');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
