<?php
require_once '../config/database.php';
require_once '../config/auth.php';

verificarAdmin();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID do professor não fornecido']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM professores WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $professor = $stmt->fetch();
    
    if (!$professor) {
        http_response_code(404);
        echo json_encode(['error' => 'Professor não encontrado']);
        exit;
    }
    
    // Remover senha do retorno por segurança
    unset($professor['password']);
    
    echo json_encode($professor);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}
?>
