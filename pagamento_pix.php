<?php
/**
 * "Tentar de novo" do modal: gera o Pix de uma inscrição pendente quando o Mercado Pago falhou na hora da inscrição.
 * POST c=CODIGO → {"ok": true, "pix": {"copia_cola","qr_base64","expira_em"}} ou {"ok": false, "erro": "..."}.
 */
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/pagamento.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$responder = function (int $http, array $dados) {
    http_response_code($http);
    echo json_encode($dados);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $responder(405, ['ok' => false, 'erro' => 'Use POST.']);
}
$cfg = mp_config();
if (!$cfg) {
    $responder(503, ['ok' => false, 'erro' => 'Pagamento online indisponível.']);
}
$codigo = strtoupper(trim((string) ($_POST['c'] ?? '')));
if (!preg_match('/^[A-Z0-9]{10}$/', $codigo)) {
    $responder(404, ['ok' => false, 'erro' => 'Inscrição não encontrada.']);
}

try {
    $pdo = db();
    $st = $pdo->prepare('SELECT id FROM inscricoes WHERE codigo = :c LIMIT 1');
    $st->execute([':c' => $codigo]);
    $id = $st->fetchColumn();
    if ($id === false) {
        $responder(404, ['ok' => false, 'erro' => 'Inscrição não encontrada.']);
    }
    $erro = pagamento_gerar_pix($pdo, $cfg, (int) $id);
    $pix  = $erro === null ? pagamento_pix_atual($pdo, (int) $id) : null;
} catch (Throwable $e) {
    error_log('Pedal Solidário – gerar Pix: ' . $e->getMessage());
    $responder(500, ['ok' => false, 'erro' => 'Não foi possível gerar o Pix agora.']);
}

if ($pix === null) {
    $responder(200, ['ok' => false, 'erro' => 'Não foi possível gerar o Pix agora. Tente de novo em instantes ou pague com cartão.']);
}
$pix['expira_em'] = date('c', strtotime((string) $pix['expira_em']));
$responder(200, ['ok' => true, 'pix' => $pix]);
