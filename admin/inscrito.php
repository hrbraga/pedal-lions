<?php
require __DIR__ . '/_bootstrap.php';
admin_exigir();

$ev = evento();
$id = (int) ($_GET['id'] ?? 0);
$i  = null;
$erro_banco = null;

try {
    $st = db()->prepare('SELECT * FROM inscricoes WHERE id = :id LIMIT 1');
    $st->execute([':id' => $id]);
    $i = $st->fetch() ?: null;
} catch (PDOException $e) {
    error_log('Pedal Solidário – erro ao abrir inscrição: ' . $e->getMessage());
    $erro_banco = banco_debug() ? $e->getMessage() : 'Não foi possível carregar a inscrição.';
}

if (!$i && !$erro_banco) {
    flash('erro', 'Inscrição não encontrada.');
    header('Location: painel.php');
    exit;
}

$titulo_admin = $i ? $i['nome'] : 'Inscrição';
$menu_ativo = 'inscritos';
require __DIR__ . '/_topo.php';

if ($erro_banco): ?>
    <p class="adm-aviso adm-aviso--erro"><?= e($erro_banco) ?></p>
    <p><a class="adm-btn adm-btn--claro" href="painel.php">Voltar à lista</a></p>
<?php else:
    $cat   = categoria_por_slug($i['categoria']);
    $idade = idade_no_evento($i['nascimento']);
    $wa    = '55' . so_digitos($i['telefone']);
    $voltar_detalhe = 'inscrito.php?id=' . (int) $i['id'];

    function campo_acao(string $acao, string $rotulo, string $classe, string $confirmacao, array $i, string $voltar): void
    { ?>
        <form method="post" action="acao.php" onsubmit="return confirm('<?= e($confirmacao) ?>');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
            <input type="hidden" name="acao" value="<?= e($acao) ?>">
            <input type="hidden" name="voltar" value="<?= e($voltar) ?>">
            <button class="adm-btn <?= e($classe) ?>" type="submit"><?= e($rotulo) ?></button>
        </form>
    <?php } ?>

    <p><a href="painel.php">&larr; Voltar à lista</a></p>

    <div class="adm-titulo-linha">
        <h1><?= e($i['nome']) ?></h1>
        <span class="adm-selo adm-selo--<?= e($i['status']) ?>"><?= e(STATUS_ROTULOS[$i['status']] ?? $i['status']) ?></span>
    </div>

    <?php if ($idade < 18): ?>
        <p class="adm-aviso"><strong>Menor de idade (<?= (int) $idade ?> anos no dia do evento).</strong> Conferir a autorização assinada pelo responsável na retirada do kit.</p>
    <?php endif; ?>

    <div class="adm-acoes">
        <?php if ($i['status'] !== 'pago'): ?>
            <?php campo_acao('pago', 'Confirmar pagamento', '', 'Confirmar o pagamento desta inscrição?', $i, $voltar_detalhe); ?>
        <?php endif; ?>
        <?php if ($i['status'] === 'pago'): ?>
            <?php campo_acao('pendente', 'Voltar para pendente', 'adm-btn--claro', 'Desfazer a confirmação e voltar para pendente?', $i, $voltar_detalhe); ?>
        <?php endif; ?>
        <?php if ($i['status'] !== 'cancelado'): ?>
            <?php campo_acao('cancelado', 'Cancelar inscrição', 'adm-btn--claro', 'Cancelar esta inscrição?', $i, $voltar_detalhe); ?>
        <?php endif; ?>
        <?php campo_acao('excluir', 'Excluir', 'adm-btn--perigo', 'Excluir DEFINITIVAMENTE esta inscrição? Esta ação não pode ser desfeita.', $i, $voltar_detalhe); ?>
    </div>

    <div class="adm-duplo">
        <section>
            <h2>Participante</h2>
            <dl class="adm-dados">
                <dt>Código</dt><dd><code><?= e($i['codigo']) ?></code></dd>
                <dt>CPF</dt><dd><?= e(cpf_formatado($i['cpf'])) ?></dd>
                <dt>Nascimento</dt><dd><?= e(data_br($i['nascimento'])) ?> (<?= (int) $idade ?> anos no evento)</dd>
                <dt>Sexo</dt><dd><?= $i['sexo'] === 'F' ? 'Feminino' : 'Masculino' ?></dd>
                <dt>E-mail</dt><dd><a href="mailto:<?= e($i['email']) ?>"><?= e($i['email']) ?></a></dd>
                <dt>WhatsApp</dt><dd><a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= e(telefone_formatado($i['telefone'])) ?></a></dd>
                <dt>Cidade</dt><dd><?= e($i['cidade']) ?>/<?= e($i['uf']) ?></dd>
                <dt>Equipe</dt><dd><?= $i['equipe'] ? e($i['equipe']) : '—' ?></dd>
            </dl>

            <h2>Emergência e saúde</h2>
            <dl class="adm-dados">
                <dt>Contato</dt><dd><?= e($i['emerg_nome']) ?></dd>
                <dt>Telefone</dt><dd><?= e(telefone_formatado($i['emerg_tel'])) ?></dd>
                <dt>Saúde</dt><dd><?= $i['saude'] ? nl2br(e($i['saude'])) : '—' ?></dd>
            </dl>
        </section>

        <section>
            <h2>Inscrição e pagamento</h2>
            <dl class="adm-dados">
                <dt>Categoria</dt><dd><?= e($cat['nome'] ?? $i['categoria']) ?></dd>
                <dt>Valor da inscrição</dt><dd><?= e(moeda((float) $i['valor_inscricao'])) ?></dd>
                <dt>Camiseta</dt><dd><?= $i['camiseta_tamanho'] ? e(moeda((float) $i['valor_camiseta'])) . ' (tamanho ' . e($i['camiseta_tamanho']) . ')' : 'Não' ?></dd>
                <dt>Total</dt><dd><strong><?= e(moeda((float) $i['valor_total'])) ?></strong></dd>
                <dt>Situação</dt><dd><?= e(STATUS_ROTULOS[$i['status']] ?? $i['status']) ?></dd>
                <dt>Pago em</dt><dd><?= $i['pago_em'] ? e(data_hora_br($i['pago_em'])) : '—' ?></dd>
                <dt>Inscrito em</dt><dd><?= e(data_hora_br($i['criado_em'])) ?></dd>
                <dt>Aceites</dt><dd>Regulamento e termo de responsabilidade aceitos no ato da inscrição</dd>
            </dl>
        </section>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_rodape.php'; ?>
