<?php
$pagina = 'programacao';
$titulo = 'Programação';
$descricao = 'Programação do 2º Pedal Solidário: retirada de kits, largadas e premiação.';
require __DIR__ . '/includes/header.php';
?>

<div class="pagina-topo">
    <div class="wrap">
        <h1>Programação</h1>
        <p><?= e($ev['data_extenso']) ?>, em <?= e($ev['cidade']) ?>.</p>
    </div>
</div>

<section class="secao">
    <div class="wrap">
        <p class="aviso"><strong>Horários a confirmar.</strong> A organização ainda vai definir os horários de cada etapa. Eles serão publicados aqui e enviados por e-mail aos inscritos.</p>

        <ol class="linha-tempo">
            <?php foreach ($ev['programacao'] as $item): ?>
                <li>
                    <span class="hora"><?= e($item['hora']) ?></span>
                    <h3><?= e($item['titulo']) ?></h3>
                    <?php if (!empty($item['desc'])): ?>
                        <p><?= e($item['desc']) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
