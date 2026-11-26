<?php
/**
 * Abertura das inscrições: fecham sozinhas depois do prazo ou quando as vagas acabam.
 * Prazo, vagas e o interruptor manual ficam em config/evento.php.
 */
require_once __DIR__ . '/funcoes.php';
require_once __DIR__ . '/db.php';

/**
 * Regra pura (sem banco, testada em tests/inscricoes_test.php).
 * Devolve null se as inscrições estão abertas, ou o motivo: 'encerradas' (prazo vencido), 'fechadas' (interruptor do
 * painel desligado antes do prazo, ex.: antes de abrir) ou 'esgotadas'.
 * O prazo vale até o fim do dia; prazo vazio = sem prazo; vagas 0 = sem limite.
 */
function inscricoes_motivo_fechamento(bool $abertas, string $prazo_iso, int $vagas, int $ocupadas, string $hoje_iso): ?string
{
    if ($prazo_iso !== '' && $hoje_iso > $prazo_iso) {
        return 'encerradas';
    }
    if (!$abertas) {
        return 'fechadas';
    }
    if ($vagas > 0 && $ocupadas >= $vagas) {
        return 'esgotadas';
    }
    return null;
}

/** Inscrições que ocupam vaga: todas menos as canceladas (pagas e pendentes). */
function vagas_ocupadas(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM inscricoes WHERE status <> 'cancelado'")->fetchColumn();
}

/**
 * Situação das inscrições agora: null = abertas, ou 'encerradas' / 'fechadas' / 'esgotadas'.
 * Se o banco não responder, não fecha por vagas (o envio do formulário reconfere com o banco).
 */
function inscricoes_fechamento(?PDO $pdo = null): ?string
{
    $ev       = evento();
    $vagas    = (int) ($ev['inscricoes_vagas'] ?? 0);
    $ocupadas = 0;
    if ($vagas > 0) {
        try {
            $ocupadas = vagas_ocupadas($pdo ?? db());
        } catch (Throwable $e) {
            error_log('Pedal Solidário – não foi possível contar as vagas: ' . $e->getMessage());
        }
    }
    return inscricoes_motivo_fechamento(
        !empty($ev['inscricoes_abertas']),
        (string) ($ev['inscricoes_prazo_iso'] ?? ''),
        $vagas,
        $ocupadas,
        date('Y-m-d')
    );
}

/** Solta a trava de vagas (GET_LOCK) pega no envio da inscrição. Pode ser chamada mais de uma vez. */
function liberar_trava_vagas(?PDO $pdo, bool &$trava): void
{
    if ($trava && $pdo) {
        try {
            $pdo->query("SELECT RELEASE_LOCK('pedal_solidario_vagas')");
        } catch (Throwable $e) {
            // a trava também se solta sozinha quando a conexão fecha
        }
    }
    $trava = false;
}

/** Frase curta para o motivo do fechamento. */
function inscricoes_texto_fechamento(string $motivo): string
{
    if ($motivo === 'esgotadas') {
        return 'As vagas estão esgotadas.';
    }
    return $motivo === 'fechadas' ? 'As inscrições ainda não estão abertas.' : 'As inscrições estão encerradas.';
}
