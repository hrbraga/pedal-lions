<?php
/**
 * Mercado Pago FALSO, só para os testes (sem internet e sem dinheiro).
 * Suba com: php -S 127.0.0.1:<porta> tests/mp_falso.php
 * Estado (pagamentos e pedidos recebidos) em <temp>/mp_falso_<porta>.json.
 *
 *  POST /v1/payments      pix → pending com QR falso; cartão pelo token: APRO → approved, OTHE → rejected, CONT → in_process
 *  GET  /v1/payments/{id} → o pagamento guardado (404 se não existe)
 *  POST /__mudar/{id}     → altera campos do pagamento guardado (ex.: {"status":"approved"})
 */
$arquivo = sys_get_temp_dir() . '/mp_falso_' . $_SERVER['SERVER_PORT'] . '.json';
$estado  = is_file($arquivo) ? (json_decode((string) file_get_contents($arquivo), true) ?: []) : [];
$estado += ['seq' => 1000, 'pagamentos' => [], 'idem' => [], 'pedidos' => []];

$metodo  = $_SERVER['REQUEST_METHOD'];
$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$corpo   = json_decode((string) file_get_contents('php://input'), true);
$cab     = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];

$responder = function (int $http, $dados) use (&$estado, $arquivo) {
    file_put_contents($arquivo, json_encode($estado));
    http_response_code($http);
    header('Content-Type: application/json');
    echo json_encode($dados);
    exit;
};

$estado['pedidos'][] = ['metodo' => $metodo, 'caminho' => $caminho, 'corpo' => $corpo,
                        'idem' => $cab['x-idempotency-key'] ?? null];

if (strpos($caminho, '/__mudar/') === 0) {
    $id = substr($caminho, 9);
    if (!isset($estado['pagamentos'][$id])) {
        $responder(404, ['message' => 'not found']);
    }
    $estado['pagamentos'][$id] = array_merge($estado['pagamentos'][$id], (array) $corpo);
    $responder(200, $estado['pagamentos'][$id]);
}

if (!preg_match('/^Bearer \S+/', $cab['authorization'] ?? '')) {
    $responder(401, ['message' => 'invalid access token', 'status' => 401]);
}

if ($metodo === 'POST' && $caminho === '/v1/payments') {
    $idem = $cab['x-idempotency-key'] ?? '';
    if ($idem !== '' && isset($estado['idem'][$idem])) {
        $responder(201, $estado['pagamentos'][$estado['idem'][$idem]]);
    }
    $id = (string) ++$estado['seq'];
    $p  = [
        'id' => (int) $id, 'transaction_amount' => $corpo['transaction_amount'] ?? null,
        'external_reference' => $corpo['external_reference'] ?? null, 'description' => $corpo['description'] ?? null,
        'payment_method_id' => $corpo['payment_method_id'] ?? null, 'installments' => $corpo['installments'] ?? 1,
        'date_of_expiration' => $corpo['date_of_expiration'] ?? null, 'payer' => $corpo['payer'] ?? null,
    ];
    if (($corpo['payment_method_id'] ?? '') === 'pix') {
        $p['status'] = 'pending';
        $p['status_detail'] = 'pending_waiting_transfer';
        $p['point_of_interaction'] = ['transaction_data' => [
            'qr_code' => '00020126FALSO' . $id,
            // PNG 1×1 transparente
            'qr_code_base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
        ]];
    } else {
        $p['status'] = ['APRO' => 'approved', 'OTHE' => 'rejected', 'CONT' => 'in_process'][$corpo['token'] ?? ''] ?? 'rejected';
        $p['status_detail'] = ['approved' => 'accredited', 'rejected' => 'cc_rejected_other_reason', 'in_process' => 'pending_contingency'][$p['status']];
    }
    $estado['pagamentos'][$id] = $p;
    if ($idem !== '') {
        $estado['idem'][$idem] = $id;
    }
    $responder(201, $p);
}

if ($metodo === 'GET' && preg_match('#^/v1/payments/(\d+)$#', $caminho, $m)) {
    if (!isset($estado['pagamentos'][$m[1]])) {
        $responder(404, ['message' => 'Payment not found', 'status' => 404]);
    }
    $responder(200, $estado['pagamentos'][$m[1]]);
}

$responder(404, ['message' => 'resource not found']);
