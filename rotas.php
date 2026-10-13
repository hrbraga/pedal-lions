<?php
$pagina = 'rotas';
$titulo = 'Rotas';
$descricao = 'Conheça os percursos Light, Hard e Rachão do 2º Pedal Solidário em Cornélio Procópio.';
require __DIR__ . '/includes/header.php';
?>

<div class="pagina-topo">
    <div class="wrap">
        <h1>Rotas</h1>
        <p>Três percursos para três ritmos. As e-bikes participam no percurso Hard.</p>
    </div>
</div>

<section class="secao">
    <div class="wrap">
        <p class="aviso"><strong>Em breve:</strong> as distâncias, a altimetria e os mapas oficiais serão publicados aqui assim que a organização fechar os percursos.</p>

        <?php foreach ($ev['rotas'] as $rota): ?>
            <article class="rota">
                <div class="rota__grade">
                    <div>
                        <h2><?= e($rota['nome']) ?></h2>
                        <p><?= e($rota['descricao']) ?></p>
                        <dl class="rota__medidas">
                            <dt>Distância</dt>   <dd><?= e($rota['distancia']) ?></dd>
                            <dt>Altimetria</dt>  <dd><?= e($rota['altimetria']) ?></dd>
                            <dt>Terreno</dt>     <dd><?= e($rota['terreno']) ?></dd>
                        </dl>
                    </div>
                    <div class="mapa">
                        <?php if (!empty($rota['mapa_url'])): ?>
                            <iframe src="<?= e($rota['mapa_url']) ?>" title="Mapa do percurso <?= e($rota['nome']) ?>" loading="lazy" allowfullscreen></iframe>
                        <?php else: ?>
                            <span>Mapa do percurso <?= e($rota['nome']) ?><br>disponível em breve</span>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>

        <p><a class="btn btn--azul" href="inscricao.php">Escolher minha categoria</a></p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
