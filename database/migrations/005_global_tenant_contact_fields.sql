-- Cadastro administrativo: número público da casa.
-- Aplicar somente no banco meuterreiro_admin.
USE meuterreiro_admin;

ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS numero_publico VARCHAR(30) NULL AFTER bairro_publico;
