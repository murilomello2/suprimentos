<?php
require_once __DIR__ . '/config.php';

/**
 * Base do Cockpit de Suprimentos.
 * Interina em SQLite (sem dependência do TI). O schema foi desenhado para
 * portar 1:1 para MySQL quando o banco definitivo estiver disponível.
 *
 * Tabelas:
 *   servico    — catálogo de ~123 tipos de serviço (independente de obra)
 *   obra       — obras + mapeamento de ids entre sistemas
 *   radar_item — célula (obra × serviço): status/curadoria do radar
 */

define('SCHEMA_VERSION', 7); // bump força recriação do catálogo + reseed

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!is_dir(dirname(DB_PATH))) @mkdir(dirname(DB_PATH), 0775, true);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    db_migrate($pdo);
    db_schema($pdo);
    return $pdo;
}

/** Auto-migração simples: se a versão do schema mudou, recria as tabelas e força reseed. */
function db_migrate($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS meta (k TEXT PRIMARY KEY, v TEXT)");
    $cur = $pdo->query("SELECT v FROM meta WHERE k='schema_version'")->fetch();
    $ver = $cur ? (int)$cur['v'] : 0;
    if ($ver !== SCHEMA_VERSION) {
        foreach (['radar_item','servico','obra','orcamento_linha','composicao','composicao_insumo'] as $t) $pdo->exec("DROP TABLE IF EXISTS $t");
        $pdo->prepare("INSERT OR REPLACE INTO meta (k,v) VALUES ('schema_version',?)")
            ->execute([SCHEMA_VERSION]);
    }
}

function db_schema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS servico (
        id INTEGER PRIMARY KEY,
        ordem INTEGER,
        nome TEXT NOT NULL,
        slug TEXT,
        fase TEXT,
        grupo TEXT,
        grupo_ordem INTEGER,
        curva TEXT,
        forma_contratacao TEXT,
        unidade TEXT,
        quantitativo TEXT,
        lead_dias INTEGER,
        marco_cronograma TEXT,
        termos_orcamento TEXT,
        termos_cronograma TEXT,
        responsavel_padrao TEXT,
        escopo TEXT,
        variaveis_cotar TEXT,
        licoes TEXT,
        documentos TEXT,
        verba_linhas TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS obra (
        id INTEGER PRIMARY KEY,
        nome TEXT NOT NULL,
        slug TEXT UNIQUE,
        codinome TEXT,
        local TEXT,
        cronograma_id TEXT,
        orcamento_total REAL,
        cobertura_orcamento REAL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS radar_item (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        obra_id INTEGER NOT NULL,
        servico_id INTEGER NOT NULL,
        status TEXT,
        responsavel TEXT,
        fornecedor TEXT,
        inicio_cotacao TEXT,
        fim_cotacao TEXT,
        verba_estim REAL,
        confianca TEXT,
        observacoes TEXT,
        validado INTEGER DEFAULT 0,
        tipo TEXT,
        verba_metodo TEXT,
        verba_material REAL,
        verba_mo REAL,
        composicao_id INTEGER,
        area_base REAL,
        verba_override REAL,
        lead_override INTEGER,
        crono_marco_override TEXT,
        data_necessaria_override TEXT,
        orcamento_refs TEXT,
        quantitativo_valor REAL,
        quantitativo_unidade TEXT,
        quantitativo_refs TEXT,
        quantitativo_fonte TEXT,
        updated_at TEXT,
        UNIQUE(obra_id, servico_id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS orcamento_linha (
        id INTEGER PRIMARY KEY,
        obra_id INTEGER NOT NULL DEFAULT 1,
        codigo TEXT,
        parent TEXT,
        depth INTEGER,
        nivel INTEGER,
        descricao TEXT,
        path_str TEXT,
        unidade TEXT,
        qtde REAL,
        valor REAL,
        folha INTEGER
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_orc_parent ON orcamento_linha(parent)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS composicao (
        id INTEGER PRIMARY KEY, obra_id INTEGER DEFAULT 1,
        descricao TEXT, unidade TEXT, qtde_total REAL, rs_unit REAL, rs_total REAL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS composicao_insumo (
        id INTEGER PRIMARY KEY AUTOINCREMENT, composicao_id INTEGER,
        descricao TEXT, unidade TEXT, coef REAL, rs_unit REAL, rs_total REAL, tipo TEXT
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ci_comp ON composicao_insumo(composicao_id)");
    // usuários/permissões — NÃO entra no drop de migração (persiste entre versões de schema)
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuario (
        bitrix_id TEXT PRIMARY KEY,
        nome TEXT, cargo TEXT,
        papel TEXT,                 -- admin | diretor | comprador | coordenador | personalizado
        ver_escopo TEXT,            -- todas | sel
        editar_escopo TEXT,         -- nenhuma | todas | sel
        obras_ver TEXT,             -- json de obra ids
        obras_editar TEXT,          -- json de obra ids
        menus TEXT,                 -- json de chaves de menu liberadas
        perm_admin INTEGER DEFAULT 0,
        ativo INTEGER DEFAULT 1,
        updated_at TEXT
    )");
}

function _slugify($s){
    $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE', $s);
    $s = strtolower(preg_replace('/[^a-zA-Z0-9]+/','-', $s));
    return trim($s,'-');
}

/** Cria um novo item (servico catálogo + radar_item da obra 1). Copia dicionário de copy_from se dado. Retorna ordem. */
function criar_item($pdo, $nome, $grupo, $tipo = '', $curva = '', $copy_from = null) {
    $nome = trim($nome);
    if ($nome === '') throw new Exception('nome obrigatório');
    $nid = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM servico")->fetchColumn();
    $maxo = (int)$pdo->query("SELECT COALESCE(MAX(ordem),0) FROM servico")->fetchColumn();
    $nid = max($nid, $maxo) + 1; // mantém id == ordem (invariante usada no item_update)

    $go = $pdo->prepare("SELECT grupo_ordem FROM servico WHERE grupo=? LIMIT 1");
    $go->execute([$grupo]);
    $grupo_ordem = $go->fetchColumn();
    if ($grupo_ordem === false || $grupo_ordem === null) $grupo_ordem = 50;

    $src = null;
    if ($copy_from) {
        $s = $pdo->prepare("SELECT * FROM servico WHERE id=?"); $s->execute([(int)$copy_from]);
        $src = $s->fetch() ?: null;
    }
    $g = fn($k, $d='') => $src[$k] ?? $d;

    $pdo->prepare("INSERT INTO servico
        (id,ordem,nome,slug,fase,grupo,grupo_ordem,curva,forma_contratacao,unidade,quantitativo,lead_dias,
         marco_cronograma,termos_orcamento,termos_cronograma,responsavel_padrao,escopo,variaveis_cotar,licoes,documentos,verba_linhas)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$nid,$nid,$nome,_slugify($nome),$g('fase'),$grupo,$grupo_ordem,
            $curva ?: $g('curva','C'),$g('forma_contratacao'),$g('unidade'),$g('quantitativo'),$g('lead_dias'),
            $g('marco_cronograma'),$g('termos_orcamento'),$g('termos_cronograma'),$g('responsavel_padrao'),
            $g('escopo'),$g('variaveis_cotar'),$g('licoes'),$g('documentos'),$g('verba_linhas')]);
    $pdo->prepare("INSERT INTO radar_item (obra_id,servico_id,status,tipo,updated_at) VALUES (1,?,?,?,?)")
        ->execute([$nid,'Não Iniciado',$tipo,date('c')]);
    return $nid;
}

/** Carga inicial (idempotente) a partir de data/seed/trinity.json. */
function db_seed_if_empty() {
    $pdo = db();
    $n = (int)$pdo->query("SELECT COUNT(*) c FROM servico")->fetch()['c'];
    if ($n > 0) return false;

    $seed = json_decode(@file_get_contents(SEED_DIR . '/trinity.json'), true);
    if (!$seed) throw new Exception('seed trinity.json não encontrado');

    $o = $seed['obra'];
    $pdo->prepare("INSERT INTO obra (id,nome,slug,codinome,local,cronograma_id,orcamento_total,cobertura_orcamento)
                   VALUES (1,?,?,?,?,?,?,?)")
        ->execute([$o['nome'], $o['slug'], $o['codinome'], $o['local'] ?? '',
                   $o['cronograma_id'], $o['orcamento_total'], $o['cobertura_orcamento'] ?? null]);

    $sv = $pdo->prepare("INSERT INTO servico
        (id,ordem,nome,slug,fase,grupo,grupo_ordem,curva,forma_contratacao,unidade,quantitativo,lead_dias,
         marco_cronograma,termos_orcamento,termos_cronograma,responsavel_padrao,
         escopo,variaveis_cotar,licoes,documentos,verba_linhas)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $ri = $pdo->prepare("INSERT INTO radar_item
        (obra_id,servico_id,status,responsavel,fornecedor,inicio_cotacao,fim_cotacao,
         verba_estim,confianca,observacoes,tipo,updated_at)
        VALUES (1,?,?,?,?,?,?,?,?,?,?,?)");

    $pdo->beginTransaction();
    $i = 0;
    foreach ($seed['servicos'] as $s) {
        $i++;
        $sid = is_int($s['ordem']) ? $s['ordem'] : $i;
        $sv->execute([$sid, $s['ordem'], $s['nome'], $s['slug'], $s['fase'],
            $s['grupo'] ?? 'Outros', $s['grupo_ordem'] ?? 99, $s['curva'],
            $s['forma_contratacao'], $s['unidade'], $s['quantitativo'], $s['lead_dias'],
            $s['marco_cronograma'], $s['termos_orcamento'], $s['termos_cronograma'],
            $s['responsavel_padrao'] ?? '',
            $s['escopo'] ?? '', $s['variaveis_cotar'] ?? '', $s['licoes'] ?? '',
            $s['documentos'] ?? '', $s['verba_linhas'] ?? '']);
        $ri->execute([$sid, $s['status'], $s['responsavel'], $s['fornecedor'],
            $s['inicio_cotacao'], $s['fim_cotacao'], $s['verba_estim'], $s['confianca'],
            $s['observacoes'], $s['tipo'] ?? '', date('c')]);
    }
    $pdo->commit();

    // linhas do orçamento analítico (para compor a verba)
    $orc = json_decode(@file_get_contents(SEED_DIR . '/orcamento_trinity.json'), true);
    if ($orc && !empty($orc['linhas'])) {
        $ol = $pdo->prepare("INSERT INTO orcamento_linha
            (id,obra_id,codigo,parent,depth,nivel,descricao,path_str,unidade,qtde,valor,folha)
            VALUES (?,1,?,?,?,?,?,?,?,?,?,?)");
        $pdo->beginTransaction();
        foreach ($orc['linhas'] as $l) {
            $ol->execute([$l['id'], $l['codigo'], $l['parent'], $l['depth'], $l['nivel'],
                $l['descricao'], $l['path_str'], $l['unidade'], $l['qtde'], $l['valor'], $l['folha']]);
        }
        $pdo->commit();
    }

    // composições (Lista de Composição) — insumos com coeficiente p/ material×MO e quantitativo
    $comp = json_decode(@file_get_contents(SEED_DIR . '/composicao_trinity.json'), true);
    if ($comp && !empty($comp['composicoes'])) {
        $c  = $pdo->prepare("INSERT INTO composicao (id,obra_id,descricao,unidade,qtde_total,rs_unit,rs_total) VALUES (?,1,?,?,?,?,?)");
        $ci = $pdo->prepare("INSERT INTO composicao_insumo (composicao_id,descricao,unidade,coef,rs_unit,rs_total,tipo) VALUES (?,?,?,?,?,?,?)");
        $pdo->beginTransaction();
        foreach ($comp['composicoes'] as $co) {
            $c->execute([$co['id'], $co['descricao'], $co['unidade'], $co['qtde_total'], $co['rs_unit'], $co['rs_total']]);
            foreach ($co['insumos'] as $in) {
                $ci->execute([$co['id'], $in['descricao'], $in['unidade'], $in['coef'], $in['rs_unit'], $in['rs_total'], $in['tipo']]);
            }
        }
        $pdo->commit();
    }
    return true;
}
