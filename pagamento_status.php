<?php
/**
 * Situação de uma inscrição, perguntada pelo modal de pagamento a cada poucos segundos.
 * Responde só o status. Se o aviso do Mercado Pago ainda não chegou, consulta a API (no máximo a cada 10 s).
 */
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/pagamento.php';
require_once __DIR__ . '/includes/email_pagamento.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$codigo = strtoupper(trim((string) ($_GET['c'] ?? '')));
$insc   = null;
if (preg_match('/^[A-Z0-9]{10}$/', $codigo)) {
    try {
        $pdo  = db();
        $buscar = function () use ($pdo, $codigo) {
            $st = $pdo->prepare('SELECT id, status FROM inscricoes WHERE codigo = :c LIMIT 1');
            $st->execute([':c' => $codigo]);
            return $st->fetch() ?: null;
        };
        $insc = $buscar();
        $cfg  = mp_config();
        if ($insc && $cfg && $insc['status'] === 'pendente') {
            try {
                pagamento_consultar($pdo, $cfg, $insc, (int) (evento()['inscricoes_vagas'] ?? 0), email_site_base($_SERVER), email_config());
                $insc = $buscar();
            } catch (Throwable $e) {
                error_log('Pedal Solidário – consulta de pagamento: ' . $e->getMessage());
            }
        }
    } catch (PDOException $e) {
        error_log('Pedal Solidário – status do pagamento: ' . $e->getMessage());
        http_response_code(503);
        echo json_encode(['status' => null]);
        exit;
    }
}

if (!$insc) {
    http_response_code(404);
    echo json_encode(['status' => null]);
    exit;
}
echo json_encode(['status' => $insc['status']]);
