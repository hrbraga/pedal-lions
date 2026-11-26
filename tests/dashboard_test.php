<?php
/**
 * Testes das contas do Dashboard (sem banco).
 * Rode com: php tests/dashboard_test.php   (no XAMPP: C:\xampp\php\php tests\dashboard_test.php)
 */
require __DIR__ . '/../includes/dashboard.php';

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

confere('idade no dia do evento (já fez aniversário)', dash_idade('1990-03-10', '2027-03-14'), 37);
confere('idade no dia do evento (ainda não fez)', dash_idade('1990-03-20', '2027-03-14'), 36);
foreach ([[10, 'Até 17'], [17, 'Até 17'], [18, '18–29'], [29, '18–29'], [30, '30–39'], [45, '40–49'], [59, '50–59'], [60, '60+'], [80, '60+']] as [$i, $f]) {
    confere("faixa de $i anos", dash_faixa($i), $f);
}

$cats = [['slug' => 'light', 'nome' => 'Light'], ['slug' => 'hard', 'nome' => 'Hard']];
$modelos = [['slug' => 'curta', 'nome' => 'Manga curta', 'valor' => 130.0], ['slug' => 'longa', 'nome' => 'Manga longa', 'valor' => 150.0]];
$tamanhos = ['P', 'M', 'G'];
$i = function ($status, $cat, $sexo, $nasc, $cidade, $equipe, $modelo, $tam, $criado) {
    return ['status' => $status, 'categoria' => $cat, 'sexo' => $sexo, 'nascimento' => $nasc, 'cidade' => $cidade, 'equipe' => $equipe,
            'camiseta_modelo' => $modelo, 'camiseta_tamanho' => $tam, 'criado_em' => $criado];
};
$insc = [
    $i('pago', 'light', 'M', '1990-01-01', 'Cornélio Procópio', 'Pedal Forte', 'curta', 'M', '2026-12-01 12:05:00'),
    $i('pago', 'light', 'F', '2010-01-01', 'cornelio procopio ', 'pedal forte', null, null, '2026-12-01 13:00:00'),
    $i('pendente', 'hard', 'M', '1960-06-01', 'Bandeirantes', '', 'longa', 'G', '2026-12-02 09:00:00'),
    $i('pendente', 'light', 'M', '1985-01-01', 'Londrina', 'Trilheiros', 'longa', 'G', '2026-12-02 10:00:00'),
    $i('cancelado', 'hard', 'F', '1990-01-01', 'Londrina', 'X', 'curta', 'P', '2026-12-02 11:00:00'),
];
$r = dash_resumo($insc, $cats, $modelos, $tamanhos, '2027-03-14', 300);

confere('totais (canceladas fora)', [$r['pagas'], $r['pendentes'], $r['total']], [2, 2, 4]);
confere('vagas restantes', $r['vagas_restantes'], 296);
confere('sem limite de vagas', dash_resumo($insc, $cats, $modelos, $tamanhos, '2027-03-14', 0)['vagas_restantes'], null);
confere('camisetas', $r['camisetas'], ['pagas' => 1, 'pendentes' => 2]);
confere('por categoria, na ordem do evento', $r['categorias'], [
    ['rotulo' => 'Light', 'pagas' => 2, 'pendentes' => 1], ['rotulo' => 'Hard', 'pagas' => 0, 'pendentes' => 1]]);
confere('por gênero', $r['genero'], [
    ['rotulo' => 'Masculino', 'pagas' => 1, 'pendentes' => 2], ['rotulo' => 'Feminino', 'pagas' => 1, 'pendentes' => 0]]);
confere('por idade (todas as faixas, na ordem)', array_map(function ($l) { return $l['rotulo'] . ':' . $l['pagas'] . '/' . $l['pendentes']; }, $r['idade']),
    ['Até 17:1/0', '18–29:0/0', '30–39:1/0', '40–49:0/1', '50–59:0/0', '60+:0/1']);
confere('camisetas por modelo e tamanho (só as que têm)', $r['camisetas_modelo'], [
    ['rotulo' => 'Manga curta – M', 'pagas' => 1, 'pendentes' => 0], ['rotulo' => 'Manga longa – G', 'pagas' => 0, 'pendentes' => 2]]);
confere('cidades juntam grafias, mais inscritos primeiro', $r['cidades'], [
    ['rotulo' => 'Cornélio Procópio', 'pagas' => 2, 'pendentes' => 0],
    ['rotulo' => 'Bandeirantes', 'pagas' => 0, 'pendentes' => 1],
    ['rotulo' => 'Londrina', 'pagas' => 0, 'pendentes' => 1]]);
confere('equipes e sem equipe', [$r['equipes'], $r['sem_equipe']], [[
    ['rotulo' => 'Pedal Forte', 'pagas' => 2, 'pendentes' => 0], ['rotulo' => 'Trilheiros', 'pagas' => 0, 'pendentes' => 1]], 1]);
confere('por dia', $r['por_dia'], [['data' => '2026-12-01', 'qtd' => 2], ['data' => '2026-12-02', 'qtd' => 2]]);

// Mais de 10 cidades: as 10 maiores e "Outras"
$muitas = [];
for ($k = 1; $k <= 12; $k++) {
    $muitas[] = $i('pago', 'light', 'M', '1990-01-01', "Cidade $k", '', null, null, '2026-12-01 10:00:00');
}
$muitas[] = $i('pago', 'light', 'M', '1990-01-01', 'Cidade 5', '', null, null, '2026-12-01 10:00:00');
$c = dash_resumo($muitas, $cats, $modelos, $tamanhos, '2027-03-14', 0)['cidades'];
confere('top 10 + Outras', [count($c), $c[0]['rotulo'], $c[0]['pagas'], end($c)['rotulo'], end($c)['pagas']], [11, 'Cidade 5', 2, 'Outras', 2]);

echo $falhas ? "\n$falhas falha(s)\n" : "\nTudo certo\n";
exit($falhas ? 1 : 0);
