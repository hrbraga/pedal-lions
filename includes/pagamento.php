<?php
/**
 * Regras do pagamento online sobre o banco: prazo, inscrição ativa por CPF e vagas.
 * "Ativa" = paga ou pendente dentro do prazo (canceladas e expiradas não ocupam vaga nem bloqueiam o CPF).
 */
require_once __DIR__ . '/funcoes.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inscricoes.php';

/** Horas para pagar depois de se inscrever. Passou, a inscrição expira e libera a vaga. */
const PAGAMENTO_PRAZO_HORAS = 6;

/** Inscrição ativa (paga ou pendente) com este CPF, fora a $exceto_id: ['id','status','expira_em'] ou null. */
function pagamento_cpf_ativo(PDO $pdo, string $cpf, int $exceto_id = 0): ?array
{
    $st = $pdo->prepare("SELECT id, status, expira_em FROM inscricoes
                         WHERE cpf = :cpf AND status IN ('pendente', 'pago') AND id <> :id
                         ORDER BY status = 'pago' DESC, id DESC LIMIT 1");
    $st->execute([':cpf' => $cpf, ':id' => $exceto_id]);
    return $st->fetch() ?: null;
}

/** Cabe mais uma inscrição ativa? $vagas <= 0 = sem limite. */
function pagamento_tem_vaga(PDO $pdo, int $vagas): bool
{
    return $vagas <= 0 || vagas_ocupadas($pdo) < $vagas;
}
