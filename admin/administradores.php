<?php
require __DIR__ . '/_bootstrap.php';
admin_exigir('admins');

$meu_id = (int) $_SESSION['admin_id'];
$eu_total = admin_acesso()['total']; // só quem tem acesso total mexe em quem tem acesso total
$liberaveis = $eu_total ? null : admin_acesso()['paginas']; // páginas que posso liberar a outros (null = todas)
$erros  = [];
$novo_usuario = '';
$novo_acesso  = ['total' => false, 'paginas' => []];

/** Acesso marcado no formulário enviado. Com "Acesso total", as páginas são ignoradas. */
function acesso_do_post(): array
{
    $total = ($_POST['acesso_total'] ?? '') === '1';
    $paginas = is_array($_POST['paginas'] ?? null) ? acesso_paginas_normalizar($_POST['paginas']) : [];
    return ['total' => $total, 'paginas' => $total ? [] : $paginas];
}

/**
 * Caixas "Acesso total" + uma por página. $id_base deixa os ids únicos quando há vários formulários.
 * $liberaveis: páginas que quem está usando pode liberar (null = acesso total, pode tudo). Sem acesso total,
 * "Acesso total" e as páginas que ele não tem aparecem desabilitadas — menos as já marcadas, que o outro já tinha
 * (desabilitada não é enviada e seria tirada sem querer).
 */
function caixas_acesso(string $id_base, bool $total, array $paginas, ?array $liberaveis): string
{
    $pode_dar_total = $liberaveis === null;
    $h = '<fieldset class="adm-checks"><legend>Páginas que pode acessar</legend>';
    $h .= '<label class="adm-checks__total"><input type="checkbox" name="acesso_total" value="1"' . ($total ? ' checked' : '')
        . ($pode_dar_total ? '' : ' disabled') . '> Acesso total <small>('
        . ($pode_dar_total ? 'todas as páginas, inclusive as que forem criadas depois' : 'só quem tem acesso total pode dar')
        . ')</small></label>';
    foreach (ACESSO_PAGINAS as $chave => $p) {
        $marcada = in_array($chave, $paginas, true);
        $travada = !$marcada && $liberaveis !== null && !in_array($chave, $liberaveis, true);
        $h .= '<label><input type="checkbox" name="paginas[]" value="' . e($chave) . '" id="' . e($id_base . '-' . $chave) . '"'
            . ($marcada ? ' checked' : '') . ($travada ? ' disabled' : '') . '> ' . e($p['rotulo'])
            . ($travada ? ' <small>(você não tem essa página)</small>' : '') . '</label>';
    }
    return $h . '</fieldset>';
}

function erros_usuario(string $usuario): array
{
    return preg_match('/^[A-Za-z0-9._-]{3,50}$/', $usuario)
        ? []
        : ['O usuário deve ter de 3 a 50 caracteres (letras, números, ponto, hífen ou sublinhado).'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = post_txt('acao');

    if (!csrf_valido(post_txt('csrf'))) {
        flash('erro', 'Sessão expirada. Tente novamente.');
        header('Location: administradores.php');
        exit;
    }

    try {
        $pdo = db();

        switch ($acao) {

            case 'criar':
                $novo_usuario = trim(post_txt('usuario'));
                $senha        = (string) ($_POST['senha'] ?? '');
                $confirmar    = (string) ($_POST['confirmar'] ?? '');
                $novo_acesso  = acesso_do_post();
                $erros = array_merge(erros_usuario($novo_usuario), erros_senha($senha, $confirmar));
                if (!$novo_acesso['total'] && !$novo_acesso['paginas']) {
                    $erros[] = 'Marque pelo menos uma página ou "Acesso total".';
                }
                if ($e_total = acesso_erro_total($eu_total, false, $novo_acesso['total'])
                    ?? acesso_erro_paginas($eu_total, (array) $liberaveis, [], $novo_acesso['paginas'])) {
                    $erros[] = $e_total;
                }

                if (!$erros) {
                    try {
                        $pdo->prepare('INSERT INTO admins (usuario, senha_hash, acesso_total, paginas, trocar_senha) VALUES (:u, :h, :t, :p, 1)')
                            ->execute([
                                ':u' => $novo_usuario,
                                ':h' => password_hash($senha, PASSWORD_DEFAULT),
                                ':t' => $novo_acesso['total'] ? 1 : 0,
                                ':p' => $novo_acesso['total'] ? '' : acesso_paginas_texto($novo_acesso['paginas']),
                            ]);
                        flash('ok', 'Administrador "' . $novo_usuario . '" criado.');
                        header('Location: administradores.php');
                        exit;
                    } catch (PDOException $e) {
                        if (($e->errorInfo[1] ?? 0) === 1062) {
                            $erros[] = 'Já existe um administrador com esse usuário.';
                        } else {
                            throw $e;
                        }
                    }
                }
                break;

            case 'redefinir':
                $id        = (int) ($_POST['id'] ?? 0);
                $senha     = (string) ($_POST['senha'] ?? '');
                $confirmar = (string) ($_POST['confirmar'] ?? '');
                $erros = erros_senha($senha, $confirmar);

                $st = $pdo->prepare('SELECT usuario, acesso_total FROM admins WHERE id = :id');
                $st->execute([':id' => $id]);
                $alvo = $st->fetch();

                if ($alvo === false) {
                    $erros[] = 'Administrador não encontrado.';
                } elseif ($id === $meu_id) {
                    $erros[] = 'Para trocar a sua própria senha, use a página "Minha conta".';
                } elseif ($e_total = acesso_erro_total($eu_total, (bool) $alvo['acesso_total'], false)) {
                    $erros[] = $e_total;
                }

                if (!$erros) {
                    $pdo->prepare('UPDATE admins SET senha_hash = :h, trocar_senha = 1 WHERE id = :id')
                        ->execute([':h' => password_hash($senha, PASSWORD_DEFAULT), ':id' => $id]);
                    flash('ok', 'Senha de "' . $alvo['usuario'] . '" redefinida. Essa pessoa precisa entrar de novo e criar uma senha própria no primeiro acesso.');
                    header('Location: administradores.php');
                    exit;
                }
                break;

            case 'acesso':
            case 'remover':
                $id = (int) ($_POST['id'] ?? 0);
                // Trava as linhas dos administradores: duas mudanças ao mesmo tempo não podem,
                // juntas, deixar o painel sem ninguém com acesso total.
                $pdo->beginTransaction();
                $todos = $pdo->query('SELECT id, usuario, acesso_total, paginas FROM admins FOR UPDATE')->fetchAll();
                $qtd_total = count(array_filter($todos, fn($a) => (int) $a['acesso_total'] === 1));
                $alvo = null;
                foreach ($todos as $a) {
                    if ((int) $a['id'] === $id) {
                        $alvo = $a;
                    }
                }

                if ($alvo === null) {
                    $erro = 'Administrador não encontrado.';
                } elseif ($acao === 'acesso') {
                    // variável própria: um erro aqui não deve marcar caixas no formulário "Novo administrador"
                    $acesso_pedido = acesso_do_post();
                    $erro = acesso_erro_total($eu_total, (bool) $alvo['acesso_total'], $acesso_pedido['total'])
                        ?? acesso_erro_alterar($meu_id, $id, (bool) $alvo['acesso_total'], $acesso_pedido['total'], $qtd_total)
                        ?? acesso_erro_paginas($eu_total, (array) $liberaveis,
                            acesso_paginas_ler((string) $alvo['paginas']), $acesso_pedido['paginas']);
                } else {
                    $erro = acesso_erro_total($eu_total, (bool) $alvo['acesso_total'], false)
                        ?? acesso_erro_remover($meu_id, $id, (bool) $alvo['acesso_total'], $qtd_total, count($todos));
                }

                if ($erro !== null) {
                    $pdo->rollBack();
                    $erros[] = $erro;
                    break;
                }
                if ($acao === 'acesso') {
                    $pdo->prepare('UPDATE admins SET acesso_total = :t, paginas = :p WHERE id = :id')->execute([
                        ':t'  => $acesso_pedido['total'] ? 1 : 0,
                        ':p'  => $acesso_pedido['total'] ? '' : acesso_paginas_texto($acesso_pedido['paginas']),
                        ':id' => $id,
                    ]);
                    flash('ok', 'Acesso de "' . $alvo['usuario'] . '" atualizado.');
                } else {
                    $pdo->prepare('DELETE FROM admins WHERE id = :id')->execute([':id' => $id]);
                    flash('ok', 'Administrador "' . $alvo['usuario'] . '" removido.');
                }
                $pdo->commit();
                header('Location: administradores.php');
                exit;

            default:
                $erros[] = 'Ação inválida.';
        }
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Pedal Solidário – erro em administradores: ' . $e->getMessage());
        $erros[] = 'Não foi possível concluir a ação.' . (banco_debug() ? ' ' . $e->getMessage() : '');
    }
}

$admins = [];
try {
    $admins = db()->query('SELECT id, usuario, criado_em, acesso_total, paginas FROM admins ORDER BY usuario')->fetchAll();
} catch (PDOException $e) {
    error_log('Pedal Solidário – erro ao listar administradores: ' . $e->getMessage());
    $erros[] = 'Não foi possível carregar a lista de administradores.';
}

// Pode mexer em $a? Nunca em si mesmo (há Minha conta); em quem tem acesso total, só se eu também tiver.
$posso_mexer = fn(array $a): bool => (int) $a['id'] !== $meu_id && ($eu_total || !(bool) $a['acesso_total']);
$outros = array_values(array_filter($admins, $posso_mexer));

$titulo_admin = 'Administradores';
$menu_ativo = 'admins';
require __DIR__ . '/_topo.php';
?>

<h1>Administradores</h1>

<?php if ($erros): ?>
    <div class="adm-aviso adm-aviso--erro" role="alert">
        <ul><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<p>Cada administrador só vê as páginas liberadas para ele. Quem tem a página Administradores pode criar pessoas e mudar o acesso dos outros.
    Quem tem acesso total só pode ser alterado, ter a senha redefinida ou ser removido por outro administrador com acesso total.
    Quem não tem acesso total só libera páginas que ele mesmo acessa.</p>

<div class="adm-rolagem">
    <table class="adm-tabela">
        <thead><tr><th>Usuário</th><th>Acesso</th><th>Criado em</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($admins as $a):
            $a_total   = (bool) $a['acesso_total'];
            $a_paginas = acesso_paginas_ler((string) $a['paginas']);
            $eu        = (int) $a['id'] === $meu_id;
            $mexer     = $posso_mexer($a); ?>
            <tr data-usuario="<?= e($a['usuario']) ?>">
                <td>
                    <?= e($a['usuario']) ?>
                    <?php if ($eu): ?><span class="adm-selo adm-selo--pago">você</span><?php endif; ?>
                </td>
                <td class="acesso"><?= e(acesso_descricao($a_total, $a_paginas)) ?></td>
                <td><?= e(data_hora_br($a['criado_em'])) ?></td>
                <td class="acoes">
                    <?php if ($mexer): ?>
                        <details class="adm-editar-acesso" data-editar="<?= e($a['usuario']) ?>">
                            <summary class="adm-btn adm-btn--claro adm-btn--mini">Editar acesso</summary>
                            <form method="post" class="adm-form">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="acao" value="acesso">
                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <?= caixas_acesso('acesso-' . (int) $a['id'], $a_total, $a_paginas, $liberaveis) ?>
                                <button class="adm-btn adm-btn--mini" type="submit">Salvar acesso</button>
                            </form>
                        </details>
                    <?php endif; ?>
                    <?php if ($mexer && count($admins) > 1): ?>
                        <form method="post" onsubmit="return confirm('Remover o administrador <?= e(addslashes($a['usuario'])) ?>? Ele perde o acesso imediatamente.');">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="acao" value="remover">
                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                            <button class="adm-btn adm-btn--perigo adm-btn--mini" type="submit">Remover</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="adm-duplo">
    <section>
        <h2>Novo administrador</h2>
        <form method="post" class="adm-form adm-form--cartao" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="criar">
            <label for="novo-usuario">Usuário</label>
            <input type="text" id="novo-usuario" name="usuario" value="<?= e($novo_usuario) ?>" required>
            <label for="novo-senha">Senha <small>(mínimo de <?= SENHA_MINIMA ?> caracteres)</small></label>
            <input type="password" id="novo-senha" name="senha" required autocomplete="new-password">
            <label for="novo-confirmar">Confirmar senha</label>
            <input type="password" id="novo-confirmar" name="confirmar" required autocomplete="new-password">
            <?= caixas_acesso('novo', $novo_acesso['total'], $novo_acesso['paginas'], $liberaveis) ?>
            <button class="adm-btn" type="submit">Criar administrador</button>
        </form>
    </section>

    <section>
        <p>Para trocar a sua própria senha, use a página <a href="conta.php">Minha conta</a>.</p>

        <?php if ($outros): ?>
            <h2>Redefinir senha de outro administrador</h2>
            <form method="post" class="adm-form adm-form--cartao" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="acao" value="redefinir">
                <label for="redef-id">Administrador</label>
                <select id="redef-id" name="id" required>
                    <?php foreach ($outros as $o): ?>
                        <option value="<?= (int) $o['id'] ?>"><?= e($o['usuario']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="redef-senha">Nova senha</label>
                <input type="password" id="redef-senha" name="senha" required autocomplete="new-password">
                <label for="redef-confirmar">Confirmar nova senha</label>
                <input type="password" id="redef-confirmar" name="confirmar" required autocomplete="new-password">
                <button class="adm-btn" type="submit">Redefinir senha</button>
            </form>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/_rodape.php'; ?>
