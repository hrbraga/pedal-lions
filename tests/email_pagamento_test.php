<?php
/**
 * Testes da mensagem "Pagamento confirmado" (sem banco nem rede).
 * Rode com: php tests/email_pagamento_test.php   (no XAMPP: C:\xampp\php\php tests\email_pagamento_test.php)
 */
require __DIR__ . '/../includes/email_pagamento.php';

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
function contem(string $nome, string $texto, string $trecho, bool $deve = true): void
{
    confere($nome . ($deve ? ' (contém ' : ' (não contém ') . $trecho . ')', str_contains($texto, $trecho), $deve);
}

$ev = [
    'nome'          => '2º Pedal Solidário',
    'organizador'   => 'Lions Clube de Cornélio Procópio',
    'data_extenso'  => 'Domingo, 14 de março de 2027',
    'local_largada' => 'Bosque Municipal de Cornélio Procópio',
    'categorias'    => [['slug' => 'light', 'nome' => 'Light'], ['slug' => 'hard', 'nome' => 'Hard']],
    'camiseta'      => ['modelos' => [['slug' => 'curta', 'nome' => 'Manga curta', 'valor' => 130], ['slug' => 'longa', 'nome' => 'Manga longa', 'valor' => 150]]],
    'contato'       => ['whatsapp' => '(43) 99675-9480', 'grupo_whatsapp' => 'https://chat.whatsapp.com/ABC'],
    'programacao'   => [
        ['hora' => '6:00', 'titulo' => 'Retirada de kits', 'desc' => ''],
        ['hora' => '7:40', 'titulo' => 'Largada Hard', 'desc' => ''],
        ['hora' => '7:50', 'titulo' => 'Largada Light', 'desc' => ''],
    ],
];
$insc = ['nome' => 'Maria da Silva', 'email' => 'maria@t.test', 'codigo' => 'PS12345678', 'categoria' => 'light',
         'camiseta_modelo' => 'longa', 'camiseta_tamanho' => 'G', 'valor_total' => '216.00'];
$site = 'https://site.test';

$m = email_pago_mensagem($insc, $ev, $site);
confere('assunto', $m['assunto'], 'Pagamento confirmado – 2º Pedal Solidário');
foreach (['Olá, Maria', 'PS12345678', 'Light', 'Manga longa, tamanho G', 'R$ 216,00', 'Domingo, 14 de março de 2027',
          'Bosque Municipal', '7:50 – Largada Light', '7:40 – Largada Hard', 'https://site.test/regulamento.php',
          'https://chat.whatsapp.com/ABC', '(43) 99675-9480', 'Lions Clube de Cornélio Procópio'] as $t) {
    contem('texto', $m['texto'], $t);
    contem('html', $m['html'], htmlspecialchars($t, ENT_QUOTES, 'UTF-8'));
}
contem('texto só com largadas', $m['texto'], 'Retirada de kits', false);

$sem = email_pago_mensagem(['camiseta_tamanho' => null, 'camiseta_modelo' => null] + $insc, $ev, $site);
contem('sem camiseta', $sem['texto'], 'Camiseta', false);
contem('sem camiseta (html)', $sem['html'], 'Camiseta', false);

$xyz = email_pago_mensagem(['camiseta_modelo' => 'xyz', 'camiseta_tamanho' => 'M'] + $insc, $ev, $site);
contem('modelo removido mostra o identificador', $xyz['texto'], 'xyz, tamanho M');

$ev2 = $ev;
$ev2['contato']['grupo_whatsapp'] = '';
$g = email_pago_mensagem($insc, $ev2, $site);
contem('sem grupo (texto)', $g['texto'], 'chat.whatsapp', false);
contem('sem grupo (html)', $g['html'], 'chat.whatsapp', false);

$x = email_pago_mensagem(['nome' => '<b>&"Zé" Ana'] + $insc, $ev, $site);
contem('html escapa o nome', $x['html'], 'Olá, &lt;b&gt;&amp;&quot;Zé&quot;!');
contem('html sem a tag crua', $x['html'], '<b>&', false);
contem('texto mantém o nome', $x['texto'], 'Olá, <b>&"Zé"!');

confere('endereço do site (produção)', email_site_base(['HTTPS' => 'on', 'HTTP_HOST' => 'lionsclubecornelio.com', 'SCRIPT_NAME' => '/admin/acao.php']), 'https://lionsclubecornelio.com');
confere('endereço do site (XAMPP em pasta)', email_site_base(['HTTP_HOST' => '[::1]', 'SCRIPT_NAME' => '/pedal-solidario/admin/acao.php']), 'http://[::1]/pedal-solidario');
confere('endereço do site (HTTPS off)', email_site_base(['HTTPS' => 'off', 'HTTP_HOST' => 'localhost:8123', 'SCRIPT_NAME' => '/admin/acao.php']), 'http://localhost:8123');

confere('motivo curto: primeira frase', email_motivo_curto('Erro de SMTP: Não foi possível conectar ao servidor SMTP. Failed to connect to server Código 10061'), 'Erro de SMTP: Não foi possível conectar ao servidor SMTP');
confere('motivo curto: sem ponto fica igual', email_motivo_curto('e-mail não configurado'), 'e-mail não configurado');
confere('motivo curto: ponto em endereço não corta', email_motivo_curto('Endereço de e-mail inválido:  (to): a.b'), 'Endereço de e-mail inválido:  (to): a.b');

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo certo\n";
exit($falhas ? 1 : 0);
