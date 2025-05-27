<?php
/**
 * Gerenciador de CRON para facilitar configuração e monitoramento
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/email.php';

class CronManager {
    private $db;
    private $logFile;
    private $lockFile;
    
    public function __construct() {
        $this->db = getDB();
        $this->logFile = __DIR__ . '/../logs/cron_email.log';
        $this->lockFile = __DIR__ . '/../logs/cron_email.lock';
        
        // Criar diretório de logs se não existir
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Executar processo de envio de emails
     */
    public function executarEnvioEmails($forcado = false) {
        // Verificar se já está rodando (evitar execuções simultâneas)
        if (!$forcado && $this->processoJaRodando()) {
            $this->log("⚠️ Processo já está rodando. Pulando execução.");
            return false;
        }
        
        // Criar arquivo de lock
        file_put_contents($this->lockFile, getmypid());
        
        try {
            $this->log("🚀 Iniciando processo de envio de emails");
            
            $emailService = new EmailService();
            $totalEnviados = 0;
            
            // 1. Enviar lembretes de pagamento (3 dias antes)
            $lembretes = $this->enviarLembretesPagamento($emailService);
            $totalEnviados += $lembretes;
            
            // 2. Enviar avisos de vencimento hoje
            $vencimentosHoje = $this->enviarVencimentosHoje($emailService);
            $totalEnviados += $vencimentosHoje;
            
            // 3. Enviar avisos de atraso
            $atrasos = $this->enviarAvisosAtraso($emailService);
            $totalEnviados += $atrasos;
            
            // 4. Processar emails de boas-vindas pendentes
            $boasVindas = $this->enviarBoasVindasPendentes($emailService);
            $totalEnviados += $boasVindas;
            
            // 5. Enviar relatório para administradores (se houver atividade)
            if ($totalEnviados > 0) {
                $this->enviarRelatorioAdmin($emailService, [
                    'lembretes' => $lembretes,
                    'vencimentos' => $vencimentosHoje,
                    'atrasos' => $atrasos,
                    'boas_vindas' => $boasVindas,
                    'total' => $totalEnviados
                ]);
            }
            
            $this->log("✅ Processo concluído. Total de emails enviados: $totalEnviados");
            
            // Registrar execução no banco
            $this->registrarExecucao($totalEnviados);
            
            return $totalEnviados;
            
        } catch (Exception $e) {
            $this->log("❌ Erro no processo: " . $e->getMessage());
            $this->log("Stack trace: " . $e->getTraceAsString());
            return false;
        } finally {
            // Remover arquivo de lock
            if (file_exists($this->lockFile)) {
                unlink($this->lockFile);
            }
        }
    }
    
    /**
     * Enviar lembretes de pagamento
     */
    private function enviarLembretesPagamento($emailService) {
        $this->log("📧 Verificando lembretes de pagamento (3 dias antes)...");
        
        $stmt = $this->db->query("
            SELECT 
                a.id, a.nome, a.email,
                pp.id as pagamento_id, pp.valor, pp.descricao, 
                pp.data_vencimento, pp.forma_pagamento
            FROM alunos a
            JOIN pagamentos_pendentes pp ON a.id = pp.aluno_id
            WHERE pp.status = 'Aguardando'
            AND DATE(pp.data_vencimento) = DATE_ADD(CURDATE(), INTERVAL 3 DAY)
            AND NOT EXISTS (
                SELECT 1 FROM log_emails le 
                WHERE le.aluno_id = a.id 
                AND le.pagamento_id = pp.id 
                AND le.tipo = 'lembrete_pagamento'
                AND DATE(le.data_envio) = CURDATE()
                AND le.status = 'enviado'
            )
        ");
        
        $enviados = 0;
        while ($row = $stmt->fetch()) {
            if ($emailService->enviarLembretePagamento($row, $row)) {
                $enviados++;
                $this->log("  ✅ Lembrete enviado: {$row['nome']} ({$row['email']})");
            } else {
                $this->log("  ❌ Erro ao enviar: {$row['nome']} ({$row['email']})");
            }
            
            // Pequena pausa para não sobrecarregar o servidor SMTP
            usleep(500000); // 0.5 segundos
        }
        
        $this->log("📊 Lembretes enviados: $enviados");
        return $enviados;
    }
    
    /**
     * Enviar avisos de vencimento hoje
     */
    private function enviarVencimentosHoje($emailService) {
        $this->log("🚨 Verificando pagamentos que vencem hoje...");
        
        $stmt = $this->db->query("
            SELECT 
                a.id, a.nome, a.email,
                pp.id as pagamento_id, pp.valor, pp.descricao, 
                pp.data_vencimento, pp.forma_pagamento
            FROM alunos a
            JOIN pagamentos_pendentes pp ON a.id = pp.aluno_id
            WHERE pp.status = 'Aguardando'
            AND DATE(pp.data_vencimento) = CURDATE()
            AND NOT EXISTS (
                SELECT 1 FROM log_emails le 
                WHERE le.aluno_id = a.id 
                AND le.pagamento_id = pp.id 
                AND le.tipo = 'pagamento_vence_hoje'
                AND DATE(le.data_envio) = CURDATE()
                AND le.status = 'enviado'
            )
        ");
        
        $enviados = 0;
        while ($row = $stmt->fetch()) {
            if ($emailService->enviarLembretePagamento($row, $row)) {
                $enviados++;
                $this->log("  ⚠️ Vencimento hoje enviado: {$row['nome']} ({$row['email']})");
            } else {
                $this->log("  ❌ Erro ao enviar: {$row['nome']} ({$row['email']})");
            }
            
            usleep(500000);
        }
        
        $this->log("📊 Avisos de vencimento hoje: $enviados");
        return $enviados;
    }
    
    /**
     * Enviar avisos de atraso
     */
    private function enviarAvisosAtraso($emailService) {
        $this->log("❌ Verificando pagamentos em atraso...");
        
        $stmt = $this->db->query("
            SELECT 
                a.id, a.nome, a.email,
                pp.id as pagamento_id, pp.valor, pp.descricao, 
                pp.data_vencimento, pp.forma_pagamento,
                DATEDIFF(CURDATE(), pp.data_vencimento) as dias_atraso
            FROM alunos a
            JOIN pagamentos_pendentes pp ON a.id = pp.aluno_id
            WHERE pp.status = 'Aguardando'
            AND pp.data_vencimento < CURDATE()
            AND DATEDIFF(CURDATE(), pp.data_vencimento) IN (1, 3, 7, 15, 30)
            AND NOT EXISTS (
                SELECT 1 FROM log_emails le 
                WHERE le.aluno_id = a.id 
                AND le.pagamento_id = pp.id 
                AND le.tipo = 'pagamento_atrasado'
                AND DATE(le.data_envio) = CURDATE()
                AND le.status = 'enviado'
            )
        ");
        
        $enviados = 0;
        while ($row = $stmt->fetch()) {
            if ($emailService->enviarLembretePagamento($row, $row)) {
                $enviados++;
                $this->log("  🔴 Atraso enviado: {$row['nome']} ({$row['dias_atraso']} dias)");
            } else {
                $this->log("  ❌ Erro ao enviar: {$row['nome']}");
            }
            
            usleep(500000);
        }
        
        $this->log("📊 Avisos de atraso enviados: $enviados");
        return $enviados;
    }
    
    /**
     * Enviar emails de boas-vindas pendentes
     */
    private function enviarBoasVindasPendentes($emailService) {
        $this->log("👋 Verificando boas-vindas pendentes...");
        
        // Buscar alunos cadastrados nas últimas 24h que ainda não receberam boas-vindas
        $stmt = $this->db->query("
            SELECT 
                a.id, a.nome, a.email, a.plano_pagamento,
                u.username,
                i.nome as instrumento_nome
            FROM alunos a
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            LEFT JOIN matriculas m ON a.id = m.aluno_id
            LEFT JOIN turmas t ON m.turma_id = t.id
            LEFT JOIN instrumentos i ON t.instrumento_id = i.id
            WHERE a.data_matricula >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND NOT EXISTS (
                SELECT 1 FROM log_emails le 
                WHERE le.aluno_id = a.id 
                AND le.tipo = 'boas_vindas'
                AND le.status = 'enviado'
            )
            GROUP BY a.id
        ");
        
        $enviados = 0;
        while ($row = $stmt->fetch()) {
            $dados_aluno = [
                'id' => $row['id'],
                'nome' => $row['nome'],
                'email' => $row['email'],
                'instrumento' => $row['instrumento_nome'] ?? 'A definir',
                'plano_pagamento' => $row['plano_pagamento'] ?? 'mensal',
                'valor_matricula' => 150.00,
                'data_vencimento' => date('d/m/Y', strtotime('+7 days'))
            ];
            
            $credenciais = [
                'username' => $row['username'] ?? 'a_definir',
                'senha_temporaria' => 'Consulte a secretaria'
            ];
            
            if ($emailService->enviarBoasVindas($dados_aluno, $credenciais)) {
                $enviados++;
                $this->log("  👋 Boas-vindas enviado: {$row['nome']} ({$row['email']})");
            } else {
                $this->log("  ❌ Erro ao enviar boas-vindas: {$row['nome']}");
            }
            
            usleep(500000);
        }
        
        $this->log("📊 Boas-vindas enviados: $enviados");
        return $enviados;
    }
    
    /**
     * Enviar relatório para administradores
     */
    private function enviarRelatorioAdmin($emailService, $estatisticas) {
        $relatorio = "
Relatório automático de emails - " . date('d/m/Y H:i') . "

📊 ESTATÍSTICAS DO DIA:
• Lembretes de pagamento: {$estatisticas['lembretes']}
• Pagamentos que vencem hoje: {$estatisticas['vencimentos']}
• Avisos de atraso: {$estatisticas['atrasos']}
• Boas-vindas: {$estatisticas['boas_vindas']}

📈 TOTAL DE EMAILS ENVIADOS: {$estatisticas['total']}

🔍 Para mais detalhes, acesse o painel administrativo.
        ";
        
        if ($emailService->enviarNotificacaoAdmin(
            'Relatório Diário de Emails - ' . date('d/m/Y'),
            $relatorio,
            $estatisticas
        )) {
            $this->log("📋 Relatório enviado para administradores");
        } else {
            $this->log("❌ Erro ao enviar relatório para administradores");
        }
    }
    
    /**
     * Verificar se processo já está rodando
     */
    private function processoJaRodando() {
        if (!file_exists($this->lockFile)) {
            return false;
        }
        
        $pid = file_get_contents($this->lockFile);
        
        // Verificar se o processo ainda existe (Linux/Unix)
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }
        
        // Fallback para Windows ou sistemas sem posix
        $output = shell_exec("ps -p $pid");
        return !empty($output);
    }
    
    /**
     * Registrar execução no banco
     */
    private function registrarExecucao($emailsEnviados) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO log_execucoes_cron (
                    tipo, emails_enviados, data_execucao, status
                ) VALUES ('envio_emails', ?, NOW(), 'sucesso')
            ");
            $stmt->execute([$emailsEnviados]);
        } catch (Exception $e) {
            $this->log("Erro ao registrar execução: " . $e->getMessage());
        }
    }
    
    /**
     * Log com timestamp
     */
    private function log($mensagem) {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[$timestamp] $mensagem" . PHP_EOL;
        
        // Escrever no arquivo de log
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        
        // Também exibir no console se executado via CLI
        if (php_sapi_name() === 'cli') {
            echo $logLine;
        }
    }
    
    /**
     * Obter estatísticas das últimas execuções
     */
    public function obterEstatisticas($dias = 7) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(data_execucao) as data,
                    SUM(emails_enviados) as total_emails,
                    COUNT(*) as total_execucoes,
                    AVG(emails_enviados) as media_emails
                FROM log_execucoes_cron 
                WHERE tipo = 'envio_emails' 
                AND data_execucao >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY DATE(data_execucao)
                ORDER BY data DESC
            ");
            $stmt->execute([$dias]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Limpar logs antigos
     */
    public function limparLogsAntigos($diasManter = 30) {
        try {
            // Limpar logs do banco
            $stmt = $this->db->prepare("
                DELETE FROM log_execucoes_cron 
                WHERE data_execucao < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$diasManter]);
            
            // Limpar arquivo de log se muito grande (> 10MB)
            if (file_exists($this->logFile) && filesize($this->logFile) > 10 * 1024 * 1024) {
                // Manter apenas as últimas 1000 linhas
                $lines = file($this->logFile);
                $lines = array_slice($lines, -1000);
                file_put_contents($this->logFile, implode('', $lines));
            }
            
            return true;
        } catch (Exception $e) {
            $this->log("Erro ao limpar logs: " . $e->getMessage());
            return false;
        }
    }
}

// Se executado diretamente via CLI
if (php_sapi_name() === 'cli') {
    $cronManager = new CronManager();
    
    // Verificar argumentos
    $forcado = in_array('--force', $argv);
    $limparLogs = in_array('--clean-logs', $argv);
    
    if ($limparLogs) {
        echo "🧹 Limpando logs antigos...\n";
        $cronManager->limparLogsAntigos();
        echo "✅ Logs limpos!\n";
    } else {
        $cronManager->executarEnvioEmails($forcado);
    }
}
?>
