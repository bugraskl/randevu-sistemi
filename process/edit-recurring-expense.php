<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../expenses');
    exit();
}

try {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    $end_date = $_POST['end_date'] ?? null;
    $recurrence_interval = $_POST['recurrence_interval'] ?? 'monthly';
    $notes = trim($_POST['notes'] ?? '');

    if ($id <= 0 || $title === '' || $amount <= 0) {
        throw new Exception('Geçersiz veri.');
    }

    $stmt = $db->prepare("UPDATE recurring_expenses SET title=?, category=?, amount=?, payment_method=?, start_date=?, end_date=?, recurrence_interval=?, notes=? WHERE id=?");
    $stmt->execute([$title, $category ?: null, $amount, $payment_method, $start_date, $end_date ?: null, $recurrence_interval, $notes ?: null, $id]);

    $_SESSION['success'] = 'Tekrarlayan gider güncellendi.';
} catch(Exception $e) {
    $_SESSION['error'] = 'Tekrarlayan gider güncellenemedi: ' . $e->getMessage();
}

header('Location: ../expenses');
exit();

