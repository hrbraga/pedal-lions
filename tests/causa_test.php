<?php
/**
 * Testes das fotos da seção "Onde o valor arrecadado será usado?" (carrossel da página inicial).
 * Rode com: php tests/causa_test.php   (no XAMPP: C:\xampp\php\php tests\causa_test.php)
 */
require __DIR__ . '/../includes/patrocinadores.php';
require __DIR__ . '/../includes/causa.php';

$falhas = 0;
function confere(string $nome, $obtido, $esperado): void
{
    global $falhas;
    if ($obtido === $esperado) {
        echo "ok - $nome\n";
    } else {
        $falhas++;
        echo "FALHOU - $nome: esperado " . var_export($esperado, true) . ', obtido ' . var_export($obtido, true) . "\n";
    }
}

// Pasta temporária no lugar da raiz do site (apagada no fim)
$dir = sys_get_temp_dir() . '/causa_teste_' . bin2hex(random_bytes(4));
mkdir($dir . '/uploads/causa', 0777, true);
mkdir($dir . '/uploads/patrocinadores', 0777, true);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
file_put_contents("$dir/uploads/causa/0123456789abcdef.jpg", $png);
file_put_contents("$dir/uploads/causa/fedcba9876543210.webp", $png);
file_put_contents("$dir/uploads/causa/0123456789abcdef.php", '<?php echo 1;');
file_put_contents("$dir/uploads/patrocinadores/0123456789abcdef.png", $png);

try {
    // Caminho da foto: só arquivo enviado pelo painel, com nome sorteado e extensão de imagem
    confere('foto enviada vale', causa_foto_valida('uploads/causa/0123456789abcdef.jpg', $dir), true);
    foreach (['uploads/causa/0123456789abcdef.php', 'uploads/causa/ffffffffffffffff.jpg', 'uploads/patrocinadores/0123456789abcdef.png',
              'uploads/causa/../patrocinadores/0123456789abcdef.png', 'https://x.com/a.jpg', '../config/banco.php', ''] as $forjado) {
        confere("foto recusada: $forjado", causa_foto_valida($forjado, $dir), false);
    }

    // O que o site mostra
    $c = causa_de([], $dir);
    confere('sem nada salvo: título padrão e nenhuma foto', [$c['titulo'], $c['texto'], $c['fotos']], ['Onde o valor arrecadado será usado?', '', []]);
    $c = causa_de(['causa' => ['titulo' => '  Para onde vai  ', 'texto' => 'Ações do Lions.', 'fotos' => [
        ['foto' => 'uploads/causa/fedcba9876543210.webp', 'legenda' => ' Campanha do agasalho '],
        ['foto' => 'uploads/causa/ffffffffffffffff.jpg', 'legenda' => 'Sumiu'],
        ['foto' => 'uploads/causa/0123456789abcdef.jpg'],
        'lixo',
    ]]], $dir);
    confere('site: título e texto salvos', [$c['titulo'], $c['texto']], ['Para onde vai', 'Ações do Lions.']);
    confere('site: fotos na ordem, pula a que sumiu, legenda limpa', $c['fotos'], [
        ['foto' => 'uploads/causa/fedcba9876543210.webp', 'arquivo' => 'uploads/causa/fedcba9876543210.webp', 'legenda' => 'Campanha do agasalho'],
        ['foto' => 'uploads/causa/0123456789abcdef.jpg', 'arquivo' => 'uploads/causa/0123456789abcdef.jpg', 'legenda' => ''],
    ]);
    confere('site: título em branco volta ao padrão', causa_de(['causa' => ['titulo' => ' ']], $dir)['titulo'], 'Onde o valor arrecadado será usado?');

    // Formulário: itens em branco saem; numeração bate com a da validação
    [$post, $erros] = causa_receber_envios(['causa_fotos' => [
        'a' => ['foto' => 'uploads/causa/0123456789abcdef.jpg', 'legenda' => 'Um'],
        'b' => ['foto' => '', 'legenda' => ''],
        'c' => ['foto' => '', 'legenda' => 'Sem foto'],
    ]], [], $dir);
    confere('formulário: item em branco sai', $post['causa_fotos'], [
        ['foto' => 'uploads/causa/0123456789abcdef.jpg', 'legenda' => 'Um'],
        ['foto' => '', 'legenda' => 'Sem foto'],
    ]);
    confere('formulário: sem envio, sem erro', $erros, []);

    // Mensagens de envio recusado falam de foto (e o limite é o das fotos)
    confere('foto grande', envio_erro(UPLOAD_ERR_OK, CAUSA_MAX_BYTES + 1, 'jpg', CAUSA_MAX_BYTES, 'a foto'), 'A foto passa de 8 MB.');
    confere('foto: falha no envio', envio_erro(UPLOAD_ERR_PARTIAL, 10, null, CAUSA_MAX_BYTES, 'a foto'), 'O envio da foto falhou. Tente de novo.');
    confere('foto: tipo errado', envio_erro(UPLOAD_ERR_OK, 10, null, CAUSA_MAX_BYTES, 'a foto'), 'Use PNG, JPG ou WEBP.');
    confere('logo continua igual', patro_erro_envio(UPLOAD_ERR_OK, PATRO_MAX_BYTES + 1, 'png'), 'O logo passa de 2 MB.');
} finally {
    foreach (['uploads/causa/0123456789abcdef.jpg', 'uploads/causa/fedcba9876543210.webp', 'uploads/causa/0123456789abcdef.php',
              'uploads/patrocinadores/0123456789abcdef.png'] as $a) {
        @unlink("$dir/$a");
    }
    @rmdir("$dir/uploads/causa");
    @rmdir("$dir/uploads/patrocinadores");
    @rmdir("$dir/uploads");
    @rmdir($dir);
}

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo certo\n";
exit($falhas ? 1 : 0);
