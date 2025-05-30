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

// Türkçe ay isimleri
$aylar = [
    'January' => 'Ocak',
    'February' => 'Şubat',
    'March' => 'Mart',
    'April' => 'Nisan',
    'May' => 'Mayıs',
    'June' => 'Haziran',
    'July' => 'Temmuz',
    'August' => 'Ağustos',
    'September' => 'Eylül',
    'October' => 'Ekim',
    'November' => 'Kasım',
    'December' => 'Aralık'
];

// Tarih aralığını al
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Son 6 ayın verilerini hazırla
$labels = [];
$kazanc_data = [];
$randevu_data = [];
$yeni_danisan_data = [];

for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('F', strtotime($month));
    $year = date('Y', strtotime($month));
    $labels[] = $aylar[$month_name] . ' ' . $year;
    
    // Ay başı ve sonu
    $month_start = date('Y-m-01', strtotime($month));
    $month_end = date('Y-m-t', strtotime($month));
    
    try {
        // Aylık kazanç
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_date BETWEEN ? AND ?");
        $stmt->execute([$month_start, $month_end]);
        $kazanc_data[] = $stmt->fetch()['total'];
        
        // Aylık randevu sayısı
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM appointments WHERE appointment_date BETWEEN ? AND ?");
        $stmt->execute([$month_start, $month_end]);
        $randevu_data[] = $stmt->fetch()['total'];
        
        // Aylık yeni danışan sayısı
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT client_id) as total 
            FROM appointments 
            WHERE appointment_date BETWEEN ? AND ?
            AND client_id NOT IN (
                SELECT DISTINCT client_id 
                FROM appointments 
                WHERE appointment_date < ?
            )
        ");
        $stmt->execute([$month_start, $month_end, $month_start]);
        $yeni_danisan_data[] = $stmt->fetch()['total'];
        
    } catch(PDOException $e) {
        $kazanc_data[] = 0;
        $randevu_data[] = 0;
        $yeni_danisan_data[] = 0;
    }
}

try {
    // Randevu istatistikleri
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_appointments,
            COUNT(CASE WHEN appointment_date < CURDATE() THEN 1 END) as past_appointments,
            COUNT(CASE WHEN appointment_date = CURDATE() THEN 1 END) as today_appointments,
            COUNT(CASE WHEN appointment_date > CURDATE() THEN 1 END) as future_appointments
        FROM appointments 
        WHERE appointment_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $appointment_stats = $stmt->fetch();

    // Ödeme istatistikleri
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_payments,
            SUM(amount) as total_amount,
            COUNT(CASE WHEN payment_method = 'cash' THEN 1 END) as cash_payments,
            COUNT(CASE WHEN payment_method = 'credit_card' THEN 1 END) as credit_card_payments,
            COUNT(CASE WHEN payment_method = 'bank_transfer' THEN 1 END) as bank_transfer_payments
        FROM payments 
        WHERE payment_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $payment_stats = $stmt->fetch();

    // Danışan istatistikleri
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT client_id) as total_clients,
            COUNT(DISTINCT CASE WHEN appointment_date BETWEEN ? AND ? THEN client_id END) as active_clients
        FROM appointments
    ");
    $stmt->execute([$start_date, $end_date]);
    $client_stats = $stmt->fetch();

} catch(PDOException $e) {
    $_SESSION['error'] = "Raporlar alınırken bir hata oluştu: " . $e->getMessage();
    $appointment_stats = ['total_appointments' => 0, 'past_appointments' => 0, 'today_appointments' => 0, 'future_appointments' => 0];
    $payment_stats = ['total_payments' => 0, 'total_amount' => 0, 'cash_payments' => 0, 'credit_card_payments' => 0, 'bank_transfer_payments' => 0];
    $client_stats = ['total_clients' => 0, 'active_clients' => 0];
}

// Header'ı dahil et
include 'includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

                <?php if (isset($_SESSION['warning'])): ?>
                <script>
                    window.sessionWarning = '<?php echo addslashes($_SESSION['warning']); ?>';
                    document.addEventListener('DOMContentLoaded', function() {
                        window.showToastMessage(window.sessionWarning, 'warning');
                    });
                </script>
                <?php unset($_SESSION['warning']); endif; ?>

                <div class="row">
                    <!-- Grafikler -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Aylık Kazanç ve Randevu Sayısı</h5>
                            </div>
                            <div class="card-body">
                                <div style="position: relative; height: 300px; width: 100%;">
                                    <canvas id="kazancChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Aylık Yeni Danışan Sayısı</h5>
                            </div>
                            <div class="card-body">
                                <div style="position: relative; height: 300px; width: 100%;">
                                    <canvas id="yeniDanisanChart"></canvas>
                                </div>
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
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Kazanç ve Randevu Grafiği
        const kazancCtx = document.getElementById('kazancChart').getContext('2d');
        new Chart(kazancCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Kazanç (₺)',
                    data: <?php echo json_encode($kazanc_data); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1,
                    yAxisID: 'y'
                }, {
                    label: 'Randevu Sayısı',
                    data: <?php echo json_encode($randevu_data); ?>,
                    borderColor: 'rgb(255, 99, 132)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Kazanç (₺)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Randevu Sayısı'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Yeni Danışan Grafiği
        const yeniDanisanCtx = document.getElementById('yeniDanisanChart').getContext('2d');
        new Chart(yeniDanisanCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Yeni Danışan Sayısı',
                    data: <?php echo json_encode($yeni_danisan_data); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgb(54, 162, 235)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Danışan Sayısı'
                        }
                    }
                }
            }
        });
    });
    </script>
</body>
</html> 