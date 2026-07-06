<?php
/**
 * Cria itens no radar.
 * POST JSON:
 *   {acao:"novo", nome, grupo, tipo, curva, copy_from?}     -> 1 item
 *   {acao:"desdobrar", ordem}                                -> 2 itens (MAT) e (MO) a partir do item
 *   {acao:"excluir", ordem}                                  -> remove o item
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

$SUFIXO = ['Material'=>'(MAT)', 'Mão de obra'=>'(MO)', 'Material + MO'=>'(MAT + MO)', 'Empreitada'=>'(EMP)', 'Locação'=>'(LOC)'];
function base_nome($n){ return trim(preg_replace('/\s*\((MAT|MO|EMP|LOC|MAT \+ MO)\)\s*$/u','',$n)); }

try {
    $pdo = db(); db_seed_if_empty();
    $in = json_decode(file_get_contents('php://input'), true);
    $acao = $in['acao'] ?? 'novo';
    $me = $in['me'] ?? null;
    $OBRA = max(1, (int)($in['obra'] ?? 1));   // multi-obra (criação replica p/ todas; responsável/exclusão usam a obra)

    // ---- PERMISSÃO (servidor): só cria/altera quem tem escopo de edição ----
    $perms = user_perms($pdo, $me);
    if (!can_edit_obra($perms, $OBRA)) {
        http_response_code(403);
        echo json_encode(['error'=>'Sem permissão de edição.'], JSON_UNESCAPED_UNICODE); exit;
    }
    $pdo->beginTransaction(); // criação/exclusão + histórico atômicos (evita item duplicado em retry)

    if ($acao === 'excluir') {
        $ordem = (int)($in['ordem'] ?? 0);
        // POR PADRÃO remove o item SÓ DESTA OBRA (o radar de cada obra é INNER JOIN em radar_item):
        // apagar o radar_item da obra atual tira o item do radar dela e MANTÉM nas outras obras.
        // Só remove do CATÁLOGO (todas as obras + a definição do serviço) com a flag explícita `catalogo` (admin).
        $doCatalogo = !empty($in['catalogo']);
        $nm = $pdo->prepare("SELECT nome FROM servico WHERE id=?"); $nm->execute([$ordem]); $nome = $nm->fetchColumn() ?: ('#'.$ordem);
        if ($doCatalogo) {
            if (empty($perms['perm_admin'])) { throw new Exception('Remover do catálogo (todas as obras) é só administrador.'); }
            $pdo->prepare("DELETE FROM radar_item WHERE servico_id=?")->execute([$ordem]);   // sai de TODAS as obras
            $pdo->prepare("DELETE FROM servico WHERE id=?")->execute([$ordem]);
            log_historico($pdo, $OBRA, $ordem, $nome, $me, $perms['nome'], 'Item excluído do catálogo (todas as obras)', $nome, '');
            $pdo->commit();
            echo json_encode(['ok'=>true, 'escopo'=>'catalogo']); exit;
        }
        // per-obra (padrão seguro)
        $del = $pdo->prepare("DELETE FROM radar_item WHERE servico_id=? AND obra_id=?"); $del->execute([$ordem, $OBRA]);
        $restam = $pdo->prepare("SELECT COUNT(*) FROM radar_item WHERE servico_id=?"); $restam->execute([$ordem]);
        $restam = (int)$restam->fetchColumn();
        log_historico($pdo, $OBRA, $ordem, $nome, $me, $perms['nome'], 'Item removido da obra', $nome, '');
        $pdo->commit();
        echo json_encode(['ok'=>true, 'escopo'=>'obra', 'restam_obras'=>$restam]); exit;
    }

    if ($acao === 'restaurar') {   // RECUPERAÇÃO de item excluído por engano: recria com o MESMO id (reconecta receita órfã)
        if (empty($perms['perm_admin'])) { throw new Exception('Restaurar item é só administrador.'); }
        $sv = $in['servico'] ?? [];
        $id = (int)($sv['id'] ?? 0); if ($id <= 0) throw new Exception('servico.id obrigatório');
        $nome = trim((string)($sv['nome'] ?? '')); if ($nome === '') throw new Exception('servico.nome obrigatório');
        $grupo = trim((string)($sv['grupo'] ?? 'Outros')) ?: 'Outros';
        $curva = trim((string)($sv['curva'] ?? 'A')) ?: 'A';
        $rpad  = trim((string)($sv['responsavel_padrao'] ?? ''));
        $ex = $pdo->prepare("SELECT COUNT(*) FROM servico WHERE id=?"); $ex->execute([$id]);
        if (!(int)$ex->fetchColumn()) {
            $go = $pdo->prepare("SELECT grupo_ordem FROM servico WHERE grupo=? LIMIT 1"); $go->execute([$grupo]);
            $grupo_ordem = $go->fetchColumn();
            if ($grupo_ordem === false || $grupo_ordem === null)
                $grupo_ordem = (int)$pdo->query("SELECT COALESCE(MAX(grupo_ordem),0)+1 FROM servico")->fetchColumn();
            $pdo->prepare("INSERT INTO servico
                (id,ordem,nome,slug,fase,grupo,grupo_ordem,curva,forma_contratacao,unidade,quantitativo,lead_dias,
                 marco_cronograma,termos_orcamento,termos_cronograma,responsavel_padrao,escopo,variaveis_cotar,licoes,documentos,verba_linhas)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$id,$id,$nome,_slugify($nome),'',$grupo,$grupo_ordem,$curva,'','','','',
                    '', (string)($sv['termos_orcamento'] ?? ''), (string)($sv['termos_cronograma'] ?? ''),
                    $rpad, '','','','','']);
        }
        // recria a célula do radar nas obras pedidas (default: TODAS) — pula onde já existe
        $obras = $in['obras'] ?? null;
        if (!is_array($obras) || !$obras) { $obras = array_map(fn($o)=>(int)$o['id'], $pdo->query("SELECT id FROM obra ORDER BY id")->fetchAll()); }
        $insR = $pdo->prepare("INSERT INTO radar_item (obra_id,servico_id,status,responsavel,updated_at) VALUES (?,?,?,?,?)");
        $chkR = $pdo->prepare("SELECT COUNT(*) FROM radar_item WHERE obra_id=? AND servico_id=?");
        foreach ($obras as $oid) { $oid=(int)$oid; $chkR->execute([$oid,$id]);
            if (!(int)$chkR->fetchColumn()) $insR->execute([$oid,$id,'Não Iniciado',$rpad!==''?$rpad:null,date('c')]); }
        // curadoria conhecida (do histórico) numa obra: crono exato (marco + data) + responsável — entra como CURADO
        $cur = $in['curadoria'] ?? null;
        if ($cur && !empty($cur['obra_id'])) {
            $co=(int)$cur['obra_id']; $sets=[]; $vals=[];
            if (!empty($cur['crono_marco']))     { $sets[]='crono_marco_override=?';    $vals[]=(string)$cur['crono_marco']; }
            if (!empty($cur['data_necessaria'])) { $sets[]='data_necessaria_override=?'; $vals[]=(string)$cur['data_necessaria']; }
            if (!empty($cur['responsavel']))     { $sets[]='responsavel=?';              $vals[]=(string)$cur['responsavel']; }
            if ($sets) { $sets[]='updated_at=?'; $vals[]=date('c'); $vals[]=$co; $vals[]=$id;
                $pdo->prepare("UPDATE radar_item SET ".implode(',',$sets)." WHERE obra_id=? AND servico_id=?")->execute($vals); }
        }
        $rc = $pdo->prepare("SELECT COUNT(*) FROM receita WHERE servico_id=?"); $rc->execute([$id]); $reconn=(int)$rc->fetchColumn();
        log_historico($pdo, $OBRA, $id, $nome, $me, $perms['nome'], 'Item restaurado', '', $nome);
        $pdo->commit();
        echo json_encode(['ok'=>true, 'servico_id'=>$id, 'obras'=>count($obras), 'receita_reconectada'=>$reconn]); exit;
    }

    if ($acao === 'desdobrar') {
        global $SUFIXO;
        $ordem = (int)($in['ordem'] ?? 0);
        $s = $pdo->prepare("SELECT * FROM servico WHERE id=?"); $s->execute([$ordem]);
        $src = $s->fetch();
        if (!$src) throw new Exception('item de origem não encontrado');
        $base = base_nome($src['nome']);
        $o1 = criar_item($pdo, "$base (MAT)", $src['grupo'], 'Material', $src['curva'], $ordem);
        $o2 = criar_item($pdo, "$base (MO)",  $src['grupo'], 'Mão de obra', $src['curva'], $ordem);
        log_historico($pdo, 1, $ordem, $src['nome'], $me, $perms['nome'], 'Desdobrado em Material + MO', $src['nome'], "$base (MAT) + $base (MO)");
        $pdo->commit();
        echo json_encode(['ok'=>true, 'criados'=>[$o1,$o2]]); exit;
    }

    // novo
    $nome  = trim($in['nome'] ?? '');
    $grupo = trim($in['grupo'] ?? '');
    $tipo  = trim($in['tipo'] ?? '');
    $curva = trim($in['curva'] ?? '');
    if ($nome === '' || $grupo === '') throw new Exception('nome e grupo são obrigatórios');
    // anexa sufixo do tipo se ainda não tiver
    if ($tipo && isset($SUFIXO[$tipo]) && !preg_match('/\((MAT|MO|EMP|MAT \+ MO)\)\s*$/u', $nome)) {
        $nome .= ' ' . $SUFIXO[$tipo];
    }
    $ordem = criar_item($pdo, $nome, $grupo, $tipo, $curva, $in['copy_from'] ?? null);
    $resp = trim($in['responsavel'] ?? '');
    if ($resp !== '') {
        if (stripos($resp, 'camila') !== false) throw new Exception('responsável inválido'); // mesma regra do item_update
        $pdo->prepare("UPDATE radar_item SET responsavel=? WHERE obra_id=? AND servico_id=?")
            ->execute([$resp, $OBRA, $ordem]);
    }
    log_historico($pdo, $OBRA, $ordem, $nome, $me, $perms['nome'], 'Item criado', '', $nome.' · grupo '.$grupo);
    $pdo->commit();
    echo json_encode(['ok'=>true, 'ordem'=>$ordem]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
