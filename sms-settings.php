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

// Admin rolü kontrolü
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error'] = 'Bu sayfaya erişim yetkiniz bulunmuyor.';
    header('Location: dashboard');
    exit();
}

// Şablonları veritabanından çek
try {
    $stmt = $db->query("SELECT * FROM sms_templates ORDER BY id");
    $templates = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "SMS şablonları alınırken bir hata oluştu: " . $e->getMessage();
    $templates = [];
}

// Şablon güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_template'])) {
    try {
        $stmt = $db->prepare("UPDATE sms_templates SET template_text = ? WHERE id = ?");
        $stmt->execute([$_POST['template_text'], $_POST['template_id']]);
        $_SESSION['success'] = "SMS şablonu başarıyla güncellendi.";
        header('Location: sms-settings');
        exit();
    } catch(PDOException $e) {
        $_SESSION['error'] = "SMS şablonu güncellenirken bir hata oluştu: " . $e->getMessage();
    }
}

// Header'ı dahil et
include 'includes/header.php';
?>

<body class="<?php echo $themeClass; ?>">
    <div class="wrapper">
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
                <?php if (isset($_SESSION['success'])): ?>
                <script>
                    window.sessionSuccess = '<?php echo addslashes($_SESSION['success']); ?>';
                    document.addEventListener('DOMContentLoaded', function() {
                        window.showToastMessage(window.sessionSuccess, 'success');
                    });
                </script>
                <?php unset($_SESSION['success']); endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                <script>
                    window.sessionError = '<?php echo addslashes($_SESSION['error']); ?>';
                    document.addEventListener('DOMContentLoaded', function() {
                        window.showToastMessage(window.sessionError, 'error');
                    });
                </script>
                <?php unset($_SESSION['error']); endif; ?>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">SMS Şablonları</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h6 class="alert-heading">Kullanılabilir Değişkenler:</h6>
                                    <ul class="mb-0">
                                        <li><code>{danisan_adi}</code> - Danışanın adı</li>
                                        <li><code>{tarih}</code> - Randevu tarihi</li>
                                        <li><code>{saat}</code> - Randevu saati</li>
                                    </ul>
                                </div>

                                <?php foreach ($templates as $template): ?>
                                <div class="mb-4">
                                    <h6 class="mb-3">
                                        <?php 
                                        switch($template['template_name']) {
                                            case 'randevu_olusturma':
                                                echo 'Randevu Oluşturma SMS Şablonu';
                                                break;
                                            case 'randevu_hatirlatma':
                                                echo 'Randevu Hatırlatma SMS Şablonu';
                                                break;
                                            default:
                                                echo ucfirst(str_replace('_', ' ', $template['template_name']));
                                        }
                                        ?>
                                    </h6>
                                    <form method="POST" class="needs-validation" novalidate>
                                        <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                        <div class="mb-3">
                                            <textarea class="form-control" name="template_text" rows="3" required><?php echo htmlspecialchars($template['template_text']); ?></textarea>
                                        </div>
                                        <button type="submit" name="update_template" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Kaydet
                                        </button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/toast.js"></script>
</body>
</html> 