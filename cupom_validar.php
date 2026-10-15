<?php
/**
 * Confere um cupom e devolve o desconto (usado pelo botão "Aplicar" do formulário).
 * A conferência definitiva sempre é refeita em inscricao.php ao enviar a inscrição.
 */
session_start();
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/cupons.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(array $dados): void
{
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responder(['ok' => false, 'mensagem' => 'Requisição inválida.']);
}
if (!csrf_valido(post_txt('csrf'))) {
    responder(['ok' => false, 'mensagem' => 'Sessão expirada. Recarregue a página.']);
}

$codigo    = cupom_normalizar(post_txt('codigo'));
$categoria = categoria_por_slug(post_txt('categoria'));

if ($codigo === '') {
    responder(['ok' => false, 'mensagem' => 'Cupom inválido.']);
}
if (!$categoria) {
    responder(['ok' => false, 'mensagem' => 'Escolha a categoria para aplicar o cupom.']);
}

try {
    $pdo = db();
    if (cupom_bloqueado($pdo)) {
        responder(['ok' => false, 'mensagem' => 'Muitas tentativas. Aguarde alguns minutos e tente de novo.']);
    }

    [$cupom, $erro] = cupom_para_uso($pdo, $codigo);
    if (!$cupom) {
        cupom_registrar_falha($pdo);
        responder(['ok' => false, 'mensagem' => $erro]);
    }

    $desconto = cupom_desconto($cupom, (float) $categoria['valor']);
    responder([
        'ok'       => true,
        'desconto' => $desconto,
        'mensagem' => 'Cupom aplicado: ' . $cupom['nome'] . ' (−' . moeda($desconto) . ' na inscrição).',
    ]);
} catch (PDOException $e) {
    error_log('Pedal Solidário – erro ao validar cupom: ' . $e->getMessage());
    responder(['ok' => false, 'mensagem' => 'Não foi possível conferir o cupom agora.']);
}
