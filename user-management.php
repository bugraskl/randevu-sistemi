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

// --- Görünüm için yardımcılar (sorgulara dokunmaz) ---
function userInitials($name) {
    $parts = array_filter(preg_split('/\s+/u', trim((string) $name)));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return $initials !== '' ? $initials : '?';
}

$roleNames = ['admin' => 'Yönetici', 'user' => 'Kullanıcı'];

$pageTitle = 'Kullanıcı Yönetimi';
$pageSubtitle = isset($total_records) ? ((int) $total_records . ' kullanıcı') : '';
$pageActions = '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-person-plus" aria-hidden="true"></i> Kullanıcı ekle</button>';

// Header'ı dahil et
include 'includes/header.php';
?>
<body class="<?php echo $themeClass; ?>" data-page="user-management">
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main id="content" tabindex="-1">
            <?php include 'includes/topbar.php'; ?>

            <div class="page">
                <div class="um-search">
                    <div class="search-field">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <label for="searchInput" class="visually-hidden">Kullanıcı ara</label>
                        <input type="search" id="searchInput" class="form-control" placeholder="Ad veya e-posta" autocomplete="off">
                    </div>
                    <button class="btn btn-secondary" type="button" id="searchButton">Ara</button>
                </div>

                <?php if (empty($users)): ?>
                <div class="empty">
                    <p class="empty-title">Henüz kullanıcı yok</p>
                    <p>Üstteki “Kullanıcı ekle” ile ilk kullanıcıyı oluşturun.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-stack">
                        <thead>
                            <tr>
                                <th scope="col">Ad soyad</th>
                                <th scope="col">E-posta</th>
                                <th scope="col">Rol</th>
                                <th scope="col">Durum</th>
                                <th scope="col">Kayıt tarihi</th>
                                <th scope="col"><span class="visually-hidden">İşlemler</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user):
                                $isSelf = $user['id'] == $_SESSION['user_id'];
                                $isActive = $user['status'] === 'active';
                            ?>
                            <tr>
                                <td class="td-title">
                                    <span class="um-user">
                                        <span class="avatar avatar-sm" aria-hidden="true"><?php echo htmlspecialchars(userInitials($user['name'])); ?></span>
                                        <span class="um-name">
                                            <?php echo htmlspecialchars($user['name']); ?>
                                            <?php if ($isSelf): ?><span class="badge bg-secondary">Siz</span><?php endif; ?>
                                        </span>
                                    </span>
                                </td>
                                <td data-label="E-posta"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td data-label="Rol">
                                    <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-primary' : 'bg-secondary'; ?>"><?php echo $user['role'] === 'admin' ? 'Yönetici' : 'Kullanıcı'; ?></span>
                                </td>
                                <td data-label="Durum">
                                    <?php if ($isActive): ?>
                                    <span class="mark mark-confirmed">Aktif</span>
                                    <?php else: ?>
                                    <span class="mark mark-pending">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Kayıt tarihi" class="tnum"><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                                <td class="td-actions">
                                    <div class="um-actions">
                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo (int) $user['id']; ?>">
                                            Düzenle<span class="visually-hidden">: <?php echo htmlspecialchars($user['name']); ?></span>
                                        </button>
                                        <?php if (!$isSelf): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal<?php echo (int) $user['id']; ?>">
                                            Sil<span class="visually-hidden">: <?php echo htmlspecialchars($user['name']); ?></span>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Sayfalama -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Kullanıcı listesi sayfaları" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">Önceki</a>
                        </li>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                            if ($start_page > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                            }
                        }

                        for ($i = $start_page; $i <= $end_page; $i++) {
                            echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
                            echo '<a class="page-link" href="?page=' . $i . '"' . ($i == $page ? ' aria-current="page"' : '') . '>' . $i . '</a>';
                            echo '</li>';
                        }

                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">Sonraki</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Yeni Kullanıcı Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="addUserTitle">Kullanıcı ekle</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <form action="process/add-user" method="POST" class="needs-validation" novalidate>
                    <div class="modal-body">
                        <div class="field">
                            <label for="name" class="form-label">Ad soyad</label>
                            <input type="text" class="form-control" id="name" name="name" autocomplete="off" required>
                            <div class="invalid-feedback">Ad soyad girin.</div>
                        </div>
                        <div class="field">
                            <label for="email" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="email" name="email" inputmode="email" autocomplete="off" required>
                            <div class="invalid-feedback">Geçerli bir e-posta adresi girin.</div>
                        </div>
                        <div class="field">
                            <label for="password" class="form-label">Şifre</label>
                            <input type="password" class="form-control" id="password" name="password" minlength="6" autocomplete="new-password" aria-describedby="passwordHelp" required>
                            <div class="form-text" id="passwordHelp">En az 6 karakter.</div>
                            <div class="invalid-feedback">Şifre en az 6 karakter olmalı.</div>
                        </div>
                        <div class="field-row">
                            <div class="field">
                                <label for="role" class="form-label">Rol</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Seçin</option>
                                    <option value="user">Kullanıcı</option>
                                    <option value="admin">Yönetici</option>
                                </select>
                                <div class="invalid-feedback">Bir rol seçin.</div>
                            </div>
                            <div class="field">
                                <label for="status" class="form-label">Durum</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="">Seçin</option>
                                    <option value="active" selected>Aktif</option>
                                    <option value="inactive">Pasif</option>
                                </select>
                                <div class="invalid-feedback">Bir durum seçin.</div>
                            </div>
                        </div>
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-primary">
                                <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                                Kullanıcıyı ekle
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Düzenleme Modalleri -->
    <?php foreach ($users as $user): ?>
    <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="editUserTitle<?php echo (int) $user['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="editUserTitle<?php echo (int) $user['id']; ?>">Kullanıcıyı düzenle</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <form action="process/edit-user" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                    <div class="modal-body">
                        <div class="field">
                            <label for="edit_name<?php echo $user['id']; ?>" class="form-label">Ad soyad</label>
                            <input type="text" class="form-control" id="edit_name<?php echo $user['id']; ?>" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" autocomplete="off" required>
                            <div class="invalid-feedback">Ad soyad girin.</div>
                        </div>
                        <div class="field">
                            <label for="edit_email<?php echo $user['id']; ?>" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="edit_email<?php echo $user['id']; ?>" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" inputmode="email" autocomplete="off" required>
                            <div class="invalid-feedback">Geçerli bir e-posta adresi girin.</div>
                        </div>
                        <div class="field">
                            <label for="edit_password<?php echo $user['id']; ?>" class="form-label">Yeni şifre</label>
                            <input type="password" class="form-control" id="edit_password<?php echo $user['id']; ?>" name="password" minlength="6" autocomplete="new-password" aria-describedby="edit_passwordHelp<?php echo (int) $user['id']; ?>">
                            <div class="form-text" id="edit_passwordHelp<?php echo (int) $user['id']; ?>">Boş bırakırsanız şifre değişmez. En az 6 karakter.</div>
                            <div class="invalid-feedback">Şifre en az 6 karakter olmalı.</div>
                        </div>
                        <div class="field-row">
                            <div class="field">
                                <label for="edit_role<?php echo $user['id']; ?>" class="form-label">Rol</label>
                                <select class="form-select" id="edit_role<?php echo $user['id']; ?>" name="role" required>
                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>Kullanıcı</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Yönetici</option>
                                </select>
                                <div class="invalid-feedback">Bir rol seçin.</div>
                            </div>
                            <div class="field">
                                <label for="edit_status<?php echo $user['id']; ?>" class="form-label">Durum</label>
                                <select class="form-select" id="edit_status<?php echo $user['id']; ?>" name="status" required>
                                    <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Pasif</option>
                                </select>
                                <div class="invalid-feedback">Bir durum seçin.</div>
                            </div>
                        </div>
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-primary">
                                <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                                Değişiklikleri kaydet
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Silme Modal -->
    <?php if ($user['id'] != $_SESSION['user_id']): ?>
    <div class="modal fade" id="deleteUserModal<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="deleteUserTitle<?php echo (int) $user['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="deleteUserTitle<?php echo (int) $user['id']; ?>">Kullanıcıyı sil</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="sheet-summary">
                        <span class="avatar avatar-sm" aria-hidden="true"><?php echo htmlspecialchars(userInitials($user['name'])); ?></span>
                        <div>
                            <p class="row-title"><span><?php echo htmlspecialchars($user['name']); ?></span></p>
                            <p class="row-meta"><?php echo htmlspecialchars($user['email']); ?> · <?php echo htmlspecialchars($roleNames[$user['role']] ?? $user['role']); ?></p>
                        </div>
                    </div>
                    <p>Bu kullanıcı silinir ve artık giriş yapamaz. Bu işlem geri alınamaz.</p>
                    <div class="sheet-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                        <form action="process/delete-user" method="POST">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" class="btn btn-danger">
                                <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                                Kullanıcıyı sil
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>

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

    <script>
        // Arama sonuçları (assets/js/user-management.js) eski tablo satırı üretir;
        // bu satırları sayfanın diliyle aynı görünüme getirir: etiketler, sözlü düğmeler, durum işareti.
        (function () {
            const tbody = document.querySelector('tbody');
            if (!tbody || !window.MutationObserver) {
                return;
            }
            const labels = [null, 'E-posta', 'Rol', 'Durum', 'Kayıt tarihi', null];

            function initials(name) {
                return name.trim().split(/\s+/).slice(0, 2).map(p => p.charAt(0).toLocaleUpperCase('tr-TR')).join('') || '?';
            }

            function decorate(tr) {
                if (tr.nodeType !== 1 || tr.tagName !== 'TR' || tr.dataset.umReady) {
                    return;
                }
                const cells = tr.children;
                if (cells.length !== 6) {
                    return; // yükleniyor / sonuç yok satırı
                }
                tr.dataset.umReady = '1';

                for (let i = 1; i <= 4; i++) {
                    cells[i].setAttribute('data-label', labels[i]);
                }
                cells[4].classList.add('tnum');

                // Ad hücresi: baş harf + ad
                const nameCell = cells[0];
                const name = nameCell.textContent.trim();
                nameCell.className = 'td-title';
                nameCell.textContent = '';
                const wrap = document.createElement('span');
                wrap.className = 'um-user';
                const avatar = document.createElement('span');
                avatar.className = 'avatar avatar-sm';
                avatar.setAttribute('aria-hidden', 'true');
                avatar.textContent = initials(name);
                const nameEl = document.createElement('span');
                nameEl.className = 'um-name';
                nameEl.textContent = name;
                wrap.append(avatar, nameEl);
                nameCell.appendChild(wrap);

                // Rol: yönetici için kökboya değil, ana renk etiketi
                const roleBadge = cells[2].querySelector('.badge');
                if (roleBadge && roleBadge.classList.contains('bg-danger')) {
                    roleBadge.classList.replace('bg-danger', 'bg-primary');
                }

                // Durum: tek durum dili
                const statusText = cells[3].textContent.trim();
                const mark = document.createElement('span');
                mark.className = 'mark ' + (statusText === 'Aktif' ? 'mark-confirmed' : 'mark-pending');
                mark.textContent = statusText;
                cells[3].textContent = '';
                cells[3].appendChild(mark);

                // Eylemler: kelimeyle (onclick işleyicileri korunur)
                const actionCell = cells[5];
                const actions = document.createElement('div');
                actions.className = 'um-actions';
                actionCell.querySelectorAll('button').forEach(btn => {
                    const onclick = btn.getAttribute('onclick') || '';
                    const isEdit = onclick.indexOf('openEditModal') === 0;
                    btn.className = 'btn btn-sm ' + (isEdit ? 'btn-secondary' : 'btn-outline-danger');
                    btn.textContent = isEdit ? 'Düzenle' : 'Sil';
                    const hidden = document.createElement('span');
                    hidden.className = 'visually-hidden';
                    hidden.textContent = ': ' + name;
                    btn.appendChild(hidden);
                    actions.appendChild(btn);

                    const idMatch = onclick.match(/openEditModal\((\d+)\)/);
                    if (isEdit && idMatch && String(window.currentUserId) === idMatch[1]) {
                        const self = document.createElement('span');
                        self.className = 'badge bg-secondary';
                        self.textContent = 'Siz';
                        nameEl.append(' ', self);
                    }
                });
                actionCell.className = 'td-actions';
                actionCell.textContent = '';
                actionCell.appendChild(actions);
            }

            new MutationObserver(function (mutations) {
                mutations.forEach(m => m.addedNodes.forEach(decorate));
            }).observe(tbody, { childList: true });
        })();
    </script>

<?php include 'includes/footer.php'; ?>
