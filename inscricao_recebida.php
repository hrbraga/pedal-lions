<?php
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/db.php';

$ev = evento();
$inscricao = null;
$falha = false;

$codigo = strtoupper(trim((string) ($_GET['c'] ?? '')));
if (preg_match('/^[A-F0-9]{10}$/', $codigo)) {
    try {
        $st = db()->prepare('SELECT codigo, nome, categoria, camiseta_tamanho, valor_inscricao, valor_camiseta, valor_total, status
                             FROM inscricoes WHERE codigo = :c LIMIT 1');
        $st->execute([':c' => $codigo]);
        $inscricao = $st->fetch() ?: null;
    } catch (PDOException $e) {
        error_log('Pedal Solidário – erro ao consultar inscrição: ' . $e->getMessage());
        $falha = true;
    }
}

$pagina = 'inscricao';
$titulo = 'Inscrição recebida';
require __DIR__ . '/includes/header.php';

$rotulos = ['pendente' => 'Aguardando pagamento', 'pago' => 'Pagamento confirmado', 'cancelado' => 'Cancelada'];
?>

<div class="pagina-topo">
    <div class="wrap">
        <h1><?= $inscricao ? 'Inscrição recebida' : 'Inscrição não encontrada' ?></h1>
        <p><?= $inscricao ? 'Seus dados foram registrados. Falta só o pagamento para efetivar a inscrição.' : 'Confira o link ou volte para a página de inscrição.' ?></p>
    </div>
</div>

<section class="secao">
    <div class="wrap">
        <?php if ($inscricao): ?>
            <?php $cat = categoria_por_slug($inscricao['categoria']); ?>

            <p class="aviso"><strong>Sua vaga ainda não está garantida.</strong> A inscrição só é efetivada após a confirmação do pagamento. As instruções de pagamento serão divulgadas em breve e enviadas ao seu e-mail.</p>

            <div class="recibo">
                <p class="recibo__codigo">Código da inscrição <strong><?= e($inscricao['codigo']) ?></strong></p>
                <dl class="recibo__dados">
                    <dt>Participante</dt>
                    <dd><?= e($inscricao['nome']) ?></dd>
                    <dt>Categoria</dt>
                    <dd><?= e($cat['nome'] ?? $inscricao['categoria']) ?></dd>
                    <dt>Inscrição</dt>
                    <dd><?= e(moeda((float) $inscricao['valor_inscricao'])) ?></dd>
                    <dt>Camiseta</dt>
                    <dd>
                        <?php if ($inscricao['camiseta_tamanho']): ?>
                            <?= e(moeda((float) $inscricao['valor_camiseta'])) ?> (tamanho <?= e($inscricao['camiseta_tamanho']) ?>)
                        <?php else: ?>
                            Não
                        <?php endif; ?>
                    </dd>
                    <dt>Total</dt>
                    <dd class="recibo__total"><?= e(moeda((float) $inscricao['valor_total'])) ?></dd>
                    <dt>Situação</dt>
                    <dd><span class="selo selo--<?= e($inscricao['status']) ?>"><?= e($rotulos[$inscricao['status']] ?? $inscricao['status']) ?></span></dd>
                </dl>
                <p class="dica">Guarde este código. Ele identifica a sua inscrição junto à organização.</p>
            </div>

            <p><a class="btn btn--azul" href="index.php">Voltar ao início</a></p>

        <?php elseif ($falha): ?>
            <p class="aviso aviso--erro">Não foi possível consultar a inscrição agora. Tente novamente em instantes.</p>
            <p><a class="btn btn--azul" href="index.php">Voltar ao início</a></p>
        <?php else: ?>
            <p class="aviso aviso--erro">Não encontramos uma inscrição com esse código.</p>
            <p><a class="btn btn--azul" href="inscricao.php">Ir para a inscrição</a></p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
