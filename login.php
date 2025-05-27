<?php
require_once 'config/database.php';

session_start();

// Se já estiver logado, redirecionar
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit();
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
        try {
            $db = getDB();
            
            // Buscar usuário
            $stmt = $db->prepare("
                SELECT u.*, a.id as aluno_id 
                FROM usuarios u 
                LEFT JOIN alunos a ON u.id = a.usuario_id 
                WHERE u.username = ? AND u.ativo = 1
            ");
            $stmt->execute([$username]);
            $usuario = $stmt->fetch();
            
            if ($usuario && password_verify($password, $usuario['password'])) {
                // Login bem-sucedido
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['nome'] = $usuario['nome'];
                $_SESSION['email'] = $usuario['email'];
                $_SESSION['tipo'] = $usuario['tipo'];
                
                if ($usuario['tipo'] === 'aluno' && $usuario['aluno_id']) {
                    $_SESSION['aluno_id'] = $usuario['aluno_id'];
                }
                
                header('Location: dashboard.php');
                exit();
            } else {
                $erro = 'Usuário ou senha incorretos.';
            }
        } catch (PDOException $e) {
            $erro = 'Erro no sistema. Tente novamente.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Escola de Música Harmonia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        
        .login-header {
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: var(--text-primary);
            font-size: 2.5em;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .login-header p {
            color: var(--text-muted);
            font-size: 1.1em;
        }
        
        .login-form {
            text-align: left;
        }
        
        .demo-users {
            background: linear-gradient(135deg, #e6fffa 0%, #f0fff4 100%);
            border: 2px solid #38a169;
            border-radius: 12px;
            padding: 20px;
            margin-top: 25px;
            text-align: left;
        }
        
        .demo-users h4 {
            color: var(--success-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-type-section {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid;
        }
        
        .user-type-section.admin {
            background: rgba(33, 150, 243, 0.1);
            border-left-color: #2196f3;
        }
        
        .user-type-section.professor {
            background: rgba(255, 152, 0, 0.1);
            border-left-color: #ff9800;
        }
        
        .user-type-section.aluno {
            background: rgba(76, 175, 80, 0.1);
            border-left-color: #4caf50;
        }
        
        .user-type-title {
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-type-title.admin { color: #2196f3; }
        .user-type-title.professor { color: #ff9800; }
        .user-type-title.aluno { color: #4caf50; }
        
        .demo-user {
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 6px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .demo-user:last-child {
            margin-bottom: 0;
        }
        
        .demo-user strong {
            color: var(--text-primary);
        }
        
        .demo-user .credentials {
            color: var(--text-muted);
            font-size: 12px;
        }
        
        .access-level {
            font-size: 11px;
            color: #666;
            font-style: italic;
            margin-top: 8px;
            padding: 5px 8px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 4px;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .feature {
            background: rgba(102, 126, 234, 0.1);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border: 2px solid rgba(102, 126, 234, 0.2);
        }
        
        .feature-icon {
            font-size: 1.5em;
            margin-bottom: 5px;
            display: block;
        }
        
        .feature-text {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .quick-login {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .quick-login-btn {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid #ddd;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 12px;
            text-align: center;
        }
        
        .quick-login-btn:hover {
            background: white;
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .quick-login-btn .icon {
            display: block;
            font-size: 1.2em;
            margin-bottom: 4px;
        }
        
        @media (max-width: 480px) {
            .demo-user {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            
            .quick-login {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="login-container fade-in">
        <div class="login-header">
            <h1>🎵 Harmonia</h1>
            <p>Sistema de Gestão Escolar</p>
        </div>
        
        <?php if ($erro): ?>
            <div class="alert alert-danger">
                <span>⚠️</span>
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($sucesso): ?>
            <div class="alert alert-success">
                <span>✅</span>
                <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="username">👤 Usuário</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-control" 
                    placeholder="Digite seu usuário"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    required
                    autocomplete="username"
                >
            </div>
            
            <div class="form-group">
                <label for="password">🔒 Senha</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control" 
                    placeholder="Digite sua senha"
                    required
                    autocomplete="current-password"
                >
            </div>
            
            <button type="submit" class="btn btn-primary w-full">
                🚀 Entrar no Sistema
            </button>
        </form>
        
        <div class="features">
            <div class="feature">
                <span class="feature-icon">📊</span>
                <span class="feature-text">Dashboard</span>
            </div>
            <div class="feature">
                <span class="feature-icon">📋</span>
                <span class="feature-text">Chamadas</span>
            </div>
            <div class="feature">
                <span class="feature-icon">👥</span>
                <span class="feature-text">Alunos</span>
            </div>
            <div class="feature">
                <span class="feature-icon">💰</span>
                <span class="feature-text">Financeiro</span>
            </div>
        </div>
        
        <div class="demo-users">
            <h4>🔑 Usuários de Demonstração</h4>
            
            <!-- Administradores -->
            <div class="user-type-section admin">
                <div class="user-type-title admin">
                    <span>👨‍💼</span>
                    <span>ADMINISTRADORES</span>
                </div>
                <div class="demo-user">
                    <strong>Administrador Geral</strong>
                    <span class="credentials">admin / senha123</span>
                </div>
                <div class="access-level">
                    ✅ Acesso completo: todos os dados, configurações e relatórios
                </div>
            </div>
            
            <!-- Professores -->
            <div class="user-type-section professor">
                <div class="user-type-title professor">
                    <span>👨‍🏫</span>
                    <span>PROFESSORES</span>
                </div>
                <div class="demo-user">
                    <strong>Prof. Carlos (Piano)</strong>
                    <span class="credentials">prof.carlos / senha123</span>
                </div>
                <div class="demo-user">
                    <strong>Prof. Ana (Violão)</strong>
                    <span class="credentials">prof.ana / senha123</span>
                </div>
                <div class="demo-user">
                    <strong>Prof. João (Bateria)</strong>
                    <span class="credentials">prof.joao / senha123</span>
                </div>
                <div class="access-level">
                    📋 Acesso limitado: dados básicos dos alunos + controle de presença das suas turmas
                </div>
            </div>
            
            <!-- Alunos -->
            <div class="user-type-section aluno">
                <div class="user-type-title aluno">
                    <span>👩‍🎓</span>
                    <span>ALUNOS</span>
                </div>
                <div class="demo-user">
                    <strong>Maria Santos</strong>
                    <span class="credentials">maria.santos / senha123</span>
                </div>
                <div class="demo-user">
                    <strong>Pedro Costa</strong>
                    <span class="credentials">pedro.costa / senha123</span>
                </div>
                <div class="demo-user">
                    <strong>Ana Silva</strong>
                    <span class="credentials">ana.silva / senha123</span>
                </div>
                <div class="access-level">
                    👤 Acesso pessoal: apenas seus próprios dados e informações acadêmicas
                </div>
            </div>
            
            <!-- Login Rápido -->
            <div style="margin-top: 20px;">
                <div style="font-weight: bold; margin-bottom: 10px; color: #333;">⚡ Login Rápido:</div>
                <div class="quick-login">
                    <div class="quick-login-btn" onclick="quickLogin('admin', 'senha123')">
                        <span class="icon">👨‍💼</span>
                        <div>Admin</div>
                    </div>
                    <div class="quick-login-btn" onclick="quickLogin('prof.carlos', 'senha123')">
                        <span class="icon">👨‍🏫</span>
                        <div>Professor</div>
                    </div>
                    <div class="quick-login-btn" onclick="quickLogin('maria.santos', 'senha123')">
                        <span class="icon">👩‍🎓</span>
                        <div>Aluna</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Função para login rápido
        function quickLogin(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            
            // Destacar os campos preenchidos
            document.getElementById('username').style.borderColor = 'var(--success-color)';
            document.getElementById('password').style.borderColor = 'var(--success-color)';
            
            // Focar no botão de submit
            document.querySelector('button[type="submit"]').focus();
        }
        
        // Auto-focus no campo de usuário
        document.getElementById('username').focus();
        
        // Animação de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.login-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.6s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
        
        // Validação em tempo real
        const form = document.querySelector('.login-form');
        const inputs = form.querySelectorAll('input');
        
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.style.borderColor = 'var(--success-color)';
                } else {
                    this.style.borderColor = 'var(--border-color)';
                }
            });
        });
        
        // Efeito de loading no botão
        form.addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<span class="loading"></span> Entrando...';
            btn.disabled = true;
            
            // Se houver erro, restaurar o botão
            setTimeout(() => {
                if (document.querySelector('.alert-danger')) {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            }, 2000);
        });
        
        // Animação dos botões de login rápido
        document.querySelectorAll('.quick-login-btn').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px) scale(1.05)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>
