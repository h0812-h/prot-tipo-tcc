<?php
session_start();

// Verificar se o usuário está logado
function verificarLogin() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Verificar se é administrador
function verificarAdmin() {
    verificarLogin();
    if ($_SESSION['tipo'] !== 'administrador') {
        header('Location: dashboard.php');
        exit();
    }
}

// Verificar se é administrador ou professor
function verificarAdminOuProfessor() {
    verificarLogin();
    if (!in_array($_SESSION['tipo'], ['administrador', 'professor'])) {
        header('Location: dashboard.php');
        exit();
    }
}

// Fazer logout
function logout() {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Obter dados do usuário logado
function getUsuarioLogado() {
    if (!isset($_SESSION['usuario_id'])) {
        return null;
    }
    
    return [
        'id' => $_SESSION['usuario_id'],
        'nome' => $_SESSION['nome'],
        'tipo' => $_SESSION['tipo'],
        'email' => $_SESSION['email']
    ];
}

// Verificar se é o próprio aluno ou admin
function verificarAcessoAluno($aluno_id) {
    verificarLogin();
    
    if ($_SESSION['tipo'] === 'administrador') {
        return true;
    }
    
    if ($_SESSION['tipo'] === 'aluno' && $_SESSION['aluno_id'] == $aluno_id) {
        return true;
    }
    
    header('Location: dashboard.php');
    exit();
}
?>
