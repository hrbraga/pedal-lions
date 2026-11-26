<?php
/**
 * Dashboard do painel: contas sobre as inscrições (sem banco, testadas em tests/dashboard_test.php).
 * Contam pagas e pendentes, sempre separadas; canceladas ficam de fora.
 * Cada linha de gráfico tem o formato ['rotulo' => ..., 'pagas' => n, 'pendentes' => n].
 */
require_once __DIR__ . '/lista_publica.php'; // lista_texto_busca(): junta "Cornélio" e "cornelio"

const DASH_FAIXAS = ['Até 17' => [0, 17], '18–29' => [18, 29], '30–39' => [30, 39], '40–49' => [40, 49], '50–59' => [50, 59], '60+' => [60, 200]];
const DASH_TOP = 10;

/** Idade completa no dia do evento. */
function dash_idade(string $nascimento, string $data_evento): int
{
    $n = new DateTime($nascimento);
    return (int) $n->diff(new DateTime($data_evento))->y;
}

function dash_faixa(int $idade): string
{
    foreach (DASH_FAIXAS as $rotulo => [$min, $max]) {
        if ($idade >= $min && $idade <= $max) {
            return $rotulo;
        }
    }
    return '60+';
}

/**
 * Resumo para o dashboard.
 * $inscricoes: linhas com status, categoria, sexo, nascimento, cidade, equipe, camiseta_modelo, camiseta_tamanho, criado_em.
 * $vagas = 0 → sem limite (vagas_restantes null).
 */
function dash_resumo(array $inscricoes, array $categorias, array $modelos, array $tamanhos, string $data_evento, int $vagas): array
{
    $vazia = function (string $rotulo): array {
        return ['rotulo' => $rotulo, 'pagas' => 0, 'pendentes' => 0];
    };
    $somar = function (array &$grupo, string $chave, string $rotulo, string $col) use ($vazia) {
        if (!isset($grupo[$chave])) {
            $grupo[$chave] = $vazia($rotulo);
        }
        $grupo[$chave][$col]++;
    };

    $cat = [];
    foreach ($categorias as $c) {
        $cat[$c['slug']] = $vazia($c['nome']);
    }
    $genero = ['M' => $vazia('Masculino'), 'F' => $vazia('Feminino')];
    $idade = [];
    foreach (array_keys(DASH_FAIXAS) as $f) {
        $idade[$f] = $vazia($f);
    }
    $cam = [];
    foreach ($modelos as $m) {
        foreach ($tamanhos as $t) {
            $cam[$m['slug'] . '|' . $t] = $vazia($m['nome'] . ' – ' . $t);
        }
    }
    $cidades = $equipes = $dias = [];
    $pagas = $pendentes = $cam_pagas = $cam_pend = $sem_equipe = 0;

    foreach ($inscricoes as $i) {
        $status = $i['status'] ?? '';
        if ($status !== 'pago' && $status !== 'pendente') {
            continue;
        }
        $col = $status === 'pago' ? 'pagas' : 'pendentes';
        $status === 'pago' ? $pagas++ : $pendentes++;

        if (isset($cat[$i['categoria'] ?? ''])) {
            $cat[$i['categoria']][$col]++;
        }
        if (isset($genero[$i['sexo'] ?? ''])) {
            $genero[$i['sexo']][$col]++;
        }
        if (!empty($i['nascimento'])) {
            $idade[dash_faixa(dash_idade($i['nascimento'], $data_evento))][$col]++;
        }
        if (!empty($i['camiseta_tamanho'])) {
            $status === 'pago' ? $cam_pagas++ : $cam_pend++;
            $chave = ($i['camiseta_modelo'] ?? '') . '|' . $i['camiseta_tamanho'];
            if (isset($cam[$chave])) {
                $cam[$chave][$col]++;
            } else { // modelo removido ou inscrição de antes dos modelos
                $somar($cam, $chave, trim(($i['camiseta_modelo'] ?: 'Camiseta') . ' – ' . $i['camiseta_tamanho']), $col);
            }
        }
        $cidade = trim((string) ($i['cidade'] ?? ''));
        if ($cidade !== '') {
            $somar($cidades, lista_texto_busca($cidade), $cidade, $col);
        }
        $equipe = trim((string) ($i['equipe'] ?? ''));
        if ($equipe === '') {
            $sem_equipe++;
        } else {
            $somar($equipes, lista_texto_busca($equipe), $equipe, $col);
        }
        $dia = substr((string) ($i['criado_em'] ?? ''), 0, 10);
        if ($dia !== '') {
            $dias[$dia] = ($dias[$dia] ?? 0) + 1;
        }
    }

    // Mais inscritos primeiro; empate em ordem alfabética
    $ordenar = function (array $linhas): array {
        usort($linhas, function ($a, $b) {
            $ta = $a['pagas'] + $a['pendentes'];
            $tb = $b['pagas'] + $b['pendentes'];
            return $tb <=> $ta ?: strcmp(lista_texto_busca($a['rotulo']), lista_texto_busca($b['rotulo']));
        });
        return $linhas;
    };
    $cidades = $ordenar(array_values($cidades));
    if (count($cidades) > DASH_TOP) {
        $outras = $vazia('Outras');
        foreach (array_slice($cidades, DASH_TOP) as $l) {
            $outras['pagas'] += $l['pagas'];
            $outras['pendentes'] += $l['pendentes'];
        }
        $cidades = array_merge(array_slice($cidades, 0, DASH_TOP), [$outras]);
    }
    ksort($dias);

    return [
        'pagas'            => $pagas,
        'pendentes'        => $pendentes,
        'total'            => $pagas + $pendentes,
        'vagas_restantes'  => $vagas > 0 ? max(0, $vagas - $pagas - $pendentes) : null,
        'camisetas'        => ['pagas' => $cam_pagas, 'pendentes' => $cam_pend],
        'categorias'       => array_values($cat),
        'genero'           => array_values($genero),
        'idade'            => array_values($idade),
        'camisetas_modelo' => array_values(array_filter($cam, function ($l) {
            return $l['pagas'] + $l['pendentes'] > 0;
        })),
        'cidades'          => $cidades,
        'equipes'          => array_slice($ordenar(array_values($equipes)), 0, DASH_TOP),
        'sem_equipe'       => $sem_equipe,
        'por_dia'          => array_map(function ($d, $q) {
            return ['data' => $d, 'qtd' => $q];
        }, array_keys($dias), array_values($dias)),
    ];
}

/** Inscrições com os campos que o dashboard usa (nada de CPF, contato ou saúde). */
function dash_inscricoes(PDO $pdo): array
{
    return $pdo->query("SELECT status, categoria, sexo, nascimento, cidade, equipe, camiseta_modelo, camiseta_tamanho, criado_em
                        FROM inscricoes WHERE status IN ('pago', 'pendente')")->fetchAll();
}
