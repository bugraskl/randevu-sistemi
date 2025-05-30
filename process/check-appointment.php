<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Oturum bulunamadı']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    $time = filter_input(INPUT_POST, 'time', FILTER_SANITIZE_STRING);
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_SANITIZE_NUMBER_INT);

    if (empty($date) || empty($time)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Tarih ve saat gereklidir']);
        exit();
    }

    // Bugünün tarihini kontrol et
    $today = date('Y-m-d');
    if ($date < $today) {
        header('Content-Type: application/json');
        echo json_encode([
            'available' => false,
            'message' => 'Geçmiş bir tarih seçemezsiniz.'
        ]);
        exit();
    }

    try {
        // Seçilen saatten 59 dakika önce ve sonra randevu var mı kontrol et
        $sql = "
            SELECT * FROM appointments 
            WHERE appointment_date = ? 
            AND (
                (appointment_time BETWEEN 
                    SUBTIME(?, '00:59:00') AND 
                    ADDTIME(?, '00:59:00')
                )
                OR appointment_time = ?
            )
        ";
        $params = [$date, $time, $time, $time];

        // Eğer düzenleme işlemiyse, mevcut randevuyu hariç tut
        if ($appointment_id) {
            $sql .= " AND id != ?";
            $params[] = $appointment_id;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $existing_appointments = $stmt->fetchAll();

        if (count($existing_appointments) > 0) {
            header('Content-Type: application/json');
            echo json_encode([
                'available' => false,
                'message' => 'Bu saatte veya yakın saatlerde başka bir randevu bulunmaktadır. Lütfen farklı bir saat seçin.'
            ]);
            exit();
        }

        header('Content-Type: application/json');
        echo json_encode(['available' => true]);
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Bir hata oluştu: ' . $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Geçersiz istek']);
}
?> 