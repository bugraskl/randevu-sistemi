<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../expenses');
    exit();
}

try {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception('Geçersiz kayıt.');

    $stmt = $db->prepare("DELETE FROM expenses WHERE id=?");
    $stmt->execute([$id]);
    $_SESSION['success'] = 'Gider silindi.';
} catch(Exception $e) {
    $_SESSION['error'] = 'Gider silinemedi: ' . $e->getMessage();
}

header('Location: ../expenses');
exit();

