<?php
/**
 * Testes das regras de pagamento sobre o banco local (expiração, inscrição ativa, vagas, processar pagamentos).
 * Usa o banco do XAMPP; as linhas de teste têm código TSTPG… e são apagadas no fim.
 * Rode com: php tests/pagamento_test.php   (no XAMPP: C:\xampp\php\php tests\pagamento_test.php)
 */
require_once __DIR__ . '/../includes/funcoes.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/inscricoes.php';
require_once __DIR__ . '/../includes/cupons.php';
require_once __DIR__ . '/../includes/pagamento.php';
require_once __DIR__ . '/../includes/mercadopago.php';
require_once __DIR__ . '/../includes/email_pagamento.php';
require_once __DIR__ . '/mp_falso_util.php';

const PORTA_MP   = 25264; // Mercado Pago falso
const PORTA_SITE = 25262; // o site, para chamar os endpoints
const RAIZ       = __DIR__ . '/..';

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

$pdo = db();
$seq = 0;
/** Insere uma inscrição de teste e devolve o id. $expira: expressão SQL (ex.: "NOW() - INTERVAL 1 MINUTE") ou null. */
function insc_teste(array $campos = [], ?string $expira = null): int
{
    global $pdo, $seq;
    $seq++;
    $d = $campos + [
        'codigo' => sprintf('TSTPG%05d', $seq), 'nome' => 'Teste Pagamento ' . $seq, 'cpf' => sprintf('900000%05d', $seq),
        'status' => 'pendente', 'valor_total' => '92.70', 'cupom_id' => null, 'pago_em' => null,
    ];
    $st = $pdo->prepare("INSERT INTO inscricoes (codigo, nome, cpf, nascimento, sexo, email, telefone, cidade, uf, categoria,
            emerg_nome, emerg_tel, valor_inscricao, valor_total, status, cupom_id, pago_em, expira_em)
        VALUES (:codigo, :nome, :cpf, '1990-01-01', 'F', 'teste@t.test', '43999999999', 'Cornélio', 'PR', 'light',
            'Contato', '43988888888', 85.00, :valor_total, :status, :cupom_id, :pago_em, " . ($expira ?? 'NULL') . ')');
    $st->execute([':codigo' => $d['codigo'], ':nome' => $d['nome'], ':cpf' => $d['cpf'], ':valor_total' => $d['valor_total'],
                  ':status' => $d['status'], ':cupom_id' => $d['cupom_id'], ':pago_em' => $d['pago_em']]);
    return (int) $pdo->lastInsertId();
}
function status_de(int $id): string
{
    global $pdo;
    $st = $pdo->prepare('SELECT status FROM inscricoes WHERE id = :id');
    $st->execute([':id' => $id]);
    return (string) $st->fetchColumn();
}
function linha(int $id): array
{
    global $pdo;
    $st = $pdo->prepare('SELECT * FROM inscricoes WHERE id = :id');
    $st->execute([':id' => $id]);
    return $st->fetch() ?: [];
}
function limpar(): void
{
    global $pdo;
    $pdo->exec("DELETE p FROM pagamentos p JOIN inscricoes i ON i.id = p.inscricao_id WHERE i.codigo LIKE 'TSTPG%'");
    $pdo->exec("DELETE FROM inscricoes WHERE codigo LIKE 'TSTPG%'");
    $pdo->exec("DELETE FROM cupons WHERE codigo = 'TSTPGCUPOM'");
}
/** Cria no falso um pagamento da inscrição (Pix ou cartão com token), muda o status e devolve o que a API responde. */
function pagamento_falso(int $id, string $status, ?string $token = null): array
{
    global $cfg_mp;
    $i = linha($id);
    $r = $token === null ? mp_criar_pix($cfg_mp, $i, date('Y-m-d\TH:i:s.vP', time() + 3600))
                         : mp_criar_cartao($cfg_mp, $i, ['token' => $token, 'installments' => 2, 'payment_method_id' => 'visa']);
    mp_falso_mudar(PORTA_MP, $r['dados']['id'], ['status' => $status]);
    return mp_buscar_pagamento($cfg_mp, (string) $r['dados']['id'])['dados'];
}
function pedidos_get(): int
{
    return count(array_filter(mp_falso_estado(PORTA_MP)['pedidos'] ?? [], fn ($p) => $p['metodo'] === 'GET'));
}
/** Chama um endpoint do site de teste: [código HTTP, corpo]. */
function chamar(string $caminho, string $metodo = 'GET', string $corpo = '', array $cab = []): array
{
    $ctx = stream_context_create(['http' => ['method' => $metodo, 'content' => $corpo, 'ignore_errors' => true, 'timeout' => 30,
        'header' => implode("\r\n", array_merge(['Content-Type: application/json'], $cab)) . "\r\n"]]);
    $resp = (string) @file_get_contents('http://127.0.0.1:' . PORTA_SITE . '/' . $caminho, false, $ctx);
    preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
    return [(int) ($m[1] ?? 0), $resp];
}

// O site de teste usa config/mercadopago.php (apontando para o falso) e config/email.php com SMTP inexistente
// (nenhum e-mail de verdade sai). Os dois originais são guardados e restaurados no fim.
$cfg_mp = ['access_token' => 'TEST-123', 'public_key' => 'TEST-pk', 'webhook_secret' => 'segredo-teste', 'api' => 'http://127.0.0.1:' . PORTA_MP];
$arq_mp = RAIZ . '/config/mercadopago.php';
$arq_em = RAIZ . '/config/email.php';
$orig_mp = is_file($arq_mp) ? file_get_contents($arq_mp) : null;
$orig_em = is_file($arq_em) ? file_get_contents($arq_em) : null;
if ($orig_em !== null) {
    file_put_contents(sys_get_temp_dir() . '/email-config-copia-pagtest.php', $orig_em);
}
file_put_contents($arq_mp, '<?php return ' . var_export($cfg_mp, true) . ";\n");
file_put_contents($arq_em, "<?php return ['host' => '127.0.0.1', 'porta' => 25268, 'seguranca' => '', 'usuario' => 'contato@exemplo.test', 'senha' => 'x'];\n");
$proc_mp   = mp_falso_subir(PORTA_MP);
$proc_site = mp_falso_subir_site(PORTA_SITE, RAIZ);

limpar();
try {
    confere('relógio do banco = relógio do PHP', abs(strtotime((string) $pdo->query('SELECT NOW()')->fetchColumn()) - time()) <= 5, true);

    $pdo->exec("INSERT INTO cupons (nome, codigo, tipo, valor, quantidade) VALUES ('Teste', 'TSTPGCUPOM', 'fixo', 10, 1)");
    $cupom = (int) $pdo->lastInsertId();

    $ocupadas0 = vagas_ocupadas($pdo);
    $a = insc_teste(['cpf' => '90000000001', 'cupom_id' => $cupom], 'NOW() - INTERVAL 1 MINUTE');
    $b = insc_teste(['cpf' => '90000000002'], 'NOW() + INTERVAL 1 HOUR');
    $c = insc_teste(['cpf' => '90000000003']);
    confere('pendentes ocupam vaga antes de expirar', vagas_ocupadas($pdo), $ocupadas0 + 3);
    confere('cupom usado pela pendente', cupom_usos($pdo, $cupom), 1);

    confere('expira uma', inscricoes_expirar($pdo), 1);
    confere('vencida vira expirado', status_de($a), 'expirado');
    confere('dentro do prazo continua pendente', status_de($b), 'pendente');
    confere('sem prazo (antiga/painel) continua pendente', status_de($c), 'pendente');
    confere('expirada libera a vaga', vagas_ocupadas($pdo), $ocupadas0 + 2);
    confere('expirada devolve o uso do cupom', cupom_usos($pdo, $cupom), 0);

    confere('CPF da expirada fica livre', pagamento_cpf_ativo($pdo, '90000000001'), null);
    confere('CPF da pendente está ativo', pagamento_cpf_ativo($pdo, '90000000002')['status'] ?? null, 'pendente');
    confere('ativa de outra inscrição (exceto a própria)', pagamento_cpf_ativo($pdo, '90000000002', $b), null);

    try {
        $a2 = insc_teste(['cpf' => '90000000001'], 'NOW() + INTERVAL 1 HOUR');
        confere('mesmo CPF de uma expirada grava de novo', status_de($a2), 'pendente');
    } catch (PDOException $e) {
        confere('mesmo CPF de uma expirada grava de novo', $e->getMessage(), 'sem erro');
    }

    confere('sem limite de vagas sempre tem vaga', pagamento_tem_vaga($pdo, 0), true);
    confere('limite = ocupadas não tem vaga', pagamento_tem_vaga($pdo, vagas_ocupadas($pdo)), false);
    confere('limite = ocupadas + 1 tem vaga', pagamento_tem_vaga($pdo, vagas_ocupadas($pdo) + 1), true);

    // ---- Processar pagamentos vindos da API ----
    $site = 'http://site.test';
    $p1 = insc_teste(['cpf' => '90000000011'], 'NOW() + INTERVAL 1 HOUR');
    $mp1 = pagamento_falso($p1, 'approved');
    confere('pendente + approved → pago', pagamento_processar($pdo, $mp1, 0, $site, null), 'pago');
    $l = linha($p1);
    confere('status pago', $l['status'], 'pago');
    confere('pago_em preenchido', !empty($l['pago_em']), true);
    confere('pagamento que quitou guardado', $l['pagamento_mp_id'], (string) $mp1['id']);
    confere('pagamento registrado na tabela', (int) $pdo->query('SELECT COUNT(*) FROM pagamentos WHERE mp_id = ' . (int) $mp1['id'])->fetchColumn(), 1);
    confere('tentou o e-mail de confirmação', $l['email_pago_erro'], 'e-mail não configurado');
    $pdo->exec("UPDATE inscricoes SET email_pago_erro = 'MARCA' WHERE id = $p1");
    confere('aviso repetido não reenvia', pagamento_processar($pdo, $mp1, 0, $site, null), 'repetido');
    confere('aviso repetido: pago_em igual', linha($p1)['pago_em'], $l['pago_em']);
    confere('aviso repetido: nenhuma nova tentativa de e-mail', linha($p1)['email_pago_erro'], 'MARCA');

    $p2 = insc_teste(['cpf' => '90000000012'], 'NOW() + INTERVAL 1 HOUR');
    $mp2 = pagamento_falso($p2, 'approved');
    $mp2['transaction_amount'] = 1.0;
    confere('valor divergente não confirma', pagamento_processar($pdo, $mp2, 0, $site, null), 'valor_divergente');
    confere('valor divergente: continua pendente', status_de($p2), 'pendente');

    $p3 = insc_teste(['cpf' => '90000000013'], 'NOW() - INTERVAL 1 MINUTE');
    inscricoes_expirar($pdo);
    confere('expirada com vaga → pago_atrasado', pagamento_processar($pdo, pagamento_falso($p3, 'approved'), 0, $site, null), 'pago_atrasado');
    confere('expirada com vaga: paga', status_de($p3), 'pago');

    $p4 = insc_teste(['cpf' => '90000000014'], 'NOW() - INTERVAL 1 MINUTE');
    inscricoes_expirar($pdo);
    confere('expirada sem vaga → sem_vaga', pagamento_processar($pdo, pagamento_falso($p4, 'approved'), vagas_ocupadas($pdo), $site, null), 'sem_vaga');
    $l = linha($p4);
    confere('sem vaga: fica paga', $l['status'], 'pago');
    confere('sem vaga: alerta devolver', str_contains((string) $l['pagamento_alerta'], 'paga sem vaga — devolver o dinheiro'), true);
    confere('sem vaga: não manda e-mail de confirmação', $l['email_pago_erro'], null);

    $p5 = insc_teste(['cpf' => '90000000015'], 'NOW() - INTERVAL 1 MINUTE');
    inscricoes_expirar($pdo);
    insc_teste(['cpf' => '90000000015'], 'NOW() + INTERVAL 1 HOUR'); // refez a inscrição
    confere('expirada com o CPF ativo noutra → sem_vaga', pagamento_processar($pdo, pagamento_falso($p5, 'approved'), 0, $site, null), 'sem_vaga');

    $mp1b = pagamento_falso($p1, 'approved', 'APRO');
    confere('segundo pagamento numa paga → duplicidade', pagamento_processar($pdo, $mp1b, 0, $site, null), 'duplicidade');
    $l = linha($p1);
    confere('duplicidade: alerta devolver', str_contains((string) $l['pagamento_alerta'], 'pagamento em duplicidade — devolver o dinheiro'), true);
    confere('duplicidade: alerta tem o id', str_contains((string) $l['pagamento_alerta'], (string) $mp1b['id']), true);
    confere('duplicidade: pagamento que quitou não muda', $l['pagamento_mp_id'], (string) $mp1['id']);

    $p6 = insc_teste(['cpf' => '90000000016', 'status' => 'cancelado']);
    confere('cancelada + approved → duplicidade', pagamento_processar($pdo, pagamento_falso($p6, 'approved'), 0, $site, null), 'duplicidade');
    confere('cancelada continua cancelada', status_de($p6), 'cancelado');

    $mp1['status'] = 'refunded';
    confere('estorno do pagamento que quitou', pagamento_processar($pdo, $mp1, 0, $site, null), 'estornado');
    $l = linha($p1);
    confere('estorno: status continua pago', $l['status'], 'pago');
    confere('estorno: alerta', str_contains((string) $l['pagamento_alerta'], 'estornado no Mercado Pago'), true);
    confere('estorno: alerta anterior mantido', str_contains((string) $l['pagamento_alerta'], 'duplicidade'), true);
    pagamento_processar($pdo, $mp1, 0, $site, null);
    confere('mesmo alerta não repete', substr_count((string) linha($p1)['pagamento_alerta'], 'estornado no Mercado Pago'), 1);

    confere('referência desconhecida', pagamento_processar($pdo, ['id' => 1, 'status' => 'approved', 'external_reference' => 'NAOEXISTE1', 'transaction_amount' => 1], 0, $site, null), 'desconhecido');
    $p7 = insc_teste(['cpf' => '90000000017'], 'NOW() + INTERVAL 1 HOUR');
    confere('pendente/recusado só atualiza', pagamento_processar($pdo, pagamento_falso($p7, 'rejected', 'OTHE'), 0, $site, null), 'atualizado');
    confere('recusado: continua pendente', status_de($p7), 'pendente');

    // Consulta da página: Pix aprovado no falso vira pago; segunda consulta logo em seguida não chama a API
    $p8 = insc_teste(['cpf' => '90000000018'], 'NOW() + INTERVAL 1 HOUR');
    $pix8 = mp_criar_pix($cfg_mp, linha($p8), date('Y-m-d\TH:i:s.vP', time() + 3600));
    pagamento_registrar($pdo, $p8, $pix8['dados']);
    mp_falso_mudar(PORTA_MP, $pix8['dados']['id'], ['status' => 'approved']);
    $g0 = pedidos_get();
    pagamento_consultar($pdo, $cfg_mp, linha($p8), 0, $site, null);
    confere('consulta: Pix aprovado vira pago', status_de($p8), 'pago');
    confere('consulta: chamou a API uma vez', pedidos_get(), $g0 + 1);
    $p9 = insc_teste(['cpf' => '90000000019'], 'NOW() + INTERVAL 1 HOUR');
    $pix9 = mp_criar_pix($cfg_mp, linha($p9), date('Y-m-d\TH:i:s.vP', time() + 3600));
    pagamento_registrar($pdo, $p9, $pix9['dados']);
    pagamento_consultar($pdo, $cfg_mp, linha($p9), 0, $site, null);
    $g1 = pedidos_get();
    pagamento_consultar($pdo, $cfg_mp, linha($p9), 0, $site, null);
    confere('consulta seguida não chama a API de novo', pedidos_get(), $g1);

    // ---- Endpoints ----
    $p10 = insc_teste(['cpf' => '90000000020'], 'NOW() + INTERVAL 1 HOUR');
    $pix10 = mp_criar_pix($cfg_mp, linha($p10), date('Y-m-d\TH:i:s.vP', time() + 3600));
    mp_falso_mudar(PORTA_MP, $pix10['dados']['id'], ['status' => 'approved']);
    $id10 = (string) $pix10['dados']['id'];
    $corpo = json_encode(['type' => 'payment', 'action' => 'payment.updated', 'data' => ['id' => $id10]]);
    [$http] = chamar("pagamento_aviso.php?data.id=$id10&type=payment", 'POST', $corpo, ['x-signature: ts=1700000000,v1=' . str_repeat('a', 64), 'x-request-id: req-9']);
    confere('aviso sem assinatura válida → 401', $http, 401);
    confere('aviso sem assinatura válida: continua pendente', status_de($p10), 'pendente');
    $v1 = hash_hmac('sha256', "id:$id10;request-id:req-9;ts:1700000000;", 'segredo-teste');
    [$http] = chamar("pagamento_aviso.php?data.id=$id10&type=payment", 'POST', $corpo, ["x-signature: ts=1700000000,v1=$v1", 'x-request-id: req-9']);
    confere('aviso assinado → 200', $http, 200);
    confere('aviso assinado: inscrição paga', status_de($p10), 'pago');
    [$http] = chamar('pagamento_aviso.php?type=merchant_order&data.id=1', 'POST', '{}');
    confere('aviso de outro tipo → 200 sem fazer nada', $http, 200);

    [$http, $resp] = chamar('pagamento_status.php?c=FFFFFFFFFF');
    confere('status de código inexistente → 404', $http, 404);
    [$http, $resp] = chamar('pagamento_status.php?c=' . linha($p10)['codigo']);
    confere('status de inscrição paga', [$http, json_decode($resp, true)], [200, ['status' => 'pago']]);
} finally {
    limpar();
    mp_falso_parar($proc_site, PORTA_SITE);
    mp_falso_parar($proc_mp, PORTA_MP);
    $orig_mp === null ? @unlink($arq_mp) : file_put_contents($arq_mp, $orig_mp);
    if ($orig_em === null) {
        @unlink($arq_em);
    } else {
        file_put_contents($arq_em, $orig_em);
        @unlink(sys_get_temp_dir() . '/email-config-copia-pagtest.php');
    }
}

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo certo\n";
exit($falhas ? 1 : 0);
