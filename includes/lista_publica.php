<?php
/**
 * Lista pública de inscritos: grupos por categoria e sexo, só inscrições pagas, com nome, cidade e equipe.
 * Só aparece no site quando liberada em Dados do Evento (lista_inscritos_publica).
 */

const LISTA_SEXOS = ['M' => 'Masc', 'F' => 'Fem'];

/** Texto para comparar e buscar: minúsculas e sem acento ("Álvaro LIMA" → "alvaro lima"). */
function lista_texto_busca(string $texto): string
{
    $t = mb_strtolower(trim($texto));
    return strtr($t, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
    ]);
}

/**
 * Grupos na ordem das categorias do evento, cada uma em Masc e Fem.
 * $inscritos: linhas com nome, categoria, sexo, status, cidade, equipe. Só as pagas entram.
 * Cada grupo: ['id' => 'light-m', 'titulo' => 'Light Masc', 'qtd' => n, 'pessoas' => [['nome','cidade','equipe'], ...]].
 */
function lista_grupos(array $categorias, array $inscritos): array
{
    $grupos = [];
    foreach ($categorias as $c) {
        foreach (LISTA_SEXOS as $sexo => $rotulo) {
            $grupos[$c['slug'] . '|' . $sexo] = [
                'id' => $c['slug'] . '-' . strtolower($sexo), 'titulo' => $c['nome'] . ' ' . $rotulo, 'qtd' => 0, 'pessoas' => [],
            ];
        }
    }
    foreach ($inscritos as $i) {
        $chave = ($i['categoria'] ?? '') . '|' . ($i['sexo'] ?? '');
        if (($i['status'] ?? '') !== 'pago' || !isset($grupos[$chave])) {
            continue;
        }
        $grupos[$chave]['pessoas'][] = [
            'nome' => (string) $i['nome'], 'cidade' => (string) ($i['cidade'] ?? ''), 'equipe' => (string) ($i['equipe'] ?? ''),
        ];
    }
    foreach ($grupos as &$g) {
        usort($g['pessoas'], function ($a, $b) {
            return strcmp(lista_texto_busca($a['nome']), lista_texto_busca($b['nome']));
        });
        $g['qtd'] = count($g['pessoas']);
    }
    unset($g);
    return array_values($grupos);
}

/** Inscrições pagas, só com os campos que a lista pública pode mostrar (mais categoria e sexo para agrupar). */
function lista_inscritos_pagos(PDO $pdo): array
{
    return $pdo->query("SELECT nome, cidade, equipe, categoria, sexo, status FROM inscricoes WHERE status = 'pago'")->fetchAll();
}
