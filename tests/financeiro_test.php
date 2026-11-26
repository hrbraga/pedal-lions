<?php
/**
 * Testes das regras e contas do Financeiro (sem banco).
 * Rode com: php tests/financeiro_test.php   (no XAMPP: C:\xampp\php\php tests\financeiro_test.php)
 */
require __DIR__ . '/../includes/financeiro.php';

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

// Valores digitados → centavos
foreach ([['1.234,56', 123456], ['1234,56', 123456], ['1234.56', 123456], ['10', 1000], ['0,1', 10], ['0,10', 10],
          ['1.234', 123400], ['12.5', 1250], [' 150,00 ', 15000], ['', null], ['-5', null], ['abc', null], ['1,234', null],
          ['0', 0]] as [$texto, $esperado]) {
    confere("centavos de '$texto'", fin_centavos($texto), $esperado);
}
confere('moeda', fin_moeda(123456), 'R$ 1.234,56');
confere('moeda pequena', fin_moeda(5), 'R$ 0,05');
confere('moeda negativa', fin_moeda(-1000), '−R$ 10,00');

// Validação do lançamento
$cats = [
    ['id' => 1, 'tipo' => 'entrada', 'nome' => 'Patrocínios', 'ativa' => 1],
    ['id' => 2, 'tipo' => 'saida', 'nome' => 'Troféus e medalhas', 'ativa' => 1],
    ['id' => 3, 'tipo' => 'saida', 'nome' => 'Antiga', 'ativa' => 0],
];
$ok = ['tipo' => 'saida', 'categoria_id' => '2', 'data' => '2026-12-05', 'descricao' => 'Troféus do pódio', 'valor' => '1.500,00'];
$r = fin_validar_lancamento($ok, $cats);
confere('lançamento válido', $r['erros'], []);
confere('lançamento válido: dados', $r['dados'], ['tipo' => 'saida', 'categoria_id' => 2, 'data' => '2026-12-05', 'descricao' => 'Troféus do pódio', 'valor' => 150000]);
$erro = function (array $troca, string $campo) use ($ok, $cats) {
    return isset(fin_validar_lancamento(array_merge($ok, $troca), $cats)['erros'][$campo]);
};
confere('tipo inválido', $erro(['tipo' => 'x'], 'tipo'), true);
confere('categoria de outro tipo', $erro(['categoria_id' => '1'], 'categoria_id'), true);
confere('categoria inativa', $erro(['categoria_id' => '3'], 'categoria_id'), true);
confere('categoria inexistente', $erro(['categoria_id' => '99'], 'categoria_id'), true);
confere('valor zero', $erro(['valor' => '0'], 'valor'), true);
confere('valor negativo', $erro(['valor' => '-3'], 'valor'), true);
confere('valor enorme', $erro(['valor' => '10.000.000,00'], 'valor'), true);
confere('data inválida', $erro(['data' => '2026-02-30'], 'data'), true);
confere('descrição vazia', $erro(['descricao' => '  '], 'descricao'), true);
confere('descrição longa', $erro(['descricao' => str_repeat('a', 201)], 'descricao'), true);

// Valores automáticos das inscrições (por dia de pagamento)
$insc = [
    ['status' => 'pago', 'pago_em' => '2026-12-05 10:00:00', 'valor_inscricao' => '60.00', 'valor_desconto' => '0.00', 'valor_taxa' => '6.00', 'valor_camiseta' => '130.00'],
    ['status' => 'pago', 'pago_em' => '2026-12-05 15:00:00', 'valor_inscricao' => '70.00', 'valor_desconto' => '10.00', 'valor_taxa' => '7.00', 'valor_camiseta' => '0.00'],
    ['status' => 'cancelado', 'pago_em' => '2026-12-06 09:00:00', 'valor_inscricao' => '60.00', 'valor_desconto' => '0.00', 'valor_taxa' => '6.00', 'valor_camiseta' => '150.00'],
    ['status' => 'pendente', 'pago_em' => null, 'valor_inscricao' => '60.00', 'valor_desconto' => '0.00', 'valor_taxa' => '6.00', 'valor_camiseta' => '130.00'],
    ['status' => 'cancelado', 'pago_em' => null, 'valor_inscricao' => '60.00', 'valor_desconto' => '0.00', 'valor_taxa' => '6.00', 'valor_camiseta' => '0.00'],
];
$auto = fin_automaticos($insc);
confere('automáticos por dia e origem', $auto, [
    ['data' => '2026-12-05', 'tipo' => 'entrada', 'rotulo' => 'Inscrições', 'valor' => 12000, 'qtd' => 2],
    ['data' => '2026-12-05', 'tipo' => 'entrada', 'rotulo' => 'Camisetas', 'valor' => 13000, 'qtd' => 1],
    ['data' => '2026-12-05', 'tipo' => 'entrada', 'rotulo' => 'Taxa de administração', 'valor' => 1300, 'qtd' => 2],
    ['data' => '2026-12-05', 'tipo' => 'saida', 'rotulo' => 'Repasse da taxa de administração', 'valor' => 1300, 'qtd' => 2],
    ['data' => '2026-12-06', 'tipo' => 'entrada', 'rotulo' => 'Inscrições canceladas (sem reembolso)', 'valor' => 21000, 'qtd' => 1],
    ['data' => '2026-12-06', 'tipo' => 'entrada', 'rotulo' => 'Taxa de administração', 'valor' => 600, 'qtd' => 1],
    ['data' => '2026-12-06', 'tipo' => 'saida', 'rotulo' => 'Repasse da taxa de administração', 'valor' => 600, 'qtd' => 1],
]);

// Balancete
$man = [
    ['id' => 1, 'data' => '2026-12-01', 'tipo' => 'entrada', 'categoria' => 'Patrocínios', 'descricao' => 'Royal', 'valor' => 50000, 'criado_por' => 'admin'],
    ['id' => 2, 'data' => '2026-12-10', 'tipo' => 'saida', 'categoria' => 'Troféus e medalhas', 'descricao' => 'Troféus', 'valor' => 30000, 'criado_por' => 'admin'],
];
$b = fin_balancete($man, $auto, '', '');
confere('balancete: entradas por rótulo', $b['entradas'], [
    'Patrocínios' => 50000, 'Inscrições' => 12000, 'Camisetas' => 13000,
    'Inscrições canceladas (sem reembolso)' => 21000, 'Taxa de administração' => 1900,
]);
confere('balancete: saídas por rótulo', $b['saidas'], ['Troféus e medalhas' => 30000, 'Repasse da taxa de administração' => 1900]);
confere('balancete: totais e saldo', [$b['saldo_anterior'], $b['total_entradas'], $b['total_saidas'], $b['saldo_final']], [0, 97900, 31900, 66000]);
confere('balancete: última linha tem o saldo final', end($b['linhas'])['saldo'], 66000);
confere('balancete: linhas em ordem de data', array_column($b['linhas'], 'data'), array_values(array_merge(
    ['2026-12-01'], array_fill(0, 4, '2026-12-05'), array_fill(0, 3, '2026-12-06'), ['2026-12-10'])));

$d = fin_balancete($man, $auto, '2026-12-06', '');
confere('período: saldo anterior', $d['saldo_anterior'], 75000);
confere('período: totais só do período', [$d['total_entradas'], $d['total_saidas']], [21600, 30600]);
confere('período: saldo final igual ao sem filtro', $d['saldo_final'], 66000);
confere('período: primeira linha parte do saldo anterior', $d['linhas'][0]['saldo'], 75000 + 21000);

$a = fin_balancete($man, $auto, '', '2026-12-05');
confere('até: totais', [$a['total_entradas'], $a['total_saidas'], $a['saldo_final']], [76300, 1300, 75000]);

$c = fin_balancete([
    ['id' => 3, 'data' => '2026-12-01', 'tipo' => 'entrada', 'categoria' => 'Doações', 'descricao' => 'a', 'valor' => 10, 'criado_por' => 'x'],
    ['id' => 4, 'data' => '2026-12-01', 'tipo' => 'entrada', 'categoria' => 'Doações', 'descricao' => 'b', 'valor' => 20, 'criado_por' => 'x'],
], [], '', '');
confere('centavos exatos: 0,10 + 0,20', $c['total_entradas'], 30);

// Categoria manual com o nome de um automático: os dois valores somam (nada some dos totais) e o nome é recusado no cadastro
$mesmo = fin_balancete([['id' => 9, 'data' => '2026-12-05', 'tipo' => 'entrada', 'categoria' => 'Inscrições', 'descricao' => 'dinheiro no dia', 'valor' => 5000, 'criado_por' => 'x']],
    [['data' => '2026-12-05', 'tipo' => 'entrada', 'rotulo' => 'Inscrições', 'valor' => 6000, 'qtd' => 1]], '', '');
confere('mesmo nome: entradas somadas', $mesmo['entradas'], ['Inscrições' => 11000]);
confere('mesmo nome: total igual ao saldo da última linha', [$mesmo['total_entradas'], $mesmo['saldo_final']], [11000, end($mesmo['linhas'])['saldo']]);
confere('nome reservado: Inscrições', fin_nome_reservado(' inscrições '), true);
confere('nome reservado: Repasse', fin_nome_reservado('Repasse da taxa de administração'), true);
confere('nome livre', fin_nome_reservado('Inscrições em dinheiro'), false);

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo certo\n";
exit($falhas ? 1 : 0);
