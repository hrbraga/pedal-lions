</main>

<footer class="rodape">
    <div class="wrap rodape__grade">
        <div>
            <?php if ($logo): ?>
                <img src="<?= e($logo) ?>" alt="Lions Clube" class="rodape__logo" width="84" height="79" loading="lazy">
            <?php endif; ?>
            <p class="rodape__titulo"><?= e($ev['nome']) ?></p>
            <p><?= e($ev['organizador']) ?></p>
            <p><?= e($ev['data_extenso']) ?><br><?= e($ev['cidade']) ?>, <?= e($ev['uf']) ?></p>
        </div>
        <div>
            <p class="rodape__titulo">Navegue</p>
            <ul>
                <li><a href="inscricao.php">Inscrição</a></li>
                <li><a href="rotas.php">Rotas</a></li>
                <li><a href="programacao.php">Programação</a></li>
                <li><a href="regulamento.php">Regulamento</a></li>
                <li><a href="faq.php">Perguntas frequentes</a></li>
            </ul>
        </div>
        <div>
            <p class="rodape__titulo">Contato</p>
            <ul>
                <?php if (!empty($ev['contato']['email'])): ?>
                    <li><a href="mailto:<?= e($ev['contato']['email']) ?>"><?= e($ev['contato']['email']) ?></a></li>
                <?php endif; ?>
                <?php if (!empty($ev['contato']['whatsapp'])): ?>
                    <li>WhatsApp: <?= e($ev['contato']['whatsapp']) ?></li>
                <?php endif; ?>
                <?php if (!empty($ev['contato']['instagram'])): ?>
                    <li><a href="<?= e($ev['contato']['instagram']) ?>" rel="noopener" target="_blank">Instagram</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="wrap rodape__base">
        <p>© <?= date('Y') ?> <?= e($ev['organizador']) ?>. Todos os direitos reservados.</p>
    </div>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>
