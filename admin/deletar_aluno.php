<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se é administrador
verificarAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Processar exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'deletar') {
    $aluno_id = $_POST['aluno_id'] ?? '';
    
    if (empty($aluno_id)) {
        $mensagem = 'ID do aluno não informado.';
        $tipo_mensagem = 'error';
    } else {
        try {
            $db = getDB();
            $db->beginTransaction();
            
            // Buscar dados do aluno antes de deletar
            $stmt = $db->prepare("SELECT nome, email, usuario_id FROM alunos WHERE id = ?");
            $stmt->execute([$aluno_id]);
            $aluno = $stmt->fetch();
            
            if (!$aluno) {
                throw new Exception('Aluno não encontrado.');
            }
            
            // Função para verificar se tabela existe
            function tabelaExiste($db, $tabela) {
                try {
                    $stmt = $db->prepare("SHOW TABLES LIKE ?");
                    $stmt->execute([$tabela]);
                    return $stmt->rowCount() > 0;
                } catch (Exception $e) {
                    return false;
                }
            }
            
            // 1. Deletar presenças (se tabela existir)
            if (tabelaExiste($db, 'presencas') && tabelaExiste($db, 'chamadas')) {
                try {
                    $stmt = $db->prepare("
                        DELETE p FROM presencas p 
                        INNER JOIN chamadas c ON p.chamada_id = c.id 
                        WHERE p.aluno_id = ?
                    ");
                    $stmt->execute([$aluno_id]);
                } catch (Exception $e) {
                    // Ignorar erro se tabela não existir
                }
            }
            
            // 2. Deletar mensalidades (se tabela existir)
            if (tabelaExiste($db, 'mensalidades') && tabelaExiste($db, 'matriculas')) {
                try {
                    $stmt = $db->prepare("
                        DELETE men FROM mensalidades men 
                        INNER JOIN matriculas m ON men.matricula_id = m.id 
                        WHERE m.aluno_id = ?
                    ");
                    $stmt->execute([$aluno_id]);
                } catch (Exception $e) {
                    // Ignorar erro se tabela não existir
                }
            }
            
            // 3. Deletar matrículas (se tabela existir)
            if (tabelaExiste($db, 'matriculas')) {
                try {
                    $stmt = $db->prepare("DELETE FROM matriculas WHERE aluno_id = ?");
                    $stmt->execute([$aluno_id]);
                } catch (Exception $e) {
                    // Ignorar erro se tabela não existir
                }
            }
            
            // 4. Deletar pagamentos pendentes (se tabela existir)
            if (tabelaExiste($db, 'pagamentos_pendentes')) {
                try {
                    $stmt = $db->prepare("DELETE FROM pagamentos_pendentes WHERE aluno_id = ?");
                    $stmt->execute([$aluno_id]);
                } catch (Exception $e) {
                    // Ignorar erro se tabela não existir
                }
            }
            
            // 5. Deletar emails enviados (se tabela existir)
            if (tabelaExiste($db, 'emails_enviados')) {
                try {
                    $stmt = $db->prepare("DELETE FROM emails_enviados WHERE destinatario_email = ?");
                    $stmt->execute([$aluno['email']]);
                } catch (Exception $e) {
                    // Ignorar erro se tabela não existir
                }
            }
            
            // 6. Deletar log de emails (se tabela existir)
            if (tabelaExiste($db, 'log_emails')) {
                try {
                    $stmt = $db->prepare("DELETE FROM log_emails WHERE aluno_id = ?");
                    $stmt->execute([$aluno_id]);
                } catch (Exception $e) {
                    // Ignorar erro se tabela não existir
                }
            }
            
            // 7. Deletar o aluno (tabela principal - deve existir)
            $stmt = $db->prepare("DELETE FROM alunos WHERE id = ?");
            $stmt->execute([$aluno_id]);
            
            // 8. Deletar usuário relacionado (se existir) - CORRIGIDO
            if ($aluno['usuario_id']) {
                try {
                    // Primeiro, verificar se o usuário realmente existe
                    $stmt = $db->prepare("SELECT id, email FROM usuarios WHERE id = ?");
                    $stmt->execute([$aluno['usuario_id']]);
                    $usuario = $stmt->fetch();
                    
                    if ($usuario) {
                        // Deletar o usuário
                        $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
                        $stmt->execute([$aluno['usuario_id']]);
                    }
                } catch (Exception $e) {
                    // Se der erro, tentar deletar por email também
                    try {
                        $stmt = $db->prepare("DELETE FROM usuarios WHERE email = ?");
                        $stmt->execute([$aluno['email']]);
                    } catch (Exception $e2) {
                        // Log do erro mas não interromper o processo
                        error_log("Erro ao deletar usuário: " . $e2->getMessage());
                    }
                }
            }

            // 9. Deletar qualquer usuário órfão com o mesmo email (limpeza extra)
            try {
                $stmt = $db->prepare("DELETE FROM usuarios WHERE email = ? AND tipo = 'aluno'");
                $stmt->execute([$aluno['email']]);
            } catch (Exception $e) {
                // Ignorar erro se não conseguir limpar
                error_log("Erro na limpeza de usuários órfãos: " . $e->getMessage());
            }
            
            $db->commit();
            
            $mensagem = "Aluno '{$aluno['nome']}' foi deletado com sucesso, incluindo todos os registros relacionados.";
            $tipo_mensagem = 'success';
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $mensagem = 'Erro ao deletar aluno: ' . $e->getMessage();
            $tipo_mensagem = 'error';
        }
    }
}

// Buscar todos os alunos
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT a.*, u.username 
        FROM alunos a 
        LEFT JOIN usuarios u ON a.usuario_id = u.id 
        ORDER BY a.nome
    ");
    $alunos = $stmt->fetchAll();
} catch (Exception $e) {
    $alunos = [];
    $mensagem = 'Erro ao carregar alunos: ' . $e->getMessage();
    $tipo_mensagem = 'error';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deletar Aluno - Escola de Música Harmonia</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .danger-zone {
            background: #fff5f5;
            border: 2px solid #fed7d7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .danger-title {
            color: #c53030;
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .danger-warning {
            color: #742a2a;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .aluno-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .aluno-info h4 {
            margin: 0 0 5px 0;
            color: var(--text-primary);
        }
        
        .aluno-info p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.9em;
        }
        
        .btn-delete {
            background: #e53e3e;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
        }
        
        .btn-delete:hover {
            background: #c53030;
        }
        
        .search-box {
            margin-bottom: 20px;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 1em;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            color: #276749;
        }
        
        .alert-error {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            color: #c53030;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🗑️ Deletar Aluno</h1>
                <p class="subtitle">Remover alunos e todos os registros relacionados</p>
            </div>
            <div class="header-actions">
                <a href="alunos.php" class="btn btn-secondary">👥 Voltar para Alunos</a>
                <a href="../dashboard.php" class="btn btn-primary">🏠 Dashboard</a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo_mensagem ?>">
                <?= $mensagem ?>
            </div>
        <?php endif; ?>

        <div class="danger-zone">
            <div class="danger-title">
                <span>⚠️</span> Zona de Perigo
            </div>
            <div class="danger-warning">
                <strong>ATENÇÃO:</strong> Esta ação é irreversível! Ao deletar um aluno, os seguintes dados serão removidos permanentemente:
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Dados pessoais do aluno</li>
                    <li>Todas as matrículas</li>
                    <li>Histórico de presenças</li>
                    <li>Registros de mensalidades</li>
                    <li>Pagamentos pendentes</li>
                    <li>Emails enviados</li>
                    <li>Usuário de acesso ao sistema</li>
                </ul>
                <strong>Use apenas para testes ou em casos extremos!</strong>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Lista de Alunos Cadastrados</h3>
            </div>
            <div class="card-body">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="🔍 Buscar aluno por nome ou email...">
                </div>

                <?php if (empty($alunos)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <div style="font-size: 3em; margin-bottom: 15px;">📭</div>
                        <h3>Nenhum aluno cadastrado</h3>
                        <p>Não há alunos para deletar no momento.</p>
                    </div>
                <?php else: ?>
                    <div id="alunosList">
                        <?php foreach ($alunos as $aluno): ?>
                            <div class="aluno-card" data-nome="<?= strtolower($aluno['nome']) ?>" data-email="<?= strtolower($aluno['email']) ?>">
                                <div class="aluno-info">
                                    <h4><?= htmlspecialchars($aluno['nome']) ?></h4>
                                    <p>
                                        <strong>Email:</strong> <?= htmlspecialchars($aluno['email']) ?> | 
                                        <strong>Status:</strong> <?= htmlspecialchars($aluno['status']) ?> | 
                                        <strong>ID:</strong> <?= $aluno['id'] ?>
                                        <?php if ($aluno['username']): ?>
                                            | <strong>Usuário:</strong> <?= htmlspecialchars($aluno['username']) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <button 
                                    class="btn-delete" 
                                    onclick="confirmarDelecao(<?= $aluno['id'] ?>, '<?= htmlspecialchars($aluno['nome'], ENT_QUOTES) ?>')"
                                >
                                    🗑️ Deletar
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Form oculto para deletar -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="acao" value="deletar">
        <input type="hidden" name="aluno_id" id="deleteAlunoId">
    </form>

    <script>
        // Busca em tempo real
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const alunoCards = document.querySelectorAll('.aluno-card');
            
            alunoCards.forEach(card => {
                const nome = card.getAttribute('data-nome');
                const email = card.getAttribute('data-email');
                
                if (nome.includes(searchTerm) || email.includes(searchTerm)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Confirmar deleção
        function confirmarDelecao(alunoId, nomeAluno) {
            const confirmacao = confirm(
                `⚠️ ATENÇÃO: AÇÃO IRREVERSÍVEL!\n\n` +
                `Você tem certeza que deseja deletar o aluno "${nomeAluno}"?\n\n` +
                `Esta ação irá remover PERMANENTEMENTE:\n` +
                `• Todos os dados pessoais\n` +
                `• Histórico de matrículas\n` +
                `• Registros de presença\n` +
                `• Dados financeiros\n` +
                `• Acesso ao sistema\n\n` +
                `Digite "DELETAR" para confirmar:`
            );
            
            if (confirmacao) {
                const confirmacaoTexto = prompt(
                    `Para confirmar a deleção do aluno "${nomeAluno}", digite exatamente: DELETAR`
                );
                
                if (confirmacaoTexto === 'DELETAR') {
                    document.getElementById('deleteAlunoId').value = alunoId;
                    document.getElementById('deleteForm').submit();
                } else {
                    alert('❌ Confirmação incorreta. Deleção cancelada.');
                }
            }
        }

        // Destacar cards ao passar o mouse
        document.querySelectorAll('.aluno-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f7fafc';
                this.style.borderColor = '#cbd5e0';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.backgroundColor = 'white';
                this.style.borderColor = '#e2e8f0';
            });
        });
    </script>
</body>
</html>
