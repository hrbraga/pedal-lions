<?php
/**
 * Ações sobre uma inscrição: confirmar pagamento, reabrir (voltar a pendente), cancelar, excluir ou
 * enviar de novo o e-mail de pagamento confirmado.
 */
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/email_pagamento.php';
admin_exigir('inscritos');

$voltar = destino_seguro((string) ($_POST['voltar'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: painel.php');
    exit;
}
if (!csrf_valido(post_txt('csrf'))) {
    flash('erro', 'Sessão expirada. Tente novamente.');
    header('Location: ' . $voltar);
    exit;
}

$id   = (int) ($_POST['id'] ?? 0);
$acao = post_txt('acao');

try {
    $pdo = db();
    $st = $pdo->prepare('SELECT id, nome, email, status, pago_em, email_pago_em FROM inscricoes WHERE id = :id');
    $st->execute([':id' => $id]);
    $insc = $st->fetch();

    if (!$insc) {
        flash('erro', 'Inscrição não encontrada.');
        header('Location: painel.php');
        exit;
    }

    switch ($acao) {
        case 'pago':
            $pdo->prepare("UPDATE inscricoes SET status = 'pago', pago_em = COALESCE(pago_em, NOW()) WHERE id = :id")->execute([':id' => $id]);
            // Avisa o atleta por e-mail uma vez só (reconfirmar depois de voltar a pendente não reenvia)
            if (!empty($insc['email_pago_em'])) {
                flash('ok', 'Pagamento confirmado: ' . $insc['nome'] . '. O e-mail de confirmação já tinha sido enviado.');
                break;
            }
            $erro = email_pago_enviar($pdo, $id, email_site_base($_SERVER), email_config());
            if ($erro === null) {
                flash('ok', 'Pagamento confirmado: ' . $insc['nome'] . '. E-mail enviado para ' . $insc['email'] . '.');
            } else {
                flash('erro', 'Pagamento confirmado: ' . $insc['nome'] . '. O e-mail não foi enviado (' . email_motivo_curto($erro) . '). Use "Reenviar e-mail de confirmação" na inscrição.');
            }
            break;

        case 'email_pago':
            if ($insc['status'] !== 'pago') {
                flash('erro', 'Só inscrições pagas recebem o e-mail de confirmação.');
                break;
            }
            $erro = email_pago_enviar($pdo, $id, email_site_base($_SERVER), email_config());
            if ($erro === null) {
                flash('ok', 'E-mail de confirmação enviado para ' . $insc['email'] . '.');
            } else {
                flash('erro', 'O e-mail não foi enviado (' . email_motivo_curto($erro) . ').');
            }
            break;

        case 'pendente':
            $pdo->prepare("UPDATE inscricoes SET status = 'pendente', pago_em = NULL WHERE id = :id")->execute([':id' => $id]);
            flash('ok', 'Inscrição de ' . $insc['nome'] . ' voltou para pendente.');
            break;

        case 'cancelado':
            $pdo->prepare("UPDATE inscricoes SET status = 'cancelado' WHERE id = :id")->execute([':id' => $id]);
            flash('ok', 'Inscrição de ' . $insc['nome'] . ' cancelada.');
            break;

        case 'excluir':
            // Já paga (mesmo cancelada depois): o valor está no financeiro; excluir apagaria o dinheiro dos livros
            if (!empty($insc['pago_em'])) {
                flash('erro', 'Inscrição já paga não pode ser excluída (o valor está no financeiro). Use Cancelar.');
                break;
            }
            $pdo->prepare('DELETE FROM inscricoes WHERE id = :id')->execute([':id' => $id]);
            flash('ok', 'Inscrição de ' . $insc['nome'] . ' excluída definitivamente.');
            header('Location: painel.php');
            exit;

        default:
            flash('erro', 'Ação inválida.');
    }
} catch (PDOException $e) {
    error_log('Pedal Solidário – erro na ação do painel: ' . $e->getMessage());
    flash('erro', 'Não foi possível concluir a ação.' . (banco_debug() ? ' ' . $e->getMessage() : ''));
}

header('Location: ' . $voltar);
exit;
