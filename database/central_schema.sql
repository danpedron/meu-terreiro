-- Banco central de tenants, contas e diretório público.
-- Não armazene fundamentos, fotos privadas, pontos riscados ou dados operacionais de cada casa aqui.
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
    -- Diretório público: todos os campos são opt-in e revisáveis pela própria casa.
    listar_publicamente TINYINT(1) NOT NULL DEFAULT 0,
    mostrar_no_mapa TINYINT(1) NOT NULL DEFAULT 0,
    localizacao_publica ENUM('Nenhuma','Bairro','Aproximada','Endereco') NOT NULL DEFAULT 'Nenhuma',
    endereco_publico VARCHAR(255) NULL,
    bairro_publico VARCHAR(120) NULL,
    cidade_publica VARCHAR(120) NULL,
    estado_publico CHAR(2) NULL,
    latitude_publica DECIMAL(10,7) NULL,
    longitude_publica DECIMAL(10,7) NULL,
    descricao_publica TEXT NULL,
    nacao_publica VARCHAR(120) NULL,
    dirigente_publico VARCHAR(255) NULL,
    linha_presenca_publica VARCHAR(255) NULL,
    horarios_publicos TEXT NULL,
    whatsapp_publico VARCHAR(20) NULL,
    email_publico VARCHAR(255) NULL,
    aceita_consultas TINYINT(1) NOT NULL DEFAULT 0,
    aceita_solicitacoes_vinculo TINYINT(1) NOT NULL DEFAULT 1,
    dirigente_status ENUM('Sem dirigente','Pendente de verificação','Verificado') NOT NULL DEFAULT 'Sem dirigente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenants_public_directory (status, listar_publicamente, cidade_publica),
    INDEX idx_tenants_public_location (mostrar_no_mapa, latitude_publica, longitude_publica)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- Mantido para compatibilidade com a primeira versão. O vínculo atual vive em tenant_memberships.
    id_tenant INT NULL,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('SuperAdmin','Regente','Secretario','Financeiro','Estoque','Leitor') NOT NULL DEFAULT 'Leitor',
    global_role ENUM('Usuario','AdminGlobal') NOT NULL DEFAULT 'Usuario',
    whatsapp VARCHAR(20) NULL,
    status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
    ultimo_acesso_em DATETIME NULL,
    termos_aceitos_em DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_tenant) REFERENCES tenants(id) ON DELETE SET NULL,
    INDEX idx_users_tenant_status (id_tenant, status),
    INDEX idx_users_global_role (global_role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_profiles (
    user_id INT PRIMARY KEY,
    cidade VARCHAR(120) NULL,
    estado CHAR(2) NULL,
    recebe_comunicacoes TINYINT(1) NOT NULL DEFAULT 0,
    localizacao_compartilhada_em DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_memberships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    papel ENUM('Consulente','Assistencia','FilhoDeSanto','Babalorixa','Yalorixa','Colaborador') NOT NULL,
    status ENUM('Pendente','Ativo','Recusado','Cancelado','PendenteAdminGlobal') NOT NULL DEFAULT 'Pendente',
    solicitacao TEXT NULL,
    consentimento_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    solicitado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    aprovado_em DATETIME NULL,
    aprovado_por_user_id INT NULL,
    observacao_decisao TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_membership_tenant_user_role (tenant_id, user_id, papel),
    INDEX idx_memberships_user_status (user_id, status),
    INDEX idx_memberships_tenant_status (tenant_id, status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (aprovado_por_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_consultation_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NULL,
    nome_contato VARCHAR(255) NOT NULL,
    whatsapp_contato VARCHAR(20) NULL,
    email_contato VARCHAR(255) NULL,
    disponibilidade VARCHAR(255) NULL,
    mensagem TEXT NULL,
    consentimento_contato_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pendente','Em contato','Concluída','Recusada','Cancelada') NOT NULL DEFAULT 'Pendente',
    tratado_por_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_consultations_tenant_status (tenant_id, status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (tratado_por_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS central_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NULL,
    tenant_id INT NULL,
    action VARCHAR(100) NOT NULL,
    reference_type VARCHAR(80) NULL,
    reference_id INT NULL,
    details VARCHAR(1000) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_central_audit_tenant_created (tenant_id, created_at),
    INDEX idx_central_audit_actor_created (actor_user_id, created_at),
    FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- O primeiro administrador da plataforma deve ser definido conscientemente na implantação.
-- Exemplo: UPDATE users SET global_role='AdminGlobal' WHERE email='admin@example.org';
