-- Esquema do banco (será usado na Etapa 2). Importe pelo phpMyAdmin do XAMPP.
CREATE DATABASE IF NOT EXISTS pedal_solidario CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pedal_solidario;

CREATE TABLE IF NOT EXISTS inscricoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo CHAR(10) NOT NULL UNIQUE,
    nome VARCHAR(150) NOT NULL,
    cpf CHAR(11) NOT NULL UNIQUE,
    nascimento DATE NOT NULL,
    sexo ENUM('M','F') NOT NULL,
    email VARCHAR(150) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    cidade VARCHAR(100) NOT NULL,
    uf CHAR(2) NOT NULL,
    categoria VARCHAR(30) NOT NULL,
    equipe VARCHAR(80) NULL,
    camiseta_tamanho VARCHAR(4) NULL,           -- NULL = não comprou camiseta
    emerg_nome VARCHAR(150) NOT NULL,
    emerg_tel VARCHAR(20) NOT NULL,
    saude VARCHAR(300) NULL,
    aceite_regulamento TINYINT(1) NOT NULL DEFAULT 0,
    aceite_termo TINYINT(1) NOT NULL DEFAULT 0,
    valor_inscricao DECIMAL(8,2) NOT NULL,
    valor_camiseta DECIMAL(8,2) NOT NULL DEFAULT 0,
    valor_desconto DECIMAL(8,2) NOT NULL DEFAULT 0,   -- desconto do cupom (só na inscrição)
    cupom_id INT UNSIGNED NULL,
    cupom_codigo VARCHAR(30) NULL,
    valor_taxa DECIMAL(8,2) NOT NULL DEFAULT 0,       -- taxa de administração (só sobre a inscrição)
    valor_total DECIMAL(8,2) NOT NULL,                -- inscrição + taxa - desconto + camiseta
    status ENUM('pendente','pago','cancelado') NOT NULL DEFAULT 'pendente',
    gateway_id VARCHAR(100) NULL,
    pago_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_categoria (categoria),
    INDEX idx_cupom (cupom_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cupons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    codigo VARCHAR(30) NOT NULL UNIQUE,
    tipo ENUM('percentual','fixo') NOT NULL,
    valor DECIMAL(8,2) NOT NULL,
    quantidade INT UNSIGNED NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cupom_tentativas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip, criado_em)
) ENGINE=InnoDB;
