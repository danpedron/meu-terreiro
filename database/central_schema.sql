-- Banco central de tenants e usuários. Não armazene conteúdo ritual, fotos ou dados operacionais aqui.
CREATE DATABASE IF NOT EXISTS meuterreiro_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE meuterreiro_admin;

CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL,
    db_name VARCHAR(64) UNIQUE NOT NULL,
    nome_exibicao VARCHAR(255) NOT NULL,
    email_responsavel VARCHAR(255) NULL,
    status ENUM('Ativo', 'Suspenso', 'Inativo') NOT NULL DEFAULT 'Ativo',
    onboarding_status ENUM('Em configuração','Concluído') NOT NULL DEFAULT 'Em configuração',
    termos_aceitos_em DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_tenant INT NULL,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('SuperAdmin','Regente','Secretario','Financeiro','Estoque','Leitor') NOT NULL DEFAULT 'Regente',
    status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
    ultimo_acesso_em DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_tenant) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_users_tenant_status (id_tenant, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
