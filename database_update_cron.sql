-- Tabela para log de execuções do CRON
CREATE TABLE IF NOT EXISTS log_execucoes_cron (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    emails_enviados INT DEFAULT 0,
    data_execucao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('sucesso', 'erro', 'parcial') DEFAULT 'sucesso',
    detalhes TEXT,
    tempo_execucao INT COMMENT 'Tempo em segundos',
    INDEX idx_tipo (tipo),
    INDEX idx_data (data_execucao)
);

-- Configurações específicas do CRON
INSERT INTO configuracoes_email (chave, valor, descricao) VALUES
('cron_ativo', '1', 'Ativar execução automática do CRON (1=sim, 0=não)'),
('cron_horario_principal', '09:00', 'Horário principal de execução'),
('cron_horario_secundario', '18:30', 'Horário secundário de execução'),
('cron_max_emails_por_execucao', '100', 'Máximo de emails por execução'),
('cron_intervalo_entre_envios', '500', 'Intervalo entre envios em milissegundos'),
('cron_dias_lembrete', '3', 'Dias antes do vencimento para lembrete'),
('cron_dias_atraso_notificar', '1,3,7,15,30', 'Dias de atraso para notificar')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

-- View para monitoramento do CRON
CREATE OR REPLACE VIEW vw_monitoramento_cron AS
SELECT 
    DATE(data_execucao) as data,
    COUNT(*) as total_execucoes,
    SUM(emails_enviados) as total_emails_dia,
    AVG(emails_enviados) as media_emails_execucao,
    MAX(data_execucao) as ultima_execucao,
    SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as execucoes_com_erro,
    ROUND(
        (SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) * 100.0) / COUNT(*), 
        2
    ) as taxa_sucesso
FROM log_execucoes_cron 
WHERE tipo = 'envio_emails'
AND data_execucao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(data_execucao)
ORDER BY data DESC;
