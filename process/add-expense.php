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
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');

    if ($title === '' || $amount <= 0) {
        throw new Exception('Başlık ve tutar zorunludur.');
    }

    $stmt = $db->prepare("INSERT INTO expenses (title, category, amount, payment_method, expense_date, notes) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$title, $category ?: null, $amount, $payment_method, $expense_date, $notes ?: null]);

    $_SESSION['success'] = 'Gider başarıyla eklendi.';
} catch(Exception $e) {
    $_SESSION['error'] = 'Gider eklenemedi: ' . $e->getMessage();
}

header('Location: ../expenses');
exit();

