<?php
/**
 * API PÚBLICA DE LEITURA — Cockpit de Suprimentos (Caprem).
 * Feita para o SISTEMA DE OBRAS de terceiros ler o andamento de compras. SOMENTE LEITURA:
 * nenhum recurso desta API grava, altera ou apaga qualquer coisa no cockpit.
 *
 * AUTENTICAÇÃO: header  X-API-Key: <chave>
 *   As chaves ficam em data/.api_keys.json (fora do git, 403 no servidor). Nunca em query string —
 *   query string vaza em log de proxy/servidor.
 *
 * RECURSOS (GET):
 *   ?recurso=obras                          lista de obras (para montar o filtro)
 *   ?recurso=radar                          itens do radar de compras
 *   ?recurso=solicitacoes                   solicitações de compra (SC) do TOTVS + andamento
 *   ?recurso=cotacoes                       cotações (nascidas do radar, de SC, ou criadas do zero)
 *   ?recurso=cotacao&id=N                   uma cotação em detalhe (itens, fornecedores, mapa comparativo)
 *   (sem recurso)                           índice com a documentação resumida
 *
 * ADMIN (POST, exige perm_admin do cockpit — usado só pelo Murilo dentro do portal):
 *   {acao:'chave_criar',  me:'20', nome:'Sistema de Obras'}   cria e MOSTRA UMA VEZ a chave
 *   {acao:'chave_listar', me:'20'}                            lista as chaves (sem revelar o segredo)
 *   {acao:'chave_revogar',me:'20', id:'<id da chave>'}        revoga
 *
 * DECISÕES QUE O CONSUMIDOR PRECISA CONHECER (estão documentadas em API-SUPRIMENTOS.md):
 *  - obra_id desta API é SEMPRE o id do cadastro mestre (obra_ficha). O id interno do radar é outro
 *    espaço de numeração e COLIDE com ele (ficha 9 = Itaara × radar 9 = Vitrius); ele não é exposto.
 *  - As datas de cotação NÃO são colunas: início/fim são calculados a partir da data em obra
 *    (cronograma vivo do Planejamento) menos o lead time. Ver api_radar_linha().
 *  - "verba" tem duas naturezas: verba_definida (curada, é a que a tela do radar mostra) e
 *    verba_estimada (herdada do orçamento inicial). Nunca somamos as duas.
 */
/* Quem inclui como BIBLIOTECA (define API_LIB_ONLY antes do require) só quer as funções que montam
   radar/cotações/solicitações — as telas de consulta da obra reusam a MESMA régua de alerta,
   cobertura de SC e melhor-por-item, para não existirem duas contas que divergem com o tempo.
   Nesse modo não mandamos header nenhum (o chamador manda os dele) nem liberamos CORS. */
if (!defined('API_LIB_ONLY')) {
    if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) @ob_start('ob_gzhandler');
    header('Content-Type: application/json; charset=utf-8');
    // A API é chamada por outro sistema (e possivelmente do navegador dele). A autenticação é por chave,
    // não por cookie, então liberar a origem não expõe sessão de ninguém.
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: X-API-Key, Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/cronograma.php';
require_once __DIR__ . '/../includes/solic.php';


/* Rede de segurança contra DEPLOY PARCIAL: se o FTP entregar esta action antes do includes/solic.php,
   a tela cairia com "undefined function". As definições canônicas vivem em includes/solic.php; estas
   só entram em cena enquanto o include não chega, e somem sozinhas quando ele chega. */
if (!function_exists('solic_cobertura')) {
    function solic_cobertura($pdo) {
        $cov = [];
        $add = function ($col, $num, $seq, $codprd, $prod, $status, $cid, $ctit) use (&$cov) {
            $col = trim((string)$col); $num = trim((string)$num);
            if ($col === '' || $num === '') return;
            $seq = trim((string)$seq); $codprd = trim((string)$codprd);
            $k = $col . '|' . $num;
            $mk = $seq !== '' ? ('s:' . $seq) : ($codprd !== '' ? ('c:' . $codprd) : ('p:' . sol_norm($prod)));
            if (!isset($cov[$k])) $cov[$k] = [];
            $cur = $cov[$k][$mk] ?? null;
            if ($cur === null || ($cur['status'] !== 'coberto' && $status === 'coberto'))
                $cov[$k][$mk] = ['status' => $status, 'cid' => (int)$cid, 'ctit' => $ctit];
        };
        try {
            $cotPed = [];
            foreach ($pdo->query("SELECT cotacao_id, coligada, num_pedido FROM cotacao_pedido WHERE num_pedido IS NOT NULL AND num_pedido<>''") as $r)
                $cotPed[(int)$r['cotacao_id']][trim((string)$r['coligada'])] = trim((string)$r['num_pedido']);
            $cotNCol = [];
            foreach ($pdo->query("SELECT cotacao_id, COUNT(DISTINCT solic_coligada) n FROM cotacao_item WHERE solic_coligada IS NOT NULL AND solic_coligada<>'' GROUP BY cotacao_id") as $r)
                $cotNCol[(int)$r['cotacao_id']] = (int)$r['n'];
            foreach ($pdo->query("SELECT ci.cotacao_id cid, ci.solic_coligada col, ci.solic_numero num, ci.solic_seq seq, ci.solic_codprd codprd, ci.descricao prod, c.status st, c.num_pedido hdr, c.titulo ctit
                                  FROM cotacao_item ci JOIN cotacao c ON c.id=ci.cotacao_id
                                  WHERE ci.solic_coligada IS NOT NULL AND ci.solic_coligada<>'' AND ci.solic_numero IS NOT NULL AND ci.solic_numero<>''") as $r) {
                $cid = (int)$r['cid']; $colPc = $cotPed[$cid][trim((string)$r['col'])] ?? '';
                $isMulti = ($cotNCol[$cid] ?? 1) > 1;
                $effPc = $colPc !== '' ? $colPc : ($isMulti ? '' : trim((string)$r['hdr']));
                $status = (($r['st'] === 'finalizada') || $effPc !== '') ? 'coberto' : 'cotando';
                $add($r['col'], $r['num'], $r['seq'], $r['codprd'], $r['prod'], $status, $cid, $r['ctit']);
            }
            foreach ($pdo->query("SELECT c.id cid, o.coligada col, o.numero num, ci.descricao prod, c.status st, c.num_pedido pc, c.titulo ctit
                                  FROM solic_overlay o JOIN cotacao c ON c.id=o.cotacao_id JOIN cotacao_item ci ON ci.cotacao_id=c.id
                                  WHERE o.cotacao_id IS NOT NULL AND (ci.solic_coligada IS NULL OR ci.solic_coligada='')") as $r) {
                $status = (($r['st'] === 'finalizada') || trim((string)$r['pc']) !== '') ? 'coberto' : 'cotando';
                $add($r['col'], $r['num'], '', '', $r['prod'], $status, (int)$r['cid'], $r['ctit']);
            }
        } catch (Throwable $e) { return []; }
        return $cov;
    }
    function solic_item_cobertura(array &$itens, array $cmap) {
        $nameCount = [];
        foreach ($itens as $c) { $nn = sol_norm($c['produto'] ?? ''); $nameCount[$nn] = ($nameCount[$nn] ?? 0) + 1; }
        $nCob = 0; $nAny = 0; $cots = [];
        foreach ($itens as &$it) {
            $sq = trim((string)($it['seq'] ?? '')); $cp = trim((string)($it['codprd'] ?? '')); $nn = sol_norm($it['produto'] ?? '');
            $m = ($sq !== '') ? ($cmap['s:' . $sq] ?? null) : null;
            if ($m === null && $cp !== '') $m = $cmap['c:' . $cp] ?? null;
            if ($m === null && ($nameCount[$nn] ?? 0) <= 1) $m = $cmap['p:' . $nn] ?? null;
            if ($m) { $it['cot'] = $m['status']; $it['cot_cid'] = $m['cid']; $it['cot_ctit'] = $m['ctit'];
                      if (!empty($m['cid'])) $cots[$m['cid']] = $m['ctit']; }
            else { $it['cot'] = 'vazio'; }
            if ($it['cot'] === 'coberto') $nCob++;
            if ($it['cot'] !== 'vazio')   $nAny++;
        }
        unset($it);
        $nI = count($itens);
        $lista = []; foreach ($cots as $cid => $tit) $lista[] = ['id' => $cid, 'titulo' => $tit];
        return ['cobertura' => ($nI > 0 && $nCob === $nI) ? 'total' : ($nAny > 0 ? 'parcial' : 'vazio'),
                'n_cobertos' => $nCob, 'n_tocados' => $nAny, 'cotacoes' => $lista];
    }
}

define('API_VERSAO',      '1.0');
define('API_CHAVES_FILE', __DIR__ . '/../data/.api_keys.json');
define('API_CACHE_DIR',   __DIR__ . '/../data');
define('API_RADAR_TTL',   1800);   // 30 min — mesmo fôlego do cache do cronograma
define('API_POR_PAGINA',  100);
define('API_MAX_PAGINA',  500);
define('API_ORCAMENTO_S', 45);     // teto de tempo p/ varrer todas as obras antes de responder parcial

function api_out($x, $code = 200) {
    http_response_code($code);
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    echo json_encode($x, $flags);
    exit;
}
function api_erro($msg, $code = 400, $extra = []) { api_out(array_merge(['ok' => false, 'erro' => $msg], $extra), $code); }

/* ─────────────────────────── chaves de acesso ─────────────────────────── */

function api_chaves_ler() {
    if (!is_file(API_CHAVES_FILE)) return [];
    $j = json_decode((string)@file_get_contents(API_CHAVES_FILE), true);
    return is_array($j['chaves'] ?? null) ? $j['chaves'] : [];
}
function api_chaves_gravar($chaves) {
    @file_put_contents(API_CHAVES_FILE, json_encode(['chaves' => array_values($chaves)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    @chmod(API_CHAVES_FILE, 0600);
}
/** Confere o header X-API-Key. Devolve a chave usada; nega com 401 se não bater. */
function api_auth() {
    $hdr = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($hdr === '' && function_exists('getallheaders')) {          // alguns Apache/CGI não populam HTTP_*
        foreach ((array)getallheaders() as $k => $v) if (strcasecmp($k, 'X-API-Key') === 0) { $hdr = $v; break; }
    }
    $hdr = trim((string)$hdr);
    $chaves = api_chaves_ler();
    if (!$chaves) api_erro('A API ainda não foi liberada — nenhuma chave de acesso foi criada.', 503);
    if ($hdr === '') api_erro('Falta o header X-API-Key.', 401);
    foreach ($chaves as $i => $c) {
        if (empty($c['revogada']) && hash_equals((string)($c['chave'] ?? ''), $hdr)) {
            $hoje = date('Y-m-d');
            if (($c['ultimo_uso'] ?? '') !== $hoje) {               // 1 gravação por dia, não a cada request
                $chaves[$i]['ultimo_uso'] = $hoje;
                $chaves[$i]['usos'] = (int)($c['usos'] ?? 0) + 1;
                api_chaves_gravar($chaves);
            }
            return $chaves[$i];
        }
    }
    api_erro('Chave de acesso inválida ou revogada.', 401);
}

/* ─────────────────────────── utilitários ─────────────────────────── */

/** Sem acento, minúsculo, espaços colapsados — para comparar nome de obra/responsável digitado pelo cliente. */
function api_nz($s) {
    $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$s);
    return trim(preg_replace('/\s+/', ' ', strtolower(preg_replace('/[^A-Za-z0-9 ]/', ' ', $s))));
}
function api_num($x) { return ($x === null || $x === '') ? null : (float)$x; }
function api_paginar($lista, $pagina, $porPagina) {
    $total   = count($lista);
    $pp      = max(1, min(API_MAX_PAGINA, (int)($porPagina ?: API_POR_PAGINA)));
    $paginas = max(1, (int)ceil($total / $pp));
    $pag     = max(1, min($paginas, (int)($pagina ?: 1)));
    return [array_slice($lista, ($pag - 1) * $pp, $pp), $total, $pag, $paginas, $pp];
}

/**
 * SEMÁFORO DE PRAZO do item — a mesma regra que a tela do radar aplica em JavaScript (alertLevel).
 * Ela não existia no servidor; foi reescrita aqui para o sistema externo ver o MESMO alerta que o comprador vê.
 */
function api_alerta($status, $inicio, $fim, $hoje) {
    if ($status === 'Finalizado' || $status === 'Não se aplica') return 'finalizado';
    if ($fim && $fim < $hoje) return 'critico';
    if ($status === 'Não Iniciado') {
        if ($inicio && $inicio < $hoje) return 'atrasado';
        if ($inicio && ((strtotime($inicio) - strtotime($hoje)) / 86400) <= 7) return 'proximo';
    }
    return 'ok';
}
function api_alerta_label($a) {
    static $M = ['critico' => 'Prazo de cotação estourado', 'atrasado' => 'Atrasado para iniciar',
                 'proximo' => 'Começar a cotar agora', 'finalizado' => 'Concluído', 'ok' => 'No prazo'];
    return $M[$a] ?? $a;
}
function api_cot_status_label($s) {
    static $M = ['aberta' => 'Em cotação', 'aguardando' => 'Aguardando decisão', 'finalizada' => 'Finalizada'];
    return $M[strtolower(trim((string)$s))] ?? (string)$s;
}
function api_sc_status_label($s) {
    static $M = ['pendente' => 'Pendente', 'em_cotacao' => 'Em cotação', 'cotacoes_recebidas' => 'Cotações recebidas',
                 'pedido_criado' => 'Pedido criado', 'cancelado' => 'Cancelado'];
    return $M[$s] ?? $s;
}
/** Nome da obra da SC quando não existe de-para — mesma regra da tela (actions/solicitacoes.php). */
function api_obra_sc_default($coligada, $obraCod) {
    static $CC = ['001'=>'Comercial Americana','010'=>'Sede','015'=>'MKT','020'=>'SAT','032'=>'Licel',
                  '033'=>'Obras SAT','036'=>'Piamonte','039'=>'Contrap. Piamonte','040'=>'Cajá','041'=>'Espazo','042'=>'Prades'];
    if (stripos((string)$coligada, 'CAPRETZ') !== false && isset($CC[$obraCod])) return $CC[$obraCod];
    $n = preg_replace('/\s+(EMPREENDIMENTO|EMPREENDIMENTOS).*/i', '', (string)$coligada);
    return trim($n) ?: (string)$coligada;
}
function api_cobertura_label($c) {
    static $M = ['vazio' => 'Sem cotação', 'parcial' => 'Parcialmente cotada', 'total' => 'Totalmente cotada'];
    return $M[$c] ?? $c;
}

/* ─────────────────────────── obras ─────────────────────────── */

/**
 * Mapa das obras. Chave = obra_ficha.id (o id que ESTA API expõe).
 * Traz junto radar_obra_id só para uso interno — ele nunca sai no JSON, porque colide com o ficha_id.
 */
function api_obras($pdo) {
    static $cache = null;
    if ($cache !== null) return $cache;
    $out = [];
    foreach ($pdo->query("SELECT id, nome, cidade, estado, status, radar_obra_id, coligada_nome, coligada_cod
                          FROM obra_ficha ORDER BY nome") as $o) {
        $out[(int)$o['id']] = [
            'obra_id'   => (int)$o['id'],
            'obra'      => $o['nome'],
            'cidade'    => $o['cidade'] ?: null,
            'estado'    => $o['estado'] ?: null,
            'situacao'  => $o['status'] ?: null,
            'no_radar'  => !empty($o['radar_obra_id']),
            '_radar_id' => $o['radar_obra_id'] !== null ? (int)$o['radar_obra_id'] : null,
            '_razao'    => $o['coligada_nome'] ?: '',
        ];
    }
    return $cache = $out;
}
/** Resolve o filtro ?obra_id= / ?obra= para uma lista de ficha_ids. Devolve null = todas. */
function api_filtro_obras($pdo, $obraId, $obraNome) {
    $obras = api_obras($pdo);
    if ($obraId) {
        $ids = array_filter(array_map('intval', preg_split('/\s*,\s*/', (string)$obraId)));
        $ok  = array_values(array_intersect($ids, array_keys($obras)));
        if (!$ok) api_erro('obra_id não encontrado: ' . $obraId . '. Consulte ?recurso=obras.', 404);
        return $ok;
    }
    if (trim((string)$obraNome) !== '') {
        $alvo = api_nz($obraNome);
        $ok = [];
        foreach ($obras as $id => $o) if (api_nz($o['obra']) === $alvo) $ok[] = $id;
        if (!$ok) foreach ($obras as $id => $o) if (strpos(api_nz($o['obra']), $alvo) !== false) $ok[] = $id;
        if (!$ok) api_erro('Nenhuma obra chamada "' . $obraNome . '". Consulte ?recurso=obras.', 404);
        return $ok;
    }
    return null;
}

/* ─────────────────────────── radar ─────────────────────────── */

/** Índice das cotações por serviço, de UMA obra do radar. Conta só propostas VIGENTES (revisão ativa). */
function api_cot_por_servico($pdo, $radarObraId) {
    $out = [];
    try {
        $q = $pdo->prepare("SELECT c.id, c.servico_id, c.status, c.titulo, c.apelido,
                (SELECT COUNT(*) FROM cotacao_fornecedor cf WHERE cf.cotacao_id=c.id) AS convidados,
                (SELECT COUNT(*) FROM cotacao_fornecedor cf WHERE cf.cotacao_id=c.id AND cf.enviado_em IS NOT NULL AND cf.enviado_em<>'') AS disparados,
                (SELECT COUNT(*) FROM cotacao_proposta cp WHERE cp.cotacao_id=c.id AND (cp.ativa=1 OR cp.ativa IS NULL)) AS propostas,
                (SELECT MIN(cp.total) FROM cotacao_proposta cp WHERE cp.cotacao_id=c.id AND (cp.ativa=1 OR cp.ativa IS NULL) AND cp.total>0) AS melhor
             FROM cotacao c WHERE c.obra_id=? AND c.servico_id IS NOT NULL ORDER BY c.id DESC");
        $q->execute([$radarObraId]);
        foreach ($q as $r) {
            $sid = (int)$r['servico_id'];
            if (!isset($out[$sid])) $out[$sid] = ['n' => 0, 'ultima' => $r];
            $out[$sid]['n']++;
        }
    } catch (Throwable $e) { /* deploy parcial: segue sem cotação */ }
    return $out;
}

/** Monta as linhas do radar de UMA obra (ficha). Resultado é cacheado em disco por API_RADAR_TTL. */
function api_radar_obra($pdo, $fichaId, $recarregar = false) {
    $cache = API_CACHE_DIR . '/.api_radar_' . (int)$fichaId . '.json';
    if (!$recarregar && is_file($cache) && (time() - filemtime($cache)) < API_RADAR_TTL) {
        $j = json_decode((string)@file_get_contents($cache), true);
        if (is_array($j)) return $j;
    }
    $obras = api_obras($pdo);
    $o = $obras[$fichaId] ?? null;
    if (!$o || !$o['_radar_id']) return [];
    $rid = (int)$o['_radar_id'];

    $ob = $pdo->prepare("SELECT * FROM obra WHERE id=?"); $ob->execute([$rid]);
    $obraRadar = $ob->fetch();
    if (!$obraRadar) return [];

    $st = $pdo->prepare("
        SELECT s.id AS servico_id, s.ordem, s.curva, s.unidade, s.lead_dias, s.termos_cronograma, s.marco_cronograma,
               COALESCE(NULLIF(r.nome_override,''), s.nome)              AS nome,
               COALESCE(NULLIF(r.grupo_override,''), s.grupo)            AS grupo,
               COALESCE(r.grupo_ordem_override, s.grupo_ordem)           AS grupo_ordem,
               r.status, r.responsavel, r.fornecedor, r.observacoes, r.tipo,
               r.verba_override, r.verba_estim, r.verba_curada, r.verba_metodo,
               r.quantitativo_valor, r.quantitativo_unidade,
               r.lead_override, r.crono_marco_override, r.data_necessaria_override, r.auto_flags, r.updated_at
        FROM servico s JOIN radar_item r ON r.servico_id = s.id AND r.obra_id = ?
        ORDER BY COALESCE(r.grupo_ordem_override, s.grupo_ordem), s.ordem");
    $st->execute([$rid]);
    $rows = $st->fetchAll();

    $tasks  = !empty($obraRadar['cronograma_id']) ? crono_tasks($obraRadar['cronograma_id']) : [];
    $cotIdx = api_cot_por_servico($pdo, $rid);
    $hoje   = date('Y-m-d');

    $out = [];
    foreach ($rows as $r) $out[] = api_radar_linha($r, $o, $tasks, $cotIdx, $hoje);
    @file_put_contents($cache, json_encode($out, JSON_UNESCAPED_UNICODE));
    @chmod($cache, 0600);
    return $out;
}

/**
 * Uma linha do radar no formato da API.
 * As DATAS não vêm do banco: data em obra = curada à mão OU a data viva do cronograma do Planejamento;
 * fim da cotação = data em obra − lead; início da cotação = fim − 30 dias. Mesma conta de actions/matriz.php.
 */
function api_radar_linha($r, $obra, $tasks, $cotIdx, $hoje) {
    $auto = ['data_necessaria' => null, 'marco_casado' => null, 'confianca' => 'sem cronograma'];
    if ($tasks) { $a = crono_resolver($r, $tasks); if (is_array($a)) $auto = array_merge($auto, $a); }

    $lead     = ($r['lead_override'] !== null && $r['lead_override'] !== '') ? (int)$r['lead_override'] : 60;
    $dataObra = $r['data_necessaria_override'] ?: ($auto['data_necessaria'] ?? null);
    $fim      = $dataObra ? date('Y-m-d', strtotime($dataObra . ' -' . $lead . ' days')) : null;
    $inicio   = $fim ? date('Y-m-d', strtotime($fim . ' -30 days')) : null;

    $cot        = $cotIdx[(int)$r['servico_id']] ?? null;
    $statusBase = trim((string)($r['status'] ?? '')) ?: 'Não Iniciado';
    $statusAuto = false;
    if ($cot && in_array($statusBase, ['', 'Não Iniciado'], true)) { $statusBase = 'Cotação Iniciada'; $statusAuto = true; }

    $aflags  = !empty($r['auto_flags']) ? (json_decode($r['auto_flags'], true) ?: []) : [];
    $alerta  = api_alerta($statusBase, $inicio, $fim, $hoje);
    $verbaOk = ($r['verba_override'] !== null && $r['verba_override'] !== '');

    $bloco = null;
    if ($cot) {
        $u = $cot['ultima'];
        $bloco = [
            'cotacao_id'          => (int)$u['id'],
            'titulo'              => $u['apelido'] ?: $u['titulo'],
            'status'              => $u['status'],
            'status_texto'        => api_cot_status_label($u['status']),
            'finalizada'          => ($u['status'] === 'finalizada'),
            'fornecedores_convidados'  => (int)$u['convidados'],
            'fornecedores_disparados'  => (int)$u['disparados'],
            'propostas_recebidas' => (int)$u['propostas'],
            'melhor_oferta'       => api_num($u['melhor']),
            'quantas_cotacoes'    => (int)$cot['n'],
            'detalhe_url'         => 'api.php?recurso=cotacao&id=' . (int)$u['id'],
        ];
    }
    return [
        'obra_id'          => $obra['obra_id'],
        'obra'             => $obra['obra'],
        'item_id'          => (int)$r['servico_id'],
        'item'             => $r['nome'],
        'grupo'            => $r['grupo'],
        'curva'            => $r['curva'] ?: null,
        'tipo'             => $r['tipo'] ?: null,
        'responsavel'      => trim((string)$r['responsavel']) ?: null,
        'status'           => $statusBase,
        'status_automatico'=> $statusAuto,          // true = veio de "existe cotação", não foi digitado
        'alerta'           => $alerta,
        'alerta_texto'     => api_alerta_label($alerta),
        'inicio_cotacao'   => $inicio,
        'fim_cotacao'      => $fim,
        'data_em_obra'     => $dataObra,
        'data_em_obra_origem' => $r['data_necessaria_override'] ? 'curada' : (($auto['data_necessaria'] ?? null) ? 'cronograma' : 'sem data'),
        'marco_cronograma' => $r['crono_marco_override'] ?: ($auto['marco_casado'] ?? null),
        'lead_dias'        => $lead,
        'verba_definida'   => $verbaOk ? (float)$r['verba_override'] : null,
        'verba_estimada'   => api_num($r['verba_estim']),
        'verba_confirmada' => (bool)((int)($r['verba_curada'] ?? 0)),
        'quantidade'       => api_num($r['quantitativo_valor']),
        'unidade'          => $r['quantitativo_unidade'] ?: ($r['unidade'] ?: null),
        'fornecedor'       => trim((string)$r['fornecedor']) ?: null,
        'observacoes'      => trim((string)$r['observacoes']) ?: null,
        'preenchido_por_robo' => !empty($aflags),   // auto-vínculo ainda não confirmado por humano
        'atualizado_em'    => $r['updated_at'] ?: null,
        'cotacao'          => $bloco,
    ];
}

/* ─────────────────────────── cotações ─────────────────────────── */

function api_cotacoes_lista($pdo) {
    $obras = api_obras($pdo);
    $porRadar = [];                                  // radar obra.id => ficha
    foreach ($obras as $o) if ($o['_radar_id']) $porRadar[$o['_radar_id']] = $o;

    $sql = "SELECT c.id, c.titulo, c.apelido, c.descricao, c.obra_id, c.obra_livre, c.servico_id, c.categoria,
                   c.status, c.verba, c.criado_por, c.criado_nome, c.created_at, c.updated_at,
                   c.num_solicitacao, c.num_pedido, c.solic_coligada,
                   o.nome AS obra_radar_nome, s.nome AS item_nome,
                   (SELECT COUNT(*) FROM cotacao_item ci WHERE ci.cotacao_id=c.id) AS n_itens,
                   (SELECT COUNT(*) FROM cotacao_fornecedor cf WHERE cf.cotacao_id=c.id) AS convidados,
                   (SELECT COUNT(*) FROM cotacao_fornecedor cf WHERE cf.cotacao_id=c.id AND cf.enviado_em IS NOT NULL AND cf.enviado_em<>'') AS disparados,
                   (SELECT COUNT(*) FROM cotacao_proposta cp WHERE cp.cotacao_id=c.id AND (cp.ativa=1 OR cp.ativa IS NULL)) AS propostas,
                   (SELECT MIN(cp.total) FROM cotacao_proposta cp WHERE cp.cotacao_id=c.id AND (cp.ativa=1 OR cp.ativa IS NULL) AND cp.total>0) AS melhor
            FROM cotacao c
            LEFT JOIN obra o    ON o.id = c.obra_id
            LEFT JOIN servico s ON s.id = c.servico_id
            ORDER BY c.id DESC";
    $out = [];
    foreach ($pdo->query($sql) as $c) {
        $f = $c['obra_id'] ? ($porRadar[(int)$c['obra_id']] ?? null) : null;
        // origem: de onde a cotação nasceu — o Murilo precisa separar "criada do zero" das demais
        $origem = $c['servico_id'] ? 'radar' : (trim((string)$c['num_solicitacao']) !== '' || trim((string)$c['solic_coligada']) !== '' ? 'solicitacao' : 'zero');
        $out[] = [
            'cotacao_id'   => (int)$c['id'],
            'titulo'       => $c['titulo'],
            'apelido'      => $c['apelido'] ?: null,
            'descricao'    => trim((string)$c['descricao']) ?: null,
            'obra_id'      => $f['obra_id'] ?? null,
            'obra'         => $f['obra'] ?? ($c['obra_radar_nome'] ?: ($c['obra_livre'] ?: null)),
            'origem'       => $origem,
            'origem_texto' => ['radar' => 'Criada a partir do radar', 'solicitacao' => 'Criada a partir de solicitação de compra', 'zero' => 'Criada do zero'][$origem],
            'item_radar'   => $c['item_nome'] ?: null,
            'item_id'      => $c['servico_id'] ? (int)$c['servico_id'] : null,
            'categoria'    => $c['categoria'] ?: null,
            'status'       => $c['status'],
            'status_texto' => api_cot_status_label($c['status']),
            'finalizada'   => ($c['status'] === 'finalizada'),
            'criado_por'   => $c['criado_nome'] ?: null,
            'criado_em'    => $c['created_at'] ?: null,
            'atualizado_em'=> $c['updated_at'] ?: null,
            'itens'        => (int)$c['n_itens'],
            'fornecedores_convidados' => (int)$c['convidados'],
            'fornecedores_disparados' => (int)$c['disparados'],
            'propostas_recebidas'     => (int)$c['propostas'],
            'melhor_oferta'  => api_num($c['melhor']),
            'verba'          => api_num($c['verba']),
            'num_solicitacao'=> trim((string)$c['num_solicitacao']) ?: null,
            'num_pedido'     => trim((string)$c['num_pedido']) ?: null,
            'detalhe_url'    => 'api.php?recurso=cotacao&id=' . (int)$c['id'],
        ];
    }
    return $out;
}

/** Uma cotação em detalhe: itens, fornecedores (convidado × disparado × respondeu) e o mapa comparativo. */
function api_cotacao_detalhe($pdo, $id) {
    $lista = api_cotacoes_lista($pdo);
    $cab = null;
    foreach ($lista as $c) if ($c['cotacao_id'] === $id) { $cab = $c; break; }
    if (!$cab) api_erro('Cotação ' . $id . ' não encontrada.', 404);

    $itens = [];
    $q = $pdo->prepare("SELECT id, descricao, unidade, quantidade, observacao, solic_numero FROM cotacao_item WHERE cotacao_id=? ORDER BY ordem, id");
    $q->execute([$id]);
    foreach ($q as $i) $itens[] = ['item_id' => (int)$i['id'], 'descricao' => $i['descricao'], 'unidade' => $i['unidade'] ?: null,
                                   'quantidade' => api_num($i['quantidade']), 'observacao' => trim((string)$i['observacao']) ?: null,
                                   'num_solicitacao' => trim((string)$i['solic_numero']) ?: null];

    // propostas VIGENTES (revisões arquivadas ficam de fora — senão o comparativo mostra preço velho)
    $props = [];
    $q = $pdo->prepare("SELECT id, fornecedor_id, fornecedor_nome, prazo, observacoes, data_resposta, total, revisao
                        FROM cotacao_proposta WHERE cotacao_id=? AND (ativa=1 OR ativa IS NULL) ORDER BY (total IS NULL), total, id");
    $q->execute([$id]);
    foreach ($q as $p) $props[(int)$p['id']] = ['proposta_id' => (int)$p['id'], 'fornecedor_id' => $p['fornecedor_id'] ? (int)$p['fornecedor_id'] : null,
        'fornecedor' => $p['fornecedor_nome'], 'prazo' => $p['prazo'] ?: null, 'observacoes' => trim((string)$p['observacoes']) ?: null,
        'recebida_em' => $p['data_resposta'] ?: null, 'total' => api_num($p['total']), 'revisao' => (int)$p['revisao'], 'precos' => []];

    if ($props) {
        $in = implode(',', array_map('intval', array_keys($props)));
        foreach ($pdo->query("SELECT proposta_id, cotacao_item_id, preco_unit, preco_total, observacao FROM cotacao_proposta_item WHERE proposta_id IN ($in)") as $pi)
            $props[(int)$pi['proposta_id']]['precos'][(int)$pi['cotacao_item_id']] = [
                'preco_unitario' => api_num($pi['preco_unit']), 'preco_total' => api_num($pi['preco_total']),
                'observacao' => trim((string)$pi['observacao']) ?: null];
    }

    // MELHOR POR ITEM: compara o preço TOTAL (mesma regra do mapa da tela). Preço total nulo ou 0 não concorre.
    $melhorPorItem = []; $somaMelhores = 0.0;
    foreach ($itens as $it) {
        $best = null;
        foreach ($props as $p) {
            $c = $p['precos'][$it['item_id']] ?? null;
            $pt = $c['preco_total'] ?? null;
            if ($pt === null || $pt <= 0) continue;
            if ($best === null || $pt < $best['preco_total']) $best = ['proposta_id' => $p['proposta_id'], 'fornecedor' => $p['fornecedor'],
                'preco_unitario' => $c['preco_unitario'], 'preco_total' => $pt];
        }
        if ($best) { $melhorPorItem[$it['item_id']] = $best; $somaMelhores += $best['preco_total']; }
    }

    // fornecedores: convidado (existe a linha) × disparado (enviado_em) × respondeu (tem proposta vigente)
    $forn = [];
    $q = $pdo->prepare("SELECT cf.id, cf.fornecedor_id, cf.fornecedor_nome, cf.categoria, cf.created_at,
                               cf.enviado_em, cf.enviado_canal, cf.inbound_em, cf.inbound_tipo
                        FROM cotacao_fornecedor cf WHERE cf.cotacao_id=? ORDER BY cf.fornecedor_nome");
    $q->execute([$id]);
    foreach ($q as $cf) {
        $resp = null;
        foreach ($props as $p) {
            $mesmo = ($cf['fornecedor_id'] && $p['fornecedor_id'] && (int)$cf['fornecedor_id'] === $p['fornecedor_id'])
                  || (strtolower(trim((string)$cf['fornecedor_nome'])) === strtolower(trim((string)$p['fornecedor'])));
            if ($mesmo) { $resp = $p; break; }
        }
        $forn[] = [
            'fornecedor'   => $cf['fornecedor_nome'],
            'categoria'    => $cf['categoria'] ?: null,
            'convidado_em' => $cf['created_at'] ?: null,
            'disparado'    => !empty($cf['enviado_em']),
            'disparado_em' => $cf['enviado_em'] ?: null,
            'respondeu'    => $resp !== null,
            'proposta_total' => $resp['total'] ?? null,
            'proposta_prazo' => $resp['prazo'] ?? null,
            'email_recebido_em'  => $cf['inbound_em'] ?: null,
            'email_classificado' => $cf['inbound_tipo'] ?: null,
        ];
    }

    // pedidos de compra por coligada (o nº de PC não é único entre coligadas)
    $pcs = [];
    $q = $pdo->prepare("SELECT coligada, coligada_cod, num_pedido FROM cotacao_pedido WHERE cotacao_id=? AND num_pedido IS NOT NULL AND num_pedido<>''");
    $q->execute([$id]);
    foreach ($q as $p) $pcs[] = ['coligada' => $p['coligada'], 'coligada_cod' => $p['coligada_cod'] ? (int)$p['coligada_cod'] : null, 'num_pedido' => $p['num_pedido']];

    foreach ($itens as &$it) $it['melhor'] = $melhorPorItem[$it['item_id']] ?? null;
    unset($it);
    foreach ($props as &$p) {
        $p['precos'] = array_map(fn($k, $v) => $v + ['item_id' => $k], array_keys($p['precos']), $p['precos']);
    }
    unset($p);

    return $cab + [
        'itens_detalhe'    => $itens,
        'fornecedores'     => $forn,
        'propostas'        => array_values($props),
        'soma_dos_melhores'=> $somaMelhores > 0 ? round($somaMelhores, 2) : null,
        'pedidos_de_compra'=> $pcs,
    ];
}

/* ─────────────────────────── solicitações ─────────────────────────── */

function api_solicitacoes($pdo) {
    $rows = solic_fila_all();
    $sol  = solic_agrupar($rows);
    $cov  = solic_cobertura($pdo);

    // de-para (coligada + centro de custo) → obra + comprador, com o nome já reconciliado com o
    // cadastro da obra (a mesma obra chegava a aparecer como "PEDRA AZUL" aqui e "Diamond" no radar)
    $map = [];
    if (function_exists('solic_obra_map')) $map = solic_obra_map($pdo);
    else foreach ($pdo->query("SELECT * FROM solic_obra") as $o) $map[$o['coligada'].'|'.$o['obra_cod']] = $o;   // deploy parcial

    $ov = [];
    foreach ($pdo->query("SELECT coligada, numero, status, observacoes, cotacao_id FROM solic_overlay") as $o)
        $ov[$o['coligada'] . '|' . $o['numero']] = $o;

    // cotações citadas, p/ devolver o andamento junto com a SC
    $cots = [];
    foreach (api_cotacoes_lista($pdo) as $c) $cots[$c['cotacao_id']] = $c;

    $obras = api_obras($pdo);
    $porRadar = [];
    foreach ($obras as $o) if ($o['_radar_id']) $porRadar[$o['_radar_id']] = $o;

    $hoje = new DateTime('today');
    $out = [];
    foreach ($sol as $s) {
        $k    = $s['coligada'] . '|' . $s['obra_cod'];
        $o    = function_exists('solic_obra_de') ? solic_obra_de($map,$s['coligada'],$s['obra_cod']) : ($map[$k] ?? null);
        $ovr  = $ov[$s['coligada'] . '|' . $s['numero']] ?? null;
        $itens = $s['itens'];
        $cb   = solic_item_cobertura($itens, $cov[$s['coligada'] . '|' . $s['numero']] ?? []);
        $dias = $s['emissao'] ? (int)(new DateTime(substr($s['emissao'], 0, 10)))->diff($hoje)->format('%r%a') : null;
        $st   = $ovr['status'] ?? 'pendente';
        $fic  = ($o && !empty($o['radar_obra_id'])) ? ($porRadar[(int)$o['radar_obra_id']] ?? null) : null;

        $cotsSC = [];
        foreach ($cb['cotacoes'] as $c) if (isset($cots[$c['id']])) $cotsSC[] = $cots[$c['id']];
        if (!$cotsSC && !empty($ovr['cotacao_id']) && isset($cots[(int)$ovr['cotacao_id']])) $cotsSC[] = $cots[(int)$ovr['cotacao_id']];

        $out[] = [
            'numero'          => $s['numero'],
            'coligada'        => $s['coligada'],
            'centro_custo'    => $s['obra_cod'],
            'obra_id'         => $fic['obra_id'] ?? null,
            'obra'            => ($o['nome_comercial'] ?? '') ?: api_obra_sc_default($s['coligada'], $s['obra_cod']),
            'comprador'       => $o['comprador_nome'] ?? null,
            'emissao'         => $s['emissao'],
            'dias_em_aberto'  => $dias,
            'status'          => $st,
            'status_texto'    => api_sc_status_label($st),
            'observacoes'     => trim((string)($ovr['observacoes'] ?? '')) ?: null,
            'tem_cotacao'     => !empty($cotsSC),
            'cotacao_situacao'=> $cb['cobertura'],
            'cotacao_situacao_texto' => api_cobertura_label($cb['cobertura']),
            'itens_total'     => count($itens),
            'itens_cotados'   => $cb['n_cobertos'],
            'cotacoes'        => $cotsSC,
            'itens'           => array_map(fn($i) => [
                'seq'        => (int)$i['seq'],
                'codigo'     => $i['codprd'] ?: null,
                'produto'    => $i['produto'],
                'quantidade' => api_num($i['qtd']),
                'unidade'    => $i['und'] ?: null,
                'observacao' => trim((string)($i['observacao'] ?? '')) ?: null,
                'situacao'   => $i['cot'] ?? 'vazio',
                'situacao_texto' => ['vazio' => 'Sem cotação', 'cotando' => 'Em cotação', 'coberto' => 'Cotada'][$i['cot'] ?? 'vazio'],
                'cotacao_id' => $i['cot_cid'] ?? null,
            ], $itens),
        ];
    }
    return $out;
}

/* ─────────────────────────── roteador ─────────────────────────── */

if (defined('API_LIB_ONLY')) return;   // biblioteca: as funções acima bastam, o endpoint não roda

try {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    /* ---- POST: administração das chaves (autenticação do COCKPIT, não da API) ---- */
    if ($metodo === 'POST') {
        $pdo   = db();
        $in    = json_decode(file_get_contents('php://input'), true) ?: [];
        $perms = user_perms($pdo, $in['me'] ?? null);
        if (empty($perms['perm_admin'])) api_erro('Apenas administradores gerenciam chaves de API.', 403);
        $acao   = $in['acao'] ?? '';
        $chaves = api_chaves_ler();

        if ($acao === 'chave_criar') {
            $nome = trim((string)($in['nome'] ?? '')) ?: 'Sistema externo';
            $seg  = bin2hex(random_bytes(24));
            $nova = ['id' => bin2hex(random_bytes(6)), 'nome' => $nome, 'chave' => $seg,
                     'criada_em' => date('c'), 'criada_por' => $perms['nome'] ?? '', 'usos' => 0, 'revogada' => false];
            $chaves[] = $nova;
            api_chaves_gravar($chaves);
            // única vez em que o segredo aparece — depois disso só existe no arquivo do servidor
            api_out(['ok' => true, 'chave' => $seg, 'id' => $nova['id'], 'nome' => $nome,
                     'aviso' => 'Guarde esta chave agora. Ela não será mostrada de novo.']);
        }
        if ($acao === 'chave_listar') {
            api_out(['ok' => true, 'chaves' => array_map(fn($c) => [
                'id' => $c['id'] ?? '', 'nome' => $c['nome'] ?? '', 'criada_em' => $c['criada_em'] ?? '',
                'ultimo_uso' => $c['ultimo_uso'] ?? null, 'revogada' => !empty($c['revogada']),
                'final' => '…' . substr((string)($c['chave'] ?? ''), -6)], $chaves)]);
        }
        if ($acao === 'chave_revogar') {
            $id = (string)($in['id'] ?? '');
            $achou = false;
            foreach ($chaves as $i => $c) if (($c['id'] ?? '') === $id) { $chaves[$i]['revogada'] = true; $achou = true; }
            if (!$achou) api_erro('Chave não encontrada: ' . $id, 404);
            api_chaves_gravar($chaves);
            api_out(['ok' => true, 'revogada' => $id]);
        }
        api_erro('Ação desconhecida: ' . $acao);
    }

    /* ---- GET: os recursos de leitura ---- */
    $recurso = trim((string)($_GET['recurso'] ?? ''));

    if ($recurso === '') {   // índice — não exige chave, só descreve a API
        api_out(['ok' => true, 'api' => 'Cockpit de Suprimentos — Caprem', 'versao' => API_VERSAO,
            'autenticacao' => 'header  X-API-Key: <sua chave>',
            'somente_leitura' => true,
            'recursos' => [
                ['url' => '?recurso=obras',        'descricao' => 'Lista de obras (use o obra_id nos outros recursos)'],
                ['url' => '?recurso=radar',        'descricao' => 'Itens do radar: responsável, status, datas de cotação, data em obra, alerta e a cotação vinculada',
                 'filtros' => ['obra_id', 'obra', 'status', 'responsavel', 'alerta', 'grupo', 'q', 'com_cotacao', 'pagina', 'por_pagina']],
                ['url' => '?recurso=solicitacoes', 'descricao' => 'Solicitações de compra do TOTVS com o andamento da cotação, item a item',
                 'filtros' => ['obra_id', 'obra', 'status', 'comprador', 'situacao', 'q', 'pagina', 'por_pagina']],
                ['url' => '?recurso=cotacoes',     'descricao' => 'Cotações (do radar, de solicitação, ou criadas do zero)',
                 'filtros' => ['obra_id', 'obra', 'status', 'origem', 'criado_por', 'q', 'pagina', 'por_pagina']],
                ['url' => '?recurso=cotacao&id=N', 'descricao' => 'Uma cotação em detalhe: itens, fornecedores disparados, propostas recebidas e melhor preço por item'],
            ]]);
    }

    api_auth();
    $pdo  = db();
    $pag  = (int)($_GET['pagina'] ?? 1);
    $pp   = (int)($_GET['por_pagina'] ?? API_POR_PAGINA);
    $q    = api_nz($_GET['q'] ?? '');
    $recarregar = !empty($_GET['recarregar']);

    if ($recurso === 'obras') {
        $lista = array_values(array_map(fn($o) => array_diff_key($o, ['_radar_id' => 1, '_razao' => 1]), api_obras($pdo)));
        api_out(['ok' => true, 'recurso' => 'obras', 'total' => count($lista), 'dados' => $lista]);
    }

    if ($recurso === 'radar') {
        $fichas = api_filtro_obras($pdo, $_GET['obra_id'] ?? '', $_GET['obra'] ?? '');
        $todas  = api_obras($pdo);
        if ($fichas === null) $fichas = array_keys(array_filter($todas, fn($o) => $o['no_radar']));

        // varrer TODAS as obras custa caro na 1ª vez (resolve o cronograma item a item). O resultado fica
        // em cache por 30 min; se estourar o orçamento de tempo, devolvemos o que deu e avisamos.
        @set_time_limit(0);
        $t0 = microtime(true); $linhas = []; $faltando = [];
        foreach ($fichas as $fid) {
            if (microtime(true) - $t0 > API_ORCAMENTO_S) { $faltando[] = $todas[$fid]['obra'] ?? $fid; continue; }
            $linhas = array_merge($linhas, api_radar_obra($pdo, $fid, $recarregar));
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

        [$pagina, $total, $p, $paginas, $ppp] = api_paginar($linhas, $pag, $pp);
        api_out(['ok' => true, 'recurso' => 'radar', 'total' => $total, 'pagina' => $p, 'paginas' => $paginas,
                 'por_pagina' => $ppp, 'gerado_em' => date('c'),
                 'parcial' => !empty($faltando),   // true = estourou o tempo; chame de novo (o cache já esquentou)
                 'obras_nao_processadas' => $faltando,
                 'dados' => $pagina]);
    }

    if ($recurso === 'cotacoes') {
        $lista  = api_cotacoes_lista($pdo);
        $fichas = api_filtro_obras($pdo, $_GET['obra_id'] ?? '', $_GET['obra'] ?? '');
        $fStatus = trim((string)($_GET['status'] ?? ''));
        $fOrigem = trim((string)($_GET['origem'] ?? ''));
        $fCriado = api_nz($_GET['criado_por'] ?? '');
        $lista = array_values(array_filter($lista, function ($c) use ($fichas, $fStatus, $fOrigem, $fCriado, $q) {
            if ($fichas !== null && !in_array($c['obra_id'], $fichas, true)) return false;
            if ($fStatus !== '' && $c['status'] !== $fStatus) return false;
            if ($fOrigem !== '' && $c['origem'] !== $fOrigem) return false;
            if ($fCriado !== '' && api_nz($c['criado_por']) !== $fCriado) return false;
            if ($q !== '' && strpos(api_nz($c['titulo'] . ' ' . $c['apelido'] . ' ' . $c['item_radar'] . ' ' . $c['num_solicitacao'] . ' ' . $c['num_pedido']), $q) === false) return false;
            return true;
        }));
        [$pagina, $total, $p, $paginas, $ppp] = api_paginar($lista, $pag, $pp);
        api_out(['ok' => true, 'recurso' => 'cotacoes', 'total' => $total, 'pagina' => $p, 'paginas' => $paginas,
                 'por_pagina' => $ppp, 'gerado_em' => date('c'), 'dados' => $pagina]);
    }

    if ($recurso === 'cotacao') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) api_erro('Informe ?id=<número da cotação>.');
        api_out(['ok' => true, 'recurso' => 'cotacao', 'gerado_em' => date('c'), 'dados' => api_cotacao_detalhe($pdo, $id)]);
    }

    if ($recurso === 'solicitacoes') {
        $lista  = api_solicitacoes($pdo);
        $fichas = api_filtro_obras($pdo, $_GET['obra_id'] ?? '', $_GET['obra'] ?? '');
        $nomeObra = api_nz($_GET['obra'] ?? '');
        $fStatus = trim((string)($_GET['status'] ?? ''));
        $fComp   = api_nz($_GET['comprador'] ?? '');
        $fSit    = trim((string)($_GET['situacao'] ?? ''));
        $lista = array_values(array_filter($lista, function ($s) use ($fichas, $nomeObra, $fStatus, $fComp, $fSit, $q) {
            // a SC pode não ter obra do radar mapeada — nesse caso o filtro por nome ainda vale
            if ($fichas !== null) {
                $bate = ($s['obra_id'] !== null && in_array($s['obra_id'], $fichas, true))
                     || ($nomeObra !== '' && strpos(api_nz($s['obra']), $nomeObra) !== false);
                if (!$bate) return false;
            }
            if ($fStatus !== '' && $s['status'] !== $fStatus) return false;
            if ($fComp !== ''   && api_nz($s['comprador']) !== $fComp) return false;
            if ($fSit !== ''    && $s['cotacao_situacao'] !== $fSit) return false;
            if ($q !== '') {
                $alvo = api_nz($s['numero'] . ' ' . $s['obra'] . ' ' . implode(' ', array_map(fn($i) => $i['produto'], $s['itens'])));
                if (strpos($alvo, $q) === false) return false;
            }
            return true;
        }));
        [$pagina, $total, $p, $paginas, $ppp] = api_paginar($lista, $pag, $pp);
        api_out(['ok' => true, 'recurso' => 'solicitacoes', 'total' => $total, 'pagina' => $p, 'paginas' => $paginas,
                 'por_pagina' => $ppp, 'gerado_em' => date('c'), 'dados' => $pagina]);
    }

    api_erro('Recurso desconhecido: "' . $recurso . '". Chame a API sem parâmetros para ver a lista.', 404);

} catch (Throwable $e) {
    api_erro($e->getMessage(), 500);
}
