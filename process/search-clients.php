<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$term = $_GET['term'] ?? '';

if (strlen($term) < 2) {
    echo json_encode([]);
    exit();
}

try {
    $stmt = $db->prepare("
        SELECT id, name, phone, email 
        FROM clients 
        WHERE name LIKE ? OR phone LIKE ? OR email LIKE ?
        ORDER BY name ASC
    ");
    
    $searchTerm = "%{$term}%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
} 