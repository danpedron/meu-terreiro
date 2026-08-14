-- Aplicar em cada banco isolado meuterreiro_<slug>.
ALTER TABLE terreiro_detalhes
    ADD COLUMN IF NOT EXISTS logo_publico VARCHAR(255) NULL AFTER horarios_funcionamento;
