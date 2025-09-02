<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../expenses');
    exit();
}

try {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    $end_date = $_POST['end_date'] ?? null;
    $recurrence_interval = $_POST['recurrence_interval'] ?? 'monthly';
    $notes = trim($_POST['notes'] ?? '');

    if ($title === '' || $amount <= 0) {
        throw new Exception('Başlık ve tutar zorunludur.');
    }

    $stmt = $db->prepare("INSERT INTO recurring_expenses (title, category, amount, payment_method, start_date, end_date, recurrence_interval, notes) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$title, $category ?: null, $amount, $payment_method, $start_date, $end_date ?: null, $recurrence_interval, $notes ?: null]);

    $_SESSION['success'] = 'Tekrarlayan gider oluşturuldu.';
} catch(Exception $e) {
    $_SESSION['error'] = 'Tekrarlayan gider eklenemedi: ' . $e->getMessage();
}

header('Location: ../expenses');
exit();

