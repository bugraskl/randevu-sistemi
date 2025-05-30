<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

    if (empty($name) || empty($phone)) {
        $_SESSION['error'] = "Ad Soyad ve Telefon alanları zorunludur.";
        header('Location: ../clients');
        exit();
    }

    try {
        // Telefon kontrolü
        $stmt = $db->prepare("SELECT * FROM clients WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Bu telefon numarası zaten kayıtlı.";
            header('Location: ../clients');
            exit();
        }

        // E-posta kontrolü (sadece e-posta girilmişse)
        if (!empty($email)) {
            $stmt = $db->prepare("SELECT * FROM clients WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = "Bu e-posta adresi zaten kayıtlı.";
                header('Location: ../clients');
                exit();
            }
        }

        // Danışanı ekle
        $stmt = $db->prepare("
            INSERT INTO clients (name, phone, email, address, notes) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $phone, $email, $address, $notes]);
        
        $_SESSION['success'] = "Danışan başarıyla eklendi.";
        header('Location: ../clients');
        exit();
    } catch(PDOException $e) {
        $_SESSION['error'] = "Bir hata oluştu: " . $e->getMessage();
        header('Location: ../clients');
        exit();
    }
}
?> 