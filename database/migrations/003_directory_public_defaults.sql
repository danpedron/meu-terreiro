-- Centros aprovados passam a ser públicos por padrão e podem ser ocultados pela própria casa.
-- Cadastros sem autenticação ficam suspensos até uma decisão explícita da administração global.

ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS cadastro_publico_pendente TINYINT(1) NOT NULL DEFAULT 0 AFTER localizacao_publica,
    ADD COLUMN IF NOT EXISTS solicitante_cadastro_nome VARCHAR(255) NULL AFTER cadastro_publico_pendente;

ALTER TABLE tenants
    MODIFY COLUMN listar_publicamente TINYINT(1) NOT NULL DEFAULT 1,
    MODIFY COLUMN localizacao_publica ENUM('Nenhuma','Bairro','Aproximada','Endereco') NOT NULL DEFAULT 'Bairro';

-- Migração de comportamento: casas já ativas passam a constar no diretório.
-- Nenhum endereço, coordenada ou contato é criado/copied por esta migration.
UPDATE tenants
   SET listar_publicamente = 1,
       localizacao_publica = CASE WHEN localizacao_publica = 'Nenhuma' THEN 'Bairro' ELSE localizacao_publica END
 WHERE status = 'Ativo';
