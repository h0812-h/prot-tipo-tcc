<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se é administrador ou professor
verificarAdminOuProfessor();

$db = getDB();
$usuario = getUsuarioLogado();

// Verificar se é administrador (acesso completo) ou professor (acesso limitado)
$is_admin = ($usuario['tipo'] === 'administrador');
$is_professor = ($usuario['tipo'] === 'professor');

// Filtros
$filtro_nome = $_GET['nome'] ?? '';
$filtro_status = $_GET['status'] ?? '';
$filtro_turma = $_GET['turma'] ?? '';

// Construir query com filtros
$where_conditions = ["a.status != 'Inativo'"];
$params = [];

if (!empty($filtro_nome)) {
    $where_conditions[] = "a.nome LIKE ?";
    $params[] = "%$filtro_nome%";
}

if (!empty($filtro_status)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $filtro_status;
}

if (!empty($filtro_turma)) {
    $where_conditions[] = "t.id = ?";
    $params[] = $filtro_turma;
}

// Se for professor, mostrar apenas alunos das suas turmas
if ($is_professor) {
    $where_conditions[] = "prof.email = ?";
    $params[] = $usuario['email'];
}

$where_clause = implode(' AND ', $where_conditions);

// Query principal - adaptada para diferentes tipos de usuário
if ($is_admin) {
    // Administradores veem todos os dados
    $sql = "
        SELECT DISTINCT
            a.*,
            GROUP_CONCAT(DISTINCT t.nome SEPARATOR ', ') as turmas,
            GROUP_CONCAT(DISTINCT i.nome SEPARATOR ', ') as instrumentos,
            COUNT(DISTINCT m.id) as total_matriculas,
            COUNT(DISTINCT CASE WHEN men.status IN ('Pendente', 'Atrasado') THEN men.id END) as mensalidades_pendentes
        FROM alunos a
        LEFT JOIN matriculas m ON a.id = m.aluno_id AND m.status = 'Ativa'
        LEFT JOIN turmas t ON m.turma_id = t.id
        LEFT JOIN instrumentos i ON t.instrumento_id = i.id
        LEFT JOIN mensalidades men ON m.id = men.matricula_id
        LEFT JOIN professores prof ON t.professor_id = prof.id
        WHERE $where_clause
        GROUP BY a.id
        ORDER BY a.nome ASC
    ";
} else {
    // Professores veem dados limitados + informações de presença
    $sql = "
        SELECT DISTINCT
            a.id,
            a.nome,
            a.email,
            a.telefone,
            a.status,
            a.data_matricula,
            GROUP_CONCAT(DISTINCT t.nome SEPARATOR ', ') as turmas,
            GROUP_CONCAT(DISTINCT i.nome SEPARATOR ', ') as instrumentos,
            COUNT(DISTINCT m.id) as total_matriculas,
            COUNT(DISTINCT CASE WHEN p.presente = 1 THEN p.id END) as total_presencas,
            COUNT(DISTINCT CASE WHEN p.presente = 0 THEN p.id END) as total_faltas,
            ROUND(
                (COUNT(DISTINCT CASE WHEN p.presente = 1 THEN p.id END) * 100.0) / 
                NULLIF(COUNT(DISTINCT p.id), 0), 1
            ) as percentual_presenca
        FROM alunos a
        LEFT JOIN matriculas m ON a.id = m.aluno_id AND m.status = 'Ativa'
        LEFT JOIN turmas t ON m.turma_id = t.id
        LEFT JOIN instrumentos i ON t.instrumento_id = i.id
        LEFT JOIN professores prof ON t.professor_id = prof.id
        LEFT JOIN chamadas c ON t.id = c.turma_id
        LEFT JOIN presencas p ON c.id = p.chamada_id AND p.aluno_id = a.id
        WHERE $where_clause
        GROUP BY a.id
        ORDER BY a.nome ASC
    ";
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$alunos = $stmt->fetchAll();

// Buscar turmas para filtro
if ($is_professor) {
    // Professor vê apenas suas turmas
    $stmt = $db->prepare("
        SELECT t.* FROM turmas t 
        JOIN professores p ON t.professor_id = p.id 
        WHERE t.ativa = 1 AND p.email = ? 
        ORDER BY t.nome
    ");
    $stmt->execute([$usuario['email']]);
} else {
    // Admin vê todas as turmas
    $stmt = $db->query("SELECT * FROM turmas WHERE ativa = 1 ORDER BY nome");
}
$turmas = $stmt->fetchAll();

// Estatísticas
$stmt = $db->query("SELECT COUNT(*) as total FROM alunos WHERE status != 'Inativo'");
$total_alunos = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM alunos WHERE status = 'Matriculado'");
$alunos_matriculados = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Alunos - Escola de Música Harmonia</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .access-info {
            background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
            border: 2px solid #2196f3;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .access-info.professor {
            background: linear-gradient(135deg, #fff3e0, #fce4ec);
            border-color: #ff9800;
        }
        
        .filters-card {
            background: var(--bg-white);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        
        .student-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .student-details h4 {
            margin: 0;
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .student-details p {
            margin: 0;
            color: var(--text-muted);
            font-size: 12px;
        }
        
        .attendance-stats {
            display: flex;
            gap: 10px;
            align-items: center;
            font-size: 12px;
        }
        
        .attendance-item {
            display: flex;
            align-items: center;
            gap: 3px;
            padding: 3px 8px;
            border-radius: 12px;
            background: var(--bg-primary);
        }
        
        .attendance-item.present {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .attendance-item.absent {
            background: #ffebee;
            color: #c62828;
        }
        
        .attendance-percentage {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 15px;
            color: white;
        }
        
        .percentage-excellent { background: #4caf50; }
        .percentage-good { background: #8bc34a; }
        .percentage-warning { background: #ff9800; }
        .percentage-danger { background: #f44336; }
        
        .restricted-data {
            color: var(--text-muted);
            font-style: italic;
            font-size: 12px;
        }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .quick-stat {
            background: var(--bg-white);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary-color);
        }
        
        .quick-stat-number {
            font-size: 1.8em;
            font-weight: bold;
            color: var(--primary-color);
            display: block;
        }
        
        .quick-stat-label {
            color: var(--text-secondary);
            font-size: 0.85em;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .student-info {
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }
            
            .attendance-stats {
                justify-content: center;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>👥 Lista de Alunos</h1>
                <p class="subtitle">
                    <?= $is_admin ? 'Gestão completa de alunos' : 'Visualização de alunos e presença' ?>
                </p>
            </div>
            <div class="header-actions">
                <?php if ($is_admin): ?>
                    <a href="alunos_novo.php" class="btn btn-success">➕ Novo Aluno</a>
                <?php endif; ?>
                <a href="../dashboard.php" class="btn btn-secondary">🏠 Dashboard</a>
                <a href="../logout.php" class="btn btn-danger">🚪 Sair</a>
            </div>
        </div>

        <!-- Navegação -->
        <nav class="navbar">
            <ul>
                <li><a href="../dashboard.php">🏠 Dashboard</a></li>
                <?php if ($is_admin): ?>
                    <li><a href="alunos.php" class="active">👥 Alunos</a></li>
                    <li><a href="turmas.php">📚 Turmas</a></li>
                    <li><a href="chamadas.php">📋 Chamadas</a></li>
                    <li><a href="financeiro.php">💰 Financeiro</a></li>
                    <li><a href="relatorios.php">📊 Relatórios</a></li>
                <?php else: ?>
                    <li><a href="alunos.php" class="active">👥 Meus Alunos</a></li>
                    <li><a href="chamadas.php">📋 Chamadas</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Informações de Acesso -->
        <div class="access-info <?= $is_professor ? 'professor' : '' ?>">
            <?php if ($is_admin): ?>
                <span style="font-size: 1.5em;">🔑</span>
                <div>
                    <strong>Acesso Administrativo</strong><br>
                    <small>Você pode visualizar todos os dados dos alunos, incluindo informações pessoais e financeiras.</small>
                </div>
            <?php else: ?>
                <span style="font-size: 1.5em;">👨‍🏫</span>
                <div>
                    <strong>Acesso de Professor</strong><br>
                    <small>Você pode visualizar dados básicos e informações de presença dos alunos das suas turmas.</small>
                </div>
            <?php endif; ?>
        </div>

        <!-- Estatísticas Rápidas -->
        <div class="quick-stats">
            <div class="quick-stat">
                <span class="quick-stat-number"><?= count($alunos) ?></span>
                <div class="quick-stat-label">
                    <?= $is_professor ? 'Meus Alunos' : 'Total de Alunos' ?>
                </div>
            </div>
            <?php if ($is_admin): ?>
                <div class="quick-stat">
                    <span class="quick-stat-number"><?= $alunos_matriculados ?></span>
                    <div class="quick-stat-label">Matriculados</div>
                </div>
            <?php endif; ?>
            <div class="quick-stat">
                <span class="quick-stat-number"><?= count($turmas) ?></span>
                <div class="quick-stat-label">
                    <?= $is_professor ? 'Minhas Turmas' : 'Turmas Ativas' ?>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-card">
            <h3>🔍 Filtros</h3>
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="form-group">
                        <label for="nome">Nome do Aluno</label>
                        <input 
                            type="text" 
                            id="nome" 
                            name="nome" 
                            class="form-control" 
                            placeholder="Digite o nome..."
                            value="<?= htmlspecialchars($filtro_nome) ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">Todos os Status</option>
                            <option value="Cadastrado" <?= $filtro_status === 'Cadastrado' ? 'selected' : '' ?>>Cadastrado</option>
                            <option value="Matriculado" <?= $filtro_status === 'Matriculado' ? 'selected' : '' ?>>Matriculado</option>
                            <option value="Suspenso" <?= $filtro_status === 'Suspenso' ? 'selected' : '' ?>>Suspenso</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="turma">Turma</label>
                        <select id="turma" name="turma" class="form-control">
                            <option value="">Todas as Turmas</option>
                            <?php foreach ($turmas as $turma): ?>
                                <option value="<?= $turma['id'] ?>" <?= $filtro_turma == $turma['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($turma['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                    <a href="alunos.php" class="btn btn-secondary">🔄 Limpar</a>
                </div>
            </form>
        </div>

        <!-- Tabela de Alunos -->
        <div class="card">
            <div class="card-header">
                <h3>📋 Lista de Alunos (<?= count($alunos) ?> encontrados)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($alunos)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <div style="font-size: 3em; margin-bottom: 15px;">😔</div>
                        <h3>Nenhum aluno encontrado</h3>
                        <p>
                            <?= $is_professor ? 'Você não possui alunos nas suas turmas ou tente ajustar os filtros.' : 'Tente ajustar os filtros ou cadastre um novo aluno.' ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Aluno</th>
                                    <th>Status</th>
                                    <th>Turmas/Instrumentos</th>
                                    <th>Contato</th>
                                    <?php if ($is_admin): ?>
                                        <th>Dados Pessoais</th>
                                        <th>Financeiro</th>
                                        <th>Ações</th>
                                    <?php else: ?>
                                        <th>Frequência</th>
                                        <th>Presença (%)</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alunos as $aluno): ?>
                                <tr>
                                    <td>
                                        <div class="student-info">
                                            <div class="student-avatar">
                                                <?= strtoupper(substr($aluno['nome'], 0, 1)) ?>
                                            </div>
                                            <div class="student-details">
                                                <h4><?= htmlspecialchars($aluno['nome']) ?></h4>
                                                <p>ID: <?= $aluno['id'] ?></p>
                                                <?php if ($aluno['data_matricula']): ?>
                                                    <p>Matrícula: <?= date('d/m/Y', strtotime($aluno['data_matricula'])) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= 
                                            $aluno['status'] === 'Matriculado' ? 'success' : 
                                            ($aluno['status'] === 'Cadastrado' ? 'warning' : 'danger') 
                                        ?>">
                                            <?= htmlspecialchars($aluno['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($aluno['turmas']): ?>
                                            <strong>Turmas:</strong> <?= htmlspecialchars($aluno['turmas']) ?><br>
                                            <strong>Instrumentos:</strong> <?= htmlspecialchars($aluno['instrumentos']) ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">Sem turmas</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong>Email:</strong> <?= htmlspecialchars($aluno['email']) ?><br>
                                        <strong>Telefone:</strong> <?= htmlspecialchars($aluno['telefone'] ?? 'Não informado') ?>
                                    </td>
                                    
                                    <?php if ($is_admin): ?>
                                        <!-- Dados completos para administradores -->
                                        <td>
                                            <?php if (!empty($aluno['endereco'])): ?>
                                                <strong>Endereço:</strong> <?= htmlspecialchars($aluno['endereco']) ?><br>
                                            <?php endif; ?>
                                            <?php if (!empty($aluno['responsavel_nome'])): ?>
                                                <strong>Responsável:</strong> <?= htmlspecialchars($aluno['responsavel_nome']) ?><br>
                                                <strong>Tel. Resp.:</strong> <?= htmlspecialchars($aluno['responsavel_telefone'] ?? 'N/I') ?>
                                            <?php else: ?>
                                                <span class="restricted-data">Sem responsável cadastrado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <strong><?= $aluno['total_matriculas'] ?></strong> matrículas<br>
                                            <?php if ($aluno['mensalidades_pendentes'] > 0): ?>
                                                <span class="badge badge-danger">
                                                    <?= $aluno['mensalidades_pendentes'] ?> pendente(s)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Em dia</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                <a href="aluno_detalhes.php?id=<?= $aluno['id'] ?>" class="btn btn-info btn-sm" title="Ver Detalhes">
                                                    👁️
                                                </a>
                                                <a href="aluno_editar.php?id=<?= $aluno['id'] ?>" class="btn btn-warning btn-sm" title="Editar">
                                                    ✏️
                                                </a>
                                            </div>
                                        </td>
                                    <?php else: ?>
                                        <!-- Dados de presença para professores -->
                                        <td>
                                            <div class="attendance-stats">
                                                <div class="attendance-item present">
                                                    <span>✅</span>
                                                    <span><?= $aluno['total_presencas'] ?? 0 ?></span>
                                                </div>
                                                <div class="attendance-item absent">
                                                    <span>❌</span>
                                                    <span><?= $aluno['total_faltas'] ?? 0 ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                            $percentual = $aluno['percentual_presenca'] ?? 0;
                                            $classe_percentual = '';
                                            if ($percentual >= 90) $classe_percentual = 'percentage-excellent';
                                            elseif ($percentual >= 75) $classe_percentual = 'percentage-good';
                                            elseif ($percentual >= 60) $classe_percentual = 'percentage-warning';
                                            else $classe_percentual = 'percentage-danger';
                                            ?>
                                            <span class="attendance-percentage <?= $classe_percentual ?>">
                                                <?= number_format($percentual, 1) ?>%
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Animações de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });

        // Auto-submit do formulário de filtros
        document.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', function() {
                // Opcional: auto-submit quando mudar filtros
                // this.form.submit();
            });
        });

        // Busca em tempo real
        const searchInput = document.getElementById('nome');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                // Implementar busca em tempo real se necessário
                // this.form.submit();
            }, 500);
        });

        // Destacar linhas ao passar o mouse
        document.querySelectorAll('tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = 'var(--bg-primary)';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });
    </script>
</body>
</html>
