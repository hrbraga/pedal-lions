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
foreach (['Olá, Atleta Maria da Silva!', 'PS12345678', 'Light', 'Manga longa, tamanho G', 'R$ 216,00', 'Domingo, 14 de março de 2027',
          'Bosque Municipal', '7:50 – Largada Light', '7:40 – Largada Hard', 'https://site.test/regulamento.php',
          'https://chat.whatsapp.com/ABC', '(43) 99675-9480', 'Lions Clube de Cornélio Procópio'] as $t) {
    contem('texto', $m['texto'], $t);
    contem('html', $m['html'], htmlspecialchars($t, ENT_QUOTES, 'UTF-8'));
}
// Links como botões "Clique aqui para…", cada um com o endereço certo
foreach (['https://site.test/programacao.php' => 'Clique aqui para ver a programação completa',
          'https://site.test/regulamento.php' => 'Clique aqui para ler o regulamento',
          'https://chat.whatsapp.com/ABC' => 'Clique aqui para entrar no grupo do WhatsApp',
          'https://site.test/avatar.php' => 'Clique aqui para fazer seu avatar'] as $url => $rotulo) {
    confere("botão: $rotulo", (bool) preg_match('#<a href="' . preg_quote($url, '#') . '"[^>]*>' . preg_quote(htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8'), '#') . '</a>#u', $m['html']), true);
}
contem('sem grupo, sem botão do grupo', email_pago_mensagem($insc, ['contato' => ['grupo_whatsapp' => ''] + $ev['contato']] + $ev, $site)['html'], 'Clique aqui para entrar no grupo', false);
contem('texto: link do avatar', $m['texto'], 'Para fazer seu avatar do evento, acesse: https://site.test/avatar.php');
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
contem('html escapa o nome', $x['html'], 'Olá, Atleta &lt;b&gt;&amp;&quot;Zé&quot; Ana!');
contem('html sem a tag crua', $x['html'], '<b>&', false);
contem('texto mantém o nome', $x['texto'], 'Olá, Atleta <b>&"Zé" Ana!');
confere('espaços extras no nome somem', str_contains(email_pago_mensagem(['nome' => '  Ana   Lima '] + $insc, $ev, $site)['texto'], 'Olá, Atleta Ana Lima!'), true);

// Logo do Lions anexado ao e-mail (aparece mesmo com imagens externas bloqueadas)
confere('logo: anexo aponta para o arquivo', isset($m['imagens']['logo-lions']) && is_file($m['imagens']['logo-lions']), true);
contem('logo: html usa o anexo', $m['html'], 'src="cid:logo-lions"');
contem('logo: texto alternativo', $m['html'], 'alt="Lions Clube"');

confere('endereço do site (produção)', email_site_base(['HTTPS' => 'on', 'HTTP_HOST' => 'lionsclubecornelio.com', 'SCRIPT_NAME' => '/admin/acao.php']), 'https://lionsclubecornelio.com');
confere('endereço do site (XAMPP em pasta)', email_site_base(['HTTP_HOST' => '[::1]', 'SCRIPT_NAME' => '/pedal-solidario/admin/acao.php']), 'http://[::1]/pedal-solidario');
confere('endereço do site (HTTPS off)', email_site_base(['HTTPS' => 'off', 'HTTP_HOST' => 'localhost:8123', 'SCRIPT_NAME' => '/admin/acao.php']), 'http://localhost:8123');

confere('motivo curto: primeira frase', email_motivo_curto('Erro de SMTP: Não foi possível conectar ao servidor SMTP. Failed to connect to server Código 10061'), 'Erro de SMTP: Não foi possível conectar ao servidor SMTP');
confere('motivo curto: sem ponto fica igual', email_motivo_curto('e-mail não configurado'), 'e-mail não configurado');
confere('motivo curto: ponto em endereço não corta', email_motivo_curto('Endereço de e-mail inválido:  (to): a.b'), 'Endereço de e-mail inválido:  (to): a.b');

// E-mail de teste do painel: mesma mensagem, dados de exemplo e [TESTE] no assunto
$tt = email_teste_mensagem($ev, $site);
confere('teste: assunto com horário (o Gmail não junta os testes)', (bool) preg_match('/^\[TESTE \d{2}:\d{2}:\d{2}\] Pagamento confirmado – 2º Pedal Solidário$/u', $tt['assunto']), true);
contem('teste: saudação de exemplo', $tt['texto'], 'Olá, Atleta Fulano de Tal!');
confere('teste: leva o logo', isset($tt['imagens']['logo-lions']), true);
contem('teste: código de exemplo', $tt['texto'], 'TESTE00001');
contem('teste: aviso de que é teste', $tt['html'], 'Este é um e-mail de teste');
contem('teste: botão do regulamento', $tt['html'], 'Clique aqui para ler o regulamento');

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo certo\n";
exit($falhas ? 1 : 0);
