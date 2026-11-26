<?php
/**
 * Testes das regras dos arquivos de logo dos patrocinadores.
 * Rode com: php tests/patrocinadores_test.php   (no XAMPP: C:\xampp\php\php tests\patrocinadores_test.php)
 */
require __DIR__ . '/../includes/patrocinadores.php';

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

// Pasta temporária com as imagens de teste (apagada no fim)
$dir = sys_get_temp_dir() . '/patro_teste_' . bin2hex(random_bytes(4));
mkdir($dir . '/uploads/patrocinadores', 0777, true);
mkdir($dir . '/assets/img/patrocinadores', 0777, true);
$png  = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
$webp = base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA');
// JPEG mínimo: SOI + APP0 (JFIF) + SOF0 1x1 + EOI
$jpg  = hex2bin('FFD8' . 'FFE000104A46494600010100000100010000' . 'FFC0000B080001000101011100' . 'FFD9');
file_put_contents("$dir/a.png", $png);
file_put_contents("$dir/a.webp", $webp);
file_put_contents("$dir/a.jpg", $jpg);
file_put_contents("$dir/falso.png", '<?php echo 1;');
file_put_contents("$dir/logo.svg", '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
file_put_contents("$dir/uploads/patrocinadores/0123456789abcdef.png", $png);
file_put_contents("$dir/uploads/patrocinadores/0123456789abcdef.php", '<?php echo 1;');
file_put_contents("$dir/assets/img/patrocinadores/Velho Logo.png", $png);

confere('png reconhecido', patro_tipo_imagem($dir . '/a.png'), 'png');
confere('webp reconhecido', patro_tipo_imagem($dir . '/a.webp'), 'webp');
confere('jpeg reconhecido', patro_tipo_imagem($dir . '/a.jpg'), 'jpg');
confere('php renomeado recusado', patro_tipo_imagem($dir . '/falso.png'), null);
confere('svg recusado', patro_tipo_imagem($dir . '/logo.svg'), null);
confere('arquivo inexistente', patro_tipo_imagem($dir . '/nada.png'), null);

confere('envio ok', patro_erro_envio(UPLOAD_ERR_OK, 1000, 'png'), null);
confere('envio grande', patro_erro_envio(UPLOAD_ERR_OK, PATRO_MAX_BYTES + 1, 'png'), 'O logo passa de 2 MB.');
confere('envio grande pelo php.ini', patro_erro_envio(UPLOAD_ERR_INI_SIZE, 0, null), 'O logo passa de 2 MB.');
confere('envio parcial', patro_erro_envio(UPLOAD_ERR_PARTIAL, 10, null), 'O envio do logo falhou. Tente de novo.');
confere('tipo errado', patro_erro_envio(UPLOAD_ERR_OK, 10, null), 'Use PNG, JPG ou WEBP.');

confere('logo enviado válido', patro_logo_valido('uploads/patrocinadores/0123456789abcdef.png', $dir), true);
confere('logo da pasta antiga válido', patro_logo_valido('assets/img/patrocinadores/Velho Logo.png', $dir), true);
confere('logo inexistente', patro_logo_valido('uploads/patrocinadores/ffffffffffffffff.png', $dir), false);
confere('nome fora do padrão', patro_logo_valido('uploads/patrocinadores/logo.png', $dir), false);
confere('php na pasta de envios', patro_logo_valido('uploads/patrocinadores/0123456789abcdef.php', $dir), false);
confere('subindo pasta', patro_logo_valido('assets/img/patrocinadores/../../config/banco.php', $dir), false);
confere('subindo pasta direto', patro_logo_valido('../config/banco.php', $dir), false);
confere('url externa', patro_logo_valido('https://x.com/a.png', $dir), false);
confere('vazio', patro_logo_valido('', $dir), false);
confere('url do logo com espaço', patro_url('assets/img/patrocinadores/Velho Logo.png'), 'assets/img/patrocinadores/Velho%20Logo.png');

confere('órfãos: citado fica, novo fica, velho sem citação sai',
    patro_orfaos(['aaaaaaaaaaaaaaaa.png' => 0, 'bbbbbbbbbbbbbbbb.png' => 0, 'cccccccccccccccc.png' => 99000],
        ['{"patrocinadores":[{"logo":"uploads/patrocinadores/aaaaaaaaaaaaaaaa.png"}]}'], 100000),
    ['bbbbbbbbbbbbbbbb.png']);

confere('guardar sem arquivo', patro_guardar_envio(['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0], $dir), []);
confere('guardar arquivo que não veio de envio',
    isset(patro_guardar_envio(['name' => 'a.png', 'tmp_name' => $dir . '/a.png', 'error' => UPLOAD_ERR_OK, 'size' => 100], $dir)['erro']), true);
confere('guardar com erro de envio',
    patro_guardar_envio(['name' => 'a.png', 'tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 0], $dir), ['erro' => 'O envio do logo falhou. Tente de novo.']);

// limpeza
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($dir);

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo certo\n";
exit($falhas ? 1 : 0);
