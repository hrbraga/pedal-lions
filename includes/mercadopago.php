<?php
/**
 * Conversa com a API REST do Mercado Pago (sem SDK: HTTPS direto com cURL) e confere a assinatura dos avisos.
 * Credenciais em config/mercadopago.php (fora do git; modelo em config/mercadopago.exemplo.php).
 * Nenhuma função daqui lança exceção: erros voltam como ['http' => 0, 'erro' => '...'].
 */

/** Configuração do Mercado Pago, ou null se não existe/está com erro/incompleta (aí o pagamento online fica desligado). */
function mp_config(?string $arquivo = null): ?array
{
    $arquivo = $arquivo ?? __DIR__ . '/../config/mercadopago.php';
    if (!is_file($arquivo)) {
        return null;
    }
    try {
        $c = include $arquivo;
    } catch (\Throwable $e) {
        error_log('Pedal Solidário – config/mercadopago.php com erro: ' . $e->getMessage());
        return null;
    }
    if (!is_array($c) || trim((string) ($c['access_token'] ?? '')) === '' || trim((string) ($c['public_key'] ?? '')) === '') {
        return null;
    }
    return [
        'access_token'   => trim((string) $c['access_token']),
        'public_key'     => trim((string) $c['public_key']),
        'webhook_secret' => trim((string) ($c['webhook_secret'] ?? '')),
        'api'            => rtrim(trim((string) ($c['api'] ?? '')) ?: 'https://api.mercadopago.com', '/'),
    ];
}

/** Cancela um pagamento que ainda não foi pago (Pix pendente). O Mercado Pago recusa (400) se já foi pago. */
function mp_cancelar_pagamento(array $cfg, string $id): array
{
    return mp_requisicao($cfg, 'PUT', '/v1/payments/' . rawurlencode($id), ['status' => 'cancelled']);
}

/**
 * Uma chamada à API. Devolve ['http' => código (0 = sem resposta), 'dados' => JSON decodificado ou null,
 * 'erro' => null ou motivo curto (até 255 caracteres)].
 */
function mp_requisicao(array $cfg, string $metodo, string $caminho, ?array $corpo = null, ?string $idempotencia = null): array
{
    $cabecalhos = ['Authorization: Bearer ' . ($cfg['access_token'] ?? ''), 'Accept: application/json'];
    if ($corpo !== null) {
        $cabecalhos[] = 'Content-Type: application/json';
    }
    if ($idempotencia !== null) {
        $cabecalhos[] = 'X-Idempotency-Key: ' . $idempotencia;
    }
    $ch = curl_init(rtrim((string) ($cfg['api'] ?? ''), '/') . $caminho);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => $cabecalhos,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
    ]);
    if ($corpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $resp = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $erro_rede = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ['http' => 0, 'dados' => null, 'erro' => mb_substr('sem resposta do Mercado Pago (' . $erro_rede . ')', 0, 255)];
    }
    $dados = json_decode((string) $resp, true);
    $dados = is_array($dados) ? $dados : null;
    $erro  = null;
    if ($http < 200 || $http >= 300) {
        $erro = mb_substr('Mercado Pago respondeu ' . $http . (isset($dados['message']) ? ': ' . $dados['message'] : ''), 0, 255);
    }
    return ['http' => $http, 'dados' => $dados, 'erro' => $erro];
}

/** Separa "Maria da Silva" em ['Maria', 'da Silva']. */
function mp_nome_partes(string $nome): array
{
    $partes = preg_split('/\s+/u', trim($nome), 2) ?: [''];
    return [$partes[0], $partes[1] ?? ''];
}

/** Cria o Pix da inscrição, com vencimento $expira_iso (ex.: 2026-11-28T14:30:00.000-03:00). */
function mp_criar_pix(array $cfg, array $insc, string $expira_iso): array
{
    [$primeiro, $resto] = mp_nome_partes((string) $insc['nome']);
    return mp_requisicao($cfg, 'POST', '/v1/payments', [
        'transaction_amount' => round((float) $insc['valor_total'], 2),
        'description'        => mp_descricao($insc),
        'payment_method_id'  => 'pix',
        'date_of_expiration' => $expira_iso,
        'external_reference' => (string) $insc['codigo'],
        'payer'              => [
            'email'          => (string) $insc['email'],
            'first_name'     => $primeiro,
            'last_name'      => $resto,
            'identification' => ['type' => 'CPF', 'number' => (string) $insc['cpf']],
        ],
    ], 'pix-' . $insc['codigo'] . '-' . $expira_iso);
}

/**
 * Cobra no cartão com os dados do formulário do Mercado Pago (token, parcelas, bandeira, emissor, pagador).
 * O valor é sempre o total da inscrição — o do formulário é ignorado.
 */
function mp_criar_cartao(array $cfg, array $insc, array $form): array
{
    $pagador = (array) ($form['payer'] ?? []);
    $doc     = (array) ($pagador['identification'] ?? []);
    $corpo   = [
        'transaction_amount' => round((float) $insc['valor_total'], 2),
        'token'              => (string) ($form['token'] ?? ''),
        'installments'       => (int) ($form['installments'] ?? 1),
        'payment_method_id'  => (string) ($form['payment_method_id'] ?? ''),
        'description'        => mp_descricao($insc),
        'external_reference' => (string) $insc['codigo'],
        'payer'              => [
            'email'          => (string) (($pagador['email'] ?? '') ?: $insc['email']),
            'identification' => ['type' => (string) ($doc['type'] ?? 'CPF'), 'number' => preg_replace('/\D/', '', (string) ($doc['number'] ?? ''))],
        ],
    ];
    if (!empty($form['issuer_id'])) {
        $corpo['issuer_id'] = (string) $form['issuer_id'];
    }
    return mp_requisicao($cfg, 'POST', '/v1/payments', $corpo, 'cartao-' . $insc['codigo'] . '-' . sha1((string) ($form['token'] ?? '')));
}

function mp_buscar_pagamento(array $cfg, string $id): array
{
    return mp_requisicao($cfg, 'GET', '/v1/payments/' . rawurlencode($id));
}

function mp_descricao(array $insc): string
{
    $ev = function_exists('evento') ? evento() : [];
    return 'Inscrição ' . $insc['codigo'] . ' – ' . ($ev['nome'] ?? 'Pedal Solidário');
}

/**
 * Confere o cabeçalho x-signature ("ts=...,v1=...") de um aviso do Mercado Pago:
 * HMAC-SHA256 de "id:<data.id em minúsculas>;request-id:<x-request-id>;ts:<ts>;" com a assinatura secreta do webhook.
 */
function mp_assinatura_valida(string $segredo, string $x_signature, string $x_request_id, string $data_id): bool
{
    if ($segredo === '' || $x_signature === '') {
        return false;
    }
    $ts = $v1 = '';
    foreach (explode(',', $x_signature) as $parte) {
        [$k, $v] = array_pad(explode('=', trim($parte), 2), 2, '');
        if ($k === 'ts') {
            $ts = $v;
        } elseif ($k === 'v1') {
            $v1 = $v;
        }
    }
    if ($ts === '' || $v1 === '') {
        return false;
    }
    $manifesto = ($data_id !== '' ? 'id:' . strtolower($data_id) . ';' : '')
               . ($x_request_id !== '' ? 'request-id:' . $x_request_id . ';' : '')
               . 'ts:' . $ts . ';';
    return hash_equals(hash_hmac('sha256', $manifesto, $segredo), strtolower($v1));
}
