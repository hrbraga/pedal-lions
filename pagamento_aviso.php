<?php
/**
 * Aviso (webhook) do Mercado Pago: chamado por eles quando um pagamento muda.
 * Cadastrar no painel do Mercado Pago: https://lionsclubecornelio.com/pagamento_aviso.php, evento "Pagamentos".
 * Confere a assinatura e, mesmo assim, não confia no conteúdo do aviso: busca o pagamento na API.
 */
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/pagamento.php';
require_once __DIR__ . '/includes/email_pagamento.php';

header('Content-Type: application/json');
$responder = function (int $http, array $dados = []) {
    http_response_code($http);
    echo json_encode($dados ?: ['ok' => $http === 200]);
    exit;
};

$cfg = mp_config();
if (!$cfg) {
    $responder(503);
}

$corpo   = json_decode((string) file_get_contents('php://input'), true);
$corpo   = is_array($corpo) ? $corpo : [];
$tipo    = (string) ($_GET['type'] ?? ($corpo['type'] ?? ''));
$data_id = (string) ($_GET['data_id'] ?? ($corpo['data']['id'] ?? '')); // o PHP troca "data.id" da URL por "data_id"
if ($tipo !== 'payment' || $data_id === '') {
    $responder(200); // outros avisos (pedidos, contestações…) não interessam
}

if (!mp_assinatura_valida($cfg['webhook_secret'], (string) ($_SERVER['HTTP_X_SIGNATURE'] ?? ''),
                          (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''), $data_id)) {
    error_log('Pedal Solidário – aviso do Mercado Pago com assinatura inválida (pagamento ' . $data_id . ')');
    $responder(401);
}

$r = mp_buscar_pagamento($cfg, $data_id);
if ($r['http'] !== 200 || !is_array($r['dados'])) {
    error_log('Pedal Solidário – aviso do Mercado Pago: não foi possível buscar o pagamento ' . $data_id . ': ' . ($r['erro'] ?? '?'));
    $responder(500); // o Mercado Pago tenta de novo
}

try {
    $resultado = pagamento_processar(db(), $r['dados'], (int) (evento()['inscricoes_vagas'] ?? 0),
                                     email_site_base($_SERVER), email_config());
} catch (Throwable $e) {
    error_log('Pedal Solidário – aviso do Mercado Pago (pagamento ' . $data_id . '): ' . $e->getMessage());
    $responder(500);
}
$responder(200, ['ok' => true, 'resultado' => $resultado]);
