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
    
    if (!in_array('role', $columns) || !in_array('status', $columns)) {
        $_SESSION['error'] = "Veritabanı şeması güncel değil. Lütfen database/update_users_table.sql dosyasını çalıştırın.";
        header('Location: dashboard');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = "Veritabanı şema kontrolü yapılırken bir hata oluştu.";
    header('Location: dashboard');
    exit();
}

// Admin kontrolü - sadece admin kullanıcılar bu sayfaya erişebilir
try {
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
    
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        $_SESSION['error'] = "Bu sayfaya erişim yetkiniz bulunmamaktadır.";
        header('Location: dashboard');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = "Yetki kontrolü yapılırken bir hata oluştu.";
    header('Location: dashboard');
    exit();
}

// Sayfa numarası
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15; // Sayfa başına gösterilecek kayıt sayısı
$offset = ($page - 1) * $limit;

// Toplam kayıt sayısını al
try {
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Kullanıcı listesini veritabanından çek
    $stmt = $db->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Kullanıcı listesi alınırken bir hata oluştu: " . $e->getMessage();
    $users = [];
    $total_pages = 1;
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
                <!-- Toast mesajları -->
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

                <?php if (isset($_SESSION['warning'])): ?>
                <script>
                    window.sessionWarning = '<?php echo addslashes($_SESSION['warning']); ?>';
                    document.addEventListener('DOMContentLoaded', function() {
                        window.showToastMessage(window.sessionWarning, 'warning');
                    });
                </script>
                <?php unset($_SESSION['warning']); endif; ?>

                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Kullanıcı Yönetimi</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="bi bi-person-plus"></i> Yeni Kullanıcı
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="input-group">
                                <input type="text" id="searchInput" class="form-control" placeholder="Kullanıcı ara..." autocomplete="off">
                                <button class="btn btn-primary" type="button" id="searchButton">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                        
                        <?php if (empty($users)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-person-x display-1 text-muted"></i>
                            <p class="mt-3 text-muted">Henüz kayıtlı kullanıcı bulunmamaktadır.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ad Soyad</th>
                                        <th>E-posta</th>
                                        <th>Rol</th>
                                        <th>Durum</th>
                                        <th>Kayıt Tarihi</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-danger' : 'bg-secondary'; ?>">
                                                <?php echo $user['role'] === 'admin' ? 'Yönetici' : 'Kullanıcı'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $user['status'] === 'active' ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo $user['status'] === 'active' ? 'Aktif' : 'Pasif'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal<?php echo $user['id']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Sayfalama -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Sayfalama" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Önceki">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);

                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
                                    echo '<a class="page-link" href="?page=' . $i . '">' . $i . '</a>';
                                    echo '</li>';
                                }

                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                                }
                                ?>

                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Sonraki">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Yeni Kullanıcı Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Kullanıcı Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process/add-user" method="POST" class="needs-validation" novalidate>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Ad Soyad</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="invalid-feedback">Lütfen ad soyad giriniz.</div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="invalid-feedback">Lütfen geçerli bir e-posta adresi giriniz.</div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Şifre</label>
                            <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                            <div class="invalid-feedback">Şifre en az 6 karakter olmalıdır.</div>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Rol</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Rol Seçiniz</option>
                                <option value="user">Kullanıcı</option>
                                <option value="admin">Yönetici</option>
                            </select>
                            <div class="invalid-feedback">Lütfen bir rol seçiniz.</div>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Durum</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">Durum Seçiniz</option>
                                <option value="active" selected>Aktif</option>
                                <option value="inactive">Pasif</option>
                            </select>
                            <div class="invalid-feedback">Lütfen bir durum seçiniz.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
                            Kullanıcı Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Düzenleme Modalleri -->
    <?php foreach ($users as $user): ?>
    <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kullanıcı Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process/edit-user" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_name<?php echo $user['id']; ?>" class="form-label">Ad Soyad</label>
                            <input type="text" class="form-control" id="edit_name<?php echo $user['id']; ?>" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            <div class="invalid-feedback">Lütfen ad soyad giriniz.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email<?php echo $user['id']; ?>" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="edit_email<?php echo $user['id']; ?>" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            <div class="invalid-feedback">Lütfen geçerli bir e-posta adresi giriniz.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password<?php echo $user['id']; ?>" class="form-label">Yeni Şifre (Boş bırakılırsa değişmez)</label>
                            <input type="password" class="form-control" id="edit_password<?php echo $user['id']; ?>" name="password" minlength="6">
                            <div class="invalid-feedback">Şifre en az 6 karakter olmalıdır.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_role<?php echo $user['id']; ?>" class="form-label">Rol</label>
                            <select class="form-select" id="edit_role<?php echo $user['id']; ?>" name="role" required>
                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>Kullanıcı</option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Yönetici</option>
                            </select>
                            <div class="invalid-feedback">Lütfen bir rol seçiniz.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_status<?php echo $user['id']; ?>" class="form-label">Durum</label>
                            <select class="form-select" id="edit_status<?php echo $user['id']; ?>" name="status" required>
                                <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Pasif</option>
                            </select>
                            <div class="invalid-feedback">Lütfen bir durum seçiniz.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
                            Kullanıcı Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Silme Modal -->
    <?php if ($user['id'] != $_SESSION['user_id']): ?>
    <div class="modal fade" id="deleteUserModal<?php echo $user['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kullanıcı Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong><?php echo htmlspecialchars($user['name']); ?></strong> adlı kullanıcıyı silmek istediğinizden emin misiniz?</p>
                    <p class="text-danger"><small>Bu işlem geri alınamaz!</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <form action="process/delete-user" method="POST" style="display: inline;">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <button type="submit" class="btn btn-danger">
                            <span class="spinner-border spinner-border-sm d-none me-2" role="status"></span>
                            Sil
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/toast.js"></script>
    <script src="assets/js/user-management.js"></script>

    <script>
        // Global değişkenler
        window.currentUserId = <?php echo $_SESSION['user_id']; ?>;
        
        // Form validasyonu ve loading state yönetimi
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('.needs-validation');
            
            // Form submit işlemi
            forms.forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const spinner = submitBtn?.querySelector('.spinner-border');
                    
                    // Validasyon kontrolü
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                        
                        // Hata durumunda loading'i kaldır
                        if (spinner && submitBtn) {
                            spinner.classList.add('d-none');
                            submitBtn.disabled = false;
                        }
                    } else {
                        // Başarılı validasyon - Loading state'i başlat
                        if (spinner && submitBtn) {
                            spinner.classList.remove('d-none');
                            submitBtn.disabled = true;
                            
                            // 15 saniye sonra otomatik geri al (timeout için)
                            setTimeout(() => {
                                spinner.classList.add('d-none');
                                submitBtn.disabled = false;
                            }, 15000);
                        }
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
            
            // Modal kapandığında loading state'i temizle
            const modals = document.querySelectorAll('.modal');
            modals.forEach(function(modal) {
                modal.addEventListener('hidden.bs.modal', function() {
                    const form = this.querySelector('form');
                    if (form) {
                        const submitBtn = form.querySelector('button[type="submit"]');
                        const spinner = submitBtn?.querySelector('.spinner-border');
                        
                        // Loading state'i temizle
                        if (spinner && submitBtn) {
                            spinner.classList.add('d-none');
                            submitBtn.disabled = false;
                        }
                        
                        // Form validation durumunu temizle
                        form.classList.remove('was-validated');
                        
                        // Input alanlarındaki hata durumlarını temizle
                        const invalidInputs = form.querySelectorAll('.is-invalid');
                        invalidInputs.forEach(input => {
                            input.classList.remove('is-invalid');
                        });
                    }
                });
                
                // Modal açıldığında form'u sıfırla (sadece add modal için)
                if (modal.id === 'addUserModal') {
                    modal.addEventListener('show.bs.modal', function() {
                        const form = this.querySelector('form');
                        if (form) {
                            form.reset();
                            form.classList.remove('was-validated');
                            
                            // Tüm hata durumlarını temizle
                            const invalidInputs = form.querySelectorAll('.is-invalid');
                            invalidInputs.forEach(input => {
                                input.classList.remove('is-invalid');
                            });
                        }
                    });
                }
            });
            
            // Sayfa yüklendiğinde tüm loading state'leri temizle
            const allSpinners = document.querySelectorAll('.spinner-border');
            const allSubmitBtns = document.querySelectorAll('button[type="submit"]');
            
            allSpinners.forEach(spinner => {
                spinner.classList.add('d-none');
            });
            
            allSubmitBtns.forEach(btn => {
                btn.disabled = false;
            });
        });
        
        // Sayfa yeniden yüklenme durumunda loading state'leri temizle
        window.addEventListener('pageshow', function(event) {
            const allSpinners = document.querySelectorAll('.spinner-border');
            const allSubmitBtns = document.querySelectorAll('button[type="submit"]');
            
            allSpinners.forEach(spinner => {
                spinner.classList.add('d-none');
            });
            
            allSubmitBtns.forEach(btn => {
                btn.disabled = false;
            });
        });
    </script>
</body>
</html> 