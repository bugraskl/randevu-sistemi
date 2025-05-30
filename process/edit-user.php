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
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    // Validasyon
    if (!$user_id || empty($name) || empty($email) || empty($role) || empty($status)) {
        $_SESSION['error'] = "Gerekli alanları doldurunuz.";
        header('Location: ../user-management');
        exit();
    }

    if (!empty($password) && strlen($password) < 6) {
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
        // Kullanıcı varlık kontrolü
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $existingUser = $stmt->fetch();
        
        if (!$existingUser) {
            $_SESSION['error'] = "Kullanıcı bulunamadı.";
            header('Location: ../user-management');
            exit();
        }

        // E-posta kontrolü (diğer kullanıcılarla çakışma)
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.";
            header('Location: ../user-management');
            exit();
        }

        // Kullanıcıyı güncelle
        if (!empty($password)) {
            // Şifre de güncellenecek
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                UPDATE users 
                SET name = ?, email = ?, password = ?, role = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$name, $email, $hashedPassword, $role, $status, $user_id]);
        } else {
            // Şifre güncellenmeyecek
            $stmt = $db->prepare("
                UPDATE users 
                SET name = ?, email = ?, role = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$name, $email, $role, $status, $user_id]);
        }
        
        $_SESSION['success'] = "Kullanıcı başarıyla güncellendi.";
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