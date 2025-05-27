<?php
    // Versão com PHPMailer (se disponível)
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;
// Verificar se PHPMailer está disponível
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    // Versão simplificada para desenvolvimento local
    class EmailService {
        private $modo_desenvolvimento = true; // Mude para false em produção
        
        public function enviarBoasVindas($aluno_dados, $credenciais) {
            $to = $aluno_dados['email'];
            $subject = '🎵 Bem-vindo à Escola de Música Harmonia!';
            
            $message = $this->criarTemplateBoasVindas($aluno_dados, $credenciais);
            
            if ($this->modo_desenvolvimento) {
                // Modo desenvolvimento: salvar email em arquivo
                return $this->salvarEmailEmArquivo($to, $subject, $message);
            } else {
                // Modo produção: tentar enviar email real
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= 'From: Escola Harmonia <contato@escolaharmonia.com>' . "\r\n";
                
                $resultado = mail($to, $subject, $message, $headers);
                
                // Log simples
                $this->logEmail($aluno_dados['email'], $subject, $resultado ? 'enviado' : 'erro');
                
                return $resultado;
            }
        }
        
        private function salvarEmailEmArquivo($to, $subject, $message) {
            try {
                // Criar diretório se não existir
                $dir = __DIR__ . '/../emails_enviados';
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                
                // Nome do arquivo com timestamp
                $timestamp = date('Y-m-d_H-i-s');
                $filename = $dir . "/email_{$timestamp}_" . preg_replace('/[^a-zA-Z0-9]/', '_', $to) . ".html";
                
                // Conteúdo do arquivo
                $conteudo = "<!--\n";
                $conteudo .= "SIMULAÇÃO DE EMAIL - MODO DESENVOLVIMENTO\n";
                $conteudo .= "Data/Hora: " . date('d/m/Y H:i:s') . "\n";
                $conteudo .= "Para: {$to}\n";
                $conteudo .= "Assunto: {$subject}\n";
                $conteudo .= "Status: Simulado (salvo em arquivo)\n";
                $conteudo .= "-->\n\n";
                $conteudo .= $message;
                
                // Salvar arquivo
                $resultado = file_put_contents($filename, $conteudo);
                
                if ($resultado !== false) {
                    // Log do "envio"
                    $this->logEmail($to, $subject, 'simulado');
                    
                    // Criar arquivo de índice para fácil acesso
                    $this->criarIndiceEmails($to, $subject, $filename);
                    
                    return true;
                } else {
                    return false;
                }
                
            } catch (Exception $e) {
                error_log("Erro ao salvar email simulado: " . $e->getMessage());
                return false;
            }
        }
        
        private function criarIndiceEmails($to, $subject, $filename) {
            $indice_file = __DIR__ . '/../emails_enviados/indice.html';
            
            $nova_linha = "<tr>";
            $nova_linha .= "<td>" . date('d/m/Y H:i:s') . "</td>";
            $nova_linha .= "<td>" . htmlspecialchars($to) . "</td>";
            $nova_linha .= "<td>" . htmlspecialchars($subject) . "</td>";
            $nova_linha .= "<td><a href='" . basename($filename) . "' target='_blank'>📧 Ver Email</a></td>";
            $nova_linha .= "</tr>\n";
            
            if (!file_exists($indice_file)) {
                // Criar arquivo de índice
                $conteudo_indice = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📧 Emails Enviados - Modo Desenvolvimento</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        .alert { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        tr:hover { background: #f5f5f5; }
        a { color: #667eea; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .btn { display: inline-block; background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px; }
        .btn:hover { background: #5a6fd8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Emails Enviados - Modo Desenvolvimento</h1>
        
        <div class="alert">
            <strong>ℹ️ Modo Desenvolvimento Ativo</strong><br>
            Os emails não estão sendo enviados realmente. Eles são salvos como arquivos HTML para visualização.
            <br><br>
            <a href="../admin/teste_email_simples.php" class="btn">🧪 Testar Configuração</a>
            <a href="../index.php" class="btn">🏠 Voltar ao Site</a>
            <a href="javascript:location.reload()" class="btn">🔄 Atualizar Lista</a>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>📅 Data/Hora</th>
                    <th>📧 Destinatário</th>
                    <th>📝 Assunto</th>
                    <th>👁️ Ações</th>
                </tr>
            </thead>
            <tbody>
';
                file_put_contents($indice_file, $conteudo_indice);
            }
            
            // Adicionar nova linha ao índice
            $conteudo_atual = file_get_contents($indice_file);
            $conteudo_atual = str_replace('</tbody>', $nova_linha . '</tbody>', $conteudo_atual);
            file_put_contents($indice_file, $conteudo_atual);
        }
        
        private function criarTemplateBoasVindas($aluno_dados, $credenciais) {
            return '
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Bem-vindo à Escola Harmonia</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f5f5f5; }
                    .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
                    .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 30px; text-align: center; }
                    .header h1 { margin: 0; font-size: 2em; }
                    .content { padding: 30px; }
                    .welcome-box { background: #e8f5e8; border: 2px solid #4caf50; border-radius: 8px; padding: 20px; margin: 20px 0; }
                    .info-box { background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 20px; margin: 20px 0; }
                    .credentials { background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; margin: 15px 0; font-family: monospace; }
                    .btn { display: inline-block; background: #667eea; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                    .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
                    .highlight { color: #667eea; font-weight: bold; }
                    ul { padding-left: 20px; }
                    li { margin-bottom: 8px; }
                    .dev-notice { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>🎵 Escola de Música Harmonia</h1>
                        <p>Bem-vindo à nossa família musical!</p>
                    </div>
                    
                    <div class="content">
                        <div class="dev-notice">
                            <strong>🔧 MODO DESENVOLVIMENTO</strong><br>
                            Este email foi gerado em modo de desenvolvimento. Em produção, seria enviado para: <strong>' . htmlspecialchars($aluno_dados['email']) . '</strong>
                        </div>
                        
                        <div class="welcome-box">
                            <h2>🎉 Parabéns, ' . htmlspecialchars($aluno_dados['nome']) . '!</h2>
                            <p>Sua matrícula foi realizada com <strong>sucesso</strong>! Estamos muito felizes em tê-lo(a) conosco na Escola de Música Harmonia.</p>
                        </div>
                        
                        <h3>📋 Seus Dados de Acesso ao Sistema</h3>
                        <div class="credentials">
                            <p><strong>🌐 Site:</strong> <a href="http://localhost/protótipo-tcc/login.php">http://localhost/protótipo-tcc/login.php</a></p>
                            <p><strong>👤 Usuário:</strong> <span class="highlight">' . htmlspecialchars($credenciais['username']) . '</span></p>
                            <p><strong>🔒 Senha temporária:</strong> <span class="highlight">' . htmlspecialchars($credenciais['senha_temporaria']) . '</span></p>
                        </div>
                        
                        <div class="info-box">
                            <h4>⚠️ Importante:</h4>
                            <p>Por favor, <strong>altere sua senha</strong> no primeiro acesso ao sistema para garantir a segurança da sua conta.</p>
                        </div>
                        
                        <h3>🎯 Próximos Passos</h3>
                        <ul>
                            <li><strong>Acesse o sistema</strong> com suas credenciais</li>
                            <li><strong>Complete seu perfil</strong> com informações adicionais</li>
                            <li><strong>Aguarde contato</strong> da secretaria para definir horários</li>
                            <li><strong>Efetue o pagamento</strong> conforme orientações recebidas</li>
                        </ul>
                        
                        <h3>💰 Informações de Pagamento</h3>
                        <div class="info-box">
                            <p><strong>Plano escolhido:</strong> ' . ucfirst($aluno_dados['plano_pagamento'] ?? 'mensal') . '</p>
                            <p><strong>Valor:</strong> R$ ' . number_format($aluno_dados['valor_matricula'] ?? 150, 2, ',', '.') . '</p>
                            <p><strong>Vencimento:</strong> ' . ($aluno_dados['data_vencimento'] ?? date('d/m/Y', strtotime('+7 days'))) . '</p>
                            <p><em>Você receberá instruções detalhadas de pagamento em breve.</em></p>
                        </div>
                        
                        <h3>🎼 Sobre Suas Aulas</h3>
                        <p>Você se interessou pelo instrumento: <strong>' . htmlspecialchars($aluno_dados['instrumento'] ?? 'A definir') . '</strong></p>
                        <p>Nossa equipe entrará em contato em breve para:</p>
                        <ul>
                            <li>Definir seus horários de aula</li>
                            <li>Apresentar seu professor</li>
                            <li>Orientar sobre materiais necessários</li>
                            <li>Esclarecer dúvidas sobre o curso</li>
                        </ul>
                        
                        <h3>📞 Contato</h3>
                        <p>Se tiver alguma dúvida, entre em contato conosco:</p>
                        <ul>
                            <li><strong>📧 Email:</strong> contato@escolaharmonia.com</li>
                            <li><strong>📱 WhatsApp:</strong> (11) 99999-0000</li>
                            <li><strong>📞 Telefone:</strong> (11) 3333-4444</li>
                        </ul>
                        
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="http://localhost/protótipo-tcc/login.php" class="btn">🚀 Acessar Meu Painel</a>
                        </div>
                        
                        <div class="welcome-box">
                            <h4>🎵 Bem-vindo à Família Forjados!</h4>
                            <p>Estamos ansiosos para fazer parte da sua jornada musical. Prepare-se para descobrir o prazer de fazer música!</p>
                        </div>
                    </div>
                    
                    <div class="footer">
                        <p>&copy; ' . date('Y') . ' Escola de Música Forjados Music Studio. Todos os direitos reservados.</p>
                        <p>📍 Rua da Música, 123 - Centro - São Paulo/SP</p>
                        <p>Este é um email automático, mas você pode responder se tiver dúvidas.</p>
                        <hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">
                        <p style="font-size: 12px; color: #999;">
                            <strong>MODO DESENVOLVIMENTO:</strong> Este email foi salvo como arquivo HTML em vez de ser enviado.
                        </p>
                    </div>
                </div>
            </body>
            </html>';
        }
        
        private function logEmail($destinatario, $assunto, $status) {
            try {
                $db = getDB();
                $stmt = $db->prepare("
                    INSERT INTO log_emails (destinatario, assunto, tipo, status, data_envio) 
                    VALUES (?, ?, 'boas_vindas', ?, NOW())
                ");
                $stmt->execute([$destinatario, $assunto, $status]);
            } catch (Exception $e) {
                error_log("Erro ao registrar log de email: " . $e->getMessage());
            }
        }
        
        public function testarConfiguracao() {
            if ($this->modo_desenvolvimento) {
                return "Modo desenvolvimento ativo - emails são salvos como arquivos";
            } else {
                // Teste simples com mail() do PHP
                $teste = mail(
                    'teste@exemplo.com',
                    'Teste de Email - Escola Harmonia',
                    'Este é um teste de configuração de email.',
                    "From: contato@escolaharmonia.com\r\nContent-Type: text/html; charset=UTF-8\r\n"
                );
                
                return $teste;
            }
        }
    }
} else {


    class EmailService {
        private $mailer;
        private $config;
        private $modo_desenvolvimento = true; // Mude para false em produção
        
        public function __construct() {
            $this->config = [
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 587,
                'smtp_username' => 'contato@escolaharmonia.com',
                'smtp_password' => 'sua_senha_aqui', // Configure aqui
                'smtp_secure' => PHPMailer::ENCRYPTION_STARTTLS,
                'from_email' => 'contato@escolaharmonia.com',
                'from_name' => 'Escola de Música Harmonia',
                'use_smtp' => false // Mude para true se quiser usar SMTP
            ];
            
            if (!$this->modo_desenvolvimento) {
                $this->initializeMailer();
            }
        }
        
        private function initializeMailer() {
            $this->mailer = new PHPMailer(true);
            
            try {
                if ($this->config['use_smtp']) {
                    $this->mailer->isSMTP();
                    $this->mailer->Host = $this->config['smtp_host'];
                    $this->mailer->SMTPAuth = true;
                    $this->mailer->Username = $this->config['smtp_username'];
                    $this->mailer->Password = $this->config['smtp_password'];
                    $this->mailer->SMTPSecure = $this->config['smtp_secure'];
                    $this->mailer->Port = $this->config['smtp_port'];
                } else {
                    $this->mailer->isMail();
                }
                
                $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
                $this->mailer->CharSet = 'UTF-8';
                $this->mailer->isHTML(true);
                
            } catch (Exception $e) {
                error_log("Erro ao configurar PHPMailer: " . $e->getMessage());
            }
        }
        
        public function enviarBoasVindas($aluno_dados, $credenciais) {
            if ($this->modo_desenvolvimento) {
                // Usar versão simplificada em modo desenvolvimento
                $emailSimples = new EmailService();
                return $emailSimples->enviarBoasVindas($aluno_dados, $credenciais);
            }
            
            try {
                $this->mailer->clearAddresses();
                $this->mailer->addAddress($aluno_dados['email'], $aluno_dados['nome']);
                
                $this->mailer->Subject = '🎵 Bem-vindo à Escola de Música Harmonia!';
                $this->mailer->Body = $this->criarTemplateBoasVindas($aluno_dados, $credenciais);
                
                $resultado = $this->mailer->send();
                
                $this->logEmail($aluno_dados['email'], $this->mailer->Subject, 'enviado');
                
                return $resultado;
                
            } catch (Exception $e) {
                $this->logEmail($aluno_dados['email'] ?? 'desconhecido', 'Boas-vindas', 'erro');
                error_log("Erro ao enviar email de boas-vindas: " . $e->getMessage());
                return false;
            }
        }
        
        private function criarTemplateBoasVindas($aluno_dados, $credenciais) {
            return '
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Bem-vindo à Escola Harmonia</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f5f5f5; }
                    .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
                    .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 30px; text-align: center; }
                    .header h1 { margin: 0; font-size: 2em; }
                    .content { padding: 30px; }
                    .welcome-box { background: #e8f5e8; border: 2px solid #4caf50; border-radius: 8px; padding: 20px; margin: 20px 0; }
                    .info-box { background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 20px; margin: 20px 0; }
                    .credentials { background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; margin: 15px 0; font-family: monospace; }
                    .btn { display: inline-block; background: #667eea; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                    .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 14px; }
                    .highlight { color: #667eea; font-weight: bold; }
                    ul { padding-left: 20px; }
                    li { margin-bottom: 8px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>🎵 Escola de Música Harmonia</h1>
                        <p>Bem-vindo à nossa família musical!</p>
                    </div>
                    
                    <div class="content">
                        <div class="welcome-box">
                            <h2>🎉 Parabéns, ' . htmlspecialchars($aluno_dados['nome']) . '!</h2>
                            <p>Sua matrícula foi realizada com <strong>sucesso</strong>! Estamos muito felizes em tê-lo(a) conosco na Escola de Música Harmonia.</p>
                        </div>
                        
                        <h3>📋 Seus Dados de Acesso ao Sistema</h3>
                        <div class="credentials">
                            <p><strong>🌐 Site:</strong> <a href="http://localhost/login.php">http://localhost/login.php</a></p>
                            <p><strong>👤 Usuário:</strong> <span class="highlight">' . htmlspecialchars($credenciais['username']) . '</span></p>
                            <p><strong>🔒 Senha temporária:</strong> <span class="highlight">' . htmlspecialchars($credenciais['senha_temporaria']) . '</span></p>
                        </div>
                        
                        <div class="info-box">
                            <h4>⚠️ Importante:</h4>
                            <p>Por favor, <strong>altere sua senha</strong> no primeiro acesso ao sistema para garantir a segurança da sua conta.</p>
                        </div>
                        
                        <h3>🎯 Próximos Passos</h3>
                        <ul>
                            <li><strong>Acesse o sistema</strong> com suas credenciais</li>
                            <li><strong>Complete seu perfil</strong> com informações adicionais</li>
                            <li><strong>Aguarde contato</strong> da secretaria para definir horários</li>
                            <li><strong>Efetue o pagamento</strong> conforme orientações recebidas</li>
                        </ul>
                        
                        <h3>💰 Informações de Pagamento</h3>
                        <div class="info-box">
                            <p><strong>Plano escolhido:</strong> ' . ucfirst($aluno_dados['plano_pagamento'] ?? 'mensal') . '</p>
                            <p><strong>Valor:</strong> R$ ' . number_format($aluno_dados['valor_matricula'] ?? 150, 2, ',', '.') . '</p>
                            <p><strong>Vencimento:</strong> ' . ($aluno_dados['data_vencimento'] ?? date('d/m/Y', strtotime('+7 days'))) . '</p>
                            <p><em>Você receberá instruções detalhadas de pagamento em breve.</em></p>
                        </div>
                        
                        <h3>🎼 Sobre Suas Aulas</h3>
                        <p>Você se interessou pelo instrumento: <strong>' . htmlspecialchars($aluno_dados['instrumento'] ?? 'A definir') . '</strong></p>
                        <p>Nossa equipe entrará em contato em breve para:</p>
                        <ul>
                            <li>Definir seus horários de aula</li>
                            <li>Apresentar seu professor</li>
                            <li>Orientar sobre materiais necessários</li>
                            <li>Esclarecer dúvidas sobre o curso</li>
                        </ul>
                        
                        <h3>📞 Contato</h3>
                        <p>Se tiver alguma dúvida, entre em contato conosco:</p>
                        <ul>
                            <li><strong>📧 Email:</strong> contato@escolaharmonia.com</li>
                            <li><strong>📱 WhatsApp:</strong> (11) 99999-0000</li>
                            <li><strong>📞 Telefone:</strong> (11) 3333-4444</li>
                        </ul>
                        
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="http://localhost/login.php" class="btn">🚀 Acessar Meu Painel</a>
                        </div>
                        
                        <div class="welcome-box">
                            <h4>🎵 Bem-vindo à Família Harmonia!</h4>
                            <p>Estamos ansiosos para fazer parte da sua jornada musical. Prepare-se para descobrir o prazer de fazer música!</p>
                        </div>
                    </div>
                    
                    <div class="footer">
                        <p>&copy; ' . date('Y') . ' Escola de Música Harmonia. Todos os direitos reservados.</p>
                        <p>📍 Rua da Música, 123 - Centro - São Paulo/SP</p>
                        <p>Este é um email automático, mas você pode responder se tiver dúvidas.</p>
                    </div>
                </div>
            </body>
            </html>';
        }
        
        private function logEmail($destinatario, $assunto, $status) {
            try {
                $db = getDB();
                $stmt = $db->prepare("
                    INSERT INTO log_emails (destinatario, assunto, tipo, status, data_envio) 
                    VALUES (?, ?, 'boas_vindas', ?, NOW())
                ");
                $stmt->execute([$destinatario, $assunto, $status]);
            } catch (Exception $e) {
                error_log("Erro ao registrar log de email: " . $e->getMessage());
            }
        }
        
        public function testarConfiguracao() {
            try {
                $this->mailer->clearAddresses();
                $this->mailer->addAddress($this->config['from_email'], 'Teste');
                $this->mailer->Subject = 'Teste de Configuração - Escola Harmonia';
                $this->mailer->Body = '<h2>✅ Teste de Email</h2><p>Se você recebeu este email, a configuração está funcionando!</p>';
                
                return $this->mailer->send();
                
            } catch (Exception $e) {
                error_log("Erro no teste de email: " . $e->getMessage());
                return false;
            }
        }
    }
}

// Função helper
function enviarEmail() {
    static $emailService = null;
    
    if ($emailService === null) {
        $emailService = new EmailService();
    }
    
    return $emailService;
}
?>
