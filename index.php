<?php
$pagina = 'inicio';
require __DIR__ . '/includes/header.php';

$banner = imagem_opcional('banner.png'); // Se existir assets/img/banner.jpg, ele vira o fundo do topo.
?>

<section class="hero<?= $banner ? ' hero--imagem' : '' ?>"<?= $banner ? ' style="background-image:url(\'' . e($banner) . '\')"' : '' ?>>
    <div class="wrap hero__conteudo">
        <?php if ($logo): ?>
            <img class="hero__logo" src="<?= e($logo) ?>" alt="Lions Clube" width="321" height="303">
        <?php endif; ?>
        <p class="hero__org"><?= e($ev['organizador']) ?> apresenta</p>
        <h1>2º Pedal <span>Solidário</span></h1>
        <p class="hero__data"><?= e($ev['data_extenso']) ?> · <?= e($ev['cidade']) ?>, <?= e($ev['uf']) ?></p>

        <div class="hero__acoes">
            <?php if ($ev['inscricoes_abertas']): ?>
                <a class="btn btn--ouro" href="inscricao.php">Fazer minha inscrição</a>
            <?php endif; ?>
            <a class="btn btn--contorno" href="rotas.php">Ver rotas</a>
        </div>

        <p class="hero__contagem" data-contagem="<?= e($ev['data_iso']) ?>"></p>
    </div>

    <!-- Perfil de altimetria ilustrativo (não representa o percurso real) -->
    <div class="perfil" aria-hidden="true">
        <svg viewBox="0 0 1200 230" preserveAspectRatio="none" focusable="false">
            <defs>
                <linearGradient id="perfil-grad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#F5B800" stop-opacity=".35"/>
                    <stop offset="1" stop-color="#F5B800" stop-opacity="0"/>
                </linearGradient>
            </defs>
            <path class="perfil__area" d="M0 175 C90 175 130 125 210 135 S330 62 410 95 S530 175 610 145 S730 42 830 74 S970 155 1050 115 S1150 85 1200 105 L1200 230 L0 230 Z"/>
            <path class="perfil__linha" pathLength="1" d="M0 175 C90 175 130 125 210 135 S330 62 410 95 S530 175 610 145 S730 42 830 74 S970 155 1050 115 S1150 85 1200 105"/>
            <circle class="perfil__ponto" cx="14" cy="175" r="7"/>
            <circle class="perfil__ponto" cx="1186" cy="104" r="7"/>
        </svg>
    </div>
</section>

<?php $patrocinadores = patrocinadores(); ?>
<?php if ($patrocinadores): ?>
<section class="patrocinio" aria-labelledby="patrocinio-titulo">
    <div class="wrap patrocinio__topo">
        <h2 id="patrocinio-titulo">Patrocinadores</h2>
    </div>

    <!-- Faixa de rolagem nativa: arrasta com o dedo e, com JavaScript, também anda sozinha em laço (assets/js/main.js) -->
    <div class="patrocinio__moldura">
        <div class="patrocinio__faixa" tabindex="0" role="region" aria-label="Logos dos patrocinadores">
            <ul class="patrocinio__lista">
                <?php foreach ($patrocinadores as $pat): ?>
                    <li class="patrocinio__item">
                        <?php if ($pat['url'] !== ''): ?>
                            <a href="<?= e($pat['url']) ?>" target="_blank" rel="noopener sponsored" aria-label="<?= e($pat['nome']) ?> (abre em nova aba)">
                                <img src="<?= e($pat['arquivo']) ?>" alt="<?= e($pat['nome']) ?>" loading="lazy" decoding="async">
                            </a>
                        <?php else: ?>
                            <img src="<?= e($pat['arquivo']) ?>" alt="<?= e($pat['nome']) ?>" loading="lazy" decoding="async">
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="secao">
    <div class="wrap">
        <h2>Escolha o seu pedal</h2>
        <p class="secao__intro">São quatro modalidades, do passeio leve à disputa por classificação. Escolha a que combina com o seu ritmo no momento da inscrição.</p>

        <ul class="modalidades">
            <?php foreach ($ev['categorias'] as $cat): ?>
                <li>
                    <h3><?= e($cat['nome']) ?></h3>
                    <span class="preco"><?= e(moeda($cat['valor'])) ?></span>
                    <p><?= e($cat['resumo']) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="nota-valores">Sobre o valor da inscrição incide taxa de administração de <?= e(taxa_rotulo()) ?>, somada no pagamento. O cupom de desconto vale só para a inscrição. A camiseta é opcional e não tem taxa nem desconto.</p>
    </div>
</section>

<section class="secao secao--azul" id="premiacao">
    <div class="wrap">
        <h2>Premiação</h2>
        <p class="secao__intro">Quem cruzar a chegada entre os primeiros sobe ao pódio.</p>

        <div class="premios">
            <?php foreach ($ev['premiacoes'] as $p): ?>
                <div>
                    <h3><?= e($p['titulo']) ?></h3>
                    <p><?= e($p['regra']) ?></p>
                    <?php if (!empty($p['premio'])): ?>
                        <p class="premios__tipo"><?= e($p['premio']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="secao">
    <div class="wrap">
        <h2>Tudo sobre o evento</h2>
        <div class="atalhos">
            <a href="rotas.php"><strong>Rotas</strong><span>Distância, altimetria e mapa de cada percurso</span></a>
            <a href="programacao.php"><strong>Programação</strong><span>Horários de kits, largadas e premiação</span></a>
            <a href="regulamento.php"><strong>Regulamento</strong><span>Regras, segurança e termo de responsabilidade</span></a>
        </div>
    </div>
</section>

<section class="secao secao--papel chamada">
    <div class="wrap">
        <h2>Pedale por uma boa causa</h2>
        <p>A inscrição só é confirmada após o pagamento. Garanta a sua e chame os amigos para formar uma equipe.</p>
        <?php if ($ev['inscricoes_abertas']): ?>
            <a class="btn btn--azul" href="inscricao.php">Fazer minha inscrição</a>
        <?php else: ?>
            <p><strong>As inscrições estão encerradas.</strong></p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
