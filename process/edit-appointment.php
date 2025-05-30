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
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_SANITIZE_NUMBER_INT);
    $client_id = filter_input(INPUT_POST, 'client_id', FILTER_SANITIZE_NUMBER_INT);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    $hour = filter_input(INPUT_POST, 'hour', FILTER_SANITIZE_STRING);
    $minute = filter_input(INPUT_POST, 'minute', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

    if (empty($appointment_id) || empty($client_id) || empty($date) || empty($hour) || empty($minute)) {
        $_SESSION['error'] = "Lütfen tüm zorunlu alanları doldurun.";
        header('Location: ../appointments');
        exit();
    }

    // Saat ve dakikayı birleştir
    $time = $hour . ':' . $minute . ':00';

    try {
        // Önce mevcut randevuyu kontrol et
        $stmt = $db->prepare("SELECT * FROM appointments WHERE id = ?");
        $stmt->execute([$appointment_id]);
        $current_appointment = $stmt->fetch();

        if (!$current_appointment) {
            $_SESSION['error'] = "Randevu bulunamadı.";
            header('Location: ../appointments');
            exit();
        }

        // Eğer tarih veya saat değiştiyse, çakışma kontrolü yap
        if ($current_appointment['appointment_date'] != $date || $current_appointment['appointment_time'] != $time) {
            $stmt = $db->prepare("
                SELECT * FROM appointments 
                WHERE id != ? 
                AND appointment_date = ? 
                AND (
                    (appointment_time BETWEEN SUBTIME(?, '00:59:00') AND ADDTIME(?, '00:59:00'))
                    OR appointment_time = ?
                )
            ");
            $stmt->execute([$appointment_id, $date, $time, $time, $time]);
            $existing_appointments = $stmt->fetchAll();

            if (count($existing_appointments) > 0) {
                $_SESSION['error'] = "Bu saatte veya yakın saatlerde başka bir randevu bulunmaktadır.";
                header('Location: ../appointments');
                exit();
            }
        }

        // Danışan bilgilerini al
        $stmt = $db->prepare("SELECT name, phone FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch();

        if (!$client) {
            $_SESSION['error'] = "Danışan bulunamadı.";
            header('Location: ../appointments');
            exit();
        }

        // Randevuyu güncelle
        $stmt = $db->prepare("
            UPDATE appointments 
            SET client_id = ?, appointment_date = ?, appointment_time = ?, notes = ? 
            WHERE id = ?
        ");
        $stmt->execute([$client_id, $date, $time, $notes, $appointment_id]);
        
        // Eğer tarih veya saat değiştiyse SMS gönder
        if ($current_appointment['appointment_date'] != $date || $current_appointment['appointment_time'] != $time) {
            // Randevu tarihini kontrol et
            $appointment_datetime = strtotime($date . ' ' . $time);
            $current_datetime = strtotime('now');

            if ($appointment_datetime < $current_datetime) {
                // Geçmiş tarih için SMS gönderme
                $_SESSION['success'] = "Randevu başarıyla güncellendi.";
            } else {
                // SMS aktif mi kontrol et
                if (!EnvConfig::getBool('SMS_ENABLED', true)) {
                    $_SESSION['success'] = "Randevu başarıyla güncellendi. (SMS gönderimi devre dışı)";
                } else {
                    // Sadece gelecek randevular için SMS gönder
                    if (sendAppointmentConfirmation($client['name'], $client['phone'], $date, $time)) {
                        $_SESSION['success'] = "Randevu başarıyla güncellendi ve danışana SMS gönderildi.";
                    } else {
                        $_SESSION['warning'] = "Randevu başarıyla güncellendi fakat SMS gönderilemedi.";
                    }
                }
            }
        } else {
            $_SESSION['success'] = "Randevu başarıyla güncellendi.";
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Bir hata oluştu: " . $e->getMessage();
    }
}

header('Location: ../appointments');
exit();
?> 