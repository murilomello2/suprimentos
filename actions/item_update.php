<?php
/**
 * Atualiza/cura um item do radar.
 * POST JSON: { "ordem": <int>, "campos": { ... } }
 *
 * Campos da curadoria:
 *   status, fornecedor, responsavel, observacoes, validado          (radar)
 *   lead_override (int|null)                                        (lead time)
 *   crono_marco_override (str|null), data_necessaria_override (str) (match cronograma)
 *   orcamento_refs ([ids])  -> calcula verba_override = soma das linhas e grava refs
 *   verba_override (num|null) -> verba manual direta (sem composição)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

$SIMPLES = ['status','fornecedor','responsavel','observacoes','validado','tipo',
            'lead_override','crono_marco_override','data_necessaria_override','verba_override',
            'quantitativo_valor','quantitativo_unidade','quantitativo_fonte'];
$TIPOS_OK = ['', 'Material', 'Mão de obra', 'Empreitada', 'Material + MO', 'Locação'];
$STATUS_OK = ['Não Iniciado','Cotação Iniciada','Com Pendências','Em Andamento','Finalizado'];

function nullable($v){ return ($v === '' || $v === null) ? null : $v; }

try {
    $in = json_decode(file_get_contents('php://input'), true);
    $ordem = isset($in['ordem']) ? (int)$in['ordem'] : 0;
    $campos = $in['campos'] ?? [];
    if (!$ordem || !is_array($campos) || !$campos) throw new Exception('payload inválido (precisa de ordem + campos)');

    $pdo = db();
    $set = []; $vals = [];

    // composição de verba: recebe lista de ids de orcamento_linha -> soma + grava refs
    if (array_key_exists('orcamento_refs', $campos)) {
        $refs = array_values(array_filter(array_map('intval', (array)$campos['orcamento_refs'])));
        $soma = 0;
        if ($refs) {
            $inq = implode(',', array_fill(0, count($refs), '?'));
            $st = $pdo->prepare("SELECT COALESCE(SUM(valor),0) s FROM orcamento_linha WHERE id IN ($inq)");
            $st->execute($refs);
            $soma = (float)$st->fetch()['s'];
        }
        $set[] = "orcamento_refs = ?"; $vals[] = $refs ? json_encode($refs) : null;
        $set[] = "verba_override = ?"; $vals[] = $refs ? $soma : null;
        $set[] = "verba_metodo = ?";   $vals[] = $refs ? 'analitico' : null;
        // quantitativo automático: soma das quantidades das mesmas linhas (unidade dominante)
        if ($refs) {
            $sq = $pdo->prepare("SELECT COALESCE(SUM(qtde),0) s FROM orcamento_linha WHERE id IN ($inq)");
            $sq->execute($refs);
            $uq = $pdo->prepare("SELECT unidade, COUNT(*) c FROM orcamento_linha WHERE id IN ($inq) GROUP BY unidade ORDER BY c DESC LIMIT 1");
            $uq->execute($refs);
            $set[] = "quantitativo_valor = ?";   $vals[] = (float)$sq->fetch()['s'];
            $set[] = "quantitativo_unidade = ?"; $vals[] = ($uq->fetch()['unidade'] ?? null) ?: null;
            $set[] = "quantitativo_fonte = ?";   $vals[] = 'orcamento';
        }
        unset($campos['orcamento_refs']);
    }

    // DICIONÁRIO editável (template por tipo de serviço) — atualiza a tabela servico
    if (array_key_exists('dicionario', $campos)) {
        $dic = (array)$campos['dicionario'];
        $allow = ['escopo','variaveis_cotar','licoes','documentos','marco_cronograma','quantitativo','forma_contratacao','unidade','responsavel_padrao'];
        $ds = []; $dv = [];
        foreach ($dic as $k => $v) { if (in_array($k, $allow, true)) { $ds[] = "$k = ?"; $dv[] = (string)$v; } }
        if ($ds) { $dv[] = $ordem; $pdo->prepare("UPDATE servico SET " . implode(', ', $ds) . " WHERE id = ?")->execute($dv); }
        unset($campos['dicionario']);
        if (!$campos) { // só dicionário foi enviado
            echo json_encode(['ok'=>true, 'dicionario'=>true], JSON_UNESCAPED_UNICODE); exit;
        }
    }

    // MOVER o item de GRUPO (admin) — atualiza o catálogo (servico), não a curadoria.
    // Grupo existente herda a ordem do destino; grupo novo vai pro fim.
    if (array_key_exists('grupo', $campos)) {
        $g = trim((string)$campos['grupo']);
        if ($g !== '') {
            $go = $pdo->prepare("SELECT grupo_ordem FROM servico WHERE grupo=? AND id<>? LIMIT 1");
            $go->execute([$g, $ordem]);
            $gord = $go->fetchColumn();
            if ($gord === false || $gord === null) {
                $gord = (int)$pdo->query("SELECT COALESCE(MAX(grupo_ordem),0)+1 FROM servico")->fetchColumn();
            }
            $pdo->prepare("UPDATE servico SET grupo=?, grupo_ordem=? WHERE id=?")->execute([$g, $gord, $ordem]);
        }
        unset($campos['grupo']);
        if (!$campos) { echo json_encode(['ok'=>true, 'grupo'=>$g], JSON_UNESCAPED_UNICODE); exit; }
    }

    // composição de QUANTITATIVO: linhas do orçamento -> soma das quantidades + unidade dominante
    if (array_key_exists('quant_refs', $campos)) {
        $refs = array_values(array_filter(array_map('intval', (array)$campos['quant_refs'])));
        $soma = 0; $unid = null;
        if ($refs) {
            $inq = implode(',', array_fill(0, count($refs), '?'));
            $st = $pdo->prepare("SELECT COALESCE(SUM(qtde),0) s FROM orcamento_linha WHERE id IN ($inq)");
            $st->execute($refs);
            $soma = (float)$st->fetch()['s'];
            // unidade dominante entre as linhas escolhidas
            $u = $pdo->prepare("SELECT unidade, COUNT(*) c FROM orcamento_linha WHERE id IN ($inq) GROUP BY unidade ORDER BY c DESC LIMIT 1");
            $u->execute($refs);
            $unid = ($u->fetch()['unidade'] ?? null) ?: null;
        }
        $set[] = "quantitativo_refs = ?";    $vals[] = $refs ? json_encode($refs) : null;
        $set[] = "quantitativo_valor = ?";   $vals[] = $refs ? $soma : null;
        $set[] = "quantitativo_unidade = ?"; $vals[] = $refs ? $unid : null;
        $set[] = "quantitativo_fonte = ?";   $vals[] = $refs ? 'orcamento' : null;
        unset($campos['quant_refs']);
    }

    // VERBA/QUANTITATIVO POR COMPOSIÇÃO: area × coeficiente, separando material e MO
    if (array_key_exists('composicao', $campos)) {
        $cfg  = $campos['composicao'];
        $cid  = (int)($cfg['id'] ?? 0);
        $area = (float)($cfg['area'] ?? 0);
        $qidx = isset($cfg['quant_idx']) ? (int)$cfg['quant_idx'] : -1;
        $papel = $cfg['papel'] ?? 'ambos';  // material | mo | ambos
        if ($cid && $area > 0) {
            $ins = $pdo->prepare("SELECT descricao,unidade,coef,rs_unit,tipo FROM composicao_insumo WHERE composicao_id=? ORDER BY id");
            $ins->execute([$cid]);
            $rowsI = $ins->fetchAll();
            $vmat = 0; $vmo = 0;
            foreach ($rowsI as $in) {
                $custo = $area * (float)$in['coef'] * (float)$in['rs_unit'];
                if ($in['tipo'] === 'mo') $vmo += $custo; else $vmat += $custo;
            }
            $compRow = $pdo->prepare("SELECT unidade FROM composicao WHERE id=?"); $compRow->execute([$cid]);
            $compUn = $compRow->fetchColumn() ?: '';
            // verba conforme o papel deste item (parcela material, parcela MO, ou ambas)
            $verba = $papel==='material' ? $vmat : ($papel==='mo' ? $vmo : $vmat + $vmo);
            $set[]="verba_metodo = ?";   $vals[]='composicao';
            $set[]="composicao_id = ?";  $vals[]=$cid;
            $set[]="area_base = ?";      $vals[]=$area;
            $set[]="verba_material = ?"; $vals[]=$papel==='mo' ? null : $vmat;
            $set[]="verba_mo = ?";       $vals[]=$papel==='material' ? null : $vmo;
            $set[]="verba_override = ?"; $vals[]=$verba;
            $set[]="orcamento_refs = ?"; $vals[]=null;
            // quantitativo: MO mede pela unidade da composição × área; material pelo insumo escolhido
            if ($papel === 'mo') {
                $set[]="quantitativo_valor = ?";   $vals[]=$area;
                $set[]="quantitativo_unidade = ?"; $vals[]=$compUn;
                $set[]="quantitativo_fonte = ?";   $vals[]='composicao';
                $set[]="quantitativo_refs = ?";    $vals[]=null;
            } else {
                $qsel = ($qidx >= 0 && isset($rowsI[$qidx])) ? $rowsI[$qidx] : null;
                if (!$qsel) foreach ($rowsI as $in) { if ($in['tipo']==='material'){ $qsel=$in; break; } }
                if ($qsel) {
                    $set[]="quantitativo_valor = ?";   $vals[]=$area * (float)$qsel['coef'];
                    $set[]="quantitativo_unidade = ?"; $vals[]=$qsel['unidade'];
                    $set[]="quantitativo_fonte = ?";   $vals[]='composicao';
                    $set[]="quantitativo_refs = ?";    $vals[]=null;
                }
            }
        }
        unset($campos['composicao']);
    }

    foreach ($campos as $k => $v) {
        if (!in_array($k, $SIMPLES, true)) continue;
        if ($k === 'tipo' && !in_array($v, $TIPOS_OK, true)) throw new Exception('tipo inválido: ' . $v);
        if ($k === 'status' && $v !== '' && !in_array($v, $STATUS_OK, true)) throw new Exception('status inválido: ' . $v);
        if ($k === 'responsavel' && stripos((string)$v, 'camila') !== false) throw new Exception('responsável inválido');
        if ($k === 'validado')        { $set[]="$k = ?"; $vals[]=(int)(bool)$v; continue; }
        if ($k === 'lead_override')    { $set[]="$k = ?"; $vals[]= nullable($v)===null?null:(int)$v; continue; }
        if ($k === 'verba_override')   { $set[]="$k = ?"; $vals[]= nullable($v)===null?null:(float)$v; continue; }
        if ($k === 'quantitativo_valor'){ $set[]="$k = ?"; $vals[]= nullable($v)===null?null:(float)$v; continue; }
        $set[] = "$k = ?"; $vals[] = nullable($v);
    }
    if (!$set) throw new Exception('nenhum campo válido');

    $set[] = "updated_at = ?"; $vals[] = date('c');
    $vals[] = $ordem;
    $pdo->prepare("UPDATE radar_item SET " . implode(', ', $set) . " WHERE obra_id=1 AND servico_id=?")
        ->execute($vals);

    $row = $pdo->prepare("SELECT * FROM radar_item WHERE obra_id=1 AND servico_id=?");
    $row->execute([$ordem]);
    echo json_encode(['ok'=>true, 'item'=>$row->fetch()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
