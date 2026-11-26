<?php
/**
 * Lista pública de inscritos: só aparece quando liberada em Dados do Evento → Inscrições.
 * Só inscrições pagas; mostra nome, cidade e equipe, por categoria e sexo, com busca por nome.
 */
require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/lista_publica.php';

$pagina  = 'inscritos';
$titulo  = 'Inscritos';
$descricao = 'Lista de inscritos confirmados no ' . evento()['nome'] . ', por categoria.';
$noindex = true; // a lista de nomes não vai para os buscadores

$liberada = !empty(evento()['lista_inscritos_publica']);
$grupos = [];
$falha = false;
if ($liberada) {
    try {
        $grupos = lista_grupos(evento()['categorias'] ?? [], lista_inscritos_pagos(db()));
    } catch (Throwable $e) {
        error_log('Pedal Solidário – lista de inscritos: ' . $e->getMessage());
        $falha = true;
    }
} else {
    http_response_code(404);
}
$total = array_sum(array_column($grupos, 'qtd'));

require __DIR__ . '/includes/header.php';
?>

<div class="pagina-topo">
    <div class="wrap">
        <h1>Inscritos</h1>
        <?php if ($liberada && !$falha): ?>
            <p><?= $total ?> inscriç<?= $total === 1 ? 'ão confirmada' : 'ões confirmadas' ?> (pagamento confirmado), por categoria.</p>
        <?php endif; ?>
    </div>
</div>

<section class="secao">
    <div class="wrap">
        <?php if (!$liberada): ?>
            <p class="aviso">A lista de inscritos ainda não foi liberada.</p>
        <?php elseif ($falha): ?>
            <p class="aviso aviso--erro">Não foi possível carregar a lista agora. Tente de novo em instantes.</p>
        <?php else: ?>
            <div class="lista-busca">
                <label for="busca-nome">Buscar por nome</label>
                <input type="search" id="busca-nome" data-busca placeholder="Digite um nome" autocomplete="off">
                <p class="lista-busca__vazio" data-busca-vazio hidden>Nenhum inscrito com esse nome.</p>
            </div>

            <div class="lista-grupos">
                <?php foreach ($grupos as $g): ?>
                    <section class="lista-grupo" id="<?= e($g['id']) ?>" data-grupo>
                        <h2><?= e($g['titulo']) ?> <small><?= (int) $g['qtd'] ?></small></h2>
                        <?php if (!$g['pessoas']): ?>
                            <p class="lista-grupo__vazio">Nenhum inscrito ainda.</p>
                        <?php else: ?>
                            <ol class="lista-pessoas">
                                <?php foreach ($g['pessoas'] as $p): ?>
                                    <li data-pessoa data-nome="<?= e($p['nome']) ?>" data-busca-texto="<?= e(lista_texto_busca($p['nome'])) ?>">
                                        <strong><?= e($p['nome']) ?></strong>
                                        <span><?= e($p['cidade']) ?><?= $p['equipe'] !== '' ? ' · ' . e($p['equipe']) : '' ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($liberada && !$falha): ?>
<script>
/* Busca por nome: sem diferenciar maiúsculas e acentos; esconde grupos sem resultado */
(function () {
    var campo = document.querySelector('[data-busca]');
    if (!campo) return;
    var vazio = document.querySelector('[data-busca-vazio]');
    function normalizar(t) {
        return t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
    }
    campo.addEventListener('input', function () {
        var termo = normalizar(campo.value);
        var achados = 0;
        Array.prototype.forEach.call(document.querySelectorAll('[data-grupo]'), function (g) {
            var visiveis = 0;
            Array.prototype.forEach.call(g.querySelectorAll('[data-pessoa]'), function (p) {
                var ok = termo === '' || p.getAttribute('data-busca-texto').indexOf(termo) !== -1;
                p.hidden = !ok;
                if (ok) visiveis++;
            });
            g.hidden = termo !== '' && visiveis === 0;
            achados += visiveis;
        });
        vazio.hidden = termo === '' || achados > 0;
    });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
