<?php
require __DIR__ . '/_bootstrap.php';

if (admin_logado()) {
    header('Location: painel.php');
    exit;
}

$erro = '';
$usuario = '';

try {
    tentativas_garantir_tabela();
    $total = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($total === 0) {
        header('Location: configurar.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $usuario = trim(post_txt('usuario'));
        $senha   = (string) ($_POST['senha'] ?? '');

        if (!csrf_valido(post_txt('csrf'))) {
            $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
        } elseif (login_bloqueado()) {
            $erro = 'Muitas tentativas. Aguarde 15 minutos e tente de novo.';
        } else {
            $st = db()->prepare('SELECT id, usuario, senha_hash FROM admins WHERE usuario = :u LIMIT 1');
            $st->execute([':u' => $usuario]);
            $adm = $st->fetch();

            // Verifica sempre um hash, para o tempo de resposta não revelar se o usuário existe
            $hash = $adm['senha_hash'] ?? password_hash('sem-usuario', PASSWORD_DEFAULT);
            $ok   = password_verify($senha, $hash) && $adm;

            if ($ok) {
                login_limpar_falhas();
                session_regenerate_id(true);
                $_SESSION['admin_id']     = (int) $adm['id'];
                $_SESSION['admin_usuario'] = $adm['usuario'];
                $_SESSION['admin_ultimo'] = time();
                $_SESSION['admin_sig']    = admin_assinatura($adm['senha_hash']);
                unset($_SESSION['csrf']);
                header('Location: painel.php');
                exit;
            }
            login_registrar_falha();
            $erro = 'Usuário ou senha incorretos.';
        }
    }
} catch (PDOException $e) {
    error_log('Pedal Solidário – erro no login: ' . $e->getMessage());
    $erro = 'Não foi possível acessar o banco de dados.' . (banco_debug() ? ' ' . $e->getMessage() : '');
}

$titulo_admin = 'Entrar';
$mostrar_menu = false;
require __DIR__ . '/_topo.php';
?>
<div class="adm-login">
    <?php if (imagem_opcional('logo.png')): ?>
        <img class="adm-login__logo" src="../assets/img/logo.png" alt="Lions Clube" width="90" height="85">
    <?php endif; ?>
    <h1>Administração do evento</h1>

    <?php if ($erro): ?>
        <p class="adm-aviso adm-aviso--erro" role="alert"><?= e($erro) ?></p>
    <?php endif; ?>

    <form method="post" class="adm-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label for="usuario">Usuário</label>
        <input type="text" id="usuario" name="usuario" value="<?= e($usuario) ?>" required autofocus autocomplete="username">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" required autocomplete="current-password">
        <button class="adm-btn" type="submit">Entrar</button>
    </form>
</div>
<?php require __DIR__ . '/_rodape.php'; ?>
