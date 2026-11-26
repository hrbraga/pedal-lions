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
 * painel desligado antes do prazo), 'em_breve' (antes do início programado) ou 'esgotadas'.
 * $inicio: 'AAAA-MM-DDTHH:MM' (vazio = sem início programado); $agora: 'AAAA-MM-DD HH:MM'.
 * O prazo vale até o fim do dia; prazo vazio = sem prazo; vagas 0 = sem limite.
 */
function inscricoes_motivo_fechamento(bool $abertas, string $prazo_iso, int $vagas, int $ocupadas, string $hoje_iso,
    string $inicio = '', string $agora = ''): ?string
{
    if ($prazo_iso !== '' && $hoje_iso > $prazo_iso) {
        return 'encerradas';
    }
    if (!$abertas) {
        return 'fechadas';
    }
    if ($inicio !== '' && $agora !== '' && $agora < str_replace('T', ' ', $inicio)) {
        return 'em_breve';
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
 * Situação das inscrições agora: null = abertas, ou 'encerradas' / 'fechadas' / 'em_breve' / 'esgotadas'.
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
        date('Y-m-d'),
        (string) ($ev['inscricoes_inicio'] ?? ''),
        date('Y-m-d H:i')
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

/** '2026-12-01T12:00' → '01/12/2026 às 12:00' (vazio ou inválido → ''). */
function inscricoes_inicio_texto(string $inicio): string
{
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $inicio);
    return $d && $d->format('Y-m-d\TH:i') === $inicio ? $d->format('d/m/Y') . ' às ' . $d->format('H:i') : '';
}

/** Frase curta para o motivo do fechamento ($inicio: para o 'em_breve'). */
function inscricoes_texto_fechamento(string $motivo, string $inicio = ''): string
{
    if ($motivo === 'em_breve') {
        if ($inicio === '' && function_exists('evento')) {
            $inicio = (string) (evento()['inscricoes_inicio'] ?? '');
        }
        $quando = inscricoes_inicio_texto($inicio);
        return $quando !== '' ? 'Início das Inscrições: ' . $quando : 'As inscrições ainda não estão abertas.';
    }
    if ($motivo === 'esgotadas') {
        return 'As vagas estão esgotadas.';
    }
    return $motivo === 'fechadas' ? 'As inscrições ainda não estão abertas.' : 'As inscrições estão encerradas.';
}

/** O Salvar vai fazer as inscrições abrirem agora (estavam fechadas e passariam a abertas)? Pede confirmação. */
function inscricoes_abre_agora(?string $motivo_antes, ?string $motivo_depois): bool
{
    return $motivo_antes !== null && $motivo_depois === null;
}

/** Situação das inscrições com os dados que vão ser salvos (mesma regra de inscricoes_fechamento). */
function inscricoes_fechamento_de(array $dados, int $ocupadas): ?string
{
    return inscricoes_motivo_fechamento(
        !empty($dados['inscricoes_abertas']),
        (string) ($dados['inscricoes_prazo_iso'] ?? ''),
        (int) ($dados['inscricoes_vagas'] ?? 0),
        $ocupadas,
        date('Y-m-d'),
        (string) ($dados['inscricoes_inicio'] ?? ''),
        date('Y-m-d H:i')
    );
}
