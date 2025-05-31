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

// Seçilen ayı al, yoksa bu ayı kullan
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Yıl seçilmişse tüm yıl için tarih aralığını ayarla
if (strlen($selected_month) === 4) {
    $year = $selected_month;
    $first_day = $year . '-01-01';
    $last_day = $year . '-12-31';
} else {
    $first_day = date('Y-m-01', strtotime($selected_month));
    $last_day = date('Y-m-t', strtotime($selected_month));
}

// Bu ayki toplam kazanç
try {
    // Debug: Seçilen ay aralığını logla
    error_log("Payment stats debug - Selected month: $selected_month, First day: $first_day, Last day: $last_day");
    
    // Toplam kazanç - appointment_date kullan (çünkü payments tablosunda appointment_date göre join yapıyoruz)
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(p.amount), 0) as total 
        FROM payments p 
        JOIN appointments a ON p.appointment_id = a.id
        WHERE a.appointment_date BETWEEN ? AND ?
    ");
    $stmt->execute([$first_day, $last_day]);
    $monthly_income = $stmt->fetch()['total'];
    
    error_log("Payment stats debug - Monthly income: $monthly_income");

    // Nakit ve havale/EFT toplamı
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(p.amount), 0) as total 
        FROM payments p 
        JOIN appointments a ON p.appointment_id = a.id
        WHERE a.appointment_date BETWEEN ? AND ? 
        AND p.payment_method IN ('cash', 'bank_transfer')
    ");
    $stmt->execute([$first_day, $last_day]);
    $cash_income = $stmt->fetch()['total'];

    // Kart toplamı
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(p.amount), 0) as total 
        FROM payments p 
        JOIN appointments a ON p.appointment_id = a.id
        WHERE a.appointment_date BETWEEN ? AND ? 
        AND p.payment_method = 'card'
    ");
    $stmt->execute([$first_day, $last_day]);
    $card_income = $stmt->fetch()['total'];
    
    error_log("Payment stats debug - Cash: $cash_income, Card: $card_income");
} catch(PDOException $e) {
    error_log("Payment stats error: " . $e->getMessage());
    $_SESSION['error'] = "Toplam kazanç hesaplanırken bir hata oluştu: " . $e->getMessage();
    $monthly_income = 0;
    $cash_income = 0;
    $card_income = 0;
}

// Geçmiş randevuları getir
try {
    $stmt = $db->prepare("
        SELECT a.*, c.name as client_name, p.id as payment_id, p.amount, p.payment_method, p.payment_date
        FROM appointments a 
        JOIN clients c ON a.client_id = c.id 
        LEFT JOIN payments p ON a.id = p.appointment_id
        WHERE a.appointment_date < CURDATE() OR (a.appointment_date = CURDATE() AND a.appointment_time < CURTIME())
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute();
    $past_appointments = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Geçmiş randevular alınırken bir hata oluştu: " . $e->getMessage();
    $past_appointments = [];
}

// Sayfalama için gerekli değişkenler
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
$limit = 15;
$offset = ($sayfa - 1) * $limit;

// Toplam randevu sayısını al
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM appointments a
        WHERE DATE(a.appointment_date) BETWEEN ? AND ?
        AND (a.appointment_date < CURDATE() OR (a.appointment_date = CURDATE() AND a.appointment_time < CURTIME()))
    ");
    $stmt->execute([$first_day, $last_day]);
    $total_payments = $stmt->fetch()['total'];
    $total_pages = ceil($total_payments / $limit);
} catch(PDOException $e) {
    $_SESSION['error'] = "Toplam randevu sayısı alınırken bir hata oluştu: " . $e->getMessage();
    $total_pages = 1;
}

// Ödemeleri sayfalı şekilde çek
try {
    $stmt = $db->prepare("
        SELECT 
            a.id as appointment_id,
            a.appointment_date,
            a.appointment_time,
            c.name as client_name,
            p.id as payment_id,
            p.amount,
            p.payment_method,
            p.payment_date,
            p.notes,
            CASE 
                WHEN p.id IS NOT NULL THEN 'paid'
                ELSE 'unpaid'
            END as payment_status
        FROM appointments a
        JOIN clients c ON a.client_id = c.id
        LEFT JOIN payments p ON a.id = p.appointment_id
        WHERE DATE(a.appointment_date) BETWEEN ? AND ?
        AND (a.appointment_date < CURDATE() OR (a.appointment_date = CURDATE() AND a.appointment_time < CURTIME()))
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$first_day, $last_day, $limit, $offset]);
    $payments = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Ödeme listesi alınırken bir hata oluştu: " . $e->getMessage();
    $payments = [];
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
                <!-- Ay/Yıl Filtresi -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" class="row g-3 align-items-end" id="monthFilterForm">
                                    <div class="col-md-12">
                                        <label for="month" class="form-label">Dönem Seçin</label>
                                        <select class="form-select" name="month" id="month" onchange="document.getElementById('monthFilterForm').submit();">
                                            <option value="<?php echo date('Y-m'); ?>" <?php echo $selected_month === date('Y-m') ? 'selected' : ''; ?>>Bu Ay</option>
                                            <?php
                                            // DateTime kullanarak güvenli ay hesaplama
                                            $current_date = new DateTime();
                                            $current_date->setDate($current_date->format('Y'), $current_date->format('n'), 1);
                                            
                                            // Son 12 ayı listele
                                            for ($i = 1; $i <= 12; $i++) {
                                                $date = clone $current_date;
                                                $date->modify("-$i months");
                                                
                                                $month = $date->format('Y-m');
                                                $monthName = $date->format('F Y');
                                                $turkishMonths = [
                                                    'January' => 'Ocak', 'February' => 'Şubat', 'March' => 'Mart',
                                                    'April' => 'Nisan', 'May' => 'Mayıs', 'June' => 'Haziran',
                                                    'July' => 'Temmuz', 'August' => 'Ağustos', 'September' => 'Eylül',
                                                    'October' => 'Ekim', 'November' => 'Kasım', 'December' => 'Aralık'
                                                ];
                                                $monthName = str_replace(array_keys($turkishMonths), array_values($turkishMonths), $monthName);
                                                echo '<option value="' . $month . '"' . ($selected_month === $month ? ' selected' : '') . '>' . $monthName . '</option>';
                                            }
                                            
                                            // Son 3 yılı da ekle
                                            for ($i = 0; $i < 3; $i++) {
                                                $year = date('Y') - $i;
                                                echo '<option value="' . $year . '"' . ($selected_month === $year ? ' selected' : '') . '>' . $year . ' Yılı</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aylık Kazanç Kartları -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card d-flex align-items-center">
                            <div class="stats-info flex-grow-1">
                                <div class="number"><?php echo number_format($monthly_income, 2, ',', '.'); ?> ₺</div>
                                <div class="label">
                                    <?php 
                                    if (strlen($selected_month) === 4) {
                                        echo $selected_month . ' Yılı Toplam Kazanç';
                                    } else {
                                        $displayMonth = date('F Y', strtotime($selected_month));
                                        $turkishMonths = [
                                            'January' => 'Ocak', 'February' => 'Şubat', 'March' => 'Mart',
                                            'April' => 'Nisan', 'May' => 'Mayıs', 'June' => 'Haziran',
                                            'July' => 'Temmuz', 'August' => 'Ağustos', 'September' => 'Eylül',
                                            'October' => 'Ekim', 'November' => 'Kasım', 'December' => 'Aralık'
                                        ];
                                        $displayMonth = str_replace(array_keys($turkishMonths), array_values($turkishMonths), $displayMonth);
                                        echo $displayMonth . ' Toplam Kazanç';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="icon text-success ms-3">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card d-flex align-items-center">
                            <div class="stats-info flex-grow-1">
                                <div class="number"><?php echo number_format($cash_income, 2, ',', '.'); ?> ₺</div>
                                <div class="label">
                                    <?php 
                                    if (strlen($selected_month) === 4) {
                                        echo $selected_month . ' Yılı Nakit + Havale/EFT';
                                    } else {
                                        $displayMonth = date('F Y', strtotime($selected_month));
                                        $turkishMonths = [
                                            'January' => 'Ocak', 'February' => 'Şubat', 'March' => 'Mart',
                                            'April' => 'Nisan', 'May' => 'Mayıs', 'June' => 'Haziran',
                                            'July' => 'Temmuz', 'August' => 'Ağustos', 'September' => 'Eylül',
                                            'October' => 'Ekim', 'November' => 'Kasım', 'December' => 'Aralık'
                                        ];
                                        $displayMonth = str_replace(array_keys($turkishMonths), array_values($turkishMonths), $displayMonth);
                                        echo $displayMonth . ' Nakit + Havale/EFT';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="icon text-primary ms-3">
                                <i class="bi bi-cash"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card d-flex align-items-center">
                            <div class="stats-info flex-grow-1">
                                <div class="number"><?php echo number_format($card_income, 2, ',', '.'); ?> ₺</div>
                                <div class="label">
                                    <?php 
                                    if (strlen($selected_month) === 4) {
                                        echo $selected_month . ' Yılı Kredi Kartı Kazancı';
                                    } else {
                                        $displayMonth = date('F Y', strtotime($selected_month));
                                        $turkishMonths = [
                                            'January' => 'Ocak', 'February' => 'Şubat', 'March' => 'Mart',
                                            'April' => 'Nisan', 'May' => 'Mayıs', 'June' => 'Haziran',
                                            'July' => 'Temmuz', 'August' => 'Ağustos', 'September' => 'Eylül',
                                            'October' => 'Ekim', 'November' => 'Kasım', 'December' => 'Aralık'
                                        ];
                                        $displayMonth = str_replace(array_keys($turkishMonths), array_values($turkishMonths), $displayMonth);
                                        echo $displayMonth . ' Kredi Kartı Kazancı';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="icon text-info ms-3">
                                <i class="bi bi-credit-card"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Geçmiş Randevular ve Ödemeler -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <?php 
                            if (strlen($selected_month) === 4) {
                                echo $selected_month . ' Yılı Randevular ve Ödemeler';
                            } else {
                                $displayMonth = date('F Y', strtotime($selected_month));
                                $turkishMonths = [
                                    'January' => 'Ocak', 'February' => 'Şubat', 'March' => 'Mart',
                                    'April' => 'Nisan', 'May' => 'Mayıs', 'June' => 'Haziran',
                                    'July' => 'Temmuz', 'August' => 'Ağustos', 'September' => 'Eylül',
                                    'October' => 'Ekim', 'November' => 'Kasım', 'December' => 'Aralık'
                                ];
                                $displayMonth = str_replace(array_keys($turkishMonths), array_values($turkishMonths), $displayMonth);
                                echo $displayMonth . ' Randevular ve Ödemeler';
                            }
                            ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tarih</th>
                                        <th>Saat</th>
                                        <th>Danışan</th>
                                        <th>Ödeme Durumu</th>
                                        <th>Ödeme Yöntemi</th>
                                        <th>Tutar</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo date('d.m.Y', strtotime($payment['appointment_date'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($payment['appointment_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($payment['client_name']); ?></td>
                                        <td>
                                            <?php if ($payment['payment_id']): ?>
                                                <span class="badge bg-success">Ödendi</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Ödenmedi</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($payment['payment_id']): ?>
                                                <?php
                                                $methods = [
                                                    'cash' => 'Nakit',
                                                    'card' => 'Kredi Kartı',
                                                    'bank_transfer' => 'Havale/EFT'
                                                ];
                                                echo $methods[$payment['payment_method']] ?? $payment['payment_method'];
                                                ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($payment['payment_id']): ?>
                                                <?php echo number_format($payment['amount'], 2, ',', '.'); ?> ₺
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$payment['payment_id']): ?>
                                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal<?php echo $payment['appointment_id']; ?>">
                                                    <i class="bi bi-cash me-1"></i> Ödeme Al
                                                </button>
                                            <?php else: ?>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editPaymentModal<?php echo $payment['payment_id']; ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#cancelPaymentModal<?php echo $payment['payment_id']; ?>">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <!-- Sayfalama -->
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Sayfalama" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($sayfa > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?sayfa=<?php echo $sayfa - 1; ?>&month=<?php echo $selected_month; ?>" aria-label="Önceki">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $sayfa - 2);
                                    $end_page = min($total_pages, $sayfa + 2);
                                    
                                    if ($start_page > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?sayfa=1&month=' . $selected_month . '">1</a></li>';
                                        if ($start_page > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                    }
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        echo '<li class="page-item ' . ($i == $sayfa ? 'active' : '') . '">';
                                        echo '<a class="page-link" href="?sayfa=' . $i . '&month=' . $selected_month . '">' . $i . '</a>';
                                        echo '</li>';
                                    }
                                    
                                    if ($end_page < $total_pages) {
                                        if ($end_page < $total_pages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="?sayfa=' . $total_pages . '&month=' . $selected_month . '">' . $total_pages . '</a></li>';
                                    }
                                    ?>
                                    
                                    <?php if ($sayfa < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?sayfa=<?php echo $sayfa + 1; ?>&month=<?php echo $selected_month; ?>" aria-label="Sonraki">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <?php foreach ($payments as $payment): ?>
    <?php if (!$payment['payment_id']): ?>
    <!-- Ödeme Modal -->
    <div class="modal fade" id="paymentModal<?php echo $payment['appointment_id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ödeme Al</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="process/add-payment" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="appointment_id" value="<?php echo $payment['appointment_id']; ?>">
                        <div class="mb-3">
                            <label for="amount<?php echo $payment['appointment_id']; ?>" class="form-label">Tutar</label>
                            <input type="number" class="form-control" id="amount<?php echo $payment['appointment_id']; ?>" name="amount" value="1700" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="payment_method<?php echo $payment['appointment_id']; ?>" class="form-label">Ödeme Yöntemi</label>
                            <select class="form-select" id="payment_method<?php echo $payment['appointment_id']; ?>" name="payment_method" required>
                                <option value="cash">Nakit</option>
                                <option value="card">Kredi Kartı</option>
                                <option value="bank_transfer">Havale/EFT</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="notes<?php echo $payment['appointment_id']; ?>" class="form-label">Notlar</label>
                            <textarea class="form-control" id="notes<?php echo $payment['appointment_id']; ?>" name="notes" rows="3"></textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" class="btn btn-success">Ödeme Al</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Ödeme Düzenleme Modal -->
    <div class="modal fade" id="editPaymentModal<?php echo $payment['payment_id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ödeme Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="process/edit-payment" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="payment_id" value="<?php echo $payment['payment_id']; ?>">
                        <div class="mb-3">
                            <label for="edit_amount<?php echo $payment['payment_id']; ?>" class="form-label">Tutar</label>
                            <input type="number" class="form-control" id="edit_amount<?php echo $payment['payment_id']; ?>" name="amount" value="<?php echo $payment['amount']; ?>" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_payment_method<?php echo $payment['payment_id']; ?>" class="form-label">Ödeme Yöntemi</label>
                            <select class="form-select" id="edit_payment_method<?php echo $payment['payment_id']; ?>" name="payment_method" required>
                                <option value="cash" <?php echo $payment['payment_method'] == 'cash' ? 'selected' : ''; ?>>Nakit</option>
                                <option value="card" <?php echo $payment['payment_method'] == 'card' ? 'selected' : ''; ?>>Kredi Kartı</option>
                                <option value="bank_transfer" <?php echo $payment['payment_method'] == 'bank_transfer' ? 'selected' : ''; ?>>Havale/EFT</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_notes<?php echo $payment['payment_id']; ?>" class="form-label">Notlar</label>
                            <textarea class="form-control" id="edit_notes<?php echo $payment['payment_id']; ?>" name="notes" rows="3"><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></textarea>
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
    <?php endif; ?>
    <?php endforeach; ?>

    <!-- Ödeme İptal Modali -->
    <?php foreach ($payments as $payment): ?>
    <?php if ($payment['payment_id']): ?>
    <div class="modal fade" id="cancelPaymentModal<?php echo $payment['payment_id']; ?>" tabindex="-1">
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
                        <strong><?php echo htmlspecialchars($payment['client_name']); ?></strong> isimli danışanın 
                        <strong><?php echo date('d.m.Y H:i', strtotime($payment['appointment_date'] . ' ' . $payment['appointment_time'])); ?></strong> 
                        tarihli randevusu için alınan <strong><?php echo number_format($payment['amount'], 2, ',', '.'); ?> ₺</strong> 
                        ödemeyi iptal etmek istediğinizden emin misiniz?
                    </p>
                    <p class="text-muted">
                        Ödeme iptal edildiğinde, randevu "ödenmedi" durumuna geçecektir.
                    </p>
                </div>
                <div class="modal-footer">
                    <form action="process/cancel-payment" method="POST" style="display: inline;">
                        <input type="hidden" name="payment_id" value="<?php echo $payment['payment_id']; ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-circle me-1"></i> Ödeme İptal Et
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>

<?php include 'includes/footer.php'; ?> 