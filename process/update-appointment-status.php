<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['appointment_id']) && isset($_POST['status'])) {
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_SANITIZE_NUMBER_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    $allowed_statuses = ['beklemede', 'onaylandı', 'iptal', 'tamamlandı'];
    if (!in_array($status, $allowed_statuses)) {
        $_SESSION['error'] = "Geçersiz randevu durumu.";
        header('Location: ../appointments');
        exit();
    }

    try {
        $stmt = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->execute([$status, $appointment_id]);
        
        $_SESSION['success'] = "Randevu durumu başarıyla güncellendi.";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Bir hata oluştu: " . $e->getMessage();
    }
}

header('Location: ../appointments');
exit();
?> 