-- Esquema base isolado por terreiro.
-- Cada banco pertence exclusivamente a uma casa. Registros rituais, fotos, locais e
-- informações pessoais devem ser configurados como restritos por padrão.

CREATE TABLE IF NOT EXISTS terreiro_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    fundacao DATE NULL,
    nacao VARCHAR(100) NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    babalorixa VARCHAR(255) NULL,
    yalorixa VARCHAR(255) NULL,
    mensalidade_valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS terreiro_detalhes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao TEXT NULL,
    cidade VARCHAR(120) NULL,
    estado CHAR(2) NULL,
    telefone VARCHAR(30) NULL,
    whatsapp VARCHAR(20) NULL,
    email_contato VARCHAR(255) NULL,
    endereco_publico TEXT NULL,
    numero_publico VARCHAR(30) NULL,
    bairro_publico VARCHAR(120) NULL,
    horarios_funcionamento TEXT NULL,
    logo_publico VARCHAR(255) NULL,
    politica_privacidade TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS filhos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    cpf VARCHAR(14) UNIQUE NULL,
    data_nascimento DATE NULL,
    whatsapp VARCHAR(20) NULL,
    endereco TEXT NULL,
    data_entrada DATE NULL,
    cargo VARCHAR(100) NULL,
    status ENUM('Ativo', 'Inativo', 'Afastado') NOT NULL DEFAULT 'Ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS consentimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_filho INT NOT NULL,
    tipo ENUM('Dados pessoais','Imagem','Comunicação','Exportação') NOT NULL,
    autorizado TINYINT(1) NOT NULL DEFAULT 0,
    registrado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacao TEXT NULL,
    FOREIGN KEY (id_filho) REFERENCES filhos(id) ON DELETE CASCADE,
    INDEX idx_consentimentos_filho (id_filho, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS entidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_filho INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    tipo VARCHAR(100) NULL,
    cor_vela VARCHAR(50) NULL,
    ponto_riscado_url TEXT NULL,
    recados TEXT NULL,
    nivel_sigilo ENUM('Restrito','Dirigência') NOT NULL DEFAULT 'Restrito',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_filho) REFERENCES filhos(id) ON DELETE CASCADE,
    INDEX idx_entidades_filho (id_filho)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS obrigacoes_tipo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    nivel_sigilo ENUM('Interno','Restrito','Dirigência') NOT NULL DEFAULT 'Restrito',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS filhos_obrigacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_filho INT NOT NULL,
    id_obrigacao INT NOT NULL,
    data_realizacao DATE NOT NULL,
    observacoes TEXT NULL,
    registrado_por INT NULL,
    FOREIGN KEY (id_filho) REFERENCES filhos(id) ON DELETE CASCADE,
    FOREIGN KEY (id_obrigacao) REFERENCES obrigacoes_tipo(id),
    INDEX idx_obrigacoes_filho_data (id_filho, data_realizacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agenda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    data_hora DATETIME NOT NULL,
    tipo ENUM('Gira', 'Obrigação', 'Festa', 'Reunião', 'Estudo', 'Ação social', 'Manutenção') NOT NULL DEFAULT 'Gira',
    local_evento VARCHAR(255) NULL,
    status ENUM('Planejado','Confirmado','Concluído','Cancelado') NOT NULL DEFAULT 'Planejado',
    visibilidade ENUM('Interno','Restrito','Dirigência') NOT NULL DEFAULT 'Interno',
    capacidade INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_agenda_data (data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agenda_participantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_agenda INT NOT NULL,
    id_filho INT NOT NULL,
    situacao ENUM('Convidado','Confirmado','Ausente','Aguardando') NOT NULL DEFAULT 'Convidado',
    observacao VARCHAR(500) NULL,
    FOREIGN KEY (id_agenda) REFERENCES agenda(id) ON DELETE CASCADE,
    FOREIGN KEY (id_filho) REFERENCES filhos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_agenda_participante (id_agenda, id_filho)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mensalidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_filho INT NOT NULL,
    referencia_mes_ano CHAR(7) NOT NULL,
    valor_pago DECIMAL(10,2) NOT NULL,
    data_pagamento DATE NOT NULL,
    meio_pagamento VARCHAR(60) NULL,
    registrado_por VARCHAR(100) NULL,
    FOREIGN KEY (id_filho) REFERENCES filhos(id) ON DELETE CASCADE,
    INDEX idx_mensalidades_referencia (referencia_mes_ano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lancamentos_financeiros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('Entrada','Saída') NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    data_lancamento DATE NOT NULL,
    meio_pagamento VARCHAR(60) NULL,
    comprovante_url TEXT NULL,
    visibilidade ENUM('Financeiro','Dirigência') NOT NULL DEFAULT 'Financeiro',
    criado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lancamentos_data (data_lancamento),
    INDEX idx_lancamentos_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    categoria VARCHAR(100) NULL,
    contato VARCHAR(255) NULL,
    telefone VARCHAR(30) NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS itens_estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    categoria ENUM('Folhas e ervas','Velas','Bebidas','Alimentos','Limpeza','Cozinha','Papelaria','Vestuário','Manutenção','Outro') NOT NULL DEFAULT 'Outro',
    unidade VARCHAR(30) NOT NULL DEFAULT 'unidade',
    quantidade_atual DECIMAL(12,3) NOT NULL DEFAULT 0,
    estoque_minimo DECIMAL(12,3) NOT NULL DEFAULT 0,
    validade DATE NULL,
    local_armazenamento VARCHAR(255) NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_estoque_categoria (categoria),
    INDEX idx_estoque_validade (validade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS movimentacoes_estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_item INT NOT NULL,
    tipo ENUM('Entrada','Saída','Ajuste','Perda') NOT NULL,
    quantidade DECIMAL(12,3) NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    data_movimentacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    registrado_por INT NULL,
    FOREIGN KEY (id_item) REFERENCES itens_estoque(id) ON DELETE CASCADE,
    INDEX idx_movimentos_item_data (id_item, data_movimentacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS compras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_fornecedor INT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor_estimado DECIMAL(12,2) NULL,
    data_necessidade DATE NULL,
    status ENUM('Solicitada','Aprovada','Comprada','Cancelada') NOT NULL DEFAULT 'Solicitada',
    prioridade ENUM('Baixa','Normal','Alta','Urgente') NOT NULL DEFAULT 'Normal',
    observacoes TEXT NULL,
    criado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_fornecedor) REFERENCES fornecedores(id) ON DELETE SET NULL,
    INDEX idx_compras_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS preparos_alimentares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    data_preparo DATE NOT NULL,
    responsavel VARCHAR(255) NULL,
    destino VARCHAR(255) NULL,
    observacoes TEXT NULL,
    visibilidade ENUM('Interno','Restrito') NOT NULL DEFAULT 'Interno',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_preparos_data (data_preparo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS oferendas_registros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    data_registro DATE NOT NULL,
    responsavel VARCHAR(255) NULL,
    local_descricao VARCHAR(255) NULL,
    orientacao_ambiental TEXT NULL,
    observacoes TEXT NULL,
    nivel_sigilo ENUM('Restrito','Dirigência') NOT NULL DEFAULT 'Restrito',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tarefas_casa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    categoria ENUM('Limpeza','Manutenção','Cozinha','Organização','Segurança','Outro') NOT NULL DEFAULT 'Organização',
    responsavel VARCHAR(255) NULL,
    data_limite DATE NULL,
    status ENUM('Pendente','Em andamento','Concluída','Cancelada') NOT NULL DEFAULT 'Pendente',
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tarefas_status_prazo (status, data_limite)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS patrimonio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    categoria VARCHAR(100) NULL,
    numero_identificacao VARCHAR(100) NULL,
    valor_estimado DECIMAL(12,2) NULL,
    estado_conservacao ENUM('Bom','Atenção','Manutenção necessária') NOT NULL DEFAULT 'Bom',
    local_armazenamento VARCHAR(255) NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS biblioteca (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    link_drive TEXT NOT NULL,
    categoria VARCHAR(100) NULL,
    visibilidade ENUM('Interno','Restrito','Dirigência') NOT NULL DEFAULT 'Interno',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS album_fotos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    url_arquivo TEXT NOT NULL,
    data_registro DATE NULL,
    pessoas_identificadas TEXT NULL,
    consentimento_confirmado TINYINT(1) NOT NULL DEFAULT 0,
    visibilidade ENUM('Interno','Restrito','Dirigência') NOT NULL DEFAULT 'Restrito',
    descricao TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS locais_referencia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    tipo ENUM('Folha e erva','Encruzilhada','Fonte','Mata','Praia','Mercado','Fornecedor','Outro') NOT NULL DEFAULT 'Outro',
    localizacao_descricao TEXT NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    condicao ENUM('Confirmar','Aberta','Fechada','Evitar','Sazonal') NOT NULL DEFAULT 'Confirmar',
    acesso_restrito TINYINT(1) NOT NULL DEFAULT 1,
    observacoes TEXT NULL,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_locais_tipo_condicao (tipo, condicao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comunicados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    publico ENUM('Todos','Equipe','Dirigência') NOT NULL DEFAULT 'Equipe',
    publicado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_em DATETIME NULL,
    criado_por INT NULL,
    INDEX idx_comunicados_publicado (publicado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS incidentes_seguranca (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_incidente DATE NOT NULL,
    categoria ENUM('Intolerância religiosa','Ameaça','Vandalismo','Conflito','Acidente','Outro') NOT NULL DEFAULT 'Outro',
    descricao TEXT NOT NULL,
    providencias TEXT NULL,
    contato_apoio VARCHAR(255) NULL,
    status ENUM('Aberto','Em acompanhamento','Encerrado') NOT NULL DEFAULT 'Aberto',
    nivel_sigilo ENUM('Dirigência') NOT NULL DEFAULT 'Dirigência',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_incidentes_status_data (status, data_incidente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auditoria (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_central_id INT NULL,
    acao VARCHAR(100) NOT NULL,
    tabela_referencia VARCHAR(100) NOT NULL,
    registro_id INT NULL,
    detalhes TEXT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auditoria_referencia (tabela_referencia, registro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
