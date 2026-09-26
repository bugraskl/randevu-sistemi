<?php
session_start();
require_once '../config/database.php';
require_once '../includes/settings.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index');
    exit();
}

// Yalnızca yönetici (rol veritabanından doğrulanır)
try {
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $role = $stmt->fetchColumn();
} catch (PDOException $e) {
    $role = null;
}
if ($role !== 'admin') {
    $_SESSION['error'] = 'Bu işlem için yetkiniz bulunmuyor.';
    header('Location: ../dashboard');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../app-settings');
    exit();
}

// Ücret: "3.000", "3000,50" gibi girişleri de kabul et
$feeRaw = trim((string) ($_POST['session_fee'] ?? ''));
$feeRaw = str_replace([' ', '₺'], '', $feeRaw);
if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $feeRaw)) {
    $feeRaw = str_replace('.', '', $feeRaw);
}
$feeRaw = str_replace(',', '.', $feeRaw);
$minutesRaw = trim((string) ($_POST['session_minutes'] ?? ''));

$errors = [];
if (!is_numeric($feeRaw) || (float) $feeRaw < 1 || (float) $feeRaw > 1000000) {
    $errors[] = 'Seans ücreti 1 ile 1.000.000 ₺ arasında olmalı.';
}
if (!ctype_digit($minutesRaw) || (int) $minutesRaw < 10 || (int) $minutesRaw > 240) {
    $errors[] = 'Seans süresi 10 ile 240 dakika arasında olmalı.';
}

if ($errors) {
    $_SESSION['error'] = implode(' ', $errors);
    header('Location: ../app-settings');
    exit();
}

try {
    saveSettings($db, [
        'session_fee' => round((float) $feeRaw, 2),
        'session_minutes' => (int) $minutesRaw,
    ]);
    $_SESSION['success'] = 'Seans ayarları kaydedildi.';
} catch (PDOException $e) {
    $_SESSION['error'] = 'Ayarlar kaydedilemedi: ' . $e->getMessage();
}

header('Location: ../app-settings');
exit();
