<?php
session_start();
require_once '../config/database.php';
require_once '../includes/phone.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = filter_input(INPUT_POST, 'client_id', FILTER_SANITIZE_NUMBER_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

    // Validasyon kontrolleri
    $errors = [];

    if (empty($name)) {
        $errors[] = "Ad Soyad alanı boş bırakılamaz.";
    }

    if (empty($phone)) {
        $errors[] = "Telefon alanı boş bırakılamaz.";
    } else {
        // Telefonu tek biçime çevir (ör. +90 500 123 45 67 → 05001234567)
        $normalizedPhone = normalizePhone($phone);
        if ($normalizedPhone === null) {
            $errors[] = PHONE_FORMAT_HINT;
        } else {
            $phone = $normalizedPhone;
        }
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Geçersiz e-posta adresi formatı.";
    }

    // Eğer validasyon hataları varsa
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
        header('Location: ../clients');
        exit();
    }

    try {
        // Telefon kontrolü (kendi ID'si hariç)
        $stmt = $db->prepare("SELECT * FROM clients WHERE phone = ? AND id != ?");
        $stmt->execute([$phone, $client_id]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Bu telefon numarası başka bir danışana ait.";
            header('Location: ../clients');
            exit();
        }

        // E-posta kontrolü (sadece e-posta girilmişse ve kendi ID'si hariç)
        if (!empty($email)) {
            $stmt = $db->prepare("SELECT * FROM clients WHERE email = ? AND id != ?");
            $stmt->execute([$email, $client_id]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = "Bu e-posta adresi başka bir danışana ait.";
                header('Location: ../clients');
                exit();
            }
        }

        // Danışanı güncelle
        $stmt = $db->prepare("
            UPDATE clients 
            SET name = ?, phone = ?, email = ?, address = ?, notes = ? 
            WHERE id = ?
        ");
        
        if ($stmt->execute([$name, $phone, $email, $address, $notes, $client_id])) {
            if ($stmt->rowCount() > 0) {
                $_SESSION['success'] = "Danışan bilgileri başarıyla güncellendi.";
            } else {
                $_SESSION['warning'] = "Hiçbir değişiklik yapılmadı.";
            }
        } else {
            throw new PDOException("Güncelleme işlemi başarısız oldu.");
        }
        
    } catch(PDOException $e) {
        $_SESSION['error'] = "Veritabanı hatası: " . $e->getMessage();
    }
}

header('Location: ../clients');
exit();
?> 