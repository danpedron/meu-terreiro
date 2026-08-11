-- Banco Central: meuterreiro_admin

CREATE DATABASE IF NOT EXISTS meuterreiro_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE meuterreiro_admin;

-- Tabela de Terreiros Cadastrados
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL, -- Usado na URL ou subdomínio
    db_name VARCHAR(64) UNIQUE NOT NULL, -- Nome do banco de dados isolado
    nome_exibicao VARCHAR(255) NOT NULL,
    status ENUM('Ativo', 'Suspenso', 'Inativo') DEFAULT 'Ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Usuários (Administradores do Sistema ou do Terreiro)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_tenant INT, -- NULL se for SuperAdmin
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('SuperAdmin', 'Regente', 'Secretario') DEFAULT 'Regente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_tenant) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
