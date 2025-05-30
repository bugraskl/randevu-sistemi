<?php
session_start();
require_once '../config/env.php';
require_once '../config/database.php';
require_once '../includes/sms.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = filter_input(INPUT_POST, 'client_id', FILTER_SANITIZE_NUMBER_INT);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    $hour = filter_input(INPUT_POST, 'hour', FILTER_SANITIZE_STRING);
    $minute = filter_input(INPUT_POST, 'minute', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    $view = filter_input(INPUT_POST, 'view', FILTER_SANITIZE_STRING); // Hangi görünümden eklendiğini al

    if (empty($client_id) || empty($date) || empty($hour) || empty($minute)) {
        $_SESSION['error'] = "Lütfen tüm zorunlu alanları doldurun.";
        header('Location: ../appointments' . ($view === 'calendar' ? '?view=calendar' : ''));
        exit();
    }

    // Saat ve dakikayı birleştir
    $time = $hour . ':' . $minute . ':00';

    try {
        // Önce danışan bilgilerini al
        $stmt = $db->prepare("SELECT name, phone FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch();

        if (!$client) {
            $_SESSION['error'] = "Danışan bulunamadı.";
            header('Location: ../appointments' . ($view === 'calendar' ? '?view=calendar' : ''));
            exit();
        }

        // Randevuyu ekle
        $stmt = $db->prepare("INSERT INTO appointments (client_id, appointment_date, appointment_time, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$client_id, $date, $time, $notes]);
        
        // Randevu tarihini kontrol et
        $appointment_datetime = strtotime($date . ' ' . $time);
        $current_datetime = strtotime('now');

        if ($appointment_datetime < $current_datetime) {
            // Geçmiş tarih için SMS gönderme
            $_SESSION['success'] = "Geçmiş tarihli randevu başarıyla eklendi.";
        } else {
            // SMS aktif mi kontrol et
            if (!EnvConfig::getBool('SMS_ENABLED', true)) {
                $_SESSION['success'] = "Randevu başarıyla eklendi. (SMS gönderimi devre dışı)";
            } else {
                // Sadece gelecek randevular için SMS gönder
                if (sendAppointmentConfirmation($client['name'], $client['phone'], $date, $time)) {
                    $_SESSION['success'] = "Randevu başarıyla eklendi ve danışana SMS gönderildi.";
                } else {
                    $_SESSION['warning'] = "Randevu başarıyla eklendi fakat SMS gönderilemedi.";
                }
            }
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Bir hata oluştu: " . $e->getMessage();
    }
}

// Yönlendirme
header('Location: ../appointments' . ($view === 'calendar' ? '?view=calendar' : ''));
exit();
?> 