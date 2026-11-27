<?php
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/pagamento.php';
admin_exigir('inscritos');

$ev = evento();
$id = (int) ($_GET['id'] ?? 0);
$i  = null;
$erro_banco = null;

try {
    $st = db()->prepare('SELECT * FROM inscricoes WHERE id = :id LIMIT 1');
    $st->execute([':id' => $id]);
    $i = $st->fetch() ?: null;
    // Pagamento online que quitou (forma e parcelas)
    $pag_online = null;
    if ($i && !empty($i['pagamento_mp_id'])) {
        $sp = db()->prepare('SELECT metodo, parcelas FROM pagamentos WHERE mp_id = :mp LIMIT 1');
        $sp->execute([':mp' => $i['pagamento_mp_id']]);
        $pag_online = $sp->fetch() ?: null;
    }
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
        <?php // O texto vira string JavaScript (json_encode) antes do escape HTML: apóstrofo no e-mail não quebra nem injeta código ?>
        <form method="post" action="acao.php" onsubmit="return confirm(<?= e(json_encode($confirmacao, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)) ?>);">
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

    <?php if (!empty($i['pagamento_alerta'])): ?>
        <p class="adm-aviso adm-aviso--erro" role="alert"><strong>Atenção:</strong> <?= e($i['pagamento_alerta']) ?>. Confira no painel do Mercado Pago.</p>
    <?php endif; ?>

    <?php if ($idade < 18): ?>
        <p class="adm-aviso"><strong>Menor de idade (<?= (int) $idade ?> anos no dia do evento).</strong> Conferir a autorização assinada pelo responsável na retirada do kit.</p>
    <?php endif; ?>

    <div class="adm-acoes">
        <?php if ($i['status'] !== 'pago'): ?>
            <?php campo_acao('pago', 'Confirmar pagamento', '', 'Confirmar o pagamento desta inscrição?', $i, $voltar_detalhe); ?>
        <?php endif; ?>
        <?php if ($i['status'] === 'pago'): /* expirada não tem "Voltar para pendente": só confirmar (com vaga) ou excluir */ ?>
            <?php campo_acao('email_pago', empty($i['email_pago_em']) ? 'Enviar e-mail de confirmação' : 'Reenviar e-mail de confirmação', 'adm-btn--claro',
                'Enviar o e-mail de confirmação para ' . $i['email'] . '?', $i, $voltar_detalhe); ?>
            <?php campo_acao('pendente', 'Voltar para pendente', 'adm-btn--claro', 'Desfazer a confirmação e voltar para pendente?', $i, $voltar_detalhe); ?>
        <?php endif; ?>
        <?php if ($i['status'] !== 'cancelado'): ?>
            <?php campo_acao('cancelado', 'Cancelar inscrição', 'adm-btn--claro', 'Cancelar esta inscrição?', $i, $voltar_detalhe); ?>
        <?php endif; ?>
        <?php if (empty($i['pago_em'])): ?>
            <?php campo_acao('excluir', 'Excluir', 'adm-btn--perigo', 'Excluir DEFINITIVAMENTE esta inscrição? Esta ação não pode ser desfeita.', $i, $voltar_detalhe); ?>
        <?php endif; ?>
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
                <dt>Taxa de administração</dt><dd><?= e(moeda((float) $i['valor_taxa'])) ?></dd>
                <dt>Cupom</dt><dd><?= !empty($i['cupom_codigo']) ? '<code>' . e($i['cupom_codigo']) . '</code> (desconto de ' . e(moeda((float) $i['valor_desconto'])) . ')' : '—' ?></dd>
                <dt>Camiseta</dt><dd><?= $i['camiseta_tamanho'] ? e(moeda((float) $i['valor_camiseta'])) . ' (' . (!empty($i['camiseta_modelo']) ? e(camiseta_modelo_nome($i['camiseta_modelo'])) . ', ' : '') . 'tamanho ' . e($i['camiseta_tamanho']) . ')' : 'Não' ?></dd>
                <dt>Total</dt><dd><strong><?= e(moeda((float) $i['valor_total'])) ?></strong></dd>
                <dt>Situação</dt><dd><?= e(STATUS_ROTULOS[$i['status']] ?? $i['status']) ?></dd>
                <dt>Pago em</dt><dd><?= $i['pago_em'] ? e(data_hora_br($i['pago_em'])) : '—' ?></dd>
                <?php if (!empty($i['pagamento_mp_id'])): ?>
                    <dt>Pagamento</dt><dd><?php $forma = pagamento_forma_texto($pag_online['metodo'] ?? null, $pag_online['parcelas'] ?? null); ?><?= $forma !== '' ? e($forma) . ' · ' : '' ?>Mercado Pago <code><?= e($i['pagamento_mp_id']) ?></code></dd>
                <?php endif; ?>
                <?php if (!empty($i['expira_em']) && in_array($i['status'], ['pendente', 'expirado'], true)): ?>
                    <dt>Prazo para pagar</dt><dd><?= e(data_hora_br($i['expira_em'])) ?><?= $i['status'] === 'expirado' ? ' (vencido)' : '' ?></dd>
                <?php endif; ?>
                <?php if (!empty($i['email_instr_em']) || !empty($i['email_instr_erro'])): ?>
                    <dt>E-mail de instruções</dt>
                    <dd><?php if (!empty($i['email_instr_em'])): ?>enviado em <?= e(data_hora_br($i['email_instr_em'])) ?>
                        <?php else: ?><span class="adm-selo adm-selo--alerta">não enviado</span> — <?= e($i['email_instr_erro']) ?><?php endif; ?></dd>
                <?php endif; ?>
                <?php if ($i['status'] === 'pago'): ?>
                    <dt>E-mail de confirmação</dt>
                    <dd><?php if (!empty($i['email_pago_em'])): ?>enviado em <?= e(data_hora_br($i['email_pago_em'])) ?>
                        <?php else: ?><span class="adm-selo adm-selo--alerta">não enviado</span><?= !empty($i['email_pago_erro']) ? ' — ' . e($i['email_pago_erro']) : '' ?><?php endif; ?></dd>
                <?php endif; ?>
                <dt>Inscrito em</dt><dd><?= e(data_hora_br($i['criado_em'])) ?></dd>
                <dt>Aceites</dt><dd>Regulamento e termo de responsabilidade aceitos no ato da inscrição</dd>
            </dl>
        </section>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_rodape.php'; ?>
