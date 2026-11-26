<?php
$pagina = 'rotas';
$titulo = 'Rotas';
$descricao = 'Conheça os percursos Light, Hard e Rachão do 2º Pedal Solidário em Cornélio Procópio.';
require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/evento_validar.php';
$usa_strava = false; // o script do Strava entra uma vez só, no fim, se alguma rota tiver mapa
?>

<div class="pagina-topo">
    <div class="wrap">
        <h1>Rotas</h1>
        <p>Hard e light. As e-bikes participam no percurso Hard.</p>
    </div>
</div>

<section class="secao">
    <div class="wrap">
        <p class="aviso"><strong>Atenção:</strong> as distâncias, a altimetria e os mapas oficiais serão oficializados em breve</p>

        <?php foreach ($ev['rotas'] as $rota): ?>
            <article class="rota">
                <div class="rota__grade">
                    <div>
                        <h2><?= e($rota['nome']) ?></h2>
                        <p><?= e($rota['descricao']) ?></p>
                        <dl class="rota__medidas">
                            <dt>Distância</dt>   <dd><?= e($rota['distancia']) ?></dd>
                            <dt>Altimetria</dt>  <dd><?= e($rota['altimetria']) ?></dd>
                        </dl>
                    </div>
                    <?php $embed = trim((string) ($rota['mapa_embed'] ?? '')); ?>
                    <?php if (!empty($rota['mapa']['id'])): ?>
                        <?php $usa_strava = true; ?>
                        <!-- Mapa montado pelo site a partir dos dados da rota salvos no painel -->
                        <div class="mapa mapa--embed"><?= strava_html($rota['mapa']) ?></div>
                    <?php elseif ($embed !== ''): ?>
                        <!-- Código de incorporação colado em config/evento.php (conteúdo da organização, exibido como está) -->
                        <div class="mapa mapa--embed"><?= $embed ?></div>
                    <?php elseif (!empty($rota['mapa_url'])): ?>
                        <div class="mapa">
                            <iframe src="<?= e($rota['mapa_url']) ?>" title="Mapa do percurso <?= e($rota['nome']) ?>" loading="lazy" allowfullscreen></iframe>
                        </div>
                    <?php else: ?>
                        <div class="mapa"><span>Mapa do percurso <?= e($rota['nome']) ?><br>disponível em breve</span></div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if ($usa_strava): ?>
            <script src="https://strava-embeds.com/embed.js"></script>
        <?php endif; ?>
<br>
<br>
        <p><a class="btn btn--azul" href="inscricao.php">Fazer minha Inscrição</a></p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
