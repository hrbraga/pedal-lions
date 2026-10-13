<?php
$pagina = 'faq';
$titulo = 'Perguntas frequentes';
$descricao = 'Tire suas dúvidas sobre inscrição, categorias, kits e regras do 2º Pedal Solidário.';
require __DIR__ . '/includes/header.php';
?>

<div class="pagina-topo">
    <div class="wrap">
        <h1>Perguntas frequentes</h1>
        <p>Não encontrou o que procurava? Fale com a organização pelos contatos no rodapé da página.</p>
    </div>
</div>

<section class="secao">
    <div class="wrap">
        <div class="faq">
            <?php foreach ($ev['faq'] as $item): ?>
                <details>
                    <summary><?= e($item['p']) ?></summary>
                    <p><?= e($item['r']) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
