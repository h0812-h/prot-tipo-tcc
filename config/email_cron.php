<?php
/**
 * Script para ser executado via CRON para envio automático de emails
 * Adicione no crontab: 0 9 * * * /usr/bin/php /caminho/para/config/email_cron.php
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/email.php';

try {
    $db = getDB();
    $emailService = new EmailService();
    
    echo "🚀 Iniciando envio automático de emails - " . date('Y-m-d H:i:s') . "\n";
    
    // 1. Lembretes de pagamento (3 dias antes do vencimento)
    echo "📧 Verificando lembretes de pagamento...\n";
    
    $stmt = $db->query("
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
        )
    ");
    
    $lembretes_enviados = 0;
    while ($row = $stmt->fetch()) {
        if ($emailService->enviarLembretePagamento($row, $row)) {
            $lembretes_enviados++;
            echo "  ✅ Lembrete enviado para: {$row['nome']} ({$row['email']})\n";
        } else {
            echo "  ❌ Erro ao enviar para: {$row['nome']} ({$row['email']})\n";
        }
    }
    
    echo "📊 Lembretes enviados: $lembretes_enviados\n\n";
    
    // 2. Pagamentos que vencem hoje
    echo "🚨 Verificando pagamentos que vencem hoje...\n";
    
    $stmt = $db->query("
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
        )
    ");
    
    $vencimentos_hoje = 0;
    while ($row = $stmt->fetch()) {
        if ($emailService->enviarLembretePagamento($row, $row)) {
            $vencimentos_hoje++;
            echo "  ⚠️ Aviso de vencimento enviado para: {$row['nome']} ({$row['email']})\n";
        } else {
            echo "  ❌ Erro ao enviar para: {$row['nome']} ({$row['email']})\n";
        }
    }
    
    echo "📊 Avisos de vencimento hoje: $vencimentos_hoje\n\n";
    
    // 3. Pagamentos em atraso (1, 7, 15 e 30 dias)
    echo "❌ Verificando pagamentos em atraso...\n";
    
    $stmt = $db->query("
        SELECT 
            a.id, a.nome, a.email,
            pp.id as pagamento_id, pp.valor, pp.descricao, 
            pp.data_vencimento, pp.forma_pagamento,
            DATEDIFF(CURDATE(), pp.data_vencimento) as dias_atraso
        FROM alunos a
        JOIN pagamentos_pendentes pp ON a.id = pp.aluno_id
        WHERE pp.status = 'Aguardando'
        AND pp.data_vencimento < CURDATE()
        AND DATEDIFF(CURDATE(), pp.data_vencimento) IN (1, 7, 15, 30)
        AND NOT EXISTS (
            SELECT 1 FROM log_emails le 
            WHERE le.aluno_id = a.id 
            AND le.pagamento_id = pp.id 
            AND le.tipo = 'pagamento_atrasado'
            AND DATE(le.data_envio) = CURDATE()
        )
    ");
    
    $atrasos_enviados = 0;
    while ($row = $stmt->fetch()) {
        if ($emailService->enviarLembretePagamento($row, $row)) {
            $atrasos_enviados++;
            echo "  🔴 Aviso de atraso enviado para: {$row['nome']} ({$row['dias_atraso']} dias)\n";
        } else {
            echo "  ❌ Erro ao enviar para: {$row['nome']}\n";
        }
    }
    
    echo "📊 Avisos de atraso enviados: $atrasos_enviados\n\n";
    
    // 4. Relatório para administradores
    if ($lembretes_enviados > 0 || $vencimentos_hoje > 0 || $atrasos_enviados > 0) {
        echo "📋 Enviando relatório para administradores...\n";
        
        $relatorio = "
            Relatório automático de emails enviados:
            
            • Lembretes de pagamento (3 dias): $lembretes_enviados
            • Pagamentos que vencem hoje: $vencimentos_hoje  
            • Avisos de atraso: $atrasos_enviados
            
            Total de emails enviados: " . ($lembretes_enviados + $vencimentos_hoje + $atrasos_enviados);
        
        if ($emailService->enviarNotificacaoAdmin(
            'Relatório Diário de Emails', 
            $relatorio,
            [
                'data' => date('Y-m-d'),
                'lembretes' => $lembretes_enviados,
                'vencimentos' => $vencimentos_hoje,
                'atrasos' => $atrasos_enviados
            ]
        )) {
            echo "  ✅ Relatório enviado para administradores\n";
        } else {
            echo "  ❌ Erro ao enviar relatório\n";
        }
    }
    
    echo "\n✅ Processo concluído - " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "❌ Erro no processo: " . $e->getMessage() . "\n";
    error_log("Erro no CRON de emails: " . $e->getMessage());
}
?>
