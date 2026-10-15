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
        esquema_atualizar($pdo);
    }
    return $pdo;
}

/**
 * Garante as tabelas/colunas dos cupons (idempotente: só altera se ainda não existir).
 * Assim, bancos criados antes dessa funcionalidade são atualizados sozinhos.
 */
function esquema_atualizar(PDO $pdo): void
{
    try {
        if (!$pdo->query("SHOW TABLES LIKE 'inscricoes'")->fetch()) {
            return; // schema.sql ainda não foi importado
        }

        if (!$pdo->query("SHOW TABLES LIKE 'cupons'")->fetch()) {
            $pdo->exec("CREATE TABLE cupons (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                codigo VARCHAR(30) NOT NULL UNIQUE,
                tipo ENUM('percentual','fixo') NOT NULL,
                valor DECIMAL(8,2) NOT NULL,
                quantidade INT UNSIGNED NOT NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
        }

        if (!$pdo->query("SHOW TABLES LIKE 'cupom_tentativas'")->fetch()) {
            $pdo->exec("CREATE TABLE cupom_tentativas (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip (ip, criado_em)
            ) ENGINE=InnoDB");
        }

        if (!$pdo->query("SHOW COLUMNS FROM inscricoes LIKE 'cupom_id'")->fetch()) {
            $pdo->exec("ALTER TABLE inscricoes
                ADD COLUMN valor_desconto DECIMAL(8,2) NOT NULL DEFAULT 0,
                ADD COLUMN cupom_id INT UNSIGNED NULL,
                ADD COLUMN cupom_codigo VARCHAR(30) NULL,
                ADD INDEX idx_cupom (cupom_id)");
        }
        if (!$pdo->query("SHOW COLUMNS FROM inscricoes LIKE 'valor_taxa'")->fetch()) {
            $pdo->exec("ALTER TABLE inscricoes ADD COLUMN valor_taxa DECIMAL(8,2) NOT NULL DEFAULT 0");
        }
    } catch (PDOException $e) {
        error_log('Pedal Solidário – atualização do esquema: ' . $e->getMessage());
    }
}

function banco_debug(): bool
{
    $c = require __DIR__ . '/../config/banco.php';
    return !empty($c['debug']);
}
