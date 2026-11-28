<?php
/**
 * Testes do cliente da API do Mercado Pago, contra o servidor falso tests/mp_falso.php (sem internet).
 * Rode com: php tests/mercadopago_test.php   (no XAMPP: C:\xampp\php\php tests\mercadopago_test.php)
 */
require __DIR__ . '/../includes/mercadopago.php';
require __DIR__ . '/mp_falso_util.php';

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

$porta = 25261;
$proc  = mp_falso_subir($porta);
$tmp   = sys_get_temp_dir() . '/mp_cfg_' . getmypid() . '.php';

try {
    // Configuração
    confere('sem arquivo de configuração', mp_config(__DIR__ . '/nao-existe.php'), null);
    file_put_contents($tmp, "<?php return ['access_token' => 'x' 'public_key' => 'y'];\n"); // falta a vírgula
    confere('arquivo com erro de digitação não derruba a página', mp_config($tmp), null);
    file_put_contents($tmp, "<?php return ['access_token' => 'TEST-1'];\n");
    confere('sem public_key não serve', mp_config($tmp), null);
    file_put_contents($tmp, "<?php return ['access_token' => 'TEST-1', 'public_key' => 'TEST-pk'];\n");
    $c = mp_config($tmp);
    confere('API padrão do Mercado Pago', $c['api'] ?? null, 'https://api.mercadopago.com');
    confere('segredo do aviso vazio por padrão', $c['webhook_secret'] ?? null, '');

    $cfg  = ['access_token' => 'TEST-123', 'public_key' => 'TEST-pk', 'webhook_secret' => 's', 'api' => 'http://127.0.0.1:' . $porta];
    $insc = ['codigo' => 'ABC1234567', 'nome' => 'Maria da Silva Souza', 'email' => 'maria@t.test', 'cpf' => '52998224725', 'valor_total' => '92.70'];
    $ev_nome = '2º Pedal Solidário';

    // Pix
    $r = mp_criar_pix($cfg, $insc, '2026-11-28T14:30:00.000-03:00');
    confere('Pix criado (201)', $r['http'], 201);
    confere('Pix pendente', $r['dados']['status'] ?? null, 'pending');
    confere('copia e cola do Pix', str_starts_with((string) ($r['dados']['point_of_interaction']['transaction_data']['qr_code'] ?? ''), '00020126FALSO'), true);
    $ped = mp_falso_estado($porta)['pedidos'];
    $p = end($ped);
    confere('valor enviado = total da inscrição', $p['corpo']['transaction_amount'] ?? null, 92.7);
    confere('forma pix', $p['corpo']['payment_method_id'] ?? null, 'pix');
    confere('referência = código', $p['corpo']['external_reference'] ?? null, 'ABC1234567');
    confere('vencimento do Pix', $p['corpo']['date_of_expiration'] ?? null, '2026-11-28T14:30:00.000-03:00');
    confere('CPF do pagador', $p['corpo']['payer']['identification']['number'] ?? null, '52998224725');
    confere('nome do pagador separado', [$p['corpo']['payer']['first_name'] ?? null, $p['corpo']['payer']['last_name'] ?? null], ['Maria', 'da Silva Souza']);
    confere('chave de idempotência enviada', ($p['idem'] ?? '') !== '', true);
    $r2 = mp_criar_pix($cfg, $insc, '2026-11-28T14:30:00.000-03:00');
    confere('mesmo Pix de novo = mesmo pagamento', $r2['dados']['id'] ?? null, $r['dados']['id'] ?? 'x');

    // Cartão: valor sempre o da inscrição, nunca o do formulário
    $form = ['token' => 'APRO', 'installments' => 2, 'payment_method_id' => 'visa', 'issuer_id' => '25', 'transaction_amount' => 1.00,
             'payer' => ['email' => 'maria@t.test', 'identification' => ['type' => 'CPF', 'number' => '52998224725']]];
    $r = mp_criar_cartao($cfg, $insc, $form);
    confere('cartão APRO aprovado', $r['dados']['status'] ?? null, 'approved');
    $ped = mp_falso_estado($porta)['pedidos'];
    $p = end($ped);
    confere('cartão cobra o total da inscrição', $p['corpo']['transaction_amount'] ?? null, 92.7);
    confere('parcelas enviadas', $p['corpo']['installments'] ?? null, 2);
    confere('cartão OTHE recusado', mp_criar_cartao($cfg, $insc, ['token' => 'OTHE'] + $form)['dados']['status'] ?? null, 'rejected');
    confere('cartão CONT em análise', mp_criar_cartao($cfg, $insc, ['token' => 'CONT'] + $form)['dados']['status'] ?? null, 'in_process');

    // Consulta
    $id = (string) $r['dados']['id'];
    confere('busca pagamento existente', mp_buscar_pagamento($cfg, $id)['dados']['status'] ?? null, 'approved');
    confere('pagamento inexistente = 404', mp_buscar_pagamento($cfg, '999999')['http'], 404);
    confere('token inválido = 401', mp_buscar_pagamento(['access_token' => ''] + $cfg, $id)['http'], 401);

    // Cancelamento (Pix que não deve mais ser pago)
    $pix = mp_criar_pix($cfg, ['codigo' => 'CANC000001'] + $insc, '2026-11-28T14:30:00.000-03:00');
    $c = mp_cancelar_pagamento($cfg, (string) $pix['dados']['id']);
    confere('cancela Pix pendente', [$c['http'], $c['dados']['status'] ?? null], [200, 'cancelled']);
    $ped = mp_falso_estado($porta)['pedidos'];
    $p = end($ped);
    confere('cancelamento: PUT com status cancelled', [$p['metodo'], $p['corpo']], ['PUT', ['status' => 'cancelled']]);
    confere('não cancela pagamento aprovado', mp_cancelar_pagamento($cfg, $id)['http'], 400);

    // Fora do ar
    $t0 = microtime(true);
    $r = mp_buscar_pagamento(['api' => 'http://127.0.0.1:25269'] + $cfg, $id);
    confere('fora do ar: http 0', $r['http'], 0);
    confere('fora do ar: erro curto', is_string($r['erro']) && $r['erro'] !== '' && mb_strlen($r['erro']) <= 255, true);
    confere('fora do ar: em menos de 20 s', microtime(true) - $t0 < 20, true);

    // Assinatura do aviso
    $v1 = hash_hmac('sha256', 'id:123;request-id:req-1;ts:1700000000;', 's3cr3t');
    confere('assinatura válida', mp_assinatura_valida('s3cr3t', "ts=1700000000,v1=$v1", 'req-1', '123'), true);
    confere('assinatura com espaços', mp_assinatura_valida('s3cr3t', "ts=1700000000, v1=$v1", 'req-1', '123'), true);
    confere('assinatura trocada', mp_assinatura_valida('s3cr3t', 'ts=1700000000,v1=' . str_repeat('0', 64), 'req-1', '123'), false);
    confere('segredo vazio nunca vale', mp_assinatura_valida('', "ts=1700000000,v1=$v1", 'req-1', '123'), false);
    confere('cabeçalho vazio', mp_assinatura_valida('s3cr3t', '', 'req-1', '123'), false);
    $v1b = hash_hmac('sha256', 'id:abc;request-id:req-1;ts:1700000000;', 's3cr3t');
    confere('id alfanumérico em minúsculas', mp_assinatura_valida('s3cr3t', "ts=1700000000,v1=$v1b", 'req-1', 'ABC'), true);
    $v1c = hash_hmac('sha256', 'id:123;ts:1700000000;', 's3cr3t');
    confere('sem request-id omite a parte', mp_assinatura_valida('s3cr3t', "ts=1700000000,v1=$v1c", '', '123'), true);
} finally {
    @unlink($tmp);
    mp_falso_parar($proc, $porta);
}

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo certo\n";
exit($falhas ? 1 : 0);
