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

// Início programado (data e hora): antes dele "em_breve"; a partir do minuto marcado, abertas sozinhas
confere('antes do início = em breve', $f(true, '2027-03-12', 300, 0, '2026-12-01', '2026-12-01T12:00', '2026-12-01 11:59'), 'em_breve');
confere('no minuto do início = abertas', $f(true, '2027-03-12', 300, 0, '2026-12-01', '2026-12-01T12:00', '2026-12-01 12:00'), null);
confere('depois do início = abertas', $f(true, '2027-03-12', 300, 0, '2026-12-05', '2026-12-01T12:00', '2026-12-05 08:00'), null);
confere('dia anterior ao início = em breve', $f(true, '2027-03-12', 300, 0, '2026-11-30', '2026-12-01T12:00', '2026-11-30 23:59'), 'em_breve');
confere('sem início = abertas', $f(true, '2027-03-12', 300, 0, '2026-11-26', '', '2026-11-26 10:00'), null);
confere('interruptor desligado vence o início', $f(false, '2027-03-12', 300, 0, '2026-12-05', '2026-12-01T12:00', '2026-12-05 08:00'), 'fechadas');
confere('texto: em breve com data e hora', inscricoes_texto_fechamento('em_breve', '2026-12-01T12:00'), 'Início das Inscrições: 01/12/2026 às 12:00');
confere('início formatado', inscricoes_inicio_texto('2026-12-01T12:00'), '01/12/2026 às 12:00');
confere('início vazio formatado', inscricoes_inicio_texto(''), '');

// Salvar no painel que faz as inscrições abrirem NA HORA pede confirmação (ex.: início apagado sem querer)
confere('abertura imediata: fechada → aberta', inscricoes_abre_agora('em_breve', null), true);
confere('abertura imediata: fechada pelo painel → aberta', inscricoes_abre_agora('fechadas', null), true);
confere('abertura imediata: já estava aberta', inscricoes_abre_agora(null, null), false);
confere('abertura imediata: continua fechada', inscricoes_abre_agora('em_breve', 'em_breve'), false);
confere('abertura imediata: fechando', inscricoes_abre_agora(null, 'fechadas'), false);

echo $falhas ? "\n$falhas falha(s)\n" : "\ntodos passaram\n";
exit($falhas ? 1 : 0);
