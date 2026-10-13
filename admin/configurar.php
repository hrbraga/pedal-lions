<?php
/**
 * Criação do PRIMEIRO administrador.
 * Só funciona enquanto não existir nenhum administrador; depois disso, fica bloqueada.
 */
require __DIR__ . '/_bootstrap.php';

$erros = [];
$bloqueado = false;
$falha_banco = null;
$usuario = '';

try {
    $pdo = db();
    $total = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    $bloqueado = $total > 0;
} catch (PDOException $e) {
    $falha_banco = banco_debug() ? $e->getMessage() : 'Não foi possível conectar ao banco de dados.';
}

if (!$bloqueado && !$falha_banco && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario   = trim(post_txt('usuario'));
    $senha     = (string) ($_POST['senha'] ?? '');
    $confirmar = (string) ($_POST['confirmar'] ?? '');

    if (!csrf_valido(post_txt('csrf'))) {
        $erros[] = 'Sessão expirada. Recarregue a página e tente novamente.';
    }
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $usuario)) {
        $erros[] = 'O usuário deve ter de 3 a 50 caracteres (letras, números, ponto, hífen ou sublinhado).';
    }
    if (mb_strlen($senha) < 10) {
        $erros[] = 'A senha deve ter pelo menos 10 caracteres.';
    }
    if ($senha !== $confirmar) {
        $erros[] = 'A confirmação da senha não confere.';
    }

    if (!$erros) {
        try {
            $pdo->prepare('INSERT INTO admins (usuario, senha_hash) VALUES (:u, :h)')
                ->execute([':u' => $usuario, ':h' => password_hash($senha, PASSWORD_DEFAULT)]);
            flash('ok', 'Administrador criado. Entre com o usuário e a senha cadastrados.');
            header('Location: login.php');
            exit;
        } catch (PDOException $e) {
            error_log('Pedal Solidário – erro ao criar admin: ' . $e->getMessage());
            $erros[] = 'Não foi possível criar o administrador.';
        }
    }
}

$titulo_admin = 'Criar administrador';
$mostrar_menu = false;
require __DIR__ . '/_topo.php';
?>
<div class="adm-login">
    <h1>Criar administrador</h1>

    <?php if ($falha_banco): ?>
        <p class="adm-aviso adm-aviso--erro">Erro de conexão com o banco. Confira se o MySQL está ativo e se o <code>schema.sql</code> foi importado.<br><small><?= e($falha_banco) ?></small></p>
    <?php elseif ($bloqueado): ?>
        <p class="adm-aviso">Já existe um administrador cadastrado. Esta página está desativada.</p>
        <p><a class="adm-btn" href="login.php">Ir para o login</a></p>
    <?php else: ?>
        <p>Cadastre o primeiro acesso ao painel. Esta tela só aparece uma vez.</p>

        <?php if ($erros): ?>
            <div class="adm-aviso adm-aviso--erro" role="alert"><ul><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post" class="adm-form" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label for="usuario">Usuário</label>
            <input type="text" id="usuario" name="usuario" value="<?= e($usuario) ?>" required autofocus>
            <label for="senha">Senha <small>(mínimo de 10 caracteres)</small></label>
            <input type="password" id="senha" name="senha" required autocomplete="new-password">
            <label for="confirmar">Confirmar senha</label>
            <input type="password" id="confirmar" name="confirmar" required autocomplete="new-password">
            <button class="adm-btn" type="submit">Criar administrador</button>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_rodape.php'; ?>
