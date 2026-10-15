<?php
session_start();
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/cupons.php';

$ev         = evento();
$cam        = $ev['camiseta'];
$cam_aberta = camiseta_aberta();
$estados    = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

$erros = [];
$old   = [];

/* ------------------------------------------------------------------
   Processamento do formulário (validação sempre refeita no servidor)
   ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach (['categoria','nome','cpf','nascimento','sexo','email','telefone','cidade','uf','equipe',
              'emerg_nome','emerg_tel','saude','camiseta_tamanho','cupom'] as $campo) {
        $old[$campo] = post_txt($campo);
    }
    $old['quero_camiseta']     = !empty($_POST['quero_camiseta']);
    $old['aceite_regulamento'] = !empty($_POST['aceite_regulamento']);
    $old['aceite_termo']       = !empty($_POST['aceite_termo']);

    // Normaliza espaços repetidos
    foreach (['nome','cidade','equipe','emerg_nome'] as $campo) {
        $old[$campo] = trim(preg_replace('/\s+/u', ' ', $old[$campo]));
    }

    if (!$ev['inscricoes_abertas']) {
        $erros[] = 'As inscrições estão encerradas.';
    }
    if (!csrf_valido(post_txt('csrf'))) {
        $erros[] = 'Sua sessão expirou. Recarregue a página e envie o formulário novamente.';
    }

    // Categoria (o valor vem sempre do servidor, nunca do navegador)
    $categoria = categoria_por_slug($old['categoria']);
    if (!$categoria) {
        $erros[] = 'Escolha uma categoria.';
    }

    if (mb_strlen($old['nome']) < 5 || mb_strlen($old['nome']) > 150 || strpos($old['nome'], ' ') === false) {
        $erros[] = 'Informe o nome completo.';
    }
    if (!cpf_valido($old['cpf'])) {
        $erros[] = 'CPF inválido. Confira os números.';
    }

    $nasc = DateTime::createFromFormat('!Y-m-d', $old['nascimento']);
    $hoje = new DateTime('today');
    if (!$nasc || $nasc->format('Y-m-d') !== $old['nascimento'] || $nasc > $hoje || (int) $nasc->format('Y') < 1900) {
        $erros[] = 'Data de nascimento inválida.';
    }

    if (!in_array($old['sexo'], ['M', 'F'], true)) {
        $erros[] = 'Informe o sexo (usado na classificação).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 150) {
        $erros[] = 'E-mail inválido.';
    }
    $tel = so_digitos($old['telefone']);
    if (strlen($tel) < 10 || strlen($tel) > 11) {
        $erros[] = 'Informe um WhatsApp válido, com DDD.';
    }
    if (mb_strlen($old['cidade']) < 2 || mb_strlen($old['cidade']) > 100) {
        $erros[] = 'Informe a cidade.';
    }
    if (!in_array($old['uf'], $estados, true)) {
        $erros[] = 'Escolha o estado.';
    }
    if (mb_strlen($old['equipe']) > 80) {
        $erros[] = 'O nome da equipe deve ter no máximo 80 caracteres.';
    }
    if (mb_strlen($old['emerg_nome']) < 3 || mb_strlen($old['emerg_nome']) > 150) {
        $erros[] = 'Informe o nome do contato de emergência.';
    }
    $tel_em = so_digitos($old['emerg_tel']);
    if (strlen($tel_em) < 10 || strlen($tel_em) > 11) {
        $erros[] = 'Informe um telefone válido para o contato de emergência.';
    }
    if (mb_strlen($old['saude']) > 300) {
        $erros[] = 'O campo de saúde deve ter no máximo 300 caracteres.';
    }
    if (!$old['aceite_regulamento'] || !$old['aceite_termo']) {
        $erros[] = 'Para se inscrever, aceite o regulamento e o termo de responsabilidade.';
    }

    // Camiseta: só dentro do prazo, e com tamanho válido
    $camiseta_tamanho = null;
    $valor_camiseta   = 0.0;
    if ($old['quero_camiseta']) {
        if (!$cam_aberta) {
            $erros[] = 'O prazo para comprar a camiseta terminou em ' . data_br($cam['prazo_iso']) . '.';
        } elseif (!in_array($old['camiseta_tamanho'], $cam['tamanhos'], true)) {
            $erros[] = 'Escolha o tamanho da camiseta.';
        } else {
            $camiseta_tamanho = $old['camiseta_tamanho'];
            $valor_camiseta   = (float) $cam['valor'];
        }
    }

    // Cupom (opcional): vale só para a inscrição, nunca para a camiseta
    $cupom_codigo = cupom_normalizar($old['cupom']);
    if ($old['cupom'] !== '' && $cupom_codigo === '') {
        $erros[] = 'Cupom inválido.';
    }

    // Grava no banco
    if (!$erros) {
        $valor_inscricao = (float) $categoria['valor'];
        $cpf             = so_digitos($old['cpf']);
        $pdo             = null;

        try {
            $pdo = db();

            // 1) Conferência prévia do cupom (as falhas ficam registradas para limitar tentativas)
            if ($cupom_codigo !== '') {
                if (cupom_bloqueado($pdo)) {
                    $erros[] = 'Muitas tentativas de cupom. Aguarde alguns minutos e tente de novo.';
                } else {
                    [$cupom_teste, $msg_cupom] = cupom_para_uso($pdo, $cupom_codigo);
                    if (!$cupom_teste) {
                        cupom_registrar_falha($pdo);
                        $erros[] = $msg_cupom;
                    }
                }
            }

            if (!$erros) {
                $existe = $pdo->prepare('SELECT id FROM inscricoes WHERE cpf = :cpf LIMIT 1');
                $existe->execute([':cpf' => $cpf]);
                if ($existe->fetch()) {
                    $erros[] = 'Já existe uma inscrição com este CPF. Se precisar de ajuda, fale com a organização.';
                }
            }

            if (!$erros) {
                // 2) Transação: trava o cupom, reconfere a quantidade e grava a inscrição
                $pdo->beginTransaction();

                $cupom          = null;
                $valor_desconto = 0.0;
                if ($cupom_codigo !== '') {
                    [$cupom, $msg_cupom] = cupom_para_uso($pdo, $cupom_codigo, true);
                    if (!$cupom) {
                        $erros[] = $msg_cupom;
                    } else {
                        $valor_desconto = cupom_desconto($cupom, $valor_inscricao);
                    }
                }

                $codigo = null;
                if (!$erros) {
                    // Taxa sobre o valor cheio; cupom só na inscrição; cupom de 100% isenta a taxa; camiseta fora de tudo isso
                    $calculo     = calcular_valores($valor_inscricao, $valor_desconto, $valor_camiseta);
                    $valor_taxa  = $calculo['taxa'];
                    $valor_total = $calculo['total'];
                    // Total zerado (ex.: cupom de 100%) não tem o que pagar: já entra como pago
                    $status = $valor_total <= 0 ? 'pago' : 'pendente';
                    $pago_em = $status === 'pago' ? date('Y-m-d H:i:s') : null;

                    $sql = 'INSERT INTO inscricoes
                            (codigo, nome, cpf, nascimento, sexo, email, telefone, cidade, uf, categoria, equipe,
                             camiseta_tamanho, emerg_nome, emerg_tel, saude, aceite_regulamento, aceite_termo,
                             valor_inscricao, valor_desconto, cupom_id, cupom_codigo, valor_taxa, valor_camiseta, valor_total, status, pago_em)
                            VALUES
                            (:codigo, :nome, :cpf, :nascimento, :sexo, :email, :telefone, :cidade, :uf, :categoria, :equipe,
                             :camiseta_tamanho, :emerg_nome, :emerg_tel, :saude, 1, 1,
                             :valor_inscricao, :valor_desconto, :cupom_id, :cupom_codigo, :valor_taxa, :valor_camiseta, :valor_total, :status, :pago_em)';
                    $stmt = $pdo->prepare($sql);

                    for ($tentativa = 0; $tentativa < 5 && $codigo === null; $tentativa++) {
                        $candidato = strtoupper(bin2hex(random_bytes(5))); // 10 caracteres
                        try {
                            $stmt->execute([
                                ':codigo'           => $candidato,
                                ':nome'             => $old['nome'],
                                ':cpf'              => $cpf,
                                ':nascimento'       => $old['nascimento'],
                                ':sexo'             => $old['sexo'],
                                ':email'            => mb_strtolower($old['email']),
                                ':telefone'         => $tel,
                                ':cidade'           => $old['cidade'],
                                ':uf'               => $old['uf'],
                                ':categoria'        => $categoria['slug'],
                                ':equipe'           => $old['equipe'] !== '' ? $old['equipe'] : null,
                                ':camiseta_tamanho' => $camiseta_tamanho,
                                ':emerg_nome'       => $old['emerg_nome'],
                                ':emerg_tel'        => $tel_em,
                                ':saude'            => $old['saude'] !== '' ? $old['saude'] : null,
                                ':valor_inscricao'  => number_format($valor_inscricao, 2, '.', ''),
                                ':valor_desconto'   => number_format($valor_desconto, 2, '.', ''),
                                ':cupom_id'         => $cupom ? (int) $cupom['id'] : null,
                                ':cupom_codigo'     => $cupom ? $cupom['codigo'] : null,
                                ':valor_taxa'       => number_format($valor_taxa, 2, '.', ''),
                                ':valor_camiseta'   => number_format($valor_camiseta, 2, '.', ''),
                                ':valor_total'      => number_format($valor_total, 2, '.', ''),
                                ':status'           => $status,
                                ':pago_em'          => $pago_em,
                            ]);
                            $codigo = $candidato;
                        } catch (PDOException $e) {
                            $dup = ($e->errorInfo[1] ?? 0) === 1062;
                            if ($dup && stripos($e->getMessage(), 'cpf') !== false) {
                                $erros[] = 'Já existe uma inscrição com este CPF. Se precisar de ajuda, fale com a organização.';
                                break;
                            }
                            if (!$dup || stripos($e->getMessage(), 'codigo') === false) {
                                throw $e;
                            }
                            // código repetido (raríssimo): tenta outro
                        }
                    }
                }

                if ($codigo !== null) {
                    $pdo->commit();
                    unset($_SESSION['csrf']);
                    header('Location: inscricao_recebida.php?c=' . $codigo);
                    exit;
                }

                $pdo->rollBack();
                if (!$erros) {
                    $erros[] = 'Não foi possível concluir a inscrição agora. Tente novamente em instantes.';
                }
            }
        } catch (PDOException $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Pedal Solidário – erro no banco: ' . $e->getMessage());
            $erros[] = 'Não foi possível salvar sua inscrição agora. Tente novamente em instantes.';
            if (banco_debug()) {
                $erros[] = 'Detalhe técnico (somente no ambiente local): ' . $e->getMessage();
            }
        }
    }
}

/* Auxiliares de exibição */
function old(string $k): string { global $old; return e($old[$k] ?? ''); }
function marcado(string $k): string { global $old; return !empty($old[$k]) ? ' checked' : ''; }
function escolhido(string $k, string $v): string { global $old; return (($old[$k] ?? '') === $v) ? ' selected' : ''; }

$cam_arte   = imagem_opcional($cam['arte']);
$cam_tabela = imagem_opcional($cam['tabela']);

$pagina = 'inscricao';
$titulo = 'Inscrição';
$descricao = 'Faça sua inscrição no 2º Pedal Solidário do Lions Clube de Cornélio Procópio.';
require __DIR__ . '/includes/header.php';
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

            <?php if ($erros): ?>
                <div id="erros" class="aviso aviso--erro" role="alert" tabindex="-1">
                    <strong>Confira os pontos abaixo e envie novamente:</strong>
                    <ul>
                        <?php foreach ($erros as $erro): ?>
                            <li><?= e($erro) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="inscricao">
                <form id="form-inscricao" class="form" method="post" action="inscricao.php" novalidate data-data-evento="<?= e($ev['data_iso']) ?>" data-taxa="<?= e(number_format(taxa_percentual(), 2, '.', '')) ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                    <fieldset>
                        <legend>Dados do participante</legend>
                        <div class="campos">
                            <div class="campo campo--cheio">
                                <label for="nome">Nome completo</label>
                                <input type="text" id="nome" name="nome" autocomplete="name" maxlength="150" value="<?= old('nome') ?>" required>
                            </div>
                            <div class="campo">
                                <label for="cpf">CPF</label>
                                <input type="text" id="cpf" name="cpf" inputmode="numeric" maxlength="14" placeholder="000.000.000-00" value="<?= old('cpf') ?>" required>
                                <span class="erro" aria-live="polite"></span>
                            </div>
                            <div class="campo">
                                <label for="nascimento">Data de nascimento</label>
                                <input type="date" id="nascimento" name="nascimento" autocomplete="bday" value="<?= old('nascimento') ?>" required>
                            </div>
                            <div class="campo">
                                <label for="sexo">Sexo</label>
                                <select id="sexo" name="sexo" required>
                                    <option value="">Selecione</option>
                                    <option value="M"<?= escolhido('sexo', 'M') ?>>Masculino</option>
                                    <option value="F"<?= escolhido('sexo', 'F') ?>>Feminino</option>
                                </select>
                                <span class="dica">Usado para a classificação por gênero.</span>
                            </div>
                            <div class="campo">
                                <label for="email">E-mail</label>
                                <input type="email" id="email" name="email" autocomplete="email" maxlength="150" value="<?= old('email') ?>" required>
                            </div>
                            <div class="campo">
                                <label for="telefone">WhatsApp</label>
                                <input type="tel" id="telefone" name="telefone" autocomplete="tel" inputmode="tel" placeholder="(43) 90000-0000" value="<?= old('telefone') ?>" required>
                            </div>
                            <div class="campo">
                                <label for="cidade">Cidade</label>
                                <input type="text" id="cidade" name="cidade" autocomplete="address-level2" maxlength="100" value="<?= old('cidade') ?>" required>
                            </div>
                            <div class="campo">
                                <label for="uf">Estado</label>
                                <select id="uf" name="uf" autocomplete="address-level1" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($estados as $uf): ?>
                                        <?php $sel = isset($old['uf']) ? ($old['uf'] === $uf) : ($uf === $ev['uf']); ?>
                                        <option value="<?= e($uf) ?>"<?= $sel ? ' selected' : '' ?>><?= e($uf) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="campo campo--cheio">
                                <label for="equipe">Nome da equipe <span class="dica">(opcional)</span></label>
                                <input type="text" id="equipe" name="equipe" maxlength="80" value="<?= old('equipe') ?>">
                                <span class="dica">Para disputar a premiação de equipes, todos devem escrever o nome exatamente igual.</span>
                            </div>
                        </div>
                    </fieldset>

                    <div id="aviso-menor" class="aviso" hidden>
                        <strong>Participante menor de 18 anos.</strong> É obrigatório entregar a autorização assinada pelo responsável legal na retirada do kit.
                    </div>

                     <fieldset>
                        <legend>Categoria</legend>
                        <div class="opcoes">
                            <?php foreach ($ev['categorias'] as $i => $cat): ?>
                                <label class="opcao">
                                    <input type="radio" name="categoria" value="<?= e($cat['slug']) ?>"
                                           data-nome="<?= e($cat['nome']) ?>" data-valor="<?= e(number_format($cat['valor'], 2, '.', '')) ?>"
                                           <?= $i === 0 ? 'required' : '' ?><?= (($old['categoria'] ?? '') === $cat['slug']) ? ' checked' : '' ?>>
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
                                    <p class="dica">Compra disponível até <strong><?= e(data_br($cam['prazo_iso'])) ?></strong>. Depois dessa data não será possível adicionar a camiseta. Limite de uma camiseta por atleta.</p>

                                    <label class="aceite">
                                        <input type="checkbox" id="quero-camiseta" name="quero_camiseta" value="1"
                                               data-valor="<?= e(number_format($cam['valor'], 2, '.', '')) ?>"<?= marcado('quero_camiseta') ?>>
                                        <span>Quero comprar a camiseta oficial</span>
                                    </label>

                                    <div id="camiseta-tamanho" class="campo"<?= empty($old['quero_camiseta']) ? ' hidden' : '' ?>>
                                        <label for="tamanho">Tamanho</label>
                                        <select id="tamanho" name="camiseta_tamanho">
                                            <option value="">Selecione o tamanho</option>
                                            <?php foreach ($cam['tamanhos'] as $t): ?>
                                                <option value="<?= e($t) ?>"<?= escolhido('camiseta_tamanho', $t) ?>><?= e($t) ?></option>
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
                                <input type="text" id="emerg_nome" name="emerg_nome" maxlength="150" value="<?= old('emerg_nome') ?>" required>
                            </div>
                            <div class="campo">
                                <label for="emerg_tel">Telefone do contato</label>
                                <input type="tel" id="emerg_tel" name="emerg_tel" inputmode="tel" placeholder="(43) 90000-0000" value="<?= old('emerg_tel') ?>" required>
                            </div>
                            <div class="campo campo--cheio">
                                <label for="saude">Alergias ou condições de saúde <span class="dica">(opcional)</span></label>
                                <textarea id="saude" name="saude" rows="3" maxlength="300"><?= old('saude') ?></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Cupom de desconto <span class="dica-legenda">(opcional)</span></legend>
                        <div class="cupom">
                            <div class="campo">
                                <label for="cupom">Código do cupom</label>
                                <input type="text" id="cupom" name="cupom" maxlength="30" value="<?= old('cupom') ?>"
                                       autocomplete="off" autocapitalize="characters" spellcheck="false">
                            </div>
                            <button type="button" class="btn btn--azul btn--pequeno" id="aplicar-cupom">Aplicar</button>
                        </div>
                        <p id="cupom-msg" class="cupom__msg" role="status" aria-live="polite"></p>
                        <p class="dica">O desconto vale somente para a inscrição, não para a camiseta.</p>
                    </fieldset>

                    <aside class="resumo" aria-labelledby="resumo-titulo">
                        <h2 id="resumo-titulo">Resumo</h2>
                        <dl>
                            <dt>Evento</dt>
                            <dd><?= e($ev['data_curta']) ?></dd>
                            <dt>Categoria</dt>
                            <dd id="resumo-categoria">Não escolhida</dd>
                            <dt>Inscrição</dt>
                            <dd id="resumo-inscricao">R$ 0,00</dd>
                            <dt>Tx Administração (<?= e(taxa_rotulo()) ?>)</dt>
                            <dd id="resumo-taxa">R$ 0,00</dd>
                            <dt>Desconto (cupom)</dt>
                            <dd id="resumo-desconto">R$ 0,00</dd>
                            <dt>Camiseta</dt>
                            <dd id="resumo-camiseta">Não</dd>
                        </dl>
                        <dl class="total">
                            <dt>Total</dt>
                            <dd id="resumo-valor">R$ 0,00</dd>
                        </dl>
                        <p style="font-size: 0.6em;">A taxa de administração é calculada sobre o valor cheio da inscrição. O cupom abate apenas a inscrição (se cobrir 100%, a taxa também é isenta), e a camiseta não tem taxa nem desconto.</p>
                        <p style="font-size: 0.6em;">A inscrição só é efetivada após a confirmação do pagamento.</p>
                    </aside>

                    <fieldset>
                        <legend>Confirmações</legend>
                        <label class="aceite">
                            <input type="checkbox" name="aceite_regulamento" required<?= marcado('aceite_regulamento') ?>>
                            <span>Li e concordo com o <a href="regulamento.php" target="_blank" rel="noopener">regulamento</a> do evento.</span>
                        </label>
                        <label class="aceite">
                            <input type="checkbox" name="aceite_termo" required<?= marcado('aceite_termo') ?>>
                            <span>Declaro estar apto(a) para a prática de atividade física e assumo os riscos do ciclismo, conforme o termo de responsabilidade do regulamento.</span>
                        </label>
                    </fieldset>

                    <button class="btn btn--azul" type="submit">Enviar inscrição</button>
                </form>

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
