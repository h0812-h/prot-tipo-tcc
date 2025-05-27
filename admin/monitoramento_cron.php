<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/cron_manager.php';

verificarAdmin();

$cronManager = new CronManager();
$db = getDB();

// Processar ações
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    switch ($acao) {
        case 'executar_manual':
            $resultado = $cronManager->executarEnvioEmails(true);
            if ($resultado !== false) {
                $mensagem = "✅ Execução manual concluída! $resultado emails enviados.";
            } else {
                $mensagem = "❌ Erro na execução manual. Verifique os logs.";
            }
            break;
            
        case 'limpar_logs':
            if ($cronManager->limparLogsAntigos()) {
                $mensagem = "✅ Logs antigos limpos com sucesso!";
            } else {
                $mensagem = "❌ Erro ao limpar logs.";
            }
            break;
    }
}

// Buscar estatísticas
$estatisticas = $cronManager->obterEstatisticas(30);

// Buscar configurações atuais
$stmt = $db->query("SELECT * FROM configuracoes_email WHERE chave LIKE 'cron_%' ORDER BY chave");
$configuracoes = $stmt->fetchAll();

// Verificar última execução
$stmt = $db->query("
    SELECT * FROM log_execucoes_cron 
    WHERE tipo = 'envio_emails' 
    ORDER BY data_execucao DESC 
    LIMIT 1
");
$ultima_execucao = $stmt->fetch();

// Verificar se CRON está funcionando (última execução nas últimas 25 horas)
$cron_funcionando = false;
if ($ultima_execucao) {
    $ultima = new DateTime($ultima_execucao['data_execucao']);
    $agora = new DateTime();
    $diferenca = $agora->diff($ultima);
    $horas_diferenca = ($diferenca->days * 24) + $diferenca->h;
    $cron_funcionando = $horas_diferenca <= 25;
}

// Buscar logs recentes de email
$stmt = $db->query("
    SELECT tipo, status, COUNT(*) as total
    FROM log_emails 
    WHERE data_envio >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY tipo, status
    ORDER BY tipo, status
");
$logs_recentes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoramento CRON - Escola Harmonia</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-online { background: #28a745; }
        .status-offline { background: #dc3545; }
        .status-warning { background: #ffc107; }
        
        .cron-status {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .config-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .log-viewer {
            background: #1e1e1e;
            color: #fff;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .stats-table th,
        .stats-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .stats-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .command-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }
        
        .step-number {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            text-align: center;
            line-height: 25px;
            font-weight: bold;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>⏰ Monitoramento CRON</h1>
                <p class="subtitle">Controle e monitoramento do envio automático de emails</p>
            </div>
            <div class="header-actions">
                <a href="../dashboard.php" class="btn btn-secondary">🏠 Dashboard</a>
                <a href="teste_email.php" class="btn btn-info">📧 Teste Email</a>
                <a href="../logout.php" class="btn btn-danger">🚪 Sair</a>
            </div>
        </div>

        <!-- Navegação -->
        <nav class="navbar">
            <ul>
                <li><a href="../dashboard.php">🏠 Dashboard</a></li>
                <li><a href="alunos.php">👥 Alunos</a></li>
                <li><a href="financeiro.php">💰 Financeiro</a></li>
                <li><a href="teste_email.php">📧 Teste Email</a></li>
                <li><a href="monitoramento_cron.php" class="active">⏰ CRON</a></li>
            </ul>
        </nav>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= strpos($mensagem, '✅') !== false ? 'success' : 'danger' ?>">
                <?= $mensagem ?>
            </div>
        <?php endif; ?>

        <!-- Status do CRON -->
        <div class="cron-status">
            <h3>
                <span class="status-indicator <?= $cron_funcionando ? 'status-online' : 'status-offline' ?>"></span>
                Status do CRON: <?= $cron_funcionando ? 'Funcionando' : 'Inativo/Problema' ?>
            </h3>
            
            <?php if ($ultima_execucao): ?>
                <p><strong>Última execução:</strong> <?= date('d/m/Y H:i:s', strtotime($ultima_execucao['data_execucao'])) ?></p>
                <p><strong>Emails enviados:</strong> <?= $ultima_execucao['emails_enviados'] ?></p>
                <p><strong>Status:</strong> 
                    <span class="badge badge-<?= $ultima_execucao['status'] === 'sucesso' ? 'success' : 'danger' ?>">
                        <?= ucfirst($ultima_execucao['status']) ?>
                    </span>
                </p>
            <?php else: ?>
                <p><strong>Nenhuma execução registrada ainda.</strong></p>
            <?php endif; ?>
            
            <div style="margin-top: 15px;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="acao" value="executar_manual">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Executar envio manual de emails agora?')">
                        🚀 Executar Manualmente
                    </button>
                </form>
                
                <form method="POST" style="display: inline; margin-left: 10px;">
                    <input type="hidden" name="acao" value="limpar_logs">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Limpar logs antigos?')">
                        🧹 Limpar Logs
                    </button>
                </form>
            </div>
        </div>

        <div class="config-grid">
            <!-- Configurações -->
            <div class="config-card">
                <h4>⚙️ Configurações Atuais</h4>
                
                <?php if (!empty($configuracoes)): ?>
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Configuração</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($configuracoes as $config): ?>
                                <tr>
                                    <td><?= str_replace('cron_', '', $config['chave']) ?></td>
                                    <td><code><?= htmlspecialchars($config['valor']) ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Nenhuma configuração encontrada.</p>
                <?php endif; ?>
            </div>

            <!-- Estatísticas -->
            <div class="config-card">
                <h4>📊 Estatísticas (Últimos 30 dias)</h4>
                
                <?php if (!empty($estatisticas)): ?>
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Execuções</th>
                                <th>Emails</th>
                                <th>Média</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($estatisticas, 0, 10) as $stat): ?>
                                <tr>
                                    <td><?= date('d/m', strtotime($stat['data'])) ?></td>
                                    <td><?= $stat['total_execucoes'] ?></td>
                                    <td><?= $stat['total_emails'] ?></td>
                                    <td><?= number_format($stat['media_emails'], 1) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Nenhuma estatística disponível ainda.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Logs de Email Recentes -->
        <div class="config-card">
            <h4>📧 Logs de Email (Últimas 24h)</h4>
            
            <?php if (!empty($logs_recentes)): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                    <?php foreach ($logs_recentes as $log): ?>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center;">
                            <div style="font-weight: bold; margin-bottom: 5px;">
                                <?= ucfirst(str_replace('_', ' ', $log['tipo'])) ?>
                            </div>
                            <div style="font-size: 1.5em; margin-bottom: 5px;">
                                <?= $log['total'] ?>
                            </div>
                            <div>
                                <span class="badge badge-<?= $log['status'] === 'enviado' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($log['status']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>Nenhum email enviado nas últimas 24 horas.</p>
            <?php endif; ?>
        </div>

        <!-- Guia de Configuração -->
        <div class="config-card">
            <h4>📋 Como Configurar o CRON</h4>
            
            <div style="margin-bottom: 20px;">
                <h5><span class="step-number">1</span>Acesso ao Servidor</h5>
                <p>Conecte-se ao seu servidor via SSH:</p>
                <div class="command-box">ssh usuario@seu-servidor.com</div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h5><span class="step-number">2</span>Editar Crontab</h5>
                <p>Abra o editor de crontab:</p>
                <div class="command-box">crontab -e</div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h5><span class="step-number">3</span>Adicionar Entradas</h5>
                <p>Adicione as seguintes linhas no final do arquivo:</p>
                <div class="command-box"># Escola de Música Harmonia - Envio automático de emails
# Execução principal às 9h
0 9 * * * /usr/bin/php <?= __DIR__ ?>/../config/cron_manager.php

# Execução secundária às 18:30
30 18 * * * /usr/bin/php <?= __DIR__ ?>/../config/cron_manager.php

# Limpeza de logs todo domingo às 2h
0 2 * * 0 /usr/bin/php <?= __DIR__ ?>/../config/cron_manager.php --clean-logs</div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h5><span class="step-number">4</span>Verificar Configuração</h5>
                <p>Verifique se foi adicionado corretamente:</p>
                <div class="command-box">crontab -l</div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h5><span class="step-number">5</span>Testar Execução</h5>
                <p>Teste a execução manual:</p>
                <div class="command-box">php <?= __DIR__ ?>/../config/cron_manager.php --force</div>
            </div>
            
            <div class="alert alert-info">
                <h5>💡 Dicas Importantes:</h5>
                <ul>
                    <li><strong>Caminho do PHP:</strong> Use <code>which php</code> para encontrar o caminho correto</li>
                    <li><strong>Permissões:</strong> Certifique-se que o usuário do CRON tem permissão para executar o script</li>
                    <li><strong>Logs:</strong> Os logs são salvos em <code><?= __DIR__ ?>/../logs/cron_email.log</code></li>
                    <li><strong>Timezone:</strong> Verifique se o timezone do servidor está correto</li>
                </ul>
            </div>
        </div>

        <!-- Visualizador de Logs -->
        <div class="config-card">
            <h4>📄 Logs Recentes</h4>
            
            <div class="log-viewer" id="logViewer">
                <?php
                $logFile = __DIR__ . '/../logs/cron_email.log';
                if (file_exists($logFile)) {
                    $logs = file($logFile);
                    $logs = array_slice($logs, -50); // Últimas 50 linhas
                    echo htmlspecialchars(implode('', $logs));
                } else {
                    echo "Nenhum log encontrado ainda.\nO arquivo será criado na primeira execução do CRON.";
                }
                ?>
            </div>
            
            <div style="margin-top: 10px;">
                <button onclick="atualizarLogs()" class="btn btn-secondary">🔄 Atualizar Logs</button>
                <button onclick="limparVisualizadorLogs()" class="btn btn-warning">🧹 Limpar Visualizador</button>
            </div>
        </div>
    </div>

    <script>
        // Atualizar logs via AJAX
        function atualizarLogs() {
            fetch('<?= $_SERVER['PHP_SELF'] ?>?action=get_logs')
                .then(response => response.text())
                .then(data => {
                    if (data.trim()) {
                        document.getElementById('logViewer').textContent = data;
                        // Scroll para o final
                        const logViewer = document.getElementById('logViewer');
                        logViewer.scrollTop = logViewer.scrollHeight;
                    }
                })
                .catch(error => console.error('Erro ao atualizar logs:', error));
        }
        
        function limparVisualizadorLogs() {
            document.getElementById('logViewer').textContent = 'Logs limpos do visualizador.\nClique em "Atualizar Logs" para recarregar.';
        }
        
        // Auto-atualizar logs a cada 30 segundos
        setInterval(atualizarLogs, 30000);
        
        // Scroll automático para o final dos logs
        document.addEventListener('DOMContentLoaded', function() {
            const logViewer = document.getElementById('logViewer');
            logViewer.scrollTop = logViewer.scrollHeight;
        });
    </script>
</body>
</html>

<?php
// Endpoint para buscar logs via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'get_logs') {
    $logFile = __DIR__ . '/../logs/cron_email.log';
    if (file_exists($logFile)) {
        $logs = file($logFile);
        $logs = array_slice($logs, -50);
        echo implode('', $logs);
    }
    exit;
}
?>
