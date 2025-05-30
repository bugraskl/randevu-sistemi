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
                    document.addEventListener('DOMContentLoaded', function() {
                        window.toast.show('<?php echo addslashes($_SESSION['success']); ?>', 'success');
                    });
                </script>
                <?php unset($_SESSION['success']); endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        window.toast.show('<?php echo addslashes($_SESSION['error']); ?>', 'error');
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
    <script src="assets/js/toast.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarCollapse = document.getElementById('sidebarCollapse');
        const overlay = document.querySelector('.sidebar-overlay');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        sidebarCollapse.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // Mobil görünümde sidebar'ı varsayılan olarak kapalı yap
        if (window.innerWidth <= 768) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }

        // Pencere boyutu değiştiğinde kontrol et
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            } else {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
        });

        // Tema değiştirme işlemleri
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = themeToggle.querySelector('i');
        
        // Kaydedilmiş temayı kontrol et ve ikonu güncelle
        if (document.body.classList.contains('dark')) {
            themeIcon.classList.remove('bi-moon-fill');
            themeIcon.classList.add('bi-sun-fill');
        }

        // Tema değiştirme butonu tıklama olayı
        themeToggle.addEventListener('click', function() {
            if (document.body.classList.contains('dark')) {
                document.body.classList.remove('dark');
                themeIcon.classList.remove('bi-sun-fill');
                themeIcon.classList.add('bi-moon-fill');
                document.cookie = "theme=light; path=/; max-age=31536000";
            } else {
                document.body.classList.add('dark');
                themeIcon.classList.remove('bi-moon-fill');
                themeIcon.classList.add('bi-sun-fill');
                document.cookie = "theme=dark; path=/; max-age=31536000";
            }
        });

        // Form doğrulama
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    });
    </script>
</body>
</html> 