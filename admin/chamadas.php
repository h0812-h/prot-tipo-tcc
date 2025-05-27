<?php
require_once '../config/database.php';
require_once '../config/auth.php';

verificarAdmin();

$db = getDB();
$usuario = getUsuarioLogado();

// Processar chamada
$mensagem = '';
$tipo_mensagem = '';

if ($_POST['acao'] ?? '' === 'salvar_chamada') {
    $turma_id = $_POST['turma_id'];
    $data_aula = $_POST['data_aula'];
    $observacoes = $_POST['observacoes'] ?? '';
    $presencas = $_POST['presenca'] ?? [];
    
    try {
        $db->beginTransaction();
        
        // Verificar se já existe chamada para esta turma nesta data
        $stmt = $db->prepare("SELECT id FROM chamadas WHERE turma_id = ? AND data_aula = ?");
        $stmt->execute([$turma_id, $data_aula]);
        $chamada_existente = $stmt->fetch();
        
        if ($chamada_existente) {
            throw new Exception('Já existe uma chamada para esta turma nesta data!');
        }
        
        // Buscar professor da turma
        $stmt = $db->prepare("SELECT professor_id FROM turmas WHERE id = ?");
        $stmt->execute([$turma_id]);
        $turma = $stmt->fetch();
        
        // Inserir chamada
        $stmt = $db->prepare("
            INSERT INTO chamadas (turma_id, data_aula, professor_id, observacoes) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$turma_id, $data_aula, $turma['professor_id'], $observacoes]);
        $chamada_id = $db->lastInsertId();
        
        // Inserir presenças
        foreach ($presencas as $aluno_id => $presente) {
            $stmt = $db->prepare("
                INSERT INTO presencas (chamada_id, aluno_id, presente) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$chamada_id, $aluno_id, $presente === 'presente' ? 1 : 0]);
        }
        
        $db->commit();
        $mensagem = 'Chamada salva com sucesso!';
        $tipo_mensagem = 'success';
        
    } catch (Exception $e) {
        $db->rollBack();
        $mensagem = 'Erro ao salvar chamada: ' . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// Obter turma selecionada
$turma_selecionada = $_GET['turma'] ?? '';
$data_selecionada = $_GET['data'] ?? date('Y-m-d');

// Buscar turmas ativas
$stmt = $db->query("
    SELECT t.*, p.nome as professor_nome, i.nome as instrumento_nome, i.icone
    FROM turmas t
    JOIN professores p ON t.professor_id = p.id
    JOIN instrumentos i ON t.instrumento_id = i.id
    WHERE t.ativa = 1
    ORDER BY t.nome
");
$turmas = $stmt->fetchAll();

// Se uma turma foi selecionada, buscar alunos matriculados
$alunos_turma = [];
$turma_atual = null;

if ($turma_selecionada) {
    // Buscar dados da turma
    $stmt = $db->prepare("
        SELECT t.*, p.nome as professor_nome, i.nome as instrumento_nome, i.icone
        FROM turmas t
        JOIN professores p ON t.professor_id = p.id
        JOIN instrumentos i ON t.instrumento_id = i.id
        WHERE t.id = ?
    ");
    $stmt->execute([$turma_selecionada]);
    $turma_atual = $stmt->fetch();
    
    // Buscar alunos matriculados na turma
    $stmt = $db->prepare("
        SELECT a.*, m.data_matricula
        FROM alunos a
        JOIN matriculas m ON a.id = m.aluno_id
        WHERE m.turma_id = ? AND m.status = 'Ativa' AND a.status = 'Matriculado'
        ORDER BY a.nome
    ");
    $stmt->execute([$turma_selecionada]);
    $alunos_turma = $stmt->fetchAll();
    
    // Verificar se já existe chamada para esta data
    $stmt = $db->prepare("
        SELECT c.*, COUNT(p.id) as total_presencas
        FROM chamadas c
        LEFT JOIN presencas p ON c.id = p.chamada_id
        WHERE c.turma_id = ? AND c.data_aula = ?
        GROUP BY c.id
    ");
    $stmt->execute([$turma_selecionada, $data_selecionada]);
    $chamada_existente = $stmt->fetch();
}

// Buscar histórico de chamadas recentes
$stmt = $db->query("
    SELECT c.*, t.nome as turma_nome, p.nome as professor_nome,
           COUNT(pr.id) as total_alunos,
           COUNT(CASE WHEN pr.presente = 1 THEN 1 END) as presentes,
           COUNT(CASE WHEN pr.presente = 0 THEN 1 END) as ausentes
    FROM chamadas c
    JOIN turmas t ON c.turma_id = t.id
    JOIN professores p ON c.professor_id = p.id
    LEFT JOIN presencas pr ON c.id = pr.chamada_id
    GROUP BY c.id
    ORDER BY c.data_aula DESC, c.data_criacao DESC
    LIMIT 10
");
$historico_chamadas = $stmt->fetchAll();

// Estatísticas
$stmt = $db->query("SELECT COUNT(*) as total FROM chamadas WHERE DATE(data_aula) = CURDATE()");
$chamadas_hoje = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM chamadas WHERE WEEK(data_aula) = WEEK(CURDATE())");
$chamadas_semana = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM turmas WHERE ativa = 1");
$turmas_ativas = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Chamadas - Escola de Música Harmonia</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .chamada-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }
        
        .sidebar-chamada {
            background: var(--bg-white);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .turma-item {
            background: var(--bg-primary);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .turma-item:hover {
            background: var(--bg-secondary);
            transform: translateY(-2px);
        }
        
        .turma-item.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-dark);
        }
        
        .turma-item h4 {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .turma-item p {
            font-size: 0.9em;
            opacity: 0.8;
            margin: 2px 0;
        }
        
        .aluno-card {
            background: var(--bg-primary);
            padding: 20px;
            border-radius: 12px;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        
        .aluno-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .aluno-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .aluno-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
            margin-right: 15px;
        }
        
        .aluno-info h4 {
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        
        .aluno-info p {
            color: var(--text-muted);
            font-size: 0.9em;
        }
        
        .presenca-options {
            display: flex;
            gap: 10px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            flex: 1;
            justify-content: center;
        }
        
        .radio-option:hover {
            background: var(--bg-secondary);
        }
        
        .radio-option.selected {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }
        
        .radio-option.presente.selected {
            background: var(--success-color);
            border-color: var(--success-color);
        }
        
        .radio-option.ausente.selected {
            background: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        .radio-option input[type="radio"] {
            margin: 0;
        }
        
        .turma-info-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
        }
        
        .turma-info-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .turma-icon {
            font-size: 2.5em;
        }
        
        .turma-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .detail-item {
            background: rgba(255, 255, 255, 0.2);
            padding: 10px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        
        .detail-item strong {
            display: block;
            margin-bottom: 5px;
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .chamada-existente {
            background: var(--warning-color);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .historico-item {
            background: var(--bg-primary);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid var(--primary-color);
        }
        
        .historico-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .historico-stats {
            display: flex;
            gap: 15px;
            font-size: 0.9em;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .form-chamada {
            background: var(--bg-white);
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        @media (max-width: 768px) {
            .chamada-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar-chamada {
                position: static;
                order: 2;
            }
            
            .form-chamada {
                order: 1;
            }
            
            .presenca-options {
                flex-direction: column;
            }
            
            .turma-details {
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
                <h1>📋 Sistema de Chamadas</h1>
                <p class="subtitle">Controle de presença dos alunos</p>
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
                <li><a href="turmas.php">📚 Turmas</a></li>
                <li><a href="chamadas.php" class="active">📋 Chamadas</a></li>
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
            <div class="stat-card">
                <span class="number"><?= $chamadas_hoje ?></span>
                <span class="label">📋 Chamadas Hoje</span>
            </div>
            <div class="stat-card success">
                <span class="number"><?= $chamadas_semana ?></span>
                <span class="label">📅 Chamadas esta Semana</span>
            </div>
            <div class="stat-card info">
                <span class="number"><?= $turmas_ativas ?></span>
                <span class="label">📚 Turmas Ativas</span>
            </div>
            <div class="stat-card warning">
                <span class="number"><?= count($historico_chamadas) ?></span>
                <span class="label">📊 Histórico Recente</span>
            </div>
        </div>

        <div class="chamada-container">
            <!-- Sidebar com Turmas -->
            <div class="sidebar-chamada">
                <h3>📚 Selecionar Turma</h3>
                
                <div style="margin-bottom: 20px;">
                    <label for="data_chamada">📅 Data da Aula:</label>
                    <input 
                        type="date" 
                        id="data_chamada" 
                        class="form-control" 
                        value="<?= $data_selecionada ?>"
                        onchange="updateDate()"
                    >
                </div>
                
                <?php foreach ($turmas as $turma): ?>
                <div class="turma-item <?= $turma['id'] == $turma_selecionada ? 'active' : '' ?>" 
                     onclick="selectTurma(<?= $turma['id'] ?>)">
                    <h4>
                        <span><?= $turma['icone'] ?></span>
                        <?= htmlspecialchars($turma['nome']) ?>
                    </h4>
                    <p><strong>Professor:</strong> <?= htmlspecialchars($turma['professor_nome']) ?></p>
                    <p><strong>Instrumento:</strong> <?= htmlspecialchars($turma['instrumento_nome']) ?></p>
                    <p><strong>Horário:</strong> <?= $turma['dia_semana'] ?>s - <?= date('H:i', strtotime($turma['horario_inicio'])) ?> às <?= date('H:i', strtotime($turma['horario_fim'])) ?></p>
                </div>
                <?php endforeach; ?>

                <?php if (!empty($historico_chamadas)): ?>
                <div style="margin-top: 30px;">
                    <h3>📊 Histórico Recente</h3>
                    <?php foreach (array_slice($historico_chamadas, 0, 5) as $chamada): ?>
                    <div class="historico-item">
                        <div class="historico-header">
                            <strong><?= htmlspecialchars($chamada['turma_nome']) ?></strong>
                            <span><?= date('d/m/Y', strtotime($chamada['data_aula'])) ?></span>
                        </div>
                        <div class="historico-stats">
                            <div class="stat-item">
                                <span>✅</span>
                                <span><?= $chamada['presentes'] ?></span>
                            </div>
                            <div class="stat-item">
                                <span>❌</span>
                                <span><?= $chamada['ausentes'] ?></span>
                            </div>
                            <div class="stat-item">
                                <span>👥</span>
                                <span><?= $chamada['total_alunos'] ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Área Principal da Chamada -->
            <div class="form-chamada">
                <?php if (!$turma_selecionada): ?>
                    <div style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                        <div style="font-size: 4em; margin-bottom: 20px;">📋</div>
                        <h3>Selecione uma Turma</h3>
                        <p>Escolha uma turma na barra lateral para começar a fazer a chamada.</p>
                    </div>
                <?php elseif (empty($alunos_turma)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                        <div style="font-size: 4em; margin-bottom: 20px;">😔</div>
                        <h3>Nenhum Aluno Matriculado</h3>
                        <p>Esta turma não possui alunos matriculados ativos.</p>
                        <a href="alunos.php" class="btn btn-primary" style="margin-top: 20px;">
                            👥 Gerenciar Alunos
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Informações da Turma -->
                    <div class="turma-info-card">
                        <div class="turma-info-header">
                            <div class="turma-icon"><?= $turma_atual['icone'] ?></div>
                            <div>
                                <h2><?= htmlspecialchars($turma_atual['nome']) ?></h2>
                                <p style="opacity: 0.9;"><?= htmlspecialchars($turma_atual['instrumento_nome']) ?></p>
                            </div>
                        </div>
                        
                        <div class="turma-details">
                            <div class="detail-item">
                                <strong>Professor</strong>
                                <?= htmlspecialchars($turma_atual['professor_nome']) ?>
                            </div>
                            <div class="detail-item">
                                <strong>Horário</strong>
                                <?= $turma_atual['dia_semana'] ?>s - <?= date('H:i', strtotime($turma_atual['horario_inicio'])) ?> às <?= date('H:i', strtotime($turma_atual['horario_fim'])) ?>
                            </div>
                            <div class="detail-item">
                                <strong>Alunos</strong>
                                <?= count($alunos_turma) ?> matriculados
                            </div>
                            <div class="detail-item">
                                <strong>Data da Aula</strong>
                                <?= date('d/m/Y', strtotime($data_selecionada)) ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($chamada_existente): ?>
                        <div class="chamada-existente">
                            <strong>⚠️ Atenção!</strong> Já existe uma chamada para esta turma na data selecionada.
                            <br>Total de presenças registradas: <?= $chamada_existente['total_presencas'] ?>
                        </div>
                    <?php endif; ?>

                    <!-- Formulário de Chamada -->
                    <form method="POST" id="formChamada">
                        <input type="hidden" name="turma_id" value="<?= $turma_atual['id'] ?>">
                        <input type="hidden" name="data_aula" value="<?= $data_selecionada ?>">

                        <div class="form-group">
                            <label for="observacoes">📝 Observações da Aula (opcional)</label>
                            <textarea 
                                id="observacoes" 
                                name="observacoes" 
                                class="form-control" 
                                rows="3" 
                                placeholder="Digite observações sobre a aula..."
                            ></textarea>
                        </div>

                        <h3 style="margin: 30px 0 20px 0;">👥 Lista de Presença</h3>

                        <?php foreach ($alunos_turma as $aluno): ?>
                        <div class="aluno-card">
                            <div class="aluno-header">
                                <div class="aluno-avatar">
                                    <?= strtoupper(substr($aluno['nome'], 0, 1)) ?>
                                </div>
                                <div class="aluno-info">
                                    <h4><?= htmlspecialchars($aluno['nome']) ?></h4>
                                    <p>Matrícula: <?= date('d/m/Y', strtotime($aluno['data_matricula'])) ?></p>
                                </div>
                            </div>
                            
                            <div class="presenca-options">
                                <label class="radio-option presente" data-aluno="<?= $aluno['id'] ?>">
                                    <input type="radio" name="presenca[<?= $aluno['id'] ?>]" value="presente" required>
                                    <span>✅ Presente</span>
                                </label>
                                <label class="radio-option ausente" data-aluno="<?= $aluno['id'] ?>">
                                    <input type="radio" name="presenca[<?= $aluno['id'] ?>]" value="ausente" required>
                                    <span>❌ Ausente</span>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div style="text-align: center; margin-top: 30px;">
                            <button type="submit" name="acao" value="salvar_chamada" class="btn btn-success btn-lg">
                                💾 Salvar Chamada
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function selectTurma(turmaId) {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('turma', turmaId);
            window.location.href = currentUrl.toString();
        }

        function updateDate() {
            const data = document.getElementById('data_chamada').value;
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('data', data);
            window.location.href = currentUrl.toString();
        }

        // Interatividade dos radio buttons
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const alunoId = this.name.match(/\[(\d+)\]/)[1];
                const alunoCard = document.querySelector(`[data-aluno="${alunoId}"]`).closest('.aluno-card');
                
                // Remover classes de seleção
                alunoCard.querySelectorAll('.radio-option').forEach(option => {
                    option.classList.remove('selected');
                });
                
                // Adicionar classe à opção selecionada
                this.closest('.radio-option').classList.add('selected');
            });
        });

        // Validação do formulário
        document.getElementById('formChamada').addEventListener('submit', function(e) {
            const radios = this.querySelectorAll('input[type="radio"]:checked');
            const totalAlunos = <?= count($alunos_turma) ?>;
            
            if (radios.length !== totalAlunos) {
                e.preventDefault();
                alert('Por favor, marque a presença de todos os alunos antes de salvar.');
                return;
            }
            
            const presentes = Array.from(radios).filter(r => r.value === 'presente').length;
            const ausentes = totalAlunos - presentes;
            
            const confirmacao = confirm(
                `Confirmar chamada?\n\n` +
                `Turma: <?= htmlspecialchars($turma_atual['nome'] ?? '') ?>\n` +
                `Data: <?= date('d/m/Y', strtotime($data_selecionada)) ?>\n` +
                `Presentes: ${presentes}\n` +
                `Ausentes: ${ausentes}`
            );
            
            if (!confirmacao) {
                e.preventDefault();
            } else {
                // Mostrar loading
                const btn = this.querySelector('button[type="submit"]');
                btn.innerHTML = '<span class="loading"></span> Salvando...';
                btn.disabled = true;
            }
        });

        // Auto-save no localStorage
        function salvarRascunho() {
            const formData = new FormData(document.getElementById('formChamada'));
            const rascunho = {};
            
            for (let [key, value] of formData.entries()) {
                rascunho[key] = value;
            }
            
            localStorage.setItem(`rascunho_chamada_<?= $turma_selecionada ?>_<?= $data_selecionada ?>`, JSON.stringify(rascunho));
        }

        function carregarRascunho() {
            const rascunho = localStorage.getItem(`rascunho_chamada_<?= $turma_selecionada ?>_<?= $data_selecionada ?>`);
            
            if (rascunho) {
                const dados = JSON.parse(rascunho);
                
                for (let [key, value] of Object.entries(dados)) {
                    const input = document.querySelector(`[name="${key}"]`);
                    if (input) {
                        if (input.type === 'radio' && input.value === value) {
                            input.checked = true;
                            input.dispatchEvent(new Event('change'));
                        } else if (input.type !== 'radio') {
                            input.value = value;
                        }
                    }
                }
            }
        }

        // Carregar rascunho ao carregar a página
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($turma_selecionada && !empty($alunos_turma)): ?>
            carregarRascunho();
            <?php endif; ?>
        });

        // Salvar rascunho quando houver mudanças
        document.querySelectorAll('input, textarea').forEach(input => {
            input.addEventListener('change', salvarRascunho);
        });

        // Limpar rascunho após salvar com sucesso
        <?php if ($tipo_mensagem === 'success'): ?>
        localStorage.removeItem(`rascunho_chamada_<?= $turma_selecionada ?>_<?= $data_selecionada ?>`);
        <?php endif; ?>

        // Animações de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.aluno-card');
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