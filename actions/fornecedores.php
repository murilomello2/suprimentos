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
    // fornecedor é LISTA-MESTRE compartilhada — cadastrar/editar liberado por PAPEL (admin/gerente/comprador),
    // NÃO pelo escopo de edição de obra (compradores com 'nenhuma' ficavam sem o botão + 403; decisão 23/jul)
    if (!empty($p['perm_admin'])) return $p;
    if (in_array(($p['papel'] ?? ''), ['gerente', 'comprador'], true)) return $p;
    if (($p['editar_escopo'] ?? 'nenhuma') !== 'nenhuma') return $p;
    return null;
}
function forn_add_categoria($pdo, $nome) {
    $nome = trim((string)$nome); if ($nome === '') return;
    $q = $pdo->prepare("SELECT id FROM cot_categoria WHERE nome=?"); $q->execute([$nome]);
    if (!$q->fetch()) $pdo->prepare("INSERT INTO cot_categoria (nome, created_at) VALUES (?,?)")->execute([$nome, date('c')]);
}

try {
    $pdo = db();

    /* Acento -> ASCII por MAPA, não por iconv: com //TRANSLIT o resultado depende do locale do
       servidor, e aqui o ç era simplesmente descartado (a categoria "Aço" virava "Ao" no nome do
       arquivo exportado). Mapa fixo é feio mas é previsível. */
    if (!function_exists('forn_sem_acento')) {
        function forn_sem_acento($s) {
            return strtr((string)$s, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','ë'=>'e',
                'í'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ú'=>'u','û'=>'u','ü'=>'u',
                'ç'=>'c','ñ'=>'n','Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','É'=>'E','Ê'=>'E','Ë'=>'E',
                'Í'=>'I','Î'=>'I','Ï'=>'I','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','Ú'=>'U','Û'=>'U','Ü'=>'U',
                'Ç'=>'C','Ñ'=>'N']);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $cats = $pdo->query("SELECT id, nome FROM cot_categoria ORDER BY nome")->fetchAll();
        if (isset($_GET['categorias'])) { echo json_encode(['categorias'=>$cats], JSON_UNESCAPED_UNICODE); exit; }

        /* DUPLICADOS: mesmo CNPJ (14 dígitos) em mais de um cadastro. Devolve junto o PESO de cada um
           — quantas cotações, propostas, anexos e tabelas de preço apontam pra ele — porque é isso que
           decide quem sobrevive: some o cadastro vazio, fica o que tem histórico. */
        if (isset($_GET['duplicados'])) {
            $todos = $pdo->query("SELECT id,nome,razao_social,categoria,tipo,cidade,contato,telefone,email,cnpj,itens,
                                         totvs_compras_2026,totvs_valor_2026,created_at
                                  FROM cot_fornecedor ORDER BY id")->fetchAll();
            $peso = [];
            foreach ([['cotacao_fornecedor','convites'], ['cotacao_proposta','propostas'],
                      ['cotacao_anexo','anexos'], ['preco_tabela','tabelas_preco']] as $t) {
                try { foreach ($pdo->query("SELECT fornecedor_id id, COUNT(*) n FROM {$t[0]} WHERE fornecedor_id IS NOT NULL GROUP BY fornecedor_id") as $r)
                        $peso[(int)$r['id']][$t[1]] = (int)$r['n']; } catch (Throwable $e) {}
            }
            $g = [];
            foreach ($todos as $f) {
                $c = preg_replace('/\D/', '', (string)$f['cnpj']);
                if (strlen($c) !== 14) continue;
                $f['uso'] = $peso[(int)$f['id']] ?? [];
                $f['uso_total'] = array_sum($f['uso']);
                $g[$c][] = $f;
            }
            $out = [];
            foreach ($g as $c => $v) {
                if (count($v) < 2) continue;
                usort($v, fn($a, $b) => ($b['uso_total'] <=> $a['uso_total']) ?: ($a['id'] <=> $b['id']));
                $out[] = ['cnpj' => $c, 'n' => count($v), 'cadastros' => $v,
                          'trivial' => ($v[count($v)-1]['uso_total'] === 0)];   // o que vai sumir não tem histórico
            }
            usort($out, fn($a, $b) => ($a['trivial'] <=> $b['trivial']) ?: strcmp($a['cadastros'][0]['nome'], $b['cadastros'][0]['nome']));
            echo json_encode(['grupos' => $out, 'total' => count($out)], JSON_UNESCAPED_UNICODE); exit;
        }
        // lista de fornecedores com filtros
        $w = []; $a = [];
        // busca AMPLA (usada pelas sugestões de convite/proposta): casa nome OU itens OU categoria OU cidade.
        // Evita o bug do filtro categoria=AND rígido — a categoria do fornecedor (livre/importada) raramente bate
        // a categoria da cotação, então categoria NUNCA deve zerar uma busca por nome.
        if (trim((string)($_GET['q'] ?? '')) !== '') {
            $t = '%'.trim($_GET['q']).'%';
            $w[] = '(nome LIKE ? OR itens LIKE ? OR categoria LIKE ? OR cidade LIKE ? OR cnpj LIKE ?)';
            array_push($a, $t, $t, $t, $t, $t);
        }
        if (($_GET['nome'] ?? '') !== '')      { $w[] = 'nome LIKE ?';      $a[] = '%'.$_GET['nome'].'%'; }
        if (($_GET['categoria'] ?? '') !== '') { $w[] = 'categoria = ?';    $a[] = $_GET['categoria']; }
        if (($_GET['tipo'] ?? '') !== '')      { $w[] = 'tipo = ?';         $a[] = $_GET['tipo']; }
        if (($_GET['itens'] ?? '') !== '')     { $w[] = 'itens LIKE ?';     $a[] = '%'.$_GET['itens'].'%'; }
        if (($_GET['cidade'] ?? '') !== '')    { $w[] = 'cidade LIKE ?';    $a[] = '%'.$_GET['cidade'].'%'; }
        $where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';

        /* EXPORTAÇÃO CSV — leva TODAS as linhas do recorte atual (os mesmos filtros da tela), não só a
           página. Pedido do Murilo: filtrar por categoria/tipo/busca e exportar exatamente aquilo.
           Exige usuário autorizado: a listagem paginada é aberta, mas um dump da base inteira com
           telefone e e-mail de 1.400 fornecedores é outra coisa. */
        if (isset($_GET['csv'])) {
            $perms = user_perms($pdo, $_GET['me'] ?? null);
            if (empty($perms['autorizado'])) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(['error'=>'Não autorizado.']); exit; }
            $q = $pdo->prepare("SELECT nome, categoria, tipo, cidade, contato, telefone, whatsapp, email, cnpj, itens
                                FROM cot_fornecedor $where ORDER BY nome");
            $q->execute($a);
            $nome = 'fornecedores-' . date('Y-m-d');
            foreach (['categoria'=>'cat', 'tipo'=>'tipo', 'nome'=>'busca', 'itens'=>'itens', 'cidade'=>'cidade'] as $k => $sfx)
                if (trim((string)($_GET[$k] ?? '')) !== '') $nome .= '-' . $sfx . '_' . preg_replace('/[^A-Za-z0-9]+/', '',
                    forn_sem_acento((string)$_GET[$k]));
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nome . '.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));   // BOM: sem isso o Excel pt-BR abre "AÇO" como "AÃ‡O"
            // ; é o separador que o Excel em português espera por padrão
            fputcsv($out, ['Nome','Categoria','Tipo','Cidade','Contato','Telefone','WhatsApp','E-mail','CNPJ','Itens'], ';');
            foreach ($q as $r) fputcsv($out, [$r['nome'],$r['categoria'],$r['tipo'],$r['cidade'],$r['contato'],
                                              $r['telefone'],$r['whatsapp'],$r['email'],$r['cnpj'],$r['itens']], ';');
            fclose($out); exit;
        }

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
            // ANTI-DUPLICAÇÃO: sem id, reaproveita um fornecedor existente com o MESMO CNPJ (dígitos) ou o MESMO
            // nome (case-insensitive). Fecha o furo do cadastro-por-IA (proposta de PDF) que criava fornecedor repetido.
            $dupe = null;
            $cnpjDig = preg_replace('/\D/', '', $vals['cnpj']);
            // só casa por CNPJ se for um CNPJ/CPF REAL — ignora placeholders (00000000000, 11111111111, muito curto)
            $cnpjValido = strlen($cnpjDig) >= 11 && !preg_match('/^(\d)\1+$/', $cnpjDig);
            if ($cnpjValido) {
                $q = $pdo->prepare("SELECT id FROM cot_fornecedor WHERE REPLACE(REPLACE(REPLACE(REPLACE(cnpj,'.',''),'/',''),'-',''),' ','')=? AND (ativo=1 OR ativo IS NULL) ORDER BY id LIMIT 1");
                $q->execute([$cnpjDig]); $dupe = $q->fetchColumn() ?: null;
            }
            if (!$dupe) {
                $q = $pdo->prepare("SELECT id FROM cot_fornecedor WHERE LOWER(TRIM(nome))=LOWER(TRIM(?)) AND (ativo=1 OR ativo IS NULL) ORDER BY id LIMIT 1");
                $q->execute([$vals['nome']]); $dupe = $q->fetchColumn() ?: null;
            }
            if ($dupe) {
                // reaproveita: atualiza só os campos vindos PREENCHIDOS (não apaga o que o fornecedor já tinha)
                $id = (int)$dupe; $sets = []; $sv = [];
                foreach ($cols as $c) { if ($c === 'nome') continue; if ($vals[$c] !== '') { $sets[] = "$c=?"; $sv[] = $vals[$c]; } }
                if ($sets) { $sv[] = $id; $pdo->prepare("UPDATE cot_fornecedor SET " . implode(',', $sets) . " WHERE id=?")->execute($sv); }
                echo json_encode(['ok'=>true, 'id'=>$id, 'dedup'=>true], JSON_UNESCAPED_UNICODE); exit;
            }
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
        // EXCLUIR da lista-mestre é mais destrutivo que cadastrar: só admin/gerente (comprador cadastra/edita, não apaga)
        if (empty($perms['perm_admin']) && (($perms['papel'] ?? '') !== 'gerente')) { http_response_code(403); echo json_encode(['error'=>'Excluir fornecedor é só para administrador/gerente.'], JSON_UNESCAPED_UNICODE); exit; }
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

    /* ENRIQUECER COM O TOTVS — preenche razão social, CNPJ e código do fornecedor a partir do que o
       TOTVS já sabe (pedidos_itens traz fornecedor_cnpj/nome/fantasia/cod de quem tem pedido).
       ⚠️ REGRA DE OURO: só escreve em campo VAZIO. Nunca sobrescreve CNPJ existente — quando os dois
       lados discordam é decisão humana (pode ser filial diferente, homônimo ou erro de cadastro).
       O 'forcar_cnpj' existe só p/ o caso de digitação comprovada (CNPJ com nº de dígitos inválido). */
    /* FUNDIR duplicados: repontua o histórico para o cadastro que fica e apaga os outros.
       Não existe FK/CASCADE no banco (nenhuma tabela cot* tem), então o repontamento é manual e
       explícito — e por isso mesmo cada tabela tocada é registrada na resposta. */
    if ($acao === 'fundir_fornecedores') {
        if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Apenas administradores.']); exit; }
        $fica = (int)($in['manter_id'] ?? 0);
        $vao  = array_values(array_unique(array_map('intval', (array)($in['remover_ids'] ?? []))));
        $vao  = array_values(array_filter($vao, fn($x) => $x > 0 && $x !== $fica));
        if (!$fica || !$vao) throw new Exception('informe manter_id e remover_ids');
        $st = $pdo->prepare("SELECT id, nome, cnpj FROM cot_fornecedor WHERE id=?");
        $st->execute([$fica]); $alvo = $st->fetch();
        if (!$alvo) throw new Exception('cadastro que ficaria não existe');
        $in_ = implode(',', $vao);
        $movidos = [];
        $pdo->beginTransaction();
        foreach (['cotacao_fornecedor', 'cotacao_proposta', 'cotacao_anexo', 'preco_tabela'] as $t) {
            try {
                $q = $pdo->prepare("UPDATE $t SET fornecedor_id=? WHERE fornecedor_id IN ($in_)");
                $q->execute([$fica]);
                if ($q->rowCount()) $movidos[$t] = $q->rowCount();
            } catch (Throwable $e) { /* tabela pode não existir num deploy parcial */ }
        }
        // o nome usado nas propostas/convites é texto solto: alinha com o sobrevivente
        foreach (['cotacao_fornecedor', 'cotacao_proposta', 'cotacao_anexo'] as $t) {
            try { $pdo->prepare("UPDATE $t SET fornecedor_nome=? WHERE fornecedor_id=?")->execute([$alvo['nome'], $fica]); }
            catch (Throwable $e) {}
        }
        $del = $pdo->prepare("DELETE FROM cot_fornecedor WHERE id IN ($in_)"); $del->execute();
        $pdo->commit();
        echo json_encode(['ok'=>true, 'manteve'=>['id'=>$fica, 'nome'=>$alvo['nome']],
                          'removidos'=>$vao, 'historico_movido'=>$movidos], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'enriquecer_totvs') {
        if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Apenas administradores.']); exit; }
        $lista = (array)($in['fornecedores'] ?? []);
        if (!$lista) throw new Exception('nada recebido');
        // colunas aditivas (o projeto não tem migration runner; cada módulo cria a sua)
        foreach ([['razao_social','VARCHAR(255)'], ['totvs_cod','VARCHAR(40)'], ['totvs_compras_2026','INT'], ['totvs_valor_2026','DOUBLE']] as $c) {
            try { $pdo->query("SELECT {$c[0]} FROM cot_fornecedor LIMIT 1"); }
            catch (Throwable $e) { try { $pdo->exec("ALTER TABLE cot_fornecedor ADD COLUMN {$c[0]} {$c[1]}"); } catch (Throwable $e2) {} }
        }
        $sel = $pdo->prepare("SELECT id, cnpj, razao_social, email FROM cot_fornecedor WHERE id=? LIMIT 1");
        $n = 0; $cnpjNovo = 0; $razaoNova = 0; $emailNovo = 0; $pulou = 0; $log = [];
        $pdo->beginTransaction();
        foreach ($lista as $f) {
            $id = (int)($f['id'] ?? 0); if (!$id) continue;
            $sel->execute([$id]); $atual = $sel->fetch();
            if (!$atual) { $pulou++; continue; }
            $sets = []; $args = [];
            $meu = preg_replace('/\D/', '', (string)($atual['cnpj'] ?? ''));
            $novo = preg_replace('/\D/', '', (string)($f['cnpj'] ?? ''));
            $forcar = !empty($f['forcar_cnpj']);
            if ($novo !== '' && ($meu === '' || ($forcar && strlen($meu) !== 14))) {
                $sets[] = 'cnpj=?'; $args[] = $novo; $cnpjNovo++;
                $log[] = ['id'=>$id, 'campo'=>'cnpj', 'de'=>$meu, 'para'=>$novo];
            }
            // e-mail: mesma regra do CNPJ — só entra em campo VAZIO. Quando o cockpit tem um endereço
            // e o envio real usou outro, é troca de contato do fornecedor: decisão humana, vai p/ lista.
            if (trim((string)($f['email'] ?? '')) !== '' && trim((string)($atual['email'] ?? '')) === '') {
                $sets[] = 'email=?'; $args[] = trim((string)$f['email']); $emailNovo++;
                $log[] = ['id'=>$id, 'campo'=>'email', 'de'=>'', 'para'=>trim((string)$f['email'])];
            }
            if (trim((string)($f['razao_social'] ?? '')) !== '' && trim((string)($atual['razao_social'] ?? '')) === '') {
                $sets[] = 'razao_social=?'; $args[] = trim((string)$f['razao_social']); $razaoNova++;
            }
            foreach (['totvs_cod'=>'totvs_cod', 'compras_2026'=>'totvs_compras_2026', 'valor_2026'=>'totvs_valor_2026'] as $k => $col)
                if (isset($f[$k]) && $f[$k] !== '' && $f[$k] !== null) { $sets[] = "$col=?"; $args[] = $f[$k]; }
            if (!$sets) { $pulou++; continue; }
            $args[] = $id;
            $pdo->prepare('UPDATE cot_fornecedor SET ' . implode(',', $sets) . ' WHERE id=?')->execute($args);
            $n++;
        }
        $pdo->commit();
        echo json_encode(['ok'=>true, 'atualizados'=>$n, 'cnpj_preenchido'=>$cnpjNovo, 'razao_preenchida'=>$razaoNova, 'email_preenchido'=>$emailNovo,
                          'sem_mudanca'=>$pulou, 'log'=>$log], JSON_UNESCAPED_UNICODE); exit;
    }


    /**
     * ESPELHO DO CADASTRO DE FORNECEDORES DO TOTVS.
     *
     * O TOTVS é a base oficial — o próprio Murilo lembrou que a certeza de qual fornecedor é vem do
     * CODCFO. Este espelho existe para duas coisas:
     *   1. o PDF do pedido mostrar razão social, CNPJ, cidade/UF e e-mail corretos;
     *   2. a fila de Envio achar o e-mail do fornecedor por CODCFO — a chave exata, que não depende
     *      de como alguém digitou o nome.
     *
     * NÃO substitui o nosso cadastro: o Murilo já avisou que "muitos dos e-mails aí estão
     * desatualizados". A ordem de consulta é sempre cadastro do cockpit primeiro, TOTVS depois.
     */

    /**
     * CADASTRAR/CORRIGIR O E-MAIL DE UM FORNECEDOR direto da fila de Envio.
     *
     * O Murilo pediu: na lista de bloqueados por "fornecedor sem e-mail", um botão que abra o
     * cadastro, preencha e volte. Sem isso a pessoa larga a fila, vai na tela de Fornecedores,
     * procura pelo nome (que às vezes está escrito diferente) e perde o fio.
     *
     * A chave é o CODCFO — o mesmo que amarra o pedido ao cadastro. Se não existir cadastro nosso
     * para aquele fornecedor, um é criado com o que o TOTVS já sabe (razão, CNPJ, cidade), para o
     * e-mail não ficar órfão.
     */
    if ($acao === 'email_rapido') {
        /* Fornecedor é lista-mestre compartilhada: quem edita fornecedor edita aqui também. */
        if (!forn_editor($pdo, $in['me'] ?? null)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão para editar fornecedores.']); exit; }
        $email = trim((string)($in['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('E-mail inválido: ' . $email);
        $cod  = ltrim(preg_replace('/\D+/', '', (string)($in['cod'] ?? '')), '0');
        $cnpj = preg_replace('/\D+/', '', (string)($in['cnpj'] ?? ''));
        $nome = trim((string)($in['nome'] ?? ''));

        $id = 0;
        if ($cnpj !== '') {
            $q = $pdo->prepare("SELECT id FROM cot_fornecedor WHERE REPLACE(REPLACE(REPLACE(REPLACE(cnpj,'.',''),'/',''),'-',''),' ','')=? ORDER BY id LIMIT 1");
            $q->execute([$cnpj]); $id = (int)$q->fetchColumn();
        }
        if (!$id && $cod !== '') {
            $q = $pdo->prepare("SELECT id FROM cot_fornecedor WHERE totvs_cod=? ORDER BY id LIMIT 1");
            $q->execute([$cod]); $id = (int)$q->fetchColumn();
        }
        if (!$id && $nome !== '') {
            $q = $pdo->prepare("SELECT id FROM cot_fornecedor WHERE LOWER(TRIM(nome))=LOWER(TRIM(?)) ORDER BY id LIMIT 1");
            $q->execute([$nome]); $id = (int)$q->fetchColumn();
        }

        $criado = false;
        if (!$id) {
            // ainda não temos cadastro: nasce com o que o TOTVS sabe
            $cid = ''; $razao = $nome;
            try {
                $q = $pdo->prepare("SELECT nome, cnpj, cidade FROM totvs_fornecedor WHERE codcfo=? LIMIT 1");
                $q->execute([$cod]);
                if ($t = $q->fetch()) { $razao = trim((string)$t['nome']) ?: $nome; $cid = trim((string)$t['cidade']); }
            } catch (Throwable $e) {}
            $ins = $pdo->prepare("INSERT INTO cot_fornecedor (nome, cnpj, cidade, email, ativo) VALUES (?,?,?,?,1)");
            $ins->execute([$razao ?: ('Fornecedor ' . $cod), (string)($in['cnpj'] ?? ''), $cid, $email]);
            $id = (int)$pdo->lastInsertId(); $criado = true;
        } else {
            $pdo->prepare("UPDATE cot_fornecedor SET email=? WHERE id=?")->execute([$email, $id]);
        }
        // grava o código do TOTVS se ainda faltava — é ele que casa o pedido com o cadastro
        if ($cod !== '') { try { $pdo->prepare("UPDATE cot_fornecedor SET totvs_cod=? WHERE id=? AND (totvs_cod IS NULL OR totvs_cod='')")->execute([$cod, $id]); } catch (Throwable $e) {} }
        $q = $pdo->prepare("SELECT id, nome, cnpj, cidade, contato, telefone, email, totvs_cod FROM cot_fornecedor WHERE id=?");
        $q->execute([$id]);
        echo json_encode(['ok'=>true, 'criado'=>$criado, 'fornecedor'=>$q->fetch()], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'importar_totvs') {
        if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Apenas administradores.']); exit; }
        $E = (defined('DB_DRIVER') && DB_DRIVER === 'mysql') ? 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
        if (defined('DB_DRIVER') && DB_DRIVER === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS totvs_fornecedor (
                codcfo VARCHAR(20) NOT NULL, cnpj VARCHAR(24), nome VARCHAR(255), fantasia VARCHAR(255),
                cidade VARCHAR(120), uf VARCHAR(4), email VARCHAR(255), atualizado VARCHAR(40),
                PRIMARY KEY (codcfo), KEY idx_tf_cnpj (cnpj), KEY idx_tf_nome (nome)
            ) $E");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS totvs_fornecedor (codcfo TEXT PRIMARY KEY, cnpj TEXT, nome TEXT, fantasia TEXT, cidade TEXT, uf TEXT, email TEXT, atualizado TEXT)");
        }
        if (!empty($in['limpar'])) $pdo->exec("DELETE FROM totvs_fornecedor");
        $lista = (array)($in['linhas'] ?? []);
        $ins = $pdo->prepare("INSERT INTO totvs_fornecedor (codcfo,cnpj,nome,fantasia,cidade,uf,email,atualizado)
                              VALUES (?,?,?,?,?,?,?,?)");
        $upd = $pdo->prepare("UPDATE totvs_fornecedor SET cnpj=?,nome=?,fantasia=?,cidade=?,uf=?,email=?,atualizado=? WHERE codcfo=?");
        $n = 0; $agora = date('c');
        $pdo->beginTransaction();
        foreach ($lista as $l) {
            $cod = ltrim(preg_replace('/\D+/', '', (string)($l['cod'] ?? '')), '0');
            if ($cod === '') continue;
            $a = [(string)($l['cnpj'] ?? ''), (string)($l['nome'] ?? ''), (string)($l['fantasia'] ?? ''),
                  (string)($l['cidade'] ?? ''), (string)($l['uf'] ?? ''), (string)($l['email'] ?? ''), $agora];
            $upd->execute(array_merge($a, [$cod]));
            if (!$upd->rowCount()) { try { $ins->execute(array_merge([$cod], $a)); } catch (Throwable $e) {} }
            $n++;
        }
        $pdo->commit();
        $tot = (int)$pdo->query("SELECT COUNT(*) FROM totvs_fornecedor")->fetchColumn();
        echo json_encode(['ok'=>true, 'gravados'=>$n, 'total'=>$tot]); exit;
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
