<?php
$titulo_admin = $titulo_admin ?? 'Painel';
$mostrar_menu = $mostrar_menu ?? true;
$menu_ativo   = $menu_ativo ?? '';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$logo_admin = imagem_opcional('logo.png');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($titulo_admin) ?> | Administração</title>
    <link rel="icon" href="../favicon.ico" sizes="any">
    <link rel="stylesheet" href="../<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body>
<?php if ($mostrar_menu): ?>
<header class="adm-topo">
    <div class="adm-largura adm-topo__barra">
        <a class="adm-marca" href="painel.php">
            <?php if ($logo_admin): ?><img src="../<?= e($logo_admin) ?>" alt="" width="36" height="34"><?php endif; ?>
            <span>Administração</span>
        </a>
        <nav class="adm-menu" aria-label="Painel">
            <a href="painel.php"<?= $menu_ativo === 'inscritos' ? ' aria-current="page"' : '' ?>>Inscritos</a>
            <a href="cupons.php"<?= $menu_ativo === 'cupons' ? ' aria-current="page"' : '' ?>>Cupons</a>
            <a href="administradores.php"<?= $menu_ativo === 'admins' ? ' aria-current="page"' : '' ?>>Administradores</a>
            <a href="exportar.php">Exportar CSV</a>
            <a href="../index.php" target="_blank" rel="noopener">Ver site</a>
            <a href="logout.php">Sair (<?= e($_SESSION['admin_usuario'] ?? '') ?>)</a>
        </nav>
    </div>
</header>
<?php endif; ?>
<main class="adm-largura adm-principal">
<?php if ($flash): ?>
    <p class="adm-aviso adm-aviso--<?= e($flash['tipo']) ?>" role="status"><?= e($flash['msg']) ?></p>
<?php endif; ?>
