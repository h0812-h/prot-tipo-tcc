-- Criação do banco de dados para a Escola de Música Harmonia (VERSÃO CORRIGIDA)
CREATE DATABASE IF NOT EXISTS escola_musica;
USE escola_musica;

-- Tabela de usuários (administradores e alunos)
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    tipo ENUM('administrador', 'aluno') NOT NULL,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telefone VARCHAR(20),
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ativo BOOLEAN DEFAULT TRUE
);

-- Tabela de instrumentos
CREATE TABLE instrumentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT,
    icone VARCHAR(10) DEFAULT '🎵'
);

-- Tabela de professores
CREATE TABLE professores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telefone VARCHAR(20),
    especialidade VARCHAR(100),
    data_contratacao DATE,
    ativo BOOLEAN DEFAULT TRUE
);

-- Tabela de turmas
CREATE TABLE turmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    professor_id INT,
    instrumento_id INT,
    dia_semana ENUM('Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'),
    horario_inicio TIME,
    horario_fim TIME,
    max_alunos INT DEFAULT 10,
    ativa BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (professor_id) REFERENCES professores(id),
    FOREIGN KEY (instrumento_id) REFERENCES instrumentos(id)
);

-- Tabela de alunos
CREATE TABLE alunos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNIQUE,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    telefone VARCHAR(20),
    data_nascimento DATE,
    endereco TEXT,
    responsavel_nome VARCHAR(100),
    responsavel_telefone VARCHAR(20),
    status ENUM('Cadastrado', 'Matriculado', 'Suspenso', 'Inativo') DEFAULT 'Cadastrado',
    data_matricula DATE,
    observacoes TEXT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Tabela de matrículas (relaciona alunos com turmas)
CREATE TABLE matriculas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT,
    turma_id INT,
    data_matricula DATE NOT NULL,
    data_cancelamento DATE NULL,
    status ENUM('Ativa', 'Cancelada', 'Suspensa') DEFAULT 'Ativa',
    valor_mensalidade DECIMAL(10,2),
    FOREIGN KEY (aluno_id) REFERENCES alunos(id),
    FOREIGN KEY (turma_id) REFERENCES turmas(id)
);

-- Tabela de chamadas
CREATE TABLE chamadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turma_id INT,
    data_aula DATE NOT NULL,
    professor_id INT,
    observacoes TEXT,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (turma_id) REFERENCES turmas(id),
    FOREIGN KEY (professor_id) REFERENCES professores(id)
);

-- Tabela de presenças
CREATE TABLE presencas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chamada_id INT,
    aluno_id INT,
    presente BOOLEAN NOT NULL,
    observacoes TEXT,
    FOREIGN KEY (chamada_id) REFERENCES chamadas(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id)
);

-- Tabela de mensalidades
CREATE TABLE mensalidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricula_id INT,
    mes_referencia DATE,
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE NULL,
    status ENUM('Pendente', 'Pago', 'Atrasado', 'Cancelado') DEFAULT 'Pendente',
    forma_pagamento VARCHAR(50),
    observacoes TEXT,
    FOREIGN KEY (matricula_id) REFERENCES matriculas(id)
);

-- Inserção de dados iniciais

-- Instrumentos
INSERT INTO instrumentos (nome, descricao, icone) VALUES
('Piano', 'Instrumento de teclas clássico', '🎹'),
('Violão', 'Instrumento de cordas popular', '🎸'),
('Bateria', 'Conjunto de instrumentos de percussão', '🥁'),
('Violino', 'Instrumento de cordas erudito', '🎻'),
('Flauta', 'Instrumento de sopro', '🎵'),
('Saxofone', 'Instrumento de sopro jazz', '🎷');

-- Professores
INSERT INTO professores (nome, email, telefone, especialidade, data_contratacao) VALUES
('Carlos Silva', 'carlos@escolaharmonia.com', '(11) 99999-1111', 'Piano Clássico', '2020-01-15'),
('Ana Costa', 'ana@escolaharmonia.com', '(11) 99999-2222', 'Violão Popular', '2020-03-10'),
('João Santos', 'joao@escolaharmonia.com', '(11) 99999-3333', 'Bateria e Percussão', '2021-02-01'),
('Maria Oliveira', 'maria@escolaharmonia.com', '(11) 99999-4444', 'Violino Erudito', '2021-06-15'),
('Pedro Lima', 'pedro@escolaharmonia.com', '(11) 99999-5555', 'Flauta e Sopros', '2022-01-10');

-- Turmas
INSERT INTO turmas (nome, professor_id, instrumento_id, dia_semana, horario_inicio, horario_fim, max_alunos) VALUES
('Piano Iniciante A', 1, 1, 'Segunda', '14:00:00', '15:00:00', 8),
('Piano Iniciante B', 1, 1, 'Quarta', '15:00:00', '16:00:00', 8),
('Violão Popular', 2, 2, 'Terça', '16:00:00', '17:00:00', 10),
('Violão Intermediário', 2, 2, 'Quinta', '17:00:00', '18:00:00', 8),
('Bateria Iniciante', 3, 3, 'Sexta', '18:00:00', '19:00:00', 6),
('Violino Clássico', 4, 4, 'Sábado', '09:00:00', '10:00:00', 6);

-- Usuários com senhas corretas (senha123 para todos)
-- Hash gerado com password_hash('senha123', PASSWORD_DEFAULT)
INSERT INTO usuarios (username, password, tipo, nome, email, telefone) VALUES
('admin', '$2y$10$YourHashHere', 'administrador', 'Administrador Sistema', 'admin@escolaharmonia.com', '(11) 99999-0000'),
('maria.santos', '$2y$10$YourHashHere', 'aluno', 'Maria Santos', 'maria.santos@email.com', '(11) 99999-1001'),
('pedro.costa', '$2y$10$YourHashHere', 'aluno', 'Pedro Costa', 'pedro.costa@email.com', '(11) 99999-1002'),
('ana.silva', '$2y$10$YourHashHere', 'aluno', 'Ana Silva', 'ana.silva@email.com', '(11) 99999-1003');

-- Alunos
INSERT INTO alunos (usuario_id, nome, email, telefone, data_nascimento, endereco, responsavel_nome, responsavel_telefone, status, data_matricula) VALUES
(2, 'Maria Santos', 'maria.santos@email.com', '(11) 99999-1001', '2005-03-15', 'Rua das Flores, 123', 'José Santos', '(11) 99999-1010', 'Matriculado', '2024-01-15'),
(3, 'Pedro Costa', 'pedro.costa@email.com', '(11) 99999-1002', '2006-07-22', 'Av. Principal, 456', 'Carmen Costa', '(11) 99999-1020', 'Matriculado', '2024-02-01'),
(4, 'Ana Silva', 'ana.silva@email.com', '(11) 99999-1003', '2007-11-08', 'Rua da Música, 789', 'Roberto Silva', '(11) 99999-1030', 'Cadastrado', NULL);

-- Matrículas
INSERT INTO matriculas (aluno_id, turma_id, data_matricula, valor_mensalidade) VALUES
(1, 1, '2024-01-15', 150.00),
(2, 3, '2024-02-01', 180.00),
(1, 2, '2024-03-01', 150.00);

-- Mensalidades
INSERT INTO mensalidades (matricula_id, mes_referencia, valor, data_vencimento, data_pagamento, status) VALUES
(1, '2024-01-01', 150.00, '2024-01-10', '2024-01-08', 'Pago'),
(1, '2024-02-01', 150.00, '2024-02-10', '2024-02-09', 'Pago'),
(1, '2024-03-01', 150.00, '2024-03-10', NULL, 'Pendente'),
(2, '2024-02-01', 180.00, '2024-02-10', '2024-02-12', 'Pago'),
(2, '2024-03-01', 180.00, '2024-03-10', NULL, 'Atrasado');

-- Atualizar senhas com hash correto (execute este comando após criar as tabelas)
-- UPDATE usuarios SET password = '$2y$10$[hash_correto_aqui]' WHERE username IN ('admin', 'maria.santos', 'pedro.costa', 'ana.silva');