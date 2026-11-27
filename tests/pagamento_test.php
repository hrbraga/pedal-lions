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
function limpar(): void
{
    global $pdo;
    $pdo->exec("DELETE FROM inscricoes WHERE codigo LIKE 'TSTPG%'");
    $pdo->exec("DELETE FROM cupons WHERE codigo = 'TSTPGCUPOM'");
}

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
} finally {
    limpar();
}

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo certo\n";
exit($falhas ? 1 : 0);
