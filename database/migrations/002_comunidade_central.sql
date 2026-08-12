-- Migration 002: contas globais, diretório público, vínculos e pedidos de consulta.
-- Executar somente no banco meuterreiro_admin.
USE meuterreiro_admin;

ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS listar_publicamente TINYINT(1) NOT NULL DEFAULT 0 AFTER termos_aceitos_em,
    ADD COLUMN IF NOT EXISTS mostrar_no_mapa TINYINT(1) NOT NULL DEFAULT 0 AFTER listar_publicamente,
    ADD COLUMN IF NOT EXISTS localizacao_publica ENUM('Nenhuma','Bairro','Aproximada','Endereco') NOT NULL DEFAULT 'Nenhuma' AFTER mostrar_no_mapa,
    ADD COLUMN IF NOT EXISTS endereco_publico VARCHAR(255) NULL AFTER localizacao_publica,
    ADD COLUMN IF NOT EXISTS bairro_publico VARCHAR(120) NULL AFTER endereco_publico,
    ADD COLUMN IF NOT EXISTS cidade_publica VARCHAR(120) NULL AFTER bairro_publico,
    ADD COLUMN IF NOT EXISTS estado_publico CHAR(2) NULL AFTER cidade_publica,
    ADD COLUMN IF NOT EXISTS latitude_publica DECIMAL(10,7) NULL AFTER estado_publico,
    ADD COLUMN IF NOT EXISTS longitude_publica DECIMAL(10,7) NULL AFTER latitude_publica,
    ADD COLUMN IF NOT EXISTS descricao_publica TEXT NULL AFTER longitude_publica,
    ADD COLUMN IF NOT EXISTS nacao_publica VARCHAR(120) NULL AFTER descricao_publica,
    ADD COLUMN IF NOT EXISTS dirigente_publico VARCHAR(255) NULL AFTER nacao_publica,
    ADD COLUMN IF NOT EXISTS linha_presenca_publica VARCHAR(255) NULL AFTER dirigente_publico,
    ADD COLUMN IF NOT EXISTS horarios_publicos TEXT NULL AFTER linha_presenca_publica,
    ADD COLUMN IF NOT EXISTS whatsapp_publico VARCHAR(20) NULL AFTER horarios_publicos,
    ADD COLUMN IF NOT EXISTS email_publico VARCHAR(255) NULL AFTER whatsapp_publico,
    ADD COLUMN IF NOT EXISTS aceita_consultas TINYINT(1) NOT NULL DEFAULT 0 AFTER email_publico,
    ADD COLUMN IF NOT EXISTS aceita_solicitacoes_vinculo TINYINT(1) NOT NULL DEFAULT 1 AFTER aceita_consultas,
    ADD COLUMN IF NOT EXISTS dirigente_status ENUM('Sem dirigente','Pendente de verificação','Verificado') NOT NULL DEFAULT 'Sem dirigente' AFTER aceita_solicitacoes_vinculo;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS global_role ENUM('Usuario','AdminGlobal') NOT NULL DEFAULT 'Usuario' AFTER role,
    ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(20) NULL AFTER global_role,
    ADD COLUMN IF NOT EXISTS termos_aceitos_em DATETIME NULL AFTER ultimo_acesso_em;

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

CREATE INDEX IF NOT EXISTS idx_tenants_public_directory ON tenants (status, listar_publicamente, cidade_publica);
CREATE INDEX IF NOT EXISTS idx_tenants_public_location ON tenants (mostrar_no_mapa, latitude_publica, longitude_publica);
CREATE INDEX IF NOT EXISTS idx_users_global_role ON users (global_role, status);

-- Migração de compatibilidade: o regente inicial vira membro ativo da própria casa.
INSERT IGNORE INTO tenant_memberships (tenant_id, user_id, papel, status, solicitacao, consentimento_em, solicitado_em, aprovado_em, aprovado_por_user_id, observacao_decisao)
SELECT u.id_tenant, u.id, 'Yalorixa', 'Ativo', 'Vínculo migrado do onboarding inicial.', NOW(), NOW(), NOW(), u.id, 'Criado na migração de compatibilidade.'
FROM users u
WHERE u.id_tenant IS NOT NULL AND u.role = 'Regente' AND u.status = 'Ativo';

UPDATE tenants t
JOIN users u ON u.id_tenant = t.id AND u.role = 'Regente' AND u.status = 'Ativo'
SET t.dirigente_status = 'Verificado',
    t.dirigente_publico = COALESCE(t.dirigente_publico, u.nome)
WHERE t.dirigente_status = 'Sem dirigente';
