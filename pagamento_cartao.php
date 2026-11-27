<?php
/**
 * Pagamento com cartão: recebe do formulário do Mercado Pago (Card Payment Brick) o token do cartão e cobra
 * o total da inscrição (sempre o valor do banco), em até 2 parcelas, dentro do prazo.
 * POST JSON {codigo, token, installments, payment_method_id, issuer_id, payer:{email, identification:{type, number}}}
 * → {"status": "approved"|"rejected"|"in_process"|"erro", "mensagem": "..."}
 */
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/pagamento.php';
require_once __DIR__ . '/includes/email_pagamento.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$responder = function (string $status, string $mensagem, int $http = 200) {
    http_response_code($http);
    echo json_encode(['status' => $status, 'mensagem' => $mensagem]);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $responder('erro', 'Use POST.', 405);
}
$cfg = mp_config();
if (!$cfg) {
    $responder('erro', 'Pagamento online indisponível no momento.', 503);
}
$form   = json_decode((string) file_get_contents('php://input'), true);
$form   = is_array($form) ? $form : [];
$codigo = strtoupper(trim((string) ($form['codigo'] ?? '')));
$parcelas = (int) ($form['installments'] ?? 0);
if (!preg_match('/^[A-Z0-9]{10}$/', $codigo) || trim((string) ($form['token'] ?? '')) === '') {
    $responder('erro', 'Dados do pagamento incompletos.', 400);
}
if ($parcelas < 1 || $parcelas > 2) {
    $responder('erro', 'O cartão pode ser parcelado em até 2 vezes.', 400);
}

try {
    $pdo = db(); // já expira as pendentes vencidas
    $st = $pdo->prepare('SELECT *, expira_em > NOW() AS no_prazo FROM inscricoes WHERE codigo = :c LIMIT 1');
    $st->execute([':c' => $codigo]);
    $insc = $st->fetch();
    if (!$insc) {
        $responder('erro', 'Inscrição não encontrada.', 404);
    }
    if ($insc['status'] === 'pago') {
        $responder('erro', 'Esta inscrição já está paga.');
    }
    if ($insc['status'] !== 'pendente' || empty($insc['expira_em']) || !(int) $insc['no_prazo']) {
        $responder('erro', 'O prazo de pagamento terminou.');
    }

    // Já há cartão aprovado ou em análise (a confirmação pode estar a caminho): não cobra de novo
    $em_curso = $pdo->prepare("SELECT COUNT(*) FROM pagamentos WHERE inscricao_id = :id AND metodo <> 'pix'
                                AND status IN ('approved', 'in_process', 'authorized', 'pending')");
    $em_curso->execute([':id' => (int) $insc['id']]);
    if ((int) $em_curso->fetchColumn() > 0) {
        $responder('in_process', 'Já existe um pagamento com cartão em análise para esta inscrição. Aguarde a confirmação por e-mail antes de tentar de novo.');
    }

    $r = mp_criar_cartao($cfg, $insc, ['installments' => $parcelas] + $form);
    if ($r['http'] === 0) {
        // Sem resposta: o Mercado Pago pode ter cobrado. Não convidar a cobrar de novo; o aviso confirma se foi aprovado
        error_log('Pedal Solidário – cartão da inscrição ' . $codigo . ' sem resposta: ' . ($r['erro'] ?? '?'));
        $responder('erro', 'Não conseguimos confirmar o pagamento com o Mercado Pago. Aguarde alguns minutos antes de tentar de novo: '
            . 'se o pagamento foi aprovado, você recebe a confirmação por e-mail.');
    }
    if (!is_array($r['dados']) || empty($r['dados']['id'])) {
        error_log('Pedal Solidário – cartão da inscrição ' . $codigo . ': ' . ($r['erro'] ?? 'resposta inesperada'));
        $responder('rejected', 'Pagamento recusado. Confira os dados ou tente outro cartão.');
    }
    $status = (string) ($r['dados']['status'] ?? '');
    try {
        $resultado = pagamento_processar($pdo, $r['dados'], (int) (evento()['inscricoes_vagas'] ?? 0), email_site_base($_SERVER), email_config());
    } catch (Throwable $e) {
        // O cartão já foi cobrado: a confirmação vem pela consulta da página ou pelo aviso do Mercado Pago
        error_log('Pedal Solidário – cartão cobrado, confirmação pendente (' . $codigo . '): ' . $e->getMessage());
        $responder('in_process', 'Pagamento enviado ao Mercado Pago. Aguardando a confirmação; não é preciso pagar de novo.');
    }
} catch (Throwable $e) {
    error_log('Pedal Solidário – pagamento com cartão: ' . $e->getMessage());
    $responder('erro', 'Não foi possível concluir o pagamento agora. Tente novamente em instantes.', 500);
}

if ($status === 'approved') {
    $responder('approved', in_array($resultado, ['pago', 'pago_atrasado', 'repetido'], true)
        ? 'Pagamento aprovado!' : 'Pagamento aprovado. A organização vai entrar em contato.');
}
if (in_array($status, ['in_process', 'pending', 'authorized'], true)) {
    $responder('in_process', 'Pagamento em análise pelo Mercado Pago. Avisaremos por e-mail assim que for aprovado.');
}
$responder('rejected', 'Pagamento recusado. Confira os dados ou tente outro cartão.');
