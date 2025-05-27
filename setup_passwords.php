<?php
require_once 'config/database.php';

// Script para configurar as senhas corretas no banco de dados
try {
    $db = getDB();
    
    // Senha padrão para todos os usuários de teste
    $senha_padrao = 'senha123';
    $senha_hash = password_hash($senha_padrao, PASSWORD_DEFAULT);
    
    echo "<h2>🔧 Configurando Senhas do Sistema</h2>";
    echo "<p>Senha padrão para todos os usuários: <strong>$senha_padrao</strong></p>";
    echo "<hr>";
    
    // Atualizar senhas de todos os usuários
    $usuarios = [
        // Administradores
        'admin' => ['nome' => 'Administrador Sistema', 'tipo' => 'administrador'],
        
        // Professores
        'prof.carlos' => ['nome' => 'Carlos Silva (Professor)', 'tipo' => 'professor'],
        'prof.ana' => ['nome' => 'Ana Costa (Professora)', 'tipo' => 'professor'],
        'prof.joao' => ['nome' => 'João Santos (Professor)', 'tipo' => 'professor'],
        'prof.maria' => ['nome' => 'Maria Oliveira (Professora)', 'tipo' => 'professor'],
        'prof.pedro' => ['nome' => 'Pedro Lima (Professor)', 'tipo' => 'professor'],
        
        // Alunos
        'maria.santos' => ['nome' => 'Maria Santos (Aluna)', 'tipo' => 'aluno'],
        'pedro.costa' => ['nome' => 'Pedro Costa (Aluno)', 'tipo' => 'aluno'],
        'ana.silva' => ['nome' => 'Ana Silva (Aluna)', 'tipo' => 'aluno']
    ];
    
    $usuarios_atualizados = 0;
    $usuarios_criados = 0;
    
    foreach ($usuarios as $username => $dados) {
        // Verificar se o usuário já existe
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        $usuario_existente = $stmt->fetch();
        
        if ($usuario_existente) {
            // Atualizar senha do usuário existente
            $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE username = ?");
            $resultado = $stmt->execute([$senha_hash, $username]);
            
            if ($resultado) {
                echo "✅ Senha atualizada para: <strong>$username</strong> ({$dados['nome']})<br>";
                $usuarios_atualizados++;
            } else {
                echo "❌ Erro ao atualizar senha para: <strong>$username</strong><br>";
            }
        } else {
            // Criar novo usuário
            try {
                $email = '';
                switch ($dados['tipo']) {
                    case 'administrador':
                        $email = 'admin@escolaharmonia.com';
                        break;
                    case 'professor':
                        $nome_limpo = strtolower(str_replace(['prof.', ' (Professor)', ' (Professora)'], '', $username));
                        $email = $nome_limpo . '@escolaharmonia.com';
                        break;
                    case 'aluno':
                        $email = $username . '@email.com';
                        break;
                }
                
                $stmt = $db->prepare("
                    INSERT INTO usuarios (username, password, tipo, nome, email, ativo) 
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $resultado = $stmt->execute([
                    $username, 
                    $senha_hash, 
                    $dados['tipo'], 
                    $dados['nome'], 
                    $email
                ]);
                
                if ($resultado) {
                    echo "🆕 Usuário criado: <strong>$username</strong> ({$dados['nome']})<br>";
                    $usuarios_criados++;
                } else {
                    echo "❌ Erro ao criar usuário: <strong>$username</strong><br>";
                }
            } catch (Exception $e) {
                echo "❌ Erro ao criar usuário <strong>$username</strong>: " . $e->getMessage() . "<br>";
            }
        }
    }
    
    echo "<hr>";
    echo "<h3>📊 Resumo da Configuração:</h3>";
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<strong>✅ Usuários atualizados:</strong> $usuarios_atualizados<br>";
    echo "<strong>🆕 Usuários criados:</strong> $usuarios_criados<br>";
    echo "<strong>📝 Total processado:</strong> " . count($usuarios) . "<br>";
    echo "</div>";
    
    echo "<hr>";
    echo "<h3>📋 Usuários de Teste Configurados:</h3>";
    
    // Administradores
    echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #2196f3;'>";
    echo "<h4 style='color: #2196f3; margin-top: 0;'>👨‍💼 ADMINISTRADORES</h4>";
    echo "<div style='font-family: monospace; background: white; padding: 10px; border-radius: 5px;'>";
    echo "<strong>Usuário:</strong> <code>admin</code><br>";
    echo "<strong>Senha:</strong> <code>$senha_padrao</code><br>";
    echo "<strong>Acesso:</strong> Completo (todos os dados e funcionalidades)";
    echo "</div>";
    echo "</div>";
    
    // Professores
    echo "<div style='background: #fff3e0; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #ff9800;'>";
    echo "<h4 style='color: #ff9800; margin-top: 0;'>👨‍🏫 PROFESSORES</h4>";
    
    $professores_demo = [
        'prof.carlos' => 'Carlos Silva - Piano',
        'prof.ana' => 'Ana Costa - Violão',
        'prof.joao' => 'João Santos - Bateria',
        'prof.maria' => 'Maria Oliveira - Violino',
        'prof.pedro' => 'Pedro Lima - Flauta'
    ];
    
    foreach ($professores_demo as $user => $nome) {
        echo "<div style='font-family: monospace; background: white; padding: 8px; border-radius: 5px; margin-bottom: 8px;'>";
        echo "<strong>Usuário:</strong> <code>$user</code> | <strong>Senha:</strong> <code>$senha_padrao</code><br>";
        echo "<small style='color: #666;'>$nome</small>";
        echo "</div>";
    }
    echo "<p style='font-size: 12px; color: #666; margin-bottom: 0;'>";
    echo "<strong>Acesso:</strong> Dados básicos dos alunos + informações de presença das suas turmas";
    echo "</p>";
    echo "</div>";
    
    // Alunos
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #4caf50;'>";
    echo "<h4 style='color: #4caf50; margin-top: 0;'>👩‍🎓 ALUNOS</h4>";
    
    $alunos_demo = [
        'maria.santos' => 'Maria Santos',
        'pedro.costa' => 'Pedro Costa',
        'ana.silva' => 'Ana Silva'
    ];
    
    foreach ($alunos_demo as $user => $nome) {
        echo "<div style='font-family: monospace; background: white; padding: 8px; border-radius: 5px; margin-bottom: 8px;'>";
        echo "<strong>Usuário:</strong> <code>$user</code> | <strong>Senha:</strong> <code>$senha_padrao</code><br>";
        echo "<small style='color: #666;'>$nome</small>";
        echo "</div>";
    }
    echo "<p style='font-size: 12px; color: #666; margin-bottom: 0;'>";
    echo "<strong>Acesso:</strong> Apenas seus próprios dados e informações acadêmicas";
    echo "</p>";
    echo "</div>";
    
    echo "<hr>";
    echo "<p>✅ <strong>Configuração concluída!</strong></p>";
    echo "<p><a href='login.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Ir para Login</a></p>";
    
    // Verificar se as senhas estão funcionando
    echo "<hr>";
    echo "<h3>🔍 Teste de Verificação:</h3>";
    
    // Testar admin
    $stmt = $db->prepare("SELECT username, password FROM usuarios WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($senha_padrao, $admin['password'])) {
        echo "✅ Verificação OK: Senha do admin está funcionando corretamente!<br>";
    } else {
        echo "❌ Erro: Problema na verificação da senha do admin!<br>";
    }
    
    // Testar um professor
    $stmt = $db->prepare("SELECT username, password FROM usuarios WHERE username = 'prof.carlos'");
    $stmt->execute();
    $professor = $stmt->fetch();
    
    if ($professor && password_verify($senha_padrao, $professor['password'])) {
        echo "✅ Verificação OK: Senha do professor Carlos está funcionando corretamente!<br>";
    } else {
        echo "❌ Erro: Problema na verificação da senha do professor!<br>";
    }
    
    // Estatísticas finais
    echo "<hr>";
    echo "<h3>📈 Estatísticas do Sistema:</h3>";
    
    $stmt = $db->query("SELECT tipo, COUNT(*) as total FROM usuarios WHERE ativo = 1 GROUP BY tipo");
    $stats = $stmt->fetchAll();
    
    echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 15px 0;'>";
    foreach ($stats as $stat) {
        $icon = '';
        $color = '';
        switch ($stat['tipo']) {
            case 'administrador':
                $icon = '👨‍💼';
                $color = '#2196f3';
                break;
            case 'professor':
                $icon = '👨‍🏫';
                $color = '#ff9800';
                break;
            case 'aluno':
                $icon = '👩‍🎓';
                $color = '#4caf50';
                break;
        }
        
        echo "<div style='background: white; padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid $color;'>";
        echo "<div style='font-size: 1.5em;'>$icon</div>";
        echo "<div style='font-size: 1.8em; font-weight: bold; color: $color;'>{$stat['total']}</div>";
        echo "<div style='font-size: 0.9em; color: #666; text-transform: capitalize;'>{$stat['tipo']}s</div>";
        echo "</div>";
    }
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<h2>❌ Erro de Conexão</h2>";
    echo "<p>Erro: " . $e->getMessage() . "</p>";
    echo "<p><strong>Verifique se:</strong></p>";
    echo "<ul>";
    echo "<li>O MySQL está rodando</li>";
    echo "<li>O banco 'escola_musica' foi criado</li>";
    echo "<li>As configurações em config/database.php estão corretas</li>";
    echo "<li>A tabela usuarios foi atualizada com o tipo 'professor'</li>";
    echo "</ul>";
    
    echo "<h3>🔧 Script SQL para Atualizar o Banco:</h3>";
    echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
    echo "-- Execute este comando no seu banco de dados:\n";
    echo "ALTER TABLE usuarios MODIFY COLUMN tipo ENUM('administrador', 'professor', 'aluno') NOT NULL;";
    echo "</pre>";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração de Senhas - Escola Harmonia</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
            line-height: 1.6;
        }
        
        h2, h3, h4 {
            color: #333;
        }
        
        code {
            background: #e8e8e8;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }
        
        pre {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 15px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        a {
            color: #667eea;
            text-decoration: none;
        }
        
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="warning">
        <strong>⚠️ Importante:</strong> Este arquivo é apenas para configuração inicial. 
        Após usar, você pode deletá-lo por segurança.
    </div>
    
    <div class="success">
        <strong>💡 Dica:</strong> Após configurar as senhas, acesse 
        <a href="index.php">index.php</a> para ver a página inicial ou 
        <a href="login.php">login.php</a> para fazer login diretamente.
    </div>
    
    <div class="info">
        <strong>🎓 Sistema de Níveis de Acesso:</strong><br>
        <strong>Administradores:</strong> Acesso completo a todos os dados e funcionalidades<br>
        <strong>Professores:</strong> Acesso aos dados básicos dos alunos e informações de presença das suas turmas<br>
        <strong>Alunos:</strong> Acesso apenas aos seus próprios dados e informações acadêmicas
    </div>
</body>
</html>
