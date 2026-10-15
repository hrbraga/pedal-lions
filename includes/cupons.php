<?php
/**
 * Regras dos cupons de desconto.
 * O desconto vale somente para o valor da INSCRIÇÃO (nunca para a camiseta).
 * Um cupom conta como "usado" enquanto a inscrição não estiver cancelada.
 */

/** Padroniza o código: maiúsculas, 3 a 30 caracteres (A-Z, 0-9, hífen e sublinhado). Vazio = inválido. */
function cupom_normalizar(string $codigo): string
{
    $codigo = strtoupper(trim($codigo));
    return preg_match('/^[A-Z0-9_-]{3,30}$/', $codigo) ? $codigo : '';
}

/** Quantas inscrições (não canceladas) já usaram o cupom. */
function cupom_usos(PDO $pdo, int $cupom_id): int
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM inscricoes WHERE cupom_id = :id AND status <> 'cancelado'");
    $st->execute([':id' => $cupom_id]);
    return (int) $st->fetchColumn();
}

/**
 * Busca um cupom que ainda pode ser usado.
 * Retorna [cupom|null, mensagem de erro]. Com $travar = true, bloqueia a linha (usar dentro de transação).
 */
function cupom_para_uso(PDO $pdo, string $codigo, bool $travar = false): array
{
    $st = $pdo->prepare('SELECT * FROM cupons WHERE codigo = :c LIMIT 1' . ($travar ? ' FOR UPDATE' : ''));
    $st->execute([':c' => $codigo]);
    $cupom = $st->fetch();

    if (!$cupom || !(int) $cupom['ativo']) {
        return [null, 'Cupom inválido.'];
    }
    if (cupom_usos($pdo, (int) $cupom['id']) >= (int) $cupom['quantidade']) {
        return [null, 'Este cupom esgotou.'];
    }
    return [$cupom, ''];
}

/** Valor do desconto sobre a inscrição (nunca maior que o valor da inscrição). */
function cupom_desconto(array $cupom, float $valor_inscricao): float
{
    $desconto = $cupom['tipo'] === 'percentual'
        ? round($valor_inscricao * ((float) $cupom['valor'] / 100), 2)
        : (float) $cupom['valor'];

    return round(max(0.0, min($desconto, $valor_inscricao)), 2);
}

/** "20%" ou "R$ 15,00" */
function cupom_descricao(array $cupom): string
{
    return $cupom['tipo'] === 'percentual'
        ? rtrim(rtrim(number_format((float) $cupom['valor'], 2, ',', ''), '0'), ',') . '%'
        : moeda((float) $cupom['valor']);
}

/** 'inativo', 'esgotado' ou 'ativo' */
function cupom_situacao(array $cupom, int $usos): string
{
    if (!(int) $cupom['ativo']) {
        return 'inativo';
    }
    return $usos >= (int) $cupom['quantidade'] ? 'esgotado' : 'ativo';
}

/* ---------- Proteção contra tentativa de adivinhar códigos ---------- */

function ip_visitante(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function cupom_bloqueado(PDO $pdo): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM cupom_tentativas WHERE ip = :ip AND criado_em > (NOW() - INTERVAL 15 MINUTE)');
    $st->execute([':ip' => ip_visitante()]);
    return (int) $st->fetchColumn() >= 15;
}

function cupom_registrar_falha(PDO $pdo): void
{
    $pdo->prepare('INSERT INTO cupom_tentativas (ip) VALUES (:ip)')->execute([':ip' => ip_visitante()]);
    $pdo->exec('DELETE FROM cupom_tentativas WHERE criado_em < (NOW() - INTERVAL 1 DAY)');
}
