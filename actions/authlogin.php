<?php
/**
 * TROCA credencial do Bitrix por BILHETE assinado nosso.
 *
 * Por que existe: eu apostei que o Bitrix faria POST com AUTH_ID ao abrir o aplicativo local, e
 * medi que NÃO faz — 06/08/2026, três pessoas reais (Murilo, Paloma, Ricardo) chegaram ao
 * servidor sem bilhete, de navegador, vindo da própria tela. O app está registrado no modo em que
 * o Bitrix entrega a credencial só pelo BX24 (JS), não por POST no handler.
 *
 * Então o caminho é: o navegador pega a credencial com `BX24.getAuth()` e manda para cá. Isso
 * NÃO é confiar no cliente — o token que ele manda é conferido contra o PRÓPRIO BITRIX
 * (`user.current` com aquele auth). Token inventado não passa: o Bitrix não o reconhece. O que o
 * cliente diz sobre quem é continua sendo irrelevante; vale o que o Bitrix responder.
 *
 * POST {auth:'<access_token>', domain?:'caprem.tech'} -> {ok, tk, bitrix_id, nome}
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

try {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $auth = trim((string)($in['auth'] ?? ''));
    if ($auth === '') { http_response_code(400); echo json_encode(['error' => 'sem credencial']); exit; }

    [$bitrixId, $err] = auth_bitrix_quem($auth, $in['domain'] ?? null);
    if (!$bitrixId) {
        http_response_code(401);
        echo json_encode(['error' => 'O Bitrix não reconheceu esta credencial.', 'detalhe' => $err], JSON_UNESCAPED_UNICODE); exit;
    }

    // quem o BITRIX disse que é — não quem o navegador alegou
    $pdo = db();
    $nome = '';
    try { $q = $pdo->prepare("SELECT nome FROM usuario WHERE TRIM(bitrix_id)=? LIMIT 1");
          $q->execute([$bitrixId]); $nome = (string)($q->fetchColumn() ?: ''); } catch (Throwable $e) {}

    /* Registrar o SUCESSO, e não só a falha: com log só de falha, silêncio pode ser "funcionou"
       ou "ninguém abriu" — e essa diferença é exatamente o que decide se dá para virar a chave. */
    auth_registrar_ok($bitrixId, $nome);

    echo json_encode(['ok' => true, 'tk' => auth_emitir($bitrixId),
        'bitrix_id' => $bitrixId, 'nome' => $nome,
        'cadastrado' => $nome !== ''], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
