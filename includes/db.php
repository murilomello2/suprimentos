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
    // ---- MySQL (destino definitivo) — ativa quando DB_DRIVER='mysql' em secrets.php ----
    if (defined('DB_DRIVER') && DB_DRIVER === 'mysql') {
        $pdo = new PDO('mysql:host=' . MYSQL_HOST . ';port=' . MYSQL_PORT . ';dbname=' . MYSQL_DB . ';charset=' . MYSQL_CHARSET,
                       MYSQL_USER, MYSQL_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        db_schema_mysql($pdo);   // create-only (índices inline); os dados (inclusive curadoria) vêm da migração
        return $pdo;
    }
    // ---- SQLite (interino — permanece como fallback instantâneo) ----
    if (!is_dir(dirname(DB_PATH))) @mkdir(dirname(DB_PATH), 0775, true);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA busy_timeout=4000'); // evita SQLITE_BUSY imediato com múltiplos usuários
    db_migrate($pdo);
    db_schema($pdo);
    return $pdo;
}

/**
 * Schema MySQL 5.7 — create-only, InnoDB/utf8mb4, índices INLINE (o usuário não tem DROP/INDEX avulso).
 * Todas as colunas (inclusive as que no SQLite eram aditivas via ALTER) já nascem no CREATE.
 * NÃO roda datafix nem seed: os dados — inclusive a curadoria — vêm da migração server-side (_migrate_mysql.php).
 * Idempotente (CREATE TABLE IF NOT EXISTS) e usa só privilégio CREATE.
 */
function db_schema_mysql($pdo) {
    $E = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec("CREATE TABLE IF NOT EXISTS meta (
        k VARCHAR(191) NOT NULL, v TEXT, PRIMARY KEY (k)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS servico (
        id INT NOT NULL, ordem INT, nome VARCHAR(255) NOT NULL, slug VARCHAR(191), fase VARCHAR(120), grupo VARCHAR(191),
        grupo_ordem INT, curva VARCHAR(8), forma_contratacao VARCHAR(120), unidade VARCHAR(40),
        quantitativo TEXT, lead_dias INT, marco_cronograma VARCHAR(255),
        termos_orcamento TEXT, termos_cronograma TEXT, responsavel_padrao VARCHAR(191),
        escopo TEXT, variaveis_cotar TEXT, licoes TEXT, documentos TEXT, verba_linhas TEXT,
        PRIMARY KEY (id)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS obra (
        id INT NOT NULL, nome VARCHAR(255) NOT NULL, slug VARCHAR(191), codinome VARCHAR(191),
        `local` VARCHAR(255), cronograma_id VARCHAR(100), orcamento_total DOUBLE, cobertura_orcamento DOUBLE,
        PRIMARY KEY (id), UNIQUE KEY uq_obra_slug (slug)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS radar_item (
        id INT NOT NULL AUTO_INCREMENT, obra_id INT NOT NULL, servico_id INT NOT NULL, status VARCHAR(64),
        responsavel VARCHAR(191), fornecedor VARCHAR(255), inicio_cotacao VARCHAR(40), fim_cotacao VARCHAR(40),
        verba_estim DOUBLE, confianca VARCHAR(40), observacoes TEXT, validado INT DEFAULT 0, tipo VARCHAR(64),
        verba_metodo VARCHAR(40), verba_material DOUBLE, verba_mo DOUBLE, composicao_id INT, area_base DOUBLE,
        verba_override DOUBLE, lead_override INT, crono_marco_override VARCHAR(255), data_necessaria_override VARCHAR(40),
        orcamento_refs TEXT, quantitativo_valor DOUBLE, quantitativo_unidade VARCHAR(40), quantitativo_refs TEXT,
        quantitativo_fonte VARCHAR(64), updated_at VARCHAR(40), composicao_sel TEXT, verba_curada INT DEFAULT 0,
        quant_comp_sel TEXT, quant_curada INT DEFAULT 0, orcamento_excl TEXT,
        PRIMARY KEY (id), UNIQUE KEY uq_obra_servico (obra_id, servico_id)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS orcamento_linha (
        id INT NOT NULL, obra_id INT NOT NULL DEFAULT 1, codigo VARCHAR(64), parent VARCHAR(64),
        depth INT, nivel INT, descricao TEXT, path_str TEXT, unidade VARCHAR(40), qtde DOUBLE, valor DOUBLE, folha INT,
        PRIMARY KEY (id), KEY idx_orc_parent (parent)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS composicao (
        id INT NOT NULL, obra_id INT DEFAULT 1, descricao TEXT, unidade VARCHAR(40),
        qtde_total DOUBLE, rs_unit DOUBLE, rs_total DOUBLE, PRIMARY KEY (id)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS composicao_insumo (
        id INT NOT NULL AUTO_INCREMENT, composicao_id INT, descricao TEXT, unidade VARCHAR(40),
        coef DOUBLE, rs_unit DOUBLE, rs_total DOUBLE, tipo VARCHAR(40), tipo_orig VARCHAR(40),
        PRIMARY KEY (id), KEY idx_ci_comp (composicao_id)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuario (
        bitrix_id VARCHAR(64) NOT NULL, nome VARCHAR(191), cargo VARCHAR(191), papel VARCHAR(40),
        ver_escopo VARCHAR(16), editar_escopo VARCHAR(16), obras_ver TEXT, obras_editar TEXT, menus TEXT,
        perm_admin INT DEFAULT 0, ativo INT DEFAULT 1, updated_at VARCHAR(40),
        perm_crono INT DEFAULT 0, perm_orcamento INT DEFAULT 0, perm_quant INT DEFAULT 0, perm_dicionario INT DEFAULT 0,
        perm_responsaveis INT DEFAULT 0,
        PRIMARY KEY (bitrix_id)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS historico (
        id INT NOT NULL AUTO_INCREMENT, obra_id INT, servico_id INT, item_nome VARCHAR(255),
        bitrix_id VARCHAR(64), usuario_nome VARCHAR(191), campo VARCHAR(120), valor_antes TEXT, valor_depois TEXT,
        created_at VARCHAR(40), PRIMARY KEY (id), KEY idx_hist_serv (servico_id)
    ) $E");
    // DICIONÁRIO DE APRENDIZADO: receita de curadoria por serviço × método construtivo, derivada da(s)
    // obra(s) curada(s). Guardada por NOME/semântica (nunca IDs — outra obra tem outros IDs). JSON = MEDIUMTEXT
    // (lição do truncamento do composicao_sel). Corrigir receita = re-curar o item e re-derivar.
    $pdo->exec("CREATE TABLE IF NOT EXISTS receita (
        servico_id INT NOT NULL, metodo_construtivo VARCHAR(64) NOT NULL DEFAULT 'concreto armado convencional',
        obra_origem VARCHAR(64), crono MEDIUMTEXT, verba MEDIUMTEXT, quant MEDIUMTEXT,
        nota TEXT, updated_at VARCHAR(40),
        PRIMARY KEY (servico_id, metodo_construtivo)
    ) $E");
    // ---- MAPA DE COTAÇÕES (reconstruído no cockpit; Supabase antigo só p/ import em lote futuro) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS cot_categoria (
        id INT NOT NULL AUTO_INCREMENT, nome VARCHAR(191) NOT NULL, ext_id VARCHAR(64), created_at VARCHAR(40),
        PRIMARY KEY (id), UNIQUE KEY uq_cot_cat (nome)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cot_fornecedor (
        id INT NOT NULL AUTO_INCREMENT, nome VARCHAR(255) NOT NULL, categoria VARCHAR(191), cidade VARCHAR(120),
        contato VARCHAR(191), telefone VARCHAR(60), whatsapp VARCHAR(60), email VARCHAR(191),
        itens TEXT, tipo VARCHAR(60), cnpj VARCHAR(40), ativo INT DEFAULT 1, ext_id VARCHAR(64), created_at VARCHAR(40),
        PRIMARY KEY (id), KEY idx_forn_cat (categoria)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao (
        id INT NOT NULL AUTO_INCREMENT, obra_id INT, servico_id INT, titulo VARCHAR(255) NOT NULL,
        categoria VARCHAR(191), tipo_servico VARCHAR(60), verba DOUBLE, verba_origem VARCHAR(40), descricao TEXT, equalizacao TEXT,
        status VARCHAR(40) DEFAULT 'rascunho', aprovacao VARCHAR(40) DEFAULT 'aguardando',
        criado_por VARCHAR(64), criado_nome VARCHAR(191), created_at VARCHAR(40), updated_at VARCHAR(40),
        PRIMARY KEY (id), KEY idx_cot_obra (obra_id), KEY idx_cot_serv (servico_id)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_item (
        id INT NOT NULL AUTO_INCREMENT, cotacao_id INT NOT NULL, descricao TEXT, unidade VARCHAR(40),
        quantidade DOUBLE, observacao TEXT, ordem INT, PRIMARY KEY (id), KEY idx_coti_cot (cotacao_id)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_proposta (
        id INT NOT NULL AUTO_INCREMENT, cotacao_id INT NOT NULL, fornecedor_id INT, fornecedor_nome VARCHAR(255),
        prazo VARCHAR(120), observacoes TEXT, equaliza TEXT, data_resposta VARCHAR(40), total DOUBLE, created_at VARCHAR(40),
        PRIMARY KEY (id), KEY idx_prop_cot (cotacao_id)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_proposta_item (
        id INT NOT NULL AUTO_INCREMENT, proposta_id INT NOT NULL, cotacao_item_id INT NOT NULL,
        preco_unit DOUBLE, preco_total DOUBLE, observacao TEXT,
        PRIMARY KEY (id), KEY idx_propi_prop (proposta_id)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_anexo (
        id INT NOT NULL AUTO_INCREMENT, cotacao_id INT NOT NULL, proposta_id INT, nome VARCHAR(255),
        arquivo VARCHAR(255), tamanho INT, mime VARCHAR(100), criado_por VARCHAR(64), created_at VARCHAR(40),
        PRIMARY KEY (id), KEY idx_anexo_cot (cotacao_id), KEY idx_anexo_prop (proposta_id)
    ) $E");
    // DICIONÁRIO DE COTAÇÃO: aprendizado dos itens/aspectos a cotar por serviço (ex.: GRUA → locação,
    // frete montagem/desmontagem, combustível, alimentação, hora parada). Ao iniciar cotação do radar,
    // puxa estes itens (editáveis). Por servico_id (catálogo, reusado entre obras).
    $pdo->exec("CREATE TABLE IF NOT EXISTS cot_dicionario (
        id INT NOT NULL AUTO_INCREMENT, servico_id INT NOT NULL, descricao TEXT, unidade VARCHAR(40),
        ordem INT, nota TEXT, created_at VARCHAR(40), PRIMARY KEY (id), KEY idx_cotdic_sv (servico_id)
    ) $E");
    // FORNECEDORES CONVIDADOS p/ a concorrência de uma cotação. Rastreia quem foi convidado e (via proposta)
    // quem respondeu. status derivado: respondeu se houver proposta com o mesmo fornecedor.
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_fornecedor (
        id INT NOT NULL AUTO_INCREMENT, cotacao_id INT NOT NULL, fornecedor_id INT, fornecedor_nome VARCHAR(255),
        categoria VARCHAR(191), contato VARCHAR(191), email VARCHAR(191), telefone VARCHAR(60),
        created_at VARCHAR(40), PRIMARY KEY (id), KEY idx_cotf_cot (cotacao_id)
    ) $E");
    // colunas ADITIVAS na produção (radar_item já existe da migração; CREATE IF NOT EXISTS não adiciona coluna).
    // Usa ALTER (privilégio concedido) só se faltar. Espelha o self-heal do caminho SQLite.
    $rc = [];
    foreach ($pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='radar_item'") as $c) $rc[$c['COLUMN_NAME']] = true;
    if (!isset($rc['orcamento_excl'])) $pdo->exec("ALTER TABLE radar_item ADD COLUMN orcamento_excl MEDIUMTEXT");
    // auto_flags (JSON {crono:1,verba:1,quant:1}): dimensões preenchidas pelo AUTO-VÍNCULO (receitas) e ainda
    // NÃO confirmadas por humano — o item_update limpa a flag da dimensão quando alguém salva aquela aba.
    if (!isset($rc['auto_flags'])) $pdo->exec("ALTER TABLE radar_item ADD COLUMN auto_flags MEDIUMTEXT");
    // multi-obra: método construtivo por obra (receitas não podem cruzar métodos às cegas — ex.: bloco de
    // vedação não existe em alvenaria estrutural)
    $oc = [];
    foreach ($pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='obra'") as $c) $oc[$c['COLUMN_NAME']] = true;
    if (!isset($oc['metodo_construtivo'])) $pdo->exec("ALTER TABLE obra ADD COLUMN metodo_construtivo VARCHAR(64) DEFAULT 'concreto armado convencional'");
    // permissão granular perm_responsaveis (atribuição de responsável EM LOTE) — self-heal p/ tabela usuario já existente
    $uc = [];
    foreach ($pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuario'") as $c) $uc[$c['COLUMN_NAME']] = true;
    foreach (['perm_crono','perm_orcamento','perm_quant','perm_dicionario','perm_responsaveis'] as $pc)
        if (!isset($uc[$pc])) $pdo->exec("ALTER TABLE usuario ADD COLUMN $pc INT DEFAULT 0");
    // ALARGA as colunas de JSON de seleção: uma busca EM MASSA por insumo (ex.: "encanador" em N composições × M
    // locais) gera composicao_sel > 64KB e o MySQL TRUNCA a coluna TEXT (max 65.535 bytes) SILENCIOSAMENTE →
    // JSON inválido → a seleção "some" (some do read-only) embora a verba/total já tenham sido calculados.
    // MEDIUMTEXT (16MB) elimina o teto. ALTER MODIFY usa o privilégio ALTER (concedido). Só mexe se ainda for 'text'.
    $dt = [];
    foreach ($pdo->query("SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='radar_item'") as $c) $dt[$c['COLUMN_NAME']] = strtolower($c['DATA_TYPE']);
    foreach (['composicao_sel','quant_comp_sel','orcamento_refs','quantitativo_refs','orcamento_excl'] as $col) {
        if (isset($dt[$col]) && $dt[$col] === 'text') { try { $pdo->exec("ALTER TABLE radar_item MODIFY `$col` MEDIUMTEXT"); } catch (Throwable $e) {} }
    }
    // equalização (pontos a conferir por proposta): cotacao.equalizacao (lista, texto) + cotacao_proposta.equaliza (JSON ponto->valor)
    try {
        $cc = []; foreach ($pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cotacao'") as $c) $cc[$c['COLUMN_NAME']] = true;
        if ($cc && !isset($cc['equalizacao'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN equalizacao TEXT");
        if ($cc && !isset($cc['verba_origem'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN verba_origem VARCHAR(40)");
        $pc = []; foreach ($pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cotacao_proposta'") as $c) $pc[$c['COLUMN_NAME']] = true;
        if ($pc && !isset($pc['equaliza'])) $pdo->exec("ALTER TABLE cotacao_proposta ADD COLUMN equaliza TEXT");
    } catch (Throwable $e) {}
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
    // colunas ADITIVAS (não dropam radar_item):
    $rcols = [];
    foreach ($pdo->query("PRAGMA table_info(radar_item)") as $c) $rcols[$c['name']] = true;
    if (!isset($rcols['composicao_sel'])) $pdo->exec("ALTER TABLE radar_item ADD COLUMN composicao_sel TEXT");
    // verba_curada (0/1): a verba só é "curada" quando alguém altera+confirma. Default 0 => reseta a curada de TODAS.
    if (!isset($rcols['verba_curada'])) $pdo->exec("ALTER TABLE radar_item ADD COLUMN verba_curada INTEGER DEFAULT 0");
    // cesta de insumos do QUANTITATIVO por composição (independente da verba) — coluna aditiva
    if (!isset($rcols['quant_comp_sel'])) $pdo->exec("ALTER TABLE radar_item ADD COLUMN quant_comp_sel TEXT");
    // quant_curada (0/1): igual à verba — só "curado" quando alguém altera+confirma. Default 0 => reseta todos.
    if (!isset($rcols['quant_curada'])) $pdo->exec("ALTER TABLE radar_item ADD COLUMN quant_curada INTEGER DEFAULT 0");
    // orcamento_excl (JSON): insumos EXCLUÍDOS de dentro de linhas analíticas selecionadas (ex.: tirar o
    // espaçador de uma linha) — lista [{l:lineId, d:descrição}]. Verba = Σ linhas − Σ insumos excluídos.
    if (!isset($rcols['orcamento_excl'])) $pdo->exec("ALTER TABLE radar_item ADD COLUMN orcamento_excl TEXT");
    // auto_flags: dimensões sugeridas pelo auto-vínculo, pendentes de confirmação humana
    if (!isset($rcols['auto_flags'])) $pdo->exec("ALTER TABLE radar_item ADD COLUMN auto_flags TEXT");
    // DICIONÁRIO DE APRENDIZADO (espelha o MySQL): receita por serviço × método construtivo, por NOME (nunca ID)
    $pdo->exec("CREATE TABLE IF NOT EXISTS receita (
        servico_id INTEGER NOT NULL,
        metodo_construtivo TEXT NOT NULL DEFAULT 'concreto armado convencional',
        obra_origem TEXT, crono TEXT, verba TEXT, quant TEXT, nota TEXT, updated_at TEXT,
        PRIMARY KEY (servico_id, metodo_construtivo)
    )");
    // multi-obra: método construtivo por obra
    $ocols = [];
    foreach ($pdo->query("PRAGMA table_info(obra)") as $c) $ocols[$c['name']] = true;
    if (!isset($ocols['metodo_construtivo'])) $pdo->exec("ALTER TABLE obra ADD COLUMN metodo_construtivo TEXT DEFAULT 'concreto armado convencional'");
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
    // histórico de alterações — NÃO entra no drop de migração (persiste entre versões)
    $pdo->exec("CREATE TABLE IF NOT EXISTS historico (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        obra_id INTEGER, servico_id INTEGER, item_nome TEXT,
        bitrix_id TEXT, usuario_nome TEXT,
        campo TEXT, valor_antes TEXT, valor_depois TEXT, created_at TEXT
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_hist_serv ON historico(servico_id)");

    // ---- MAPA DE COTAÇÕES (reconstruído no cockpit) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS cot_categoria (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL UNIQUE, ext_id TEXT, created_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cot_fornecedor (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, categoria TEXT, cidade TEXT, contato TEXT, telefone TEXT, whatsapp TEXT, email TEXT, itens TEXT, tipo TEXT, cnpj TEXT, ativo INTEGER DEFAULT 1, ext_id TEXT, created_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao (id INTEGER PRIMARY KEY AUTOINCREMENT, obra_id INTEGER, servico_id INTEGER, titulo TEXT NOT NULL, categoria TEXT, tipo_servico TEXT, verba REAL, verba_origem TEXT, descricao TEXT, equalizacao TEXT, status TEXT DEFAULT 'rascunho', aprovacao TEXT DEFAULT 'aguardando', criado_por TEXT, criado_nome TEXT, created_at TEXT, updated_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_item (id INTEGER PRIMARY KEY AUTOINCREMENT, cotacao_id INTEGER NOT NULL, descricao TEXT, unidade TEXT, quantidade REAL, observacao TEXT, ordem INTEGER)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_proposta (id INTEGER PRIMARY KEY AUTOINCREMENT, cotacao_id INTEGER NOT NULL, fornecedor_id INTEGER, fornecedor_nome TEXT, prazo TEXT, observacoes TEXT, equaliza TEXT, data_resposta TEXT, total REAL, created_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_proposta_item (id INTEGER PRIMARY KEY AUTOINCREMENT, proposta_id INTEGER NOT NULL, cotacao_item_id INTEGER NOT NULL, preco_unit REAL, preco_total REAL, observacao TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_anexo (id INTEGER PRIMARY KEY AUTOINCREMENT, cotacao_id INTEGER NOT NULL, proposta_id INTEGER, nome TEXT, arquivo TEXT, tamanho INTEGER, mime TEXT, criado_por TEXT, created_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cot_dicionario (id INTEGER PRIMARY KEY AUTOINCREMENT, servico_id INTEGER NOT NULL, descricao TEXT, unidade TEXT, ordem INTEGER, nota TEXT, created_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_fornecedor (id INTEGER PRIMARY KEY AUTOINCREMENT, cotacao_id INTEGER NOT NULL, fornecedor_id INTEGER, fornecedor_nome TEXT, categoria TEXT, contato TEXT, email TEXT, telefone TEXT, created_at TEXT)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_coti_cot ON cotacao_item(cotacao_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_prop_cot ON cotacao_proposta(cotacao_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_propi_prop ON cotacao_proposta_item(proposta_id)");
    // equalização (self-heal p/ bancos SQLite já criados sem as colunas)
    $ccols = []; foreach ($pdo->query("PRAGMA table_info(cotacao)") as $c) $ccols[$c['name']] = true;
    if (!isset($ccols['equalizacao'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN equalizacao TEXT");
    if (!isset($ccols['verba_origem'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN verba_origem TEXT");
    $pcols = []; foreach ($pdo->query("PRAGMA table_info(cotacao_proposta)") as $c) $pcols[$c['name']] = true;
    if (!isset($pcols['equaliza'])) $pdo->exec("ALTER TABLE cotacao_proposta ADD COLUMN equaliza TEXT");

    // permissões GRANULARES de edição (além do editar_escopo geral) — aditivas, fora do drop de migração.
    // perm_admin = tudo. editar_escopo (todas/sel) = editor geral (status/fornecedor/observação).
    // estas liberam capacidades específicas POR USUÁRIO:
    $ucols = [];
    foreach ($pdo->query("PRAGMA table_info(usuario)") as $c) $ucols[$c['name']] = true;
    foreach (['perm_crono','perm_orcamento','perm_quant','perm_dicionario','perm_responsaveis'] as $pc) {
        if (!isset($ucols[$pc])) $pdo->exec("ALTER TABLE usuario ADD COLUMN $pc INTEGER DEFAULT 0");
    }

    // data-fix one-shot: compradores nasciam com editar_escopo='sel' + obras_editar vazio => 403 em tudo
    // (regressão do enforcement). Destrava quem é da equipe de Suprimentos pra editar.
    $df = $pdo->query("SELECT v FROM meta WHERE k='datafix_comprador_edit_v1'")->fetch();
    if (!$df) {
        $pdo->exec("UPDATE usuario SET editar_escopo='todas'
                    WHERE papel='comprador' AND editar_escopo='sel' AND COALESCE(obras_editar,'') IN ('','[]')");
        $pdo->prepare("INSERT OR REPLACE INTO meta (k,v) VALUES ('datafix_comprador_edit_v1','1')")->execute();
    }

    // data-fix one-shot: lead PADRÃO 60 dias p/ todos (regra geral nova). Limpa qualquer lead_override
    // pré-existente => todos os itens passam a usar o default 60 (ajuste por item vem depois, caso a caso).
    $dfl = $pdo->query("SELECT v FROM meta WHERE k='datafix_lead60_v1'")->fetch();
    if (!$dfl) {
        $pdo->exec("UPDATE radar_item SET lead_override=NULL");
        $pdo->prepare("INSERT OR REPLACE INTO meta (k,v) VALUES ('datafix_lead60_v1','1')")->execute();
    }

    // data-fix: reclassificação CURADA dos insumos (4 classes: material/mo/mat_mo/equip), por descrição.
    // A heurística do import classificava serviço/equipamento como "material" — isso quebrava verba/auditoria.
    // Guarda o tipo original em composicao_insumo.tipo_orig (reversível). Só marca como feito se havia insumos
    // (resiliente a deploy parcial e a banco recém-criado antes do seed).
    $dfr = $pdo->query("SELECT v FROM meta WHERE k='datafix_reclass_v1'")->fetch();
    if (!$dfr) {
        $cic = []; foreach ($pdo->query("PRAGMA table_info(composicao_insumo)") as $c) $cic[$c['name']] = true;
        if (!isset($cic['tipo_orig'])) $pdo->exec("ALTER TABLE composicao_insumo ADD COLUMN tipo_orig TEXT");
        $pdo->exec("UPDATE composicao_insumo SET tipo_orig=tipo WHERE tipo_orig IS NULL");
        $map = json_decode(@file_get_contents(SEED_DIR . '/reclass_tipos.json'), true);
        if (is_array($map) && $map) {
            $up = $pdo->prepare("UPDATE composicao_insumo SET tipo=? WHERE descricao=?");
            $pdo->beginTransaction();
            foreach ($map as $desc => $tipo) $up->execute([$tipo, $desc]);
            $pdo->commit();
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM composicao_insumo")->fetchColumn();
            if ($cnt > 0) $pdo->prepare("INSERT OR REPLACE INTO meta (k,v) VALUES ('datafix_reclass_v1','1')")->execute();
        }
    }

    // data-fix R06: reimporta o orçamento (valores) + composições (insumos já separados material/MO) do novo
    // ORÇ_TRINITY_R06, PRESERVANDO a curadoria. A estrutura é idêntica ao R05 (mesmas 2836 folhas/702 comps na
    // mesma ordem) → os IDs batem, então orcamento_refs/composicao_sel continuam válidos. Recarrega SÓ as 3
    // tabelas-base (não toca radar_item/usuario/historico). Transacional + try/catch: se falhar, rollback e tenta de novo.
    $dfr06 = $pdo->query("SELECT v FROM meta WHERE k='datafix_r06_v1'")->fetch();
    if (!$dfr06) {
        $orc  = json_decode(@file_get_contents(SEED_DIR . '/orcamento_trinity.json'), true);
        $comp = json_decode(@file_get_contents(SEED_DIR . '/composicao_trinity.json'), true);
        if (is_array($orc) && !empty($orc['linhas']) && is_array($comp) && !empty($comp['composicoes'])) {
            try {
                $cic2 = []; foreach ($pdo->query("PRAGMA table_info(composicao_insumo)") as $c) $cic2[$c['name']] = true;
                if (!isset($cic2['tipo_orig'])) $pdo->exec("ALTER TABLE composicao_insumo ADD COLUMN tipo_orig TEXT");
                $pdo->beginTransaction();
                $pdo->exec("DELETE FROM orcamento_linha");
                $ol = $pdo->prepare("INSERT INTO orcamento_linha (id,obra_id,codigo,parent,depth,nivel,descricao,path_str,unidade,qtde,valor,folha) VALUES (?,1,?,?,?,?,?,?,?,?,?,?)");
                foreach ($orc['linhas'] as $l)
                    $ol->execute([$l['id'],$l['codigo'],$l['parent'],$l['depth'],$l['nivel'],$l['descricao'],$l['path_str'],$l['unidade'],$l['qtde'],$l['valor'],$l['folha']]);
                $pdo->exec("DELETE FROM composicao");
                $pdo->exec("DELETE FROM composicao_insumo");
                $cc = $pdo->prepare("INSERT INTO composicao (id,obra_id,descricao,unidade,qtde_total,rs_unit,rs_total) VALUES (?,1,?,?,?,?,?)");
                $ci = $pdo->prepare("INSERT INTO composicao_insumo (composicao_id,descricao,unidade,coef,rs_unit,rs_total,tipo,tipo_orig) VALUES (?,?,?,?,?,?,?,?)");
                foreach ($comp['composicoes'] as $co) {
                    $cc->execute([$co['id'],$co['descricao'],$co['unidade'],$co['qtde_total'],$co['rs_unit'],$co['rs_total']]);
                    foreach ($co['insumos'] as $in)
                        $ci->execute([$co['id'],$in['descricao'],$in['unidade'],$in['coef'],$in['rs_unit'],$in['rs_total'],$in['tipo'],$in['tipo']]);
                }
                $pdo->commit();
                $pdo->prepare("INSERT OR REPLACE INTO meta (k,v) VALUES ('datafix_r06_v1','1')")->execute();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();   // não seta a flag → tenta de novo na próxima carga
            }
        }
    }
}

/** Permissões efetivas de um usuário p/ enforcement NO SERVIDOR. Não cadastrado/sem id => nega. */
function user_perms($pdo, $bid) {
    $deny = ['autorizado'=>false,'perm_admin'=>0,'nome'=>'','editar_escopo'=>'nenhuma','obras_editar'=>[]];
    $bid = trim((string)($bid ?? ''));               // BX24 às vezes manda id com espaço/quebra invisível
    if ($bid === '') return $deny;
    // compara já com TRIM dos dois lados (resiliente a id salvo/recebido com espaço)
    $st = $pdo->prepare("SELECT * FROM usuario WHERE TRIM(bitrix_id)=? AND ativo=1");
    $st->execute([$bid]);
    $u = $st->fetch();
    if (!$u) return $deny;
    return ['autorizado'=>true,'perm_admin'=>(int)$u['perm_admin'],'nome'=>$u['nome'] ?? '',
            'editar_escopo'=>$u['editar_escopo'] ?? 'nenhuma',
            'obras_editar'=>$u['obras_editar'] ? (json_decode($u['obras_editar'], true) ?: []) : [],
            'perm_crono'=>(int)($u['perm_crono'] ?? 0),
            'perm_orcamento'=>(int)($u['perm_orcamento'] ?? 0),
            'perm_quant'=>(int)($u['perm_quant'] ?? 0),
            'perm_dicionario'=>(int)($u['perm_dicionario'] ?? 0),
            'perm_responsaveis'=>(int)($u['perm_responsaveis'] ?? 0)];
}
function can_edit_obra($perms, $obra_id) {
    if (!empty($perms['perm_admin'])) return true;
    if (($perms['editar_escopo'] ?? '') === 'todas') return true;
    if (($perms['editar_escopo'] ?? '') === 'sel'
        && in_array((int)$obra_id, array_map('intval', $perms['obras_editar'] ?? []), true)) return true;
    return false;
}
// NB: o enforcement por campo (mapa campo->grupo + checagem) é INLINE no actions/item_update.php,
// pra ser resiliente a deploy parcial (não depende deste arquivo chegar atualizado no FTP).
function log_historico($pdo, $obra_id, $servico_id, $item_nome, $bid, $nome, $campo, $antes, $depois) {
    $pdo->prepare("INSERT INTO historico
        (obra_id,servico_id,item_nome,bitrix_id,usuario_nome,campo,valor_antes,valor_depois,created_at)
        VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$obra_id,$servico_id,$item_nome,(string)$bid,$nome ?: ('Usuário '.$bid),$campo,
                   $antes===null?null:(string)$antes, $depois===null?null:(string)$depois, date('c')]);
}

function _slugify($s){
    $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE', $s);
    $s = strtolower(preg_replace('/[^a-zA-Z0-9]+/','-', $s));
    return trim($s,'-');
}

/** Cria um novo item (servico catálogo + radar_item da obra 1). Copia dicionário de copy_from se dado. Retorna ordem. */
/** CANÔNICOS de normalização/classificação — usados pela busca em massa E pela derivação de receitas.
 *  Mantenha AQUI a única implementação; os consumidores têm fallback guarded (resiliência a deploy parcial). */
function sup_normt($s) {
    $map = ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c',
            'Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','É'=>'e','Ê'=>'e','Í'=>'i','Ó'=>'o','Ô'=>'o','Õ'=>'o','Ú'=>'u','Ç'=>'c'];
    return strtolower(strtr((string)$s, $map));
}
function sup_sistema($p) { // path normalizado → subsistema. PALAVRA INTEIRA nas ambíguas ("gas" ≠ "vigas", "fria" idem)
    $w = function($re) use ($p){ return preg_match('#\b'.$re.'\b#', $p) === 1; };
    if ($w('gas'))                                      return 'Gás';
    if (strpos($p,'quente') !== false)                  return 'Água Quente';
    if (strpos($p,'agua fria') !== false || $w('fria')) return 'Água Fria';
    if (strpos($p,'esgoto') !== false || strpos($p,'sanit') !== false) return 'Esgoto / Sanitário';
    if (strpos($p,'pluvia') !== false)                  return 'Águas Pluviais';
    if (strpos($p,'incendio') !== false)                return 'Incêndio';
    if (strpos($p,'hidr') !== false)                    return 'Hidráulica (geral)';
    return null;
}

function sup_mo_guarda($desc) {
    // true se a descrição é PESSOAL de guarda/portaria (mão de obra, não material).
    // Só termos de PESSOAL inequívocos — de propósito NÃO usa "portaria"/"vigia"/"segurança"
    // soltos (portaria/vigia podem ser construção/janela; segurança sozinho pegaria EPI).
    $d = sup_normt($desc);
    foreach (['vigilante','porteiro','seguranca patrimonial','guarda patrimonial','vigia noturno'] as $t)
        if (strpos($d, $t) !== false) return true;
    return false;
}

function criar_item($pdo, $nome, $grupo, $tipo = '', $curva = '', $copy_from = null) {
    $nome = trim($nome);
    if ($nome === '') throw new Exception('nome obrigatório');
    $nid = (int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM servico")->fetchColumn();
    $maxo = (int)$pdo->query("SELECT COALESCE(MAX(ordem),0) FROM servico")->fetchColumn();
    $nid = max($nid, $maxo) + 1; // mantém id == ordem (invariante usada no item_update)

    $go = $pdo->prepare("SELECT grupo_ordem FROM servico WHERE grupo=? LIMIT 1");
    $go->execute([$grupo]);
    $grupo_ordem = $go->fetchColumn();
    if ($grupo_ordem === false || $grupo_ordem === null)
        $grupo_ordem = (int)$pdo->query("SELECT COALESCE(MAX(grupo_ordem),0)+1 FROM servico")->fetchColumn();

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
    // serviço é CATÁLOGO (global) → cria a célula do radar em TODAS as obras existentes;
    // já nasce com o RESPONSÁVEL PADRÃO do serviço (regra padrão — a célula herda)
    $rpad = trim((string)$g('responsavel_padrao'));
    $ins = $pdo->prepare("INSERT INTO radar_item (obra_id,servico_id,status,responsavel,tipo,updated_at) VALUES (?,?,?,?,?,?)");
    foreach ($pdo->query("SELECT id FROM obra ORDER BY id")->fetchAll() as $ob)
        $ins->execute([(int)$ob['id'],$nid,'Não Iniciado',$rpad!==''?$rpad:null,$tipo,date('c')]);
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
