<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/email.php';

verificarAdmin();

$resultado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_destino = $_POST['email_destino'] ?? '';
    
    if (empty($email_destino)) {
        $resultado = '❌ Por favor, informe um email de destino.';
    } else {
        try {
            $emailService = enviarEmail();
            
            // Dados de teste
            $dados_aluno = [
                'id' => 999,
                'nome' => 'Aluno Teste',
                'email' => $email_destino,
                'instrumento' => 'Piano',
                'plano_pagamento' => 'mensal',
                'valor_matricula' => 150.00,
                'data_vencimento' => date('d/m/Y', strtotime('+7 days'))
            ];
            
            $credenciais = [
                'username' => 'aluno.teste',
                'senha_temporaria' => 'teste123'
            ];
            
            if ($emailService->enviarBoasVindas($dados_aluno, $credenciais)) {
                $resultado = '✅ Email de teste enviado com sucesso para: ' . $email_destino;
            } else {
                $resultado = '❌ Erro ao enviar email. Verifique as configurações.';
            }
            
        } catch (Exception $e) {
            $resultado = '❌ Erro: ' . $e->getMessage();
        }
    }
}

// Verificar se mail() está funcionando
$mail_disponivel = function_exists('mail');
$phpmailer_disponivel = class_exists('PHPMailer\PHPMailer\PHPMailer');

// Buscar últimos emails enviados
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT * FROM log_emails 
        WHERE tipo = 'boas_vindas' 
        ORDER BY data_envio DESC 
        LIMIT 10
    ");
    $emails_recentes = $stmt->fetchAll();
} catch (Exception $e) {
    $emails_recentes = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Email Simples - Escola Harmonia</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .status-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 5px;
        }
        
        .status-ok {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .config-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
            font-size: 14px;
        }
        
        .email-log {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        
        .email-log table {
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>📧 Teste de Email Simples</h1>
                <p class="subtitle">Testar envio de email de boas-vindas</p>
            </div>
            <div class="header-actions">
                <a href="../dashboard.php" class="btn btn-secondary">🏠 Dashboard</a>
                <a href="../logout.php" class="btn btn-danger">🚪 Sair</a>
            </div>
        </div>

        <!-- Navegação -->
        <nav class="navbar">
            <ul>
                <li><a href="../dashboard.php">🏠 Dashboard</a></li>
                <li><a href="alunos.php">👥 Alunos</a></li>
                <li><a href="teste_email_simples.php" class="active">📧 Teste Email</a></li>
            </ul>
        </nav>

        <?php if ($resultado): ?>
            <div class="alert alert-<?= strpos($resultado, '✅') !== false ? 'success' : 'danger' ?>">
                <?= $resultado ?>
            </div>
        <?php endif; ?>

        <!-- Status do Sistema -->
        <div class="status-card">
            <h3>🔍 Status do Sistema de Email</h3>
            
            <div class="status-item <?= $mail_disponivel ? 'status-ok' : 'status-error' ?>">
                <span><?= $mail_disponivel ? '✅' : '❌' ?></span>
                <span><strong>Função mail() do PHP:</strong> <?= $mail_disponivel ? 'Disponível' : 'Não disponível' ?></span>
            </div>
            
            <div class="status-item <?= $phpmailer_disponivel ? 'status-ok' : 'status-warning' ?>">
                <span><?= $phpmailer_disponivel ? '✅' : '⚠️' ?></span>
                <span><strong>PHPMailer:</strong> <?= $phpmailer_disponivel ? 'Instalado' : 'Não instalado (usando mail() nativo)' ?></span>
            </div>
            
            <?php if (!$mail_disponivel): ?>
                <div class="alert alert-danger">
                    <strong>❌ Problema:</strong> A função mail() não está disponível no seu PHP. 
                    Verifique a configuração do servidor ou instale o PHPMailer.
                </div>
            <?php endif; ?>
        </div>

        <!-- Formulário de Teste -->
        <div class="status-card">
            <h3>🧪 Testar Envio de Email</h3>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email_destino">Email de Destino:</label>
                    <input type="email" id="email_destino" name="email_destino" class="form-control" 
                           placeholder="seu@email.com" value="<?= htmlspecialchars($_POST['email_destino'] ?? '') ?>" required>
                    <small class="form-text text-muted">
                        Digite seu email para receber um exemplo do email de boas-vindas que os alunos recebem.
                    </small>
                </div>
                
                <button type="submit" class="btn btn-primary">📧 Enviar Email de Teste</button>
            </form>
        </div>

        <!-- Configuração -->
        <div class="status-card">
            <h3>⚙️ Configuração</h3>
            
            <p>O sistema está configurado para usar:</p>
            
            <?php if ($phpmailer_disponivel): ?>
                <div class="config-info">
                    <strong>📦 PHPMailer detectado!</strong><br>
                    Para usar SMTP (Gmail, Outlook, etc.), edite o arquivo:<br>
                    <code>config/email.php</code><br><br>
                    
                    Altere as configurações:<br>
                    <code>'use_smtp' => true</code><br>
                    <code>'smtp_username' => 'seu@email.com'</code><br>
                    <code>'smtp_password' => 'sua_senha'</code>
                </div>
            <?php else: ?>
                <div class="config-info">
                    <strong>📮 Usando mail() nativo do PHP</strong><br>
                    Funciona na maioria dos servidores Linux.<br>
                    Para desenvolvimento local, pode não funcionar.<br><br>
                    
                    Para instalar PHPMailer:<br>
                    <code>composer require phpmailer/phpmailer</code>
                </div>
            <?php endif; ?>
            
            <div class="alert alert-info">
                <h5>💡 Para desenvolvimento local:</h5>
                <ul>
                    <li><strong>XAMPP/WAMP:</strong> Configure sendmail ou use PHPMailer com SMTP</li>
                    <li><strong>MAMP:</strong> Ative o módulo de email nas configurações</li>
                    <li><strong>Docker:</strong> Configure um container de email ou use SMTP externo</li>
                </ul>
            </div>
        </div>

        <!-- Log de Emails -->
        <?php if (!empty($emails_recentes)): ?>
        <div class="status-card">
            <h3>📄 Últimos Emails Enviados</h3>
            
            <div class="email-log">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Destinatário</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emails_recentes as $email): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($email['data_envio'])) ?></td>
                                <td><?= htmlspecialchars($email['destinatario']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $email['status'] === 'enviado' ? 'success' : 'danger' ?>">
                                        <?= $email['status'] === 'enviado' ? '✅ Enviado' : '❌ Erro' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Instruções -->
        <div class="status-card">
            <h3>📋 Como Funciona</h3>
            
            <ol>
                <li><strong>Cadastro Automático:</strong> Quando um aluno se cadastra na página inicial, o sistema automaticamente envia um email de boas-vindas</li>
                <li><strong>Conteúdo do Email:</strong> O email inclui dados de acesso, orientações sobre próximos passos e informações de pagamento</li>
                <li><strong>Log Automático:</strong> Todos os envios são registrados no banco de dados para controle</li>
                <li><strong>Fallback:</strong> Se o email falhar, o cadastro ainda é realizado normalmente</li>
            </ol>
            
            <div class="alert alert-success">
                <h5>✅ Vantagens desta abordagem:</h5>
                <ul>
                    <li>Não precisa de CRON ou configuração complexa</li>
                    <li>Funciona imediatamente após o cadastro</li>
                    <li>Simples de manter e debugar</li>
                    <li>Funciona em qualquer ambiente (local ou produção)</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus no campo de email
        document.getElementById('email_destino').focus();
        
        // Validação do formulário
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email_destino').value;
            if (!email || !email.includes('@')) {
                e.preventDefault();
                alert('Por favor, digite um email válido.');
                return false;
            }
            
            // Confirmar envio
            if (!confirm('Enviar email de teste para: ' + email + '?')) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>
