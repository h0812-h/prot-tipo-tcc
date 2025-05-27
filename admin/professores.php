<?php
require_once '../config/database.php';
require_once '../config/auth.php';

verificarAdmin();

$db = getDB();
$usuario = getUsuarioLogado();

// Processar ações
$mensagem = '';
$tipo_mensagem = '';

if ($_POST['acao'] ?? '' === 'adicionar_professor') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $cpf = $_POST['cpf'];
    $especialidades = $_POST['especialidades'];
    $salario = $_POST['salario'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    
    try {
        $stmt = $db->prepare("
            INSERT INTO professores (nome, email, telefone, cpf, especialidades, salario, password, data_contratacao) 
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())
        ");
        $stmt->execute([$nome, $email, $telefone, $cpf, $especialidades, $salario, $senha]);
        
        $mensagem = 'Professor adicionado com sucesso!';
        $tipo_mensagem = 'success';
    } catch (Exception $e) {
        $mensagem = 'Erro ao adicionar professor: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

if ($_POST['acao'] ?? '' === 'editar_professor') {
    $id = $_POST['professor_id'];
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $cpf = $_POST['cpf'];
    $especialidades = $_POST['especialidades'];
    $salario = $_POST['salario'];
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    try {
        $sql = "UPDATE professores SET nome = ?, email = ?, telefone = ?, cpf = ?, especialidades = ?, salario = ?, ativo = ? WHERE id = ?";
        $params = [$nome, $email, $telefone, $cpf, $especialidades, $salario, $ativo, $id];
        
        // Se uma nova senha foi fornecida
        if (!empty($_POST['nova_senha'])) {
            $nova_senha = password_hash($_POST['nova_senha'], PASSWORD_DEFAULT);
            $sql = "UPDATE professores SET nome = ?, email = ?, telefone = ?, cpf = ?, especialidades = ?, salario = ?, ativo = ?, password = ? WHERE id = ?";
            $params = [$nome, $email, $telefone, $cpf, $especialidades, $salario, $ativo, $nova_senha, $id];
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $mensagem = 'Professor atualizado com sucesso!';
        $tipo_mensagem = 'success';
    } catch (Exception $e) {
        $mensagem = 'Erro ao atualizar professor: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// Buscar professores
$stmt = $db->query("
    SELECT p.*, 
           COUNT(DISTINCT t.id) as total_turmas,
           COUNT(DISTINCT CASE WHEN t.ativa = 1 THEN t.id END) as turmas_ativas,
           COUNT(DISTINCT c.id) as total_chamadas
    FROM professores p
    LEFT JOIN turmas t ON p.id = t.professor_id
    LEFT JOIN chamadas c ON p.id = c.professor_id
    GROUP BY p.id
    ORDER BY p.nome
");
$professores = $stmt->fetchAll();

// Estatísticas
$stmt = $db->query("SELECT COUNT(*) as total FROM professores WHERE ativo = 1");
$total_ativos = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM professores WHERE ativo = 0");
$total_inativos = $stmt->fetch()['total'];

$stmt = $db->query("SELECT AVG(salario) as media FROM professores WHERE ativo = 1");
$salario_medio = $stmt->fetch()['media'];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Professores - Escola de Música Harmonia</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .professor-card {
            background: var(--bg-white);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .professor-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .professor-card.inativo {
            border-left-color: var(--danger-color);
            opacity: 0.7;
        }
        
        .professor-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .professor-info {
            flex: 1;
        }
        
        .professor-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .professor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.5em;
            margin-right: 15px;
        }
        
        .professor-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .detail-item {
            background: var(--bg-primary);
            padding: 12px;
            border-radius: 8px;
        }
        
        .detail-label {
            font-size: 0.9em;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }
        
        .detail-value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .especialidades-tags {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        
        .especialidade-tag {
            background: var(--primary-color);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: var(--bg-white);
            margin: 2% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .professor-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .professor-details {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>👨‍🏫 Gestão de Professores</h1>
                <p class="subtitle">Controle completo do corpo docente</p>
            </div>
            <div class="header-actions">
                <button onclick="abrirModalAdicionar()" class="btn btn-success">➕ Novo Professor</button>
                <a href="../dashboard.php" class="btn btn-secondary">🏠 Dashboard</a>
                <a href="../logout.php" class="btn btn-danger">🚪 Sair</a>
            </div>
        </div>

        <!-- Navegação -->
        <nav class="navbar">
            <ul>
                <li><a href="../dashboard.php">🏠 Dashboard</a></li>
                <li><a href="alunos.php">📊 Planilha Alunos</a></li>
                <li><a href="turmas.php">📚 Turmas</a></li>
                <li><a href="chamadas.php">📋 Chamadas</a></li>
                <li><a href="professores.php" class="active">👨‍🏫 Professores</a></li>
                <li><a href="financeiro.php">💰 Financeiro</a></li>
                <li><a href="relatorios.php">📊 Relatórios</a></li>
            </ul>
        </nav>

        <!-- Mensagens -->
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo_mensagem ?>">
                <span><?= $tipo_mensagem === 'success' ? '✅' : '❌' ?></span>
                <?= htmlspecialchars($mensagem) ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card success">
                <span class="number"><?= $total_ativos ?></span>
                <span class="label">👨‍🏫 Professores Ativos</span>
            </div>
            <div class="stat-card danger">
                <span class="number"><?= $total_inativos ?></span>
                <span class="label">❌ Inativos</span>
            </div>
            <div class="stat-card info">
                <span class="number">R$ <?= number_format($salario_medio, 2, ',', '.') ?></span>
                <span class="label">💰 Salário Médio</span>
            </div>
            <div class="stat-card">
                <span class="number"><?= count($professores) ?></span>
                <span class="label">📊 Total Geral</span>
            </div>
        </div>

        <!-- Lista de Professores -->
        <div class="professores-list">
            <?php if (empty($professores)): ?>
                <div style="text-align: center; padding: 60px; color: var(--text-muted);">
                    <div style="font-size: 4em; margin-bottom: 20px;">👨‍🏫</div>
                    <h3>Nenhum professor cadastrado</h3>
                    <p>Clique em "Novo Professor" para começar.</p>
                </div>
            <?php else: ?>
                <?php foreach ($professores as $professor): ?>
                <div class="professor-card <?= $professor['ativo'] ? '' : 'inativo' ?>">
                    <div class="professor-header">
                        <div style="display: flex; align-items: center;">
                            <div class="professor-avatar">
                                <?= strtoupper(substr($professor['nome'], 0, 1)) ?>
                            </div>
                            <div class="professor-info">
                                <h3 style="margin: 0; color: var(--text-primary);">
                                    <?= htmlspecialchars($professor['nome']) ?>
                                    <?php if (!$professor['ativo']): ?>
                                        <span class="badge badge-danger">Inativo</span>
                                    <?php endif; ?>
                                </h3>
                                <p style="margin: 5px 0; color: var(--text-secondary);">
                                    📧 <?= htmlspecialchars($professor['email']) ?>
                                </p>
                                <p style="margin: 0; color: var(--text-secondary);">
                                    📞 <?= htmlspecialchars($professor['telefone'] ?? 'Não informado') ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="professor-actions">
                            <button onclick="editarProfessor(<?= $professor['id'] ?>)" class="btn btn-warning btn-sm" title="Editar">
                                ✏️ Editar
                            </button>
                            <button onclick="verDetalhes(<?= $professor['id'] ?>)" class="btn btn-info btn-sm" title="Detalhes">
                                👁️ Detalhes
                            </button>
                            <?php if ($professor['ativo']): ?>
                                <button onclick="toggleStatus(<?= $professor['id'] ?>, 0)" class="btn btn-danger btn-sm" title="Desativar">
                                    ❌ Desativar
                                </button>
                            <?php else: ?>
                                <button onclick="toggleStatus(<?= $professor['id'] ?>, 1)" class="btn btn-success btn-sm" title="Ativar">
                                    ✅ Ativar
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="professor-details">
                        <div class="detail-item">
                            <div class="detail-label">🎵 Especialidades</div>
                            <div class="detail-value">
                                <?php if ($professor['especialidades']): ?>
                                    <div class="especialidades-tags">
                                        <?php foreach (explode(',', $professor['especialidades']) as $esp): ?>
                                            <span class="especialidade-tag"><?= trim($esp) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    Não informado
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">📚 Turmas</div>
                            <div class="detail-value">
                                <?= $professor['turmas_ativas'] ?> ativas / <?= $professor['total_turmas'] ?> total
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">📋 Chamadas</div>
                            <div class="detail-value"><?= $professor['total_chamadas'] ?> realizadas</div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">💰 Salário</div>
                            <div class="detail-value">
                                R$ <?= number_format($professor['salario'], 2, ',', '.') ?>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">📅 Contratação</div>
                            <div class="detail-value">
                                <?= $professor['data_contratacao'] ? date('d/m/Y', strtotime($professor['data_contratacao'])) : 'Não informado' ?>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <div class="detail-label">📄 CPF</div>
                            <div class="detail-value">
                                <?= $professor['cpf'] ? htmlspecialchars($professor['cpf']) : 'Não informado' ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Adicionar Professor -->
    <div id="modalAdicionar" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>➕ Novo Professor</h3>
                <span onclick="fecharModal('modalAdicionar')" style="cursor: pointer; font-size: 24px;">&times;</span>
            </div>
            
            <form method="POST">
                <input type="hidden" name="acao" value="adicionar_professor">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nome">Nome Completo *</label>
                        <input type="text" name="nome" id="nome" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="telefone">Telefone</label>
                        <input type="text" name="telefone" id="telefone" class="form-control" placeholder="(11) 99999-9999">
                    </div>
                    
                    <div class="form-group">
                        <label for="cpf">CPF</label>
                        <input type="text" name="cpf" id="cpf" class="form-control" placeholder="000.000.000-00">
                    </div>
                    
                    <div class="form-group">
                        <label for="especialidades">Especialidades *</label>
                        <input type="text" name="especialidades" id="especialidades" class="form-control" required placeholder="Piano, Violão, Canto...">
                    </div>
                    
                    <div class="form-group">
                        <label for="salario">Salário</label>
                        <input type="number" name="salario" id="salario" class="form-control" step="0.01" placeholder="0.00">
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="senha">Senha de Acesso *</label>
                        <input type="password" name="senha" id="senha" class="form-control" required placeholder="Mínimo 6 caracteres">
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="fecharModal('modalAdicionar')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-success">Adicionar Professor</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Professor -->
    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>✏️ Editar Professor</h3>
                <span onclick="fecharModal('modalEditar')" style="cursor: pointer; font-size: 24px;">&times;</span>
            </div>
            
            <form method="POST" id="formEditar">
                <input type="hidden" name="acao" value="editar_professor">
                <input type="hidden" name="professor_id" id="editProfessorId">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="editNome">Nome Completo *</label>
                        <input type="text" name="nome" id="editNome" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editEmail">Email *</label>
                        <input type="email" name="email" id="editEmail" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editTelefone">Telefone</label>
                        <input type="text" name="telefone" id="editTelefone" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="editCpf">CPF</label>
                        <input type="text" name="cpf" id="editCpf" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="editEspecialidades">Especialidades *</label>
                        <input type="text" name="especialidades" id="editEspecialidades" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editSalario">Salário</label>
                        <input type="number" name="salario" id="editSalario" class="form-control" step="0.01">
                    </div>
                    
                    <div class="form-group">
                        <label for="editNovaSenha">Nova Senha (opcional)</label>
                        <input type="password" name="nova_senha" id="editNovaSenha" class="form-control" placeholder="Deixe em branco para manter a atual">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="ativo" id="editAtivo" value="1">
                            Professor Ativo
                        </label>
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="fecharModal('modalEditar')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalAdicionar() {
            document.getElementById('modalAdicionar').style.display = 'block';
        }

        function fecharModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function editarProfessor(professorId) {
            // Buscar dados do professor via AJAX ou usar dados já carregados
            // Por simplicidade, vou usar um fetch
            fetch(`get_professor.php?id=${professorId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('editProfessorId').value = data.id;
                    document.getElementById('editNome').value = data.nome;
                    document.getElementById('editEmail').value = data.email;
                    document.getElementById('editTelefone').value = data.telefone || '';
                    document.getElementById('editCpf').value = data.cpf || '';
                    document.getElementById('editEspecialidades').value = data.especialidades || '';
                    document.getElementById('editSalario').value = data.salario || '';
                    document.getElementById('editAtivo').checked = data.ativo == 1;
                    
                    document.getElementById('modalEditar').style.display = 'block';
                })
                .catch(error => {
                    alert('Erro ao carregar dados do professor');
                });
        }

        function verDetalhes(professorId) {
            window.open(`professor_detalhes.php?id=${professorId}`, '_blank');
        }

        function toggleStatus(professorId, novoStatus) {
            const acao = novoStatus ? 'ativar' : 'desativar';
            if (confirm(`Tem certeza que deseja ${acao} este professor?`)) {
                // Implementar via form ou AJAX
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="acao" value="editar_professor">
                    <input type="hidden" name="professor_id" value="${professorId}">
                    <input type="hidden" name="ativo" value="${novoStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Fechar modais clicando fora
        window.onclick = function(event) {
            const modals = ['modalAdicionar', 'modalEditar'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    fecharModal(modalId);
                }
            });
        }

        // Máscaras para campos
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
            e.target.value = value;
        });

        document.getElementById('cpf').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });

        // Animações de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.professor-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.4s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
