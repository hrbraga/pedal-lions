<?php
/**
 * Regras do pagamento online sobre o banco: prazo, inscrição ativa por CPF, vagas e aplicar os pagamentos
 * do Mercado Pago. "Ativa" = paga ou pendente dentro do prazo (canceladas e expiradas não ocupam vaga nem
 * bloqueiam o CPF).
 */
require_once __DIR__ . '/funcoes.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inscricoes.php';
require_once __DIR__ . '/mercadopago.php';

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

/**
 * Por que esta inscrição não pode ser excluída (null = pode): ainda dentro do prazo do Pix (o atleta pode pagar
 * pelo QR-Code do e-mail) ou já tem pagamento registrado no Mercado Pago. Excluir faria o dinheiro chegar sem dono.
 */
function pagamento_motivo_nao_excluir(PDO $pdo, array $insc): ?string
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM pagamentos WHERE inscricao_id = :id');
    $st->execute([':id' => (int) $insc['id']]);
    if ((int) $st->fetchColumn() > 0) {
        return 'Esta inscrição tem pagamento registrado no Mercado Pago e não pode ser excluída. Use Cancelar.';
    }
    if (($insc['status'] ?? '') === 'pendente' && !empty($insc['expira_em']) && strtotime((string) $insc['expira_em']) > time()) {
        return 'Esta inscrição ainda pode ser paga pelo Pix até ' . date('d/m \à\s H\hi', strtotime((string) $insc['expira_em']))
            . '. Use Cancelar ou aguarde o prazo.';
    }
    return null;
}

/** Texto da forma de pagamento para o painel/CSV: 'Pix', 'Cartão visa em 2x' ou '' (sem pagamento online). */
function pagamento_forma_texto(?string $metodo, $parcelas): string
{
    if ($metodo === null || $metodo === '') {
        return '';
    }
    if ($metodo === 'pix') {
        return 'Pix';
    }
    return 'Cartão ' . $metodo . ' em ' . max(1, (int) $parcelas) . 'x';
}

/** Pix pendente mais recente da inscrição: ['copia_cola','qr_base64','expira_em'] ou null. */
function pagamento_pix_atual(PDO $pdo, int $inscricao_id): ?array
{
    $st = $pdo->prepare("SELECT pix_copia_cola AS copia_cola, pix_qr_base64 AS qr_base64, pix_expira_em AS expira_em
                         FROM pagamentos WHERE inscricao_id = :id AND metodo = 'pix' AND status = 'pending'
                           AND pix_copia_cola IS NOT NULL AND (pix_expira_em IS NULL OR pix_expira_em > NOW())
                         ORDER BY id DESC LIMIT 1");
    $st->execute([':id' => $inscricao_id]);
    return $st->fetch() ?: null;
}

/**
 * Cria no Mercado Pago o Pix de uma inscrição pendente, vencendo junto com ela (expira_em).
 * null = criado (ou já havia um Pix válido); senão o motivo curto. Não muda a inscrição se o Mercado Pago falhar.
 */
function pagamento_gerar_pix(PDO $pdo, array $cfg_mp, int $inscricao_id): ?string
{
    $st = $pdo->prepare('SELECT *, expira_em > NOW() AS no_prazo FROM inscricoes WHERE id = :id');
    $st->execute([':id' => $inscricao_id]);
    $insc = $st->fetch();
    if (!$insc || $insc['status'] !== 'pendente') {
        return 'inscrição não está pendente';
    }
    if (empty($insc['expira_em']) || !(int) $insc['no_prazo']) {
        return 'o prazo de pagamento terminou';
    }
    if (pagamento_pix_atual($pdo, $inscricao_id) !== null) {
        return null;
    }
    $r = mp_criar_pix($cfg_mp, $insc, date('Y-m-d\TH:i:s.000P', strtotime((string) $insc['expira_em'])));
    if ($r['erro'] !== null || !is_array($r['dados']) || empty($r['dados']['id'])) {
        error_log('Pedal Solidário – Pix da inscrição ' . $insc['codigo'] . ' não gerado: ' . ($r['erro'] ?? 'resposta inesperada'));
        return $r['erro'] ?? 'resposta inesperada do Mercado Pago';
    }
    pagamento_registrar($pdo, $inscricao_id, $r['dados']);
    return null;
}

/** Guarda (ou atualiza) um pagamento do Mercado Pago na tabela pagamentos. */
/**
 * Tarifa que o Mercado Pago cobrou do clube neste pagamento (soma de fee_details com fee_payer = collector).
 * Os juros do parcelamento pagos pelo atleta (fee_payer = payer) não entram. Sem fee_details: null (ainda não informada).
 */
function pagamento_tarifa(array $mp): ?float
{
    if (!isset($mp['fee_details']) || !is_array($mp['fee_details'])) {
        return null;
    }
    $total = 0.0;
    foreach ($mp['fee_details'] as $f) {
        if (is_array($f) && ($f['fee_payer'] ?? '') === 'collector') {
            $total += (float) ($f['amount'] ?? 0);
        }
    }
    return round($total, 2);
}

function pagamento_registrar(PDO $pdo, int $inscricao_id, array $mp): void
{
    $pix    = ($mp['payment_method_id'] ?? '') === 'pix';
    $dados  = (array) ($mp['point_of_interaction']['transaction_data'] ?? []);
    $expira = !empty($mp['date_of_expiration']) ? date('Y-m-d H:i:s', strtotime((string) $mp['date_of_expiration'])) : null;
    $st = $pdo->prepare('INSERT INTO pagamentos (inscricao_id, mp_id, metodo, parcelas, status, status_detalhe, valor, taxa_mp, pix_copia_cola, pix_qr_base64, pix_expira_em)
                         VALUES (:i, :mp, :metodo, :parcelas, :status, :detalhe, :valor, :taxa, :copia, :qr, :expira)
                         ON DUPLICATE KEY UPDATE status = VALUES(status), status_detalhe = VALUES(status_detalhe), valor = VALUES(valor),
                             taxa_mp = COALESCE(VALUES(taxa_mp), taxa_mp),
                             pix_copia_cola = COALESCE(VALUES(pix_copia_cola), pix_copia_cola),
                             pix_qr_base64 = COALESCE(VALUES(pix_qr_base64), pix_qr_base64),
                             pix_expira_em = COALESCE(VALUES(pix_expira_em), pix_expira_em)');
    $st->execute([
        ':i'        => $inscricao_id,
        ':mp'       => (string) $mp['id'],
        ':metodo'   => mb_substr((string) ($mp['payment_method_id'] ?? '?'), 0, 20),
        ':parcelas' => $pix ? null : (int) ($mp['installments'] ?? 1),
        ':status'   => mb_substr((string) ($mp['status'] ?? '?'), 0, 30),
        ':detalhe'  => isset($mp['status_detail']) ? mb_substr((string) $mp['status_detail'], 0, 60) : null,
        ':valor'    => round((float) ($mp['transaction_amount'] ?? 0), 2),
        ':taxa'     => pagamento_tarifa($mp),
        ':copia'    => $pix ? ($dados['qr_code'] ?? null) : null,
        ':qr'       => $pix ? ($dados['qr_code_base64'] ?? null) : null,
        ':expira'   => $pix ? $expira : null,
    ]);
}

/** Acrescenta um destaque para o painel (" · " entre eles), sem repetir o mesmo texto. */
function pagamento_alertar(PDO $pdo, int $id, string $texto): void
{
    $st = $pdo->prepare('SELECT pagamento_alerta FROM inscricoes WHERE id = :id');
    $st->execute([':id' => $id]);
    $atual = (string) $st->fetchColumn();
    if ($atual !== '' && str_contains($atual, $texto)) {
        return;
    }
    $novo = mb_substr($atual === '' ? $texto : $atual . ' · ' . $texto, 0, 255);
    $pdo->prepare('UPDATE inscricoes SET pagamento_alerta = :a WHERE id = :id')->execute([':a' => $novo, ':id' => $id]);
}

/**
 * Aplica um pagamento como a API do Mercado Pago o descreve (nunca o corpo do aviso: quem chama já buscou na API).
 * Devolve: pago, pago_atrasado, sem_vaga, duplicidade, repetido, estornado, valor_divergente, desconhecido ou atualizado.
 * $vagas = limite de inscrições (0 = sem limite). Lança só se o banco falhar ou a trava de vagas não vier
 * (o aviso responde 500 e o Mercado Pago tenta de novo).
 */
function pagamento_processar(PDO $pdo, array $mp, int $vagas, string $site, ?array $cfg_email): string
{
    require_once __DIR__ . '/email_pagamento.php';
    $mp_id = (string) ($mp['id'] ?? '');
    $st = $pdo->prepare('SELECT id, valor_total, pagamento_mp_id FROM inscricoes WHERE codigo = :c');
    $st->execute([':c' => (string) ($mp['external_reference'] ?? '')]);
    $insc = $st->fetch();
    if (!$insc || $mp_id === '') {
        error_log('Pedal Solidário – pagamento ' . $mp_id . ' do Mercado Pago com referência desconhecida: ' . ($mp['external_reference'] ?? ''));
        return 'desconhecido';
    }
    $id = (int) $insc['id'];
    pagamento_registrar($pdo, $id, $mp);
    $status = (string) ($mp['status'] ?? '');

    if (in_array($status, ['refunded', 'charged_back'], true)) {
        if ((string) $insc['pagamento_mp_id'] !== $mp_id) {
            return 'atualizado';
        }
        pagamento_alertar($pdo, $id, 'estornado no Mercado Pago');
        return 'estornado';
    }
    if ($status !== 'approved') {
        return 'atualizado';
    }
    if (abs(round((float) ($mp['transaction_amount'] ?? 0), 2) - (float) $insc['valor_total']) > 0.004) {
        error_log('Pedal Solidário – pagamento ' . $mp_id . ' com valor ' . ($mp['transaction_amount'] ?? '?') . ' diferente do total ' . $insc['valor_total']);
        pagamento_alertar($pdo, $id, 'pagamento com valor diferente — conferir no Mercado Pago (' . $mp_id . ')');
        return 'valor_divergente';
    }

    // Uma confirmação por vez: a trava de vagas (a expirada pode voltar a ocupar vaga) e a linha travada
    $trava = travar_vagas($pdo);
    if (!$trava) {
        throw new RuntimeException('trava de vagas indisponível');
    }
    $resultado = 'repetido';
    $atual = [];
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT status, cpf, pagamento_mp_id, email_pago_em FROM inscricoes WHERE id = :id FOR UPDATE');
        $st->execute([':id' => $id]);
        $atual = $st->fetch();
        $quitar = $pdo->prepare("UPDATE inscricoes SET status = 'pago', pago_em = NOW(), pagamento_mp_id = :mp WHERE id = :id");
        if ((string) $atual['pagamento_mp_id'] === $mp_id) {
            $resultado = 'repetido';
        } elseif ($atual['status'] === 'pendente') {
            $quitar->execute([':mp' => $mp_id, ':id' => $id]);
            $resultado = 'pago';
        } elseif ($atual['status'] === 'expirado') {
            // Vagas contadas sem esta inscrição; o dinheiro entrou, então ela fica paga de qualquer jeito
            $cabe = pagamento_tem_vaga($pdo, $vagas) && !pagamento_cpf_ativo($pdo, (string) $atual['cpf'], $id);
            $quitar->execute([':mp' => $mp_id, ':id' => $id]);
            if ($cabe) {
                $resultado = 'pago_atrasado';
            } else {
                pagamento_alertar($pdo, $id, 'paga sem vaga — devolver o dinheiro');
                $resultado = 'sem_vaga';
            }
        } else {
            pagamento_alertar($pdo, $id, 'pagamento em duplicidade — devolver o dinheiro (Mercado Pago ' . $mp_id . ')');
            $resultado = 'duplicidade';
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        liberar_trava_vagas($pdo, $trava);
    }

    if (in_array($resultado, ['pago', 'pago_atrasado', 'sem_vaga'], true)) {
        pagamento_cancelar_pix_pendentes($pdo, mp_config(), $id, $mp_id); // o QR antigo não pode ser pago de novo
    }
    if (in_array($resultado, ['pago', 'pago_atrasado'], true) && empty($atual['email_pago_em'])) {
        email_pago_enviar($pdo, $id, $site, $cfg_email); // falha fica registrada na inscrição; o pagamento continua confirmado
    }
    return $resultado;
}

/**
 * Cancela no Mercado Pago os Pix ainda pendentes da inscrição (já paga por outro meio ou cancelada), para o atleta
 * não pagar duas vezes pelo QR do e-mail ou da página. Pix que já foi pago lá não é cancelado (o Mercado Pago recusa)
 * e segue o caminho normal do aviso. Devolve quantos cancelou; nunca lança.
 */
function pagamento_cancelar_pix_pendentes(PDO $pdo, ?array $cfg_mp, int $inscricao_id, string $exceto_mp_id = ''): int
{
    if (!$cfg_mp) {
        return 0;
    }
    $cancelados = 0;
    try {
        $st = $pdo->prepare("SELECT mp_id FROM pagamentos WHERE inscricao_id = :id AND metodo = 'pix' AND status = 'pending' AND mp_id <> :exceto");
        $st->execute([':id' => $inscricao_id, ':exceto' => $exceto_mp_id]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $mp_id) {
            $r = mp_cancelar_pagamento($cfg_mp, (string) $mp_id);
            if ($r['http'] === 200 && ($r['dados']['status'] ?? '') === 'cancelled') {
                $pdo->prepare("UPDATE pagamentos SET status = 'cancelled', status_detalhe = :d WHERE mp_id = :mp")
                    ->execute([':d' => mb_substr((string) ($r['dados']['status_detail'] ?? 'by_collector'), 0, 60), ':mp' => $mp_id]);
                $cancelados++;
            } else {
                error_log('Pedal Solidário – Pix ' . $mp_id . ' não cancelado: ' . ($r['erro'] ?? 'resposta inesperada'));
            }
        }
    } catch (Throwable $e) {
        error_log('Pedal Solidário – cancelar Pix pendentes da inscrição ' . $inscricao_id . ': ' . $e->getMessage());
    }
    return $cancelados;
}

/**
 * A página de pagamento pergunta o status a cada poucos segundos; se o aviso do Mercado Pago ainda não chegou,
 * consulta a API (no máximo uma vez a cada 10 s por pagamento) e aplica o resultado.
 * Vale também para expiradas (Pix pago com a página fechada e sem aviso: entra pela regra do pagamento atrasado)
 * e para pagamentos já registrados como aprovados cuja confirmação falhou no meio.
 */
function pagamento_consultar(PDO $pdo, array $cfg_mp, array $insc, int $vagas, string $site, ?array $cfg_email): void
{
    if (!in_array($insc['status'] ?? '', ['pendente', 'expirado'], true)) {
        return;
    }
    $st = $pdo->prepare("SELECT mp_id FROM pagamentos WHERE inscricao_id = :id AND status IN ('pending', 'in_process', 'authorized', 'approved')
                         ORDER BY status = 'approved' DESC, id DESC LIMIT 1");
    $st->execute([':id' => (int) $insc['id']]);
    $mp_id = $st->fetchColumn();
    if ($mp_id === false) {
        return;
    }
    // Marca a consulta antes de chamar a API: se outra aba perguntou há menos de 10 s, não faz nada
    $marca = $pdo->prepare('UPDATE pagamentos SET consultado_em = NOW()
                            WHERE mp_id = :mp AND (consultado_em IS NULL OR consultado_em < NOW() - INTERVAL 10 SECOND)');
    $marca->execute([':mp' => $mp_id]);
    if ($marca->rowCount() === 0) {
        return;
    }
    $r = mp_buscar_pagamento($cfg_mp, (string) $mp_id);
    if ($r['http'] === 200 && is_array($r['dados'])) {
        pagamento_processar($pdo, $r['dados'], $vagas, $site, $cfg_email);
    } else {
        error_log('Pedal Solidário – consulta do pagamento ' . $mp_id . ': ' . ($r['erro'] ?? '?'));
    }
}
