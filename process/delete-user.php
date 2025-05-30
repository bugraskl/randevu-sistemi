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

    if (!$user_id) {
        $_SESSION['error'] = "Geçersiz kullanıcı ID'si.";
        header('Location: ../user-management');
        exit();
    }

    // Kendi hesabını silmeye çalışıyor mu kontrolü
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "Kendi hesabınızı silemezsiniz.";
        header('Location: ../user-management');
        exit();
    }

    try {
        // Kullanıcı varlık kontrolü
        $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $_SESSION['error'] = "Kullanıcı bulunamadı.";
            header('Location: ../user-management');
            exit();
        }

        // Remember token'larını sil
        $stmt = $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // Kullanıcıyı sil
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $_SESSION['success'] = $user['name'] . " adlı kullanıcı başarıyla silindi.";
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