<?php
session_start();
require_once 'config/database.php';

// Tema
if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
    $themeClass = 'dark';
} else {
    $themeClass = '';
}

if (!isset($_SESSION['user_id'])) {
    header('Location: index');
    exit();
}

// Filtre (ay/yıl)
$selected_period = isset($_GET['period']) ? $_GET['period'] : date('Y-m');
if (strlen($selected_period) === 4) {
    $first_day = $selected_period . '-01-01';
    $last_day = $selected_period . '-12-31';
} else {
    $first_day = date('Y-m-01', strtotime($selected_period));
    $last_day = date('Y-m-t', strtotime($selected_period));
}

// Özetler (tekil giderler)
try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $stmt->execute([$first_day, $last_day]);
    $total_expense = (float)$stmt->fetch()['total'];

    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE expense_date BETWEEN ? AND ? AND payment_method IN ('cash','bank_transfer')");
    $stmt->execute([$first_day, $last_day]);
    $cash_total = (float)$stmt->fetch()['total'];

    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE expense_date BETWEEN ? AND ? AND payment_method = 'card'");
    $stmt->execute([$first_day, $last_day]);
    $card_total = (float)$stmt->fetch()['total'];
} catch(PDOException $e) {
    $_SESSION['error'] = 'Gider özetleri alınırken hata: ' . $e->getMessage();
    $total_expense = 0; $cash_total = 0; $card_total = 0;
}

// Sayfalama
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
$limit = 15;
$offset = ($sayfa - 1) * $limit;

try {
    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $stmt->execute([$first_day, $last_day]);
    $total_rows = (int)$stmt->fetch()['total'];
    $total_pages = max(1, (int)ceil($total_rows / $limit));
} catch(PDOException $e) {
    $_SESSION['error'] = 'Gider sayısı alınırken hata: ' . $e->getMessage();
    $total_pages = 1; $total_rows = 0;
}

// Liste
try {
    $stmt = $db->prepare("SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date DESC, id DESC LIMIT ? OFFSET ?");
    $stmt->execute([$first_day, $last_day, $limit, $offset]);
    $expenses = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = 'Gider listesi alınırken hata: ' . $e->getMessage();
    $expenses = [];
}

// Tekrarlayan giderler (aktif)
try {
    $stmt = $db->prepare("SELECT * FROM recurring_expenses WHERE active = 1 ORDER BY title ASC");
    $stmt->execute();
    $recurrings = $stmt->fetchAll();
} catch(PDOException $e) {
    $recurrings = [];
}

// Tekrarlayan giderleri seçilen döneme göre sanal kayıtlara dönüştür
$recurring_expense_rows = [];
if (!empty($recurrings)) {
    foreach ($recurrings as $r) {
        $seriesStart = new DateTime($r['start_date']);
        $periodStart = new DateTime($first_day);
        $endBoundary = $last_day;
        if (!empty($r['end_date'])) {
            $endBoundary = min($endBoundary, $r['end_date']);
        }
        $end = new DateTime($endBoundary);

        // Eğer seri başlangıcı son tarihten büyükse, bu kalıp bu döneme düşmez
        if ($seriesStart > $end) {
            continue;
        }

        // Seriyi özgün başlangıçtan başlat, dönemi yakalayana kadar ilerlet
        $current = clone $seriesStart;
        switch ($r['recurrence_interval']) {
            case 'weekly':
                while ($current < $periodStart) { $current->modify('+1 week'); }
                break;
            case 'quarterly':
                while ($current < $periodStart) { $current->modify('+3 months'); }
                break;
            case 'yearly':
                while ($current < $periodStart) { $current->modify('+1 year'); }
                break;
            case 'monthly':
            default:
                while ($current < $periodStart) { $current->modify('+1 month'); }
                break;
        }
        while ($current <= $end) {
            $recurringRow = [
                '__type' => 'recurring',
                'recurring_id' => (int)$r['id'],
                'expense_date' => $current->format('Y-m-d'),
                'title' => $r['title'],
                'category' => $r['category'],
                'payment_method' => $r['payment_method'],
                'amount' => $r['amount'],
            ];
            $recurring_expense_rows[] = $recurringRow;

            // İterasyonu arttır
            switch ($r['recurrence_interval']) {
                case 'weekly':
                    $current->modify('+1 week');
                    break;
                case 'quarterly':
                    $current->modify('+3 months');
                    break;
                case 'yearly':
                    $current->modify('+1 year');
                    break;
                case 'monthly':
                default:
                    $current->modify('+1 month');
                    break;
            }
        }
    }
}

// Tekrarlayan giderleri özetlere ekle
if (!empty($recurring_expense_rows)) {
    $recurring_total = 0.0;
    $recurring_cash_total = 0.0;
    $recurring_card_total = 0.0;
    foreach ($recurring_expense_rows as $row) {
        $recurring_total += (float)$row['amount'];
        if ($row['payment_method'] === 'card') {
            $recurring_card_total += (float)$row['amount'];
        } elseif (in_array($row['payment_method'], ['cash','bank_transfer'])) {
            $recurring_cash_total += (float)$row['amount'];
        }
    }
    $total_expense += $recurring_total;
    $cash_total += $recurring_cash_total;
    $card_total += $recurring_card_total;
}

// Görüntülenecek liste: tekrarlayan sanal + tekil giderler (tarihine göre azalan sırada)
$display_expenses = array_merge($recurring_expense_rows, $expenses);
usort($display_expenses, function($a, $b) {
    $dateA = $a['expense_date'] ?? '';
    $dateB = $b['expense_date'] ?? '';
    if ($dateA === $dateB) return 0;
    return ($dateA > $dateB) ? -1 : 1; // DESC
});

include 'includes/header.php';
?>

<body class="<?php echo $themeClass; ?>">
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

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
                            <div class="card-body">
                                <form method="GET" class="row g-3 align-items-end" id="periodFilterForm">
                                    <div class="col-md-12">
                                        <label for="period" class="form-label">Dönem Seçin</label>
                                        <select class="form-select" name="period" id="period" onchange="document.getElementById('periodFilterForm').submit();">
                                            <option value="<?php echo date('Y-m'); ?>" <?php echo $selected_period === date('Y-m') ? 'selected' : ''; ?>>Bu Ay</option>
                                            <?php
                                            $current_date = new DateTime();
                                            $current_date->setDate($current_date->format('Y'), $current_date->format('n'), 1);
                                            for ($i = 1; $i <= 12; $i++) {
                                                $date = clone $current_date;
                                                $date->modify("-$i months");
                                                $month = $date->format('Y-m');
                                                $monthName = $date->format('F Y');
                                                $tr = ['January'=>'Ocak','February'=>'Şubat','March'=>'Mart','April'=>'Nisan','May'=>'Mayıs','June'=>'Haziran','July'=>'Temmuz','August'=>'Ağustos','September'=>'Eylül','October'=>'Ekim','November'=>'Kasım','December'=>'Aralık'];
                                                $monthName = str_replace(array_keys($tr), array_values($tr), $monthName);
                                                echo '<option value="' . $month . '"' . ($selected_period === $month ? ' selected' : '') . '>' . $monthName . '</option>';
                                            }
                                            for ($i = 0; $i < 3; $i++) {
                                                $year = date('Y') - $i;
                                                echo '<option value="' . $year . '"' . ($selected_period === $year ? ' selected' : '') . '>' . $year . ' Yılı</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card d-flex align-items-center">
                            <div class="stats-info flex-grow-1">
                                <div class="number"><?php echo number_format($total_expense, 2, ',', '.'); ?> ₺</div>
                                <div class="label"><?php echo (strlen($selected_period)===4? $selected_period.' Yılı Toplam Gider' : str_replace(['January','February','March','April','May','June','July','August','September','October','November','December'], ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'], date('F Y', strtotime($selected_period))) . ' Toplam Gider'); ?></div>
                            </div>
                            <div class="icon text-danger ms-3"><i class="bi bi-receipt"></i></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card d-flex align-items-center">
                            <div class="stats-info flex-grow-1">
                                <div class="number"><?php echo number_format($cash_total, 2, ',', '.'); ?> ₺</div>
                                <div class="label"><?php echo (strlen($selected_period)===4? $selected_period.' Yılı Nakit+Havale/EFT' : str_replace(['January','February','March','April','May','June','July','August','September','October','November','December'], ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'], date('F Y', strtotime($selected_period))) . ' Nakit+Havale/EFT'); ?></div>
                            </div>
                            <div class="icon text-primary ms-3"><i class="bi bi-cash"></i></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card d-flex align-items-center">
                            <div class="stats-info flex-grow-1">
                                <div class="number"><?php echo number_format($card_total, 2, ',', '.'); ?> ₺</div>
                                <div class="label"><?php echo (strlen($selected_period)===4? $selected_period.' Yılı Kart ile' : str_replace(['January','February','March','April','May','June','July','August','September','October','November','December'], ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'], date('F Y', strtotime($selected_period))) . ' Kart ile'); ?></div>
                            </div>
                            <div class="icon text-info ms-3"><i class="bi bi-credit-card"></i></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Giderler</h5>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#recurringModal">
                                    <i class="bi bi-arrow-repeat"></i> Tekrarlayan Gider
                                </button>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                    <i class="bi bi-plus-circle"></i> Yeni Gider
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tarih</th>
                                        <th>Başlık</th>
                                        <th>Kategori</th>
                                        <th>Ödeme Yöntemi</th>
                                        <th>Tutar</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($display_expenses)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="bi bi-inbox fs-1 text-muted mb-2"></i>
                                                <p class="text-muted mb-0">Kayıt bulunamadı.</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($display_expenses as $exp): ?>
                                    <tr>
                                        <td><?php echo date('d.m.Y', strtotime($exp['expense_date'])); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($exp['title']); ?>
                                            <?php if (isset($exp['__type']) && $exp['__type'] === 'recurring'): ?>
                                                <i class="bi bi-arrow-repeat text-muted ms-1" title="Tekrarlayan"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($exp['category'] ?? '-'); ?></td>
                                        <td><?php 
                                            $methods = ['cash'=>'Nakit','card'=>'Kredi Kartı','bank_transfer'=>'Havale/EFT','other'=>'Diğer'];
                                            echo $methods[$exp['payment_method']] ?? $exp['payment_method'];
                                        ?></td>
                                        <td><?php echo number_format($exp['amount'], 2, ',', '.'); ?> ₺</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if (isset($exp['__type']) && $exp['__type'] === 'recurring'): ?>
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editRecurringModal<?php echo $exp['recurring_id']; ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteRecurringModal<?php echo $exp['recurring_id']; ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editExpenseModal<?php echo $exp['id']; ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteExpenseModal<?php echo $exp['id']; ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Sayfalama" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($sayfa > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?sayfa=<?php echo $sayfa - 1; ?>&period=<?php echo $selected_period; ?>" aria-label="Önceki">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <?php
                                    $start_page = max(1, $sayfa - 2);
                                    $end_page = min($total_pages, $sayfa + 2);
                                    if ($start_page > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?sayfa=1&period=' . $selected_period . '">1</a></li>';
                                        if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        echo '<li class="page-item ' . ($i == $sayfa ? 'active' : '') . '">';
                                        echo '<a class="page-link" href="?sayfa=' . $i . '&period=' . $selected_period . '">' . $i . '</a>';
                                        echo '</li>';
                                    }
                                    if ($end_page < $total_pages) {
                                        if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        echo '<li class="page-item"><a class="page-link" href="?sayfa=' . $total_pages . '&period=' . $selected_period . '">' . $total_pages . '</a></li>';
                                    }
                                    ?>
                                    <?php if ($sayfa < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?sayfa=<?php echo $sayfa + 1; ?>&period=<?php echo $selected_period; ?>" aria-label="Sonraki">
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

    <?php foreach ($expenses as $exp): ?>
    <div class="modal fade" id="editExpenseModal<?php echo $exp['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gider Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="process/edit-expense" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="id" value="<?php echo $exp['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Başlık</label>
                            <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($exp['title']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kategori</label>
                            <input type="text" class="form-control" name="category" value="<?php echo htmlspecialchars($exp['category'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tutar</label>
                            <input type="number" class="form-control" name="amount" step="0.01" value="<?php echo $exp['amount']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ödeme Yöntemi</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="cash" <?php echo $exp['payment_method']=='cash'?'selected':''; ?>>Nakit</option>
                                <option value="card" <?php echo $exp['payment_method']=='card'?'selected':''; ?>>Kredi Kartı</option>
                                <option value="bank_transfer" <?php echo $exp['payment_method']=='bank_transfer'?'selected':''; ?>>Havale/EFT</option>
                                <option value="other" <?php echo $exp['payment_method']=='other'?'selected':''; ?>>Diğer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tarih</label>
                            <input type="date" class="form-control" name="expense_date" value="<?php echo $exp['expense_date']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notlar</label>
                            <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($exp['notes'] ?? ''); ?></textarea>
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

    <div class="modal fade" id="deleteExpenseModal<?php echo $exp['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gider Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><strong>Dikkat!</strong> Bu işlem geri alınamaz.</div>
                    <p><strong><?php echo htmlspecialchars($exp['title']); ?></strong> giderini silmek istediğinizden emin misiniz?</p>
                </div>
                <div class="modal-footer">
                    <form action="process/delete-expense" method="POST" class="d-inline">
                        <input type="hidden" name="id" value="<?php echo $exp['id']; ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-danger">Sil</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="modal fade" id="addExpenseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Gider Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="process/add-expense" method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label class="form-label">Başlık</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kategori</label>
                            <input type="text" class="form-control" name="category">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tutar</label>
                            <input type="number" class="form-control" name="amount" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ödeme Yöntemi</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="cash">Nakit</option>
                                <option value="card">Kredi Kartı</option>
                                <option value="bank_transfer">Havale/EFT</option>
                                <option value="other">Diğer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tarih</label>
                            <input type="date" class="form-control" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notlar</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
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

    <div class="modal fade" id="recurringModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tekrarlayan Gider</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="process/add-recurring-expense" method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label class="form-label">Başlık</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kategori</label>
                            <input type="text" class="form-control" name="category">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tutar</label>
                            <input type="number" class="form-control" name="amount" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ödeme Yöntemi</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="cash">Nakit</option>
                                <option value="card">Kredi Kartı</option>
                                <option value="bank_transfer">Havale/EFT</option>
                                <option value="other">Diğer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Başlangıç Tarihi</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bitiş Tarihi (Opsiyonel)</label>
                            <input type="date" class="form-control" name="end_date">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tekrar</label>
                            <select class="form-select" name="recurrence_interval" required>
                                <option value="weekly">Haftalık</option>
                                <option value="monthly" selected>Aylık</option>
                                <option value="quarterly">3 Aylık</option>
                                <option value="yearly">Yıllık</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notlar</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" class="btn btn-primary">Kaydet</button>
                        </div>
                    </form>
                </div>
                <?php if (!empty($recurrings)): ?>
                <div class="border-top p-3">
                    <h6 class="mb-3">Aktif Tekrarlayan Giderler</h6>
                    <ul class="list-group">
                        <?php foreach ($recurrings as $r): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <strong><?php echo htmlspecialchars($r['title']); ?></strong>
                                <small class="text-muted"> - <?php echo number_format($r['amount'], 2, ',', '.'); ?> ₺ / <?php echo $r['recurrence_interval']; ?></small>
                            </span>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editRecurringModal<?php echo $r['id']; ?>"><i class="bi bi-pencil"></i></button>
                                <form action="process/toggle-recurring-expense" method="POST" class="ms-2">
                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning"><?php echo $r['active'] ? 'Pasifleştir' : 'Aktifleştir'; ?></button>
                                </form>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($recurrings)): foreach ($recurrings as $r): ?>
    <div class="modal fade" id="editRecurringModal<?php echo $r['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tekrarlayan Gider Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="process/edit-recurring-expense" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Başlık</label>
                            <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($r['title']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kategori</label>
                            <input type="text" class="form-control" name="category" value="<?php echo htmlspecialchars($r['category'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tutar</label>
                            <input type="number" class="form-control" name="amount" step="0.01" value="<?php echo $r['amount']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ödeme Yöntemi</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="cash" <?php echo $r['payment_method']=='cash'?'selected':''; ?>>Nakit</option>
                                <option value="card" <?php echo $r['payment_method']=='card'?'selected':''; ?>>Kredi Kartı</option>
                                <option value="bank_transfer" <?php echo $r['payment_method']=='bank_transfer'?'selected':''; ?>>Havale/EFT</option>
                                <option value="other" <?php echo $r['payment_method']=='other'?'selected':''; ?>>Diğer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Başlangıç Tarihi</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $r['start_date']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bitiş Tarihi (Opsiyonel)</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $r['end_date']; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tekrar</label>
                            <select class="form-select" name="recurrence_interval" required>
                                <option value="weekly" <?php echo $r['recurrence_interval']=='weekly'?'selected':''; ?>>Haftalık</option>
                                <option value="monthly" <?php echo $r['recurrence_interval']=='monthly'?'selected':''; ?>>Aylık</option>
                                <option value="quarterly" <?php echo $r['recurrence_interval']=='quarterly'?'selected':''; ?>>3 Aylık</option>
                                <option value="yearly" <?php echo $r['recurrence_interval']=='yearly'?'selected':''; ?>>Yıllık</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notlar</label>
                            <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($r['notes'] ?? ''); ?></textarea>
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
    <?php endforeach; endif; ?>

    <?php if (!empty($recurrings)): foreach ($recurrings as $r): ?>
    <div class="modal fade" id="deleteRecurringModal<?php echo $r['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tekrarlayan Gideri Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><strong>Dikkat!</strong> Bu işlem geri alınamaz.</div>
                    <p><strong><?php echo htmlspecialchars($r['title']); ?></strong> tekrarlayan giderini silmek istediğinizden emin misiniz?</p>
                </div>
                <div class="modal-footer">
                    <form action="process/delete-recurring-expense" method="POST" class="d-inline">
                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-danger">Sil</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>

<?php include 'includes/footer.php'; ?>


