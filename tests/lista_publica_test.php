<?php
/**
 * Testes do agrupamento da lista pública de inscritos (sem banco).
 * Rode com: php tests/lista_publica_test.php   (no XAMPP: C:\xampp\php\php tests\lista_publica_test.php)
 */
require __DIR__ . '/../includes/lista_publica.php';

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

$categorias = [
    ['slug' => 'light', 'nome' => 'Light'],
    ['slug' => 'hard', 'nome' => 'Hard'],
];
$p = function ($nome, $cat, $sexo, $status = 'pago', $cidade = 'Cornélio Procópio', $equipe = '') {
    return ['nome' => $nome, 'categoria' => $cat, 'sexo' => $sexo, 'status' => $status, 'cidade' => $cidade, 'equipe' => $equipe];
};
$inscritos = [
    $p('Zé Carlos', 'light', 'M'),
    $p('Ana Souza', 'light', 'F', 'pago', 'Bandeirantes', 'Pedal Forte'),
    $p('Álvaro Lima', 'light', 'M'),
    $p('bruno dias', 'light', 'M'),
    $p('Pendente Silva', 'light', 'M', 'pendente'),
    $p('Cancelada Souza', 'hard', 'F', 'cancelado'),
    $p('Outro Evento', 'kids', 'M'),
];
$g = lista_grupos($categorias, $inscritos);

confere('quatro grupos, na ordem das categorias, Masc antes de Fem', array_column($g, 'titulo'), ['Light Masc', 'Light Fem', 'Hard Masc', 'Hard Fem']);
confere('quantidades (só pagas, categoria desconhecida fora)', array_column($g, 'qtd'), [3, 1, 0, 0]);
confere('ordem alfabética sem ligar para acento e maiúscula', array_column($g[0]['pessoas'], 'nome'), ['Álvaro Lima', 'bruno dias', 'Zé Carlos']);
confere('só nome, cidade e equipe', $g[1]['pessoas'][0], ['nome' => 'Ana Souza', 'cidade' => 'Bandeirantes', 'equipe' => 'Pedal Forte']);
confere('identificador do grupo', [$g[0]['id'], $g[3]['id']], ['light-m', 'hard-f']);
confere('texto para a busca (sem acento, minúsculo)', lista_texto_busca('Álvaro LIMA'), 'alvaro lima');

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo certo\n";
exit($falhas ? 1 : 0);
