-- Aplicar no banco meuterreiro_admin.
-- Compatível com instalações existentes em MariaDB com suporte a IF NOT EXISTS em ALTER TABLE.
ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS email_responsavel VARCHAR(255) NULL AFTER nome_exibicao,
    ADD COLUMN IF NOT EXISTS onboarding_status ENUM('Em configuração','Concluído') NOT NULL DEFAULT 'Em configuração' AFTER status,
    ADD COLUMN IF NOT EXISTS termos_aceitos_em DATETIME NULL AFTER onboarding_status,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE users
    MODIFY COLUMN role ENUM('SuperAdmin','Regente','Secretario','Financeiro','Estoque','Leitor') NOT NULL DEFAULT 'Regente',
    ADD COLUMN IF NOT EXISTS status ENUM('Ativo','Inativo') NOT NULL DEFAULT 'Ativo' AFTER role,
    ADD COLUMN IF NOT EXISTS ultimo_acesso_em DATETIME NULL AFTER status,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

CREATE INDEX IF NOT EXISTS idx_users_tenant_status ON users (id_tenant, status);
