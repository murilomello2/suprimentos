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
        itens TEXT, tipo VARCHAR(60), cnpj VARCHAR(40), contatos_at TEXT, ativo INT DEFAULT 1, ext_id VARCHAR(64), created_at VARCHAR(40),
        PRIMARY KEY (id), KEY idx_forn_cat (categoria)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao (
        id INT NOT NULL AUTO_INCREMENT, obra_id INT, servico_id INT, titulo VARCHAR(255) NOT NULL,
        categoria VARCHAR(191), tipo_servico VARCHAR(60), verba DOUBLE, verba_origem VARCHAR(40), descricao TEXT, equalizacao TEXT,
        num_solicitacao VARCHAR(60), num_pedido VARCHAR(60),
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
        id INT NOT NULL AUTO_INCREMENT, cotacao_id INT NOT NULL, proposta_id INT, fornecedor_id INT, fornecedor_nome VARCHAR(191), nome VARCHAR(255),
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
        enviado_em VARCHAR(40), enviado_canal VARCHAR(20), enviado_por VARCHAR(64),
        created_at VARCHAR(40), PRIMARY KEY (id), KEY idx_cotf_cot (cotacao_id)
    ) $E");
    // Radar IA — uso diário por usuário (limite de perguntas/dia editável no admin)
    $pdo->exec("CREATE TABLE IF NOT EXISTS oracle_uso (
        bitrix_id VARCHAR(64) NOT NULL, dia VARCHAR(10) NOT NULL, n INT DEFAULT 0, PRIMARY KEY (bitrix_id, dia)
    ) $E");
    // E-MAIL FASE 4 — enviados (guarda o Message-ID/token por convidado p/ casar EXATO a resposta via In-Reply-To/References)
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_email_out (
        id INT NOT NULL AUTO_INCREMENT, cotacao_id INT NOT NULL, cotacao_fornecedor_id INT, fornecedor_id INT,
        fornecedor_nome VARCHAR(255), email VARCHAR(191), message_id VARCHAR(191), token VARCHAR(64),
        assunto VARCHAR(255), enviado_em VARCHAR(40),
        PRIMARY KEY (id), KEY idx_ceo_cot (cotacao_id), KEY idx_ceo_tok (token)
    ) $E");
    // E-MAIL FASE 4 — recebidos (log inbound; casado ao par cotação×fornecedor; classificação da IA; rascunho). MEDIUMTEXT p/ JSON (lição do TEXT 64KB)
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_email_in (
        id INT NOT NULL AUTO_INCREMENT, cotacao_id INT, cotacao_fornecedor_id INT, fornecedor_id INT, fornecedor_nome VARCHAR(255),
        imap_uid BIGINT, uidvalidity BIGINT, dedup_key VARCHAR(191) NOT NULL,
        message_id VARCHAR(191), in_reply_to VARCHAR(191),
        from_email VARCHAR(191), from_nome VARCHAR(255), assunto VARCHAR(255), data_email VARCHAR(40),
        match_metodo VARCHAR(20), match_confianca VARCHAR(10),
        tipo VARCHAR(20) DEFAULT 'indefinido', resumo VARCHAR(600), tem_proposta TINYINT DEFAULT 0, precisa_humano TINYINT DEFAULT 1,
        ia_confianca VARCHAR(10), tem_anexo TINYINT DEFAULT 0, ia_modelo VARCHAR(60), anexos_ids VARCHAR(191),
        draft_json MEDIUMTEXT, corpo_preview MEDIUMTEXT,
        status VARCHAR(20) DEFAULT 'novo', lido_por VARCHAR(64), lido_em VARCHAR(40), created_at VARCHAR(40),
        PRIMARY KEY (id), UNIQUE KEY uq_in_dedup (dedup_key), KEY idx_in_cot (cotacao_id), KEY idx_in_status (status)
    ) $E");
    // Cartas convite — MODELO por serviço (camada 🔧) + CONFIG global Caprem (camada 🔒, 1 linha id=1)
    $pdo->exec("CREATE TABLE IF NOT EXISTS carta_modelo (
        id INT NOT NULL AUTO_INCREMENT, servico_id INT, servico_nome VARCHAR(191), tipo VARCHAR(60),
        objeto TEXT, norma_referencia TEXT, pes_ref VARCHAR(191),
        escopo MEDIUMTEXT, criterios_medicao MEDIUMTEXT, equalizacao_campos MEDIUMTEXT, quantitativos_modelo MEDIUMTEXT,
        observacoes TEXT, versao INT DEFAULT 1, is_padrao TINYINT DEFAULT 1, origem VARCHAR(40) DEFAULT 'seed',
        criado_por VARCHAR(64), criado_nome VARCHAR(191), created_at VARCHAR(40), updated_at VARCHAR(40),
        PRIMARY KEY (id), KEY idx_cmod_serv (servico_id)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS carta_config (
        id INT NOT NULL, bloco_json MEDIUMTEXT, updated_at VARCHAR(40), PRIMARY KEY (id)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS carta_gerada (
        id INT NOT NULL AUTO_INCREMENT, cotacao_id INT NOT NULL, servico_nome VARCHAR(191), titulo VARCHAR(255),
        html MEDIUMTEXT, criado_por VARCHAR(64), criado_nome VARCHAR(191), created_at VARCHAR(40),
        PRIMARY KEY (id), KEY idx_cg_cot (cotacao_id)
    ) $E");
    // PREÇOS TABELADOS — item canônico (dedup) + tabela (contrato do fornecedor) + itens da tabela
    $pdo->exec("CREATE TABLE IF NOT EXISTS preco_insumo (
        id INT NOT NULL AUTO_INCREMENT, nome VARCHAR(255) NOT NULL, unidade VARCHAR(40), sinonimos MEDIUMTEXT,
        servico_id INT, categoria VARCHAR(191), created_at VARCHAR(40), updated_at VARCHAR(40),
        PRIMARY KEY (id), KEY idx_pins_serv (servico_id)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS preco_tabela (
        id INT NOT NULL AUTO_INCREMENT, fornecedor_id INT, fornecedor_nome VARCHAR(255), titulo VARCHAR(255),
        validade_inicio VARCHAR(20), validade_fim VARCHAR(20), observacao TEXT, anexo_id INT,
        criado_por VARCHAR(64), criado_nome VARCHAR(191), created_at VARCHAR(40), updated_at VARCHAR(40),
        PRIMARY KEY (id), KEY idx_ptab_forn (fornecedor_id)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS preco_item (
        id INT NOT NULL AUTO_INCREMENT, tabela_id INT NOT NULL, insumo_id INT, descricao_original VARCHAR(255),
        unidade VARCHAR(40), preco DOUBLE, frete_incluso TINYINT DEFAULT 0, observacao TEXT, created_at VARCHAR(40),
        PRIMARY KEY (id), KEY idx_pitem_tab (tabela_id), KEY idx_pitem_ins (insumo_id)
    ) $E");
    // SOLICITAÇÕES DE COMPRA — de-para (coligada+centro de custo → nome+comprador+obra radar) + overlay do comprador
    $pdo->exec("CREATE TABLE IF NOT EXISTS solic_obra (
        id INT NOT NULL AUTO_INCREMENT, coligada VARCHAR(255), obra_cod VARCHAR(20), nome_comercial VARCHAR(191),
        cnpj VARCHAR(24), endereco VARCHAR(255), comprador_id VARCHAR(64), comprador_nome VARCHAR(191), radar_obra_id INT, created_at VARCHAR(40), updated_at VARCHAR(40),
        PRIMARY KEY (id), KEY idx_sobra_col (obra_cod)
    ) $E");
    // FICHA DAS OBRAS (módulo Obras) — de-para entre sistemas (conector/radar/TOTVS/solicitações) + características curadas
    $pdo->exec("CREATE TABLE IF NOT EXISTS obra_ficha (
        id INT NOT NULL AUTO_INCREMENT, slug VARCHAR(191), nome VARCHAR(255) NOT NULL, cidade VARCHAR(120), estado VARCHAR(8), status VARCHAR(60),
        conector_obra_id VARCHAR(64), radar_obra_id INT,
        coligada_cod INT, coligada_nome VARCHAR(255), cnpj VARCHAR(24),
        solic_nome VARCHAR(191), solic_coligada VARCHAR(255), solic_obra_cod VARCHAR(20), endereco VARCHAR(255), comprador_nome VARCHAR(191),
        torres INT, pavimentos INT, subsolos INT, unidades INT,
        tipologias TEXT, metodo_construtivo TEXT, areas_comuns TEXT, padrao VARCHAR(120),
        observacoes TEXT, link_cronograma VARCHAR(500), link_projetos VARCHAR(500), link_local VARCHAR(500),
        de_para_ok INT DEFAULT 0, created_at VARCHAR(40), updated_at VARCHAR(40), updated_by VARCHAR(64),
        PRIMARY KEY (id), UNIQUE KEY uq_obraf_slug (slug)
    ) $E");
    $pdo->exec("CREATE TABLE IF NOT EXISTS solic_overlay (
        id INT NOT NULL AUTO_INCREMENT, coligada VARCHAR(255), numero VARCHAR(40), status VARCHAR(40),
        observacoes TEXT, fornecedores TEXT, orcamento_recebido TINYINT DEFAULT 0, cotacao_id INT,
        updated_by VARCHAR(64), updated_at VARCHAR(40), PRIMARY KEY (id), KEY idx_sov_num (numero)
    ) $E");
    // TOP 20 — grupos de NEGOCIAÇÃO (aço/concreto/blocos/argamassas…): conjuntos de serviços do catálogo
    // p/ consolidar volume × 12 meses (curva de demanda Caprem inteira p/ negociar com fornecedor)
    $pdo->exec("CREATE TABLE IF NOT EXISTS neg_grupo (
        id INT NOT NULL AUTO_INCREMENT, nome VARCHAR(160), ordem INT DEFAULT 0,
        servicos MEDIUMTEXT, nota TEXT, updated_by VARCHAR(64), updated_at VARCHAR(40),
        PRIMARY KEY (id)
    ) $E");
    // FASE 2 — MULTI-PC POR COLIGADA: uma cotação multi-obra/multi-SC atravessa várias coligadas, e cada
    // coligada tem um Nº de Pedido de Compra DIFERENTE (o nº de PC não é único entre coligadas). Uma linha por
    // (cotacao_id × coligada). O PC é read-only do TOTVS — aqui guardamos só o NÚMERO confirmado/auto-detectado.
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_pedido (
        id INT NOT NULL AUTO_INCREMENT, cotacao_id INT NOT NULL, coligada VARCHAR(255), coligada_cod INT,
        colidmov VARCHAR(40), num_pedido VARCHAR(60), status VARCHAR(40), updated_by VARCHAR(64), updated_at VARCHAR(40),
        PRIMARY KEY (id), KEY idx_cotped_cot (cotacao_id)
    ) $E");
    // colunas ADITIVAS na produção (radar_item já existe da migração; CREATE IF NOT EXISTS não adiciona coluna).
    // Usa ALTER (privilégio concedido) só se faltar. Espelha o self-heal do caminho SQLite.
    $rc = [];
    foreach ($pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='radar_item'") as $c) $rc[$c['COLUMN_NAME']] = true;
    if (!isset($rc['orcamento_excl'])) $pdo->exec("ALTER TABLE radar_item ADD COLUMN orcamento_excl MEDIUMTEXT");
    // auto_flags (JSON {crono:1,verba:1,quant:1}): dimensões preenchidas pelo AUTO-VÍNCULO (receitas) e ainda
    // NÃO confirmadas por humano — o item_update limpa a flag da dimensão quando alguém salva aquela aba.
    if (!isset($rc['auto_flags'])) $pdo->exec("ALTER TABLE radar_item ADD COLUMN auto_flags MEDIUMTEXT");
    // OVERRIDE POR-OBRA de nomenclatura/estrutura: nome/grupo do item são do CATÁLOGO (servico, global). Editar
    // durante a curadoria de UMA obra não pode reescrever as outras (a base de concreto ≠ a base de alvenaria).
    // Esses overrides ficam na CÉLULA (radar_item) e a matriz usa COALESCE(override, base). NULL = herda a base.
    if (!isset($rc['nome_override']))        $pdo->exec("ALTER TABLE radar_item ADD COLUMN nome_override VARCHAR(255)");
    if (!isset($rc['grupo_override']))       $pdo->exec("ALTER TABLE radar_item ADD COLUMN grupo_override VARCHAR(120)");
    if (!isset($rc['grupo_ordem_override'])) $pdo->exec("ALTER TABLE radar_item ADD COLUMN grupo_ordem_override INT");
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
        if ($cc && !isset($cc['num_solicitacao'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN num_solicitacao VARCHAR(60)");
        if ($cc && !isset($cc['num_pedido'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN num_pedido VARCHAR(60)");
        // origem solicitação: guarda coligada+centro de custo p/ a carta de cotação (material) resolver CNPJ/comprador via solic_obra
        if ($cc && !isset($cc['solic_coligada'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN solic_coligada VARCHAR(255)");
        if ($cc && !isset($cc['solic_obra_cod'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN solic_obra_cod VARCHAR(20)");
        if ($cc && !isset($cc['solic_colidmov'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN solic_colidmov VARCHAR(40)");   // colidmov da SC (embute a coligada) p/ casar o PC certo
        if ($cc && !isset($cc['import_origem'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN import_origem VARCHAR(80)");     // id da cotação no sistema antigo (Mapa de Cotações) — dedup do import
        if ($cc && !isset($cc['obra_livre'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN obra_livre VARCHAR(191)");         // nome da obra em texto (quando não há obra_id do radar — ex.: importadas)
        // cotacao_item MULTI-OBRA: cada item pode ser de uma obra/solicitação diferente (cotação juntando várias SCs)
        $cti = []; foreach ($pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cotacao_item'") as $c) $cti[$c['COLUMN_NAME']] = true;
        if ($cti && !isset($cti['obra_id'])) $pdo->exec("ALTER TABLE cotacao_item ADD COLUMN obra_id INT");
        if ($cti && !isset($cti['solic_coligada'])) $pdo->exec("ALTER TABLE cotacao_item ADD COLUMN solic_coligada VARCHAR(255)");
        if ($cti && !isset($cti['solic_numero'])) $pdo->exec("ALTER TABLE cotacao_item ADD COLUMN solic_numero VARCHAR(40)");
        if ($cti && !isset($cti['solic_colidmov'])) $pdo->exec("ALTER TABLE cotacao_item ADD COLUMN solic_colidmov VARCHAR(40)");
        // FASE 2 — casamento EXATO item-da-SC ↔ cotacao_item (p/ a cor de cobertura por item na lista de solicitações)
        if ($cti && !isset($cti['solic_seq']))    $pdo->exec("ALTER TABLE cotacao_item ADD COLUMN solic_seq INT");
        if ($cti && !isset($cti['solic_codprd'])) $pdo->exec("ALTER TABLE cotacao_item ADD COLUMN solic_codprd VARCHAR(60)");
        // ficha da obra: snapshot do cronograma (% físico + datas) — a tabela obra_cronogramas tem RLS, o app não lê direto
        $ofc = []; foreach ($pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='obra_ficha'") as $c) $ofc[$c['COLUMN_NAME']] = true;
        if ($ofc && !isset($ofc['pct_fisico'])) $pdo->exec("ALTER TABLE obra_ficha ADD COLUMN pct_fisico DOUBLE");
        if ($ofc && !isset($ofc['crono_inicio'])) $pdo->exec("ALTER TABLE obra_ficha ADD COLUMN crono_inicio VARCHAR(20)");
        if ($ofc && !isset($ofc['crono_fim'])) $pdo->exec("ALTER TABLE obra_ficha ADD COLUMN crono_fim VARCHAR(20)");
        if ($ofc && !isset($ofc['crono_medicao'])) $pdo->exec("ALTER TABLE obra_ficha ADD COLUMN crono_medicao VARCHAR(20)");
        if ($ofc && !isset($ofc['cronograma_nome'])) $pdo->exec("ALTER TABLE obra_ficha ADD COLUMN cronograma_nome VARCHAR(255)");
        if ($ofc && !isset($ofc['cronograma_at'])) $pdo->exec("ALTER TABLE obra_ficha ADD COLUMN cronograma_at VARCHAR(40)");
        if ($ofc && !isset($ofc['crono_obra_id'])) $pdo->exec("ALTER TABLE obra_ficha ADD COLUMN crono_obra_id VARCHAR(48)");   // obra_id no Supabase do Planejamento (junta o cronograma AO VIVO)
        if ($ofc && !isset($ofc['compra_coligada_cod'])) $pdo->exec("ALTER TABLE obra_ficha ADD COLUMN compra_coligada_cod INT");   // coligada que EMITE a compra (SC/PC) — CAPRETZ(1) p/ Cajá/Espazo/Prades/Piamonte/Licel; senão = própria
        if ($ofc && !isset($ofc['centro_custo'])) $pdo->exec("ALTER TABLE obra_ficha ADD COLUMN centro_custo VARCHAR(10)");         // centro de custo nas solicitações (campo 'obra': 001 padrão; 040/041/042/036/032 nas CAPRETZ)
        $pc = []; foreach ($pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cotacao_proposta'") as $c) $pc[$c['COLUMN_NAME']] = true;
        if ($pc && !isset($pc['equaliza'])) $pdo->exec("ALTER TABLE cotacao_proposta ADD COLUMN equaliza TEXT");
        // solic_obra.cnpj (CNPJ da obra p/ a carta de cotação) — self-heal p/ tabela já existente
        $sc = []; foreach ($pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='solic_obra'") as $c) $sc[$c['COLUMN_NAME']] = true;
        if ($sc && !isset($sc['cnpj'])) $pdo->exec("ALTER TABLE solic_obra ADD COLUMN cnpj VARCHAR(24)");
        if ($sc && !isset($sc['endereco'])) $pdo->exec("ALTER TABLE solic_obra ADD COLUMN endereco VARCHAR(255)");
        // anexo por fornecedor convidado (attach antes de existir proposta)
        $ac = []; foreach ($pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cotacao_anexo'") as $c) $ac[$c['COLUMN_NAME']] = true;
        if ($ac && !isset($ac['fornecedor_id'])) $pdo->exec("ALTER TABLE cotacao_anexo ADD COLUMN fornecedor_id INT");
        if ($ac && !isset($ac['fornecedor_nome'])) $pdo->exec("ALTER TABLE cotacao_anexo ADD COLUMN fornecedor_nome VARCHAR(191)");
        if ($ac && !isset($ac['url'])) $pdo->exec("ALTER TABLE cotacao_anexo ADD COLUMN url VARCHAR(500)");   // anexo por LINK (ex.: PDF importado do storage antigo) — sem arquivo local
        $fc = []; foreach ($pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cot_fornecedor'") as $c) $fc[$c['COLUMN_NAME']] = true;
        if ($fc && !isset($fc['contatos_at'])) $pdo->exec("ALTER TABLE cot_fornecedor ADD COLUMN contatos_at TEXT");
        $cfc = []; foreach ($pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cotacao_fornecedor'") as $c) $cfc[$c['COLUMN_NAME']] = true;
        if ($cfc && !isset($cfc['enviado_em'])) $pdo->exec("ALTER TABLE cotacao_fornecedor ADD COLUMN enviado_em VARCHAR(40)");
        if ($cfc && !isset($cfc['enviado_canal'])) $pdo->exec("ALTER TABLE cotacao_fornecedor ADD COLUMN enviado_canal VARCHAR(20)");
        if ($cfc && !isset($cfc['enviado_por'])) $pdo->exec("ALTER TABLE cotacao_fornecedor ADD COLUMN enviado_por VARCHAR(64)");
        // Fase 4 (inbound): estado da resposta do fornecedor no card da Concorrência
        if ($cfc && !isset($cfc['inbound_em'])) $pdo->exec("ALTER TABLE cotacao_fornecedor ADD COLUMN inbound_em VARCHAR(40)");
        if ($cfc && !isset($cfc['inbound_tipo'])) $pdo->exec("ALTER TABLE cotacao_fornecedor ADD COLUMN inbound_tipo VARCHAR(20)");
        if ($cfc && !isset($cfc['inbound_resumo'])) $pdo->exec("ALTER TABLE cotacao_fornecedor ADD COLUMN inbound_resumo VARCHAR(600)");
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
    // OVERRIDE POR-OBRA de nomenclatura/estrutura (espelha o MySQL) — nome/grupo por CÉLULA; NULL herda a base
    if (!isset($rcols['nome_override']))        $pdo->exec("ALTER TABLE radar_item ADD COLUMN nome_override TEXT");
    if (!isset($rcols['grupo_override']))       $pdo->exec("ALTER TABLE radar_item ADD COLUMN grupo_override TEXT");
    if (!isset($rcols['grupo_ordem_override'])) $pdo->exec("ALTER TABLE radar_item ADD COLUMN grupo_ordem_override INTEGER");
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
    $pdo->exec("CREATE TABLE IF NOT EXISTS cot_fornecedor (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, categoria TEXT, cidade TEXT, contato TEXT, telefone TEXT, whatsapp TEXT, email TEXT, itens TEXT, tipo TEXT, cnpj TEXT, contatos_at TEXT, ativo INTEGER DEFAULT 1, ext_id TEXT, created_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao (id INTEGER PRIMARY KEY AUTOINCREMENT, obra_id INTEGER, servico_id INTEGER, titulo TEXT NOT NULL, categoria TEXT, tipo_servico TEXT, verba REAL, verba_origem TEXT, descricao TEXT, equalizacao TEXT, num_solicitacao TEXT, num_pedido TEXT, solic_coligada TEXT, solic_obra_cod TEXT, status TEXT DEFAULT 'rascunho', aprovacao TEXT DEFAULT 'aguardando', criado_por TEXT, criado_nome TEXT, created_at TEXT, updated_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_item (id INTEGER PRIMARY KEY AUTOINCREMENT, cotacao_id INTEGER NOT NULL, descricao TEXT, unidade TEXT, quantidade REAL, observacao TEXT, ordem INTEGER)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_proposta (id INTEGER PRIMARY KEY AUTOINCREMENT, cotacao_id INTEGER NOT NULL, fornecedor_id INTEGER, fornecedor_nome TEXT, prazo TEXT, observacoes TEXT, equaliza TEXT, data_resposta TEXT, total REAL, created_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_proposta_item (id INTEGER PRIMARY KEY AUTOINCREMENT, proposta_id INTEGER NOT NULL, cotacao_item_id INTEGER NOT NULL, preco_unit REAL, preco_total REAL, observacao TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_anexo (id INTEGER PRIMARY KEY AUTOINCREMENT, cotacao_id INTEGER NOT NULL, proposta_id INTEGER, fornecedor_id INTEGER, fornecedor_nome TEXT, nome TEXT, arquivo TEXT, tamanho INTEGER, mime TEXT, criado_por TEXT, created_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cot_dicionario (id INTEGER PRIMARY KEY AUTOINCREMENT, servico_id INTEGER NOT NULL, descricao TEXT, unidade TEXT, ordem INTEGER, nota TEXT, created_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_fornecedor (id INTEGER PRIMARY KEY AUTOINCREMENT, cotacao_id INTEGER NOT NULL, fornecedor_id INTEGER, fornecedor_nome TEXT, categoria TEXT, contato TEXT, email TEXT, telefone TEXT, enviado_em TEXT, enviado_canal TEXT, enviado_por TEXT, created_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS carta_modelo (id INTEGER PRIMARY KEY AUTOINCREMENT, servico_id INTEGER, servico_nome TEXT, tipo TEXT, objeto TEXT, norma_referencia TEXT, pes_ref TEXT, escopo TEXT, criterios_medicao TEXT, equalizacao_campos TEXT, quantitativos_modelo TEXT, observacoes TEXT, versao INTEGER DEFAULT 1, is_padrao INTEGER DEFAULT 1, origem TEXT DEFAULT 'seed', criado_por TEXT, criado_nome TEXT, created_at TEXT, updated_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS carta_config (id INTEGER PRIMARY KEY, bloco_json TEXT, updated_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS carta_gerada (id INTEGER PRIMARY KEY AUTOINCREMENT, cotacao_id INTEGER NOT NULL, servico_nome TEXT, titulo TEXT, html TEXT, criado_por TEXT, criado_nome TEXT, created_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS preco_insumo (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, unidade TEXT, sinonimos TEXT, servico_id INTEGER, categoria TEXT, created_at TEXT, updated_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS preco_tabela (id INTEGER PRIMARY KEY AUTOINCREMENT, fornecedor_id INTEGER, fornecedor_nome TEXT, titulo TEXT, validade_inicio TEXT, validade_fim TEXT, observacao TEXT, anexo_id INTEGER, criado_por TEXT, criado_nome TEXT, created_at TEXT, updated_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS preco_item (id INTEGER PRIMARY KEY AUTOINCREMENT, tabela_id INTEGER NOT NULL, insumo_id INTEGER, descricao_original TEXT, unidade TEXT, preco REAL, frete_incluso INTEGER DEFAULT 0, observacao TEXT, created_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS solic_obra (id INTEGER PRIMARY KEY AUTOINCREMENT, coligada TEXT, obra_cod TEXT, nome_comercial TEXT, cnpj TEXT, endereco TEXT, comprador_id TEXT, comprador_nome TEXT, radar_obra_id INTEGER, created_at TEXT, updated_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS solic_overlay (id INTEGER PRIMARY KEY AUTOINCREMENT, coligada TEXT, numero TEXT, status TEXT, observacoes TEXT, fornecedores TEXT, orcamento_recebido INTEGER DEFAULT 0, cotacao_id INTEGER, updated_by TEXT, updated_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_pedido (id INTEGER PRIMARY KEY AUTOINCREMENT, cotacao_id INTEGER NOT NULL, coligada TEXT, coligada_cod INTEGER, colidmov TEXT, num_pedido TEXT, status TEXT, updated_by TEXT, updated_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS neg_grupo (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, ordem INTEGER DEFAULT 0, servicos TEXT, nota TEXT, updated_by TEXT, updated_at TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS obra_ficha (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT UNIQUE, nome TEXT NOT NULL, cidade TEXT, estado TEXT, status TEXT, conector_obra_id TEXT, radar_obra_id INTEGER, coligada_cod INTEGER, coligada_nome TEXT, cnpj TEXT, solic_nome TEXT, solic_coligada TEXT, solic_obra_cod TEXT, endereco TEXT, comprador_nome TEXT, torres INTEGER, pavimentos INTEGER, subsolos INTEGER, unidades INTEGER, tipologias TEXT, metodo_construtivo TEXT, areas_comuns TEXT, padrao TEXT, observacoes TEXT, link_cronograma TEXT, link_projetos TEXT, link_local TEXT, de_para_ok INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT, updated_by TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS oracle_uso (bitrix_id TEXT NOT NULL, dia TEXT NOT NULL, n INTEGER DEFAULT 0, PRIMARY KEY (bitrix_id, dia))");
    // E-MAIL FASE 4 — enviados/recebidos (espelho do MySQL)
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_email_out (id INTEGER PRIMARY KEY AUTOINCREMENT, cotacao_id INTEGER NOT NULL, cotacao_fornecedor_id INTEGER, fornecedor_id INTEGER, fornecedor_nome TEXT, email TEXT, message_id TEXT, token TEXT, assunto TEXT, enviado_em TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cotacao_email_in (id INTEGER PRIMARY KEY AUTOINCREMENT, cotacao_id INTEGER, cotacao_fornecedor_id INTEGER, fornecedor_id INTEGER, fornecedor_nome TEXT, imap_uid INTEGER, uidvalidity INTEGER, dedup_key TEXT, message_id TEXT, in_reply_to TEXT, from_email TEXT, from_nome TEXT, assunto TEXT, data_email TEXT, match_metodo TEXT, match_confianca TEXT, tipo TEXT DEFAULT 'indefinido', resumo TEXT, tem_proposta INTEGER DEFAULT 0, precisa_humano INTEGER DEFAULT 1, ia_confianca TEXT, tem_anexo INTEGER DEFAULT 0, ia_modelo TEXT, anexos_ids TEXT, draft_json TEXT, corpo_preview TEXT, status TEXT DEFAULT 'novo', lido_por TEXT, lido_em TEXT, created_at TEXT)");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_in_dedup ON cotacao_email_in(dedup_key)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ceo_tok ON cotacao_email_out(token)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_in_cot ON cotacao_email_in(cotacao_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_coti_cot ON cotacao_item(cotacao_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_prop_cot ON cotacao_proposta(cotacao_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_propi_prop ON cotacao_proposta_item(proposta_id)");
    // equalização (self-heal p/ bancos SQLite já criados sem as colunas)
    $ccols = []; foreach ($pdo->query("PRAGMA table_info(cotacao)") as $c) $ccols[$c['name']] = true;
    if (!isset($ccols['equalizacao'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN equalizacao TEXT");
    if (!isset($ccols['verba_origem'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN verba_origem TEXT");
    if (!isset($ccols['num_solicitacao'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN num_solicitacao TEXT");
    if (!isset($ccols['num_pedido'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN num_pedido TEXT");
    if (!isset($ccols['solic_coligada'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN solic_coligada TEXT");
    if (!isset($ccols['solic_obra_cod'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN solic_obra_cod TEXT");
    if (!isset($ccols['solic_colidmov'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN solic_colidmov TEXT");
    if (!isset($ccols['import_origem'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN import_origem TEXT");
    if (!isset($ccols['obra_livre'])) $pdo->exec("ALTER TABLE cotacao ADD COLUMN obra_livre TEXT");
    $cticols = []; foreach ($pdo->query("PRAGMA table_info(cotacao_item)") as $c) $cticols[$c['name']] = true;
    foreach (['obra_id'=>'INTEGER','solic_coligada'=>'TEXT','solic_numero'=>'TEXT','solic_colidmov'=>'TEXT','solic_seq'=>'INTEGER','solic_codprd'=>'TEXT'] as $col=>$ty) if (!isset($cticols[$col])) $pdo->exec("ALTER TABLE cotacao_item ADD COLUMN $col $ty");
    $ofcols = []; foreach ($pdo->query("PRAGMA table_info(obra_ficha)") as $c) $ofcols[$c['name']] = true;
    foreach (['pct_fisico'=>'REAL','crono_inicio'=>'TEXT','crono_fim'=>'TEXT','crono_medicao'=>'TEXT','cronograma_nome'=>'TEXT','cronograma_at'=>'TEXT','crono_obra_id'=>'TEXT','compra_coligada_cod'=>'INTEGER','centro_custo'=>'TEXT'] as $col=>$ty) if (!isset($ofcols[$col])) $pdo->exec("ALTER TABLE obra_ficha ADD COLUMN $col $ty");
    $scols = []; foreach ($pdo->query("PRAGMA table_info(solic_obra)") as $c) $scols[$c['name']] = true;
    if (!isset($scols['cnpj'])) $pdo->exec("ALTER TABLE solic_obra ADD COLUMN cnpj TEXT");
    if (!isset($scols['endereco'])) $pdo->exec("ALTER TABLE solic_obra ADD COLUMN endereco TEXT");
    $acols = []; foreach ($pdo->query("PRAGMA table_info(cotacao_anexo)") as $c) $acols[$c['name']] = true;
    if (!isset($acols['fornecedor_id'])) $pdo->exec("ALTER TABLE cotacao_anexo ADD COLUMN fornecedor_id INTEGER");
    if (!isset($acols['fornecedor_nome'])) $pdo->exec("ALTER TABLE cotacao_anexo ADD COLUMN fornecedor_nome TEXT");
    if (!isset($acols['url'])) $pdo->exec("ALTER TABLE cotacao_anexo ADD COLUMN url TEXT");
    $fcols = []; foreach ($pdo->query("PRAGMA table_info(cot_fornecedor)") as $c) $fcols[$c['name']] = true;
    if (!isset($fcols['contatos_at'])) $pdo->exec("ALTER TABLE cot_fornecedor ADD COLUMN contatos_at TEXT");
    $cfcols = []; foreach ($pdo->query("PRAGMA table_info(cotacao_fornecedor)") as $c) $cfcols[$c['name']] = true;
    if (!isset($cfcols['enviado_em'])) $pdo->exec("ALTER TABLE cotacao_fornecedor ADD COLUMN enviado_em TEXT");
    if (!isset($cfcols['enviado_canal'])) $pdo->exec("ALTER TABLE cotacao_fornecedor ADD COLUMN enviado_canal TEXT");
    if (!isset($cfcols['enviado_por'])) $pdo->exec("ALTER TABLE cotacao_fornecedor ADD COLUMN enviado_por TEXT");
    if (!isset($cfcols['inbound_em'])) $pdo->exec("ALTER TABLE cotacao_fornecedor ADD COLUMN inbound_em TEXT");
    if (!isset($cfcols['inbound_tipo'])) $pdo->exec("ALTER TABLE cotacao_fornecedor ADD COLUMN inbound_tipo TEXT");
    if (!isset($cfcols['inbound_resumo'])) $pdo->exec("ALTER TABLE cotacao_fornecedor ADD COLUMN inbound_resumo TEXT");
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

function criar_item($pdo, $nome, $grupo, $tipo = '', $curva = '', $copy_from = null, $obras = null) {
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
    // o serviço é sempre CATÁLOGO (global). A CÉLULA do radar (radar_item) é criada nas obras-alvo:
    //   $obras = null  -> TODAS as obras (padrão histórico)
    //   $obras = [ids] -> só nessas obras (ex.: item "Lago" curva A só numa obra)
    $rpad = trim((string)$g('responsavel_padrao'));
    $ins = $pdo->prepare("INSERT INTO radar_item (obra_id,servico_id,status,responsavel,tipo,updated_at) VALUES (?,?,?,?,?,?)");
    if (is_array($obras)) { $alvo = array_values(array_unique(array_map('intval', $obras))); }
    else { $alvo = array_map(fn($o) => (int)$o['id'], $pdo->query("SELECT id FROM obra ORDER BY id")->fetchAll()); }
    foreach ($alvo as $oid) { if ($oid > 0) $ins->execute([$oid,$nid,'Não Iniciado',$rpad!==''?$rpad:null,$tipo,date('c')]); }
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
