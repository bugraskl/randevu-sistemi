<?php
session_start();
require_once '../includes/Database.php';
require_once '../includes/SessionManager.php';

// Remember token varsa sil
if (isset($_COOKIE['remember_token'])) {
    $db = new Database();
    $sessionManager = new SessionManager($db->getConnection());
    $sessionManager->deleteRememberToken($_COOKIE['remember_token']);
}

// Session'ı temizle
session_destroy();

// Ana sayfaya yönlendir
header('Location: ../index');
exit;