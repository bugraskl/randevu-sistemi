<?php
session_start();
require_once 'includes/Database.php';
require_once 'includes/SessionManager.php';
require_once 'config/env.php';

// Eğer kullanıcı zaten giriş yapmışsa dashboard'a yönlendir
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard');
    exit;
}

// Beni Hatırla token'ı varsa kontrol et
if (isset($_COOKIE['remember_token'])) {
    $db = new Database();
    $conn = $db->getConnection();
    $sessionManager = new SessionManager($conn);
    
    $userId = $sessionManager->validateRememberToken($_COOKIE['remember_token']);
    if ($userId) {
        // Kullanıcı bilgilerini al
        try {
            $stmt = $conn->prepare("SELECT id, role, status FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $user['status'] === 'active') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_status'] = $user['status'];
                header('Location: dashboard');
                exit;
            } else {
                // Kullanıcı pasif ise token'ı sil
                $sessionManager->deleteRememberToken($_COOKIE['remember_token']);
            }
        } catch (PDOException $e) {
            error_log("User fetch error: " . $e->getMessage());
        }
    } else {
        // Geçersiz token varsa cookie'yi sil
        $domain = EnvConfig::get('APP_DOMAIN', 'localhost');
        
        $cookieOptions = [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => $domain,
            'secure' => EnvConfig::get('APP_ENV') === 'production',
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        setcookie('remember_token', '', $cookieOptions);
        unset($_COOKIE['remember_token']);
    }
}

include "includes/header.php";
?>

<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-4">
                <?php
                if (isset($_SESSION['error'])) {
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            ' . $_SESSION['error'] . '
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                          </div>';
                    unset($_SESSION['error']);
                }
                if (isset($_SESSION['success'])) {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert"> 
                            ' . $_SESSION['success'] . '
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                          </div>';
                    unset($_SESSION['success']);
                }
                ?>
                <div class="card shadow-lg">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Giriş Yap</h2>
                        <form action="auth/login" method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-posta</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                                <div class="invalid-feedback">
                                    Lütfen geçerli bir e-posta adresi giriniz.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Şifre</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="invalid-feedback">
                                    Lütfen şifrenizi giriniz.
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Beni Hatırla</label>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Giriş Yap</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 