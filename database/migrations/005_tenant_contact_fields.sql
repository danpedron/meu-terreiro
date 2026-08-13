-- Cadastro administrativo: endereço e contatos públicos da casa.
-- Aplicar em cada banco isolado de terreiro existente.

ALTER TABLE terreiro_detalhes
    ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(20) NULL AFTER telefone,
    ADD COLUMN IF NOT EXISTS numero_publico VARCHAR(30) NULL AFTER endereco_publico,
    ADD COLUMN IF NOT EXISTS bairro_publico VARCHAR(120) NULL AFTER numero_publico,
    ADD COLUMN IF NOT EXISTS horarios_funcionamento TEXT NULL AFTER bairro_publico;
