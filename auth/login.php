<?php
session_start();
require_once '../config/env.php';
require_once '../includes/Database.php';
require_once '../includes/SessionManager.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    $db = new Database();
    $conn = $db->getConnection();
    $sessionManager = new SessionManager($conn);

    try {
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            
            // Beni Hatırla seçeneği işaretlendiyse
            if ($remember) {
                $token = $sessionManager->createRememberToken($user['id']);
                
                // Environment'den domain bilgisini al
                $domain = EnvConfig::get('APP_DOMAIN', 'localhost');
                
                // Cookie ayarları
                $cookieOptions = [
                    'expires' => time() + (30 * 24 * 60 * 60), // 30 gün
                    'path' => '/',
                    'domain' => $domain,
                    'secure' => EnvConfig::get('APP_ENV') === 'production',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ];
                
                // Önce cookie'yi sil
                setcookie('remember_token', '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => $domain,
                    'secure' => EnvConfig::get('APP_ENV') === 'production',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                
                // Sonra yeni cookie'yi ayarla
                setcookie('remember_token', $token, $cookieOptions);
                
                // Cookie'nin ayarlandığından emin olmak için
                $_COOKIE['remember_token'] = $token;
            }

            header('Location: ../dashboard');
            exit;
        } else {
            $_SESSION['error'] = 'Geçersiz e-posta veya şifre.';
            header('Location: ../index');
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
        error_log("Login error: " . $e->getMessage());
        header('Location: ../index');
        exit;
    }
} else {
    header('Location: ../index');
    exit;
}
?> 