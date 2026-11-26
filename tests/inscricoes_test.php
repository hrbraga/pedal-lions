<?php
/**
 * Testes da regra de fechamento das inscrições (prazo e vagas).
 * Rode com: php tests/inscricoes_test.php   (no XAMPP: C:\xampp\php\php tests\inscricoes_test.php)
 */
require __DIR__ . '/../includes/inscricoes.php';

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

$f = 'inscricoes_motivo_fechamento';
confere('aberta antes do prazo e com vagas', $f(true, '2027-03-12', 300, 10, '2027-03-01'), null);
confere('aberta no último dia do prazo', $f(true, '2027-03-12', 300, 10, '2027-03-12'), null);
confere('encerrada no dia seguinte ao prazo', $f(true, '2027-03-12', 300, 10, '2027-03-13'), 'encerradas');
// Fechada pelo interruptor do painel (ex.: antes de abrir): "fechadas", com texto neutro; prazo vencido continua "encerradas"
confere('fechada pelo interruptor manual', $f(false, '2027-03-12', 300, 10, '2027-03-01'), 'fechadas');
confere('interruptor fechado depois do prazo = encerradas', $f(false, '2027-03-12', 300, 10, '2027-03-13'), 'encerradas');
confere('texto: fechadas pelo painel', inscricoes_texto_fechamento('fechadas'), 'As inscrições ainda não estão abertas.');
confere('texto: prazo encerrado', inscricoes_texto_fechamento('encerradas'), 'As inscrições estão encerradas.');
confere('texto: vagas esgotadas', inscricoes_texto_fechamento('esgotadas'), 'As vagas estão esgotadas.');
confere('aberta com uma vaga sobrando', $f(true, '2027-03-12', 300, 299, '2027-03-01'), null);
confere('esgotada ao atingir o limite', $f(true, '2027-03-12', 300, 300, '2027-03-01'), 'esgotadas');
confere('esgotada acima do limite', $f(true, '2027-03-12', 300, 305, '2027-03-01'), 'esgotadas');
confere('limite 0 = sem limite de vagas', $f(true, '2027-03-12', 0, 5000, '2027-03-01'), null);
confere('prazo vazio = sem prazo', $f(true, '', 300, 10, '2030-01-01'), null);
confere('prazo vencido tem prioridade sobre vagas', $f(true, '2027-03-12', 300, 300, '2027-03-13'), 'encerradas');

echo $falhas ? "\n$falhas falha(s)\n" : "\ntodos passaram\n";
exit($falhas ? 1 : 0);
