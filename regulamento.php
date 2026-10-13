<?php
$pagina = 'regulamento';
$titulo = 'Regulamento';
$descricao = 'Regulamento do 2º Pedal Solidário do Lions Clube de Cornélio Procópio.';
require __DIR__ . '/includes/header.php';
?>

<div class="pagina-topo">
    <div class="wrap">
        <h1>Regulamento</h1>
        <p>Leia com atenção antes de se inscrever. Ao se inscrever, você declara que concorda com todas as regras abaixo.</p>
    </div>
</div>

<section class="secao">
    <div class="wrap">
        <p class="aviso"><strong>Minuta para revisão.</strong> Este texto é um modelo inicial. A organização deve revisar e completar todos os itens marcados com “a confirmar” antes da publicação.</p>

        <div class="texto">
            <h2>1. O evento</h2>
            <p>O <?= e($ev['nome']) ?> é um evento de ciclismo de caráter solidário, promovido pelo <?= e($ev['organizador']) ?>, que acontecerá em <?= e($ev['data_curta']) ?>, em <?= e($ev['cidade']) ?>, <?= e($ev['uf']) ?>. Local de largada: <?= e($ev['local_largada']) ?>.</p>

            <h2>2. Categorias</h2>
            <ul>
                <?php foreach ($ev['categorias'] as $cat): ?>
                    <li><strong><?= e($cat['nome']) ?></strong>: <?= e(moeda($cat['valor'])) ?>. <?= e($cat['resumo']) ?></li>
                <?php endforeach; ?>
            </ul>
            <p>O evento não é uma prova de velocidade em via fechada. Os percursos podem ter trechos em estrada de terra e cruzamentos com vias abertas ao trânsito (a confirmar).</p>

            <h2>3. Inscrição e pagamento</h2>
            <ul>
                <li>A inscrição é feita exclusivamente pelo site oficial do evento.</li>
                <li>A inscrição só é efetivada após a confirmação do pagamento. Enquanto o pagamento não for confirmado, a inscrição fica pendente e não garante vaga nem kit.</li>
                <li>O participante é responsável por informar dados corretos. Os dados informados serão usados para a organização do evento e a classificação.</li>
                <li>Prazo de inscrição e limite de vagas: a confirmar.</li>
            </ul>

            <h2>4. Cancelamento e transferência</h2>
            <p>Política de cancelamento, reembolso e transferência de inscrição: a confirmar.</p>

            <h2>5. Idade mínima e menores de idade</h2>
            <p>Menores de 18 anos só poderão participar com autorização assinada pelo pai, mãe ou responsável legal, entregue na retirada do kit. Idade mínima e regras de acompanhamento: a confirmar.</p>

            <h2>6. Equipamentos e segurança</h2>
            <ul>
                <li>O uso de capacete é obrigatório durante todo o percurso.</li>
                <li>A bicicleta deve estar em boas condições, com freios e pneus funcionando.</li>
                <li>É dever do participante respeitar a sinalização, as orientações da organização e a legislação de trânsito.</li>
                <li>Recomenda-se levar água, documento e o contato de emergência.</li>
                <li>Bicicletas elétricas participam somente na categoria E-bike Hard. Regras de potência e velocidade: a confirmar.</li>
            </ul>

            <h2>7. Retirada do kit</h2>
            <p>A retirada é feita mediante documento com foto e comprovante de inscrição. Local, datas e horários serão divulgados na página de Programação. Conteúdo do kit e tamanhos de camiseta: a confirmar.</p>

            <h2>8. Classificação e premiação</h2>
            <ul>
                <?php foreach ($ev['premiacoes'] as $p): ?>
                    <li>
                        <strong><?= e($p['titulo']) ?>:</strong>
                        <?= e($p['regra']) ?><?= !empty($p['premio']) ? '. ' . e($p['premio']) : '' ?>.
                    </li>
                <?php endforeach; ?>
            </ul>
            <p>Forma de cronometragem e critérios de desempate: a confirmar. Para a premiação por equipes, todos os integrantes devem informar o mesmo nome de equipe na inscrição.</p>

            <h2>9. Termo de responsabilidade</h2>
            <p>Ao se inscrever, o participante declara estar em condições de saúde adequadas para a prática de atividade física, assume os riscos inerentes ao ciclismo e isenta a organização de responsabilidade por acidentes decorrentes do descumprimento das regras. Texto jurídico definitivo: a confirmar.</p>

            <h2>10. Uso de imagem</h2>
            <p>O participante autoriza o uso gratuito de sua imagem em fotos e vídeos do evento, para divulgação pela organização e patrocinadores. Texto definitivo: a confirmar.</p>

            <h2>11. Proteção de dados (LGPD)</h2>
            <p>Os dados pessoais coletados na inscrição serão usados somente para a organização e a comunicação do evento, conforme a Lei Geral de Proteção de Dados (Lei 13.709/2018). Política de privacidade completa: a confirmar.</p>

            <h2>12. Casos omissos</h2>
            <p>Situações não previstas neste regulamento serão decididas pela comissão organizadora do evento.</p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
