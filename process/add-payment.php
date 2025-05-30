<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = $_POST['appointment_id'] ?? null;
    $amount = $_POST['amount'] ?? null;
    $payment_method = $_POST['payment_method'] ?? null;
    $notes = $_POST['notes'] ?? null;

    if (!$appointment_id || !$amount || !$payment_method) {
        $_SESSION['error'] = "Lütfen tüm gerekli alanları doldurun.";
        header('Location: ../payments');
        exit();
    }

    try {
        // Ödeme kaydı oluştur
        $stmt = $db->prepare("
            INSERT INTO payments (appointment_id, amount, payment_method, payment_date, notes)
            VALUES (?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([$appointment_id, $amount, $payment_method, $notes]);

        $_SESSION['success'] = "Ödeme başarıyla kaydedildi.";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Ödeme kaydedilirken bir hata oluştu: " . $e->getMessage();
    }
}

header('Location: ../payments');
exit(); 