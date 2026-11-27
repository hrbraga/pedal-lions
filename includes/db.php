<?php
/**
 * Conexão PDO com o MySQL (criada uma única vez por requisição).
 */
function db(): PDO
{
    static $pdo = null;
    static $falha = null; // se a conexão falhou nesta requisição, não tenta de novo (cada tentativa pode demorar)
    if ($falha !== null) {
        throw $falha;
    }
    if ($pdo === null) {
        $c = require __DIR__ . '/../config/banco.php';
        $dsn = 'mysql:host=' . $c['host'] . ';dbname=' . $c['nome'] . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, $c['usuario'], $c['senha'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 3, // banco travado não pode prender o site: desiste em 3 s
            ]);
        } catch (PDOException $e) {
            $falha = $e;
            throw $e;
        }
        // Mesmo relógio do PHP (America/Sao_Paulo): na Hostinger o MySQL pode estar em UTC, e os prazos usam NOW()
        try {
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            error_log('Pedal Solidário – não foi possível acertar o fuso do banco: ' . $e->getMessage());
        }
        esquema_atualizar($pdo);
        inscricoes_expirar($pdo);
    }
    return $pdo;
}

/**
 * Pendentes com prazo vencido viram "expirado" (liberam a vaga, o CPF e o cupom).
 * Roda a cada conexão, então tudo o que lê o banco já enxerga as expiradas. Nunca lança.
 */
function inscricoes_expirar(PDO $pdo): int
{
    try {
        return $pdo->exec("UPDATE inscricoes SET status = 'expirado'
                           WHERE status = 'pendente' AND expira_em IS NOT NULL AND expira_em < NOW()");
    } catch (PDOException $e) {
        error_log('Pedal Solidário – expiração das inscrições: ' . $e->getMessage());
        return 0;
    }
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

        if (!$pdo->query("SHOW TABLES LIKE 'evento_versoes'")->fetch()) {
            $pdo->exec("CREATE TABLE evento_versoes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                dados LONGTEXT NOT NULL,
                salvo_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                salvo_por VARCHAR(50) NOT NULL,
                restaurada_de INT UNSIGNED NULL
            ) ENGINE=InnoDB");
        }

        // Financeiro: categorias de entrada/saída e lançamentos manuais
        if (!$pdo->query("SHOW TABLES LIKE 'fin_categorias'")->fetch()) {
            $pdo->exec("CREATE TABLE fin_categorias (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tipo ENUM('entrada','saida') NOT NULL,
                nome VARCHAR(60) NOT NULL,
                ativa TINYINT(1) NOT NULL DEFAULT 1,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_tipo_nome (tipo, nome)
            ) ENGINE=InnoDB");
        }
        if (!$pdo->query("SHOW TABLES LIKE 'fin_lancamentos'")->fetch()) {
            $pdo->exec("CREATE TABLE fin_lancamentos (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tipo ENUM('entrada','saida') NOT NULL,
                categoria_id INT UNSIGNED NOT NULL,
                data DATE NOT NULL,
                descricao VARCHAR(200) NOT NULL,
                valor DECIMAL(10,2) NOT NULL,
                criado_por VARCHAR(50) NOT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                alterado_por VARCHAR(50) NULL,
                alterado_em DATETIME NULL,
                INDEX idx_data (data),
                INDEX idx_categoria (categoria_id)
            ) ENGINE=InnoDB");
        }

        // Níveis de acesso: quem já existia continua com acesso total
        if ($pdo->query("SHOW TABLES LIKE 'admins'")->fetch()
            && !$pdo->query("SHOW COLUMNS FROM admins LIKE 'acesso_total'")->fetch()) {
            $pdo->exec("ALTER TABLE admins
                ADD COLUMN acesso_total TINYINT(1) NOT NULL DEFAULT 1,
                ADD COLUMN paginas VARCHAR(255) NOT NULL DEFAULT ''");
        }
        // Senha definida por outra pessoa: o administrador troca no primeiro uso
        if ($pdo->query("SHOW TABLES LIKE 'admins'")->fetch()
            && !$pdo->query("SHOW COLUMNS FROM admins LIKE 'trocar_senha'")->fetch()) {
            $pdo->exec("ALTER TABLE admins ADD COLUMN trocar_senha TINYINT(1) NOT NULL DEFAULT 0");
        }

        if (!$pdo->query("SHOW COLUMNS FROM inscricoes LIKE 'cupom_id'")->fetch()) {
            $pdo->exec("ALTER TABLE inscricoes
                ADD COLUMN valor_desconto DECIMAL(8,2) NOT NULL DEFAULT 0,
                ADD COLUMN cupom_id INT UNSIGNED NULL,
                ADD COLUMN cupom_codigo VARCHAR(30) NULL,
                ADD INDEX idx_cupom (cupom_id)");
        }
        // Modelo da camiseta (ex.: 'curta', 'longa'); NULL = sem camiseta ou inscrição de antes dos modelos
        if (!$pdo->query("SHOW COLUMNS FROM inscricoes LIKE 'camiseta_modelo'")->fetch()) {
            $pdo->exec("ALTER TABLE inscricoes ADD COLUMN camiseta_modelo VARCHAR(20) NULL AFTER camiseta_tamanho");
        }
        if (!$pdo->query("SHOW COLUMNS FROM inscricoes LIKE 'valor_taxa'")->fetch()) {
            $pdo->exec("ALTER TABLE inscricoes ADD COLUMN valor_taxa DECIMAL(8,2) NOT NULL DEFAULT 0");
        }
        // E-mail de pagamento confirmado: quando saiu e o motivo da última falha
        if (!$pdo->query("SHOW COLUMNS FROM inscricoes LIKE 'email_pago_em'")->fetch()) {
            $pdo->exec("ALTER TABLE inscricoes ADD COLUMN email_pago_em DATETIME NULL AFTER pago_em,
                ADD COLUMN email_pago_erro VARCHAR(255) NULL AFTER email_pago_em");
        }
        // Pagamento online (Mercado Pago): status "expirado", prazo, pagamento que quitou, alertas e e-mail de instruções
        $col_status = $pdo->query("SHOW COLUMNS FROM inscricoes LIKE 'status'")->fetch();
        if ($col_status && stripos((string) $col_status['Type'], 'expirado') === false) {
            $pdo->exec("ALTER TABLE inscricoes MODIFY status ENUM('pendente','pago','cancelado','expirado') NOT NULL DEFAULT 'pendente'");
        }
        // Um CPF pode ter várias inscrições (expiradas/canceladas); "uma ativa por CPF" é conferido no código, sob a trava de vagas
        if ($pdo->query("SHOW INDEX FROM inscricoes WHERE Key_name = 'cpf'")->fetch()) {
            $pdo->exec('ALTER TABLE inscricoes DROP INDEX cpf, ADD INDEX idx_cpf (cpf)');
        }
        if (!$pdo->query("SHOW COLUMNS FROM inscricoes LIKE 'expira_em'")->fetch()) {
            $pdo->exec("ALTER TABLE inscricoes
                ADD COLUMN expira_em DATETIME NULL AFTER pago_em,
                ADD COLUMN pagamento_mp_id VARCHAR(30) NULL AFTER expira_em,
                ADD COLUMN pagamento_alerta VARCHAR(255) NULL AFTER pagamento_mp_id,
                ADD COLUMN email_instr_em DATETIME NULL AFTER email_pago_erro,
                ADD COLUMN email_instr_erro VARCHAR(255) NULL AFTER email_instr_em");
        }
        if (!$pdo->query("SHOW TABLES LIKE 'pagamentos'")->fetch()) {
            $pdo->exec("CREATE TABLE pagamentos (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                inscricao_id INT UNSIGNED NOT NULL,
                mp_id VARCHAR(30) NOT NULL UNIQUE,
                metodo VARCHAR(20) NOT NULL,
                parcelas TINYINT UNSIGNED NULL,
                status VARCHAR(30) NOT NULL,
                status_detalhe VARCHAR(60) NULL,
                valor DECIMAL(8,2) NOT NULL,
                pix_copia_cola TEXT NULL,
                pix_qr_base64 MEDIUMTEXT NULL,
                pix_expira_em DATETIME NULL,
                consultado_em DATETIME NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_inscricao (inscricao_id)
            ) ENGINE=InnoDB");
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
