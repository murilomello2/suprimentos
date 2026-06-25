<?php
/**
 * Retorna a matriz do Radar de Aquisições (JSON) para o front.
 * Junta a base (serviço + radar_item) com as datas vivas do cronograma (Supabase).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/cronograma.php';

try {
    db_seed_if_empty();
    $pdo = db();

    // Resiliência a DEPLOY PARCIAL: este endpoint lê colunas aditivas que são criadas no db.php.
    // Como o FTP às vezes sobe actions/ sem includes/db.php (timeout), garantimos as colunas aqui
    // mesmo se o db.php online estiver desatualizado — evita o HTTP 500 do radar.
    // Usa o PRAGMA table_info clássico (suportado em qualquer build do SQLite), não a forma table-valued.
    try {
        $rcols = [];
        foreach ($pdo->query("PRAGMA table_info(radar_item)") as $c) $rcols[$c['name']] = true;
        foreach (['composicao_sel'=>'TEXT','verba_curada'=>'INTEGER DEFAULT 0',
                  'quant_comp_sel'=>'TEXT','quant_curada'=>'INTEGER DEFAULT 0'] as $col=>$type) {
            if (!isset($rcols[$col])) { try { $pdo->exec("ALTER TABLE radar_item ADD COLUMN $col $type"); } catch (Throwable $e) {} }
        }
    } catch (Throwable $e) { /* nunca derruba o endpoint por causa da auto-cura */ }

    $obra = $pdo->query("SELECT * FROM obra WHERE id=1")->fetch();

    $rows = $pdo->query("
        SELECT s.ordem, s.nome, s.fase, s.grupo, s.grupo_ordem, s.curva, s.unidade, s.forma_contratacao,
               s.lead_dias, s.marco_cronograma, s.termos_cronograma, s.quantitativo AS quantitativo_txt,
               s.escopo, s.variaveis_cotar, s.licoes, s.documentos, s.verba_linhas,
               s.responsavel_padrao,
               r.status, r.responsavel, r.fornecedor, r.inicio_cotacao, r.fim_cotacao,
               r.verba_estim, r.observacoes, r.validado,
               r.verba_override, r.lead_override, r.crono_marco_override,
               r.data_necessaria_override, r.orcamento_refs,
               r.quantitativo_valor, r.quantitativo_unidade, r.quantitativo_refs, r.quantitativo_fonte,
               r.tipo, r.verba_metodo, r.verba_material, r.verba_mo, r.composicao_id, r.area_base, r.composicao_sel, r.verba_curada, r.quant_comp_sel, r.quant_curada
        FROM servico s
        JOIN radar_item r ON r.servico_id = s.id AND r.obra_id = 1
        ORDER BY s.grupo_ordem, s.ordem
    ")->fetchAll();

    // datas vivas do cronograma (com cache); se falhar, segue sem datas
    $tasks = [];
    $crono_erro = null;
    if (!empty($obra['cronograma_id'])) {
        try { $tasks = crono_tasks($obra['cronograma_id']); }
        catch (Exception $e) { $crono_erro = $e->getMessage(); }
    }

    $itens = [];
    $verba_total = 0;
    foreach ($rows as $r) {
        $auto = $tasks ? crono_resolver($r, $tasks)
                    : ['data_necessaria'=>null,'data_gatilho'=>null,'marco_casado'=>null,'confianca'=>'cronograma indisponível'];

        // overrides da curadoria têm prioridade sobre o automático
        $lead = ($r['lead_override'] !== null && $r['lead_override'] !== '')
                ? (int)$r['lead_override'] : 60;   // lead PADRÃO 60 dias (regra geral; override por item nas exceções)
        $data_nec = $r['data_necessaria_override'] ?: $auto['data_necessaria'];
        $marco    = $r['crono_marco_override'] ?: $auto['marco_casado'];
        // caminho (WBS) de onde a tarefa-âncora veio, p/ conferência: ex. Custos Indiretos › … › tarefa.
        // function_exists: resiliente a deploy parcial (cronograma.php é includes/, pode chegar depois no FTP).
        $marco_wbs = ($tasks && function_exists('crono_wbs_por_nome'))
            ? ($r['crono_marco_override'] ? crono_wbs_por_nome($marco, $tasks) : ($auto['marco_wbs'] ?? null)) : null;
        $marco_path = ($tasks && $marco_wbs && function_exists('crono_path_por_wbs')) ? crono_path_por_wbs($marco_wbs, $tasks) : [];
        $crono_pct = $r['crono_marco_override']
                   ? crono_percent_por_nome($r['crono_marco_override'], $tasks)
                   : ($auto['percent'] ?? null);
        // FIM da cotação = data em obra − lead (prazo de Suprimentos fechar; depois vem a fabricação).
        // INÍCIO da cotação = fim − 30 dias (fixo). Tudo recalcula da data viva do cronograma.
        $fim    = $data_nec ? date('Y-m-d', strtotime($data_nec . ' -' . (int)$lead . ' days')) : null;
        $inicio = $fim ? date('Y-m-d', strtotime($fim . ' -30 days')) : null;
        $verba    = ($r['verba_override'] !== null && $r['verba_override'] !== '')
                ? (float)$r['verba_override'] : (float)$r['verba_estim'];

        $d = [
            'data_necessaria' => $data_nec,
            'inicio_cotacao'  => $inicio,
            'fim_cotacao'     => $fim,
            'data_gatilho'    => $inicio,   // alias: o front usa data_gatilho como "início da cotação"
            'marco_casado'    => $marco,
            'marco_path'      => $marco_path,
            'cronograma_pct'  => $crono_pct,
            'confianca'       => $r['data_necessaria_override'] ? 'curado (manual)' : $auto['confianca'],
            'lead_efetivo'    => $lead,
            'verba'           => $verba,
            'curado_verba'    => (bool)((int)($r['verba_curada'] ?? 0)),
            'curado_data'     => (bool)$r['data_necessaria_override'],
            'orcamento_refs'  => $r['orcamento_refs'] ? json_decode($r['orcamento_refs'], true) : [],
            'quantitativo'         => $r['quantitativo_valor'] !== null ? (float)$r['quantitativo_valor'] : null,
            'quantitativo_unidade' => $r['quantitativo_unidade'],
            'quantitativo_fonte'   => $r['quantitativo_fonte'],
            'quantitativo_refs'    => $r['quantitativo_refs'] ? json_decode($r['quantitativo_refs'], true) : [],
            'quant_comp_sel'       => $r['quant_comp_sel'] ? json_decode($r['quant_comp_sel'], true) : [],
            'curado_quant'         => (bool)((int)($r['quant_curada'] ?? 0)) && $r['quantitativo_valor'] !== null,
            'verba_material'       => $r['verba_material'] !== null ? (float)$r['verba_material'] : null,
            'verba_mo'             => $r['verba_mo'] !== null ? (float)$r['verba_mo'] : null,
            'verba_metodo'         => $r['verba_metodo'],
            'composicao_sel'       => $r['composicao_sel'] ? json_decode($r['composicao_sel'], true) : [],
        ];
        $verba_total += $verba;
        $r['obra_nome'] = $obra['nome']; // p/ identificação e busca multi-obra futura
        $itens[] = array_merge($r, $d);
    }

    // ----- COBERTURA REAL do orçamento (sem double-count): linhas analíticas DISTINTAS + verba por composição -----
    $used = []; $comp_verba = 0;
    foreach ($rows as $r) {
        if (!empty($r['orcamento_refs'])) {
            foreach ((json_decode($r['orcamento_refs'], true) ?: []) as $id) $used[(int)$id] = true;
        }
        if (($r['verba_metodo'] ?? '') === 'composicao' && $r['verba_override'] !== null) {
            $comp_verba += (float)$r['verba_override'];
        }
    }
    $cov_analitico = 0;
    if ($used) {
        $valmap = [];
        foreach ($pdo->query("SELECT id, valor FROM orcamento_linha") as $l) $valmap[(int)$l['id']] = (float)$l['valor'];
        foreach (array_keys($used) as $id) $cov_analitico += ($valmap[$id] ?? 0);
    }
    $total_leaf = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM orcamento_linha WHERE folha=1")->fetchColumn();
    $cov_real = $cov_analitico + $comp_verba;
    $cobertura_real = $total_leaf ? round($cov_real / $total_leaf * 100, 1) : null;

    echo json_encode([
        'obra' => $obra,
        'itens' => $itens,
        'resumo' => [
            'total' => count($itens),
            'por_status' => array_count_values(array_map(fn($i)=>$i['status'] ?: 'Não Iniciado', $itens)),
            'verba_total' => $verba_total,
            'crono_erro' => $crono_erro,
            'cobertura_real'       => $cobertura_real,
            'cobertura_analitico'  => $total_leaf ? round($cov_analitico / $total_leaf * 100, 1) : null,
            'cobertura_composicao' => $total_leaf ? round($comp_verba / $total_leaf * 100, 1) : null,
            'cobertura_valor'      => round($cov_real, 2),
            'cobertura_total_leaf' => round($total_leaf, 2),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
