<?php
session_start();
require_once '../config/database.php';
require_once '../includes/phone.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$term = $_GET['term'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15; // Sayfa başına gösterilecek kayıt sayısı
$offset = ($page - 1) * $limit;

if (strlen($term) < 2) {
    echo json_encode([
        'success' => false,
        'message' => 'Lütfen en az 2 karakter giriniz.'
    ]);
    exit();
}

try {
    $searchTerm = "%{$term}%";
    $where = "name LIKE ? OR phone LIKE ? OR email LIKE ? OR address LIKE ?";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];

    // Telefon hangi biçimde yazılırsa yazılsın (500 123, +90 500..., 0500...) kayıtlı numarayla eşleşsin
    $phoneDigits = phoneSearchDigits($term);
    if ($phoneDigits !== null) {
        $where .= " OR phone LIKE ?";
        $params[] = "%{$phoneDigits}%";
    }

    // Önce toplam kayıt sayısını al
    $countStmt = $db->prepare("SELECT COUNT(*) FROM clients WHERE $where");
    $countStmt->execute($params);
    $total_records = $countStmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Sonra sayfalı sonuçları al
    $stmt = $db->prepare("
        SELECT id, name, phone, email, address, notes, created_at
        FROM clients
        WHERE $where
        ORDER BY name ASC
        LIMIT ? OFFSET ?
    ");

    $position = 1;
    foreach ($params as $param) {
        $stmt->bindValue($position++, $param, PDO::PARAM_STR);
    }
    $stmt->bindValue($position++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($position, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sonuçları formatla
    foreach ($results as &$client) {
        $client['created_at_formatted'] = date('d.m.Y H:i', strtotime($client['created_at']));
        
        // Randevu sayısını al
        $appointmentStmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE client_id = ?");
        $appointmentStmt->execute([$client['id']]);
        $client['appointment_count'] = $appointmentStmt->fetchColumn();
    }
    
    echo json_encode([
        'success' => true,
        'clients' => $results,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total_records,
            'has_next' => $page < $total_pages,
            'has_prev' => $page > 1
        ],
        'search_term' => $term
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} 