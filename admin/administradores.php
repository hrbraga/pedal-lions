<?php
require __DIR__ . '/_bootstrap.php';
admin_exigir();

const SENHA_MINIMA = 10;

$meu_id = (int) $_SESSION['admin_id'];
$erros  = [];
$novo_usuario = '';

function erros_usuario(string $usuario): array
{
    return preg_match('/^[A-Za-z0-9._-]{3,50}$/', $usuario)
        ? []
        : ['O usuário deve ter de 3 a 50 caracteres (letras, números, ponto, hífen ou sublinhado).'];
}

function erros_senha(string $senha, string $confirmar): array
{
    $erros = [];
    if (mb_strlen($senha) < SENHA_MINIMA) {
        $erros[] = 'A senha deve ter pelo menos ' . SENHA_MINIMA . ' caracteres.';
    }
    if ($senha !== $confirmar) {
        $erros[] = 'A confirmação da senha não confere.';
    }
    return $erros;
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
                $erros = array_merge(erros_usuario($novo_usuario), erros_senha($senha, $confirmar));

                if (!$erros) {
                    try {
                        $pdo->prepare('INSERT INTO admins (usuario, senha_hash) VALUES (:u, :h)')
                            ->execute([':u' => $novo_usuario, ':h' => password_hash($senha, PASSWORD_DEFAULT)]);
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

                $st = $pdo->prepare('SELECT usuario FROM admins WHERE id = :id');
                $st->execute([':id' => $id]);
                $alvo = $st->fetchColumn();

                if ($alvo === false) {
                    $erros[] = 'Administrador não encontrado.';
                } elseif ($id === $meu_id) {
                    $erros[] = 'Para trocar a sua própria senha, use a seção "Minha senha".';
                }

                if (!$erros) {
                    $pdo->prepare('UPDATE admins SET senha_hash = :h WHERE id = :id')
                        ->execute([':h' => password_hash($senha, PASSWORD_DEFAULT), ':id' => $id]);
                    flash('ok', 'Senha de "' . $alvo . '" redefinida. Se essa pessoa estiver logada, precisará entrar de novo.');
                    header('Location: administradores.php');
                    exit;
                }
                break;

            case 'remover':
                $id = (int) ($_POST['id'] ?? 0);
                $total = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();

                $st = $pdo->prepare('SELECT usuario FROM admins WHERE id = :id');
                $st->execute([':id' => $id]);
                $alvo = $st->fetchColumn();

                if ($alvo === false) {
                    $erros[] = 'Administrador não encontrado.';
                } elseif ($id === $meu_id) {
                    $erros[] = 'Você não pode remover o seu próprio acesso.';
                } elseif ($total <= 1) {
                    $erros[] = 'É preciso manter pelo menos um administrador.';
                } else {
                    $pdo->prepare('DELETE FROM admins WHERE id = :id')->execute([':id' => $id]);
                    flash('ok', 'Administrador "' . $alvo . '" removido.');
                    header('Location: administradores.php');
                    exit;
                }
                break;

            case 'minha_senha':
                tentativas_garantir_tabela();
                $atual     = (string) ($_POST['atual'] ?? '');
                $senha     = (string) ($_POST['senha'] ?? '');
                $confirmar = (string) ($_POST['confirmar'] ?? '');

                if (login_bloqueado()) {
                    $erros[] = 'Muitas tentativas. Aguarde 15 minutos e tente de novo.';
                    break;
                }

                $st = $pdo->prepare('SELECT senha_hash FROM admins WHERE id = :id');
                $st->execute([':id' => $meu_id]);
                $hash_atual = $st->fetchColumn();

                if ($hash_atual === false || !password_verify($atual, (string) $hash_atual)) {
                    login_registrar_falha();
                    $erros[] = 'A senha atual está incorreta.';
                    break;
                }

                $erros = erros_senha($senha, $confirmar);
                if ($senha === $atual) {
                    $erros[] = 'A nova senha deve ser diferente da atual.';
                }

                if (!$erros) {
                    $novo_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $pdo->prepare('UPDATE admins SET senha_hash = :h WHERE id = :id')
                        ->execute([':h' => $novo_hash, ':id' => $meu_id]);
                    login_limpar_falhas();
                    session_regenerate_id(true);
                    $_SESSION['admin_sig'] = admin_assinatura($novo_hash);
                    flash('ok', 'Sua senha foi alterada.');
                    header('Location: administradores.php');
                    exit;
                }
                break;

            default:
                $erros[] = 'Ação inválida.';
        }
    } catch (PDOException $e) {
        error_log('Pedal Solidário – erro em administradores: ' . $e->getMessage());
        $erros[] = 'Não foi possível concluir a ação.' . (banco_debug() ? ' ' . $e->getMessage() : '');
    }
}

$admins = [];
try {
    $admins = db()->query('SELECT id, usuario, criado_em FROM admins ORDER BY usuario')->fetchAll();
} catch (PDOException $e) {
    error_log('Pedal Solidário – erro ao listar administradores: ' . $e->getMessage());
    $erros[] = 'Não foi possível carregar a lista de administradores.';
}

$outros = array_values(array_filter($admins, fn($a) => (int) $a['id'] !== $meu_id));

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

<p>Todos os administradores têm o mesmo acesso: ver e alterar inscrições, exportar dados e gerenciar esta lista.</p>

<div class="adm-rolagem">
    <table class="adm-tabela">
        <thead><tr><th>Usuário</th><th>Criado em</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($admins as $a): ?>
            <tr>
                <td>
                    <?= e($a['usuario']) ?>
                    <?php if ((int) $a['id'] === $meu_id): ?><span class="adm-selo adm-selo--pago">você</span><?php endif; ?>
                </td>
                <td><?= e(data_hora_br($a['criado_em'])) ?></td>
                <td class="acoes">
                    <?php if ((int) $a['id'] !== $meu_id && count($admins) > 1): ?>
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
            <button class="adm-btn" type="submit">Criar administrador</button>
        </form>
    </section>

    <section>
        <h2>Minha senha</h2>
        <form method="post" class="adm-form adm-form--cartao" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="minha_senha">
            <label for="atual">Senha atual</label>
            <input type="password" id="atual" name="atual" required autocomplete="current-password">
            <label for="minha-senha">Nova senha <small>(mínimo de <?= SENHA_MINIMA ?> caracteres)</small></label>
            <input type="password" id="minha-senha" name="senha" required autocomplete="new-password">
            <label for="minha-confirmar">Confirmar nova senha</label>
            <input type="password" id="minha-confirmar" name="confirmar" required autocomplete="new-password">
            <button class="adm-btn" type="submit">Alterar minha senha</button>
        </form>

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
