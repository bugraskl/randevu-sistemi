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
    header('Location: index.php');
    exit();
}

// Danışan ID'sini al
$client_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Danışan bilgilerini veritabanından çek
try {
    $stmt = $db->prepare("
        SELECT c.*, 
               COUNT(a.id) as total_sessions
        FROM clients c
        LEFT JOIN appointments a ON c.id = a.client_id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();

    if (!$client) {
        $_SESSION['error'] = "Danışan bulunamadı.";
        header('Location: clients.php');
        exit();
    }

    // Danışanın randevularını çek
    $stmt = $db->prepare("
        SELECT a.*, 
               CASE 
                   WHEN a.appointment_date < CURDATE() THEN 'past'
                   WHEN a.appointment_date = CURDATE() THEN 'today'
                   ELSE 'future'
               END as date_status,
               TIME_FORMAT(a.appointment_time, '%H:%i') as formatted_time,
               CASE 
                   WHEN EXISTS (SELECT 1 FROM payments p WHERE p.appointment_id = a.id) THEN 'paid'
                   ELSE 'unpaid'
               END as payment_status
        FROM appointments a 
        WHERE a.client_id = ? 
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([$client_id]);
    $appointments = $stmt->fetchAll();

    // Danışanın ödemelerini çek
    $stmt = $db->prepare("
        SELECT p.*, 
               CASE 
                   WHEN p.payment_date < CURDATE() THEN 'past'
                   WHEN p.payment_date = CURDATE() THEN 'today'
                   ELSE 'future'
               END as date_status
        FROM payments p 
        JOIN appointments a ON p.appointment_id = a.id
        WHERE a.client_id = ? 
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$client_id]);
    $payments = $stmt->fetchAll();

} catch(PDOException $e) {
    $_SESSION['error'] = "Veritabanı hatası: " . $e->getMessage();
    header('Location: clients.php');
    exit();
}

// Gün isimlerini Türkçe olarak tanımla
$gunler = [
    'Monday' => 'Pazartesi',
    'Tuesday' => 'Salı',
    'Wednesday' => 'Çarşamba',
    'Thursday' => 'Perşembe',
    'Friday' => 'Cuma',
    'Saturday' => 'Cumartesi',
    'Sunday' => 'Pazar'
];

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

            <div class="container-fluid py-4">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['warning'])): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['warning'];
                        unset($_SESSION['warning']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Danışan Bilgileri</h5>
                            </div>
                            <div class="card-body">
                                <h4><?php echo htmlspecialchars($client['name']); ?></h4>
                                <p class="mb-1"><strong>Telefon:</strong> <?php echo htmlspecialchars($client['phone']); ?></p>
                                <?php if ($client['email']): ?>
                                <p class="mb-1"><strong>E-posta:</strong> <?php echo htmlspecialchars($client['email']); ?></p>
                                <?php endif; ?>
                                <?php if ($client['address']): ?>
                                <p class="mb-1"><strong>Adres:</strong> <?php echo nl2br(htmlspecialchars($client['address'])); ?></p>
                                <?php endif; ?>
                                <p class="mb-1"><strong>Toplam Seans:</strong> <?php echo $client['total_sessions']; ?></p>
                                <?php if ($client['notes']): ?>
                                <p class="mb-1"><strong>Notlar:</strong> <?php echo nl2br(htmlspecialchars($client['notes'])); ?></p>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <a href="clients" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Geri Dön
                                    </a>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editClientModal">
                                        <i class="bi bi-pencil"></i> Düzenle
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Randevu Geçmişi</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tarih</th>
                                                <th>Gün</th>
                                                <th>Saat</th>
                                                <th>Durum</th>
                                                <th>Ödeme</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($appointments as $appointment): 
                                                $appointmentDate = new DateTime($appointment['appointment_date']);
                                                $dayName = $gunler[$appointmentDate->format('l')];
                                            ?>
                                            <tr class="<?php echo $appointment['date_status'] === 'past' ? 'table-secondary' : ''; ?>">
                                                <td><?php echo $appointmentDate->format('d.m.Y'); ?></td>
                                                <td><?php echo $dayName; ?></td>
                                                <td><?php echo date('H:i', strtotime($appointment['formatted_time'])); ?></td>
                                                <td>
                                                    <?php if ($appointment['date_status'] === 'past'): ?>
                                                        <span class="badge bg-secondary">Geçmiş</span>
                                                    <?php elseif ($appointment['date_status'] === 'today'): ?>
                                                        <span class="badge bg-primary">Bugün</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Gelecek</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (isset($appointment['payment_status']) && $appointment['payment_status'] === 'paid'): ?>
                                                        <span class="badge bg-success">Ödendi</span>
                                                    <?php elseif (isset($appointment['payment_status']) && $appointment['payment_status'] === 'unpaid'): ?>
                                                        <span class="badge bg-danger">Ödenmedi</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Beklemede</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" onclick="editAppointment(<?php echo $appointment['id']; ?>, '<?php echo $appointment['appointment_date']; ?>', '<?php echo date('H:i', strtotime($appointment['formatted_time'])); ?>', '<?php echo htmlspecialchars($appointment['notes']); ?>')">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if ($appointment['payment_status'] === 'unpaid'): ?>
                                                    <button type="button" class="btn btn-sm btn-success" onclick="addPayment(<?php echo $appointment['id']; ?>)">
                                                        <i class="bi bi-cash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteAppointment(<?php echo $appointment['id']; ?>, '<?php echo date('d.m.Y', strtotime($appointment['appointment_date'])); ?>', '<?php echo date('H:i', strtotime($appointment['formatted_time'])); ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Düzenleme Modal -->
    <div class="modal fade" id="editClientModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Danışan Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="process/edit-client" method="POST" class="needs-validation" novalidate id="editClientForm">
                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                        <div class="mb-3">
                            <label for="name" class="form-label">Ad Soyad</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($client['name']); ?>" required>
                            <div class="invalid-feedback">
                                Lütfen ad soyad giriniz.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Telefon</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($client['phone']); ?>" required pattern="[0-9]{10,11}">
                            <div class="invalid-feedback">
                                Lütfen geçerli bir telefon numarası giriniz (10-11 haneli).
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($client['email']); ?>">
                            <div class="invalid-feedback">
                                Lütfen geçerli bir e-posta adresi giriniz.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Adres</label>
                            <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($client['address']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notlar</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($client['notes']); ?></textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" class="btn btn-primary" data-original-text="Kaydet">Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Randevu Düzenleme Modal -->
    <div class="modal fade" id="editAppointmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Randevu Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="process/edit-appointment" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="appointment_id" id="edit_appointment_id">
                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                        <div class="mb-3">
                            <label for="edit_date" class="form-label">Tarih</label>
                            <input type="date" class="form-control" id="edit_date" name="date" min="<?php echo $today; ?>" required>
                            <div class="invalid-feedback">
                                Lütfen geçerli bir tarih seçiniz.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Saat</label>
                            <div class="d-flex gap-2">
                                <select class="form-select" id="edit_hour" name="hour" required>
                                    <option value="">Saat</option>
                                    <?php for($i = 9; $i <= 20; $i++): ?>
                                        <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select class="form-select" id="edit_minute" name="minute" required>
                                    <option value="">Dakika</option>
                                    <option value="00">00</option>
                                    <option value="15">15</option>
                                    <option value="30">30</option>
                                    <option value="45">45</option>
                                </select>
                            </div>
                            <div class="invalid-feedback">
                                Lütfen saat ve dakika seçiniz.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_notes" class="form-label">Notlar</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" class="btn btn-primary">Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Randevu Silme Modal -->
    <div class="modal fade" id="deleteAppointmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Randevu Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Bu randevuyu silmek istediğinizden emin misiniz?</p>
                    <p><strong>Danışan:</strong> <?php echo htmlspecialchars($client['name']); ?></p>
                    <p><strong>Tarih:</strong> <span id="delete_appointment_date"></span></p>
                    <p><strong>Saat:</strong> <span id="delete_appointment_time"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <form action="process/delete-appointment" method="POST" class="d-inline">
                        <input type="hidden" name="appointment_id" id="delete_appointment_id">
                        <button type="submit" class="btn btn-danger">Sil</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Ödeme Ekleme Modal -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ödeme Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="process/add-payment" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="appointment_id" id="payment_appointment_id">
                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                        <div class="mb-3">
                            <label for="amount" class="form-label">Tutar</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="amount" name="amount" min="0" step="0.01" value="1700" required>
                                <span class="input-group-text">₺</span>
                            </div>
                            <div class="invalid-feedback">
                                Lütfen geçerli bir tutar giriniz.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Ödeme Yöntemi</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="">Ödeme Yöntemi Seçin</option>
                                <option value="cash">Nakit</option>
                                <option value="credit_card">Kredi Kartı</option>
                                <option value="bank_transfer">Banka Transferi</option>
                            </select>
                            <div class="invalid-feedback">
                                Lütfen ödeme yöntemi seçiniz.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="payment_notes" class="form-label">Notlar</label>
                            <textarea class="form-control" id="payment_notes" name="notes" rows="3"></textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" class="btn btn-success">Ödeme Ekle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Randevu düzenleme modalını açma fonksiyonu
    function editAppointment(id, date, time, notes) {
        document.getElementById('edit_appointment_id').value = id;
        document.getElementById('edit_date').value = date;
        
        // Saat ve dakikayı ayır
        const [hour, minute] = time.split(':');
        document.getElementById('edit_hour').value = hour;
        document.getElementById('edit_minute').value = minute;
        
        document.getElementById('edit_notes').value = notes;
        new bootstrap.Modal(document.getElementById('editAppointmentModal')).show();
    }

    // Randevu silme modalını açma fonksiyonu
    function deleteAppointment(id, date, time) {
        document.getElementById('delete_appointment_id').value = id;
        document.getElementById('delete_appointment_date').textContent = date;
        document.getElementById('delete_appointment_time').textContent = time;
        new bootstrap.Modal(document.getElementById('deleteAppointmentModal')).show();
    }

    // Ödeme ekleme modalını açma fonksiyonu
    function addPayment(id) {
        document.getElementById('payment_appointment_id').value = id;
        new bootstrap.Modal(document.getElementById('addPaymentModal')).show();
    }

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

        // Form doğrulama ve gönderimi
        const forms = document.querySelectorAll('.needs-validation');
        forms.forEach(form => {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                } else {
                    // Form geçerliyse, submit butonunu devre dışı bırak
                    const submitButton = form.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Kaydediliyor...';
                    }
                }
                form.classList.add('was-validated');
            });
        });

        // Modal kapanma olaylarını dinle
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('hidden.bs.modal', function() {
                // Modal kapandığında formu sıfırla
                const form = this.querySelector('form');
                if (form) {
                    form.reset();
                    form.classList.remove('was-validated');
                    const submitButton = form.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.innerHTML = submitButton.getAttribute('data-original-text') || 'Kaydet';
                    }
                }
            });
        });

        // Submit butonlarının orijinal metinlerini sakla
        document.querySelectorAll('button[type="submit"]').forEach(button => {
            button.setAttribute('data-original-text', button.innerHTML);
        });
    });
    </script>
</body>
</html> 