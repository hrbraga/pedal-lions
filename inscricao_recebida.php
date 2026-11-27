<?php
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/inscricoes.php';
require_once __DIR__ . '/includes/pagamento.php';
require_once __DIR__ . '/includes/email_pagamento.php';

$ev = evento();
$inscricao = null;
$falha = false;
$pix = null;

$codigo = strtoupper(trim((string) ($_GET['c'] ?? '')));
if (preg_match('/^[A-F0-9]{10}$/', $codigo)) {
    try {
        $st = db()->prepare('SELECT id, email, expira_em, codigo, nome, categoria, camiseta_modelo, camiseta_tamanho, valor_inscricao, valor_desconto, cupom_codigo, valor_taxa, valor_camiseta, valor_total, status
                             FROM inscricoes WHERE codigo = :c LIMIT 1');
        $st->execute([':c' => $codigo]);
        $inscricao = $st->fetch() ?: null;
        // Pagou com a página fechada e o aviso do Mercado Pago não chegou? Confere antes de mostrar (no máximo a cada 10 s)
        $cfg_consulta = mp_config();
        if ($inscricao && $cfg_consulta && in_array($inscricao['status'], ['pendente', 'expirado'], true)) {
            try {
                pagamento_consultar(db(), $cfg_consulta, $inscricao, (int) ($ev['inscricoes_vagas'] ?? 0), email_site_base($_SERVER), email_config());
                $st->execute([':c' => $codigo]);
                $inscricao = $st->fetch() ?: null;
            } catch (Throwable $e) {
                error_log('Pedal Solidário – conferência do pagamento: ' . $e->getMessage());
            }
        }
        if ($inscricao && $inscricao['status'] === 'pendente' && !empty($inscricao['expira_em'])) {
            $pix = pagamento_pix_atual(db(), (int) $inscricao['id']);
        }
    } catch (PDOException $e) {
        error_log('Pedal Solidário – erro ao consultar inscrição: ' . $e->getMessage());
        $falha = true;
    }
}

// Pagamento online: só para pendente com prazo e com o Mercado Pago configurado
$cfg_mp   = mp_config();
$online   = $inscricao && $cfg_mp && $inscricao['status'] === 'pendente' && !empty($inscricao['expira_em']);
$expirada = $inscricao && $inscricao['status'] === 'expirado';
$prazo_txt = $online ? date('d/m \à\s H\hi', strtotime((string) $inscricao['expira_em'])) : '';
// E-mail mascarado para a tela de confirmação (m*****@gmail.com)
$email_mascara = '';
if ($inscricao && preg_match('/^(.)(.*)(@.+)$/u', (string) $inscricao['email'], $m)) {
    $email_mascara = $m[1] . str_repeat('*', max(3, mb_strlen($m[2]))) . $m[3];
}
if ($online) {
    $scripts_extra = ['assets/js/pagamento.js'];
}

$pagina = 'inscricao';
$titulo = 'Inscrição recebida';
require __DIR__ . '/includes/header.php';

$rotulos = ['pendente' => 'Aguardando pagamento', 'pago' => 'Pagamento confirmado', 'cancelado' => 'Cancelada', 'expirado' => 'Não paga (prazo encerrado)'];
?>

<div class="pagina-topo">
    <div class="wrap">
        <h1><?= $inscricao ? 'Inscrição recebida' : 'Inscrição não encontrada' ?></h1>
        <p><?= $inscricao ? ($inscricao['status'] === 'pago' ? 'Seus dados foram registrados e a inscrição está confirmada.'
            : ($expirada ? 'O prazo de pagamento desta inscrição terminou.' : 'Seus dados foram registrados. Falta só o pagamento para efetivar a inscrição.'))
            : 'Confira o link ou volte para a página de inscrição.' ?></p>
    </div>
</div>

<section class="secao">
    <div class="wrap">
        <?php if ($inscricao): ?>
            <?php $cat = categoria_por_slug($inscricao['categoria']); ?>

            <?php if ($inscricao['status'] === 'pago'): ?>
                <p class="aviso aviso--ok"><strong>Inscrição confirmada.</strong> Não há valor a pagar.</p>
            <?php elseif ($online): ?>
                <div class="aviso pag-aviso">
                    <p><strong>Sua vaga está reservada até <?= e($prazo_txt) ?>.</strong> Pague com Pix ou cartão de crédito (até 2x) para garantir a inscrição. Depois do prazo, a vaga é liberada.</p>
                    <p><button type="button" class="btn btn--ouro btn--pequeno" data-pag-abrir>Pagar agora</button></p>
                </div>
            <?php elseif ($expirada): ?>
                <div class="aviso aviso--erro">
                    <p><strong>O prazo de pagamento terminou e a vaga foi liberada.</strong> Se ainda quiser participar, faça uma nova inscrição.</p>
                    <?php if (inscricoes_fechamento() === null): ?>
                        <p><a class="btn btn--azul btn--pequeno" href="inscricao.php">Fazer nova inscrição</a></p>
                    <?php endif; ?>
                </div>
            <?php elseif ($inscricao['status'] === 'cancelado'): ?>
                <p class="aviso aviso--erro"><strong>Inscrição cancelada.</strong> Em caso de dúvida, fale com a organização.</p>
            <?php elseif ($cfg_mp): /* pendente sem prazo (feita/reaberta no painel): o pagamento é combinado com a organização */ ?>
                <p class="aviso"><strong>Sua vaga ainda não está garantida.</strong> A inscrição só é efetivada após a confirmação do pagamento. O pagamento desta inscrição é combinado diretamente com a organização.</p>
            <?php else: ?>
                <p class="aviso"><strong>Sua vaga ainda não está garantida.</strong> A inscrição só é efetivada após a confirmação do pagamento. As instruções de pagamento serão divulgadas em breve e enviadas ao seu e-mail.</p>
            <?php endif; ?>

            <div class="recibo">
                <p class="recibo__codigo">Código da inscrição <strong><?= e($inscricao['codigo']) ?></strong></p>
                <dl class="recibo__dados">
                    <dt>Participante</dt>
                    <dd><?= e($inscricao['nome']) ?></dd>
                    <dt>Categoria</dt>
                    <dd><?= e($cat['nome'] ?? $inscricao['categoria']) ?></dd>
                    <dt>Inscrição</dt>
                    <dd><?= e(moeda((float) $inscricao['valor_inscricao'])) ?></dd>
                    <?php if ((float) $inscricao['valor_taxa'] > 0): ?>
                        <dt>Taxa de administração</dt>
                        <dd><?= e(moeda((float) $inscricao['valor_taxa'])) ?></dd>
                    <?php endif; ?>
                    <?php if ((float) $inscricao['valor_desconto'] > 0): ?>
                        <dt>Desconto (cupom <?= e($inscricao['cupom_codigo']) ?>)</dt>
                        <dd>− <?= e(moeda((float) $inscricao['valor_desconto'])) ?></dd>
                    <?php endif; ?>
                    <dt>Camiseta</dt>
                    <dd>
                        <?php if ($inscricao['camiseta_tamanho']): ?>
                            <?php $m = $inscricao['camiseta_modelo'] ? camiseta_modelo($inscricao['camiseta_modelo']) : null; ?>
                            <?= e(moeda((float) $inscricao['valor_camiseta'])) ?> (<?= $m ? e($m['nome']) . ', ' : '' ?>tamanho <?= e($inscricao['camiseta_tamanho']) ?>)
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

            <div class="aviso">
                <p><strong>Inscrição feita? Avise a galera!</strong> Monte sua foto com a moldura do evento e poste no Instagram.</p>
                <p><a class="btn btn--ouro btn--pequeno" href="avatar.php">Montar minha foto “Eu vou!”</a></p>
            </div>

            <p><a class="btn btn--azul" href="./">Voltar ao início</a></p>

        <?php elseif ($falha): ?>
            <p class="aviso aviso--erro">Não foi possível consultar a inscrição agora. Tente novamente em instantes.</p>
            <p><a class="btn btn--azul" href="./">Voltar ao início</a></p>
        <?php else: ?>
            <p class="aviso aviso--erro">Não encontramos uma inscrição com esse código.</p>
            <p><a class="btn btn--azul" href="inscricao.php">Ir para a inscrição</a></p>
        <?php endif; ?>
    </div>
</section>

<?php if ($online): ?>
<div class="pag-modal" id="pag-modal" hidden
     data-codigo="<?= e($inscricao['codigo']) ?>"
     data-expira="<?= e(date('c', strtotime((string) $inscricao['expira_em']))) ?>"
     data-public-key="<?= e($cfg_mp['public_key']) ?>"
     data-total="<?= e(number_format((float) $inscricao['valor_total'], 2, '.', '')) ?>"
     data-abrir="<?= isset($_GET['pagar']) ? '1' : '0' ?>">
    <div class="pag-modal__veu" data-pag-fechar></div>
    <div class="pag-modal__caixa" role="dialog" aria-modal="true" aria-labelledby="pag-titulo">
        <div class="pag-modal__topo">
            <img src="<?= e(asset('assets/img/logo.png')) ?>" alt="" width="36" height="34">
            <div class="pag-modal__titulo">
                <strong id="pag-titulo">Pagamento da inscrição</strong>
                <small>Inscrição <?= e($inscricao['codigo']) ?> · <?= e($inscricao['nome']) ?></small>
            </div>
            <button type="button" class="pag-modal__fechar" data-pag-fechar aria-label="Fechar">×</button>
        </div>

        <div class="pag-modal__corpo" data-pag-etapa="pagar">
            <div class="pag-resumo"><span>Total a pagar</span><b><?= e(moeda((float) $inscricao['valor_total'])) ?></b></div>
            <div class="pag-abas" role="tablist">
                <button type="button" role="tab" id="pag-aba-pix" aria-controls="pag-painel-pix" aria-selected="true">Pix</button>
                <button type="button" role="tab" id="pag-aba-cartao" aria-controls="pag-painel-cartao" aria-selected="false" tabindex="-1">Cartão de crédito</button>
            </div>

            <div class="pag-painel" id="pag-painel-pix" role="tabpanel" aria-labelledby="pag-aba-pix">
                <div data-pag-pix<?= $pix ? '' : ' hidden' ?>>
                    <img class="pag-qr" alt="QR-Code do Pix" width="200" height="200"
                         src="<?= $pix ? 'data:image/png;base64,' . e((string) $pix['qr_base64']) : '' ?>">
                    <p class="pag-prazo">Pague até <b><?= e($prazo_txt) ?></b> · <span data-pag-contagem></span></p>
                    <div class="pag-copia">
                        <input type="text" readonly aria-label="Pix copia e cola" value="<?= e((string) ($pix['copia_cola'] ?? '')) ?>">
                        <button type="button" class="btn btn--ouro" data-pag-copiar>Copiar</button>
                    </div>
                    <ol class="pag-passos">
                        <li>Abra o app do seu banco e escolha Pix.</li>
                        <li>Leia o QR-Code ou cole o código.</li>
                        <li>Pronto: esta tela confirma sozinha.</li>
                    </ol>
                    <p class="pag-esperando"><span class="pag-pulso" aria-hidden="true"></span>Aguardando o pagamento…</p>
                </div>
                <div class="pag-falha" data-pag-sem-pix<?= $pix ? ' hidden' : '' ?>>
                    <p>Não foi possível gerar o Pix agora.</p>
                    <button type="button" class="btn btn--azul" data-pag-tentar>Tentar de novo</button>
                    <p class="pag-nota">Você também pode pagar com cartão de crédito.</p>
                </div>
            </div>

            <div class="pag-painel" id="pag-painel-cartao" role="tabpanel" aria-labelledby="pag-aba-cartao" hidden>
                <div id="pag-cartao-brick"></div>
                <p class="pag-msg" data-pag-cartao-msg role="status" hidden></p>
                <p class="pag-nota">🔒 Os dados do cartão vão direto para o Mercado Pago. Até 2x.</p>
            </div>
        </div>

        <div class="pag-ok" data-pag-etapa="ok" hidden>
            <div class="pag-ok__circ" aria-hidden="true"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7"/></svg></div>
            <h2>Pagamento confirmado!</h2>
            <p>Sua inscrição no <b><?= e($ev['nome']) ?></b> está garantida.</p>
            <span class="pag-ok__codigo"><?= e($inscricao['codigo']) ?></span>
            <p>Enviamos a confirmação para <b><?= e($email_mascara) ?></b>, com o link do grupo do WhatsApp.</p>
            <a class="btn btn--ouro pag-bloco" href="avatar.php">Faça seu avatar!</a>
            <button type="button" class="pag-link" data-pag-recarregar>Fechar</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
