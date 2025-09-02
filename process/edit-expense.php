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
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');

    if ($id <= 0 || $title === '' || $amount <= 0) {
        throw new Exception('Geçersiz veri.');
    }

    $stmt = $db->prepare("UPDATE expenses SET title=?, category=?, amount=?, payment_method=?, expense_date=?, notes=? WHERE id=?");
    $stmt->execute([$title, $category ?: null, $amount, $payment_method, $expense_date, $notes ?: null, $id]);

    $_SESSION['success'] = 'Gider güncellendi.';
} catch(Exception $e) {
    $_SESSION['error'] = 'Gider güncellenemedi: ' . $e->getMessage();
}

header('Location: ../expenses');
exit();

