<?php
/**
 * MAPA DE COTAÇÕES (reconstruído no cockpit / MySQL). Núcleo: cotação → itens → propostas de
 * fornecedores (preço por item) → MAPA comparativo (melhor preço por item + totais).
 * Vínculo opcional a um item do radar (servico_id) e a uma obra (obra_id). Standalone é permitido.
 *
 * GET  ?id=N                      -> cotação completa (header + itens + propostas + mapa computado)
 * GET  (?obra=N opcional)         -> lista de cotações (+ resumo por cotação: n_propostas, melhor_oferta)
 * POST {acao:'criar', me, obra_id?, servico_id?, titulo, categoria, tipo_servico, verba, descricao, itens[]}
 * POST {acao:'proposta', me, cotacao_id, proposta_id?, fornecedor_id?, fornecedor_nome, prazo, observacoes, itens[],
 *       nova_opcao?, opcao_rotulo?}   nova_opcao=1 → 2ª/3ª forma de o MESMO fornecedor apresentar a proposta
 * POST {acao:'status', me, cotacao_id, status?, aprovacao?}
 * POST {acao:'excluir', me, cotacao_id}
 * POST {acao:'excluir_proposta', me, proposta_id}
 * POST {acao:'proposta_desqualificar', me, proposta_id, motivo, justificativa?, desfazer?}
 */
if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) @ob_start('ob_gzhandler');   // PERF: gzip do JSON (hosting não faz)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/obra_registry.php';   // cadastro único: resolver/promover obra
require_once __DIR__ . '/../includes/coligadas.php';       // FASE 2: coligada_cod_de_nome p/ agrupar PC por coligada

// Teto da lista. Os filtros/busca/ordenação da tela são CLIENT-SIDE (varrem a lista toda), então o servidor
// manda tudo e o front pagina de 30 em 30. Era 500 — não cabe o histórico importado do sistema antigo (~760).
define('COT_LISTA_MAX', 3000);

/* Números do contrato com a ETR (assinado ago/2026), usados na apuração mensal:
   5.1  — success fee = 40% da Economia Prevista (os 60% restantes ficam com a CAPREM);
   5.3.1 — R$ 67.000/mês é ADIANTAMENTO compensável: fatura-se o MAIOR entre ele e o fee do mês,
           vedada a cumulação na mesma competência.
   Ficam aqui, nomeados, em vez de espalhados como número mágico no meio da conta. */
define('COT_ETR_PCT', 40);
define('COT_ADIANTAMENTO', 67000.00);

// O catálogo de motivos de desqualificação (cot_desq_motivos/label/texto) mora em includes/db.php:
// a API de leitura também precisa traduzir o código do motivo.

/* ───────── FECHAMENTO DA NEGOCIAÇÃO ─────────
   A fotografia assinada da decisão de compra. A rodada 1 é a RÉGUA — o Preço Inicial de Referência,
   o que a Caprem compraria sozinha antes de negociar. A última é o que virou pedido. O ganho é a
   diferença, e é sobre ele que a consultoria é remunerada — por isso o fechamento aprovado é
   imutável e cada ato fica no histórico.

   Quem aprova: rodada 1 é homologação do gerente (ele confere se a rodada foi bem feita e pode
   DEVOLVER pedindo mais fornecedores); da rodada 2 em diante é assinatura de quem tem alçada. */
function cot_fech_origens() {
    return [
        'mapa_1a_rodada'  => 'Menor proposta qualificada da 1ª rodada',
        'preco_vigente'   => 'Preço vigente (tabela/contrato do fornecedor)',
        'ultimo_pc'       => 'Último preço pago (pedido de compra)',
        'forn_exclusivo'  => 'Fornecedor exclusivo — primeira cotação dele',
        'outra'           => 'Outra origem (descrita na justificativa)',
    ];
}
function cot_fech_origem_label($c) { $m = cot_fech_origens(); return $m[(string)$c] ?? ''; }
/* Pode VER A APURAÇÃO DE GANHOS. Separada da alçada de aprovar de propósito: o gerente aprova E vê;
   o diretor precisa das duas; a consultoria vê e nunca aprova; e o COMPRADOR não vê — decisão do
   Murilo (12/08/2026): quanto se ganhou por negociação vira comentário entre áreas, e o comprador
   não deve carregar isso. Um comprador específico só passa a ver se ele marcar a permissão.
   O corte é AQUI, no servidor: esconder só na tela deixaria o valor viajando para o navegador. */
function cot_pode_ver_ganhos($pdo, $me) {
    $p = user_perms($pdo, $me);
    if (empty($p['autorizado'])) return false;
    return !empty($p['perm_admin']) || (($p['papel'] ?? '') === 'gerente') || !empty($p['perm_ganhos']);
}
// pode APROVAR/DEVOLVER um fechamento: admin, gerente ou quem recebeu a permissão específica (diretor)
function cot_pode_aprovar_fechamento($pdo, $me) {
    $p = user_perms($pdo, $me);
    if (empty($p['autorizado'])) return false;
    return !empty($p['perm_admin']) || (($p['papel'] ?? '') === 'gerente') || !empty($p['perm_fechamento']);
}
// linhas + cabeçalho de um fechamento
function cot_fech_linhas($pdo, $fid) {
    $q = $pdo->prepare("SELECT * FROM cotacao_fechamento_linha WHERE fechamento_id=? ORDER BY id"); $q->execute([(int)$fid]);
    $out = [];
    foreach ($q->fetchAll() as $l) {
        $out[] = ['id'=>(int)$l['id'], 'cotacao_item_id'=>(int)$l['cotacao_item_id'],
                  'proposta_id'=>$l['proposta_id'] ? (int)$l['proposta_id'] : null,
                  'origem'=>(string)$l['origem'], 'origem_ref'=>(string)$l['origem_ref'],
                  'fornecedor_id'=>$l['fornecedor_id'] ? (int)$l['fornecedor_id'] : null,
                  'fornecedor_nome'=>(string)$l['fornecedor_nome'],
                  'preco_unit'=>$l['preco_unit'] !== null ? (float)$l['preco_unit'] : null,
                  'quantidade'=>$l['quantidade'] !== null ? (float)$l['quantidade'] : null,
                  'preco_total'=>$l['preco_total'] !== null ? (float)$l['preco_total'] : null,
                  'lote'=>(string)$l['lote'], 'justificativa'=>(string)$l['justificativa']];
    }
    return $out;
}
/* GANHO = Σ [ (unit da régua − unit do fechado) × quantidade FECHADA ].
   Por preço UNITÁRIO, nunca total contra total: a quantidade muda entre as rodadas e entre o
   fechamento e o PC, e é essa mesma fórmula que permite recalcular quando o pedido é cancelado ou
   reduzido. Item sem par nos dois lados contribui zero e é sinalizado — item que a consultoria
   trouxe depois não tem contra o que ser medido, e seria porta para inflar ganho. */
function cot_fech_ganho($base, $final) {
    if (!$base || !$final) return null;
    $unitBase = [];   // item => menor unitário da régua (se o item foi dividido, a régua é a mais barata dele)
    foreach ($base['linhas'] as $l) {
        $i = $l['cotacao_item_id']; $u = $l['preco_unit'];
        if ($u === null) continue;
        if (!isset($unitBase[$i]) || $u < $unitBase[$i]) $unitBase[$i] = $u;
    }
    $ganho = 0.0; $totalFinal = 0.0; $itens = []; $semBase = 0;
    foreach ($final['linhas'] as $l) {
        $i = $l['cotacao_item_id']; $q = (float)($l['quantidade'] ?? 0); $u = $l['preco_unit'];
        $totalFinal += (float)($l['preco_total'] ?? 0);
        if ($u === null || $q <= 0) continue;
        if (!isset($unitBase[$i])) { $semBase++; continue; }
        $d = ($unitBase[$i] - $u) * $q;
        $ganho += $d;
        $itens[] = ['cotacao_item_id'=>$i, 'unit_base'=>$unitBase[$i], 'unit_final'=>$u,
                    'quantidade'=>$q, 'ganho'=>round($d, 2),
                    'pct'=>$unitBase[$i] > 0 ? round(($unitBase[$i] - $u) / $unitBase[$i] * 100, 2) : null];
    }
    $totalBase = 0.0;
    foreach ($final['linhas'] as $l) {
        $i = $l['cotacao_item_id']; $q = (float)($l['quantidade'] ?? 0);
        if (isset($unitBase[$i]) && $q > 0) $totalBase += $unitBase[$i] * $q;
    }
    return ['rodada_base'=>(int)$base['rodada'], 'rodada_final'=>(int)$final['rodada'],
            'total_base'=>round($totalBase, 2), 'total_final'=>round($totalFinal, 2),
            'ganho'=>round($ganho, 2), 'pct'=>$totalBase > 0 ? round($ganho / $totalBase * 100, 2) : null,
            'itens'=>$itens, 'itens_sem_base'=>$semBase,
            'etr'=>!empty($final['etr_participou'])];
}
// todos os fechamentos da cotação + o cálculo do ganho entre a régua e o contratado
function cot_fechamentos($pdo, $cid) {
    try { $q = $pdo->prepare("SELECT * FROM cotacao_fechamento WHERE cotacao_id=? ORDER BY rodada, id"); $q->execute([(int)$cid]); }
    catch (Throwable $e) { return ['fechamentos'=>[], 'ganho'=>null]; }   // tabela ainda não criada (deploy parcial)
    $fs = [];
    foreach ($q->fetchAll() as $f) {
        $f['id'] = (int)$f['id']; $f['rodada'] = (int)$f['rodada'];
        $f['etr_participou'] = (int)($f['etr_participou'] ?? 0);
        $f['total'] = $f['total'] !== null ? (float)$f['total'] : null;
        $f['origem_label'] = cot_fech_origem_label($f['origem_preco'] ?? '');
        $f['linhas'] = cot_fech_linhas($pdo, $f['id']);
        $fs[] = $f;
    }
    // régua = 1º APROVADO; contratado = último APROVADO. Rascunho não mede nada.
    $aprov = array_values(array_filter($fs, fn($x) => ($x['status'] ?? '') === 'homologado'));
    $ganho = count($aprov) >= 2 ? cot_fech_ganho($aprov[0], $aprov[count($aprov) - 1]) : null;
    return ['fechamentos'=>$fs, 'ganho'=>$ganho, 'origens'=>array_map(fn($k, $v) => ['cod'=>$k, 'label'=>$v],
            array_keys(cot_fech_origens()), array_values(cot_fech_origens()))];
}

function cot_can_edit($pdo, $me, $obra) {
    $perms = user_perms($pdo, $me);
    if (empty($perms['autorizado'])) return null;
    // papel de CONSULTA (obra, etr) não cria nem edita cotação, tenha o escopo de obra que tiver
    if (function_exists('sup_papeis_leitores') && in_array(($perms['papel'] ?? ''), sup_papeis_leitores(), true)) return null;
    // CRIAR cotação é dinâmica de comprador — liberado por PAPEL (admin/gerente/comprador), NÃO por edição de obra
    // (a maioria dos compradores tem editar_escopo='nenhuma'). can_edit_obra fica só p/ o menu Obras/estrutura.
    if (!empty($perms['perm_admin']) || in_array(($perms['papel'] ?? ''), ['gerente', 'comprador'], true)) return $perms;
    if (can_edit_obra($perms, max(1, (int)$obra))) return $perms;   // compat: quem edita a obra também pode
    return null;
}
// Gerir/editar/excluir uma cotação EXISTENTE: só ADMIN ou quem CRIOU (comprador não mexe nas dos outros).
// (Criar cotação nova segue liberado por obra via cot_can_edit — isso não é "editar a dos outros".)
// registra uma mudança na trilha de auditoria da cotação (quem/o quê/quando) — nunca derruba a ação principal
function cot_log($pdo, $cid, $me, $acao, $detalhe = '') {
    try {
        $nome = ''; try { $p = user_perms($pdo, $me); $nome = $p['nome'] ?? ''; } catch (Throwable $e) {}
        $pdo->prepare("INSERT INTO cotacao_historico (cotacao_id, bitrix_id, usuario_nome, acao, detalhe, created_at) VALUES (?,?,?,?,?,?)")
            ->execute([(int)$cid, (string)$me, $nome ?: ('Usuário ' . $me), $acao, $detalhe, date('c')]);
    } catch (Throwable $e) {}
}
function cot_can_manage($pdo, $me, $cid) {
    $q = $pdo->prepare("SELECT criado_por, colaboradores FROM cotacao WHERE id=?"); $q->execute([(int)$cid]); $r = $q->fetch();
    if (!$r) return false;
    $perms = user_perms($pdo, $me);
    // papel de CONSULTA (obra, etr) não gere cotação nem quando compartilhada com ele — mesma regra do cot_pode_gerir()
    if (function_exists('sup_papeis_leitores') && in_array(($perms['papel'] ?? ''), sup_papeis_leitores(), true)) return false;
    if (!empty($perms['perm_admin'])) return true;
    if (($perms['papel'] ?? '') === 'gerente') return true;   // GERENTE DE SUPRIMENTOS edita qualquer cotação (decisão 23/jul — tudo fica no Histórico)
    if ($me === null || $me === '') return false;
    if ((string)$r['criado_por'] === (string)$me) return true;
    // COLABORADORES (compartilhar — ex.: criador de férias): lista de bitrix_ids com os mesmos poderes de edição
    $cols = json_decode((string)($r['colaboradores'] ?? ''), true) ?: [];
    foreach ((array)$cols as $b) if (trim((string)$b) === trim((string)$me)) return true;
    return false;
}
// insere fornecedores CONVIDADOS na concorrência (dedup por fornecedor_id/nome)
function cot_insert_convidados($pdo, $cid, $lista) {
    $ins = $pdo->prepare("INSERT INTO cotacao_fornecedor (cotacao_id, fornecedor_id, fornecedor_nome, categoria, contato, email, telefone, created_at) VALUES (?,?,?,?,?,?,?,?)");
    $ex = $pdo->prepare("SELECT fornecedor_id, fornecedor_nome FROM cotacao_fornecedor WHERE cotacao_id=?"); $ex->execute([$cid]);
    $seen = [];
    foreach ($ex->fetchAll() as $r) { $seen['n:'.strtolower(trim((string)$r['fornecedor_nome']))] = 1; if ($r['fornecedor_id']) $seen['i:'.(int)$r['fornecedor_id']] = 1; }
    $now = date('c'); $n = 0;
    foreach ((array)$lista as $f) {
        $nome = trim((string)($f['nome'] ?? $f['fornecedor_nome'] ?? '')); if ($nome === '') continue;
        $fid = (int)($f['id'] ?? $f['fornecedor_id'] ?? 0) ?: null;
        if (($fid && isset($seen['i:'.$fid])) || isset($seen['n:'.strtolower($nome)])) continue;
        $ins->execute([$cid, $fid, $nome, trim((string)($f['categoria'] ?? '')), trim((string)($f['contato'] ?? '')), trim((string)($f['email'] ?? '')), trim((string)($f['telefone'] ?? '')), $now]);
        $seen['n:'.strtolower($nome)] = 1; if ($fid) $seen['i:'.$fid] = 1; $n++;
    }
    return $n;
}
// mapa comparativo a partir das propostas: melhor (menor) preço por item + total ótimo + melhor fornecedor único
// Proposta DESQUALIFICADA continua no mapa (visível, com o motivo) mas não julga: fica fora daqui inteira.
function cot_mapa($itens, $propostas) {
    $melhor = [];        // item_id => ['proposta_id','fornecedor','preco_unit','preco_total']
    foreach ($itens as $it) {
        $best = null;
        foreach ($propostas as $p) {
            if (!empty($p['desq'])) continue;
            $pi = $p['itens'][$it['id']] ?? null;
            if (!$pi) continue;
            $pt = $pi['preco_total'];
            if ($pt === null || $pt <= 0) continue;
            if ($best === null || $pt < $best['preco_total'])
                $best = ['proposta_id'=>$p['id'], 'fornecedor'=>$p['fornecedor_nome'], 'preco_unit'=>$pi['preco_unit'], 'preco_total'=>$pt];
        }
        if ($best) $melhor[$it['id']] = $best;
    }
    $melhor_total = 0.0; foreach ($melhor as $b) $melhor_total += (float)$b['preco_total'];
    // melhor fornecedor ÚNICO (menor total entre quem respondeu com valor)
    $melhor_oferta = null; $fornecedor_destaque = null;
    foreach ($propostas as $p) {
        if (!empty($p['desq'])) continue;
        if (($p['total'] ?? 0) <= 0) continue;
        if ($melhor_oferta === null || $p['total'] < $melhor_oferta) { $melhor_oferta = (float)$p['total']; $fornecedor_destaque = $p['fornecedor_nome']; }
    }
    return ['melhor_por_item'=>$melhor, 'melhor_total'=>round($melhor_total, 2),
            'melhor_oferta'=>$melhor_oferta !== null ? round($melhor_oferta, 2) : null,
            'fornecedor_destaque'=>$fornecedor_destaque];
}
function cot_get_full($pdo, $id) {
    $c = $pdo->prepare("SELECT c.*, o.nome AS obra_nome, s.nome AS servico_nome
                        FROM cotacao c LEFT JOIN obra o ON o.id=c.obra_id LEFT JOIN servico s ON s.id=c.servico_id
                        WHERE c.id=?");
    $c->execute([$id]); $cot = $c->fetch();
    if (!$cot) return null;
    // obra: se não há obra do radar vinculada mas a cotação nasceu de uma solicitação, mostra o nome comercial do de-para
    if (empty($cot['obra_nome']) && !empty($cot['solic_coligada'])) {
        $so = $pdo->prepare("SELECT nome_comercial FROM solic_obra WHERE coligada=? AND obra_cod=?");
        $so->execute([$cot['solic_coligada'], (string)($cot['solic_obra_cod'] ?? '')]);
        $nc = (string)$so->fetchColumn(); if ($nc !== '') $cot['obra_nome'] = $nc;
    }
    if (empty($cot['obra_nome']) && !empty($cot['obra_livre'])) $cot['obra_nome'] = $cot['obra_livre'];   // cotação importada (obra por texto)
    // colaboradores (compartilhar): ids + nomes resolvidos (p/ o front mostrar e o dono gerenciar)
    $cot['colaboradores'] = array_values(array_filter(array_map(fn($b) => trim((string)$b), (array)(json_decode((string)($cot['colaboradores'] ?? ''), true) ?: []))));
    $cot['colaboradores_nomes'] = [];
    if ($cot['colaboradores']) {
        try {
            $ph = implode(',', array_fill(0, count($cot['colaboradores']), '?'));
            $nq = $pdo->prepare("SELECT bitrix_id, nome FROM usuario WHERE TRIM(bitrix_id) IN ($ph)");
            $nq->execute($cot['colaboradores']);
            $nm = []; foreach ($nq->fetchAll() as $u) $nm[trim((string)$u['bitrix_id'])] = $u['nome'];
            $cot['colaboradores_nomes'] = array_map(fn($b) => $nm[$b] ?? ('#' . $b), $cot['colaboradores']);
        } catch (Throwable $e) {}
    }
    $iq = $pdo->prepare("SELECT * FROM cotacao_item WHERE cotacao_id=? ORDER BY ordem, id"); $iq->execute([$id]);
    $itens = $iq->fetchAll();
    // obra por item (cotação MULTI-OBRA): resolve nome + cidade p/ o front agrupar/rotular e p/ o texto de negociação
    // (cidade existe no MySQL de produção; no SQLite-sandbox pode faltar → fallback sem cidade)
    $obraNomes = []; $obraCidades = [];
    try { $orows = $pdo->query("SELECT id, nome, cidade FROM obra"); }
    catch (Throwable $e) { $orows = $pdo->query("SELECT id, nome FROM obra"); }
    foreach ($orows as $o) { $obraNomes[(int)$o['id']] = $o['nome']; $obraCidades[(int)$o['id']] = (string)($o['cidade'] ?? ''); }
    $obrasNoItens = [];
    // CNPJ da coligada por item: cod do prefixo do colidmov ("27-20628"→27) senão pelo nome da coligada
    $cnpjColidmov = function($cm, $col) {
        $cod = 0;
        if ($cm !== '' && strpos($cm, '-') !== false) { $cc = (int)substr($cm, 0, strpos($cm, '-')); if ($cc > 0) $cod = $cc; }
        if (!$cod && $col !== '' && function_exists('coligada_cod_de_nome')) $cod = (int)coligada_cod_de_nome($col);
        return $cod && function_exists('coligada_cnpj') ? (string)coligada_cnpj($cod) : '';
    };
    foreach ($itens as &$it) {
        $oid = (int)($it['obra_id'] ?? 0);
        $it['obra_nome'] = $oid ? ($obraNomes[$oid] ?? '') : '';
        $it['cidade'] = $oid ? ($obraCidades[$oid] ?? '') : '';
        $it['sc'] = trim((string)($it['solic_numero'] ?? ''));
        $it['coligada'] = trim((string)($it['solic_coligada'] ?? ''));
        $it['cnpj'] = $cnpjColidmov(trim((string)($it['solic_colidmov'] ?? '')), $it['coligada']);
        if ($oid) $obrasNoItens[$oid] = $it['obra_nome'];
    }
    unset($it);
    $cot['multi_obra'] = count($obrasNoItens) > 1;
    $cot['obras_itens'] = $obrasNoItens;
    // BLOCOS por OBRA (agrupamento p/ a lista, o texto de WhatsApp, a carta e o PDF): cada bloco carrega
    // obra/cidade/coligada/CNPJ + as SCs + os itens. Chave = obra_id (senão coligada; senão "geral").
    $blocos = [];
    foreach ($itens as $it) {
        $oid = (int)($it['obra_id'] ?? 0);
        $chave = $oid ? ('o'.$oid) : ($it['coligada'] !== '' ? ('c'.strtolower($it['coligada'])) : 'geral');
        if (!isset($blocos[$chave])) $blocos[$chave] = ['chave'=>$chave, 'obra_id'=>$oid ?: null,
            'obra_nome'=>$it['obra_nome'], 'cidade'=>$it['cidade'], 'coligada'=>$it['coligada'], 'cnpj'=>$it['cnpj'], 'scs'=>[], 'itens'=>[]];
        if ($it['sc'] !== '' && !in_array($it['sc'], $blocos[$chave]['scs'], true)) $blocos[$chave]['scs'][] = $it['sc'];
        if ($it['cnpj'] !== '' && $blocos[$chave]['cnpj'] === '') $blocos[$chave]['cnpj'] = $it['cnpj'];
        if ($it['cidade'] !== '' && $blocos[$chave]['cidade'] === '') $blocos[$chave]['cidade'] = $it['cidade'];
        $blocos[$chave]['itens'][] = ['id'=>(int)$it['id'], 'descricao'=>$it['descricao'], 'unidade'=>$it['unidade'], 'quantidade'=>$it['quantidade'], 'observacao'=>$it['observacao'], 'sc'=>$it['sc']];
    }
    $cot['blocos_obra'] = array_values($blocos);
    // FASE 2 — agrupa os itens por COLIGADA (multi-PC): cada coligada tem seu Nº de Pedido de Compra próprio.
    // coligada_cod: 1º do prefixo do colidmov ("27-20628" → 27); senão, pelo nome. num_pedido: da tabela cotacao_pedido.
    $colItens = [];
    foreach ($itens as $it) {
        $col = trim((string)($it['solic_coligada'] ?? '')); if ($col === '') continue;
        if (!isset($colItens[$col])) $colItens[$col] = ['coligada'=>$col, 'coligada_cod'=>null, 'colidmov'=>'', 'n'=>0, 'num_pedido'=>'', 'status'=>'', 'numeros'=>[]];
        $colItens[$col]['n']++;
        $num = trim((string)($it['solic_numero'] ?? ''));   // Nº da SOLICITAÇÃO (SC) — pode haver mais de uma por coligada
        if ($num !== '' && !in_array($num, $colItens[$col]['numeros'], true)) $colItens[$col]['numeros'][] = $num;
        $cm = trim((string)($it['solic_colidmov'] ?? ''));
        if ($cm !== '' && $colItens[$col]['colidmov'] === '') {
            $colItens[$col]['colidmov'] = $cm;
            if (strpos($cm, '-') !== false) { $cc = (int)substr($cm, 0, strpos($cm, '-')); if ($cc > 0) $colItens[$col]['coligada_cod'] = $cc; }
        }
    }
    foreach ($colItens as $cn => &$ci) { if (empty($ci['coligada_cod']) && function_exists('coligada_cod_de_nome')) { $cc = coligada_cod_de_nome($cn); if ($cc > 0) $ci['coligada_cod'] = $cc; } }
    unset($ci);
    // injeta o PC salvo por coligada (cotacao_pedido) — casa por coligada_cod (preferido) ou nome
    try {
        $pp = $pdo->prepare("SELECT * FROM cotacao_pedido WHERE cotacao_id=?"); $pp->execute([$id]);
        foreach ($pp->fetchAll() as $row) {
            foreach ($colItens as $cn => &$ci) {
                if ((!empty($row['coligada_cod']) && (int)$row['coligada_cod'] === (int)$ci['coligada_cod'])
                    || (empty($row['coligada_cod']) && trim((string)$row['coligada']) === $cn)) {
                    $ci['num_pedido'] = (string)$row['num_pedido']; $ci['status'] = (string)($row['status'] ?? ''); break;
                }
            }
            unset($ci);
        }
    } catch (Throwable $e) {}
    $cot['multi_coligada'] = count($colItens) > 1;
    $cot['coligadas_itens'] = array_values($colItens);
    // REVISÕES: busca TODAS as propostas; só a VIGENTE (ativa=1) entra no mapa; as anteriores viram histórico da cadeia.
    $pq = $pdo->prepare("SELECT * FROM cotacao_proposta WHERE cotacao_id=? ORDER BY (total IS NULL), total, id"); $pq->execute([$id]);
    $allProp = $pq->fetchAll();
    $byp = [];
    if ($allProp) {
        $ids = implode(',', array_map(fn($p)=>(int)$p['id'], $allProp));
        foreach ($pdo->query("SELECT * FROM cotacao_proposta_item WHERE proposta_id IN ($ids)")->fetchAll() as $r)
            $byp[(int)$r['proposta_id']][(int)$r['cotacao_item_id']] =
                ['preco_unit'=>$r['preco_unit']!==null?(float)$r['preco_unit']:null, 'preco_total'=>$r['preco_total']!==null?(float)$r['preco_total']:null, 'observacao'=>$r['observacao']];
    }
    $chain = fn($p)=> ((int)($p['raiz_id'] ?? 0)) ?: (int)$p['id'];   // raiz da cadeia de revisões
    $isAtiva = fn($p)=> ((int)($p['ativa'] ?? 1)) === 1;
    $histChain = [];
    foreach ($allProp as $p) {
        if ($isAtiva($p)) continue;
        $histChain[$chain($p)][] = ['id'=>(int)$p['id'], 'revisao'=>(int)($p['revisao'] ?? 0), 'opcao'=>(int)($p['opcao'] ?? 1),
            'total'=>$p['total']!==null?(float)$p['total']:null, 'prazo'=>$p['prazo'], 'observacoes'=>$p['observacoes'],
            'desq'=>(int)($p['desq'] ?? 0), 'desq_texto'=>((int)($p['desq'] ?? 0) ? cot_desq_texto($p['desq_motivo'] ?? '', $p['desq_obs'] ?? '') : ''),
            'created_at'=>$p['created_at'], 'itens'=>$byp[(int)$p['id']] ?? []];
    }
    foreach ($histChain as &$h) usort($h, fn($a,$b)=>($a['revisao']<=>$b['revisao'])); unset($h);
    $propostas = array_values(array_filter($allProp, $isAtiva));
    foreach ($propostas as &$p) {
        $p['total'] = $p['total']!==null?(float)$p['total']:null; $p['itens'] = $byp[(int)$p['id']] ?? [];
        $p['equaliza'] = !empty($p['equaliza'] ?? '') ? (json_decode($p['equaliza'], true) ?: []) : [];
        $p['revisao'] = (int)($p['revisao'] ?? 0);
        // OPÇÃO: o mesmo fornecedor pode ter mais de uma proposta vigente (formas diferentes de apresentar o preço)
        $p['opcao'] = (int)($p['opcao'] ?? 1) ?: 1;
        $p['opcao_rotulo'] = trim((string)($p['opcao_rotulo'] ?? ''));
        // DESQUALIFICADA: continua na lista (o mapa mostra a coluna marcada), mas cot_mapa a ignora no julgamento
        $p['desq'] = (int)($p['desq'] ?? 0);
        $p['desq_motivo'] = (string)($p['desq_motivo'] ?? '');
        $p['desq_obs'] = trim((string)($p['desq_obs'] ?? ''));
        $p['desq_texto'] = $p['desq'] ? cot_desq_texto($p['desq_motivo'], $p['desq_obs']) : '';
        $p['historico'] = $histChain[$chain($p)] ?? [];
    }
    unset($p);
    /* Desqualificadas vão para o FIM das colunas do mapa: quem julga lê primeiro quem está no páreo.
       Partição manual (não usort) porque ordenação estável só é garantida a partir do PHP 8 — aqui
       a ordem de dentro de cada grupo (por total) precisa ser preservada. */
    $qual = []; $fora = [];
    foreach ($propostas as $p) { if ($p['desq']) $fora[] = $p; else $qual[] = $p; }
    $propostas = array_merge($qual, $fora);
    $anx = $pdo->prepare("SELECT id, proposta_id, fornecedor_id, fornecedor_nome, nome, tamanho, mime, url FROM cotacao_anexo WHERE cotacao_id=? AND (fornecedor_nome IS NULL OR fornecedor_nome<>'__CARTA__') ORDER BY id"); $anx->execute([$id]);
    // fornecedores CONVIDADOS (concorrência) + status respondeu (deriva de proposta com mesmo fornecedor)
    $cf = $pdo->prepare("SELECT cf.*, f.email AS f_email, f.telefone AS f_telefone, f.whatsapp AS f_whatsapp, f.contatos_at AS f_contatos_at
                         FROM cotacao_fornecedor cf LEFT JOIN cot_fornecedor f ON f.id=cf.fornecedor_id WHERE cf.cotacao_id=? ORDER BY cf.fornecedor_nome"); $cf->execute([$id]);
    $convidados = $cf->fetchAll();
    foreach ($convidados as &$c) {
        // TODAS as propostas vigentes do fornecedor — com OPÇÕES, ele pode ter mais de uma (opção 1, 2, 3…).
        // A "principal" (proposta_id/total do chip) segue sendo a mais barata: $propostas já vem ordenada por total.
        $resp = null; $cn = strtolower(trim((string)$c['fornecedor_nome'])); $minhas = [];
        foreach ($propostas as $p) {
            if (($c['fornecedor_id'] && (int)$p['fornecedor_id'] === (int)$c['fornecedor_id'])
                || ($cn !== '' && strtolower(trim((string)$p['fornecedor_nome'])) === $cn)) {
                if ($resp === null) $resp = $p;   // $propostas vem com as qualificadas primeiro: o chip do card mostra uma VÁLIDA quando existe
                $minhas[] = ['id'=>(int)$p['id'], 'opcao'=>(int)$p['opcao'], 'opcao_rotulo'=>$p['opcao_rotulo'],
                             'revisao'=>(int)$p['revisao'], 'total'=>$p['total'], 'prazo'=>$p['prazo'],
                             'desq'=>(int)$p['desq'], 'desq_texto'=>$p['desq_texto'],
                             'n_historico'=>count($p['historico'] ?? [])];
            }
        }
        usort($minhas, fn($a, $b) => ($a['opcao'] <=> $b['opcao']) ?: ($a['id'] <=> $b['id']));
        $c['propostas'] = $minhas;
        $c['respondeu'] = $resp ? 1 : 0; $c['proposta_id'] = $resp['id'] ?? null; $c['proposta_total'] = $resp['total'] ?? null;
        // quantas propostas dele estão desqualificadas (e se sobrou alguma no páreo) — o card avisa sem entrar nas opções
        $c['desq_n'] = count(array_filter($minhas, fn($x) => !empty($x['desq'])));
        $c['desq_todas'] = ($minhas && $c['desq_n'] === count($minhas)) ? 1 : 0;
        $c['desq_texto'] = $c['desq_todas'] ? (string)($minhas[0]['desq_texto'] ?? '') : '';
        // contatos p/ a conferência (mestre cot_fornecedor quando há vínculo; senão o snapshot do convite)
        $c['email'] = ($c['f_email'] ?? '') !== '' ? $c['f_email'] : ($c['email'] ?? '');
        $c['telefone'] = ($c['f_telefone'] ?? '') !== '' ? $c['f_telefone'] : ($c['telefone'] ?? '');
        $c['whatsapp'] = $c['f_whatsapp'] ?? '';
        $c['contatos_at'] = !empty($c['f_contatos_at']) ? (json_decode($c['f_contatos_at'], true) ?: null) : null;
        unset($c['f_email'], $c['f_telefone'], $c['f_whatsapp'], $c['f_contatos_at']);
    }
    unset($c);
    $ger = $pdo->prepare("SELECT id, titulo, criado_nome, created_at FROM carta_gerada WHERE cotacao_id=? ORDER BY id DESC"); $ger->execute([$id]);
    // catálogo de motivos de desqualificação: o front monta o select a partir daqui (uma fonte só)
    $motivos = []; foreach (cot_desq_motivos() as $cod => $lbl) $motivos[] = ['cod'=>$cod, 'label'=>$lbl];
    $fech = cot_fechamentos($pdo, $id);   // fechamentos + ganho vão junto: a tela desenha tudo numa requisição só
    return ['cotacao'=>$cot, 'itens'=>$itens, 'propostas'=>$propostas, 'anexos'=>$anx->fetchAll(),
            'convidados'=>$convidados, 'mapa'=>cot_mapa($itens, $propostas), 'cartas_geradas'=>$ger->fetchAll(),
            'desq_motivos'=>$motivos, 'fechamentos'=>$fech['fechamentos'], 'ganho'=>$fech['ganho'],
            'fech_origens'=>$fech['origens'] ?? []];
}

try {
    $pdo = db();

    // ---------- GET ----------
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['dicionario'])) {   // itens-padrão a cotar do serviço (aprendizado de cotação)
            $sid = (int)$_GET['dicionario'];
            $q = $pdo->prepare("SELECT id, descricao, unidade, nota FROM cot_dicionario WHERE servico_id=? ORDER BY ordem, id"); $q->execute([$sid]);
            $sv = $pdo->prepare("SELECT nome, grupo FROM servico WHERE id=?"); $sv->execute([$sid]); $sv = $sv->fetch();
            echo json_encode(['servico'=>$sv, 'itens'=>$q->fetchAll()], JSON_UNESCAPED_UNICODE); exit;
        }
        if (isset($_GET['historico'])) {   // trilha de auditoria da cotação (quem mudou o quê, quando)
            $hq = $pdo->prepare("SELECT bitrix_id, usuario_nome, acao, detalhe, created_at FROM cotacao_historico WHERE cotacao_id=? ORDER BY id DESC LIMIT 300");
            $hq->execute([(int)$_GET['historico']]);
            echo json_encode(['historico' => $hq->fetchAll()], JSON_UNESCAPED_UNICODE); exit;
        }
        /* ───────── MÓDULO FECHAMENTOS ─────────
           A tela de cotação ficou poluída, então a apuração saiu de lá. Duas consultas: a FILA (o
           estado do fechamento de cada cotação, com o aging de quem está esperando aprovação) e a
           APURAÇÃO MENSAL, que espelha a tabela do próprio contrato para poder ser conferida linha
           a linha numa reunião. Ambas exigem a permissão de ver ganhos — o comprador não entra. */
        if (isset($_GET['fila']) || isset($_GET['apuracao'])) {
            if (!cot_pode_ver_ganhos($pdo, $_GET['me'] ?? null)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão para ver a apuração de ganhos.'], JSON_UNESCAPED_UNICODE); exit; }
            $rows = $pdo->query("SELECT f.*, c.titulo, c.apelido, c.obra_id, c.criado_nome, c.criado_por,
                                        c.num_pedido, c.status AS cot_status, c.servico_id,
                                        COALESCE(NULLIF(o.nome,''), c.obra_livre) AS obra_nome
                                 FROM cotacao_fechamento f
                                 JOIN cotacao c ON c.id=f.cotacao_id
                                 LEFT JOIN obra o ON o.id=c.obra_id
                                 ORDER BY f.cotacao_id, f.rodada, f.id")->fetchAll();
            $porCot = [];
            foreach ($rows as $r) {
                $cid = (int)$r['cotacao_id'];
                if (!isset($porCot[$cid])) $porCot[$cid] = ['cotacao_id'=>$cid, 'titulo'=>$r['titulo'], 'apelido'=>$r['apelido'],
                    'obra_nome'=>$r['obra_nome'], 'criado_nome'=>$r['criado_nome'], 'criado_por'=>$r['criado_por'],
                    'num_pedido'=>trim((string)$r['num_pedido']), 'cot_status'=>$r['cot_status'],
                    'do_radar'=>!empty($r['servico_id']) ? 1 : 0, 'rodadas'=>[]];
                $r['id'] = (int)$r['id']; $r['rodada'] = (int)$r['rodada'];
                $r['etr_participou'] = (int)($r['etr_participou'] ?? 0);
                $r['total'] = $r['total'] !== null ? (float)$r['total'] : null;
                $r['linhas'] = cot_fech_linhas($pdo, $r['id']);
                $porCot[$cid]['rodadas'][] = $r;
            }
            /* QUEM VÊ RODADA EM ANDAMENTO. Rascunho, "aguardando aprovação" e principalmente o
               MOTIVO DA DEVOLUÇÃO são deliberação interna da Caprem: se o gerente devolve dizendo
               "faltou cotar com a Tatu", esse texto conta para a consultoria exatamente onde a
               régua está fraca — e ela é remunerada sobre a diferença contra essa régua.
               Então quem está FORA da cadeia de aprovação (a consultoria, e qualquer visualizador)
               só enxerga rodada APROVADA; cotação sem nenhuma aprovada nem aparece. O dono da
               cotação continua vendo a dele, e quem aprova vê tudo — é a fila de trabalho dele. */
            $meFila = $_GET['me'] ?? null;
            $permFila = user_perms($pdo, $meFila);
            $veAndamento = !empty($permFila['perm_admin']) || (($permFila['papel'] ?? '') === 'gerente')
                        || !empty($permFila['perm_fechamento']);
            $lista = [];
            foreach ($porCot as $cid => $c) {
                $ehDono = ($meFila !== null && $meFila !== '' && (string)$c['criado_por'] === (string)$meFila);
                if (!$veAndamento && !$ehDono) {
                    $emAndamento = count(array_filter($c['rodadas'], fn($x) => ($x['status'] ?? '') !== 'homologado')) > 0;
                    $c['rodadas'] = array_values(array_filter($c['rodadas'], fn($x) => ($x['status'] ?? '') === 'homologado'));
                    if (!$c['rodadas']) continue;                 // nada aprovado ainda: não existe para quem está de fora
                    $c['em_andamento'] = $emAndamento ? 1 : 0;    // sinal neutro: "há rodada em curso", sem valor nem status
                }
                $rs = $c['rodadas']; $ult = $rs[count($rs) - 1];
                $ap = array_values(array_filter($rs, fn($x) => ($x['status'] ?? '') === 'homologado'));
                $c['rodada_atual'] = (int)$ult['rodada'];
                $c['status'] = (string)$ult['status'];
                $c['fechamento_id'] = (int)$ult['id'];
                $c['aprovadas'] = count($ap);
                $c['etr'] = !empty($ult['etr_participou']) ? 1 : 0;
                $c['total_atual'] = $ult['total'];
                $c['aprovado_nome'] = (string)($ult['aprovado_nome'] ?? '');
                $c['aprovado_at'] = (string)($ult['aprovado_at'] ?? '');
                $c['devolvido_motivo'] = (string)($ult['devolvido_motivo'] ?? '');
                $ts = strtotime((string)($ult['updated_at'] ?? $ult['created_at'] ?? ''));
                $c['dias_parado'] = $ts ? (int)floor((time() - $ts) / 86400) : null;
                $c['ganho'] = count($ap) >= 2 ? cot_fech_ganho($ap[0], $ap[count($ap) - 1]) : null;
                // competência = mês em que o fechamento final foi aprovado (quando a negociação concluiu)
                $c['competencia'] = (count($ap) >= 2 && !empty($ap[count($ap) - 1]['aprovado_at']))
                    ? substr((string)$ap[count($ap) - 1]['aprovado_at'], 0, 7) : '';
                $c['tem_pc'] = $c['num_pedido'] !== '' ? 1 : 0;
                unset($c['rodadas']);
                $c['rodadas'] = array_map(fn($x) => ['id'=>$x['id'], 'rodada'=>$x['rodada'], 'status'=>$x['status'],
                    'total'=>$x['total'], 'etr'=>(int)$x['etr_participou'], 'aprovado_nome'=>$x['aprovado_nome'] ?? '',
                    'aprovado_at'=>$x['aprovado_at'] ?? '', 'data_fechamento'=>$x['data_fechamento'] ?? '',
                    'origem_label'=>cot_fech_origem_label($x['origem_preco'] ?? ''),
                    'fornecedores'=>array_values(array_unique(array_filter(array_map(fn($l) => $l['fornecedor_nome'], $x['linhas']))))], $rs);
                $lista[] = $c;
            }
            if (isset($_GET['fila'])) {
                usort($lista, function ($a, $b) {   // quem está esperando aprovação primeiro, mais parado no topo
                    $pa = ($a['status'] === 'aguardando') ? 0 : 1; $pb = ($b['status'] === 'aguardando') ? 0 : 1;
                    return ($pa <=> $pb) ?: (($b['dias_parado'] ?? 0) <=> ($a['dias_parado'] ?? 0));
                });
                echo json_encode(['fila'=>$lista, 'pode_aprovar'=>cot_pode_aprovar_fechamento($pdo, $meFila) ? 1 : 0,
                                  've_andamento'=>$veAndamento ? 1 : 0], JSON_UNESCAPED_UNICODE); exit;
            }

            /* APURAÇÃO DO MÊS. Regras do contrato aplicadas aqui, não no Excel de ninguém:
               - só entra o Projeto de Negociação com PEDIDO DE COMPRA emitido (cláusula 5.2 — vedada
                 apuração sobre economia projetada). Sem PC a linha aparece como PENDENTE, não somada;
               - só gera honorário a rodada com participação registrada da ETR (cláusula 3.12);
               - fatura-se o MAIOR entre o success fee do mês e o adiantamento (cláusula 5.3.1),
                 vedada a cumulação. */
            $mes = trim((string)($_GET['mes'] ?? ''));
            $meses = [];
            foreach ($lista as $c) if ($c['competencia'] !== '') $meses[$c['competencia']] = true;
            krsort($meses);
            if ($mes === '') $mes = (string)(array_key_first($meses) ?? date('Y-m'));
            $apRows = [];
            try { foreach ($pdo->query("SELECT * FROM cotacao_apuracao") as $r) $apRows[(int)$r['cotacao_id']] = $r; }
            catch (Throwable $e) {}
            $linhas = []; $somaGanhoEtr = 0.0; $somaGanhoTotal = 0.0; $pendentes = [];
            foreach ($lista as $c) {
                if (!$c['ganho']) continue;
                if ($c['competencia'] !== $mes) continue;
                $a = $apRows[$c['cotacao_id']] ?? null;
                $congelado = $a && ($a['status'] ?? '') !== 'analise' && $a['ganho_snap'] !== null;
                $ganho = $congelado ? (float)$a['ganho_snap'] : (float)$c['ganho']['ganho'];
                $base  = $congelado ? (float)$a['base_snap']  : (float)$c['ganho']['total_base'];
                $fim   = $congelado ? (float)$a['final_snap'] : (float)$c['ganho']['total_final'];
                $pct   = (float)($a['pct_etr'] ?? COT_ETR_PCT);
                $l = ['cotacao_id'=>$c['cotacao_id'], 'titulo'=>$c['apelido'] ?: $c['titulo'], 'obra_nome'=>$c['obra_nome'],
                      'comprador'=>$c['criado_nome'], 'num_pedido'=>$c['num_pedido'], 'tem_pc'=>$c['tem_pc'],
                      'etr'=>$c['etr'], 'total_base'=>round($base, 2), 'total_final'=>round($fim, 2),
                      'ganho'=>round($ganho, 2), 'pct'=>$c['ganho']['pct'],
                      'status'=>$a ? (string)$a['status'] : 'analise', 'observacao'=>$a ? (string)$a['observacao'] : '',
                      'congelado'=>$congelado ? 1 : 0, 'pct_etr'=>$pct,
                      'por_nome'=>$a ? (string)$a['por_nome'] : '', 'atualizado'=>$a ? (string)$a['updated_at'] : '',
                      'itens'=>$c['ganho']['itens'], 'rodadas'=>$c['rodadas']];
                // conta no honorário só se: tem PC + ETR participou + não foi contestado + ganho positivo
                $vale = $c['tem_pc'] && $c['etr'] && ($l['status'] !== 'contestado') && $ganho > 0;
                $l['conta'] = $vale ? 1 : 0;
                $l['fee_etr'] = $vale ? round($ganho * $pct / 100, 2) : 0.0;
                $l['fee_caprem'] = $vale ? round($ganho - $ganho * $pct / 100, 2) : 0.0;
                if (!$c['tem_pc']) $l['motivo_fora'] = 'sem pedido de compra emitido (cláusula 5.2)';
                elseif (!$c['etr']) $l['motivo_fora'] = 'rodada sem participação registrada da consultoria (cláusula 3.12)';
                elseif ($l['status'] === 'contestado') $l['motivo_fora'] = 'contestado pela Caprem';
                elseif ($ganho <= 0) $l['motivo_fora'] = 'sem ganho a apurar';
                if ($vale) { $somaGanhoEtr += $ganho; }
                $somaGanhoTotal += $ganho;
                $linhas[] = $l;
            }
            usort($linhas, fn($a, $b) => ($b['conta'] <=> $a['conta']) ?: ($b['ganho'] <=> $a['ganho']));
            $fee = round($somaGanhoEtr * COT_ETR_PCT / 100, 2);
            echo json_encode(['mes'=>$mes, 'meses'=>array_keys($meses), 'linhas'=>$linhas,
                'resumo'=>['ganho_total'=>round($somaGanhoTotal, 2), 'ganho_apuravel'=>round($somaGanhoEtr, 2),
                           'fee_etr'=>$fee, 'fee_caprem'=>round($somaGanhoEtr - $fee, 2),
                           'pct_etr'=>COT_ETR_PCT, 'adiantamento'=>COT_ADIANTAMENTO,
                           'a_faturar'=>max($fee, COT_ADIANTAMENTO),
                           'prevalece'=>$fee >= COT_ADIANTAMENTO ? 'success_fee' : 'adiantamento'],
                'pode_aprovar'=>cot_pode_aprovar_fechamento($pdo, $_GET['me'] ?? null) ? 1 : 0], JSON_UNESCAPED_UNICODE); exit;
        }
        if (isset($_GET['id'])) {
            $full = cot_get_full($pdo, (int)$_GET['id']);
            if (!$full) { http_response_code(404); echo json_encode(['error'=>'cotação não encontrada']); exit; }
            // APURAÇÃO DE GANHOS não vai no payload de quem não pode vê-la (ver cot_pode_ver_ganhos)
            $full['pode_ver_ganhos'] = cot_pode_ver_ganhos($pdo, $_GET['me'] ?? null) ? 1 : 0;
            if (!$full['pode_ver_ganhos']) $full['ganho'] = null;
            echo json_encode($full, JSON_UNESCAPED_UNICODE); exit;
        }
        // lista
        $where = ''; $args = [];
        if (isset($_GET['obra']) && $_GET['obra'] !== '') { $where = 'WHERE c.obra_id=?'; $args[] = (int)$_GET['obra']; }
        $q = $pdo->prepare("SELECT c.id, c.obra_id, c.servico_id, c.titulo, c.categoria, c.tipo_servico, c.verba,
                                   c.num_solicitacao, c.num_pedido,
                                   c.apelido, c.status, c.aprovacao, c.criado_por, c.criado_nome, c.created_at, COALESCE(NULLIF(o.nome,''), c.obra_livre) AS obra_nome,
                                   (SELECT COUNT(*) FROM cotacao_item ci WHERE ci.cotacao_id=c.id) AS n_itens,
                                   (SELECT COUNT(*) FROM cotacao_proposta cp WHERE cp.cotacao_id=c.id AND (cp.ativa=1 OR cp.ativa IS NULL)) AS n_propostas,
                                   (SELECT COUNT(*) FROM cotacao_fornecedor cf WHERE cf.cotacao_id=c.id) AS n_convidados,
                                   (SELECT MIN(cp.total) FROM cotacao_proposta cp WHERE cp.cotacao_id=c.id AND cp.total>0 AND (cp.ativa=1 OR cp.ativa IS NULL) AND (cp.desq=0 OR cp.desq IS NULL)) AS melhor_oferta,
                                   (SELECT COUNT(*) FROM cotacao_proposta cp WHERE cp.cotacao_id=c.id AND (cp.ativa=1 OR cp.ativa IS NULL) AND cp.desq=1) AS n_desq,
                                   (SELECT COUNT(*) FROM cotacao_email_in ei WHERE ei.cotacao_id=c.id AND ei.status='novo') AS n_inbound_novo
                            FROM cotacao c LEFT JOIN obra o ON o.id=c.obra_id
                            $where ORDER BY c.created_at DESC, c.id DESC LIMIT " . COT_LISTA_MAX);
        $q->execute($args);
        $rows = $q->fetchAll();
        // total real (p/ a lista avisar se bateu no teto) — as importadas do sistema antigo têm id ALTO e data ANTIGA,
        // por isso a ordenação é por created_at: por id elas empurrariam as cotações recentes para fora do limite.
        $tq = $pdo->prepare("SELECT COUNT(*) FROM cotacao c " . $where); $tq->execute($args);
        echo json_encode(['cotacoes'=>$rows, 'total'=>(int)$tq->fetchColumn(), 'limite'=>COT_LISTA_MAX], JSON_UNESCAPED_UNICODE); exit;
    }

    // ---------- POST ----------
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $acao = $in['acao'] ?? '';
    $me = $in['me'] ?? null;

    if ($acao === 'criar') {
        $obra = (int)($in['obra_id'] ?? 0);
        // cadastro único: se veio obra_ficha_id, resolve (e PROMOVE ao radar se preciso) o obra_id
        if (!empty($in['obra_ficha_id'])) {
            require_once __DIR__ . '/../includes/obra_registry.php';
            $rid = obra_radar_id($pdo, (int)$in['obra_ficha_id']);
            if ($rid) $obra = $rid;
        }
        $perms = cot_can_edit($pdo, $me, $obra ?: 1);
        if (!$perms) { http_response_code(403); echo json_encode(['error'=>'Sem permissão de edição.']); exit; }
        $titulo = trim((string)($in['titulo'] ?? '')); if ($titulo === '') throw new Exception('título obrigatório');
        $itens = array_values(array_filter((array)($in['itens'] ?? []), fn($i)=>trim((string)($i['descricao'] ?? '')) !== ''));
        if (!$itens) throw new Exception('inclua ao menos um item a cotar');
        $now = date('c');
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO cotacao (obra_id, servico_id, titulo, categoria, tipo_servico, verba, verba_origem, descricao, equalizacao, num_solicitacao, num_pedido, status, aprovacao, criado_por, criado_nome, created_at, updated_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?, 'aberta', 'aguardando', ?,?,?,?)")
            ->execute([$obra ?: null, ($in['servico_id'] ?? null) ?: null, $titulo, trim((string)($in['categoria'] ?? '')),
                       trim((string)($in['tipo_servico'] ?? '')), (float)($in['verba'] ?? 0) ?: null, trim((string)($in['verba_origem'] ?? '')),
                       trim((string)($in['descricao'] ?? '')), trim((string)($in['equalizacao'] ?? '')),
                       trim((string)($in['num_solicitacao'] ?? '')) ?: null, trim((string)($in['num_pedido'] ?? '')) ?: null,
                       $me, $perms['nome'] ?? null, $now, $now]);
        $cid = (int)$pdo->lastInsertId();
        $insI = $pdo->prepare("INSERT INTO cotacao_item (cotacao_id, descricao, unidade, quantidade, observacao, ordem) VALUES (?,?,?,?,?,?)");
        $o = 0;
        foreach ($itens as $it) $insI->execute([$cid, trim((string)$it['descricao']), trim((string)($it['unidade'] ?? '')),
                        ($it['quantidade'] ?? null) !== null && $it['quantidade'] !== '' ? (float)$it['quantidade'] : null,
                        trim((string)($it['observacao'] ?? '')), $o++]);
        cot_insert_convidados($pdo, $cid, $in['convidados'] ?? []);   // fornecedores convidados p/ a concorrência
        $pdo->commit();
        echo json_encode(['ok'=>true, 'id'=>$cid], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'colaborador_salvar') {   // COMPARTILHAR a cotação: admin OU o criador definem quem mais pode editá-la
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $row = $pdo->prepare("SELECT criado_por FROM cotacao WHERE id=?"); $row->execute([$cid]);
        $criador = (string)($row->fetchColumn() ?? '');
        $perms = user_perms($pdo, $me);
        $souCriador = ($me !== null && $me !== '' && $criador === (string)$me);
        $souGerente = (($perms['papel'] ?? '') === 'gerente');
        if (empty($perms['perm_admin']) && !$souGerente && !$souCriador) { http_response_code(403); echo json_encode(['error'=>'Só administrador, gerente de suprimentos ou quem criou a cotação pode compartilhá-la.'], JSON_UNESCAPED_UNICODE); exit; }
        $cols = array_values(array_unique(array_filter(array_map(fn($b) => trim((string)$b), (array)($in['colaboradores'] ?? [])), fn($b) => $b !== '' && $b !== $criador)));   // criador não precisa estar na lista
        $pdo->prepare("UPDATE cotacao SET colaboradores=?, updated_at=? WHERE id=?")->execute([$cols ? json_encode($cols) : null, date('c'), $cid]);
        // nomes p/ o log
        $nomes = [];
        if ($cols) { try { $ph = implode(',', array_fill(0, count($cols), '?')); $nq = $pdo->prepare("SELECT nome FROM usuario WHERE TRIM(bitrix_id) IN ($ph)"); $nq->execute($cols); $nomes = $nq->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $e) {} }
        cot_log($pdo, $cid, $me, 'Compartilhamento', $cols ? ('Colaboradores: ' . implode(', ', $nomes ?: $cols)) : 'Removeu todos os colaboradores');
        echo json_encode(['ok'=>true, 'colaboradores'=>$cols], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'set_obra') {   // define/corrige a obra de uma cotação (cadastro único: obra_ficha_id → promove)
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só admin ou quem criou a cotação pode mudar a obra.']); exit; }
        $obra = 0;
        if (!empty($in['obra_ficha_id'])) $obra = (int)obra_radar_id($pdo, (int)$in['obra_ficha_id']);
        elseif (isset($in['obra_id'])) $obra = (int)$in['obra_id'];   // 0 = limpar
        $pdo->prepare("UPDATE cotacao SET obra_id=?, updated_at=? WHERE id=?")->execute([$obra ?: null, date('c'), $cid]);
        cot_log($pdo, $cid, $me, 'Obra', 'Obra da cotação alterada (id ' . ($obra ?: '—') . ')');
        echo json_encode(['ok'=>true, 'cotacao_id'=>$cid, 'obra_id'=>$obra ?: null], JSON_UNESCAPED_UNICODE); exit;
    }

    /* VINCULAR A UM ITEM DO RADAR DEPOIS DE CRIADA. Até aqui o vínculo só existia no momento da
       criação: cotação nascida "do zero" (ou importada do sistema antigo) ficava órfã para sempre,
       e é justamente o histórico antigo que mais precisa ser reconciliado com o radar.
       Quem pode: a MESMA regra de gerir a cotação — admin | gerente | criador | colaborador.
       servico_id=0 desvincula. */
    /* TRANSFERIR A AUTORIA de uma cotação (admin).
       Nasceu do incidente 05/08/2026 — a Paloma criou uma cotação que ficou gravada no nome do
       Murilo (o app assumia identidade quando o BX24 não respondia) e ela ficou sem conseguir
       editar, porque `cot_can_manage` compara criado_por com quem está logado. Serve também para
       o caso normal de alguém sair da empresa e as cotações dele precisarem de dono. */
    if ($acao === 'trocar_criador') {
        // resolve aqui: cada bloco deste roteador dá exit, então $perms de outro bloco nunca chega
        $perms = user_perms($pdo, $me);
        if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error' => 'Apenas administradores.']); exit; }
        $cid = (int)($in['cotacao_id'] ?? 0);
        $novo = trim((string)($in['bitrix_id'] ?? ''));
        if (!$cid || $novo === '') throw new Exception('informe cotacao_id e bitrix_id');
        $u = $pdo->prepare("SELECT nome FROM usuario WHERE bitrix_id=? LIMIT 1"); $u->execute([$novo]);
        $nomeNovo = (string)($u->fetchColumn() ?: '');
        if ($nomeNovo === '') throw new Exception('usuário não encontrado no cockpit');
        $q = $pdo->prepare("SELECT criado_por, criado_nome, titulo FROM cotacao WHERE id=? LIMIT 1");
        $q->execute([$cid]); $antes = $q->fetch();
        if (!$antes) throw new Exception('cotação não encontrada');
        $pdo->prepare("UPDATE cotacao SET criado_por=?, criado_nome=?, updated_at=? WHERE id=?")
            ->execute([$novo, $nomeNovo, date('c'), $cid]);
        // fica no histórico da cotação: mudar dono é o tipo de coisa que alguém vai questionar depois
        try { cot_log($pdo, $cid, $me, 'trocou o criador',
              ($antes['criado_nome'] ?: $antes['criado_por']) . ' → ' . $nomeNovo
              . (($in['motivo'] ?? '') !== '' ? ' · ' . trim((string)$in['motivo']) : '')); } catch (Throwable $e) {}
        echo json_encode(['ok' => true, 'criado_por' => $novo, 'criado_nome' => $nomeNovo,
            'antes' => $antes['criado_nome'] ?: $antes['criado_por']], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'set_servico') {
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só admin, gerente, quem criou ou quem recebeu a cotação compartilhada pode vinculá-la ao radar.'], JSON_UNESCAPED_UNICODE); exit; }
        $sid = (int)($in['servico_id'] ?? 0);
        $nome = '';
        if ($sid) {
            $q = $pdo->prepare("SELECT nome FROM servico WHERE id=?"); $q->execute([$sid]);
            $nome = (string)$q->fetchColumn();
            if ($nome === '') throw new Exception('item do radar não encontrado (servico_id ' . $sid . ')');
        }
        // se a cotação ainda não tem obra e o vínculo trouxe uma, aproveita — NUNCA sobrescreve a existente
        $cur = $pdo->prepare("SELECT obra_id FROM cotacao WHERE id=?"); $cur->execute([$cid]);
        $obraAtual = (int)($cur->fetchColumn() ?: 0);
        $obraNova = (int)($in['obra_id'] ?? 0);
        if (!$obraAtual && $obraNova) {
            $pdo->prepare("UPDATE cotacao SET obra_id=? WHERE id=?")->execute([$obraNova, $cid]);
            $obraAtual = $obraNova;
        }
        $pdo->prepare("UPDATE cotacao SET servico_id=?, updated_at=? WHERE id=?")->execute([$sid ?: null, date('c'), $cid]);
        cot_log($pdo, $cid, $me, 'Radar', $sid ? ('Vinculada ao item do radar "' . $nome . '" (#' . $sid . ')') : 'Vínculo com o radar removido');
        echo json_encode(['ok'=>true, 'cotacao_id'=>$cid, 'servico_id'=>$sid ?: null,
                          'servico_nome'=>$nome, 'obra_id'=>$obraAtual ?: null], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'reprocessar_obras') {   // ADMIN: preenche a obra das cotações antigas sem obra, pela solicitação vinculada
        $perms = user_perms($pdo, $me);
        if (empty($perms['perm_admin'])) { http_response_code(403); echo json_encode(['error'=>'Apenas administradores.']); exit; }
        $rows = $pdo->query("SELECT id, solic_coligada, solic_obra_cod FROM cotacao WHERE (obra_id IS NULL OR obra_id=0) AND solic_coligada IS NOT NULL AND solic_coligada<>''")->fetchAll();
        $ok = 0; $skip = 0;
        foreach ($rows as $r) {
            $rid = obra_radar_de_solicitacao($pdo, (string)$r['solic_coligada'], (string)$r['solic_obra_cod']);
            if ($rid) { $pdo->prepare("UPDATE cotacao SET obra_id=?, updated_at=? WHERE id=?")->execute([$rid, date('c'), (int)$r['id']]); $ok++; }
            else $skip++;
        }
        echo json_encode(['ok'=>true, 'resolvidas'=>$ok, 'nao_resolvidas'=>$skip, 'total'=>count($rows)], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'convidar') {   // adiciona fornecedores convidados a uma cotação existente
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só o administrador ou quem criou a cotação pode editá-la.']); exit; }
        $pdo->beginTransaction();
        $n = cot_insert_convidados($pdo, $cid, $in['convidados'] ?? $in['fornecedores'] ?? []);
        $pdo->commit();
        if ($n) cot_log($pdo, $cid, $me, 'Concorrência', 'Convidou ' . $n . ' fornecedor(es): ' . implode(', ', array_slice(array_map(fn($f) => (string)($f['nome'] ?? $f['fornecedor_nome'] ?? ''), (array)($in['convidados'] ?? $in['fornecedores'] ?? [])), 0, 6)));
        echo json_encode(['ok'=>true, 'n'=>$n], JSON_UNESCAPED_UNICODE); exit;
    }
    if ($acao === 'desconvidar') {
        $id = (int)($in['id'] ?? 0); if (!$id) throw new Exception('id obrigatório');
        // deriva a cotação a partir do convidado (esta ação recebe o id do cotacao_fornecedor, não cotacao_id)
        $row = $pdo->prepare("SELECT cotacao_id FROM cotacao_fornecedor WHERE id=?"); $row->execute([$id]);
        $cid = (int)$row->fetchColumn();
        if (!$cid) { echo json_encode(['ok'=>true, 'ja_removido'=>true], JSON_UNESCAPED_UNICODE); exit; }   // já não existe
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só o administrador ou quem criou a cotação pode editá-la.']); exit; }
        $cf = $pdo->prepare("SELECT fornecedor_nome, fornecedor_id FROM cotacao_fornecedor WHERE id=?"); $cf->execute([$id]);
        $cfr = $cf->fetch() ?: []; $fn = (string)($cfr['fornecedor_nome'] ?? ''); $ffid = (int)($cfr['fornecedor_id'] ?? 0);

        /* AS PROPOSTAS DELE VÃO JUNTO. Antes daqui só a linha do convidado era apagada e a proposta
           sobrevivia — o mapa desenha a partir das PROPOSTAS, então o fornecedor "removido"
           continuava lá, agora sem card e sem como excluir pela Concorrência. Era assim que nascia
           coluna órfã. Mas apagar proposta é destrutivo: se houver alguma, o servidor NÃO apaga de
           primeira — devolve o que está em jogo e o front pergunta, exigindo motivo. */
        $q = $pdo->prepare("SELECT id, total FROM cotacao_proposta
                            WHERE cotacao_id=? AND (ativa=1 OR ativa IS NULL)
                              AND ((fornecedor_id IS NOT NULL AND fornecedor_id=?) OR LOWER(TRIM(fornecedor_nome))=LOWER(TRIM(?)))");
        $q->execute([$cid, $ffid ?: -1, $fn]);
        $props = $q->fetchAll();

        if ($props && empty($in['com_propostas'])) {
            echo json_encode(['ok'=>false, 'precisa_confirmar'=>true, 'fornecedor'=>$fn,
                              'propostas'=>count($props),
                              'total'=>array_sum(array_map(fn($p) => (float)$p['total'], $props))], JSON_UNESCAPED_UNICODE); exit;
        }
        $motivo = trim((string)($in['motivo'] ?? ''));
        if ($props && $motivo === '') { http_response_code(400); echo json_encode(['error'=>'Escreva o motivo — a proposta será apagada e isso fica registrado no histórico.'], JSON_UNESCAPED_UNICODE); exit; }

        $pdo->beginTransaction();
        $apagadas = 0; $valor = 0.0;
        foreach ($props as $p) {   // apaga a CADEIA de revisões de cada proposta, como no excluir_proposta
            $raiz = (int)$p['id'];
            $rr = $pdo->prepare("SELECT COALESCE(raiz_id, id) FROM cotacao_proposta WHERE id=?"); $rr->execute([$raiz]);
            $raiz = (int)($rr->fetchColumn() ?: $raiz);
            $pdo->prepare("DELETE FROM cotacao_proposta_item WHERE proposta_id IN (SELECT id FROM cotacao_proposta WHERE cotacao_id=? AND (id=? OR raiz_id=?))")->execute([$cid, $raiz, $raiz]);
            $pdo->prepare("DELETE FROM cotacao_proposta WHERE cotacao_id=? AND (id=? OR raiz_id=?)")->execute([$cid, $raiz, $raiz]);
            $apagadas++; $valor += (float)$p['total'];
        }
        $pdo->prepare("DELETE FROM cotacao_fornecedor WHERE id=?")->execute([$id]);
        $pdo->commit();

        cot_log($pdo, $cid, $me, 'Concorrência',
            'Removeu ' . ($fn ?: ('convidado #' . $id)) . ' da concorrência'
            . ($apagadas ? (' — apagou ' . $apagadas . ' proposta(s), R$ ' . number_format($valor, 2, ',', '.')) : ' (não havia proposta)')
            . ($motivo !== '' ? ' · motivo: ' . $motivo : ''));
        echo json_encode(['ok'=>true, 'propostas_apagadas'=>$apagadas], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'apelido_salvar') {   // nome curto/descrição do criador (achar fácil) — ex.: "Pregos"
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só o administrador ou quem criou a cotação pode editá-la.']); exit; }
        $ap = trim((string)($in['apelido'] ?? '')); if (function_exists('mb_substr')) $ap = mb_substr($ap, 0, 160); else $ap = substr($ap, 0, 160);
        $pdo->prepare("UPDATE cotacao SET apelido=?, updated_at=? WHERE id=?")->execute([$ap !== '' ? $ap : null, date('c'), $cid]);
        cot_log($pdo, $cid, $me, 'Apelido', $ap !== '' ? ('Apelido → "' . $ap . '"') : 'Apelido removido');
        echo json_encode(['ok'=>true, 'apelido'=>$ap], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'verba_salvar') {   // edita/puxa a verba prevista da cotação
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só o administrador ou quem criou a cotação pode editá-la.']); exit; }
        $verba = (float)($in['verba'] ?? 0);
        $pdo->prepare("UPDATE cotacao SET verba=?, verba_origem=?, updated_at=? WHERE id=?")
            ->execute([$verba ?: null, trim((string)($in['verba_origem'] ?? 'manual')), date('c'), $cid]);
        cot_log($pdo, $cid, $me, 'Verba', 'Verba prevista → R$ ' . number_format($verba, 2, ',', '.'));
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'numeros_salvar') {   // nº da SOLICITAÇÃO de compra (SC) e/ou nº do PEDIDO de compra (PC)
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só o administrador ou quem criou a cotação pode editá-la.']); exit; }
        $sets = []; $args = [];
        if (array_key_exists('num_solicitacao', $in)) { $sets[] = 'num_solicitacao=?'; $args[] = trim((string)$in['num_solicitacao']) ?: null; }
        if (array_key_exists('num_pedido', $in))      { $sets[] = 'num_pedido=?';      $args[] = trim((string)$in['num_pedido']) ?: null; }
        if (!$sets) throw new Exception('nada a atualizar');
        $sets[] = 'updated_at=?'; $args[] = date('c'); $args[] = $cid;
        $pdo->prepare("UPDATE cotacao SET " . implode(',', $sets) . " WHERE id=?")->execute($args);
        cot_log($pdo, $cid, $me, 'SC/PC', 'Números atualizados' . (array_key_exists('num_solicitacao', $in) ? (' · SC: ' . trim((string)$in['num_solicitacao'])) : '') . (array_key_exists('num_pedido', $in) ? (' · PC: ' . trim((string)$in['num_pedido'])) : ''));
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'pedido_coligada_salvar') {   // FASE 2 — Nº do PEDIDO de compra de UMA coligada (multi-PC por coligada)
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só o administrador ou quem criou a cotação pode editá-la.']); exit; }
        $col = trim((string)($in['coligada'] ?? '')); $cod = (int)($in['coligada_cod'] ?? 0);
        $cm  = trim((string)($in['colidmov'] ?? '')); $pc = trim((string)($in['num_pedido'] ?? ''));
        if ($col === '' && $cod === 0) throw new Exception('coligada obrigatória');
        $now = date('c');
        // upsert por (cotacao_id, coligada_cod) — ou por nome, quando não há código
        $sel = $cod ? $pdo->prepare("SELECT id FROM cotacao_pedido WHERE cotacao_id=? AND coligada_cod=?")
                    : $pdo->prepare("SELECT id FROM cotacao_pedido WHERE cotacao_id=? AND coligada=?");
        $sel->execute($cod ? [$cid, $cod] : [$cid, $col]);
        $rid = (int)($sel->fetchColumn() ?: 0);
        if ($rid) $pdo->prepare("UPDATE cotacao_pedido SET coligada=?, coligada_cod=?, colidmov=?, num_pedido=?, updated_by=?, updated_at=? WHERE id=?")
                        ->execute([$col, $cod ?: null, $cm ?: null, $pc ?: null, $me, $now, $rid]);
        else $pdo->prepare("INSERT INTO cotacao_pedido (cotacao_id, coligada, coligada_cod, colidmov, num_pedido, updated_by, updated_at) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$cid, $col, $cod ?: null, $cm ?: null, $pc ?: null, $me, $now]);
        // denormaliza p/ o campo header num_pedido (usado na LISTA de cotações): junta os PCs distintos
        $all = $pdo->prepare("SELECT num_pedido FROM cotacao_pedido WHERE cotacao_id=? AND num_pedido IS NOT NULL AND num_pedido<>''"); $all->execute([$cid]);
        $nums = array_values(array_unique(array_filter(array_map(fn($x)=>trim((string)$x), $all->fetchAll(PDO::FETCH_COLUMN)))));
        $pdo->prepare("UPDATE cotacao SET num_pedido=?, updated_at=? WHERE id=?")->execute([$nums ? implode(', ', $nums) : null, $now, $cid]);
        cot_log($pdo, $cid, $me, 'SC/PC', 'PC da coligada ' . ($col ?: $cod) . ' → ' . ($pc ?: '—'));
        echo json_encode(['ok'=>true, 'num_pedido_resumo'=>implode(', ', $nums)], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'equaliza_salvar') {   // pontos de equalização da cotação e/ou os valores por proposta
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só o administrador ou quem criou a cotação pode editá-la.']); exit; }
        // 1) lista de pontos a conferir (texto livre, 1 por linha) — no header da cotação
        if (array_key_exists('equalizacao', $in)) {
            $pdo->prepare("UPDATE cotacao SET equalizacao=?, updated_at=? WHERE id=?")->execute([trim((string)$in['equalizacao']), date('c'), $cid]);
        }
        // 2) valores da equalização de UMA proposta (JSON ponto->valor) — valida que a proposta é desta cotação
        if (!empty($in['proposta_id'])) {
            $pid = (int)$in['proposta_id'];
            $ok = $pdo->prepare("SELECT 1 FROM cotacao_proposta WHERE id=? AND cotacao_id=?"); $ok->execute([$pid, $cid]);
            if (!$ok->fetch()) throw new Exception('proposta não pertence a esta cotação');
            $val = isset($in['equaliza']) ? json_encode((object)$in['equaliza'], JSON_UNESCAPED_UNICODE) : null;
            $pdo->prepare("UPDATE cotacao_proposta SET equaliza=? WHERE id=?")->execute([$val, $pid]);
        }
        cot_log($pdo, $cid, $me, 'Equalização', !empty($in['proposta_id']) ? ('Equalização da proposta #' . (int)$in['proposta_id'] . ' atualizada') : 'Pontos de equalização atualizados');
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'dicionario_salvar') {   // grava os itens-padrão a cotar do serviço (template global)
        $perms = user_perms($pdo, $me);
        if (empty($perms['perm_admin']) && ($perms['editar_escopo'] ?? '') !== 'todas') { http_response_code(403); echo json_encode(['error'=>'Dicionário de cotação é mudança global — só admin ou quem edita todas as obras.']); exit; }
        $sid = (int)($in['servico_id'] ?? 0); if (!$sid) throw new Exception('servico_id obrigatório');
        $itens = array_values(array_filter((array)($in['itens'] ?? []), fn($i)=>trim((string)($i['descricao'] ?? '')) !== ''));
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM cot_dicionario WHERE servico_id=?")->execute([$sid]);
        $insD = $pdo->prepare("INSERT INTO cot_dicionario (servico_id, descricao, unidade, ordem, nota, created_at) VALUES (?,?,?,?,?,?)");
        $o = 0; $now = date('c');
        foreach ($itens as $it) $insD->execute([$sid, trim((string)$it['descricao']), trim((string)($it['unidade'] ?? '')), $o++, trim((string)($it['nota'] ?? '')), $now]);
        $pdo->commit();
        echo json_encode(['ok'=>true, 'n'=>count($itens)], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'proposta') {
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só o administrador ou quem criou a cotação pode editá-la.']); exit; }
        $forn = trim((string)($in['fornecedor_nome'] ?? '')); if ($forn === '') throw new Exception('fornecedor obrigatório');
        $itens = (array)($in['itens'] ?? []);
        $total = 0.0;
        foreach ($itens as $it) $total += (float)($it['preco_total'] ?? 0);
        $now = date('c');
        $pdo->beginTransaction();
        $pid = (int)($in['proposta_id'] ?? 0);
        // NOVA OPÇÃO: o mesmo fornecedor apresentando a proposta de OUTRA FORMA (ex.: com bomba x sem bomba,
        // global x por diária). Não substitui nada — nasce como opção 2, 3… e concorre no mapa junto com a 1.
        // Por isso pula o dedup abaixo: aqui "já existe proposta desse fornecedor" é exatamente o esperado.
        $novaOpcao = !empty($in['nova_opcao']);
        $rotulo = trim((string)($in['opcao_rotulo'] ?? ''));
        // DEDUP: proposta NOVA (sem proposta_id) de um fornecedor que JÁ tem proposta vigente nesta cotação
        // → atualiza a existente em vez de criar outra (mata o duplo-submit/duplo-clique e o cadastro repetido).
        if (!$pid && !$novaOpcao) {
            $fid = (int)($in['fornecedor_id'] ?? 0);
            if ($fid) { $q = $pdo->prepare("SELECT id FROM cotacao_proposta WHERE cotacao_id=? AND fornecedor_id=? AND (ativa=1 OR ativa IS NULL) ORDER BY id DESC"); $q->execute([$cid, $fid]); }
            else { $q = $pdo->prepare("SELECT id FROM cotacao_proposta WHERE cotacao_id=? AND LOWER(TRIM(fornecedor_nome))=? AND (ativa=1 OR ativa IS NULL) ORDER BY id DESC"); $q->execute([$cid, strtolower(trim($forn))]); }
            $vig = $q->fetchAll(PDO::FETCH_COLUMN);
            /* Com MAIS DE UMA opção vigente não dá para adivinhar qual o usuário quis atualizar —
               e escolher sozinho sobrescreveria uma opção inteira em silêncio. Melhor parar e mandar
               ele usar "editar" na opção certa (ou "nova opção", se for mesmo mais uma). */
            if (count($vig) > 1) {
                $pdo->rollBack();
                echo json_encode(['error'=>'Este fornecedor já tem ' . count($vig) . ' opções de proposta nesta cotação. Use "editar" na opção certa, ou "nova opção" no card dele — assim nenhuma é sobrescrita.',
                                  'opcoes'=>count($vig)], JSON_UNESCAPED_UNICODE); exit;
            }
            $pid = (int)($vig[0] ?? 0);
        }
        $opcao = 1;
        if ($pid) {
            $pdo->prepare("UPDATE cotacao_proposta SET fornecedor_id=?, fornecedor_nome=?, prazo=?, observacoes=?, opcao_rotulo=?, total=? WHERE id=? AND cotacao_id=?")
                ->execute([($in['fornecedor_id'] ?? null) ?: null, $forn, trim((string)($in['prazo'] ?? '')), trim((string)($in['observacoes'] ?? '')), $rotulo ?: null, $total ?: null, $pid, $cid]);
            $pdo->prepare("DELETE FROM cotacao_proposta_item WHERE proposta_id=?")->execute([$pid]);
            $opcao = (int)$pdo->query("SELECT COALESCE(opcao,1) FROM cotacao_proposta WHERE id=" . $pid)->fetchColumn() ?: 1;
        } else {
            // nº da opção = MAX das opções desse fornecedor nesta cotação + 1 (conta as arquivadas também: o número não se repete)
            // COALESCE(opcao,1) por dentro: linhas antigas (anteriores à coluna) contam como opção 1
            $mo = $pdo->prepare("SELECT COALESCE(MAX(COALESCE(opcao,1)),0) FROM cotacao_proposta WHERE cotacao_id=?
                                 AND ((fornecedor_id IS NOT NULL AND fornecedor_id=?) OR LOWER(TRIM(fornecedor_nome))=?)");
            $mo->execute([$cid, (int)($in['fornecedor_id'] ?? 0) ?: -1, strtolower(trim($forn))]);
            $opcao = max(1, (int)$mo->fetchColumn() + 1);
            $pdo->prepare("INSERT INTO cotacao_proposta (cotacao_id, fornecedor_id, fornecedor_nome, prazo, observacoes, data_resposta, total, opcao, opcao_rotulo, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$cid, ($in['fornecedor_id'] ?? null) ?: null, $forn, trim((string)($in['prazo'] ?? '')), trim((string)($in['observacoes'] ?? '')), $now, $total ?: null, $opcao, $rotulo ?: null, $now]);
            $pid = (int)$pdo->lastInsertId();
        }
        $insPI = $pdo->prepare("INSERT INTO cotacao_proposta_item (proposta_id, cotacao_item_id, preco_unit, preco_total, observacao) VALUES (?,?,?,?,?)");
        foreach ($itens as $it) {
            $ciid = (int)($it['cotacao_item_id'] ?? 0); if (!$ciid) continue;
            $pu = ($it['preco_unit'] ?? '') !== '' ? (float)$it['preco_unit'] : null;
            $pt = ($it['preco_total'] ?? '') !== '' ? (float)$it['preco_total'] : null;
            $insPI->execute([$pid, $ciid, $pu, $pt, trim((string)($it['observacao'] ?? ''))]);
        }
        $pdo->prepare("UPDATE cotacao SET status=CASE WHEN status='aberta' THEN 'aguardando' ELSE status END, updated_at=? WHERE id=?")->execute([$now, $cid]);
        $pdo->commit();
        $selo = $opcao > 1 ? (' [opção ' . $opcao . ($rotulo !== '' ? ' · ' . $rotulo : '') . ']') : '';
        cot_log($pdo, $cid, $me, 'Proposta', 'Proposta de ' . $forn . $selo . ' salva — total R$ ' . number_format($total, 2, ',', '.') . ' (' . count($itens) . ' item(ns))');
        echo json_encode(['ok'=>true, 'proposta_id'=>$pid, 'opcao'=>$opcao, 'total'=>round($total, 2)], JSON_UNESCAPED_UNICODE); exit;
    }

    // NOVA REVISÃO de uma proposta: arquiva a vigente (ativa=0) e cria a próxima revisão como vigente,
    // preservando o histórico. O fornecedor mandou preço novo depois da negociação — nada se perde.
    if ($acao === 'proposta_revisar') {
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $pid = (int)($in['proposta_id'] ?? 0); if (!$pid) throw new Exception('proposta_id obrigatório');
        $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só o administrador ou quem criou a cotação pode editá-la.']); exit; }
        $cur = $pdo->prepare("SELECT * FROM cotacao_proposta WHERE id=? AND cotacao_id=?"); $cur->execute([$pid, $cid]); $cur = $cur->fetch();
        if (!$cur) throw new Exception('proposta não encontrada');
        if (($cur['ativa'] ?? null) !== null && (int)$cur['ativa'] === 0) throw new Exception('só a proposta vigente pode ser revisada');
        $raiz = ((int)($cur['raiz_id'] ?? 0)) ?: (int)$cur['id'];
        // revisão = MAX da cadeia + 1 (monotônico — nunca colide, mesmo revisando a partir de uma arquivada)
        $mx = $pdo->prepare("SELECT COALESCE(MAX(revisao),0) FROM cotacao_proposta WHERE cotacao_id=? AND (id=? OR raiz_id=?)");
        $mx->execute([$cid, $raiz, $raiz]);
        $rev = (int)$mx->fetchColumn() + 1;
        $forn = trim((string)($in['fornecedor_nome'] ?? $cur['fornecedor_nome']));
        $fid  = ($in['fornecedor_id'] ?? $cur['fornecedor_id']) ?: null;
        $itens = (array)($in['itens'] ?? []);
        $total = 0.0; foreach ($itens as $it) $total += (float)($it['preco_total'] ?? 0);
        $now = date('c');
        $pdo->beginTransaction();
        // arquiva TODA a cadeia como não-vigente (defensivo: garante uma só ativa) e cria a nova
        $pdo->prepare("UPDATE cotacao_proposta SET ativa=0 WHERE cotacao_id=? AND (id=? OR raiz_id=?)")->execute([$cid, $raiz, $raiz]);
        // a revisão herda a OPÇÃO da proposta revisada — revisar a opção 2 gera a rev seguinte DA OPÇÃO 2
        // (cada opção é uma cadeia própria; as outras opções do mesmo fornecedor não são tocadas)
        $opc = (int)($cur['opcao'] ?? 1) ?: 1;
        $rot = array_key_exists('opcao_rotulo', $in) ? trim((string)$in['opcao_rotulo']) : trim((string)($cur['opcao_rotulo'] ?? ''));
        $pdo->prepare("INSERT INTO cotacao_proposta (cotacao_id, fornecedor_id, fornecedor_nome, prazo, observacoes, data_resposta, total, revisao, raiz_id, ativa, opcao, opcao_rotulo, created_at) VALUES (?,?,?,?,?,?,?,?,?,1,?,?,?)")
            ->execute([$cid, $fid, $forn, trim((string)($in['prazo'] ?? '')), trim((string)($in['observacoes'] ?? '')), $now, $total ?: null, $rev, $raiz, $opc, $rot ?: null, $now]);
        $novo = (int)$pdo->lastInsertId();
        $insPI = $pdo->prepare("INSERT INTO cotacao_proposta_item (proposta_id, cotacao_item_id, preco_unit, preco_total, observacao) VALUES (?,?,?,?,?)");
        foreach ($itens as $it) {
            $ciid = (int)($it['cotacao_item_id'] ?? 0); if (!$ciid) continue;
            $pu = ($it['preco_unit'] ?? '') !== '' ? (float)$it['preco_unit'] : null;
            $pt = ($it['preco_total'] ?? '') !== '' ? (float)$it['preco_total'] : null;
            $insPI->execute([$novo, $ciid, $pu, $pt, trim((string)($it['observacao'] ?? ''))]);
        }
        $pdo->prepare("UPDATE cotacao SET updated_at=? WHERE id=?")->execute([$now, $cid]);
        $pdo->commit();
        cot_log($pdo, $cid, $me, 'Proposta', 'Revisão ' . $rev . ' da proposta de ' . $forn
            . ($opc > 1 ? (' [opção ' . $opc . ($rot !== '' ? ' · ' . $rot : '') . ']') : '')
            . ' — total R$ ' . number_format($total, 2, ',', '.')
            /* A revisão nasce QUALIFICADA de propósito: o defeito era da proposta anterior, e o fornecedor
               mandou outra justamente para corrigi-lo. Fica dito no histórico p/ ninguém achar que "sumiu". */
            . ((int)($cur['desq'] ?? 0) ? ' · a revisão anterior estava desqualificada ('
                . cot_desq_texto($cur['desq_motivo'] ?? '', $cur['desq_obs'] ?? '') . ') — esta volta a concorrer' : ''));
        echo json_encode(['ok'=>true, 'proposta_id'=>$novo, 'revisao'=>$rev, 'opcao'=>$opc, 'total'=>round($total, 2)], JSON_UNESCAPED_UNICODE); exit;
    }

    /* DESQUALIFICAR / REQUALIFICAR uma proposta. Marca A PROPOSTA (não o fornecedor): ele segue na
       concorrência e pode mandar revisão nova — que nasce qualificada, porque o defeito era daquela
       proposta. A desqualificada não some do mapa: fica visível com o motivo e fora do julgamento.
       Fica registrada na trilha da cotação (quem, quando, motivo e justificativa) — é decisão que
       alguém vai questionar depois, principalmente quando desqualifica a mais barata. */
    if ($acao === 'proposta_desqualificar') {
        $pid = (int)($in['proposta_id'] ?? 0); if (!$pid) throw new Exception('proposta_id obrigatório');
        $row = $pdo->prepare("SELECT id, cotacao_id, fornecedor_nome, total, opcao, opcao_rotulo, revisao, ativa, desq, desq_motivo, desq_obs FROM cotacao_proposta WHERE id=?");
        $row->execute([$pid]); $p = $row->fetch();
        if (!$p) throw new Exception('proposta não encontrada');
        $cid = (int)$p['cotacao_id'];
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só o administrador, o gerente, quem criou ou quem recebeu a cotação compartilhada pode desqualificar uma proposta.'], JSON_UNESCAPED_UNICODE); exit; }
        if (($p['ativa'] ?? null) !== null && (int)$p['ativa'] === 0) throw new Exception('esta revisão está arquivada — desqualifique a proposta vigente');
        $rot = ' de ' . ((string)$p['fornecedor_nome'] ?: ('#' . $pid))
             . (((int)($p['opcao'] ?? 1)) > 1 ? (' [opção ' . (int)$p['opcao'] . (trim((string)$p['opcao_rotulo']) !== '' ? ' · ' . trim((string)$p['opcao_rotulo']) : '') . ']') : '')
             . ((float)($p['total'] ?? 0) > 0 ? (' — R$ ' . number_format((float)$p['total'], 2, ',', '.')) : '');

        if (!empty($in['desfazer'])) {   // REQUALIFICAR: volta ao julgamento
            if (!(int)($p['desq'] ?? 0)) { echo json_encode(['ok'=>true, 'desq'=>0, 'ja'=>true], JSON_UNESCAPED_UNICODE); exit; }
            $antes = cot_desq_texto($p['desq_motivo'] ?? '', $p['desq_obs'] ?? '');
            $pdo->prepare("UPDATE cotacao_proposta SET desq=0, desq_motivo=NULL, desq_obs=NULL, desq_por=NULL, desq_nome=NULL, desq_at=NULL WHERE id=?")->execute([$pid]);
            $pdo->prepare("UPDATE cotacao SET updated_at=? WHERE id=?")->execute([date('c'), $cid]);
            cot_log($pdo, $cid, $me, 'Desqualificação', 'Requalificou a proposta' . $rot . ' — volta a concorrer no mapa'
                . ($antes !== '' ? ' (estava desqualificada por: ' . $antes . ')' : ''));
            echo json_encode(['ok'=>true, 'desq'=>0], JSON_UNESCAPED_UNICODE); exit;
        }

        $motivo = trim((string)($in['motivo'] ?? ''));
        if (cot_desq_label($motivo) === '') throw new Exception('escolha um motivo da lista para desqualificar a proposta');
        $obs = trim((string)($in['justificativa'] ?? $in['desq_obs'] ?? ''));
        // 'outro' existe justamente para o que não está na lista — sem o texto ele não diz nada a quem ler depois
        // strlen (bytes), não mb_strlen: a hospedagem NÃO tem mbstring e mb_strlen aqui derrubava o
        // endpoint com 500 em vez de devolver a mensagem — só aparecia em produção, nunca no sandbox
        if ($motivo === 'outro' && strlen($obs) < 5) throw new Exception('escreva a justificativa — em "outro motivo" ela é o próprio motivo');
        if (function_exists('mb_substr')) $obs = mb_substr($obs, 0, 1000); else $obs = substr($obs, 0, 1000);
        $nome = ''; try { $pp = user_perms($pdo, $me); $nome = (string)($pp['nome'] ?? ''); } catch (Throwable $e) {}
        $now = date('c');
        $pdo->prepare("UPDATE cotacao_proposta SET desq=1, desq_motivo=?, desq_obs=?, desq_por=?, desq_nome=?, desq_at=? WHERE id=?")
            ->execute([$motivo, $obs !== '' ? $obs : null, (string)$me, $nome ?: null, $now, $pid]);
        $pdo->prepare("UPDATE cotacao SET updated_at=? WHERE id=?")->execute([$now, $cid]);
        cot_log($pdo, $cid, $me, 'Desqualificação', 'Desqualificou a proposta' . $rot . ' · motivo: ' . cot_desq_texto($motivo, $obs)
            . ' — sai do julgamento (o fornecedor continua na concorrência)');
        echo json_encode(['ok'=>true, 'desq'=>1, 'motivo'=>$motivo, 'texto'=>cot_desq_texto($motivo, $obs)], JSON_UNESCAPED_UNICODE); exit;
    }

    /* ───────── FECHAMENTO: salvar (cria ou edita o rascunho da rodada) ─────────
       Quem fecha é quem gere a cotação (o comprador). Fechamento APROVADO não se edita — para mexer
       é preciso reabrir, e reabrir é ato registrado, porque o número já pode ter virado pagamento. */
    if ($acao === 'fechamento_salvar') {
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só o administrador, o gerente, quem criou ou quem recebeu a cotação compartilhada pode fechar a negociação.'], JSON_UNESCAPED_UNICODE); exit; }
        $fid = (int)($in['fechamento_id'] ?? 0);
        $linhas = array_values(array_filter((array)($in['linhas'] ?? []), fn($l) => (int)($l['cotacao_item_id'] ?? 0) > 0));
        if (!$linhas) throw new Exception('escolha ao menos um item com fornecedor e preço');
        $perms = user_perms($pdo, $me); $now = date('c');

        if ($fid) {
            $cur = $pdo->prepare("SELECT * FROM cotacao_fechamento WHERE id=? AND cotacao_id=?"); $cur->execute([$fid, $cid]);
            $cur = $cur->fetch(); if (!$cur) throw new Exception('fechamento não encontrado');
            if (($cur['status'] ?? '') === 'homologado') throw new Exception('este fechamento já foi aprovado — reabra antes de editar');
            $rodada = (int)$cur['rodada'];
        } else {
            // rodada = próxima da cotação. Só existe rodada nova depois da anterior APROVADA: sem isso
            // dava para empilhar rascunhos e ninguém saberia qual é a régua.
            $mx = $pdo->prepare("SELECT COALESCE(MAX(rodada),0), SUM(CASE WHEN status<>'homologado' THEN 1 ELSE 0 END) FROM cotacao_fechamento WHERE cotacao_id=?");
            $mx->execute([$cid]); $r = $mx->fetch(PDO::FETCH_NUM);
            if ((int)($r[1] ?? 0) > 0) throw new Exception('já existe um fechamento em aberto nesta cotação — conclua ou exclua ele antes de abrir outro');
            $rodada = (int)($r[0] ?? 0) + 1;
        }
        $total = 0.0;
        foreach ($linhas as $l) $total += (float)($l['preco_total'] ?? 0);

        $campos = [
            'origem_preco'   => trim((string)($in['origem_preco'] ?? '')) ?: null,
            'etr_participou' => !empty($in['etr_participou']) ? 1 : 0,
            'responsavel_id' => trim((string)($in['responsavel_id'] ?? '')) ?: null,
            'responsavel_nome' => trim((string)($in['responsavel_nome'] ?? '')) ?: null,
            'data_fechamento'=> trim((string)($in['data_fechamento'] ?? '')) ?: substr($now, 0, 10),
            'cond_pagamento' => trim((string)($in['cond_pagamento'] ?? '')) ?: null,
            'cond_prazo'     => trim((string)($in['cond_prazo'] ?? '')) ?: null,
            'cond_frete'     => trim((string)($in['cond_frete'] ?? '')) ?: null,
            'cond_validade'  => trim((string)($in['cond_validade'] ?? '')) ?: null,
            'cond_obs'       => trim((string)($in['cond_obs'] ?? '')) ?: null,
            'justificativa'  => trim((string)($in['justificativa'] ?? '')) ?: null,
            'total'          => $total ?: null,
        ];
        $pdo->beginTransaction();
        if ($fid) {
            $sets = []; $args = [];
            foreach ($campos as $k => $v) { $sets[] = "$k=?"; $args[] = $v; }
            $sets[] = 'status=?'; $args[] = 'rascunho';   // editou, volta a rascunho (inclusive se estava devolvido)
            $sets[] = 'updated_at=?'; $args[] = $now; $args[] = $fid;
            $pdo->prepare("UPDATE cotacao_fechamento SET " . implode(',', $sets) . " WHERE id=?")->execute($args);
            $pdo->prepare("DELETE FROM cotacao_fechamento_linha WHERE fechamento_id=?")->execute([$fid]);
        } else {
            $cols = array_keys($campos);
            $pdo->prepare("INSERT INTO cotacao_fechamento (cotacao_id, rodada, status, " . implode(',', $cols)
                        . ", criado_por, criado_nome, created_at, updated_at) VALUES (?,?, 'rascunho', "
                        . implode(',', array_fill(0, count($cols), '?')) . ", ?,?,?,?)")
                ->execute(array_merge([$cid, $rodada], array_values($campos), [$me, $perms['nome'] ?? null, $now, $now]));
            $fid = (int)$pdo->lastInsertId();
        }
        $ins = $pdo->prepare("INSERT INTO cotacao_fechamento_linha (fechamento_id, cotacao_item_id, proposta_id, origem, origem_ref, fornecedor_id, fornecedor_nome, preco_unit, quantidade, preco_total, lote, justificativa, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($linhas as $l) {
            $pu = ($l['preco_unit'] ?? '') !== '' ? (float)$l['preco_unit'] : null;
            $q  = ($l['quantidade'] ?? '') !== '' ? (float)$l['quantidade'] : null;
            $pt = ($l['preco_total'] ?? '') !== '' ? (float)$l['preco_total'] : (($pu !== null && $q !== null) ? $pu * $q : null);
            $ins->execute([$fid, (int)$l['cotacao_item_id'], ($l['proposta_id'] ?? null) ?: null,
                trim((string)($l['origem'] ?? 'proposta')) ?: 'proposta', trim((string)($l['origem_ref'] ?? '')) ?: null,
                ($l['fornecedor_id'] ?? null) ?: null, trim((string)($l['fornecedor_nome'] ?? '')),
                $pu, $q, $pt, trim((string)($l['lote'] ?? '')) ?: null, trim((string)($l['justificativa'] ?? '')) ?: null, $now]);
        }
        $pdo->commit();
        cot_log($pdo, $cid, $me, 'Fechamento', 'Rascunho do fechamento da rodada ' . $rodada . ' salvo — '
            . count($linhas) . ' linha(s), total R$ ' . number_format($total, 2, ',', '.'));
        echo json_encode(['ok'=>true, 'fechamento_id'=>$fid, 'rodada'=>$rodada, 'total'=>round($total, 2)], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'fechamento_enviar') {   // rascunho → aguardando aprovação
        $fid = (int)($in['fechamento_id'] ?? 0); if (!$fid) throw new Exception('fechamento_id obrigatório');
        $q = $pdo->prepare("SELECT * FROM cotacao_fechamento WHERE id=?"); $q->execute([$fid]); $f = $q->fetch();
        if (!$f) throw new Exception('fechamento não encontrado');
        $cid = (int)$f['cotacao_id'];
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão para enviar este fechamento.']); exit; }
        if (($f['status'] ?? '') === 'homologado') throw new Exception('este fechamento já foi aprovado');
        $n = (int)$pdo->query("SELECT COUNT(*) FROM cotacao_fechamento_linha WHERE fechamento_id=" . $fid)->fetchColumn();
        if (!$n) throw new Exception('fechamento sem linhas — escolha os fornecedores antes de enviar');
        $pdo->prepare("UPDATE cotacao_fechamento SET status='aguardando', updated_at=? WHERE id=?")->execute([date('c'), $fid]);
        cot_log($pdo, $cid, $me, 'Fechamento', 'Rodada ' . (int)$f['rodada'] . ' enviada para aprovação'
            . ((int)$f['rodada'] === 1 ? ' (homologação do gerente)' : ' (assinatura)'));
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'fechamento_aprovar') {   // homologa (rodada 1) / assina (rodada 2+) — congela
        $fid = (int)($in['fechamento_id'] ?? 0); if (!$fid) throw new Exception('fechamento_id obrigatório');
        $q = $pdo->prepare("SELECT * FROM cotacao_fechamento WHERE id=?"); $q->execute([$fid]); $f = $q->fetch();
        if (!$f) throw new Exception('fechamento não encontrado');
        if (!cot_pode_aprovar_fechamento($pdo, $me)) { http_response_code(403); echo json_encode(['error'=>'Só o gerente, o administrador ou quem tem alçada de fechamento pode aprovar.'], JSON_UNESCAPED_UNICODE); exit; }
        if (($f['status'] ?? '') === 'homologado') { echo json_encode(['ok'=>true, 'ja'=>true], JSON_UNESCAPED_UNICODE); exit; }
        if (($f['status'] ?? '') !== 'aguardando') throw new Exception('este fechamento ainda não foi enviado para aprovação');
        $cid = (int)$f['cotacao_id']; $rod = (int)$f['rodada'];
        $p = user_perms($pdo, $me); $now = date('c');
        $pdo->prepare("UPDATE cotacao_fechamento SET status='homologado', aprovado_por=?, aprovado_nome=?, aprovado_at=?, updated_at=? WHERE id=?")
            ->execute([(string)$me, $p['nome'] ?? null, $now, $now, $fid]);
        $pdo->prepare("UPDATE cotacao SET updated_at=? WHERE id=?")->execute([$now, $cid]);
        cot_log($pdo, $cid, $me, 'Fechamento', ($rod === 1 ? 'HOMOLOGOU a rodada 1 — este é o Preço Inicial de Referência'
            : 'ASSINOU o fechamento da rodada ' . $rod) . ' · total R$ ' . number_format((float)($f['total'] ?? 0), 2, ',', '.'));
        echo json_encode(['ok'=>true, 'rodada'=>$rod], JSON_UNESCAPED_UNICODE); exit;
    }

    /* DEVOLVER — o mecanismo que o Murilo pediu: "você esqueceu de cotar com esses 3 fornecedores,
       inclua no mapa antes de fechar". Volta para rascunho com o motivo escrito, e o motivo fica
       tanto no fechamento (a tela mostra) quanto no histórico da cotação. */
    if ($acao === 'fechamento_devolver') {
        $fid = (int)($in['fechamento_id'] ?? 0); if (!$fid) throw new Exception('fechamento_id obrigatório');
        $motivo = trim((string)($in['motivo'] ?? ''));
        if (strlen($motivo) < 5) throw new Exception('escreva o que falta — é o que o comprador vai ler para corrigir');
        $q = $pdo->prepare("SELECT * FROM cotacao_fechamento WHERE id=?"); $q->execute([$fid]); $f = $q->fetch();
        if (!$f) throw new Exception('fechamento não encontrado');
        if (!cot_pode_aprovar_fechamento($pdo, $me)) { http_response_code(403); echo json_encode(['error'=>'Só quem aprova pode devolver.']); exit; }
        if (($f['status'] ?? '') === 'homologado') throw new Exception('fechamento já aprovado — use reabrir');
        $p = user_perms($pdo, $me); $now = date('c');
        $pdo->prepare("UPDATE cotacao_fechamento SET status='devolvido', devolvido_motivo=?, devolvido_por=?, devolvido_nome=?, devolvido_at=?, updated_at=? WHERE id=?")
            ->execute([$motivo, (string)$me, $p['nome'] ?? null, $now, $now, $fid]);
        cot_log($pdo, (int)$f['cotacao_id'], $me, 'Fechamento', 'DEVOLVEU a rodada ' . (int)$f['rodada'] . ' ao comprador · ' . $motivo);
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'fechamento_reabrir') {   // desfaz a aprovação (admin/gerente/alçada) — invalida o cálculo
        $fid = (int)($in['fechamento_id'] ?? 0); if (!$fid) throw new Exception('fechamento_id obrigatório');
        $motivo = trim((string)($in['motivo'] ?? ''));
        if (strlen($motivo) < 5) throw new Exception('escreva o motivo — reabrir um fechamento aprovado muda o cálculo do ganho');
        $q = $pdo->prepare("SELECT * FROM cotacao_fechamento WHERE id=?"); $q->execute([$fid]); $f = $q->fetch();
        if (!$f) throw new Exception('fechamento não encontrado');
        if (!cot_pode_aprovar_fechamento($pdo, $me)) { http_response_code(403); echo json_encode(['error'=>'Só quem aprova pode reabrir um fechamento.']); exit; }
        $pdo->prepare("UPDATE cotacao_fechamento SET status='rascunho', aprovado_por=NULL, aprovado_nome=NULL, aprovado_at=NULL, updated_at=? WHERE id=?")
            ->execute([date('c'), $fid]);
        cot_log($pdo, (int)$f['cotacao_id'], $me, 'Fechamento', 'REABRIU o fechamento da rodada ' . (int)$f['rodada']
            . ' (aprovação desfeita, o ganho volta a ser recalculado) · ' . $motivo);
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'fechamento_excluir') {   // só rascunho/devolvido, e por quem gere a cotação
        $fid = (int)($in['fechamento_id'] ?? 0); if (!$fid) throw new Exception('fechamento_id obrigatório');
        $q = $pdo->prepare("SELECT * FROM cotacao_fechamento WHERE id=?"); $q->execute([$fid]); $f = $q->fetch();
        if (!$f) { echo json_encode(['ok'=>true, 'ja'=>true]); exit; }
        $cid = (int)$f['cotacao_id'];
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão.']); exit; }
        if (($f['status'] ?? '') === 'homologado') throw new Exception('fechamento aprovado não se exclui — reabra se precisar corrigir');
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM cotacao_fechamento_linha WHERE fechamento_id=?")->execute([$fid]);
        $pdo->prepare("DELETE FROM cotacao_fechamento WHERE id=?")->execute([$fid]);
        $pdo->commit();
        cot_log($pdo, $cid, $me, 'Fechamento', 'Excluiu o rascunho de fechamento da rodada ' . (int)$f['rodada']);
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    /* APURAÇÃO: aceitar ou CONTESTAR o ganho de um projeto na medição do mês.
       Contestar é direito contratual (cláusula 3.3: 10 dias úteis para manifestação por escrito), e
       é o que o Murilo pediu — "não concordo com esse ganho aqui, por conta disso". Ao sair de
       'análise' os valores são CONGELADOS: medição fechada não pode escorregar porque alguém
       reabriu um fechamento depois. */
    if ($acao === 'apuracao_salvar') {
        if (!cot_pode_ver_ganhos($pdo, $me)) { http_response_code(403); echo json_encode(['error'=>'Sem permissão para tratar a apuração.'], JSON_UNESCAPED_UNICODE); exit; }
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $st = trim((string)($in['status'] ?? 'analise'));
        if (!in_array($st, ['analise', 'aceito', 'contestado'], true)) throw new Exception('status inválido');
        $obs = trim((string)($in['observacao'] ?? ''));
        if ($st === 'contestado' && strlen($obs) < 5) throw new Exception('escreva por que não concorda — a contestação sem motivo não serve para a discussão com a consultoria');
        $fech = cot_fechamentos($pdo, $cid);
        $ap = array_values(array_filter($fech['fechamentos'], fn($x) => ($x['status'] ?? '') === 'homologado'));
        $g = count($ap) >= 2 ? cot_fech_ganho($ap[0], $ap[count($ap) - 1]) : null;
        if (!$g) throw new Exception('esta cotação ainda não tem duas rodadas aprovadas — não há ganho a apurar');
        $comp = !empty($ap[count($ap) - 1]['aprovado_at']) ? substr((string)$ap[count($ap) - 1]['aprovado_at'], 0, 7) : date('Y-m');
        $p = user_perms($pdo, $me); $now = date('c');
        // congela os valores ao sair de "em análise"; voltar para análise descongela
        $snapG = $st === 'analise' ? null : $g['ganho'];
        $snapB = $st === 'analise' ? null : $g['total_base'];
        $snapF = $st === 'analise' ? null : $g['total_final'];
        $ex = $pdo->prepare("SELECT id FROM cotacao_apuracao WHERE cotacao_id=?"); $ex->execute([$cid]);
        $aid = (int)($ex->fetchColumn() ?: 0);
        if ($aid) $pdo->prepare("UPDATE cotacao_apuracao SET competencia=?, status=?, observacao=?, ganho_snap=?, base_snap=?, final_snap=?, por=?, por_nome=?, updated_at=? WHERE id=?")
                        ->execute([$comp, $st, $obs ?: null, $snapG, $snapB, $snapF, (string)$me, $p['nome'] ?? null, $now, $aid]);
        else $pdo->prepare("INSERT INTO cotacao_apuracao (cotacao_id, competencia, status, observacao, ganho_snap, base_snap, final_snap, pct_etr, por, por_nome, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$cid, $comp, $st, $obs ?: null, $snapG, $snapB, $snapF, COT_ETR_PCT, (string)$me, $p['nome'] ?? null, $now, $now]);
        cot_log($pdo, $cid, $me, 'Apuração', ['analise'=>'Devolveu a apuração para análise',
                'aceito'=>'ACEITOU o ganho apurado de R$ ' . number_format($g['ganho'], 2, ',', '.') . ' (competência ' . $comp . ')',
                'contestado'=>'CONTESTOU o ganho apurado de R$ ' . number_format($g['ganho'], 2, ',', '.') . ' (competência ' . $comp . ')'][$st]
                . ($obs !== '' ? ' · ' . $obs : ''));
        echo json_encode(['ok'=>true, 'status'=>$st, 'competencia'=>$comp], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'status') {
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $row = $pdo->prepare("SELECT COALESCE(obra_id,1) AS obra, servico_id, num_pedido FROM cotacao WHERE id=?"); $row->execute([$cid]);
        $cot = $row->fetch(); if (!$cot) throw new Exception('cotação não encontrada');
        $obra = (int)$cot['obra'];
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só o administrador ou quem criou a cotação pode editá-la.']); exit; }
        $sets = []; $args = [];
        // se veio o nº do pedido junto, grava e usa ele na trava
        $pc = trim((string)($cot['num_pedido'] ?? ''));
        if (array_key_exists('num_pedido', $in)) { $pc = trim((string)$in['num_pedido']); $sets[] = 'num_pedido=?'; $args[] = $pc ?: null; }
        if (isset($in['status']) && in_array($in['status'], ['aberta','aguardando','finalizada'], true)) {
            // TRAVA: cotação AVULSA (sem vínculo ao radar) só finaliza com nº do PEDIDO DE COMPRA — exceto admin (exceção).
            // FASE 2: se atravessa VÁRIAS coligadas, exige 1 PC POR COLIGADA (cada coligada tem seu pedido).
            if ($in['status'] === 'finalizada' && empty($cot['servico_id'])) {
                $perms = user_perms($pdo, $me);
                if (empty($perms['perm_admin'])) {
                    $need = [];   // coligada => coligada_cod (coligadas presentes nos itens)
                    foreach ($pdo->query("SELECT DISTINCT solic_coligada, solic_colidmov FROM cotacao_item WHERE cotacao_id=$cid AND solic_coligada IS NOT NULL AND solic_coligada<>''") as $r2) {
                        $cn = trim((string)$r2['solic_coligada']); $cm = trim((string)$r2['solic_colidmov']);
                        $cc = (strpos($cm, '-') !== false) ? (int)substr($cm, 0, strpos($cm, '-')) : 0;
                        if (!$cc && function_exists('coligada_cod_de_nome')) $cc = coligada_cod_de_nome($cn);
                        $need[$cn] = $cc;
                    }
                    if (count($need) > 1) {   // MULTI-COLIGADA: cada uma precisa do seu PC (cotacao_pedido)
                        $have = [];
                        foreach ($pdo->query("SELECT coligada, coligada_cod, num_pedido FROM cotacao_pedido WHERE cotacao_id=$cid") as $r3)
                            if (trim((string)$r3['num_pedido']) !== '') { $have['c:'.(int)$r3['coligada_cod']] = 1; $have['n:'.trim((string)$r3['coligada'])] = 1; }
                        $faltam = [];
                        foreach ($need as $cn => $cc) if (!isset($have['c:'.$cc]) && !isset($have['n:'.$cn])) $faltam[] = preg_replace('/\s+(EMPREENDIMENTO|EMPREENDIMENTOS).*/i', '', $cn);
                        if ($faltam) { echo json_encode(['error'=>'Informe o nº do PEDIDO DE COMPRA de cada coligada para finalizar. Faltam: '.implode(', ', $faltam).'.', 'precisa_pedido'=>true, 'multi_coligada'=>true], JSON_UNESCAPED_UNICODE); exit; }
                    } elseif ($pc === '') {   // coligada única (ou avulsa sem origem): mantém a regra do PC único
                        echo json_encode(['error'=>'Informe o nº do PEDIDO DE COMPRA para finalizar esta cotação avulsa (sem vínculo ao radar).', 'precisa_pedido'=>true], JSON_UNESCAPED_UNICODE); exit;
                    }
                }
            }
            $sets[] = 'status=?'; $args[] = $in['status'];
        }
        if (isset($in['aprovacao']) && in_array($in['aprovacao'], ['aguardando','aprovada','reprovada'], true)) { $sets[] = 'aprovacao=?'; $args[] = $in['aprovacao']; }
        if (!$sets) throw new Exception('nada a atualizar');
        $sets[] = 'updated_at=?'; $args[] = date('c'); $args[] = $cid;
        $pdo->prepare("UPDATE cotacao SET " . implode(',', $sets) . " WHERE id=?")->execute($args);
        cot_log($pdo, $cid, $me, 'Status', (isset($in['status']) ? ('Status → ' . $in['status']) : '') . (isset($in['aprovacao']) ? ((isset($in['status']) ? ' · ' : '') . 'Aprovação → ' . $in['aprovacao']) : ''));
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'itens_salvar') {   // add/editar/excluir itens a cotar (preserva IDs; remove os tirados + suas propostas)
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só o administrador ou quem criou a cotação pode editar os itens.']); exit; }
        $itens = (array)($in['itens'] ?? []); $now = date('c');
        $pdo->beginTransaction();
        $existing = []; foreach ($pdo->query("SELECT id FROM cotacao_item WHERE cotacao_id=$cid") as $r) $existing[(int)$r['id']] = true;
        $keep = []; $o = 0;
        $ins = $pdo->prepare("INSERT INTO cotacao_item (cotacao_id, descricao, unidade, quantidade, observacao, ordem) VALUES (?,?,?,?,?,?)");
        $upd = $pdo->prepare("UPDATE cotacao_item SET descricao=?, unidade=?, quantidade=?, observacao=?, ordem=? WHERE id=? AND cotacao_id=?");
        foreach ($itens as $it) {
            $desc = trim((string)($it['descricao'] ?? '')); if ($desc === '') continue;
            $q = (($it['quantidade'] ?? null) !== null && $it['quantidade'] !== '') ? (float)$it['quantidade'] : null;
            $id = (int)($it['id'] ?? 0);
            if ($id && isset($existing[$id])) { $upd->execute([$desc, trim((string)($it['unidade'] ?? '')), $q, trim((string)($it['observacao'] ?? '')), $o++, $id, $cid]); $keep[$id] = true; }
            else { $ins->execute([$cid, $desc, trim((string)($it['unidade'] ?? '')), $q, trim((string)($it['observacao'] ?? '')), $o++]); }
        }
        foreach ($existing as $id => $_) if (empty($keep[$id])) { $pdo->prepare("DELETE FROM cotacao_proposta_item WHERE cotacao_item_id=?")->execute([$id]); $pdo->prepare("DELETE FROM cotacao_item WHERE id=?")->execute([$id]); }
        $pdo->prepare("UPDATE cotacao SET updated_at=? WHERE id=?")->execute([$now, $cid]);
        $pdo->commit();
        cot_log($pdo, $cid, $me, 'Itens', 'Itens a cotar editados (' . count(array_filter($itens, fn($i) => trim((string)($i['descricao'] ?? '')) !== '')) . ' na lista)');
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'excluir') {
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        if (!cot_can_manage($pdo, $me, $cid)) { http_response_code(403); echo json_encode(['error'=>'Só o administrador ou quem criou a cotação pode excluí-la.']); exit; }
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM cotacao_proposta_item WHERE proposta_id IN (SELECT id FROM cotacao_proposta WHERE cotacao_id=$cid)");
        $pdo->prepare("DELETE FROM cotacao_proposta WHERE cotacao_id=?")->execute([$cid]);
        $pdo->prepare("DELETE FROM cotacao_item WHERE cotacao_id=?")->execute([$cid]);
        $pdo->prepare("DELETE FROM cotacao_fornecedor WHERE cotacao_id=?")->execute([$cid]);
        $pdo->prepare("DELETE FROM cotacao_anexo WHERE cotacao_id=?")->execute([$cid]);
        $pdo->prepare("DELETE FROM carta_gerada WHERE cotacao_id=?")->execute([$cid]);
        // desvincula a solicitação de compra que apontava p/ esta cotação (evita "marcação órfã" na fila de Solicitações)
        // e reverte o status automático 'em_cotacao' -> 'pendente' (a solicitação volta a precisar de cotação)
        try { $pdo->prepare("UPDATE solic_overlay SET cotacao_id=NULL, status=CASE WHEN status='em_cotacao' THEN 'pendente' ELSE status END WHERE cotacao_id=?")->execute([$cid]); } catch (Throwable $e) {}
        $pdo->prepare("DELETE FROM cotacao WHERE id=?")->execute([$cid]);
        $pdo->commit();
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'excluir_proposta') {
        $pid = (int)($in['proposta_id'] ?? 0); if (!$pid) throw new Exception('proposta_id obrigatório');
        $row = $pdo->prepare("SELECT p.cotacao_id, p.raiz_id, p.fornecedor_nome, p.total, c.obra_id FROM cotacao_proposta p JOIN cotacao c ON c.id=p.cotacao_id WHERE p.id=?"); $row->execute([$pid]);
        $r = $row->fetch(); if (!$r) throw new Exception('proposta não encontrada');
        if (!cot_can_manage($pdo, $me, (int)$r['cotacao_id'])) { http_response_code(403); echo json_encode(['error'=>'Só o administrador ou quem criou a cotação pode editá-la.']); exit; }
        /* Motivo só quando há VALOR a perder. Exigir justificativa para apagar uma proposta zerada,
           digitada errada há dez segundos, é atrito sem retorno — e atrito é o que faz gente parar
           de registrar as coisas. Com valor, fica no histórico. */
        $motivoP = trim((string)($in['motivo'] ?? ''));
        if ((float)($r['total'] ?? 0) > 0 && $motivoP === '') {
            http_response_code(400);
            echo json_encode(['error'=>'Escreva o motivo — esta proposta tem valor lançado e a exclusão fica registrada no histórico.'], JSON_UNESCAPED_UNICODE); exit;
        }
        // exclui a CADEIA inteira de revisões (id=raiz OU raiz_id=raiz) — não deixa revisão arquivada órfã/invisível
        $cidp = (int)$r['cotacao_id']; $raiz = ((int)($r['raiz_id'] ?? 0)) ?: $pid;
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM cotacao_proposta_item WHERE proposta_id IN (SELECT id FROM cotacao_proposta WHERE cotacao_id=? AND (id=? OR raiz_id=?))")->execute([$cidp, $raiz, $raiz]);
        $pdo->prepare("DELETE FROM cotacao_proposta WHERE cotacao_id=? AND (id=? OR raiz_id=?)")->execute([$cidp, $raiz, $raiz]);
        $pdo->commit();
        cot_log($pdo, $cidp, $me, 'Proposta', 'Excluiu a proposta de ' . ((string)($r['fornecedor_nome'] ?? '') ?: ('#' . $pid))
            . ((float)($r['total'] ?? 0) > 0 ? (' — R$ ' . number_format((float)$r['total'], 2, ',', '.')) : '')
            . ' (cadeia de revisões inteira)' . ($motivoP !== '' ? ' · motivo: ' . $motivoP : ''));
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    throw new Exception('ação inválida');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
