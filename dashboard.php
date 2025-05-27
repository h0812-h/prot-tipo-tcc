<?php
require_once 'config/database.php';
require_once 'config/auth.php';

verificarLogin();

$db = getDB();
$usuario = getUsuarioLogado();

// Estatísticas para administrador
if ($usuario['tipo'] === 'administrador') {
    // Total de alunos
    $stmt = $db->query("SELECT COUNT(*) as total FROM alunos WHERE status != 'Inativo'");
    $total_alunos = $stmt->fetch()['total'];
    
    // Alunos matriculados
    $stmt = $db->query("SELECT COUNT(*) as total FROM alunos WHERE status = 'Matriculado'");
    $alunos_matriculados = $stmt->fetch()['total'];
    
    // Mensalidades pendentes
    $stmt = $db->query("SELECT COUNT(*) as total FROM mensalidades WHERE status IN ('Pendente', 'Atrasado')");
    $mensalidades_pendentes = $stmt->fetch()['total'];
    
    // Turmas ativas
    $stmt = $db->query("SELECT COUNT(*) as total FROM turmas WHERE ativa = 1");
    $turmas_ativas = $stmt->fetch()['total'];
    
    // Chamadas hoje
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM chamadas WHERE DATE(data_aula) = CURDATE()");
    $stmt->execute();
    $chamadas_hoje = $stmt->fetch()['total'];
    
    // Últimas atividades
    $stmt = $db->query("
        SELECT 'chamada' as tipo, t.nome as descricao, c.data_aula as data, p.nome as professor
        FROM chamadas c 
        JOIN turmas t ON c.turma_id = t.id 
        JOIN professores p ON c.professor_id = p.id
        ORDER BY c.data_criacao DESC 
        LIMIT 5
    ");
    $atividades = $stmt->fetchAll();
    
} elseif ($usuario['tipo'] === 'professor') {
    // Estatísticas para professor
    
    // Buscar dados do professor
    $stmt = $db->prepare("SELECT * FROM professores WHERE email = ?");
    $stmt->execute([$usuario['email']]);
    $dados_professor = $stmt->fetch();
    
    if ($dados_professor) {
        // Turmas do professor
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM turmas WHERE professor_id = ? AND ativa = 1");
        $stmt->execute([$dados_professor['id']]);
        $minhas_turmas = $stmt->fetch()['total'];
        
        // Alunos do professor
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT a.id) as total 
            FROM alunos a
            JOIN matriculas m ON a.id = m.aluno_id
            JOIN turmas t ON m.turma_id = t.id
            WHERE t.professor_id = ? AND m.status = 'Ativa' AND a.status = 'Matriculado'
        ");
        $stmt->execute([$dados_professor['id']]);
        $meus_alunos = $stmt->fetch()['total'];
        
        // Chamadas hoje
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM chamadas c
            JOIN turmas t ON c.turma_id = t.id
            WHERE t.professor_id = ? AND DATE(c.data_aula) = CURDATE()
        ");
        $stmt->execute([$dados_professor['id']]);
        $chamadas_hoje = $stmt->fetch()['total'];
        
        // Próximas aulas do professor
        $stmt = $db->prepare("
            SELECT t.nome, t.dia_semana, t.horario_inicio, t.horario_fim, i.icone,
                   COUNT(m.id) as total_alunos
            FROM turmas t
            JOIN instrumentos i ON t.instrumento_id = i.id
            LEFT JOIN matriculas m ON t.id = m.turma_id AND m.status = 'Ativa'
            WHERE t.professor_id = ? AND t.ativa = 1
            GROUP BY t.id
            ORDER BY 
                CASE t.dia_semana
                    WHEN 'Segunda' THEN 1
                    WHEN 'Terça' THEN 2
                    WHEN 'Quarta' THEN 3
                    WHEN 'Quinta' THEN 4
                    WHEN 'Sexta' THEN 5
                    WHEN 'Sábado' THEN 6
                    WHEN 'Domingo' THEN 7
                END
        ");
        $stmt->execute([$dados_professor['id']]);
        $proximas_aulas = $stmt->fetchAll();
    } else {
        // Professor não encontrado na tabela professores
        $minhas_turmas = 0;
        $meus_alunos = 0;
        $chamadas_hoje = 0;
        $proximas_aulas = [];
    }
    
} else {
    // Dados do aluno
    $stmt = $db->prepare("
        SELECT a.*, 
               COUNT(DISTINCT m.id) as total_matriculas,
               COUNT(DISTINCT CASE WHEN men.status = 'Pendente' THEN men.id END) as mensalidades_pendentes
        FROM alunos a
        LEFT JOIN matriculas m ON a.id = m.aluno_id AND m.status = 'Ativa'
        LEFT JOIN mensalidades men ON m.id = men.matricula_id AND men.status IN ('Pendente', 'Atrasado')
        WHERE a.usuario_id = ?
        GROUP BY a.id
    ");
    $stmt->execute([$usuario['id']]);
    $dados_aluno = $stmt->fetch();
    
    // Verificar se o aluno existe na tabela alunos
    if ($dados_aluno) {
        // Presenças e faltas do aluno
        $stmt = $db->prepare("
            SELECT 
                COUNT(CASE WHEN p.presente = 1 THEN 1 END) as presencas,
                COUNT(CASE WHEN p.presente = 0 THEN 1 END) as faltas
            FROM presencas p
            JOIN chamadas c ON p.chamada_id = c.id
            WHERE p.aluno_id = ? AND c.data_aula >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$dados_aluno['id']]);
        $frequencia = $stmt->fetch();
        
        // Próximas aulas
        $stmt = $db->prepare("
            SELECT t.nome, t.dia_semana, t.horario_inicio, t.horario_fim, p.nome as professor, i.icone
            FROM matriculas m
            JOIN turmas t ON m.turma_id = t.id
            JOIN professores p ON t.professor_id = p.id
            JOIN instrumentos i ON t.instrumento_id = i.id
            WHERE m.aluno_id = ? AND m.status = 'Ativa' AND t.ativa = 1
            ORDER BY 
                CASE t.dia_semana
                    WHEN 'Segunda' THEN 1
                    WHEN 'Terça' THEN 2
                    WHEN 'Quarta' THEN 3
                    WHEN 'Quinta' THEN 4
                    WHEN 'Sexta' THEN 5
                    WHEN 'Sábado' THEN 6
                    WHEN 'Domingo' THEN 7
                END
        ");
        $stmt->execute([$dados_aluno['id']]);
        $proximas_aulas = $stmt->fetchAll();
    } else {
        // Aluno não encontrado na tabela alunos - criar dados padrão
        $dados_aluno = [
            'id' => null,
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'telefone' => null,
            'status' => 'Cadastrado',
            'data_matricula' => null,
            'total_matriculas' => 0,
            'mensalidades_pendentes' => 0
        ];
        
        $frequencia = [
            'presencas' => 0,
            'faltas' => 0
        ];
        
        $proximas_aulas = [];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Escola de Música Harmonia</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>🎵 Escola Harmonia</h1>
                <p class="subtitle">Bem-vindo, <?= htmlspecialchars($usuario['nome']) ?>!</p>
            </div>
            <div class="header-actions">
                <?php if ($usuario['tipo'] === 'administrador'): ?>
                    <a href="admin/alunos.php" class="btn btn-primary">👥 Gerenciar Alunos</a>
                    <a href="admin/chamadas.php" class="btn btn-info">📋 Chamadas</a>
                <?php elseif ($usuario['tipo'] === 'professor'): ?>
                    <a href="admin/alunos.php" class="btn btn-primary">👥 Meus Alunos</a>
                    <a href="admin/chamadas.php" class="btn btn-info">📋 Chamadas</a>
                <?php endif; ?>
                <a href="perfil.php" class="btn btn-secondary">👤 Perfil</a>
                <a href="logout.php" class="btn btn-danger">🚪 Sair</a>
            </div>
        </div>

        <!-- Navegação -->
        <nav class="navbar">
            <ul>
                <li><a href="dashboard.php" class="active">🏠 Dashboard</a></li>
                <?php if ($usuario['tipo'] === 'administrador'): ?>
                    <li><a href="admin/alunos.php">👥 Alunos</a></li>
                    <li><a href="admin/turmas.php">📚 Turmas</a></li>
                    <li><a href="admin/chamadas.php">📋 Chamadas</a></li>
                    <li><a href="admin/financeiro.php">💰 Financeiro</a></li>
                    <li><a href="admin/relatorios.php">📊 Relatórios</a></li>
                <?php elseif ($usuario['tipo'] === 'professor'): ?>
                    <li><a href="admin/alunos.php">👥 Meus Alunos</a></li>
                    <li><a href="admin/chamadas.php">📋 Chamadas</a></li>
                    <li><a href="professor/turmas.php">📚 Minhas Turmas</a></li>
                <?php else: ?>
                    <li><a href="aluno/aulas.php">📚 Minhas Aulas</a></li>
                    <li><a href="aluno/frequencia.php">📊 Frequência</a></li>
                    <li><a href="aluno/financeiro.php">💰 Financeiro</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <?php if ($usuario['tipo'] === 'administrador'): ?>
            <!-- Dashboard do Administrador -->
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="number"><?= $total_alunos ?></span>
                    <span class="label">👥 Total de Alunos</span>
                </div>
                <div class="stat-card success">
                    <span class="number"><?= $alunos_matriculados ?></span>
                    <span class="label">✅ Matriculados</span>
                </div>
                <div class="stat-card warning">
                    <span class="number"><?= $mensalidades_pendentes ?></span>
                    <span class="label">⏰ Mensalidades Pendentes</span>
                </div>
                <div class="stat-card">
                    <span class="number"><?= $turmas_ativas ?></span>
                    <span class="label">📚 Turmas Ativas</span>
                </div>
                <div class="stat-card info">
                    <span class="number"><?= $chamadas_hoje ?></span>
                    <span class="label">📋 Chamadas Hoje</span>
                </div>
            </div>

            <div class="grid grid-2">
                <!-- Ações Rápidas -->
                <div class="card">
                    <div class="card-header">
                        <h3>🚀 Ações Rápidas</h3>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-2 gap-2">
                            <a href="admin/alunos.php?acao=novo" class="btn btn-primary">
                                ➕ Novo Aluno
                            </a>
                            <a href="admin/chamadas.php" class="btn btn-info">
                                📋 Fazer Chamada
                            </a>
                            <a href="admin/turmas.php?acao=nova" class="btn btn-success">
                                📚 Nova Turma
                            </a>
                            <a href="admin/relatorios.php" class="btn btn-warning">
                                📊 Relatórios
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Últimas Atividades -->
                <div class="card">
                    <div class="card-header">
                        <h3>📈 Últimas Atividades</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($atividades)): ?>
                            <p class="text-center" style="color: var(--text-muted); padding: 20px;">
                                Nenhuma atividade recente encontrada.
                            </p>
                        <?php else: ?>
                            <?php foreach ($atividades as $atividade): ?>
                                <div style="padding: 10px; border-left: 3px solid var(--primary-color); margin-bottom: 10px; background: var(--bg-primary); border-radius: 5px;">
                                    <strong>📋 Chamada realizada</strong><br>
                                    <span style="color: var(--text-secondary);">
                                        <?= htmlspecialchars($atividade['descricao']) ?> - 
                                        Prof. <?= htmlspecialchars($atividade['professor']) ?>
                                    </span><br>
                                    <small style="color: var(--text-muted);">
                                        <?= date('d/m/Y', strtotime($atividade['data'])) ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($usuario['tipo'] === 'professor'): ?>
            <!-- Dashboard do Professor -->
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="number"><?= $minhas_turmas ?></span>
                    <span class="label">📚 Minhas Turmas</span>
                </div>
                <div class="stat-card success">
                    <span class="number"><?= $meus_alunos ?></span>
                    <span class="label">👥 Meus Alunos</span>
                </div>
                <div class="stat-card info">
                    <span class="number"><?= $chamadas_hoje ?></span>
                    <span class="label">📋 Chamadas Hoje</span>
                </div>
                <div class="stat-card warning">
                    <span class="number"><?= count($proximas_aulas) ?></span>
                    <span class="label">📅 Aulas Programadas</span>
                </div>
            </div>

            <div class="grid grid-2">
                <!-- Ações Rápidas do Professor -->
                <div class="card">
                    <div class="card-header">
                        <h3>🚀 Ações Rápidas</h3>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-2 gap-2">
                            <a href="admin/chamadas.php" class="btn btn-primary">
                                📋 Fazer Chamada
                            </a>
                            <a href="admin/alunos.php" class="btn btn-info">
                                👥 Ver Meus Alunos
                            </a>
                            <a href="professor/frequencia.php" class="btn btn-success">
                                📊 Relatório de Frequência
                            </a>
                            <a href="professor/turmas.php" class="btn btn-warning">
                                📚 Minhas Turmas
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Minhas Aulas -->
                <div class="card">
                    <div class="card-header">
                        <h3>📅 Minhas Aulas</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($proximas_aulas)): ?>
                            <p class="text-center" style="color: var(--text-muted); padding: 20px;">
                                Você não possui turmas ativas no momento.
                            </p>
                        <?php else: ?>
                            <?php foreach ($proximas_aulas as $aula): ?>
                                <div style="padding: 15px; border: 2px solid var(--border-color); margin-bottom: 10px; border-radius: 8px; background: var(--bg-primary);">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                        <span style="font-size: 1.5em;"><?= $aula['icone'] ?></span>
                                        <strong style="color: var(--text-primary);"><?= htmlspecialchars($aula['nome']) ?></strong>
                                    </div>
                                    <div style="color: var(--text-secondary); font-size: 0.9em;">
                                        📅 <?= htmlspecialchars($aula['dia_semana']) ?>s - 
                                        🕐 <?= date('H:i', strtotime($aula['horario_inicio'])) ?> às <?= date('H:i', strtotime($aula['horario_fim'])) ?><br>
                                        👥 <?= $aula['total_alunos'] ?> aluno(s) matriculado(s)
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Dashboard do Aluno -->
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="number"><?= $dados_aluno['total_matriculas'] ?? 0 ?></span>
                    <span class="label">📚 Turmas Matriculadas</span>
                </div>
                <div class="stat-card success">
                    <span class="number"><?= $frequencia['presencas'] ?? 0 ?></span>
                    <span class="label">✅ Presenças (30 dias)</span>
                </div>
                <div class="stat-card danger">
                    <span class="number"><?= $frequencia['faltas'] ?? 0 ?></span>
                    <span class="label">❌ Faltas (30 dias)</span>
                </div>
                <div class="stat-card warning">
                    <span class="number"><?= $dados_aluno['mensalidades_pendentes'] ?? 0 ?></span>
                    <span class="label">⏰ Mensalidades Pendentes</span>
                </div>
            </div>

            <div class="grid grid-2">
                <!-- Status do Aluno -->
                <div class="card">
                    <div class="card-header">
                        <h3>👤 Meu Status</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($dados_aluno['id']): ?>
                            <div style="display: grid; gap: 15px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong>Status da Matrícula:</strong>
                                    <span class="badge badge-<?= $dados_aluno['status'] === 'Matriculado' ? 'success' : 'warning' ?>">
                                        <?= htmlspecialchars($dados_aluno['status']) ?>
                                    </span>
                                </div>
                                
                                <?php if ($dados_aluno['data_matricula']): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong>Data de Matrícula:</strong>
                                    <span><?= date('d/m/Y', strtotime($dados_aluno['data_matricula'])) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong>Email:</strong>
                                    <span><?= htmlspecialchars($dados_aluno['email']) ?></span>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong>Telefone:</strong>
                                    <span><?= htmlspecialchars($dados_aluno['telefone'] ?? 'Não informado') ?></span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 20px; color: var(--text-muted);">
                                <div style="font-size: 3em; margin-bottom: 15px;">📝</div>
                                <h4>Cadastro Incompleto</h4>
                                <p>Seu cadastro como aluno ainda não foi finalizado. Entre em contato com a secretaria para completar sua matrícula.</p>
                                <div style="margin-top: 15px;">
                                    <strong>Email:</strong> <?= htmlspecialchars($usuario['email']) ?><br>
                                    <strong>Status:</strong> <span class="badge badge-warning">Aguardando Cadastro</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Próximas Aulas -->
                <div class="card">
                    <div class="card-header">
                        <h3>📅 Minhas Aulas</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($proximas_aulas)): ?>
                            <p class="text-center" style="color: var(--text-muted); padding: 20px;">
                                <?= $dados_aluno['id'] ? 'Você não está matriculado em nenhuma turma.' : 'Complete seu cadastro para ver suas aulas.' ?>
                            </p>
                        <?php else: ?>
                            <?php foreach ($proximas_aulas as $aula): ?>
                                <div style="padding: 15px; border: 2px solid var(--border-color); margin-bottom: 10px; border-radius: 8px; background: var(--bg-primary);">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                        <span style="font-size: 1.5em;"><?= $aula['icone'] ?></span>
                                        <strong style="color: var(--text-primary);"><?= htmlspecialchars($aula['nome']) ?></strong>
                                    </div>
                                    <div style="color: var(--text-secondary); font-size: 0.9em;">
                                        📅 <?= htmlspecialchars($aula['dia_semana']) ?>s - 
                                        🕐 <?= date('H:i', strtotime($aula['horario_inicio'])) ?> às <?= date('H:i', strtotime($aula['horario_fim'])) ?><br>
                                        👨‍🏫 <?= htmlspecialchars($aula['professor']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Animações de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card, .stat-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Atualizar dados a cada 5 minutos
        setInterval(function() {
            location.reload();
        }, 300000);

        // Notificações de boas-vindas
        <?php if ($usuario['tipo'] === 'administrador'): ?>
            if (<?= $mensalidades_pendentes ?> > 0) {
                setTimeout(() => {
                    if (confirm('Existem <?= $mensalidades_pendentes ?> mensalidades pendentes. Deseja verificar agora?')) {
                        window.location.href = 'admin/financeiro.php';
                    }
                }, 2000);
            }
        <?php endif; ?>
    </script>
</body>
</html>
