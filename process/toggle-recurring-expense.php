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

    $stmt = $db->prepare("UPDATE recurring_expenses SET active = IF(active=1,0,1) WHERE id=?");
    $stmt->execute([$id]);
    $_SESSION['success'] = 'Tekrarlayan gider durumu güncellendi.';
} catch(Exception $e) {
    $_SESSION['error'] = 'Durum güncellenemedi: ' . $e->getMessage();
}

header('Location: ../expenses');
exit();

