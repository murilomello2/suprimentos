<?php
/**
 * CADASTRO ÚNICO DE OBRAS.
 * `obra_ficha` é o mestre (todas as obras + de-para). A tabela `obra` do radar é a
 * operacional (itens/cotações/orçamento apontam pra ela). Aqui a gente reconcilia as duas:
 * todo seletor de obra puxa da ficha; quando a obra ainda não existe no radar, é PROMOVIDA
 * (cria a linha vazia em `obra` e grava o radar_obra_id de volta na ficha).
 */
require_once __DIR__ . '/coligadas.php';   // ob_norm

/** Lista unificada de obras (a partir da obra_ficha) p/ os dropdowns de qualquer módulo. */
function obras_picker_list($pdo) {
    try {
        $rows = $pdo->query("SELECT id AS ficha_id, nome, radar_obra_id, coligada_cod, compra_coligada_cod, centro_custo, status, cidade
                             FROM obra_ficha ORDER BY (status='Finalizada'), nome")->fetchAll();
        return $rows ?: [];
    } catch (Throwable $e) { return []; }
}

/** tokens distintivos (>=4 letras) do nome da obra, sem acento. */
function _obra_tokens($nome) {
    $n = ob_norm((string)$nome); if ($n === '') return [];
    return array_values(array_filter(explode(' ', $n), fn($t) => strlen($t) >= 4));
}

/** Garante que a obra da ficha exista no RADAR (promove se preciso) e devolve o obra_id do radar (ou null). */
function obra_radar_id($pdo, $fichaId) {
    $fichaId = (int)$fichaId; if (!$fichaId) return null;
    $f = $pdo->prepare("SELECT * FROM obra_ficha WHERE id=?"); $f->execute([$fichaId]); $of = $f->fetch();
    if (!$of) return null;
    // já linkada e a obra do radar existe?
    if (!empty($of['radar_obra_id'])) {
        $chk = $pdo->prepare("SELECT id FROM obra WHERE id=?"); $chk->execute([(int)$of['radar_obra_id']]);
        if ($chk->fetchColumn()) return (int)$of['radar_obra_id'];
    }
    // 1) RECONCILIA: casa com uma obra que JÁ existe no radar pelo nome (evita duplicar — ex.: ficha "Trinity - Araçatuba" ~ radar "Trinity")
    $ftoks = _obra_tokens((string)$of['nome']);
    if ($ftoks) {
        $best = null; $bestScore = 0;
        foreach ($pdo->query("SELECT id, nome FROM obra")->fetchAll() as $ro) {
            $score = count(array_intersect($ftoks, _obra_tokens((string)$ro['nome'])));
            if ($score > $bestScore) { $bestScore = $score; $best = (int)$ro['id']; }
        }
        if ($best && $bestScore >= 1) {
            $pdo->prepare("UPDATE obra_ficha SET radar_obra_id=?, updated_at=? WHERE id=?")->execute([$best, date('c'), $fichaId]);
            return $best;
        }
    }
    // 2) PROMOVE: cria a obra vazia no radar (id manual = MAX+1) e grava o vínculo na ficha
    $nid = (int)$pdo->query("SELECT COALESCE(MAX(id),0)+1 FROM obra")->fetchColumn();
    $base = ob_norm((string)$of['nome']); if ($base === '') $base = 'obra-' . $nid;
    $slug = $base; $k = 1;
    while (true) { $c = $pdo->prepare("SELECT COUNT(*) FROM obra WHERE slug=?"); $c->execute([$slug]); if (!(int)$c->fetchColumn()) break; $slug = $base . '-' . (++$k); }
    $pdo->prepare("INSERT INTO obra (id,nome,slug,codinome,`local`) VALUES (?,?,?,?,?)")
        ->execute([$nid, (string)$of['nome'], $slug, (string)$of['nome'], (string)($of['endereco'] ?? '')]);
    $pdo->prepare("UPDATE obra_ficha SET radar_obra_id=?, updated_at=? WHERE id=?")->execute([$nid, date('c'), $fichaId]);
    return $nid;
}
