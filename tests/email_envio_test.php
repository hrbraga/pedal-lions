<?php
/**
 * Testes do envio do e-mail por SMTP, contra o servidor falso tests/smtp_falso.php (sem internet).
 * Rode com: php tests/email_envio_test.php   (no XAMPP: C:\xampp\php\php tests\email_envio_test.php)
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

$porta   = 25251;
$arquivo = sys_get_temp_dir() . '/smtp_falso_' . getmypid() . '.txt';
@unlink($arquivo);
$proc = proc_open([PHP_BINARY, __DIR__ . '/smtp_falso.php', (string) $porta, $arquivo], [], $pipes);
for ($k = 0; $k < 50 && !($s = @fsockopen('127.0.0.1', $porta, $en, $es, 0.2)); $k++) {
    usleep(100000);
}
if ($s) {
    fclose($s);
}

try {
    confere('sem arquivo de configuração', email_config(__DIR__ . '/nao-existe.php'), null);
    $quebrado = sys_get_temp_dir() . '/email_quebrado_' . getmypid() . '.php';
    file_put_contents($quebrado, "<?php return ['host' => 'smtp.hostinger.com' 'porta' => 465];
"); // falta a vírgula
    try {
        $r = email_config($quebrado);
    } catch (\Throwable $e) {
        $r = 'exceção: ' . get_class($e);
    }
    @unlink($quebrado);
    confere('arquivo de configuração com erro de digitação não derruba a página', $r, null);
    confere('sem configuração não envia', email_enviar(null, 'maria@t.test', 'Maria', ['assunto' => 'a', 'texto' => 'b', 'html' => 'c']), 'e-mail não configurado');

    $cfg = ['host' => '127.0.0.1', 'porta' => $porta, 'seguranca' => '', 'usuario' => 'contato@exemplo.test', 'senha' => 'x',
            'nome' => 'Lions Clube de Cornélio Procópio'];
    $msg = ['assunto' => 'Pagamento confirmado – 2º Pedal Solidário', 'texto' => "Olá, Maria!\n", 'html' => '<p>Olá, Maria!</p>'];
    // A conexão do teste acima (fsockopen) já foi atendida e fechada pelo servidor falso
    confere('envio aceito', email_enviar($cfg, 'maria@t.test', 'Maria Silva', $msg), null);
    $rec = (string) @file_get_contents($arquivo);
    foreach (['To: Maria Silva <maria@t.test>', 'contato@exemplo.test', 'multipart/alternative'] as $t) {
        confere("mensagem recebida contém $t", str_contains($rec, $t), true);
    }
    $cab = iconv_mime_decode_headers(preg_split('/\r?\n\r?\n/', $rec, 2)[0], ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: [];
    confere('remetente com o nome do Lions', $cab['From'] ?? null, 'Lions Clube de Cornélio Procópio <contato@exemplo.test>');
    confere('assunto em UTF-8', $cab['Subject'] ?? null, 'Pagamento confirmado – 2º Pedal Solidário');

    $r = email_enviar($cfg, 'nao-e-email', 'X', $msg);
    confere('endereço inválido dá erro', is_string($r) && $r !== '', true);

    $t0 = microtime(true);
    $r = email_enviar(['porta' => 25259] + $cfg, 'maria@t.test', 'Maria', $msg);
    confere('servidor fora do ar dá erro', is_string($r) && $r !== '', true);
    confere('erro cabe na coluna (até 255)', is_string($r) && mb_strlen($r) <= 255, true);
    confere('erro em menos de 20 s', microtime(true) - $t0 < 20, true);

    // Servidor que aceita a conexão e trava: também tem de desistir em segundos
    $mudo = proc_open([PHP_BINARY, __DIR__ . '/smtp_falso.php', '25253', $arquivo . '.mudo', 'mudo'], [], $p2);
    usleep(500000);
    $t0 = microtime(true);
    $r = email_enviar(['porta' => 25253] + $cfg, 'maria@t.test', 'Maria', $msg);
    $tempo = microtime(true) - $t0;
    proc_terminate($mudo);
    proc_close($mudo);
    confere('servidor travado dá erro', is_string($r) && $r !== '', true);
    confere('servidor travado: erro em menos de 40 s (' . round($tempo) . ' s)', $tempo < 40, true);
} finally {
    proc_terminate($proc);
    proc_close($proc);
    @unlink($arquivo);
}

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo certo\n";
exit($falhas ? 1 : 0);
