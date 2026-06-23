<?php
require_once __DIR__ . '/supabase.php';

/**
 * Motor de datas: liga cada serviço do radar ao cronograma vivo (Supabase).
 *
 * Estratégia v1 (curável): busca as tarefas de resumo (outline_level <= 3) do
 * cronograma da obra UMA vez (cache em arquivo), e para cada serviço casa os
 * 'termos match cronograma' curados no De-Para contra o nome das tarefas.
 * A menor data de início entre as tarefas casadas vira a "data necessária".
 * data_gatilho = data_necessaria - lead_dias.
 */

define('CRONO_CACHE_TTL', 1800); // 30 min

function crono_tasks($cronograma_id) {
    $cache = SEED_DIR . '/../.crono_' . substr($cronograma_id, 0, 8) . '.json';
    if (is_file($cache) && (time() - filemtime($cache)) < CRONO_CACHE_TTL) {
        $d = json_decode(@file_get_contents($cache), true);
        if (is_array($d)) return $d;
    }
    // tarefas de resumo: poucas centenas, o suficiente para casar fases/marcos
    $path = 'obra_cronograma_tarefas?cronograma_id=eq.' . rawurlencode($cronograma_id)
          . '&outline_level=lte.3&select=nome,wbs,start,finish,is_milestone,outline_level&order=ordem&limit=600';
    $rows = sb_get($path);
    @file_put_contents($cache, json_encode($rows));
    return $rows;
}

function _norm_txt($s) {
    // sem dependência de mbstring (servidor não tem a extensão):
    // mapeia acentos (maiúsc. e minúsc.) para ascii e depois minúscula A-Z.
    $map = ['Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e','Í'=>'i','Ó'=>'o','Ô'=>'o','Õ'=>'o','Ú'=>'u','Ç'=>'c',
            'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c'];
    return strtolower(strtr((string)$s, $map));
}

/** Resolve datas de um serviço. Retorna [data_necessaria, data_gatilho, marco_casado, confianca]. */
function crono_resolver($servico, $tasks) {
    $termos = [];
    foreach (preg_split('/[;,\/]/', (string)$servico['termos_cronograma']) as $t) {
        $t = trim(_norm_txt($t));
        if (strlen($t) >= 4) $termos[] = $t;
    }
    if (!$termos && $servico['nome']) $termos[] = _norm_txt($servico['nome']);

    $melhor = null; $marco = null;
    foreach ($tasks as $tk) {
        $nome = _norm_txt($tk['nome']);
        foreach ($termos as $t) {
            if (strpos($nome, $t) !== false) {
                $st = $tk['start'] ?? null;
                if ($st && (!$melhor || $st < $melhor)) { $melhor = $st; $marco = $tk['nome']; }
            }
        }
    }
    if (!$melhor) {
        return ['data_necessaria'=>null, 'data_gatilho'=>null, 'marco_casado'=>null, 'confianca'=>'sem match'];
    }
    $gatilho = null;
    if (!empty($servico['lead_dias'])) {
        $ts = strtotime($melhor . ' -' . (int)$servico['lead_dias'] . ' days');
        $gatilho = date('Y-m-d', $ts);
    }
    return [
        'data_necessaria' => $melhor,
        'data_gatilho'    => $gatilho,
        'marco_casado'    => $marco,
        'confianca'       => 'auto (cronograma)',
    ];
}
