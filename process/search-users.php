<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Admin kontrolü
try {
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
    
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit();
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit();
}

$search = $_GET['search'] ?? '';

try {
    if (empty($search)) {
        // Tüm kullanıcıları getir
        $stmt = $db->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT 50");
        $stmt->execute();
    } else {
        // Arama yap
        $searchTerm = '%' . $search . '%';
        $stmt = $db->prepare("
            SELECT * FROM users 
            WHERE name LIKE ? OR email LIKE ? 
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $stmt->execute([$searchTerm, $searchTerm]);
    }
    
    $users = $stmt->fetchAll();
    
    header('Content-Type: application/json');
    echo json_encode(['users' => $users]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 