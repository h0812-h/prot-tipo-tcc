<?php
session_start();

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Se desejar destruir a sessão completamente, apague também o cookie de sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir a sessão
session_destroy();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - Escola de Música Harmonia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .logout-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 450px;
            width: 100%;
        }
        
        .logout-icon {
            font-size: 4em;
            margin-bottom: 20px;
            animation: wave 2s ease-in-out infinite;
        }
        
        @keyframes wave {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-10deg); }
            75% { transform: rotate(10deg); }
        }
        
        .logout-title {
            color: var(--text-primary);
            font-size: 2em;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .logout-message {
            color: var(--text-secondary);
            font-size: 1.1em;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .logout-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .countdown {
            background: var(--bg-primary);
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid var(--primary-color);
        }
        
        .countdown-text {
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        
        .countdown-number {
            color: var(--primary-color);
            font-weight: bold;
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <div class="logout-container fade-in">
        <div class="logout-icon">👋</div>
        <h1 class="logout-title">Logout Realizado</h1>
        <p class="logout-message">
            Você foi desconectado com sucesso do sistema da Escola de Música Harmonia. 
            Obrigado por usar nosso sistema!
        </p>
        
        <div class="countdown">
            <div class="countdown-text">
                Redirecionando para a página inicial em 
                <span class="countdown-number" id="countdown">5</span> segundos...
            </div>
        </div>
        
        <div class="logout-actions">
            <a href="index.php" class="btn btn-primary">
                🏠 Página Inicial
            </a>
            <a href="login.php" class="btn btn-secondary">
                🔑 Fazer Login Novamente
            </a>
        </div>
    </div>

    <script>
        // Countdown para redirecionamento
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = 'index.php';
            }
        }, 1000);
        
        // Animação de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.logout-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.6s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>