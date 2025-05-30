<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['date'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Date parameter is required']);
    exit();
}

$date = $_GET['date'];

try {
    $stmt = $db->prepare("
        SELECT a.*, c.name as client_name 
        FROM appointments a 
        JOIN clients c ON a.client_id = c.id 
        WHERE a.appointment_date = ?
        ORDER BY a.appointment_time ASC
    ");
    $stmt->execute([$date]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tarih formatını düzenle
    foreach ($appointments as &$appointment) {
        $appointment['appointment_date'] = date('d.m.Y', strtotime($appointment['appointment_date']));
        $appointment['appointment_time'] = date('H:i', strtotime($appointment['appointment_time']));
    }

    echo json_encode($appointments);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 