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

$themeClass = (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? 'dark' : '';
$pageTitle = 'Giriş';
$themeColor = '#4B1D35';

$gunler = ['Monday' => 'Pazartesi', 'Tuesday' => 'Salı', 'Wednesday' => 'Çarşamba', 'Thursday' => 'Perşembe', 'Friday' => 'Cuma', 'Saturday' => 'Cumartesi', 'Sunday' => 'Pazar'];
$aylar = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

include "includes/header.php";
?>
<body class="<?php echo $themeClass; ?>">
    <div class="login">
        <section class="login-hero wool" aria-label="Randevu Yönetim Sistemi">
            <span class="login-mark" aria-hidden="true"><i class="bi bi-clock"></i></span>
            <time class="login-clock" data-live-clock datetime="<?php echo date('Y-m-d\TH:i'); ?>"><?php echo date('H:i'); ?></time>
            <h1>Randevu Yönetim Sistemi</h1>
            <p><?php echo date('j') . ' ' . $aylar[(int) date('n')] . ' ' . $gunler[date('l')]; ?></p>
        </section>

        <main class="login-body">
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
            </div>
            <?php unset($_SESSION['error']); endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
            </div>
            <?php unset($_SESSION['success']); endif; ?>

            <h2>Giriş yap</h2>
            <form action="auth/login" method="POST">
                <div class="field">
                    <label for="email" class="form-label">E-posta</label>
                    <input type="email" class="form-control" id="email" name="email" autocomplete="username" inputmode="email" required>
                </div>
                <div class="field">
                    <label for="password" class="form-label">Şifre</label>
                    <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                </div>
                <div class="field form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Bu cihazda oturumum açık kalsın</label>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block">Giriş yap</button>
            </form>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Saat canlı kalsın
    (function () {
        var el = document.querySelector('[data-live-clock]');
        if (!el) return;
        setInterval(function () {
            var d = new Date();
            el.textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        }, 15000);
    })();
    </script>
</body>
</html>
