<?php
/**
 * CARTAS CONVITE — modelos por serviço (camada 🔧) + config fixa Caprem (camada 🔒) + geração (monta as 3 camadas).
 * GET  ?config                      -> config Caprem (resolvida: salva ou padrão)
 * GET  ?modelo=<id>                 -> um modelo completo
 * GET  ?gerar=<cotacao_id>&me=      -> carta MONTADA (3 camadas + dados da cotação) p/ a tela de conferência
 * GET                               -> {modelos:[lista], config}
 * POST {acao:'save_modelo', me, modelo{...}}         (admin/edita todas)
 * POST {acao:'save_config', me, config{...}}         (admin)
 * POST {acao:'import_seed', me, modelos:[...]}        (admin) — upsert em lote (seed da IA)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

// ---- Camada 🔒 FIXA CAPREM (padrão; editável e salva em carta_config) ----
function carta_default_config() {
    return [
        'obrigacoes' => [
            'Manter, durante toda a execução do contrato, todas as condições de habilitação e qualificação exigidas.',
            'Iniciar o atendimento no prazo máximo de 05 (cinco) dias após o recebimento e a assinatura eletrônica do contrato.',
            'Satisfazer as normas da ABNT pertinentes ao serviço contratado.',
            'Indicar um responsável técnico à disposição para esclarecimentos e reuniões com a engenharia da obra.',
            'Assumir integral responsabilidade pela boa execução, eficiência e segurança do serviço, bem como pelos danos decorrentes.',
            'Guardar sigilo sobre as informações obtidas, atendendo à Lei Geral de Proteção de Dados (LGPD).',
        ],
        'seguranca' => [
            'a_cada_medicao' => 'GFIP, folha de pagamento, GRF/GRRF, GPS, holerites, cartões de ponto, fichas de EPI, ASO, DARF e DCTFWeb — conforme o regime tributário.',
            'da_empresa'     => 'CNPJ/CNAE, contrato social, CRF/FGTS, PGR, PCMSO e apólice de seguro conforme a Convenção Coletiva.',
            'dos_empregados' => 'Registro, CTPS, ASO, ordem de serviço e treinamentos/NRs aplicáveis aos alocados na obra.',
            'nota'           => 'Todo o EPI e sua gestão são de responsabilidade do empreiteiro. A não apresentação da documentação posterga o pagamento da parcela até o cumprimento da obrigação.',
        ],
        'julgamento' => [
            'As propostas serão classificadas por ordem crescente de preços, observada a equalização das condições da planilha de preços.',
            'Será considerada vencedora a proponente que oferecer o menor preço global equalizado, desde que atendidas as disposições desta Carta Convite.',
            'Serão classificadas apenas as propostas que atenderem integralmente às disposições desta Carta Convite.',
        ],
        'faturamento' => 'O pagamento será efetuado no prazo de até 28 (vinte e oito) dias corridos contados da retirada da nota fiscal, acompanhada da documentação fiscal e trabalhista completa, admitido o parcelamento acordado no fechamento. Eventuais retenções e garantias seguirão o padrão Caprem, conforme a natureza do serviço.',
        // Responsável do Departamento de Suprimentos = o usuário (Bitrix) que CRIOU a cotação (preenchido na geração).
        'contatos' => [],
        'declaracao' => 'Ao receber esta Carta Convite, a proponente declara que está ciente e de posse de todas as informações necessárias para a participação na concorrência ao serviço destacado.',
        'validade_dias' => 30,
    ];
}
function carta_get_config($pdo) {
    $row = $pdo->query("SELECT bloco_json FROM carta_config WHERE id=1")->fetch();
    $j = $row ? json_decode($row['bloco_json'] ?? '', true) : null;
    return is_array($j) ? array_replace_recursive(carta_default_config(), $j) : carta_default_config();
}
function cj($x) { $j = json_decode((string)$x, true); return is_array($j) ? $j : []; }
function carta_modelo_out($m) {
    return [
        'id' => (int)$m['id'], 'servico_id' => $m['servico_id'] !== null ? (int)$m['servico_id'] : null,
        'servico_nome' => $m['servico_nome'], 'tipo' => $m['tipo'], 'objeto' => $m['objeto'],
        'norma_referencia' => cj($m['norma_referencia']), 'pes_ref' => $m['pes_ref'],
        'escopo' => cj($m['escopo']), 'criterios_medicao' => cj($m['criterios_medicao']),
        'equalizacao_campos' => cj($m['equalizacao_campos']), 'quantitativos_modelo' => cj($m['quantitativos_modelo']),
        'observacoes' => $m['observacoes'], 'versao' => (int)$m['versao'], 'is_padrao' => (int)$m['is_padrao'],
        'origem' => $m['origem'], 'updated_at' => $m['updated_at'],
    ];
}

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['config'])) { echo json_encode(['config' => carta_get_config($pdo)], JSON_UNESCAPED_UNICODE); exit; }
        if (isset($_GET['modelo'])) {
            $q = $pdo->prepare("SELECT * FROM carta_modelo WHERE id=?"); $q->execute([(int)$_GET['modelo']]);
            $m = $q->fetch(); echo json_encode(['modelo' => $m ? carta_modelo_out($m) : null], JSON_UNESCAPED_UNICODE); exit;
        }
        if (isset($_GET['gerar'])) { echo json_encode(carta_gerar($pdo, (int)$_GET['gerar']), JSON_UNESCAPED_UNICODE); exit; }
        if (isset($_GET['geradas'])) {
            $q = $pdo->prepare("SELECT id, titulo, servico_nome, criado_nome, created_at FROM carta_gerada WHERE cotacao_id=? ORDER BY id DESC"); $q->execute([(int)$_GET['geradas']]);
            echo json_encode(['geradas' => $q->fetchAll()], JSON_UNESCAPED_UNICODE); exit;
        }
        if (isset($_GET['gerada'])) {
            $q = $pdo->prepare("SELECT id, cotacao_id, titulo, html, created_at FROM carta_gerada WHERE id=?"); $q->execute([(int)$_GET['gerada']]);
            echo json_encode(['gerada' => $q->fetch() ?: null], JSON_UNESCAPED_UNICODE); exit;
        }
        // lista compacta
        $ms = $pdo->query("SELECT id, servico_id, servico_nome, tipo, pes_ref, is_padrao, versao, updated_at,
                                  (LENGTH(criterios_medicao)>4) AS tem_medicao, (LENGTH(equalizacao_campos)>4) AS tem_eq
                           FROM carta_modelo ORDER BY servico_nome")->fetchAll();
        $servs = $pdo->query("SELECT DISTINCT id, nome FROM servico ORDER BY nome")->fetchAll();
        echo json_encode(['modelos' => $ms, 'config' => carta_get_config($pdo), 'servicos' => $servs], JSON_UNESCAPED_UNICODE); exit;
    }

    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $me = $in['me'] ?? null;
    $perms = user_perms($pdo, $me);
    if (empty($perms['autorizado'])) { http_response_code(403); echo json_encode(['error' => 'Não autorizado.']); exit; }
    // modelos/config são TEMPLATE GLOBAL — só admin ou quem edita todas as obras
    $podeGlobal = !empty($perms['perm_admin']) || ($perms['editar_escopo'] ?? '') === 'todas';
    $acao = $in['acao'] ?? '';

    if ($acao === 'save_config') {
        if (!$podeGlobal) { http_response_code(403); echo json_encode(['error' => 'Config da carta é global — só admin.']); exit; }
        $cfg = array_replace_recursive(carta_default_config(), (array)($in['config'] ?? []));
        $exists = $pdo->query("SELECT 1 FROM carta_config WHERE id=1")->fetch();
        if ($exists) $pdo->prepare("UPDATE carta_config SET bloco_json=?, updated_at=? WHERE id=1")->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE), date('c')]);
        else $pdo->prepare("INSERT INTO carta_config (id, bloco_json, updated_at) VALUES (1,?,?)")->execute([json_encode($cfg, JSON_UNESCAPED_UNICODE), date('c')]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'save_modelo' || $acao === 'import_seed') {
        if (!$podeGlobal) { http_response_code(403); echo json_encode(['error' => 'Modelos de carta são globais — só admin.']); exit; }
        $lista = $acao === 'import_seed' ? (array)($in['modelos'] ?? []) : [ (array)($in['modelo'] ?? []) ];
        $now = date('c'); $n = 0;
        // resolve servico_id por nome quando não vier
        $servByNome = [];
        foreach ($pdo->query("SELECT id, nome FROM servico") as $s) $servByNome[mb_strtolower_safe($s['nome'])] = (int)$s['id'];
        foreach ($lista as $m) {
            $nome = trim((string)($m['servico_nome'] ?? $m['carta_arquivo'] ?? '')); if ($nome === '') continue;
            $sid = $m['servico_id'] ?? null;
            if (!$sid) { $sug = trim((string)($m['servico_sistema_sugerido'] ?? '')); if ($sug !== '') $sid = $servByNome[mb_strtolower_safe($sug)] ?? null; }
            $enc = fn($v) => json_encode(array_values((array)($v ?? [])), JSON_UNESCAPED_UNICODE);
            $vals = [
                $sid ?: null, $nome, trim((string)($m['tipo'] ?? '')), trim((string)($m['objeto'] ?? '')),
                $enc($m['norma_referencia'] ?? []), trim((string)($m['pes_ref'] ?? $m['pes_sugerido'] ?? '')),
                $enc($m['escopo'] ?? []), $enc($m['criterios_medicao'] ?? []), $enc($m['equalizacao_campos'] ?? []),
                json_encode(array_values((array)($m['quantitativos_modelo'] ?? [])), JSON_UNESCAPED_UNICODE),
                trim((string)($m['observacoes'] ?? '')),
            ];
            // upsert por id (save_modelo) ou por servico_nome (import)
            $id = (int)($m['id'] ?? 0);
            if (!$id && $acao === 'import_seed') { $q = $pdo->prepare("SELECT id FROM carta_modelo WHERE servico_nome=?"); $q->execute([$nome]); $id = (int)($q->fetchColumn() ?: 0); }
            if ($id) {
                $pdo->prepare("UPDATE carta_modelo SET servico_id=?, servico_nome=?, tipo=?, objeto=?, norma_referencia=?, pes_ref=?, escopo=?, criterios_medicao=?, equalizacao_campos=?, quantitativos_modelo=?, observacoes=?, versao=versao+1, updated_at=? WHERE id=?")
                    ->execute(array_merge($vals, [$now, $id]));
            } else {
                $pdo->prepare("INSERT INTO carta_modelo (servico_id, servico_nome, tipo, objeto, norma_referencia, pes_ref, escopo, criterios_medicao, equalizacao_campos, quantitativos_modelo, observacoes, versao, is_padrao, origem, criado_por, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,1,?,?,?,?)")
                    ->execute(array_merge($vals, [$acao === 'import_seed' ? 'seed' : 'manual', $me, $now, $now]));
            }
            $n++;
        }
        echo json_encode(['ok' => true, 'n' => $n], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($acao === 'salvar_carta') {   // guarda a carta gerada (HTML) na cotação — vira o registro do que foi enviado
        $cid = (int)($in['cotacao_id'] ?? 0); if (!$cid) throw new Exception('cotacao_id obrigatório');
        $obra = (int)$pdo->query("SELECT COALESCE(obra_id,1) FROM cotacao WHERE id=" . $cid)->fetchColumn();
        // permissão: quem edita a obra da cotação
        $pe = user_perms($pdo, $me);
        $pode = !empty($pe['perm_admin']) || ($pe['editar_escopo'] ?? '') === 'todas'
             || (is_array($pe['obras_editar'] ?? null) && in_array((string)$obra, array_map('strval', $pe['obras_editar']), true));
        if (!$pode) { http_response_code(403); echo json_encode(['error' => 'Sem permissão de edição.']); exit; }
        $html = (string)($in['html'] ?? ''); if (strlen($html) < 20) throw new Exception('carta vazia');
        if (strlen($html) > 2000000) throw new Exception('carta muito grande');
        $pdo->prepare("INSERT INTO carta_gerada (cotacao_id, servico_nome, titulo, html, criado_por, criado_nome, created_at) VALUES (?,?,?,?,?,?,?)")
            ->execute([$cid, trim((string)($in['servico_nome'] ?? '')), trim((string)($in['titulo'] ?? 'Carta convite')), $html, $me, $pe['nome'] ?? null, date('c')]);
        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE); exit;
    }

    echo json_encode(['error' => 'ação desconhecida'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// mb_strtolower sem depender de mbstring (servidor não tem) — strtr por ARRAY é multibyte-safe
function mb_strtolower_safe($s) {
    $map = ['Á'=>'á','À'=>'à','Â'=>'â','Ã'=>'ã','Ä'=>'ä','É'=>'é','È'=>'è','Ê'=>'ê','Ë'=>'ë','Í'=>'í','Ì'=>'ì','Î'=>'î','Ï'=>'ï','Ó'=>'ó','Ò'=>'ò','Ô'=>'ô','Õ'=>'õ','Ö'=>'ö','Ú'=>'ú','Ù'=>'ù','Û'=>'û','Ü'=>'ü','Ç'=>'ç','Ñ'=>'ñ'];
    return strtolower(strtr((string)$s, $map));
}

// ---- GERAÇÃO: monta a carta (3 camadas) p/ a cotação ----
function carta_gerar($pdo, $cid) {
    $c = $pdo->prepare("SELECT c.*, o.nome AS obra_nome, s.nome AS servico_nome FROM cotacao c LEFT JOIN obra o ON o.id=c.obra_id LEFT JOIN servico s ON s.id=c.servico_id WHERE c.id=?");
    $c->execute([$cid]); $cot = $c->fetch();
    if (!$cot) return ['error' => 'cotação não encontrada'];
    // modelo do serviço (por servico_id; senão por nome do serviço/título)
    $mod = null;
    if (!empty($cot['servico_id'])) { $q = $pdo->prepare("SELECT * FROM carta_modelo WHERE servico_id=? ORDER BY is_padrao DESC, versao DESC LIMIT 1"); $q->execute([(int)$cot['servico_id']]); $mod = $q->fetch(); }
    if (!$mod) { $q = $pdo->prepare("SELECT * FROM carta_modelo WHERE servico_nome LIKE ? ORDER BY is_padrao DESC LIMIT 1"); $q->execute(['%'.trim((string)($cot['servico_nome'] ?? $cot['titulo'])).'%']); $mod = $q->fetch(); }
    $modelo = $mod ? carta_modelo_out($mod) : null;
    // quantitativos da COTAÇÃO (itens reais); fallback = quantitativos_modelo
    $iq = $pdo->prepare("SELECT descricao, unidade, quantidade FROM cotacao_item WHERE cotacao_id=? ORDER BY ordem, id"); $iq->execute([$cid]);
    $itens = $iq->fetchAll();
    $quant = [];
    foreach ($itens as $it) $quant[] = ['item' => $it['descricao'], 'unidade' => $it['unidade'], 'qtde' => $it['quantidade']];
    if (!$quant && $modelo) foreach ($modelo['quantitativos_modelo'] as $q2) $quant[] = ['item' => $q2['item'] ?? '', 'unidade' => $q2['unidade'] ?? '', 'qtde' => null];
    // pontos de equalização da cotação (se houver) senão os campos do modelo
    $eqCot = array_values(array_filter(array_map('trim', preg_split('/\r?\n|\|/', (string)($cot['equalizacao'] ?? '')))));
    return [
        'ok' => true,
        'cotacao' => [
            'id' => (int)$cot['id'], 'titulo' => $cot['titulo'], 'obra_nome' => $cot['obra_nome'],
            'categoria' => $cot['categoria'], 'servico_nome' => $cot['servico_nome'], 'servico_id' => $cot['servico_id'],
            'descricao' => $cot['descricao'], 'verba' => $cot['verba'], 'criado_nome' => $cot['criado_nome'],
            'created_at' => $cot['created_at'], 'num_solicitacao' => $cot['num_solicitacao'],
        ],
        'modelo' => $modelo,
        'tem_modelo' => (bool)$modelo,
        'quantitativos' => $quant,
        'equalizacao_cotacao' => $eqCot,
        'config' => carta_get_config($pdo),
    ];
}
