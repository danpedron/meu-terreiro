-- Esquema Base para cada Terreiro (Banco Isolado)

-- Informações do Terreiro
CREATE TABLE IF NOT EXISTS terreiro_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    fundacao DATE,
    nacao VARCHAR(100),
    latitude DECIMAL(10,8),
    longitude DECIMAL(10,8),
    babalorixa VARCHAR(255),
    yalorixa VARCHAR(255),
    mensalidade_valor DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cadastro de Filhos de Santo
CREATE TABLE IF NOT EXISTS filhos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    cpf VARCHAR(14) UNIQUE,
    data_nascimento DATE,
    whatsapp VARCHAR(20),
    endereco TEXT,
    data_entrada DATE,
    cargo VARCHAR(100), -- Ex: Ogã, Ekedi, Filho de Santo
    status ENUM('Ativo', 'Inativo', 'Afastado') DEFAULT 'Ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Entidades dos Filhos
CREATE TABLE IF NOT EXISTS entidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_filho INT NOT NULL,
    nome VARCHAR(255) NOT NULL,
    tipo VARCHAR(100), -- Ex: Caboclo, Preto Velho, Exu
    cor_vela VARCHAR(50),
    ponto_riscado_url TEXT, -- Link para imagem do ponto
    recados TEXT,
    FOREIGN KEY (id_filho) REFERENCES filhos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Obrigações (Rituais)
CREATE TABLE IF NOT EXISTS obrigacoes_tipo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Registro de Obrigações Feitas
CREATE TABLE IF NOT EXISTS filhos_obrigacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_filho INT NOT NULL,
    id_obrigacao INT NOT NULL,
    data_realizacao DATE NOT NULL,
    observacoes TEXT,
    FOREIGN KEY (id_filho) REFERENCES filhos(id) ON DELETE CASCADE,
    FOREIGN KEY (id_obrigacao) REFERENCES obrigacoes_tipo(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Agenda de Giras e Rituais
CREATE TABLE IF NOT EXISTS agenda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    data_hora DATETIME NOT NULL,
    tipo ENUM('Gira', 'Obrigação', 'Festa', 'Reunião') DEFAULT 'Gira',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Controle Financeiro (Mensalidades)
CREATE TABLE IF NOT EXISTS mensalidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_filho INT NOT NULL,
    referencia_mes_ano VARCHAR(7) NOT NULL, -- Format: YYYY-MM
    valor_pago DECIMAL(10,2) NOT NULL,
    data_pagamento DATE NOT NULL,
    registrado_por VARCHAR(100),
    FOREIGN KEY (id_filho) REFERENCES filhos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Biblioteca (Links Google Drive)
CREATE TABLE IF NOT EXISTS biblioteca (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    link_drive TEXT NOT NULL,
    categoria VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
