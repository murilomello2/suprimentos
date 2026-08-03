<?php
/**
 * CONSULTA DA OBRA — as três telas de leitura (Radar, Cotações, Solicitações) para engenheiros e
 * coordenadores de obra (papel 'obra'), e para qualquer usuário autorizado que queira a visão magra.
 *
 * POR QUE ESTE ARQUIVO EXISTE, se já há o api.php:
 *   - o api.php é a API EXTERNA: autentica por chave secreta e libera CORS para qualquer origem.
 *     Aceitar o `me` do cockpit lá dentro faria os dados de suprimentos ficarem legíveis de qualquer
 *     site por quem soubesse um bitrix_id — sem chave nenhuma. Não é o mesmo nível de exposição.
 *   - mas a CONTA é a mesma: alerta do radar, cobertura da SC, melhor preço por item. Reescrever aqui
 *     criaria duas réguas que divergem no primeiro ajuste. Então este endpoint inclui o api.php como
 *     BIBLIOTECA (API_LIB_ONLY) e reusa as funções — auth e CORS diferentes, contas idênticas.
 *
 * SOMENTE LEITURA: só GET. Escrita é barrada antes disso, no sup_veta_leitor_em_post() do db.php.
 *
 *   ?tela=radar          &obra_id=&status=&alerta=&responsavel=&grupo=&com_cotacao=&q=&pagina=&por_pagina=
 *   ?tela=cotacoes       &obra_id=&status=&origem=&criado_por=&q=&pagina=&por_pagina=
 *   ?tela=solicitacoes   &obra_id=&status=&comprador=&situacao=&q=&pagina=&por_pagina=
 *   ?tela=cotacao&id=N   detalhe de uma cotação (itens, fornecedores, mapa comparativo)
 *   ?tela=obras          lista de obras p/ o seletor
 */
if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) @ob_start('ob_gzhandler');
header('Content-Type: application/json; charset=utf-8');

define('API_LIB_ONLY', 1);
require_once __DIR__ . '/api.php';              // traz db.php/cronograma/solic e as funções de montagem

function oc_out($d) { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function oc_erro($msg, $http = 400) { http_response_code($http); oc_out(['ok' => false, 'error' => $msg]); }

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') oc_erro('Esta tela é somente leitura.', 405);

    $pdo   = db();
    $perms = user_perms($pdo, $_GET['me'] ?? null);
    if (empty($perms['autorizado'])) oc_erro('Sem acesso ao Cockpit.', 403);

    $tela = trim((string)($_GET['tela'] ?? ''));
    $q    = api_nz($_GET['q'] ?? '');
    $pag  = (int)($_GET['pagina'] ?? 1);
    $pp   = (int)($_GET['por_pagina'] ?? API_POR_PAGINA);

    /* Recorte por obra. O papel 'obra' vê TODAS (decisão de 31/jul), mas quem tiver ver_escopo='sel'
       — coordenador, por exemplo — continua limitado às obras dele. O recorte é do SERVIDOR: o filtro
       que vem do front é sugestão, não autorização. */
    $pedidas = api_filtro_obras($pdo, $_GET['obra_id'] ?? '', $_GET['obra'] ?? '');
    if (($perms['ver_escopo'] ?? '') === 'sel') {
        $todas = api_obras($pdo);
        $podeVer = [];
        foreach (array_map('intval', $perms['obras_ver'] ?? []) as $radarId)   // obras_ver guarda id do RADAR
            foreach ($todas as $fid => $o) if ((int)($o['_radar_id'] ?? 0) === $radarId) $podeVer[] = $fid;
        $pedidas = $pedidas === null ? $podeVer : array_values(array_intersect($pedidas, $podeVer));
        if (!$pedidas) oc_out(['ok' => true, 'tela' => $tela, 'total' => 0, 'pagina' => 1, 'paginas' => 1,
                               'por_pagina' => $pp, 'dados' => [], 'aviso' => 'Nenhuma obra liberada para você.']);
    }

    if ($tela === 'obras') {
        $todas = api_obras($pdo);
        if ($pedidas !== null) $todas = array_intersect_key($todas, array_flip($pedidas));
        $lista = array_values(array_map(fn($o) => array_diff_key($o, ['_radar_id' => 1, '_razao' => 1]), $todas));
        oc_out(['ok' => true, 'tela' => 'obras', 'total' => count($lista), 'dados' => $lista]);
    }

    if ($tela === 'radar') {
        $todas  = api_obras($pdo);
        $fichas = $pedidas ?? array_keys(array_filter($todas, fn($o) => $o['no_radar']));
        @set_time_limit(0);
        $t0 = microtime(true); $linhas = []; $faltando = [];
        foreach ($fichas as $fid) {
            // o radar resolve cronograma item a item; na 1ª varredura (cache frio) isso estoura o tempo.
            // Devolve o que deu + `parcial`, e a tela chama de novo — a 2ª volta é rápida.
            if (microtime(true) - $t0 > API_ORCAMENTO_S) { $faltando[] = $todas[$fid]['obra'] ?? $fid; continue; }
            $linhas = array_merge($linhas, api_radar_obra($pdo, $fid, !empty($_GET['recarregar'])));
        }
        $fStatus = trim((string)($_GET['status'] ?? ''));
        $fResp   = api_nz($_GET['responsavel'] ?? '');
        $fAlerta = trim((string)($_GET['alerta'] ?? ''));
        $fGrupo  = api_nz($_GET['grupo'] ?? '');
        $fCot    = $_GET['com_cotacao'] ?? '';
        $linhas = array_values(array_filter($linhas, function ($l) use ($fStatus, $fResp, $fAlerta, $fGrupo, $fCot, $q) {
            if ($fStatus !== '' && $l['status'] !== $fStatus) return false;
            if ($fResp !== ''   && api_nz($l['responsavel']) !== $fResp) return false;
            if ($fAlerta !== '' && $l['alerta'] !== $fAlerta) return false;
            if ($fGrupo !== ''  && strpos(api_nz($l['grupo']), $fGrupo) === false) return false;
            if ($fCot === '1' && !$l['cotacao']) return false;
            if ($fCot === '0' && $l['cotacao'])  return false;
            if ($q !== '' && strpos(api_nz($l['item'] . ' ' . $l['grupo'] . ' ' . $l['fornecedor']), $q) === false) return false;
            return true;
        }));
        // listas para os seletores — montadas do resultado ANTES da paginação
        $grupos = []; $resps = [];
        foreach ($linhas as $l) {
            if (trim((string)$l['grupo']) !== '') $grupos[$l['grupo']] = true;
            if (trim((string)$l['responsavel']) !== '') $resps[$l['responsavel']] = true;
        }
        ksort($grupos); ksort($resps);
        $cont = ['total' => count($linhas), 'atrasados' => 0, 'agora' => 0, 'com_cotacao' => 0, 'sem_data' => 0];
        foreach ($linhas as $l) {
            if (in_array($l['alerta'], ['critico', 'atrasado'], true)) $cont['atrasados']++;
            if ($l['alerta'] === 'proximo') $cont['agora']++;
            if ($l['cotacao']) $cont['com_cotacao']++;
            if (!$l['data_em_obra']) $cont['sem_data']++;
        }
        [$pagina, $total, $p, $paginas, $ppp] = api_paginar($linhas, $pag, $pp);
        oc_out(['ok' => true, 'tela' => 'radar', 'total' => $total, 'pagina' => $p, 'paginas' => $paginas,
                'por_pagina' => $ppp, 'gerado_em' => date('c'), 'parcial' => !empty($faltando),
                'obras_nao_processadas' => $faltando, 'contadores' => $cont,
                'grupos' => array_keys($grupos), 'responsaveis' => array_keys($resps), 'dados' => $pagina]);
    }

    if ($tela === 'cotacoes') {
        $lista = api_cotacoes_lista($pdo);
        $fStatus = trim((string)($_GET['status'] ?? ''));
        $fOrigem = trim((string)($_GET['origem'] ?? ''));
        $fCriado = api_nz($_GET['criado_por'] ?? '');
        $lista = array_values(array_filter($lista, function ($c) use ($pedidas, $fStatus, $fOrigem, $fCriado, $q) {
            if ($pedidas !== null && !in_array($c['obra_id'], $pedidas, true)) return false;
            if ($fStatus !== '' && $c['status'] !== $fStatus) return false;
            if ($fOrigem !== '' && $c['origem'] !== $fOrigem) return false;
            if ($fCriado !== '' && api_nz($c['criado_por']) !== $fCriado) return false;
            if ($q !== '' && strpos(api_nz($c['titulo'] . ' ' . $c['apelido'] . ' ' . $c['item_radar'] . ' '
                                         . $c['categoria'] . ' ' . $c['num_solicitacao'] . ' ' . $c['num_pedido']), $q) === false) return false;
            return true;
        }));
        $criadores = []; $cats = [];
        foreach ($lista as $c) {
            if (trim((string)$c['criado_por']) !== '') $criadores[$c['criado_por']] = true;
            if (trim((string)$c['categoria']) !== '')  $cats[$c['categoria']] = true;
        }
        ksort($criadores); ksort($cats);
        $cont = ['total' => count($lista), 'em_cotacao' => 0, 'aguardando' => 0, 'finalizadas' => 0, 'sem_proposta' => 0];
        foreach ($lista as $c) {
            if ($c['status'] === 'aberta') $cont['em_cotacao']++;
            elseif ($c['status'] === 'aguardando') $cont['aguardando']++;
            elseif ($c['status'] === 'finalizada') $cont['finalizadas']++;
            if ((int)$c['propostas_recebidas'] === 0) $cont['sem_proposta']++;
        }
        [$pagina, $total, $p, $paginas, $ppp] = api_paginar($lista, $pag, $pp);
        oc_out(['ok' => true, 'tela' => 'cotacoes', 'total' => $total, 'pagina' => $p, 'paginas' => $paginas,
                'por_pagina' => $ppp, 'gerado_em' => date('c'), 'contadores' => $cont,
                'criadores' => array_keys($criadores), 'categorias' => array_keys($cats), 'dados' => $pagina]);
    }

    if ($tela === 'cotacao') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) oc_erro('Informe ?id=<número da cotação>.');
        $d = api_cotacao_detalhe($pdo, $id);
        if ($pedidas !== null && $d['obra_id'] !== null && !in_array($d['obra_id'], $pedidas, true))
            oc_erro('Esta cotação é de uma obra fora do seu acesso.', 403);
        oc_out(['ok' => true, 'tela' => 'cotacao', 'gerado_em' => date('c'), 'dados' => $d]);
    }

    if ($tela === 'solicitacoes') {
        $lista = api_solicitacoes($pdo);
        $nomeObra = api_nz($_GET['obra'] ?? '');
        $fStatus  = trim((string)($_GET['status'] ?? ''));
        $fComp    = api_nz($_GET['comprador'] ?? '');
        $fSit     = trim((string)($_GET['situacao'] ?? ''));
        $lista = array_values(array_filter($lista, function ($s) use ($pedidas, $nomeObra, $fStatus, $fComp, $fSit, $q) {
            // SC sem obra mapeada tem obra_id null — não pode sumir por causa disso; o nome ainda casa
            if ($pedidas !== null) {
                $bate = ($s['obra_id'] !== null && in_array($s['obra_id'], $pedidas, true))
                     || ($nomeObra !== '' && strpos(api_nz($s['obra']), $nomeObra) !== false);
                if (!$bate) return false;
            }
            if ($fStatus !== '' && $s['status'] !== $fStatus) return false;
            if ($fComp !== ''   && api_nz($s['comprador']) !== $fComp) return false;
            if ($fSit !== ''    && $s['cotacao_situacao'] !== $fSit) return false;
            if ($q !== '') {
                $alvo = api_nz($s['numero'] . ' ' . $s['obra'] . ' '
                             . implode(' ', array_map(fn($i) => $i['produto'], $s['itens'])));
                if (strpos($alvo, $q) === false) return false;
            }
            return true;
        }));
        $comps = [];
        foreach ($lista as $s) if (trim((string)$s['comprador']) !== '') $comps[$s['comprador']] = true;
        ksort($comps);
        $cont = ['total' => count($lista), 'sem_cotacao' => 0, 'paradas15' => 0, 'paradas30' => 0, 'media_dias' => 0];
        $soma = 0; $n = 0;
        foreach ($lista as $s) {
            if ($s['cotacao_situacao'] === 'vazio') $cont['sem_cotacao']++;
            $d = $s['dias_em_aberto'];
            if ($d !== null) { $soma += $d; $n++; if ($d > 15) $cont['paradas15']++; if ($d > 30) $cont['paradas30']++; }
        }
        $cont['media_dias'] = $n ? round($soma / $n) : 0;
        [$pagina, $total, $p, $paginas, $ppp] = api_paginar($lista, $pag, $pp);
        oc_out(['ok' => true, 'tela' => 'solicitacoes', 'total' => $total, 'pagina' => $p, 'paginas' => $paginas,
                'por_pagina' => $ppp, 'gerado_em' => date('c'), 'contadores' => $cont,
                'compradores' => array_keys($comps), 'dados' => $pagina]);
    }

    oc_erro('Tela desconhecida: "' . $tela . '".', 404);
} catch (Throwable $e) {
    oc_erro($e->getMessage(), 500);
}
