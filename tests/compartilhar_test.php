<?php
/**
 * Testes da prévia ao compartilhar o link (WhatsApp, Facebook, Instagram): etiquetas Open Graph no topo das páginas.
 * Rode com: php tests/compartilhar_test.php   (no XAMPP: C:\xampp\php\php tests\compartilhar_test.php)
 */
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

/** Monta o topo de uma página como o servidor faria e devolve as etiquetas meta (property/name => content). */
function metas_de(array $server, array $vars = []): array
{
    $_SERVER = $server + $_SERVER;
    extract($vars);
    ob_start();
    require __DIR__ . '/../includes/header.php';
    $html = ob_get_clean();
    $html = substr($html, 0, (int) strpos($html, '</head>'));
    preg_match_all('#<meta (?:property|name)="([^"]+)" content="([^"]*)"#', $html, $m, PREG_SET_ORDER);
    $metas = [];
    foreach ($m as $x) {
        $metas[$x[1]] = html_entity_decode($x[2], ENT_QUOTES, 'UTF-8');
    }
    return $metas;
}

$m = metas_de(['HTTPS' => 'on', 'HTTP_HOST' => 'lionsclubecornelio.com', 'SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/?utm=zap']);
$ev = evento();
confere('título', $m['og:title'] ?? null, $ev['nome'] . ' · ' . $ev['organizador']);
confere('descrição com data e cidade', str_contains($m['og:description'] ?? '', $ev['data_curta']) && str_contains($m['og:description'] ?? '', $ev['cidade']), true);
confere('imagem com endereço completo', (bool) preg_match('#^https://lionsclubecornelio\.com/assets/img/compartilhar\.jpg\?v=\d+$#', $m['og:image'] ?? ''), true);
confere('tamanho da imagem', [$m['og:image:width'] ?? null, $m['og:image:height'] ?? null], ['1200', '630']);
confere('texto alternativo da imagem', ($m['og:image:alt'] ?? '') !== '', true);
confere('endereço da página sem parâmetros', $m['og:url'] ?? null, 'https://lionsclubecornelio.com/');
confere('tipo e idioma', [$m['og:type'] ?? null, $m['og:locale'] ?? null, $m['og:site_name'] ?? null], ['website', 'pt_BR', $ev['nome']]);
confere('cartão grande no X/Twitter', $m['twitter:card'] ?? null, 'summary_large_image');
confere('arquivo da imagem existe e é leve (< 300 KB)', is_file(__DIR__ . '/../assets/img/compartilhar.jpg') && filesize(__DIR__ . '/../assets/img/compartilhar.jpg') < 300 * 1024, true);
$dim = @getimagesize(__DIR__ . '/../assets/img/compartilhar.jpg');
confere('imagem 1200 × 630', $dim ? [$dim[0], $dim[1]] : null, [1200, 630]);

confere('endereço do site (pasta no XAMPP)', site_base(['HTTP_HOST' => 'localhost', 'SCRIPT_NAME' => '/pedal-solidario/rotas.php']), 'http://localhost/pedal-solidario');

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo certo\n";
exit($falhas ? 1 : 0);
