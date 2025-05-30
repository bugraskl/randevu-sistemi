<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_id = $_POST['payment_id'] ?? null;
    $amount = $_POST['amount'] ?? null;
    $payment_method = $_POST['payment_method'] ?? null;
    $notes = $_POST['notes'] ?? null;

    if (!$payment_id || !$amount || !$payment_method) {
        $_SESSION['error'] = "Lütfen tüm gerekli alanları doldurun.";
        header('Location: ../payments');
        exit();
    }

    try {
        // Ödeme kaydını güncelle
        $stmt = $db->prepare("
            UPDATE payments 
            SET amount = ?, payment_method = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->execute([$amount, $payment_method, $notes, $payment_id]);

        $_SESSION['success'] = "Ödeme başarıyla güncellendi.";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Ödeme güncellenirken bir hata oluştu: " . $e->getMessage();
    }
}

header('Location: ../payments');
exit(); 