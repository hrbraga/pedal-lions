<?php
/**
 * Filtros compartilhados entre a lista (painel.php) e a exportação (exportar.php).
 * Retorna [cláusula WHERE, parâmetros, valores normalizados dos filtros].
 */
function montar_filtros(array $get): array
{
    $q         = trim((string) ($get['q'] ?? ''));
    $status    = (string) ($get['status'] ?? '');
    $categoria = (string) ($get['categoria'] ?? '');
    $camiseta  = (string) ($get['camiseta'] ?? '');

    if (!isset(STATUS_ROTULOS[$status])) {
        $status = '';
    }
    if ($categoria !== '' && !categoria_por_slug($categoria)) {
        $categoria = '';
    }
    if (!in_array($camiseta, ['sim', 'nao'], true)) {
        $camiseta = '';
    }
    $q = mb_substr($q, 0, 100);

    $onde   = [];
    $params = [];

    if ($q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $partes = [
            'nome LIKE :q1',
            'equipe LIKE :q2',
            'email LIKE :q3',
            'codigo LIKE :q4',
        ];
        $params[':q1'] = $like;
        $params[':q2'] = $like;
        $params[':q3'] = $like;
        $params[':q4'] = $like;

        $digitos = so_digitos($q);
        if ($digitos !== '') {
            $partes[]      = 'cpf LIKE :q5';
            $params[':q5'] = '%' . $digitos . '%';
        }
        $onde[] = '(' . implode(' OR ', $partes) . ')';
    }
    if ($status !== '') {
        $onde[]            = 'status = :status';
        $params[':status'] = $status;
    }
    if ($categoria !== '') {
        $onde[]               = 'categoria = :categoria';
        $params[':categoria'] = $categoria;
    }
    if ($camiseta === 'sim') {
        $onde[] = 'camiseta_tamanho IS NOT NULL';
    } elseif ($camiseta === 'nao') {
        $onde[] = 'camiseta_tamanho IS NULL';
    }

    $sql = $onde ? 'WHERE ' . implode(' AND ', $onde) : '';
    return [$sql, $params, ['q' => $q, 'status' => $status, 'categoria' => $categoria, 'camiseta' => $camiseta]];
}
