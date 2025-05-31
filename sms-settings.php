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
    // Debug: POST verilerini kontrol et
    error_log("SMS Settings POST Data: " . print_r($_POST, true));
    
    // Veri kontrolü
    if (empty($_POST['template_text']) || empty($_POST['template_id'])) {
        $_SESSION['error'] = "Gerekli alanlar eksik.";
        header('Location: sms-settings');
        exit();
    }
    
    try {
        // Debug: SQL sorgusunu logla
        error_log("SMS Template Update - ID: " . $_POST['template_id'] . ", Text: " . $_POST['template_text']);
        
        $stmt = $db->prepare("UPDATE sms_templates SET template_text = ? WHERE id = ?");
        $result = $stmt->execute([$_POST['template_text'], $_POST['template_id']]);
        
        // Debug: Etkilenen satır sayısını kontrol et
        $rowCount = $stmt->rowCount();
        error_log("SMS Template Update - Affected rows: " . $rowCount);
        
        if ($rowCount > 0) {
            $_SESSION['success'] = "SMS şablonu başarıyla güncellendi.";
        } else {
            $_SESSION['warning'] = "Herhangi bir değişiklik yapılmadı veya şablon bulunamadı.";
        }
        
        header('Location: sms-settings');
        exit();
    } catch(PDOException $e) {
        error_log("SMS Template Update Error: " . $e->getMessage());
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
                                    <form method="POST" class="sms-template-form">
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

<?php include 'includes/footer.php'; ?> 