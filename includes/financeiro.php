<?php
/**
 * Financeiro do evento: lançamentos manuais, valores automáticos das inscrições e balancete.
 * As contas são feitas em CENTAVOS (int), para os totais serem exatos. As funções de cálculo recebem arrays
 * (testadas em tests/financeiro_test.php); as que começam com fin_ e recebem PDO leem o banco.
 */

const FIN_TIPOS = ['entrada' => 'Entrada', 'saida' => 'Saída'];
const FIN_VALOR_MAX = 999999999; // R$ 9.999.999,99

/** Rótulos dos valores automáticos (vindos das inscrições), na ordem em que aparecem no balancete. */
const FIN_AUTO = [
    'Inscrições'                            => 'entrada',
    'Camisetas'                             => 'entrada',
    'Inscrições canceladas (sem reembolso)' => 'entrada',
    'Taxa de administração'                 => 'entrada',
    'Repasse da taxa de administração'      => 'saida',
];

const FIN_CATEGORIAS_INICIAIS = [
    'entrada' => ['Patrocínios', 'Doações', 'Outras entradas'],
    'saida'   => ['Troféus e medalhas', 'Camisetas (fornecedor)', 'Kits e brindes', 'Alimentação e hidratação',
                  'Estrutura e som', 'Divulgação', 'Seguro atleta', 'Tarifas (Asaas/banco)', 'Reembolsos', 'Outras saídas'],
];

/**
 * Valor digitado → centavos. Aceita "1.234,56", "1234,56", "1234.56", "1.234" (milhar) e "12.5".
 * Vazio, negativo, letras ou mais de 2 casas → null.
 */
function fin_centavos(string $texto): ?int
{
    $t = trim($texto);
    if ($t === '' || !preg_match('/^[\d.,]+$/', $t)) {
        return null;
    }
    if (strpos($t, ',') !== false) {
        if (!preg_match('/^(\d{1,3}(\.\d{3})+|\d+),(\d{1,2})$/', $t, $m)) {
            return null;
        }
        $inteiro = str_replace('.', '', $m[1]);
        $dec = $m[3];
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $t)) {
        $inteiro = str_replace('.', '', $t);
        $dec = '';
    } elseif (preg_match('/^(\d+)(\.(\d{1,2}))?$/', $t, $m)) {
        $inteiro = $m[1];
        $dec = $m[3] ?? '';
    } else {
        return null;
    }
    if (strlen(ltrim($inteiro, '0')) > 12) {
        return null;
    }
    return (int) $inteiro * 100 + (int) str_pad($dec, 2, '0');
}

/** Centavos → "R$ 1.234,56" (negativo: "−R$ 10,00"). */
function fin_moeda(int $centavos): string
{
    return ($centavos < 0 ? '−' : '') . 'R$ ' . number_format(abs($centavos) / 100, 2, ',', '.');
}

/** Valor do banco ("60.00") → centavos. */
function fin_de_decimal($valor): int
{
    return (int) round(((float) $valor) * 100);
}

/**
 * Confere um lançamento enviado pelo formulário.
 * $categorias: linhas com id, tipo, nome, ativa. Devolve ['dados' => [...], 'erros' => [campo => mensagem]].
 */
function fin_validar_lancamento(array $e, array $categorias): array
{
    $erros = [];
    $tipo = trim((string) ($e['tipo'] ?? ''));
    if (!isset(FIN_TIPOS[$tipo])) {
        $erros['tipo'] = 'Escolha Entrada ou Saída.';
    }
    $cat_id = (int) ($e['categoria_id'] ?? 0);
    $cat = null;
    foreach ($categorias as $c) {
        if ((int) $c['id'] === $cat_id) {
            $cat = $c;
        }
    }
    if (!$cat || !(int) $cat['ativa'] || $cat['tipo'] !== $tipo) {
        $erros['categoria_id'] = 'Escolha uma categoria ativa de ' . ($tipo === 'saida' ? 'saída' : 'entrada') . '.';
    }
    $data = trim((string) ($e['data'] ?? ''));
    $d = DateTime::createFromFormat('!Y-m-d', $data);
    if (!$d || $d->format('Y-m-d') !== $data) {
        $erros['data'] = 'Informe uma data válida.';
    }
    $descricao = trim((string) ($e['descricao'] ?? ''));
    if ($descricao === '') {
        $erros['descricao'] = 'Escreva uma descrição.';
    } elseif (mb_strlen($descricao) > 200) {
        $erros['descricao'] = 'A descrição pode ter até 200 caracteres.';
    }
    $valor = fin_centavos((string) ($e['valor'] ?? ''));
    if ($valor === null || $valor <= 0 || $valor > FIN_VALOR_MAX) {
        $erros['valor'] = 'Informe um valor maior que zero (ex.: 150,00).';
    }
    return [
        'dados' => ['tipo' => $tipo, 'categoria_id' => $cat_id, 'data' => $data, 'descricao' => $descricao, 'valor' => (int) $valor],
        'erros' => $erros,
    ];
}

/**
 * Valores automáticos das inscrições, agrupados por dia de pagamento e origem.
 * $inscricoes: linhas com status, pago_em, valor_inscricao, valor_desconto, valor_taxa, valor_camiseta.
 * Devolve linhas ['data', 'tipo', 'rotulo', 'valor' (centavos), 'qtd'], por data e na ordem de FIN_AUTO.
 */
function fin_automaticos(array $inscricoes): array
{
    $dias = [];
    $somar = function (string $data, string $rotulo, int $valor) use (&$dias) {
        if ($valor <= 0) {
            return;
        }
        $dias[$data][$rotulo]['valor'] = ($dias[$data][$rotulo]['valor'] ?? 0) + $valor;
        $dias[$data][$rotulo]['qtd'] = ($dias[$data][$rotulo]['qtd'] ?? 0) + 1;
    };
    foreach ($inscricoes as $i) {
        if (empty($i['pago_em']) || !in_array($i['status'] ?? '', ['pago', 'cancelado'], true)) {
            continue;
        }
        $data = substr((string) $i['pago_em'], 0, 10);
        $inscricao = fin_de_decimal($i['valor_inscricao'] ?? 0) - fin_de_decimal($i['valor_desconto'] ?? 0);
        $camiseta = fin_de_decimal($i['valor_camiseta'] ?? 0);
        $taxa = fin_de_decimal($i['valor_taxa'] ?? 0);
        if ($i['status'] === 'pago') {
            $somar($data, 'Inscrições', $inscricao);
            $somar($data, 'Camisetas', $camiseta);
        } else {
            $somar($data, 'Inscrições canceladas (sem reembolso)', $inscricao + $camiseta);
        }
        $somar($data, 'Taxa de administração', $taxa);
        $somar($data, 'Repasse da taxa de administração', $taxa);
    }
    ksort($dias);
    $linhas = [];
    foreach ($dias as $data => $porRotulo) {
        foreach (FIN_AUTO as $rotulo => $tipo) {
            if (isset($porRotulo[$rotulo])) {
                $linhas[] = ['data' => $data, 'tipo' => $tipo, 'rotulo' => $rotulo,
                             'valor' => $porRotulo[$rotulo]['valor'], 'qtd' => $porRotulo[$rotulo]['qtd']];
            }
        }
    }
    return $linhas;
}

/**
 * Balancete de um período ($de/$ate 'AAAA-MM-DD'; vazio = sem limite).
 * $manuais: linhas com id, data, tipo, categoria, descricao, valor (centavos), criado_por.
 * Entradas e saídas por rótulo: categorias manuais em ordem alfabética, depois as automáticas na ordem de FIN_AUTO.
 * As linhas (manuais e automáticas, por data) trazem o saldo acumulado, que parte do saldo anterior ao período.
 */
function fin_balancete(array $manuais, array $automaticos, string $de, string $ate): array
{
    $todas = [];
    foreach ($automaticos as $a) {
        $todas[] = ['data' => $a['data'], 'tipo' => $a['tipo'], 'rotulo' => $a['rotulo'], 'automatico' => true,
                    'descricao' => $a['qtd'] . ($a['qtd'] > 1 ? ' inscrições' : ' inscrição'), 'valor' => (int) $a['valor'],
                    'id' => null, 'criado_por' => ''];
    }
    foreach ($manuais as $m) {
        $todas[] = ['data' => $m['data'], 'tipo' => $m['tipo'], 'rotulo' => $m['categoria'], 'automatico' => false,
                    'descricao' => $m['descricao'], 'valor' => (int) $m['valor'], 'id' => $m['id'], 'criado_por' => $m['criado_por'] ?? ''];
    }
    usort($todas, function ($x, $y) {
        return strcmp($x['data'], $y['data']); // estável: no mesmo dia, automáticos antes dos manuais
    });

    $anterior = 0;
    // [manual|auto][entrada|saida][rótulo] => centavos
    $grupos = ['manual' => ['entrada' => [], 'saida' => []], 'auto' => ['entrada' => [], 'saida' => []]];
    $linhas = [];
    $saldo = null;
    foreach ($todas as $l) {
        $sinal = $l['tipo'] === 'saida' ? -1 : 1;
        if ($de !== '' && $l['data'] < $de) {
            $anterior += $sinal * $l['valor'];
            continue;
        }
        if ($ate !== '' && $l['data'] > $ate) {
            continue;
        }
        if ($saldo === null) {
            $saldo = $anterior;
        }
        $saldo += $sinal * $l['valor'];
        $l['saldo'] = $saldo;
        $linhas[] = $l;
        $g = $l['automatico'] ? 'auto' : 'manual';
        $grupos[$g][$l['tipo']][$l['rotulo']] = ($grupos[$g][$l['tipo']][$l['rotulo']] ?? 0) + $l['valor'];
    }
    $ordenar_auto = function (array $porRotulo): array {
        $saida = [];
        foreach (array_keys(FIN_AUTO) as $r) {
            if (isset($porRotulo[$r])) {
                $saida[$r] = $porRotulo[$r];
            }
        }
        return $saida;
    };
    ksort($grupos['manual']['entrada'], SORT_STRING | SORT_FLAG_CASE);
    ksort($grupos['manual']['saida'], SORT_STRING | SORT_FLAG_CASE);
    // Soma (não sobrescreve): uma categoria manual com o mesmo nome de um automático não pode esconder valores
    $juntar = function (array $manual, array $auto): array {
        foreach ($auto as $rotulo => $c) {
            $manual[$rotulo] = ($manual[$rotulo] ?? 0) + $c;
        }
        return $manual;
    };
    $entradas = $juntar($grupos['manual']['entrada'], $ordenar_auto($grupos['auto']['entrada']));
    $saidas = $juntar($grupos['manual']['saida'], $ordenar_auto($grupos['auto']['saida']));
    $total_e = array_sum($entradas);
    $total_s = array_sum($saidas);
    return [
        'saldo_anterior' => $anterior,
        'entradas'       => $entradas,
        'saidas'         => $saidas,
        'total_entradas' => $total_e,
        'total_saidas'   => $total_s,
        'saldo_final'    => $anterior + $total_e - $total_s,
        'linhas'         => $linhas,
    ];
}

/** Nome que não pode ser usado em categoria manual (é de um valor automático), sem diferenciar maiúsculas. */
function fin_nome_reservado(string $nome): bool
{
    $n = mb_strtolower(trim($nome));
    foreach (array_keys(FIN_AUTO) as $rotulo) {
        if (mb_strtolower($rotulo) === $n) {
            return true;
        }
    }
    return false;
}

/* ---------------------------------------------------------------------------
   Banco
   --------------------------------------------------------------------------- */

/** Cria as categorias iniciais se ainda não houver nenhuma. */
function fin_garantir(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM fin_categorias')->fetchColumn() > 0) {
        return;
    }
    $st = $pdo->prepare('INSERT INTO fin_categorias (tipo, nome) VALUES (:t, :n)');
    foreach (FIN_CATEGORIAS_INICIAIS as $tipo => $nomes) {
        foreach ($nomes as $nome) {
            $st->execute([':t' => $tipo, ':n' => $nome]);
        }
    }
}

/** Categorias com a quantidade de lançamentos de cada uma. */
function fin_categorias(PDO $pdo): array
{
    return $pdo->query('SELECT c.id, c.tipo, c.nome, c.ativa, COUNT(l.id) AS usos
                        FROM fin_categorias c LEFT JOIN fin_lancamentos l ON l.categoria_id = c.id
                        GROUP BY c.id, c.tipo, c.nome, c.ativa ORDER BY c.tipo, c.nome')->fetchAll();
}

/** Lançamentos manuais (valor em centavos), no formato de fin_balancete. */
function fin_lancamentos(PDO $pdo): array
{
    $linhas = [];
    foreach ($pdo->query('SELECT l.id, l.data, l.tipo, l.descricao, l.valor, l.categoria_id, l.criado_por, l.alterado_por, c.nome AS categoria
                          FROM fin_lancamentos l JOIN fin_categorias c ON c.id = l.categoria_id
                          ORDER BY l.data, l.id') as $l) {
        $l['valor'] = fin_de_decimal($l['valor']);
        $linhas[] = $l;
    }
    return $linhas;
}

/** Inscrições com pagamento confirmado (pagas ou pagas e depois canceladas), para fin_automaticos. */
function fin_inscricoes_com_pagamento(PDO $pdo): array
{
    return $pdo->query('SELECT status, pago_em, valor_inscricao, valor_desconto, valor_taxa, valor_camiseta
                        FROM inscricoes WHERE pago_em IS NOT NULL')->fetchAll();
}
