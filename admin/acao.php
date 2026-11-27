<?php
/**
 * Ações sobre uma inscrição: confirmar pagamento, reabrir (voltar a pendente), cancelar, excluir ou
 * enviar de novo o e-mail de pagamento confirmado.
 */
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/email_pagamento.php';
require_once __DIR__ . '/../includes/pagamento.php';
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
    $st = $pdo->prepare('SELECT id, nome, email, cpf, status, pago_em, email_pago_em FROM inscricoes WHERE id = :id');
    $st->execute([':id' => $id]);
    $insc = $st->fetch();

    if (!$insc) {
        flash('erro', 'Inscrição não encontrada.');
        header('Location: painel.php');
        exit;
    }

    switch ($acao) {
        case 'pago':
            // Expirada que pagou por fora: só volta se ainda houver vaga e o CPF não tiver outra inscrição ativa
            $trava = false;
            if ($insc['status'] === 'expirado') {
                $trava = travar_vagas($pdo);
                $motivo = !$trava ? 'Muitas operações ao mesmo tempo. Tente novamente em instantes.'
                    : (!pagamento_tem_vaga($pdo, (int) (evento()['inscricoes_vagas'] ?? 0)) ? 'Não há vaga para confirmar esta inscrição.'
                    : (pagamento_cpf_ativo($pdo, (string) $insc['cpf'], $id) ? 'Este CPF já tem outra inscrição ativa.' : null));
                if ($motivo !== null) {
                    liberar_trava_vagas($pdo, $trava);
                    flash('erro', $motivo);
                    break;
                }
            }
            $pdo->prepare("UPDATE inscricoes SET status = 'pago', pago_em = COALESCE(pago_em, NOW()) WHERE id = :id")->execute([':id' => $id]);
            liberar_trava_vagas($pdo, $trava);
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
            // Pendente feita à mão no painel não expira (expira_em = NULL)
            $pdo->prepare("UPDATE inscricoes SET status = 'pendente', pago_em = NULL, expira_em = NULL WHERE id = :id")->execute([':id' => $id]);
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
