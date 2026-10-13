<?php
require_once __DIR__ . '/funcoes.php';
$ev     = evento();
$pagina = $pagina ?? '';
$titulo_pagina = !empty($titulo)
    ? $titulo . ' | ' . $ev['nome']
    : $ev['nome'] . ' · ' . $ev['organizador'];
$descricao_pagina = $descricao ?? ($ev['nome'] . ' do ' . $ev['organizador'] . '. ' . $ev['data_curta'] . ' em ' . $ev['cidade'] . '. Faça sua inscrição.');
$logo = imagem_opcional('logo.png');

$menu = [
    'inicio'      => ['index.php',       'Início'],
    'rotas'       => ['rotas.php',       'Rotas'],
    'programacao' => ['programacao.php', 'Programação'],
    'regulamento' => ['regulamento.php', 'Regulamento'],
    'faq'         => ['faq.php',         'FAQ'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo_pagina) ?></title>
    <meta name="description" content="<?= e($descricao_pagina) ?>">
    <meta name="theme-color" content="#0A1E4D">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pagina-<?= e($pagina) ?>">
<a class="pular" href="#conteudo">Ir para o conteúdo</a>

<header class="topo">
    <div class="wrap topo__barra">
        <a class="marca" href="index.php" aria-label="<?= e($ev['nome']) ?> – página inicial">
            <?php if ($logo): ?>
                <img src="<?= e($logo) ?>" alt="Lions Clube" class="marca__logo" width="56" height="53">
            <?php endif; ?>
            <span class="marca__texto">
                <strong><?= e($ev['nome']) ?></strong>
                <small><?= e($ev['organizador']) ?></small>
            </span>
        </a>

        <button class="menu-botao" type="button" aria-expanded="false" aria-controls="menu-principal">
            <span class="menu-botao__linhas" aria-hidden="true"></span>
            <span class="sr-only">Abrir menu</span>
        </button>

        <nav id="menu-principal" class="menu" aria-label="Principal">
            <ul>
                <?php foreach ($menu as $chave => [$url, $rotulo]): ?>
                    <li>
                        <a href="<?= e($url) ?>"<?= $pagina === $chave ? ' aria-current="page"' : '' ?>><?= e($rotulo) ?></a>
                    </li>
                <?php endforeach; ?>
                <li class="menu__cta">
                    <a class="btn btn--ouro btn--pequeno" href="inscricao.php"<?= $pagina === 'inscricao' ? ' aria-current="page"' : '' ?>>Inscreva-se</a>
                </li>
            </ul>
        </nav>
    </div>
</header>

<main id="conteudo">
