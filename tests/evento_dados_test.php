<?php
/**
 * Testes do armazenamento dos Dados do Evento (mescla, datas e versões no banco).
 * Rode com: php tests/evento_dados_test.php   (no XAMPP: C:\xampp\php\php tests\evento_dados_test.php)
 * O bloco do banco só roda com o MySQL ligado e sem versões de verdade salvas.
 */
require __DIR__ . '/../includes/evento_dados.php';

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

confere('simples substitui', evento_mesclar(['a' => 1, 'b' => 2], ['a' => 9]), ['a' => 9, 'b' => 2]);
confere('associativo mescla', evento_mesclar(['c' => ['x' => 1, 'y' => 2]], ['c' => ['y' => 5]]), ['c' => ['x' => 1, 'y' => 5]]);
confere('lista substitui inteira', evento_mesclar(['l' => [1, 2, 3]], ['l' => [7]]), ['l' => [7]]);
confere('lista vazia substitui', evento_mesclar(['l' => [1]], ['l' => []]), ['l' => []]);
confere('chave nova entra', evento_mesclar(['a' => 1], ['z' => 2]), ['a' => 1, 'z' => 2]);
confere('lista de associativos substitui', evento_mesclar(['l' => [['n' => 1], ['n' => 2]]], ['l' => [['n' => 3]]]), ['l' => [['n' => 3]]]);
confere('eh_lista', [eh_lista([]), eh_lista([1, 2]), eh_lista([1 => 'a']), eh_lista(['a' => 1])], [true, true, false, false]);
confere('datas', evento_datas('2027-03-14'), ['data_curta' => '14/03/2027', 'data_extenso' => 'Domingo, 14 de março de 2027']);
confere('datas sábado', evento_datas('2027-01-02')['data_extenso'], 'Sábado, 2 de janeiro de 2027');
confere('data inválida', evento_datas('x'), ['data_curta' => 'x', 'data_extenso' => 'x']);
confere('evento() calcula as datas', [evento()['data_curta'], evento()['data_extenso']], ['14/03/2027', 'Domingo, 14 de março de 2027']);

try {
    $pdo = db();
    $pdo->query('SELECT 1 FROM evento_versoes LIMIT 1');
} catch (Throwable $e) {
    $pdo = null;
}
if (!$pdo) {
    echo "pulado - banco indisponível\n";
} elseif ((int) $pdo->query("SELECT COUNT(*) FROM evento_versoes WHERE salvo_por <> 'teste'")->fetchColumn() > 0) {
    echo "pulado - já existem versões de verdade salvas\n";
} else {
    $id = evento_salvar($pdo, ['local_largada' => 'TESTE-LOCAL'], 'teste');
    $atual = evento_versao_atual($pdo);
    confere('versão atual é a salva', [$atual['id'], $atual['dados']['local_largada'], $atual['salvo_por']], [$id, 'TESTE-LOCAL', 'teste']);
    confere('evento_versao busca por id', evento_versao($pdo, $id)['dados'], ['local_largada' => 'TESTE-LOCAL']);
    $rid = evento_salvar($pdo, ['local_largada' => 'TESTE-2'], 'teste', $id);
    confere('restauração guarda a origem', (int) evento_versoes($pdo)[0]['restaurada_de'], $id);
    confere('histórico vem do mais novo', (int) evento_versoes($pdo)[0]['id'], $rid);
    for ($i = 0; $i < 51; $i++) {
        evento_salvar($pdo, ['n' => $i], 'teste');
    }
    confere('guarda só as 50 mais recentes', (int) $pdo->query('SELECT COUNT(*) FROM evento_versoes')->fetchColumn(), 50);
    confere('a mais recente sobrevive', evento_versao_atual($pdo)['dados'], ['n' => 50]);
    $eid = evento_salvar($pdo, ['faq' => [['p' => 'Vai ter café? 🚴', 'r' => 'Sim']]], 'teste');
    $cru = (string) $pdo->query('SELECT dados FROM evento_versoes WHERE id = ' . (int) $eid)->fetchColumn();
    confere('JSON gravado só com ASCII (funciona com qualquer charset do banco)', preg_match('/[^\x00-\x7F]/', $cru), 0);
    confere('acento e emoji voltam intactos', evento_versao($pdo, $eid)['dados']['faq'][0]['p'], 'Vai ter café? 🚴');
    $pdo->exec("DELETE FROM evento_versoes WHERE salvo_por = 'teste'");
    confere('sem versões, atual é null', evento_versao_atual($pdo), null);
}

echo $falhas ? "\n$falhas falha(s)\n" : "\ntodos passaram\n";
exit($falhas ? 1 : 0);
