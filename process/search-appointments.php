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

// Tarih formatını doğrula
if (!DateTime::createFromFormat('Y-m-d', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format']);
    exit();
}

try {
    $stmt = $db->prepare("
        SELECT a.*, c.name as client_name, c.phone as client_phone,
               CASE 
                   WHEN a.appointment_date < CURDATE() THEN 'past'
                   WHEN a.appointment_date = CURDATE() THEN 'today'
                   ELSE 'future'
               END as date_status,
               TIME_FORMAT(a.appointment_time, '%H:%i') as formatted_time
        FROM appointments a 
        JOIN clients c ON a.client_id = c.id 
        WHERE a.appointment_date = ?
        ORDER BY a.appointment_time ASC
    ");
    $stmt->execute([$date]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Gün isimleri
    $gunler = [
        'Monday' => 'Pazartesi',
        'Tuesday' => 'Salı',
        'Wednesday' => 'Çarşamba',
        'Thursday' => 'Perşembe',
        'Friday' => 'Cuma',
        'Saturday' => 'Cumartesi',
        'Sunday' => 'Pazar'
    ];

    // Tarih formatını düzenle ve ek bilgiler ekle
    foreach ($appointments as &$appointment) {
        $appointmentDate = new DateTime($appointment['appointment_date']);
        $appointment['formatted_date'] = $appointmentDate->format('d.m.Y');
        $appointment['day_name'] = $gunler[$appointmentDate->format('l')];
        
        // Randevu durumunu belirle
        $appointmentDateTime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
        $now = strtotime('now');
        
        if ($appointmentDateTime < $now) {
            $appointment['status_badge'] = '<span class="badge bg-secondary">Geçmiş</span>';
        } elseif ($appointment['appointment_date'] === date('Y-m-d')) {
            $appointment['status_badge'] = '<span class="badge bg-primary">Bugün</span>';
        } else {
            $appointment['status_badge'] = '<span class="badge bg-success">Gelecek</span>';
        }
    }

    echo json_encode([
        'success' => true,
        'appointments' => $appointments,
        'date' => $date,
        'formatted_date' => date('d.m.Y', strtotime($date)),
        'day_name' => $gunler[date('l', strtotime($date))]
    ]);

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 