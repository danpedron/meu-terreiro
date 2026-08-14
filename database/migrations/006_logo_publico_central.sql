-- Aplicar somente no banco meuterreiro_admin.
USE meuterreiro_admin;

ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS logo_publico VARCHAR(255) NULL AFTER email_publico;
