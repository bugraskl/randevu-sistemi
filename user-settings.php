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

// Header'ı dahil et
include 'includes/header.php';
?>

<body class="<?php echo $themeClass; ?>">
    <div class="wrapper">
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay"></div>
        
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        <!-- Page Content -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-secondary">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="ms-auto">
                        <button type="button" id="themeToggle" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-moon-fill"></i>
                        </button>
                        <a href="auth/logout" class="btn btn-outline-danger">
                            <i class="bi bi-box-arrow-right"></i> Çıkış Yap
                        </a>
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Kullanıcı Ayarları</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="name" class="form-label">İsim</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($userName); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($userEmail); ?>" required>
                            </div>
                            
                            <hr class="my-4">
                            <h6 class="mb-3">Şifre Değiştir</h6>
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Mevcut Şifre</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Yeni Şifre</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Yeni Şifre (Tekrar)</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?> 