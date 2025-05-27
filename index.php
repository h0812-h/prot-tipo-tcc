<?php
require_once 'config/database.php';

session_start();

// Se já estiver logado, redirecionar para o dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit();
}

$mensagem = '';
$tipo_mensagem = '';

// Processar cadastro de aluno
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cadastrar_aluno') {
    $db = null;
    $transacao_ativa = false;
    
    try {
        $db = getDB();
        
        // Validar dados
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $data_nascimento = $_POST['data_nascimento'] ?? '';
        $endereco = trim($_POST['endereco'] ?? '');
        $responsavel_nome = trim($_POST['responsavel_nome'] ?? '');
        $responsavel_telefone = trim($_POST['responsavel_telefone'] ?? '');
        $instrumento_id = $_POST['instrumento_id'] ?? '';
        $nivel = $_POST['nivel'] ?? '';
        $observacoes = trim($_POST['observacoes'] ?? '');
        
        // Dados de pagamento (apenas para demonstração)
        $plano_pagamento = $_POST['plano_pagamento'] ?? 'mensal';
        $forma_pagamento = $_POST['forma_pagamento'] ?? 'cartao';
        
        if (empty($nome) || empty($email) || empty($telefone) || empty($instrumento_id)) {
            throw new Exception('Por favor, preencha todos os campos obrigatórios.');
        }
        
        // Verificar se email já existe
        $stmt = $db->prepare("SELECT id FROM alunos WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Este email já está cadastrado em nosso sistema.');
        }
        
        // Iniciar transação APENAS AQUI
        $db->beginTransaction();
        $transacao_ativa = true;
        
        // Inserir aluno
        $stmt = $db->prepare("
            INSERT INTO alunos (
                nome, email, telefone, data_nascimento, endereco, 
                responsavel_nome, responsavel_telefone, status, 
                data_matricula, observacoes, status_pagamento,
                plano_pagamento, forma_pagamento_preferida
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pré-Matricula', NOW(), ?, 'Pendente', ?, ?)
        ");
        
        $stmt->execute([
            $nome, $email, $telefone, $data_nascimento, $endereco,
            $responsavel_nome, $responsavel_telefone, $observacoes,
            $plano_pagamento, $forma_pagamento
        ]);
        
        $aluno_id = $db->lastInsertId();
        
        // Criar usuário para o aluno - CORRIGIDO
        $username = strtolower(str_replace(' ', '.', $nome));
        $username = preg_replace('/[^a-z0-9.]/', '', $username);

        // Verificar se username já existe e adicionar número se necessário
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        $counter = 1;
        $original_username = $username;
        while ($stmt->fetch()) {
            $username = $original_username . $counter;
            $stmt->execute([$username]);
            $counter++;
        }

        // NOVO: Verificar e limpar email duplicado antes de inserir
        try {
            $stmt = $db->prepare("DELETE FROM usuarios WHERE email = ? AND tipo = 'aluno'");
            $stmt->execute([$email]);
        } catch (Exception $e) {
            // Ignorar erro se não conseguir limpar
        }

        $senha_temporaria = 'temp' . rand(1000, 9999);
        $senha_hash = password_hash($senha_temporaria, PASSWORD_DEFAULT);

        $stmt = $db->prepare("
            INSERT INTO usuarios (username, password, tipo, nome, email, ativo) 
            VALUES (?, ?, 'aluno', ?, ?, 1)
        ");
        $stmt->execute([$username, $senha_hash, $nome, $email]);
        
        $usuario_id = $db->lastInsertId();
        
        // Atualizar aluno com usuario_id
        $stmt = $db->prepare("UPDATE alunos SET usuario_id = ? WHERE id = ?");
        $stmt->execute([$usuario_id, $aluno_id]);
        
        // Verificar se tabela pagamentos_pendentes existe
        try {
            $stmt = $db->prepare("
                INSERT INTO pagamentos_pendentes (
                    aluno_id, valor, descricao, data_vencimento, 
                    plano_pagamento, forma_pagamento, status
                ) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), ?, ?, 'Aguardando')
            ");
            
            $valor_matricula = match($plano_pagamento) {
                'mensal' => 150.00,
                'trimestral' => 400.00,
                'semestral' => 750.00,
                'anual' => 1400.00,
                default => 150.00
            };
            
            $stmt->execute([
                $aluno_id, 
                $valor_matricula, 
                "Taxa de matrícula - Plano $plano_pagamento", 
                $plano_pagamento, 
                $forma_pagamento
            ]);
        } catch (PDOException $e) {
            // Se a tabela não existir, apenas continuar
            error_log("Tabela pagamentos_pendentes não existe: " . $e->getMessage());
        }
        
        // Commit da transação
        $db->commit();
        $transacao_ativa = false;
        
        // Preparar mensagem de sucesso inicial
        $mensagem = "
            <strong>Cadastro realizado com sucesso!</strong><br>
            <strong>Usuário:</strong> $username<br>
            <strong>Senha temporária:</strong> $senha_temporaria<br>
            <strong>Valor da matrícula:</strong> R$ " . number_format($valor_matricula, 2, ',', '.') . "<br>
            <em>Você receberá instruções de pagamento por email em breve.</em>
        ";
        
        // ENVIAR EMAIL DE BOAS-VINDAS AUTOMATICAMENTE
        try {
            require_once 'config/email.php';
            
            $emailService = enviarEmail();
            
            // Buscar nome do instrumento
            $stmt = $db->prepare("SELECT nome FROM instrumentos WHERE id = ?");
            $stmt->execute([$instrumento_id]);
            $instrumento_result = $stmt->fetch();
            $instrumento_nome = $instrumento_result ? $instrumento_result['nome'] : 'A definir';
            
            $dados_aluno = [
                'id' => $aluno_id,
                'nome' => $nome,
                'email' => $email,
                'instrumento' => $instrumento_nome,
                'plano_pagamento' => $plano_pagamento,
                'valor_matricula' => $valor_matricula,
                'data_vencimento' => date('d/m/Y', strtotime('+7 days'))
            ];
            
            $credenciais = [
                'username' => $username,
                'senha_temporaria' => $senha_temporaria
            ];
            
            // Tentar enviar email
            $email_enviado = $emailService->enviarBoasVindas($dados_aluno, $credenciais);
            
            if ($email_enviado) {
                $mensagem .= "<br><br>📧 <strong>Email de boas-vindas enviado com sucesso!</strong><br>";
                $mensagem .= "<small>Verifique sua caixa de entrada (e spam) para ver as orientações completas.</small>";
            } else {
                $mensagem .= "<br><br>⚠️ <strong>Atenção:</strong> Não foi possível enviar o email de boas-vindas automaticamente.<br>";
                $mensagem .= "<small>Mas não se preocupe! Seu cadastro foi realizado com sucesso. Entre em contato conosco se precisar das orientações.</small>";
            }
            
        } catch (Exception $email_error) {
            // Se der erro no email, não afetar o cadastro
            error_log("Erro ao enviar email de boas-vindas: " . $email_error->getMessage());
            $mensagem .= "<br><br>⚠️ <strong>Observação:</strong> Seu cadastro foi realizado com sucesso, mas houve um problema no envio do email de boas-vindas.<br>";
            $mensagem .= "<small>Nossa equipe entrará em contato em breve com todas as orientações.</small>";
        }
        
        $tipo_mensagem = 'success';
        
    } catch (Exception $e) {
        // Fazer rollback APENAS se a transação estiver ativa
        if ($transacao_ativa && $db) {
            try {
                $db->rollBack();
            } catch (PDOException $rollback_error) {
                error_log("Erro no rollback: " . $rollback_error->getMessage());
            }
        }
        $mensagem = $e->getMessage();
        $tipo_mensagem = 'error';
    }
}

// Buscar algumas estatísticas públicas para mostrar na página inicial
try {
    $db = getDB();
    
    // Total de alunos ativos
    $stmt = $db->query("SELECT COUNT(*) as total FROM alunos WHERE status NOT IN ('Inativo', 'Cancelado')");
    $total_alunos = $stmt->fetch()['total'];
    
    // Total de turmas ativas
    $stmt = $db->query("SELECT COUNT(*) as total FROM turmas WHERE ativa = 1");
    $total_turmas = $stmt->fetch()['total'];
    
    // Total de professores
    $stmt = $db->query("SELECT COUNT(*) as total FROM professores WHERE ativo = 1");
    $total_professores = $stmt->fetch()['total'];
    
    // Instrumentos disponíveis
    $stmt = $db->query("SELECT * FROM instrumentos ORDER BY nome");
    $instrumentos = $stmt->fetchAll();
    
} catch (PDOException $e) {
    // Em caso de erro, usar valores padrão
    $total_alunos = 0;
    $total_turmas = 0;
    $total_professores = 0;
    $instrumentos = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escola de Música Harmonia - Sistema de Gestão</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Estilos específicos para a página inicial */
        .hero-section {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9)),
                        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><pattern id="music" patternUnits="userSpaceOnUse" width="100" height="100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100%" height="100%" fill="url(%23music)"/></svg>');
            color: white;
            padding: 80px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
            z-index: 2;
        }
        
        .hero-title {
            font-size: 3.5em;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            animation: fadeInUp 1s ease;
        }
        
        .hero-subtitle {
            font-size: 1.4em;
            margin-bottom: 30px;
            opacity: 0.95;
            animation: fadeInUp 1s ease 0.2s both;
        }
        
        .hero-description {
            font-size: 1.1em;
            margin-bottom: 40px;
            line-height: 1.8;
            opacity: 0.9;
            animation: fadeInUp 1s ease 0.4s both;
        }
        
        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease 0.6s both;
        }
        
        .hero-buttons .btn {
            padding: 15px 30px;
            font-size: 1.1em;
            border-radius: 50px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .hero-buttons .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }
        
        .btn-hero-primary {
            background: white;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .btn-hero-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid white;
            backdrop-filter: blur(10px);
        }
        
        .btn-hero-register {
            background: #ff6b35;
            color: white;
            font-weight: 600;
        }
        
        .features-section {
            padding: 80px 0;
            background: var(--bg-white);
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5em;
            color: var(--text-primary);
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .section-subtitle {
            text-align: center;
            font-size: 1.2em;
            color: var(--text-muted);
            margin-bottom: 60px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .feature-card {
            background: var(--bg-white);
            padding: 40px 30px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            border-color: var(--primary-color);
        }
        
        .feature-icon {
            font-size: 3em;
            margin-bottom: 20px;
            display: block;
        }
        
        .feature-title {
            font-size: 1.4em;
            color: var(--text-primary);
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .feature-description {
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        /* Seção de Cadastro */
        .registration-section {
            padding: 80px 0;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }
        
        .registration-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .registration-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 40px;
        }
        
        .registration-form-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        
        .payment-preview-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 2px solid #e9ecef;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section-title {
            font-size: 1.3em;
            color: var(--text-primary);
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group-full {
            grid-column: 1 / -1;
        }
        
        .payment-plans {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .payment-plan {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .payment-plan:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .payment-plan.selected {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.1);
        }
        
        .payment-plan input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .plan-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        
        .plan-price {
            font-size: 1.5em;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .plan-description {
            font-size: 0.9em;
            color: var(--text-muted);
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .payment-method {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .payment-method:hover {
            border-color: var(--primary-color);
        }
        
        .payment-method.selected {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.1);
        }
        
        .payment-method input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .payment-icon {
            font-size: 1.5em;
            margin-bottom: 5px;
            display: block;
        }
        
        .payment-name {
            font-size: 0.9em;
            font-weight: 600;
        }
        
        .payment-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .summary-total {
            border-top: 2px solid #dee2e6;
            padding-top: 15px;
            margin-top: 15px;
            font-weight: 700;
            font-size: 1.2em;
        }
        
        .payment-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
        }
        
        .payment-notice .icon {
            font-size: 1.5em;
            margin-bottom: 10px;
            display: block;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }
        
        .alert-success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .stats-section {
            padding: 80px 0;
            background: linear-gradient(135deg, var(--bg-primary), var(--bg-secondary));
        }
        
        .stats-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            margin-top: 40px;
        }
        
        .stat-item {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-item:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 3em;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
            display: block;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9em;
        }
        
        .instruments-section {
            padding: 80px 0;
            background: var(--bg-white);
        }
        
        .instruments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 30px;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .instrument-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px 20px;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .instrument-card:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .instrument-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
            display: block;
        }
        
        .instrument-name {
            font-weight: 600;
            font-size: 1.1em;
        }
        
        .cta-section {
            padding: 80px 0;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            text-align: center;
        }
        
        .cta-content {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .cta-title {
            font-size: 2.5em;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .cta-description {
            font-size: 1.2em;
            margin-bottom: 40px;
            opacity: 0.95;
            line-height: 1.6;
        }
        
        .footer {
            background: var(--text-primary);
            color: white;
            padding: 40px 0;
            text-align: center;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .footer-links a {
            color: white;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-links a:hover {
            color: var(--primary-color);
        }
        
        /* Animações */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .floating {
            animation: float 3s ease-in-out infinite;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5em;
            }
            
            .hero-subtitle {
                font-size: 1.2em;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .hero-buttons .btn {
                width: 100%;
                max-width: 300px;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .registration-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .payment-plans {
                grid-template-columns: 1fr;
            }
            
            .payment-methods {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            
            .instruments-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .instruments-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">🎵 Escola de Música Forjados Music Studio</h1>
            <p class="hero-subtitle">Transformando vidas através da música</p>
            <p class="hero-description">
                Sistema completo de gestão escolar para instituições de ensino musical. 
                Gerencie alunos, turmas, chamadas, financeiro e muito mais de forma simples e eficiente.
            </p>
            <div class="hero-buttons">
                <a href="login.php" class="btn btn-hero-primary">
                    🚀 Acessar Sistema
                </a>
                <a href="#cadastro" class="btn btn-hero-register">
                    📝 Matricule-se Agora
                </a>
                <a href="#features" class="btn btn-hero-secondary">
                    📖 Saiba Mais
                </a>
            </div>
        </div>
    </section>

    <!-- Registration Section -->
    <section id="cadastro" class="registration-section">
        <div class="registration-container">
            <h2 class="section-title">📝 Faça sua Matrícula</h2>
            <p class="section-subtitle">
                Preencha seus dados e escolha seu plano de pagamento para começar suas aulas
            </p>
            
            <?php if ($mensagem): ?>
                <div class="alert alert-<?= $tipo_mensagem ?>">
                    <?= $mensagem ?>
                </div>
            <?php endif; ?>
            
            <div class="registration-grid">
                <!-- Formulário de Cadastro -->
                <div class="registration-form-card">
                    <form method="POST" action="" id="registrationForm">
                        <input type="hidden" name="acao" value="cadastrar_aluno">
                        
                        <!-- Dados Pessoais -->
                        <div class="form-section">
                            <h3 class="form-section-title">
                                <span>👤</span> Dados Pessoais
                            </h3>
                            
                            <div class="form-group">
                                <label for="nome">Nome Completo *</label>
                                <input type="text" id="nome" name="nome" class="form-control" required 
                                       value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email">Email *</label>
                                    <input type="email" id="email" name="email" class="form-control" required
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="telefone">Telefone *</label>
                                    <input type="tel" id="telefone" name="telefone" class="form-control" required
                                           value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="data_nascimento">Data de Nascimento</label>
                                    <input type="date" id="data_nascimento" name="data_nascimento" class="form-control"
                                           value="<?= htmlspecialchars($_POST['data_nascimento'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="instrumento_id">Instrumento Desejado *</label>
                                    <select id="instrumento_id" name="instrumento_id" class="form-control" required>
                                        <option value="">Selecione um instrumento</option>
                                        <?php foreach ($instrumentos as $instrumento): ?>
                                            <option value="<?= $instrumento['id'] ?>" 
                                                    <?= ($_POST['instrumento_id'] ?? '') == $instrumento['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($instrumento['icone'] . ' ' . $instrumento['nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="endereco">Endereço</label>
                                <input type="text" id="endereco" name="endereco" class="form-control"
                                       value="<?= htmlspecialchars($_POST['endereco'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="nivel">Nível de Experiência</label>
                                <select id="nivel" name="nivel" class="form-control">
                                    <option value="">Selecione seu nível</option>
                                    <option value="Iniciante" <?= ($_POST['nivel'] ?? '') === 'Iniciante' ? 'selected' : '' ?>>Iniciante</option>
                                    <option value="Básico" <?= ($_POST['nivel'] ?? '') === 'Básico' ? 'selected' : '' ?>>Básico</option>
                                    <option value="Intermediário" <?= ($_POST['nivel'] ?? '') === 'Intermediário' ? 'selected' : '' ?>>Intermediário</option>
                                    <option value="Avançado" <?= ($_POST['nivel'] ?? '') === 'Avançado' ? 'selected' : '' ?>>Avançado</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Responsável (se menor de idade) -->
                        <div class="form-section">
                            <h3 class="form-section-title">
                                <span>👨‍👩‍👧‍👦</span> Responsável (se menor de idade)
                            </h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="responsavel_nome">Nome do Responsável</label>
                                    <input type="text" id="responsavel_nome" name="responsavel_nome" class="form-control"
                                           value="<?= htmlspecialchars($_POST['responsavel_nome'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="responsavel_telefone">Telefone do Responsável</label>
                                    <input type="tel" id="responsavel_telefone" name="responsavel_telefone" class="form-control"
                                           value="<?= htmlspecialchars($_POST['responsavel_telefone'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Observações -->
                        <div class="form-section">
                            <h3 class="form-section-title">
                                <span>📝</span> Observações
                            </h3>
                            
                            <div class="form-group">
                                <label for="observacoes">Observações Adicionais</label>
                                <textarea id="observacoes" name="observacoes" class="form-control" rows="3" 
                                          placeholder="Conte-nos sobre seus objetivos musicais, experiências anteriores, etc."><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-full">
                            🎯 Finalizar Matrícula
                        </button>
                    </form>
                </div>
                
                <!-- Preview de Pagamento -->
                <div class="payment-preview-card">
                    <h3 class="form-section-title">
                        <span>💳</span> Planos e Pagamento
                    </h3>
                    
                    <!-- Planos de Pagamento -->
                    <div class="form-section">
                        <h4 style="margin-bottom: 15px; color: var(--text-secondary);">Escolha seu plano:</h4>
                        <div class="payment-plans">
                            <label class="payment-plan" for="mensal">
                                <input type="radio" id="mensal" name="plano_pagamento" value="mensal" checked>
                                <div class="plan-name">Mensal</div>
                                <div class="plan-price">R$ 150</div>
                                <div class="plan-description">Por mês</div>
                            </label>
                            
                            <label class="payment-plan" for="trimestral">
                                <input type="radio" id="trimestral" name="plano_pagamento" value="trimestral">
                                <div class="plan-name">Trimestral</div>
                                <div class="plan-price">R$ 400</div>
                                <div class="plan-description">3 meses<br><small>Economize R$ 50</small></div>
                            </label>
                            
                            <label class="payment-plan" for="semestral">
                                <input type="radio" id="semestral" name="plano_pagamento" value="semestral">
                                <div class="plan-name">Semestral</div>
                                <div class="plan-price">R$ 750</div>
                                <div class="plan-description">6 meses<br><small>Economize R$ 150</small></div>
                            </label>
                            
                            <label class="payment-plan" for="anual">
                                <input type="radio" id="anual" name="plano_pagamento" value="anual">
                                <div class="plan-name">Anual</div>
                                <div class="plan-price">R$ 1.400</div>
                                <div class="plan-description">12 meses<br><small>Economize R$ 400</small></div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Formas de Pagamento -->
                    <div class="form-section">
                        <h4 style="margin-bottom: 15px; color: var(--text-secondary);">Forma de pagamento preferida:</h4>
                        <div class="payment-methods">
                            <label class="payment-method" for="cartao">
                                <input type="radio" id="cartao" name="forma_pagamento" value="cartao" checked>
                                <span class="payment-icon">💳</span>
                                <div class="payment-name">Cartão</div>
                            </label>
                            
                            <label class="payment-method" for="pix">
                                <input type="radio" id="pix" name="forma_pagamento" value="pix">
                                <span class="payment-icon">📱</span>
                                <div class="payment-name">PIX</div>
                            </label>
                            
                            <label class="payment-method" for="boleto">
                                <input type="radio" id="boleto" name="forma_pagamento" value="boleto">
                                <span class="payment-icon">🧾</span>
                                <div class="payment-name">Boleto</div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Resumo do Pagamento -->
                    <div class="payment-summary">
                        <h4 style="margin-bottom: 15px; color: var(--text-primary);">Resumo do Pagamento</h4>
                        
                        <div class="summary-row">
                            <span>Taxa de matrícula:</span>
                            <span id="valor-matricula">R$ 150,00</span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Plano selecionado:</span>
                            <span id="plano-selecionado">Mensal</span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Forma de pagamento:</span>
                            <span id="forma-selecionada">Cartão de Crédito</span>
                        </div>
                        
                        <div class="summary-row summary-total">
                            <span>Total a pagar:</span>
                            <span id="total-pagar">R$ 150,00</span>
                        </div>
                    </div>
                    
                    <!-- Aviso sobre Pagamento -->
                    <div class="payment-notice">
                        <span class="icon">⚠️</span>
                        <strong>Importante:</strong><br>
                        O pagamento será processado após a confirmação da matrícula. 
                        Você receberá as instruções de pagamento por email.
                        <br><br>
                        <small style="color: #666;">
                            * Esta é apenas uma demonstração visual do sistema de pagamento.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <h2 class="section-title">Funcionalidades Principais</h2>
            <p class="section-subtitle">
                Tudo que você precisa para gerenciar sua escola de música de forma profissional
            </p>
            
            <div class="features-grid">
                <div class="feature-card floating">
                    <span class="feature-icon">👥</span>
                    <h3 class="feature-title">Gestão de Alunos</h3>
                    <p class="feature-description">
                        Cadastro completo de alunos com informações pessoais, responsáveis, 
                        status de matrícula e histórico acadêmico.
                    </p>
                </div>
                
                <div class="feature-card floating" style="animation-delay: 0.2s;">
                    <span class="feature-icon">📋</span>
                    <h3 class="feature-title">Sistema de Chamadas</h3>
                    <p class="feature-description">
                        Controle de presença digital com histórico completo, 
                        relatórios de frequência e notificações automáticas.
                    </p>
                </div>
                
                <div class="feature-card floating" style="animation-delay: 0.4s;">
                    <span class="feature-icon">💰</span>
                    <h3 class="feature-title">Controle Financeiro</h3>
                    <p class="feature-description">
                        Gestão de mensalidades, controle de pagamentos, 
                        relatórios financeiros e alertas de inadimplência.
                    </p>
                </div>
                
                <div class="feature-card floating" style="animation-delay: 0.6s;">
                    <span class="feature-icon">📚</span>
                    <h3 class="feature-title">Gestão de Turmas</h3>
                    <p class="feature-description">
                        Organização de turmas por instrumento, nível e horário. 
                        Controle de vagas e distribuição de alunos.
                    </p>
                </div>
                
                <div class="feature-card floating" style="animation-delay: 0.8s;">
                    <span class="feature-icon">📊</span>
                    <h3 class="feature-title">Relatórios Detalhados</h3>
                    <p class="feature-description">
                        Relatórios completos de frequência, desempenho financeiro, 
                        estatísticas de turmas e muito mais.
                    </p>
                </div>
                
                <div class="feature-card floating" style="animation-delay: 1s;">
                    <span class="feature-icon">🔐</span>
                    <h3 class="feature-title">Acesso Seguro</h3>
                    <p class="feature-description">
                        Sistema de login seguro com diferentes níveis de acesso 
                        para administradores, professores e alunos.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="stats-container">
            <h2 class="section-title">Nossa Escola em Números</h2>
            <p class="section-subtitle">
                Dados atualizados do nosso sistema de gestão
            </p>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?= $total_alunos ?></span>
                    <span class="stat-label">Alunos Ativos</span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-number"><?= $total_turmas ?></span>
                    <span class="stat-label">Turmas Ativas</span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-number"><?= $total_professores ?></span>
                    <span class="stat-label">Professores</span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-number"><?= count($instrumentos) ?></span>
                    <span class="stat-label">Instrumentos</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Instruments Section -->
    <?php if (!empty($instrumentos)): ?>
    <section class="instruments-section">
        <div class="container">
            <h2 class="section-title">Instrumentos Disponíveis</h2>
            <p class="section-subtitle">
                Conheça os instrumentos que oferecemos em nossa escola
            </p>
            
            <div class="instruments-grid">
                <?php foreach ($instrumentos as $instrumento): ?>
                <div class="instrument-card">
                    <span class="instrument-icon"><?= htmlspecialchars($instrumento['icone']) ?></span>
                    <div class="instrument-name"><?= htmlspecialchars($instrumento['nome']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="cta-content">
            <h2 class="cta-title">Pronto para Começar?</h2>
            <p class="cta-description">
                Acesse nosso sistema de gestão e descubra como é fácil 
                gerenciar sua escola de música de forma profissional.
            </p>
            <a href="login.php" class="btn btn-hero-primary">
                🎯 Fazer Login Agora
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="login.php">Login</a>
                <a href="#features">Funcionalidades</a>
                <a href="#cadastro">Matrícula</a>
                <a href="mailto:contato@escolaharmonia.com">Contato</a>
                <a href="tel:(11)99999-0000">Telefone</a>
            </div>
            <p>&copy; <?= date('Y') ?> Escola de Música Harmonia. Todos os direitos reservados.</p>
            <p style="margin-top: 10px; opacity: 0.7; font-size: 0.9em;">
                Sistema desenvolvido para gestão educacional musical
            </p>
        </div>
    </footer>

    <script>
        // Smooth scroll para links internos
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Animação de contadores
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            
            counters.forEach(counter => {
                const target = parseInt(counter.textContent);
                const increment = target / 50;
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = target;
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current);
                    }
                }, 30);
            });
        }

        // Gerenciar seleção de planos de pagamento
        document.querySelectorAll('input[name="plano_pagamento"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Remover seleção anterior
                document.querySelectorAll('.payment-plan').forEach(plan => {
                    plan.classList.remove('selected');
                });
                
                // Adicionar seleção atual
                this.closest('.payment-plan').classList.add('selected');
                
                // Atualizar resumo
                const valores = {
                    'mensal': { valor: 150, nome: 'Mensal' },
                    'trimestral': { valor: 400, nome: 'Trimestral' },
                    'semestral': { valor: 750, nome: 'Semestral' },
                    'anual': { valor: 1400, nome: 'Anual' }
                };
                
                const plano = valores[this.value];
                document.getElementById('valor-matricula').textContent = `R$ ${plano.valor.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
                document.getElementById('plano-selecionado').textContent = plano.nome;
                document.getElementById('total-pagar').textContent = `R$ ${plano.valor.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
            });
        });

        // Gerenciar seleção de formas de pagamento
        document.querySelectorAll('input[name="forma_pagamento"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Remover seleção anterior
                document.querySelectorAll('.payment-method').forEach(method => {
                    method.classList.remove('selected');
                });
                
                // Adicionar seleção atual
                this.closest('.payment-method').classList.add('selected');
                
                // Atualizar resumo
                const formas = {
                    'cartao': 'Cartão de Crédito',
                    'pix': 'PIX',
                    'boleto': 'Boleto Bancário'
                };
                
                document.getElementById('forma-selecionada').textContent = formas[this.value];
            });
        });

        // Inicializar seleções padrão
        document.addEventListener('DOMContentLoaded', function() {
            // Selecionar plano mensal por padrão
            document.querySelector('input[name="plano_pagamento"][value="mensal"]').closest('.payment-plan').classList.add('selected');
            
            // Selecionar cartão por padrão
            document.querySelector('input[name="forma_pagamento"][value="cartao"]').closest('.payment-method').classList.add('selected');
        });

        // Intersection Observer para animações
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    
                    // Animar contadores quando a seção de stats aparecer
                    if (entry.target.classList.contains('stats-section')) {
                        animateCounters();
                    }
                }
            });
        }, observerOptions);

        // Observar elementos para animação
        document.addEventListener('DOMContentLoaded', function() {
            const animatedElements = document.querySelectorAll('.feature-card, .stat-item, .instrument-card');
            
            animatedElements.forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                el.style.transition = 'all 0.6s ease';
                observer.observe(el);
            });

            // Observar seção de stats
            const statsSection = document.querySelector('.stats-section');
            if (statsSection) {
                observer.observe(statsSection);
            }
        });

        // Efeito parallax suave no hero
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero-section');
            if (hero) {
                hero.style.transform = `translateY(${scrolled * 0.5}px)`;
            }
        });

        // Adicionar efeito de hover nos cards de instrumento
        document.querySelectorAll('.instrument-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.05) rotate(2deg)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1) rotate(0deg)';
            });
        });

        // Validação do formulário
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const nome = document.getElementById('nome').value.trim();
            const email = document.getElementById('email').value.trim();
            const telefone = document.getElementById('telefone').value.trim();
            const instrumento = document.getElementById('instrumento_id').value;
            
            if (!nome || !email || !telefone || !instrumento) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos obrigatórios marcados com *');
                return false;
            }
            
            // Confirmar envio
            if (!confirm('Confirma o envio da matrícula? Você receberá as instruções de pagamento por email.')) {
                e.preventDefault();
                return false;
            }
        });

        // Preloader simples
        window.addEventListener('load', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.5s ease';
            
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);
        });
    </script>
</body>
</html>
