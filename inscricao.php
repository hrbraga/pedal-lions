<?php
$pagina = 'inscricao';
$titulo = 'Inscrição';
$descricao = 'Faça sua inscrição no 2º Pedal Solidário do Lions Clube de Cornélio Procópio.';
require __DIR__ . '/includes/header.php';

$enviado = ($_SERVER['REQUEST_METHOD'] === 'POST');
$cam      = $ev['camiseta'];
$cam_aberta = camiseta_aberta();
$cam_arte   = imagem_opcional($cam['arte']);
$cam_tabela = imagem_opcional($cam['tabela']);
$estados  = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
?>

<div class="pagina-topo">
    <div class="wrap">
        <h1>Inscrição</h1>
        <p>Preencha seus dados e escolha a categoria. A inscrição só é efetivada após a confirmação do pagamento.</p>
    </div>
</div>

<section class="secao">
    <div class="wrap">

        <?php if (!$ev['inscricoes_abertas']): ?>
            <p class="aviso aviso--erro"><strong>Inscrições encerradas.</strong> Acompanhe as novidades pelos canais oficiais do evento.</p>
        <?php else: ?>

            <?php if ($enviado): ?>
                <p class="aviso"><strong>Etapa em desenvolvimento.</strong> O formulário já está montado, mas a gravação dos dados e o pagamento serão ligados nas próximas etapas. Nada foi salvo.</p>
            <?php endif; ?>

            <div class="inscricao">
                <form id="form-inscricao" class="form" method="post" action="inscricao.php" novalidate data-data-evento="<?= e($ev['data_iso']) ?>">

                    <fieldset>
                        <legend>Categoria</legend>
                        <div class="opcoes">
                            <?php foreach ($ev['categorias'] as $i => $cat): ?>
                                <label class="opcao">
                                    <input type="radio" name="categoria" value="<?= e($cat['slug']) ?>"
                                           data-nome="<?= e($cat['nome']) ?>" data-valor="<?= e(number_format($cat['valor'], 2, '.', '')) ?>"
                                           <?= $i === 0 ? 'required' : '' ?>>
                                    <span class="opcao__corpo">
                                        <span>
                                            <strong><?= e($cat['nome']) ?></strong>
                                            <small><?= e($cat['resumo']) ?></small>
                                        </span>
                                        <span class="opcao__valor"><?= e(moeda($cat['valor'])) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Dados do participante</legend>
                        <div class="campos">
                            <div class="campo campo--cheio">
                                <label for="nome">Nome completo</label>
                                <input type="text" id="nome" name="nome" autocomplete="name" required>
                            </div>
                            <div class="campo">
                                <label for="cpf">CPF</label>
                                <input type="text" id="cpf" name="cpf" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" required>
                                <span class="erro" aria-live="polite"></span>
                            </div>
                            <div class="campo">
                                <label for="nascimento">Data de nascimento</label>
                                <input type="date" id="nascimento" name="nascimento" autocomplete="bday" required>
                            </div>
                            <div class="campo">
                                <label for="sexo">Sexo</label>
                                <select id="sexo" name="sexo" required>
                                    <option value="">Selecione</option>
                                    <option value="M">Masculino</option>
                                    <option value="F">Feminino</option>
                                </select>
                                <span class="dica">Usado para a classificação por gênero.</span>
                            </div>
                            <div class="campo">
                                <label for="email">E-mail</label>
                                <input type="email" id="email" name="email" autocomplete="email" required>
                            </div>
                            <div class="campo">
                                <label for="telefone">WhatsApp</label>
                                <input type="tel" id="telefone" name="telefone" autocomplete="tel" inputmode="tel" placeholder="(43) 90000-0000" required>
                            </div>
                            <div class="campo">
                                <label for="cidade">Cidade</label>
                                <input type="text" id="cidade" name="cidade" autocomplete="address-level2" required>
                            </div>
                            <div class="campo">
                                <label for="uf">Estado</label>
                                <select id="uf" name="uf" autocomplete="address-level1" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($estados as $uf): ?>
                                        <option value="<?= e($uf) ?>"<?= $uf === $ev['uf'] ? ' selected' : '' ?>><?= e($uf) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="campo campo--cheio">
                                <label for="equipe">Nome da equipe <span class="dica">(opcional)</span></label>
                                <input type="text" id="equipe" name="equipe" maxlength="80">
                                <span class="dica">Para disputar a premiação de equipes, todos devem escrever o nome exatamente igual.</span>
                            </div>
                        </div>
                    </fieldset>

                    <div id="aviso-menor" class="aviso" hidden>
                        <strong>Participante menor de 18 anos.</strong> É obrigatório entregar a autorização assinada pelo responsável legal na retirada do kit.
                    </div>

                    <fieldset>
                        <legend>Camiseta oficial <span class="dica-legenda">(opcional)</span></legend>

                        <?php if ($cam_aberta): ?>
                            <div class="camiseta">
                                <div class="camiseta__arte">
                                    <?php if ($cam_arte): ?>
                                        <button type="button" class="camiseta__ampliar" data-modal="modal-arte" aria-label="Ampliar a arte da camiseta">
                                            <img src="<?= e($cam_arte) ?>" alt="Arte da camiseta oficial do evento" loading="lazy">
                                            <span>Clique para ampliar</span>
                                        </button>
                                    <?php else: ?>
                                        <div class="camiseta__vazio">A arte da camiseta será divulgada em breve.</div>
                                    <?php endif; ?>
                                </div>

                                <div class="camiseta__dados">
                                    <p class="camiseta__preco"><?= e(moeda($cam['valor'])) ?> <small>valor à parte da inscrição</small></p>
                                    <p class="dica">Compra disponível até <strong><?= e(data_br($cam['prazo_iso'])) ?></strong>. Depois dessa data não será possível adicionar a camiseta.</p>

                                    <label class="aceite">
                                        <input type="checkbox" id="quero-camiseta" name="quero_camiseta" value="1"
                                               data-valor="<?= e(number_format($cam['valor'], 2, '.', '')) ?>">
                                        <span>Quero comprar a camiseta oficial</span>
                                    </label>

                                    <div id="camiseta-tamanho" class="campo" hidden>
                                        <label for="tamanho">Tamanho</label>
                                        <select id="tamanho" name="camiseta_tamanho">
                                            <option value="">Selecione o tamanho</option>
                                            <?php foreach ($cam['tamanhos'] as $t): ?>
                                                <option value="<?= e($t) ?>"><?= e($t) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="link-botao" data-modal="modal-tabela">Ver tabela de tamanhos</button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="aviso aviso--erro"><strong>Compra de camiseta encerrada.</strong> O prazo terminou em <?= e(data_br($cam['prazo_iso'])) ?>. Você ainda pode concluir a inscrição normalmente.</p>
                        <?php endif; ?>
                    </fieldset>

                    <fieldset>
                        <legend>Emergência e saúde</legend>
                        <div class="campos">
                            <div class="campo">
                                <label for="emerg_nome">Contato de emergência</label>
                                <input type="text" id="emerg_nome" name="emerg_nome" required>
                            </div>
                            <div class="campo">
                                <label for="emerg_tel">Telefone do contato</label>
                                <input type="tel" id="emerg_tel" name="emerg_tel" inputmode="tel" placeholder="(43) 90000-0000" required>
                            </div>
                            <div class="campo campo--cheio">
                                <label for="saude">Alergias ou condições de saúde <span class="dica">(opcional)</span></label>
                                <textarea id="saude" name="saude" rows="3" maxlength="300"></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Confirmações</legend>
                        <label class="aceite">
                            <input type="checkbox" name="aceite_regulamento" required>
                            <span>Li e concordo com o <a href="regulamento.php" target="_blank" rel="noopener">regulamento</a> do evento.</span>
                        </label>
                        <label class="aceite">
                            <input type="checkbox" name="aceite_termo" required>
                            <span>Declaro estar apto(a) para a prática de atividade física e assumo os riscos do ciclismo, conforme o termo de responsabilidade do regulamento.</span>
                        </label>
                    </fieldset>

                    <button class="btn btn--azul" type="submit">Continuar para o pagamento</button>
                </form>

                <aside class="resumo" aria-labelledby="resumo-titulo">
                    <h2 id="resumo-titulo">Resumo</h2>
                    <dl>
                        <dt>Evento</dt>
                        <dd><?= e($ev['data_curta']) ?></dd>
                        <dt>Categoria</dt>
                        <dd id="resumo-categoria">Não escolhida</dd>
                        <dt>Inscrição</dt>
                        <dd id="resumo-inscricao">R$ 0,00</dd>
                        <dt>Camiseta</dt>
                        <dd id="resumo-camiseta">Não</dd>
                    </dl>
                    <dl class="total">
                        <dt>Total</dt>
                        <dd id="resumo-valor">R$ 0,00</dd>
                    </dl>
                    <p>A inscrição só é efetivada após a confirmação do pagamento.</p>
                </aside>
            </div>

        <?php endif; ?>
    </div>
</section>


<?php if ($ev['inscricoes_abertas'] && $cam_aberta): ?>
    <dialog id="modal-arte" class="modal" aria-label="Arte da camiseta">
        <button type="button" class="modal__fechar" data-fechar aria-label="Fechar">&times;</button>
        <?php if ($cam_arte): ?>
            <img src="<?= e($cam_arte) ?>" alt="Arte da camiseta oficial do evento">
        <?php endif; ?>
    </dialog>

    <dialog id="modal-tabela" class="modal" aria-label="Tabela de tamanhos">
        <button type="button" class="modal__fechar" data-fechar aria-label="Fechar">&times;</button>
        <h2>Tabela de tamanhos</h2>
        <?php if ($cam_tabela): ?>
            <img src="<?= e($cam_tabela) ?>" alt="Tabela de medidas dos tamanhos da camiseta">
        <?php else: ?>
            <p>A tabela de tamanhos será divulgada em breve.</p>
        <?php endif; ?>
    </dialog>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
