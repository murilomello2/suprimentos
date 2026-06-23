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

function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!is_dir(dirname(DB_PATH))) @mkdir(dirname(DB_PATH), 0775, true);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    db_schema($pdo);
    return $pdo;
}

function db_schema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS servico (
        id INTEGER PRIMARY KEY,
        ordem INTEGER,
        nome TEXT NOT NULL,
        slug TEXT,
        fase TEXT,
        curva TEXT,
        forma_contratacao TEXT,
        unidade TEXT,
        quantitativo TEXT,
        lead_dias INTEGER,
        marco_cronograma TEXT,
        termos_orcamento TEXT,
        termos_cronograma TEXT,
        responsavel_padrao TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS obra (
        id INTEGER PRIMARY KEY,
        nome TEXT NOT NULL,
        slug TEXT UNIQUE,
        codinome TEXT,
        cronograma_id TEXT,
        orcamento_total REAL
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
        updated_at TEXT,
        UNIQUE(obra_id, servico_id)
    )");
}

/** Carga inicial (idempotente) a partir de data/seed/trinity.json. */
function db_seed_if_empty() {
    $pdo = db();
    $n = (int)$pdo->query("SELECT COUNT(*) c FROM servico")->fetch()['c'];
    if ($n > 0) return false;

    $seed = json_decode(@file_get_contents(SEED_DIR . '/trinity.json'), true);
    if (!$seed) throw new Exception('seed trinity.json não encontrado');

    $o = $seed['obra'];
    $pdo->prepare("INSERT INTO obra (id,nome,slug,codinome,cronograma_id,orcamento_total)
                   VALUES (1,?,?,?,?,?)")
        ->execute([$o['nome'], $o['slug'], $o['codinome'], $o['cronograma_id'], $o['orcamento_total']]);

    $sv = $pdo->prepare("INSERT INTO servico
        (id,ordem,nome,slug,fase,curva,forma_contratacao,unidade,quantitativo,lead_dias,
         marco_cronograma,termos_orcamento,termos_cronograma,responsavel_padrao)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $ri = $pdo->prepare("INSERT INTO radar_item
        (obra_id,servico_id,status,responsavel,fornecedor,inicio_cotacao,fim_cotacao,
         verba_estim,confianca,observacoes,updated_at)
        VALUES (1,?,?,?,?,?,?,?,?,?,?)");

    $pdo->beginTransaction();
    $i = 0;
    foreach ($seed['servicos'] as $s) {
        $i++;
        $sid = is_int($s['ordem']) ? $s['ordem'] : $i;
        $sv->execute([$sid, $s['ordem'], $s['nome'], $s['slug'], $s['fase'], $s['curva'],
            $s['forma_contratacao'], $s['unidade'], $s['quantitativo'], $s['lead_dias'],
            $s['marco_cronograma'], $s['termos_orcamento'], $s['termos_cronograma'], '']);
        $ri->execute([$sid, $s['status'], $s['responsavel'], $s['fornecedor'],
            $s['inicio_cotacao'], $s['fim_cotacao'], $s['verba_estim'], $s['confianca'],
            $s['observacoes'], date('c')]);
    }
    $pdo->commit();
    return true;
}
