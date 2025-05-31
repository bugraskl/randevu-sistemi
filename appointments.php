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

// Danışan listesini veritabanından çek
try {
    $stmt = $db->query("SELECT id, name, phone FROM clients ORDER BY name ASC");
    $clients = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Danışan listesi alınırken bir hata oluştu: " . $e->getMessage();
    $clients = [];
}

// Randevu listesini veritabanından çek (sadece bugün ve sonrası)
try {
    $stmt = $db->prepare("
        SELECT a.*, c.name as client_name, c.phone as client_phone,
               CASE 
                   WHEN a.appointment_date < CURDATE() THEN 'past'
                   WHEN a.appointment_date = CURDATE() THEN 'today'
                   ELSE 'future'
               END as date_status,
               TIME_FORMAT(a.appointment_time, '%H:%i') as formatted_time
        FROM appointments a 
        JOIN clients c ON a.client_id = c.id 
        WHERE a.appointment_date >= CURDATE()
        ORDER BY 
            a.appointment_date ASC,
            a.appointment_time ASC
    ");
    $stmt->execute();
    $appointments = $stmt->fetchAll();

    // Takvim için tüm randevuları çek
    $stmt = $db->prepare("
        SELECT a.*, c.name as client_name, c.phone as client_phone,
               CASE 
                   WHEN a.appointment_date < CURDATE() THEN 'past'
                   WHEN a.appointment_date = CURDATE() THEN 'today'
                   ELSE 'future'
               END as date_status,
               TIME_FORMAT(a.appointment_time, '%H:%i') as formatted_time
        FROM appointments a 
        JOIN clients c ON a.client_id = c.id 
        ORDER BY 
            a.appointment_date ASC,
            a.appointment_time ASC
    ");
    $stmt->execute();
    $calendar_appointments = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Randevu listesi alınırken bir hata oluştu: " . $e->getMessage();
    $appointments = [];
    $calendar_appointments = [];
}

// Bugünün tarihini al
$today = date('Y-m-d');
$current_time = date('H:i:s');

// Gün isimleri
$gunler = [
    'Monday' => 'Pazartesi',
    'Tuesday' => 'Salı',
    'Wednesday' => 'Çarşamba',
    'Thursday' => 'Perşembe',
    'Friday' => 'Cuma',
    'Saturday' => 'Cumartesi',
    'Sunday' => 'Pazar'
];

// Görünüm seçeneğini URL'den al
$view = isset($_GET['view']) ? $_GET['view'] : 'list';
$activeTab = $view === 'calendar' ? 'calendar' : 'list';

// Header'ı dahil et
include 'includes/header.php';
?>

<style>
.past-appointment {
    background-color: #f8f9fa !important;
    opacity: 0.7;
}

.past-appointment td {
    color: #6c757d;
}

.add-appointment-btn {
    opacity: 0;
    transition: opacity 0.2s;
}

.calendar-day:hover .add-appointment-btn {
    opacity: 1;
}

.appointment-item {
    font-size: 0.8rem;
    padding: 2px 4px;
    margin: 2px 0;
    border-radius: 3px;
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.appointment-item.past {
    background: #ffc107;
    color: #000;
}

.appointment-item.today {
    background: #0d6efd;
    color: #fff;
}

.appointment-item.future {
    background: #198754;
    color: #fff;
}

/* Select2 özelleştirmeleri */
.select2-container--bootstrap-5 .select2-selection {
    box-shadow: none !important;
}

.select2-container--bootstrap-5 .select2-selection:focus {
    box-shadow: none !important;
}

.select2-container--bootstrap-5 .select2-selection--single {
    box-shadow: none !important;
}

.select2-container--bootstrap-5 .select2-selection--single:focus {
    box-shadow: none !important;
}

.select2-container--bootstrap-5 .select2-search__field {
    box-shadow: none !important;
}

.select2-container--bootstrap-5 .select2-search__field:focus {
    box-shadow: none !important;
}

.select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
    box-shadow: none !important;
}

.select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field:focus {
    box-shadow: none !important;
}
</style>

<body class="<?php echo $themeClass; ?>" data-page="appointments">
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
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Randevularım</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAppointmentModal">
                                <i class="bi bi-plus-circle"></i> Yeni Randevu
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Görünüm Seçenekleri -->
                        <ul class="nav nav-tabs mb-3" id="viewTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $activeTab === 'list' ? 'active' : ''; ?>" 
                                        id="list-tab" 
                                        data-bs-toggle="tab" 
                                        data-bs-target="#list-view" 
                                        type="button" 
                                        role="tab"
                                        onclick="changeView('list')">
                                    <i class="bi bi-list-ul"></i> Liste Görünümü
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $activeTab === 'calendar' ? 'active' : ''; ?>" 
                                        id="calendar-tab" 
                                        data-bs-toggle="tab" 
                                        data-bs-target="#calendar-view" 
                                        type="button" 
                                        role="tab"
                                        onclick="changeView('calendar')">
                                    <i class="bi bi-calendar3"></i> Takvim Görünümü
                                </button>
                            </li>
                        </ul>

                        <!-- Tab İçerikleri -->
                        <div class="tab-content" id="viewTabsContent">
                            <!-- Liste Görünümü -->
                            <div class="tab-pane fade <?php echo $activeTab === 'list' ? 'show active' : ''; ?>" 
                                 id="list-view" 
                                 role="tabpanel">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="input-group">
                                        <input type="date" id="searchDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                        <button class="btn btn-primary" type="button" id="searchButton">
                                            <i class="bi bi-search"></i> Ara
                                        </button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tarih</th>
                                                <th>Gün</th>
                                                <th>Saat</th>
                                                <th>Danışan</th>
                                                <th>Telefon</th>
                                                <th>Durum</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($appointments)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <div class="d-flex flex-column align-items-center">
                                                        <i class="bi bi-calendar-x fs-1 text-muted mb-2"></i>
                                                        <p class="text-muted mb-0">Henüz randevu bulunmuyor.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                            <?php foreach ($appointments as $appointment): 
                                                $appointmentDate = new DateTime($appointment['appointment_date']);
                                                $dayName = $gunler[$appointmentDate->format('l')];
                                            ?>
                                            <tr class="<?php echo strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']) < strtotime('now') ? 'past-appointment' : ''; ?>">
                                                <td><?php echo $appointmentDate->format('d.m.Y'); ?></td>
                                                <td><?php echo $dayName; ?></td>
                                                <td><?php echo $appointment['formatted_time']; ?></td>
                                                <td><?php echo htmlspecialchars($appointment['client_name']); ?></td>
                                                <td><?php echo htmlspecialchars($appointment['client_phone']); ?></td>
                                                <td>
                                                    <?php if (strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']) < strtotime('now')): ?>
                                                        <span class="badge bg-secondary">Geçmiş</span>
                                                    <?php elseif ($appointment['appointment_date'] === date('Y-m-d')): ?>
                                                        <span class="badge bg-primary">Bugün</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Gelecek</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editAppointmentModal<?php echo $appointment['id']; ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAppointmentModal<?php echo $appointment['id']; ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Takvim Görünümü -->
                            <div class="tab-pane fade <?php echo $activeTab === 'calendar' ? 'show active' : ''; ?>" 
                                 id="calendar-view" 
                                 role="tabpanel">
                                <div class="d-none d-md-block">
                                    <div class="calendar-container">
                                        <div class="calendar-header mb-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <button class="btn btn-outline-primary" id="prevMonth">
                                                    <i class="bi bi-chevron-left"></i>
                                                </button>
                                                <h4 id="currentMonth" class="mb-0"></h4>
                                                <button class="btn btn-outline-primary" id="nextMonth">
                                                    <i class="bi bi-chevron-right"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="calendar-grid">
                                            <div class="calendar-weekdays">
                                                <div>Pazartesi</div>
                                                <div>Salı</div>
                                                <div>Çarşamba</div>
                                                <div>Perşembe</div>
                                                <div>Cuma</div>
                                                <div>Cumartesi</div>
                                                <div>Pazar</div>
                                            </div>
                                            <div id="calendarDays" class="calendar-days"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-md-none text-center p-4">
                                    <div class="alert alert-warning mb-0">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        Mobil cihazlarda takvim görünümü kullanılamıyor. Lütfen liste görünümünü kullanın.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <?php foreach ($appointments as $appointment): ?>
    <!-- Düzenleme Modal -->
    <div class="modal fade" id="editAppointmentModal<?php echo $appointment['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Randevu Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="process/edit-appointment" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                        <div class="mb-3">
                            <label for="client<?php echo $appointment['id']; ?>" class="form-label">Danışan</label>
                            <select class="form-select" id="client<?php echo $appointment['id']; ?>" name="client_id" required>
                                <option value="">Danışan Seçin</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo ($client['id'] == $appointment['client_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Lütfen Danışan seçiniz.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="date<?php echo $appointment['id']; ?>" class="form-label">Tarih</label>
                            <input type="date" class="form-control" id="date<?php echo $appointment['id']; ?>" name="date" value="<?php echo $appointment['appointment_date']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Saat</label>
                            <div class="d-flex gap-2">
                                <select class="form-select" id="hour<?php echo $appointment['id']; ?>" name="hour" required>
                                    <option value="">Saat</option>
                                    <?php 
                                    $currentHour = date('H', strtotime($appointment['appointment_time']));
                                    for($i = 9; $i <= 20; $i++): 
                                        $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
                                    ?>
                                        <option value="<?php echo $hour; ?>" <?php echo ($hour === $currentHour) ? 'selected' : ''; ?>><?php echo $hour; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select class="form-select" id="minute<?php echo $appointment['id']; ?>" name="minute" required>
                                    <option value="">Dakika</option>
                                    <?php 
                                    $currentMinute = date('i', strtotime($appointment['appointment_time']));
                                    $minutes = ['00', '15', '30', '45'];
                                    foreach($minutes as $minute): 
                                    ?>
                                        <option value="<?php echo $minute; ?>" <?php echo ($minute === $currentMinute) ? 'selected' : ''; ?>><?php echo $minute; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="notes<?php echo $appointment['id']; ?>" class="form-label">Notlar</label>
                            <textarea class="form-control" id="notes<?php echo $appointment['id']; ?>" name="notes" rows="3"><?php echo htmlspecialchars($appointment['notes']); ?></textarea>
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

    <!-- Silme Onay Modal -->
    <div class="modal fade" id="deleteAppointmentModal<?php echo $appointment['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Randevu Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Bu randevuyu silmek istediğinizden emin misiniz?</p>
                    <p><strong>Danışan:</strong> <?php echo htmlspecialchars($appointment['client_name']); ?></p>
                    <p><strong>Tarih:</strong> <?php echo date('d.m.Y', strtotime($appointment['appointment_date'])); ?></p>
                    <p><strong>Saat:</strong> <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <form action="process/delete-appointment" method="POST" class="d-inline">
                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                        <button type="submit" class="btn btn-danger">Sil</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Yeni Randevu Modal -->
    <div class="modal fade" id="addAppointmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Randevu Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="process/add-appointment" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="view" value="<?php echo $view; ?>">
                        <div class="mb-3">
                            <label for="client" class="form-label">Danışan</label>
                            <select class="form-select" id="client" name="client_id" required>
                                <option value="">Danışan Seçin</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Lütfen Danışan seçiniz.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="date" class="form-label">Tarih</label>
                            <input type="date" class="form-control" id="date" name="date" required>
                            <div class="invalid-feedback">
                                Lütfen geçerli bir tarih seçiniz.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Saat</label>
                            <div class="d-flex gap-2">
                                <select class="form-select" id="hour" name="hour" required>
                                    <option value="">Saat</option>
                                    <?php for($i = 9; $i <= 20; $i++): ?>
                                        <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select class="form-select" id="minute" name="minute" required>
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
                            <label for="notes" class="form-label">Notlar</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
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

    <!-- Arama Sonuçları Modal -->
    <div class="modal fade" id="searchResultsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Randevu Arama Sonuçları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="searchResults" class="list-group">
                        <!-- Arama sonuçları buraya dinamik olarak eklenecek -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Randevuları global değişkene aktar
    window.appointments = <?php echo json_encode($calendar_appointments); ?>;
    
    document.addEventListener('DOMContentLoaded', function() {
        // Select2'yi başlat - sadece yeni randevu ekleme modalı için
        $('#addAppointmentModal #client').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Danışan seçin veya arama yapın',
            allowClear: true,
            dropdownParent: $('#addAppointmentModal'),
            language: {
                noResults: function() {
                    return "Sonuç bulunamadı";
                },
                searching: function() {
                    return "Aranıyor...";
                }
            },
            templateResult: function(data) {
                if (!data.id) return data.text;
                return $('<span>' + data.text + '</span>');
            },
            templateSelection: function(data) {
                if (!data.id) return data.text;
                return $('<span>' + data.text + '</span>');
            }
        });

        // Modal açıldığında Select2'yi yeniden başlat
        $('#addAppointmentModal').on('shown.bs.modal', function () {
            $('#addAppointmentModal #client').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Danışan seçin veya arama yapın',
                allowClear: true,
                dropdownParent: $('#addAppointmentModal'),
                language: {
                    noResults: function() {
                        return "Sonuç bulunamadı";
                    },
                    searching: function() {
                        return "Aranıyor...";
                    }
                },
                templateResult: function(data) {
                    if (!data.id) return data.text;
                    return $('<span>' + data.text + '</span>');
                },
                templateSelection: function(data) {
                    if (!data.id) return data.text;
                    return $('<span>' + data.text + '</span>');
                }
            });
        });
    });
    </script>

<?php include 'includes/footer.php'; ?> 