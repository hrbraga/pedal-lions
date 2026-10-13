<?php
/**
 * Ações sobre uma inscrição: confirmar pagamento, reabrir (voltar a pendente), cancelar ou excluir.
 */
require __DIR__ . '/_bootstrap.php';
admin_exigir();

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
    $st = $pdo->prepare('SELECT id, nome, status FROM inscricoes WHERE id = :id');
    $st->execute([':id' => $id]);
    $insc = $st->fetch();

    if (!$insc) {
        flash('erro', 'Inscrição não encontrada.');
        header('Location: painel.php');
        exit;
    }

    switch ($acao) {
        case 'pago':
            $pdo->prepare("UPDATE inscricoes SET status = 'pago', pago_em = NOW() WHERE id = :id")->execute([':id' => $id]);
            flash('ok', 'Pagamento confirmado: ' . $insc['nome'] . '.');
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
