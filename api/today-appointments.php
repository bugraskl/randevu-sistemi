<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$expectedToken = EnvConfig::get('API_TOKEN');

$providedToken = $_POST['token'] ?? null;
if ($providedToken === null) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $jsonData = json_decode($rawInput, true);
        if (is_array($jsonData) && isset($jsonData['token'])) {
            $providedToken = $jsonData['token'];
        }
    }
}

if (empty($expectedToken) || !is_string($providedToken) || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $stmt = $db->prepare("
        SELECT a.id, a.client_id, a.appointment_date, a.appointment_time, a.status, a.notes,
               TIME_FORMAT(a.appointment_time, '%H:%i') AS formatted_time,
               c.name AS client_name, c.phone AS client_phone
        FROM appointments a
        JOIN clients c ON a.client_id = c.id
        WHERE a.appointment_date = CURDATE()
        ORDER BY a.appointment_time ASC
    ");
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'date' => date('Y-m-d'),
        'count' => count($appointments),
        'appointments' => $appointments
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
