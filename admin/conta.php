<?php
/**
 * Minha conta: aberta a todos os administradores, qualquer que seja o acesso.
 * Aqui cada um troca a própria senha.
 */
require __DIR__ . '/_bootstrap.php';
admin_exigir();

$meu_id = (int) $_SESSION['admin_id'];
$erros  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido(post_txt('csrf'))) {
        flash('erro', 'Sessão expirada. Tente novamente.');
        header('Location: conta.php');
        exit;
    }

    try {
        $pdo = db();
        if (post_txt('acao') !== 'minha_senha') {
            $erros[] = 'Ação inválida.';
        } else {
            tentativas_garantir_tabela();
            $atual     = (string) ($_POST['atual'] ?? '');
            $senha     = (string) ($_POST['senha'] ?? '');
            $confirmar = (string) ($_POST['confirmar'] ?? '');

            if (login_bloqueado()) {
                $erros[] = 'Muitas tentativas. Aguarde 15 minutos e tente de novo.';
            } else {
                $st = $pdo->prepare('SELECT senha_hash FROM admins WHERE id = :id');
                $st->execute([':id' => $meu_id]);
                $hash_atual = $st->fetchColumn();

                if ($hash_atual === false || !password_verify($atual, (string) $hash_atual)) {
                    login_registrar_falha();
                    $erros[] = 'A senha atual está incorreta.';
                } else {
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
                        header('Location: conta.php');
                        exit;
                    }
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Pedal Solidário – erro em Minha conta: ' . $e->getMessage());
        $erros[] = 'Não foi possível concluir a ação.' . (banco_debug() ? ' ' . $e->getMessage() : '');
    }
}

$acesso = admin_acesso();
$sem_paginas = !$acesso['total'] && !$acesso['paginas'];

$titulo_admin = 'Minha conta';
$menu_ativo   = 'conta';
require __DIR__ . '/_topo.php';
?>

<h1>Minha conta</h1>

<?php if ($sem_paginas): ?>
    <p class="adm-aviso adm-aviso--erro" role="status">Você ainda não tem acesso a nenhuma página do painel. Peça a um administrador para liberar.</p>
<?php endif; ?>

<?php if ($erros): ?>
    <div class="adm-aviso adm-aviso--erro" role="alert">
        <ul><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<p>Usuário: <strong><?= e($_SESSION['admin_usuario'] ?? '') ?></strong> · Acesso: <?= e(acesso_descricao($acesso['total'], $acesso['paginas'])) ?></p>

<section>
    <h2>Trocar minha senha</h2>
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
</section>

<?php require __DIR__ . '/_rodape.php'; ?>
