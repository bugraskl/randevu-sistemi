<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['appointment_id'])) {
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_SANITIZE_NUMBER_INT);

    try {
        $stmt = $db->prepare("DELETE FROM appointments WHERE id = ?");
        $stmt->execute([$appointment_id]);
        
        $_SESSION['success'] = "Randevu başarıyla silindi.";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Bir hata oluştu: " . $e->getMessage();
    }
}

header('Location: ../appointments');
exit();
?> 