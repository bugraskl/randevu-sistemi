<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index');
    exit();
}

// Admin kontrolü
try {
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
    
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        $_SESSION['error'] = "Bu işlemi gerçekleştirmek için yetkiniz bulunmamaktadır.";
        header('Location: ../user-management');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = "Yetki kontrolü yapılırken bir hata oluştu.";
    header('Location: ../user-management');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    // Validasyon
    if (empty($name) || empty($email) || empty($password) || empty($role) || empty($status)) {
        $_SESSION['error'] = "Tüm alanları doldurunuz.";
        header('Location: ../user-management');
        exit();
    }

    if (strlen($password) < 6) {
        $_SESSION['error'] = "Şifre en az 6 karakter olmalıdır.";
        header('Location: ../user-management');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Geçerli bir e-posta adresi giriniz.";
        header('Location: ../user-management');
        exit();
    }

    if (!in_array($role, ['admin', 'user'])) {
        $_SESSION['error'] = "Geçersiz rol seçimi.";
        header('Location: ../user-management');
        exit();
    }

    if (!in_array($status, ['active', 'inactive'])) {
        $_SESSION['error'] = "Geçersiz durum seçimi.";
        header('Location: ../user-management');
        exit();
    }

    try {
        // E-posta kontrolü
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Bu e-posta adresi zaten kayıtlı.";
            header('Location: ../user-management');
            exit();
        }

        // Şifreyi hash'le
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Kullanıcıyı ekle
        $stmt = $db->prepare("
            INSERT INTO users (name, email, password, role, status) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $email, $hashedPassword, $role, $status]);
        
        $_SESSION['success'] = "Kullanıcı başarıyla eklendi.";
        header('Location: ../user-management');
        exit();
    } catch(PDOException $e) {
        $_SESSION['error'] = "Bir hata oluştu: " . $e->getMessage();
        header('Location: ../user-management');
        exit();
    }
} else {
    header('Location: ../user-management');
    exit();
}
?> 