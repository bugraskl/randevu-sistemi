<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['client_id'])) {
    $client_id = filter_input(INPUT_POST, 'client_id', FILTER_SANITIZE_NUMBER_INT);

    try {
        $stmt = $db->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        
        $_SESSION['success'] = "Danışan başarıyla silindi.";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Bir hata oluştu: " . $e->getMessage();
    }
}

header('Location: ../clients');
exit();
?> 