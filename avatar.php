<?php
$pagina = 'avatar';
$titulo = 'Eu vou!';
$descricao = 'Monte sua foto com a moldura do 2º Pedal Solidário e avise a galera no Instagram que você vai.';
$scripts_extra = ['assets/js/avatar-geometria.js', 'assets/js/avatar.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="pagina-topo">
    <div class="wrap">
        <h1>Eu vou!</h1>
        <p>Monte sua foto com a moldura do 2º Pedal Solidário e poste no Instagram.</p>
    </div>
</div>

<section class="secao">
    <div class="wrap">
        <div id="avatar" class="avatar" data-formato="feed">

            <div class="avatar__passo">
                <input id="avatar-arquivo" class="sr-only" type="file" accept="image/*">
                <label class="btn btn--ouro" for="avatar-arquivo">Escolher foto</label>
            </div>

            <div class="avatar__abas" role="tablist" aria-label="Formato da imagem">
                <button type="button" role="tab" data-formato="feed" aria-selected="true">Feed</button>
                <button type="button" role="tab" data-formato="stories" aria-selected="false">Stories</button>
            </div>

            <div class="avatar__palco">
                <canvas id="avatar-canvas" width="1080" height="1080" tabindex="0"
                        aria-label="Prévia: arraste para posicionar, use + e − para zoom"></canvas>
            </div>

            <div class="avatar__ajustes">
                <label for="avatar-zoom">Zoom</label>
                <input id="avatar-zoom" type="range" min="1" max="4" step="0.01" value="1" disabled>
                <button id="avatar-centralizar" type="button" class="btn btn--pequeno avatar__btn-claro" disabled>Centralizar</button>
            </div>

            <div class="avatar__acoes">
                <button id="avatar-compartilhar" type="button" class="btn btn--ouro" hidden disabled>Compartilhar</button>
                <button id="avatar-baixar" type="button" class="btn btn--azul" disabled>Baixar</button>
            </div>

            <p id="avatar-aviso" class="avatar__aviso" role="status" aria-live="polite"></p>

            <p class="dica avatar__privacidade">Sua foto não sai do seu aparelho: a imagem é montada aqui mesmo, no navegador.</p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
