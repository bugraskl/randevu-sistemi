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

// İstatistikleri veritabanından çek
try {
    // Toplam danışan sayısı
    $stmt = $db->query("SELECT COUNT(*) FROM clients");
    $total_clients = $stmt->fetchColumn();

    // Bugünkü randevu sayısı
    $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()");
    $stmt->execute();
    $today_appointments = $stmt->fetchColumn();

    // Bugünkü tamamlanan randevu sayısı
    $stmt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND appointment_time <= CURTIME()");
    $stmt->execute();
    $completed_appointments = $stmt->fetchColumn();

    // Ödeme yapılmamış randevuları çek
    $stmt = $db->prepare("
        SELECT a.*, c.name as client_name, TIME_FORMAT(a.appointment_time, '%H:%i') as formatted_time
        FROM appointments a 
        JOIN clients c ON a.client_id = c.id 
        LEFT JOIN payments p ON a.id = p.appointment_id
        WHERE (a.appointment_date < CURDATE() OR (a.appointment_date = CURDATE() AND a.appointment_time < CURTIME()))
        AND p.id IS NULL
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 5
    ");
    $stmt->execute();
    $unpaid_appointments = $stmt->fetchAll();

    // Bu haftaki randevu sayısı
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM appointments 
        WHERE appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) 
        AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)
    ");
    $stmt->execute();
    $week_appointments = $stmt->fetchColumn();

    // Bu ayki randevu sayısı
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM appointments 
        WHERE appointment_date BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') 
        AND LAST_DAY(CURDATE())
    ");
    $stmt->execute();
    $month_appointments = $stmt->fetchColumn();

    // Bugünkü randevuları çek
    $stmt = $db->prepare("
        SELECT a.*, c.name as client_name, c.phone as client_phone,
               TIME_FORMAT(a.appointment_time, '%H:%i') as formatted_time,
               CASE 
                   WHEN a.appointment_date = CURDATE() AND a.appointment_time > CURTIME() THEN 'upcoming'
                   WHEN a.appointment_date = CURDATE() AND a.appointment_time <= CURTIME() THEN 'past'
                   WHEN a.appointment_date > CURDATE() THEN 'future'
                   ELSE 'past'
               END as appointment_status
        FROM appointments a 
        JOIN clients c ON a.client_id = c.id 
        WHERE (a.appointment_date = CURDATE() AND a.appointment_time > CURTIME())
           OR a.appointment_date > CURDATE()
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 10
    ");
    $stmt->execute();
    $today_appointments_list = $stmt->fetchAll();

} catch(PDOException $e) {
    $_SESSION['error'] = "Veritabanı hatası: " . $e->getMessage();
    $total_clients = 0;
    $today_appointments = 0;
    $week_appointments = 0;
    $month_appointments = 0;
    $today_appointments_list = [];
}

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

.welcome-icon {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(45deg, #0d6efd, #0dcaf0);
    box-shadow: 0 4px 15px rgba(13, 110, 253, 0.2);
}

.welcome-icon i {
    font-size: 2rem;
}
</style>
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
                <!-- Hoş Geldiniz Kartı -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="welcome-icon bg-primary text-white rounded-circle p-3">
                                    <i class="bi bi-person-circle fs-1"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h4 class="mb-1">Hoş Geldiniz, <?php 
                                    $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $user = $stmt->fetch();
                                    echo htmlspecialchars($user['name']); 
                                ?></h4>
                                <p class="mb-0">
                                    <?php
                                    $remaining = $today_appointments - $completed_appointments;
                                    if ($today_appointments > 0) {
                                        if ($remaining > 0) {
                                            echo "Bugün toplam {$today_appointments} randevunuz var, {$completed_appointments} tanesini tamamladınız, {$remaining} randevunuz kaldı.";
                                        } else {
                                            echo "Bugün tüm randevularınızı tamamladınız. İyi dinlenmeler!";
                                        }
                                    } else {
                                        echo "Bugün randevunuz bulunmuyor.";
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

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

                <?php if (count($unpaid_appointments) > 0): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <div>
                            <strong>Ödeme Bekleyen Randevular!</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($unpaid_appointments as $appointment): ?>
                                <li>
                                    <?php echo htmlspecialchars($appointment['client_name']); ?> - 
                                    <?php echo date('d.m.Y', strtotime($appointment['appointment_date'])); ?> 
                                    <?php echo $appointment['formatted_time']; ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="payments" class="btn btn-secondary btn-sm">
                            <i class="bi bi-cash"></i> Ödemeleri Görüntüle
                        </a>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                </div>
                <?php endif; ?>

                <!-- İstatistik Kartları -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card d-flex align-items-center">
                            <div class="stats-info flex-grow-1">
                                <div class="number"><?php echo $total_clients; ?></div>
                                <div class="label">Toplam Danışan</div>
                            </div>
                            <div class="icon text-primary ms-3">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card d-flex align-items-center">
                            <div class="stats-info flex-grow-1">
                                <div class="number">
                                    <?php echo $today_appointments; ?>
                                    <?php if ($completed_appointments > 0): ?>
                                        <span class="text-danger ms-1" style="font-size: 0.35em;">-<?php echo $completed_appointments; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="label">Bugünkü Randevular</div>
                            </div>
                            <div class="icon text-success ms-3">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card d-flex align-items-center">
                            <div class="stats-info flex-grow-1">
                                <div class="number"><?php echo $week_appointments; ?></div>
                                <div class="label">Bu Haftaki Randevular</div>
                            </div>
                            <div class="icon text-info ms-3">
                                <i class="bi bi-calendar-week"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Yaklaşan Randevular -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Yaklaşan Randevular</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($today_appointments_list) > 0): ?>
                            <?php foreach ($today_appointments_list as $appointment): 
                                $appointment_date = strtotime($appointment['appointment_date']);
                                $day_name = date('l', $appointment_date);
                                
                                // Günlere göre arka plan renkleri
                                $bg_colors = [
                                    'Monday' => 'bg-primary bg-opacity-10',
                                    'Tuesday' => 'bg-success bg-opacity-10',
                                    'Wednesday' => 'bg-info bg-opacity-10',
                                    'Thursday' => 'bg-warning bg-opacity-10',
                                    'Friday' => 'bg-danger bg-opacity-10',
                                    'Saturday' => 'bg-secondary bg-opacity-10',
                                    'Sunday' => 'bg-dark bg-opacity-10'
                                ];
                                
                                $bg_class = $bg_colors[$day_name];
                                
                                // Türkçe gün isimleri
                                $turkish_days = [
                                    'Monday' => 'Pazartesi',
                                    'Tuesday' => 'Salı',
                                    'Wednesday' => 'Çarşamba',
                                    'Thursday' => 'Perşembe',
                                    'Friday' => 'Cuma',
                                    'Saturday' => 'Cumartesi',
                                    'Sunday' => 'Pazar'
                                ];
                            ?>
                            <div class="appointment-item <?php echo $bg_class; ?> mb-2 p-3 rounded">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="client-name fw-bold"><?php echo htmlspecialchars($appointment['client_name']); ?></div>
                                        <div class="appointment-time">
                                            <span class="badge bg-primary"><?php echo $turkish_days[$day_name]; ?></span>
                                            <?php 
                                            if (date('Y-m-d') == date('Y-m-d', $appointment_date)) {
                                                echo 'Bugün';
                                            } else {
                                                echo date('d.m.Y', $appointment_date);
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <a href="appointments" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Detay
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-muted">Yaklaşan randevu bulunmuyor.</div>
                        <?php endif; ?>
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