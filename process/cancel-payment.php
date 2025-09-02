<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_id'])) {
    try {
        // Ödeme bilgilerini al (log için)
        $stmt = $db->prepare("
            SELECT p.*, a.appointment_date, a.appointment_time, c.name as client_name 
            FROM payments p
            JOIN appointments a ON p.appointment_id = a.id
            JOIN clients c ON a.client_id = c.id
            WHERE p.id = ?
        ");
        $stmt->execute([$_POST['payment_id']]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            $_SESSION['error'] = 'Ödeme kaydı bulunamadı.';
            header('Location: ../payments');
            exit();
        }
        
        // Ödeme kaydını sil
        $stmt = $db->prepare("DELETE FROM payments WHERE id = ?");
        $result = $stmt->execute([$_POST['payment_id']]);
        
        if ($result) {
            $_SESSION['success'] = 
                $payment['client_name'] . ' isimli danışanın ' . 
                date('d.m.Y H:i', strtotime($payment['appointment_date'] . ' ' . $payment['appointment_time'])) . 
                ' tarihli randevusu için alınan ' . 
                number_format($payment['amount'], 2, ',', '.') . ' ₺ ödeme iptal edildi.';
            
            // Log kaydet
            error_log("Payment cancelled - ID: " . $payment['id'] . ", Amount: " . $payment['amount'] . ", Client: " . $payment['client_name'] . ", Date: " . $payment['appointment_date']);
        } else {
            $_SESSION['error'] = 'Ödeme iptal edilirken bir hata oluştu.';
        }
        
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Ödeme iptal edilirken bir hata oluştu: ' . $e->getMessage();
        error_log("Payment cancel error: " . $e->getMessage());
    }
} else {
    $_SESSION['error'] = 'Geçersiz istek.';
}

// Geri yönlendir
if (isset($_POST['redirect']) && $_POST['redirect'] === 'client-details') {
    header('Location: ../client-details?id=' . $_POST['client_id']);
} else {
    header('Location: ../payments');
}
exit();