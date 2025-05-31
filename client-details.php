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
               p.id as payment_id,
               p.amount,
               p.payment_method,
               p.payment_date,
               CASE 
                   WHEN p.id IS NOT NULL THEN 'paid'
                   ELSE 'unpaid'
               END as payment_status
        FROM appointments a 
        LEFT JOIN payments p ON a.id = p.appointment_id
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
                                                    <?php elseif ($appointment['payment_status'] === 'paid'): ?>
                                                    <button type="button" class="btn btn-sm btn-warning" onclick="cancelPayment(<?php echo $appointment['payment_id']; ?>, '<?php echo date('d.m.Y H:i', strtotime($appointment['appointment_date'] . ' ' . $appointment['formatted_time'])); ?>', '<?php echo number_format($appointment['amount'], 2, ',', '.'); ?>')">
                                                        <i class="bi bi-x-circle"></i>
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

    <!-- Ödeme İptal Modal -->
    <div class="modal fade" id="cancelPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ödeme İptal Et</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Dikkat!</strong> Bu işlem geri alınamaz.
                    </div>
                    <p>
                        <strong><?php echo htmlspecialchars($client['name']); ?></strong> isimli danışanın 
                        <strong><span id="cancel_payment_date"></span></strong> tarihli randevusu için alınan 
                        <strong><span id="cancel_payment_amount"></span> ₺</strong> ödemeyi iptal etmek istediğinizden emin misiniz?
                    </p>
                    <p class="text-muted">
                        Ödeme iptal edildiğinde, randevu "ödenmedi" durumuna geçecektir.
                    </p>
                </div>
                <div class="modal-footer">
                    <form action="process/cancel-payment" method="POST" style="display: inline;">
                        <input type="hidden" name="payment_id" id="cancel_payment_id">
                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                        <input type="hidden" name="redirect" value="client-details">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-circle me-1"></i> Ödeme İptal Et
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

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

    // Ödeme iptal modalını açma fonksiyonu
    function cancelPayment(paymentId, date, amount) {
        document.getElementById('cancel_payment_id').value = paymentId;
        document.getElementById('cancel_payment_date').textContent = date;
        document.getElementById('cancel_payment_amount').textContent = amount;
        new bootstrap.Modal(document.getElementById('cancelPaymentModal')).show();
    }
    </script>
</body>
</html>

<?php include 'includes/footer.php'; ?> 