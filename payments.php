<?php
session_start();
require_once 'config/database.php';
require_once 'includes/settings.php';

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
            a.status,
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

// Ödeme bekleyenler: dönemin TAMAMI (sayfalamadan bağımsız), iptaller hariç — her sayfada en üstte
try {
    $stmt = $db->prepare("
        SELECT
            a.id as appointment_id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            c.name as client_name,
            NULL as payment_id,
            NULL as amount,
            NULL as payment_method,
            NULL as payment_date,
            NULL as notes,
            'unpaid' as payment_status
        FROM appointments a
        JOIN clients c ON a.client_id = c.id
        LEFT JOIN payments p ON a.id = p.appointment_id
        WHERE DATE(a.appointment_date) BETWEEN ? AND ?
        AND (a.appointment_date < CURDATE() OR (a.appointment_date = CURDATE() AND a.appointment_time < CURTIME()))
        AND p.id IS NULL
        AND (a.status IS NULL OR a.status <> 'iptal')
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([$first_day, $last_day]);
    $unpaidRows = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Ödeme bekleyen seanslar alınırken bir hata oluştu: " . $e->getMessage();
    $unpaidRows = [];
}

// ---------------------------------------------------------------------------
// Görünüm yardımcıları (yalnızca biçimlendirme; sorgulara dokunmaz)
// ---------------------------------------------------------------------------
$trDays = ['Monday' => 'Pazartesi', 'Tuesday' => 'Salı', 'Wednesday' => 'Çarşamba', 'Thursday' => 'Perşembe', 'Friday' => 'Cuma', 'Saturday' => 'Cumartesi', 'Sunday' => 'Pazar'];
$trMonths = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
$methodNames = ['cash' => 'Nakit', 'card' => 'Kart', 'bank_transfer' => 'Havale/EFT'];

function money($amount) {
    $amount = (float) $amount;
    return '₺' . number_format($amount, (floor($amount) == $amount) ? 0 : 2, ',', '.');
}

function trDateLong($ts, $trDays, $trMonths) {
    return date('j', $ts) . ' ' . $trMonths[(int) date('n', $ts)] . ' ' . $trDays[date('l', $ts)];
}

function payDayLabel($dateStr, $trDays, $trMonths) {
    $ts = strtotime($dateStr);
    $datePart = date('j', $ts) . ' ' . $trMonths[(int) date('n', $ts)];
    if ($dateStr === date('Y-m-d')) return 'Bugün, ' . $datePart;
    if ($dateStr === date('Y-m-d', strtotime('-1 day'))) return 'Dün, ' . $datePart;
    return $trDays[date('l', $ts)] . ', ' . $datePart;
}

// Seçili dönemin adı
$isYear = strlen($selected_month) === 4;
$periodTs = strtotime($first_day);
if ($isYear) {
    $periodLabel = $selected_month . ' yılı';
    $periodShort = $selected_month;
} else {
    $periodLabel = $trMonths[(int) date('n', $periodTs)] . ' ' . date('Y', $periodTs);
    $periodShort = $trMonths[(int) date('n', $periodTs)];
}

// Dönem seçenekleri: bu ay, son 12 ay, son 3 yıl
$periodOptions = [];
$periodOptions[] = [date('Y-m'), 'Bu ay'];
$optBase = new DateTime();
$optBase->setDate((int) $optBase->format('Y'), (int) $optBase->format('n'), 1);
for ($i = 1; $i <= 12; $i++) {
    $optDate = clone $optBase;
    $optDate->modify("-$i months");
    $periodOptions[] = [$optDate->format('Y-m'), $trMonths[(int) $optDate->format('n')] . ' ' . $optDate->format('Y')];
}
for ($i = 0; $i < 3; $i++) {
    $optYear = (string) ((int) date('Y') - $i);
    $periodOptions[] = [$optYear, $optYear . ' yılı'];
}
// Listede olmayan (daha eski) bir dönem açıksa onu da göster
if (!in_array($selected_month, array_column($periodOptions, 0), true) && preg_match('/^\d{4}(-\d{2})?$/', $selected_month)) {
    array_unshift($periodOptions, [$selected_month, $periodLabel]);
}

// Alınan ödemeler güne göre (sayfalı listeden); ödeme bekleyenler yukarıda dönemin tamamından geldi
$paidByDay = [];
$paidCount = 0;
foreach ($payments as $payment) {
    if ($payment['payment_id']) {
        $paidByDay[$payment['appointment_date']][] = $payment;
        $paidCount++;
    }
}
$pageNote = $total_pages > 1 ? ' · bu sayfada' : '';

$pageUrl = function ($n) use ($selected_month) {
    return '?sayfa=' . (int) $n . '&month=' . urlencode($selected_month);
};

$pageTitle = 'Kasa';
$pageSubtitle = $periodLabel;

// Header'ı dahil et
include 'includes/header.php';
?>
<body class="<?php echo $themeClass; ?>" data-page="payments">
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main id="content" tabindex="-1">
            <?php include 'includes/topbar.php'; ?>

            <div class="page">
                <nav class="seg seg-block" aria-label="Kasa">
                    <a class="seg-btn active" aria-current="page" href="payments">Ödemeler</a>
                    <a class="seg-btn" href="expenses">Giderler</a>
                </nav>

                <!-- Dönem seçimi -->
                <form method="GET" class="kasa-period" id="monthFilterForm">
                    <label for="month" class="form-label">Dönem</label>
                    <select class="form-select" name="month" id="month" onchange="document.getElementById('monthFilterForm').submit();">
                        <?php foreach ($periodOptions as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt[0]); ?>"<?php echo $selected_month === $opt[0] ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt[1]); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit" class="btn btn-secondary btn-sm">Göster</button></noscript>
                </form>

                <!-- Dönem özeti -->
                <div class="ledger" aria-label="<?php echo htmlspecialchars($periodLabel); ?> gelir özeti">
                    <div class="ledger-item is-lead">
                        <span class="ledger-label"><?php echo htmlspecialchars($periodShort); ?> toplamı</span>
                        <span class="ledger-value"><?php echo money($monthly_income); ?></span>
                    </div>
                    <div class="ledger-item">
                        <span class="ledger-label">Nakit + Havale/EFT</span>
                        <span class="ledger-value"><?php echo money($cash_income); ?></span>
                    </div>
                    <div class="ledger-item">
                        <span class="ledger-label">Kart</span>
                        <span class="ledger-value"><?php echo money($card_income); ?></span>
                    </div>
                </div>

                <?php if (empty($payments) && empty($unpaidRows)): ?>
                <section class="section" aria-labelledby="paymentsEmptyTitle">
                    <div class="empty">
                        <p class="empty-title" id="paymentsEmptyTitle"><?php echo htmlspecialchars($periodLabel); ?> için geçmiş seans yok</p>
                        <p>Seans saati geçtiğinde burada listelenir; ödemesi alınmamış olanlar en üstte “Ödeme al” düğmesiyle görünür. Başka bir dönem için üstteki listeden seçin.</p>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (!empty($unpaidRows)): ?>
                <!-- Ödeme bekleyenler -->
                <section class="section" aria-labelledby="unpaidTitle">
                    <div class="section-head">
                        <h2 class="section-title" id="unpaidTitle">Ödeme bekleyenler</h2>
                        <span class="section-note"><?php echo count($unpaidRows); ?> seans · <?php echo htmlspecialchars($periodLabel); ?></span>
                    </div>
                    <div class="list">
                        <?php foreach ($unpaidRows as $payment):
                            $ts = strtotime($payment['appointment_date'] . ' ' . $payment['appointment_time']);
                        ?>
                        <div class="row-item">
                            <span class="row-time"><?php echo date('H:i', $ts); ?><small><?php echo date('j', $ts) . ' ' . mb_substr($trMonths[(int) date('n', $ts)], 0, 3); ?></small></span>
                            <div class="row-main">
                                <p class="row-title"><span><?php echo htmlspecialchars($payment['client_name']); ?></span></p>
                                <p class="row-meta"><span class="mark mark-unpaid">Ödenmedi</span></p>
                            </div>
                            <div class="row-trail">
                                <button type="button" class="btn btn-sm btn-brass" data-bs-toggle="modal" data-bs-target="#paymentModal<?php echo (int) $payment['appointment_id']; ?>">
                                    Ödeme al
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (!empty($paidByDay)): ?>
                <!-- Alınan ödemeler, güne göre -->
                <section class="section" aria-labelledby="paidTitle">
                    <div class="section-head">
                        <h2 class="section-title" id="paidTitle">Alınan ödemeler</h2>
                        <span class="section-note"><?php echo $paidCount; ?> seans<?php echo $pageNote; ?></span>
                    </div>
                    <?php foreach ($paidByDay as $date => $items):
                        $dayTotal = 0;
                        foreach ($items as $item) { $dayTotal += (float) $item['amount']; }
                    ?>
                    <div class="list-day"><?php echo htmlspecialchars(payDayLabel($date, $trDays, $trMonths)); ?><span><?php echo money($dayTotal); ?></span></div>
                    <div class="list">
                        <?php foreach ($items as $payment):
                            $method = $methodNames[$payment['payment_method']] ?? $payment['payment_method'];
                        ?>
                        <div class="row-item">
                            <span class="row-time"><?php echo date('H:i', strtotime($payment['appointment_time'])); ?></span>
                            <div class="row-main">
                                <p class="row-title"><span><?php echo htmlspecialchars($payment['client_name']); ?></span></p>
                                <p class="row-meta">
                                    <span class="mark mark-paid">Ödendi · <?php echo htmlspecialchars((string) $method); ?></span><?php if (!empty($payment['notes'])): ?> · <?php echo htmlspecialchars($payment['notes']); ?><?php endif; ?>
                                </p>
                            </div>
                            <div class="row-trail"><span class="row-amount"><?php echo money($payment['amount']); ?></span></div>
                            <div class="row-actions">
                                <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editPaymentModal<?php echo (int) $payment['payment_id']; ?>">Düzenle</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelPaymentModal<?php echo (int) $payment['payment_id']; ?>">İptal et</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </section>
                <?php endif; ?>

                <!-- Sayfalama -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Sayfalama" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($sayfa > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo htmlspecialchars($pageUrl($sayfa - 1)); ?>" aria-label="Önceki">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $sayfa - 2);
                        $end_page = min($total_pages, $sayfa + 2);

                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($pageUrl(1)) . '">1</a></li>';
                            if ($start_page > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }

                        for ($i = $start_page; $i <= $end_page; $i++) {
                            echo '<li class="page-item ' . ($i == $sayfa ? 'active' : '') . '"' . ($i == $sayfa ? ' aria-current="page"' : '') . '>';
                            echo '<a class="page-link" href="' . htmlspecialchars($pageUrl($i)) . '">' . $i . '</a>';
                            echo '</li>';
                        }

                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($pageUrl($total_pages)) . '">' . $total_pages . '</a></li>';
                        }
                        ?>

                        <?php if ($sayfa < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo htmlspecialchars($pageUrl($sayfa + 1)); ?>" aria-label="Sonraki">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modallar -->
    <?php foreach (array_merge($unpaidRows, array_filter($payments, function ($p) { return !empty($p['payment_id']); })) as $payment):
        $ts = strtotime($payment['appointment_date'] . ' ' . $payment['appointment_time']);
        $sumTime = date('H:i', $ts);
        $sumDate = trDateLong($ts, $trDays, $trMonths);
    ?>
    <?php if (!$payment['payment_id']):
        $aid = (int) $payment['appointment_id'];
    ?>
    <!-- Ödeme al -->
    <div class="modal fade" id="paymentModal<?php echo $aid; ?>" tabindex="-1" aria-labelledby="paymentModalTitle<?php echo $aid; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="paymentModalTitle<?php echo $aid; ?>">Ödeme al</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="sheet-summary">
                        <span class="row-time"><?php echo $sumTime; ?></span>
                        <div>
                            <p class="row-title"><span><?php echo htmlspecialchars($payment['client_name']); ?></span></p>
                            <p class="row-meta"><?php echo htmlspecialchars($sumDate); ?></p>
                        </div>
                    </div>
                    <form action="process/add-payment" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="appointment_id" value="<?php echo $aid; ?>">
                        <input type="hidden" name="return_to" value="payments">
                        <div class="field">
                            <label for="amount<?php echo $aid; ?>" class="form-label">Tutar (₺)</label>
                            <input type="number" inputmode="decimal" class="form-control tnum" id="amount<?php echo $aid; ?>" name="amount" value="<?php echo htmlspecialchars(feeInputValue(getSessionFee($db))); ?>" step="0.01" min="0" required>
                            <div class="invalid-feedback">Tutarı girin.</div>
                        </div>
                        <fieldset class="field">
                            <legend class="form-label">Ödeme yöntemi</legend>
                            <div class="choice">
                                <input type="radio" name="payment_method" id="payCash<?php echo $aid; ?>" value="cash" checked required>
                                <label for="payCash<?php echo $aid; ?>"><i class="bi bi-cash-stack" aria-hidden="true"></i>Nakit</label>
                                <input type="radio" name="payment_method" id="payCard<?php echo $aid; ?>" value="card">
                                <label for="payCard<?php echo $aid; ?>"><i class="bi bi-credit-card" aria-hidden="true"></i>Kart</label>
                                <input type="radio" name="payment_method" id="payBank<?php echo $aid; ?>" value="bank_transfer">
                                <label for="payBank<?php echo $aid; ?>"><i class="bi bi-bank" aria-hidden="true"></i>Havale/EFT</label>
                            </div>
                        </fieldset>
                        <div class="field">
                            <label for="notes<?php echo $aid; ?>" class="form-label">Not <span class="ink-3">(isteğe bağlı)</span></label>
                            <textarea class="form-control" id="notes<?php echo $aid; ?>" name="notes" rows="2"></textarea>
                        </div>
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-brass">Ödemeyi kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php else:
        $pid = (int) $payment['payment_id'];
        $currentMethod = $payment['payment_method'];
    ?>
    <!-- Ödemeyi düzenle -->
    <div class="modal fade" id="editPaymentModal<?php echo $pid; ?>" tabindex="-1" aria-labelledby="editPaymentModalTitle<?php echo $pid; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="editPaymentModalTitle<?php echo $pid; ?>">Ödemeyi düzenle</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="sheet-summary">
                        <span class="row-time"><?php echo $sumTime; ?></span>
                        <div>
                            <p class="row-title"><span><?php echo htmlspecialchars($payment['client_name']); ?></span></p>
                            <p class="row-meta"><?php echo htmlspecialchars($sumDate); ?></p>
                        </div>
                    </div>
                    <form action="process/edit-payment" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="payment_id" value="<?php echo $pid; ?>">
                        <div class="field">
                            <label for="edit_amount<?php echo $pid; ?>" class="form-label">Tutar (₺)</label>
                            <input type="number" inputmode="decimal" class="form-control tnum" id="edit_amount<?php echo $pid; ?>" name="amount" value="<?php echo htmlspecialchars((string) $payment['amount']); ?>" step="0.01" min="0" required>
                            <div class="invalid-feedback">Tutarı girin.</div>
                        </div>
                        <fieldset class="field">
                            <legend class="form-label">Ödeme yöntemi</legend>
                            <div class="choice">
                                <input type="radio" name="payment_method" id="editCash<?php echo $pid; ?>" value="cash"<?php echo $currentMethod === 'cash' ? ' checked' : ''; ?> required>
                                <label for="editCash<?php echo $pid; ?>"><i class="bi bi-cash-stack" aria-hidden="true"></i>Nakit</label>
                                <input type="radio" name="payment_method" id="editCard<?php echo $pid; ?>" value="card"<?php echo $currentMethod === 'card' ? ' checked' : ''; ?>>
                                <label for="editCard<?php echo $pid; ?>"><i class="bi bi-credit-card" aria-hidden="true"></i>Kart</label>
                                <input type="radio" name="payment_method" id="editBank<?php echo $pid; ?>" value="bank_transfer"<?php echo $currentMethod === 'bank_transfer' ? ' checked' : ''; ?>>
                                <label for="editBank<?php echo $pid; ?>"><i class="bi bi-bank" aria-hidden="true"></i>Havale/EFT</label>
                            </div>
                        </fieldset>
                        <div class="field">
                            <label for="edit_notes<?php echo $pid; ?>" class="form-label">Not <span class="ink-3">(isteğe bağlı)</span></label>
                            <textarea class="form-control" id="edit_notes<?php echo $pid; ?>" name="notes" rows="2"><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></textarea>
                        </div>
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-primary">Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Ödemeyi iptal et -->
    <div class="modal fade" id="cancelPaymentModal<?php echo $pid; ?>" tabindex="-1" aria-labelledby="cancelPaymentModalTitle<?php echo $pid; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="cancelPaymentModalTitle<?php echo $pid; ?>">Ödemeyi iptal et</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="sheet-summary">
                        <span class="row-time"><?php echo $sumTime; ?></span>
                        <div>
                            <p class="row-title"><span><?php echo htmlspecialchars($payment['client_name']); ?></span></p>
                            <p class="row-meta"><?php echo htmlspecialchars($sumDate); ?> · <?php echo money($payment['amount']); ?></p>
                        </div>
                    </div>
                    <p>Bu seans için alınan <strong class="tnum"><?php echo money($payment['amount']); ?></strong> ödeme kaydı silinir ve seans yeniden “Ödenmedi” olarak görünür. Bu işlem geri alınamaz.</p>
                    <form action="process/cancel-payment" method="POST">
                        <input type="hidden" name="payment_id" value="<?php echo $pid; ?>">
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-danger">Ödemeyi iptal et</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>

<?php include 'includes/footer.php'; ?>
