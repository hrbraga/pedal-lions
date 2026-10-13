<?php
/**
 * Conexão PDO com o MySQL (criada uma única vez por requisição).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = require __DIR__ . '/../config/banco.php';
        $dsn = 'mysql:host=' . $c['host'] . ';dbname=' . $c['nome'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $c['usuario'], $c['senha'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function banco_debug(): bool
{
    $c = require __DIR__ . '/../config/banco.php';
    return !empty($c['debug']);
}
