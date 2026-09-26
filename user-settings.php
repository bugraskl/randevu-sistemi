<?php
session_start();
require_once 'config/database.php';

// Tema kontrolü
if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
    $themeClass = 'dark';
} else {
    $themeClass = '';
}

if (!isset($_SESSION['user_id'])) {
    header('Location: index');
    exit();
}

// Veritabanı şeması kontrolü
try {
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = ['name', 'email', 'password'];
    foreach ($requiredColumns as $column) {
        if (!in_array($column, $columns)) {
            $_SESSION['error'] = "Veritabanı şeması eksik. '$column' sütunu bulunamadı.";
            header('Location: dashboard');
            exit();
        }
    }
} catch(PDOException $e) {
    $_SESSION['error'] = "Veritabanı kontrolü yapılırken bir hata oluştu.";
    header('Location: dashboard');
    exit();
}

// Kullanıcı bilgilerini veritabanından çek
try {
    $stmt = $db->prepare("SELECT id, name, email, password, role, status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION['error'] = "Kullanıcı bulunamadı.";
        header('Location: index');
        exit();
    }
    
    // Güvenli değişken atama
    $userName = $user['name'] ?? '';
    $userEmail = $user['email'] ?? '';
    $userPassword = $user['password'] ?? '';
    
    // Ek güvenlik kontrolleri
    if (empty($userName) && empty($userEmail)) {
        $_SESSION['error'] = "Kullanıcı verileri eksik veya bozuk.";
        header('Location: dashboard');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = "Kullanıcı bilgileri alınırken bir hata oluştu: " . $e->getMessage();
    header('Location: index');
    exit();
}

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $hasError = false;
    
    // İsim kontrolü
    if (empty($name)) {
        $_SESSION['error'] = "İsim alanı boş bırakılamaz.";
        $hasError = true;
    }
    
    // Email kontrolü
    if (empty($email)) {
        $_SESSION['error'] = "Email alanı boş bırakılamaz.";
        $hasError = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Geçerli bir email adresi giriniz.";
        $hasError = true;
    }
    
    // Email benzersizlik kontrolü
    if (!$hasError && $email !== $userEmail) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = "Bu email adresi başka bir kullanıcı tarafından kullanılıyor.";
            $hasError = true;
        }
    }
    
    // Şifre değişikliği yapılacaksa
    if (!$hasError && (!empty($new_password) || !empty($confirm_password))) {
        // Mevcut şifre kontrolü
        if (empty($current_password)) {
            $_SESSION['error'] = "Mevcut şifrenizi giriniz.";
            $hasError = true;
        } elseif (!password_verify($current_password, $userPassword)) {
            $_SESSION['error'] = "Mevcut şifreniz yanlış.";
            $hasError = true;
        }
        
        // Yeni şifre kontrolü
        if (!$hasError && empty($new_password)) {
            $_SESSION['error'] = "Yeni şifre alanı boş bırakılamaz.";
            $hasError = true;
        } elseif (!$hasError && strlen($new_password) < 6) {
            $_SESSION['error'] = "Yeni şifre en az 6 karakter olmalıdır.";
            $hasError = true;
        }
        
        // Şifre eşleşme kontrolü
        if (!$hasError && $new_password !== $confirm_password) {
            $_SESSION['error'] = "Yeni şifreler eşleşmiyor.";
            $hasError = true;
        }
    }
    
    // Hata yoksa güncelle
    if (!$hasError) {
        try {
            if (!empty($new_password)) {
                // Şifre değişikliği ile birlikte güncelle
                $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
                $stmt->execute([$name, $email, password_hash($new_password, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            } else {
                // Sadece isim ve email güncelle
                $stmt = $db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt->execute([$name, $email, $_SESSION['user_id']]);
            }
            
            $_SESSION['success'] = "Bilgileriniz başarıyla güncellendi.";
            header('Location: user-settings');
            exit();
        } catch(PDOException $e) {
            $_SESSION['error'] = "Bilgiler güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

$pageTitle = 'Hesap Ayarları';
$pageSubtitle = 'Ad, e-posta ve şifre';

// Header'ı dahil et
include 'includes/header.php';
?>
<body class="<?php echo $themeClass; ?>" data-page="user-settings">
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main id="content" tabindex="-1">
            <?php include 'includes/topbar.php'; ?>

            <div class="page page-narrow">
                <form method="POST" action="">
                    <section class="section" aria-labelledby="profileTitle">
                        <div class="section-head">
                            <h2 class="section-title" id="profileTitle">Profil</h2>
                        </div>
                        <div class="panel panel-pad">
                            <div class="field">
                                <label for="name" class="form-label">Ad soyad</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($userName); ?>" autocomplete="name" required>
                            </div>
                            <div class="field mb-0">
                                <label for="email" class="form-label">E-posta</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($userEmail); ?>" autocomplete="email" inputmode="email" required>
                                <div class="form-text">Giriş yaparken bu adresi kullanırsınız.</div>
                            </div>
                        </div>
                    </section>

                    <section class="section" aria-labelledby="passwordTitle">
                        <div class="section-head">
                            <h2 class="section-title" id="passwordTitle">Şifre değiştir</h2>
                        </div>
                        <div class="panel panel-pad">
                            <p class="form-text mt-0 mb-3">Şifrenizi değiştirmeyecekseniz bu alanları boş bırakın.</p>
                            <div class="field">
                                <label for="current_password" class="form-label">Mevcut şifre</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password">
                            </div>
                            <div class="field">
                                <label for="new_password" class="form-label">Yeni şifre</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" autocomplete="new-password" aria-describedby="newPasswordHelp">
                                <div class="form-text" id="newPasswordHelp">En az 6 karakter.</div>
                            </div>
                            <div class="field mb-0">
                                <label for="confirm_password" class="form-label">Yeni şifre (tekrar)</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password">
                            </div>
                        </div>
                    </section>

                    <div class="d-grid d-sm-flex justify-content-sm-end mt-4">
                        <button type="submit" class="btn btn-primary">Değişiklikleri kaydet</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

<?php include 'includes/footer.php'; ?>
