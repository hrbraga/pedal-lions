<?php
/**
 * Servidor SMTP falso, só para testes: aceita qualquer login e guarda cada mensagem num arquivo.
 * Uso: php tests/smtp_falso.php <porta> <arquivo>
 * Cada mensagem recebida é anexada ao arquivo, seguida de uma linha "=====". Roda até ser encerrado.
 */
[$porta, $arquivo] = [(int) ($argv[1] ?? 2525), (string) ($argv[2] ?? 'smtp_falso.txt')];
$srv = stream_socket_server("tcp://127.0.0.1:$porta", $errno, $errstr);
if (!$srv) {
    fwrite(STDERR, "não abriu a porta $porta: $errstr\n");
    exit(1);
}
while ($c = @stream_socket_accept($srv, -1)) {
    $diz = function (string $linha) use ($c) {
        fwrite($c, $linha . "\r\n");
    };
    $diz('220 smtp-falso pronto');
    $dados = false;
    $msg = '';
    while (($l = fgets($c)) !== false) {
        if ($dados) {
            if (rtrim($l, "\r\n") === '.') {
                file_put_contents($arquivo, $msg . "\n=====\n", FILE_APPEND);
                $dados = false;
                $msg = '';
                $diz('250 recebida');
            } else {
                $msg .= (substr($l, 0, 2) === '..' ? substr($l, 1) : $l);
            }
            continue;
        }
        $cmd = strtoupper(substr(trim($l), 0, 4));
        if ($cmd === 'EHLO') {
            $diz('250-smtp-falso');
            $diz('250 AUTH LOGIN PLAIN');
        } elseif ($cmd === 'HELO') {
            $diz('250 smtp-falso');
        } elseif ($cmd === 'AUTH') {
            $partes = preg_split('/\s+/', trim($l));
            if (strtoupper($partes[1] ?? '') === 'LOGIN') { // pede usuário e senha
                $diz('334 VXNlcm5hbWU6');
                fgets($c);
                $diz('334 UGFzc3dvcmQ6');
                fgets($c);
            } elseif (!isset($partes[2])) { // AUTH PLAIN sem os dados na mesma linha
                $diz('334 ');
                fgets($c);
            }
            $diz('235 ok');
        } elseif ($cmd === 'DATA') {
            $dados = true;
            $diz('354 manda');
        } elseif ($cmd === 'QUIT') {
            $diz('221 tchau');
            break;
        } else { // MAIL, RCPT, RSET, NOOP
            $diz('250 ok');
        }
    }
    fclose($c);
}
