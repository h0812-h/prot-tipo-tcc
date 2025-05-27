<?php
require_once 'config/database.php';

session_start();

// Se já estiver logado, redirecionar para o dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Buscar algumas estatísticas públicas para mostrar na página inicial
try {
    $db = getDB();
    
    // Total de alunos ativos
    $stmt = $db->query("SELECT COUNT(*) as total FROM alunos WHERE status != 'Inativo'");
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
    <link rel="stylesheet" href="assets/style.css">
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
            background: rgb(18, 54, 153);
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .btn-hero-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid white;
            backdrop-filter: blur(10px);
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
            <h1 class="hero-title">🎵 Escola de Música Harmonia</h1>
            <p class="hero-subtitle">Transformando vidas através da música</p>
            <p class="hero-description">
                Sistema completo de gestão escolar para instituições de ensino musical. 
                Gerencie alunos, turmas, chamadas, financeiro e muito mais de forma simples e eficiente.
            </p>
            <div class="hero-buttons">
                <a href="login.php" class="btn btn-hero-primary">
                    🚀 Acessar Sistema
                </a>
                <a href="#features" class="btn btn-hero-secondary">
                    📖 Saiba Mais
                </a>
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