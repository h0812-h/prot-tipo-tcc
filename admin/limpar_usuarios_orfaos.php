<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se é administrador
verificarAdmin();

$mensagem = '';
$tipo_mensagem = '';

// Processar limpeza
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'limpar') {
    try {
        $db = getDB();
        $db->beginTransaction();
        
        // 1. Encontrar usuários órfãos (usuários que não têm aluno correspondente)
        $stmt = $db->query("
            SELECT u.id, u.username, u.email, u.nome 
            FROM usuarios u 
            WHERE u.tipo = 'aluno' 
            AND u.id NOT IN (SELECT DISTINCT usuario_id FROM alunos WHERE usuario_id IS NOT NULL)
        ");
        $usuarios_orfaos = $stmt->fetchAll();
        
        // 2. Deletar usuários órfãos
        $count_orfaos = 0;
        foreach ($usuarios_orfaos as $usuario) {
            $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario['id']]);
            $count_orfaos++;
        }
        
        // 3. Encontrar emails duplicados
        $stmt = $db->query("
            SELECT email, COUNT(*) as total 
            FROM usuarios 
            WHERE tipo = 'aluno' 
            GROUP BY email 
            HAVING COUNT(*) > 1
        ");
        $emails_duplicados = $stmt->fetchAll();
        
        // 4. Limpar emails duplicados (manter apenas o mais recente)
        $count_duplicados = 0;
        foreach ($emails_duplicados as $email_dup) {
            // Manter apenas o usuário mais recente com este email
            $stmt = $db->prepare("
                DELETE FROM usuarios 
                WHERE email = ? AND tipo = 'aluno' 
                AND id NOT IN (
                    SELECT * FROM (
                        SELECT MAX(id) FROM usuarios 
                        WHERE email = ? AND tipo = 'aluno'
                    ) as temp
                )
            ");
            $stmt->execute([$email_dup['email'], $email_dup['email']]);
            $count_duplicados += ($email_dup['total'] - 1);
        }
        
        $db->commit();
        
        $mensagem = "Limpeza concluída com sucesso!<br>";
        $mensagem .= "• Usuários órfãos removidos: $count_orfaos<br>";
        $mensagem .= "• Emails duplicados limpos: $count_duplicados";
        $tipo_mensagem = 'success';
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $mensagem = 'Erro na limpeza: ' . $e->getMessage();
        $tipo_mensagem = 'error';
    }
}

// Buscar problemas atuais
try {
    $db = getDB();
    
    // Usuários órfãos
    $stmt = $db->query("
        SELECT u.id, u.username, u.email, u.nome 
        FROM usuarios u 
        WHERE u.tipo = 'aluno' 
        AND u.id NOT IN (SELECT DISTINCT usuario_id FROM alunos WHERE usuario_id IS NOT NULL)
        ORDER BY u.nome
    ");
    $usuarios_orfaos = $stmt->fetchAll();
    
    // Emails duplicados
    $stmt = $db->query("
        SELECT email, COUNT(*) as total, GROUP_CONCAT(username) as usernames
        FROM usuarios 
        WHERE tipo = 'aluno' 
        GROUP BY email 
        HAVING COUNT(*) > 1
        ORDER BY email
    ");
    $emails_duplicados = $stmt->fetchAll();
    
} catch (Exception $e) {
    $usuarios_orfaos = [];
    $emails_duplicados = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpar Usuários Órfãos - Escola de Música Harmonia</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .problem-section {
            background: #fff8e1;
            border: 2px solid #ffcc02;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .problem-title {
            color: #f57c00;
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .problem-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 5px;
        }
        
        .clean-zone {
            background: #f0fff4;
            border: 2px solid #9ae6b4;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .btn-clean {
            background: #38a169;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
        }
        
        .btn-clean:hover {
            background: #2f855a;
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
                <h1>🧹 Limpar Usuários Órfãos</h1>
                <p class="subtitle">Resolver problemas de usuários duplicados e órfãos</p>
            </div>
            <div class="header-actions">
                <a href="deletar_aluno.php" class="btn btn-secondary">🗑️ Deletar Aluno</a>
                <a href="../dashboard.php" class="btn btn-primary">🏠 Dashboard</a>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $tipo_mensagem ?>">
                <?= $mensagem ?>
            </div>
        <?php endif; ?>

        <!-- Usuários Órfãos -->
        <div class="card">
            <div class="card-header">
                <h3>👻 Usuários Órfãos (<?= count($usuarios_orfaos) ?>)</h3>
                <p>Usuários que não têm aluno correspondente</p>
            </div>
            <div class="card-body">
                <?php if (empty($usuarios_orfaos)): ?>
                    <div style="text-align: center; padding: 20px; color: #38a169;">
                        <div style="font-size: 2em; margin-bottom: 10px;">✅</div>
                        <p>Nenhum usuário órfão encontrado!</p>
                    </div>
                <?php else: ?>
                    <div class="problem-section">
                        <div class="problem-title">
                            <span>⚠️</span> Usuários sem aluno correspondente
                        </div>
                        <?php foreach ($usuarios_orfaos as $usuario): ?>
                            <div class="problem-item">
                                <strong><?= htmlspecialchars($usuario['nome']) ?></strong> 
                                (<?= htmlspecialchars($usuario['username']) ?>) - 
                                <?= htmlspecialchars($usuario['email']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Emails Duplicados -->
        <div class="card">
            <div class="card-header">
                <h3>📧 Emails Duplicados (<?= count($emails_duplicados) ?>)</h3>
                <p>Emails que aparecem em múltiplos usuários</p>
            </div>
            <div class="card-body">
                <?php if (empty($emails_duplicados)): ?>
                    <div style="text-align: center; padding: 20px; color: #38a169;">
                        <div style="font-size: 2em; margin-bottom: 10px;">✅</div>
                        <p>Nenhum email duplicado encontrado!</p>
                    </div>
                <?php else: ?>
                    <div class="problem-section">
                        <div class="problem-title">
                            <span>⚠️</span> Emails com múltiplos usuários
                        </div>
                        <?php foreach ($emails_duplicados as $email): ?>
                            <div class="problem-item">
                                <strong><?= htmlspecialchars($email['email']) ?></strong> 
                                (<?= $email['total'] ?> usuários) - 
                                Usernames: <?= htmlspecialchars($email['usernames']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Botão de Limpeza -->
        <?php if (!empty($usuarios_orfaos) || !empty($emails_duplicados)): ?>
            <div class="clean-zone">
                <h3 style="color: #38a169; margin-bottom: 15px;">🧹 Executar Limpeza</h3>
                <p style="margin-bottom: 20px;">
                    Esta ação irá remover todos os usuários órfãos e resolver emails duplicados.<br>
                    <strong>Esta operação é segura e não afeta alunos válidos.</strong>
                </p>
                <form method="POST" onsubmit="return confirm('Confirma a limpeza dos usuários órfãos e emails duplicados?')">
                    <input type="hidden" name="acao" value="limpar">
                    <button type="submit" class="btn-clean">
                        🧹 Executar Limpeza Agora
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="clean-zone">
                <h3 style="color: #38a169; margin-bottom: 15px;">✅ Tudo Limpo!</h3>
                <p>Não há problemas de usuários órfãos ou emails duplicados no momento.</p>
            </div>
        <?php endif; ?>

        <!-- Informações -->
        <div class="card">
            <div class="card-header">
                <h3>ℹ️ Informações</h3>
            </div>
            <div class="card-body">
                <h4>O que esta ferramenta faz:</h4>
                <ul>
                    <li><strong>Remove usuários órfãos:</strong> Usuários que não têm aluno correspondente</li>
                    <li><strong>Resolve emails duplicados:</strong> Mantém apenas o usuário mais recente para cada email</li>
                    <li><strong>Não afeta dados válidos:</strong> Apenas limpa registros problemáticos</li>
                </ul>
                
                <h4 style="margin-top: 20px;">Quando usar:</h4>
                <ul>
                    <li>Após deletar alunos e ter problemas de cadastro</li>
                    <li>Quando aparecer erro de "email duplicado"</li>
                    <li>Para manutenção geral do banco de dados</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
