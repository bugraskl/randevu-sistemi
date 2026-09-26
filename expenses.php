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

// ---------------------------------------------------------------------------
// Görünüm yardımcıları (yalnızca biçimlendirme; sorgulara dokunmaz)
// ---------------------------------------------------------------------------
$trMonths = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
$methodNames = ['cash' => 'Nakit', 'card' => 'Kart', 'bank_transfer' => 'Havale/EFT', 'other' => 'Diğer'];
$methodIcons = ['cash' => 'bi-cash-stack', 'card' => 'bi-credit-card', 'bank_transfer' => 'bi-bank', 'other' => 'bi-three-dots'];
$intervalNames = ['weekly' => 'Haftalık', 'monthly' => 'Aylık', 'quarterly' => '3 aylık', 'yearly' => 'Yıllık'];
$intervalPer = ['weekly' => 'her hafta', 'monthly' => 'her ay', 'quarterly' => '3 ayda bir', 'yearly' => 'her yıl'];

function money($amount) {
    $amount = (float) $amount;
    return '₺' . number_format($amount, (floor($amount) == $amount) ? 0 : 2, ',', '.');
}

function trDateMid($dateStr, $trMonths) {
    $ts = strtotime((string) $dateStr);
    if (!$ts) return '';
    return date('j', $ts) . ' ' . $trMonths[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

// Ödeme yöntemi radyo kartları (değerler: cash, card, bank_transfer, other)
function methodChoice($prefix, $current, $methodNames, $methodIcons) {
    if (!isset($methodNames[$current])) {
        $current = 'cash';
    }
    $html = '<div class="choice choice-4">';
    $first = true;
    foreach ($methodNames as $value => $label) {
        $id = $prefix . '_' . $value;
        $html .= '<input type="radio" name="payment_method" id="' . htmlspecialchars($id) . '" value="' . $value . '"'
            . ($current === $value ? ' checked' : '') . ($first ? ' required' : '') . '>';
        $html .= '<label for="' . htmlspecialchars($id) . '"><i class="bi ' . $methodIcons[$value] . '" aria-hidden="true"></i>' . $label . '</label>';
        $first = false;
    }
    return $html . '</div>';
}

// Seçili dönemin adı
$isYear = strlen($selected_period) === 4;
$periodTs = strtotime($first_day);
if ($isYear) {
    $periodLabel = $selected_period . ' yılı';
    $periodShort = $selected_period;
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
if (!in_array($selected_period, array_column($periodOptions, 0), true) && preg_match('/^\d{4}(-\d{2})?$/', $selected_period)) {
    array_unshift($periodOptions, [$selected_period, $periodLabel]);
}

// "Diğer" yöntemle ödenenler (toplamdan nakit ve kart çıkınca kalan)
$other_total = round($total_expense - $cash_total - $card_total, 2);

$recurringById = [];
foreach ($recurrings as $r) {
    $recurringById[(int) $r['id']] = $r;
}
$entryCount = $total_rows + count($recurring_expense_rows);

$pageUrl = function ($n) use ($selected_period) {
    return '?sayfa=' . (int) $n . '&period=' . urlencode($selected_period);
};

$pageTitle = 'Kasa';
$pageSubtitle = $periodLabel;
$pageActions = '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal"><i class="bi bi-plus-lg" aria-hidden="true"></i> Gider ekle</button>';

include 'includes/header.php';
?>
<body class="<?php echo $themeClass; ?>" data-page="expenses">
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main id="content" tabindex="-1">
            <?php include 'includes/topbar.php'; ?>

            <div class="page">
                <nav class="seg seg-block" aria-label="Kasa">
                    <a class="seg-btn" href="payments">Ödemeler</a>
                    <a class="seg-btn active" aria-current="page" href="expenses">Giderler</a>
                </nav>

                <!-- Dönem seçimi -->
                <form method="GET" class="kasa-period" id="periodFilterForm">
                    <label for="period" class="form-label">Dönem</label>
                    <select class="form-select" name="period" id="period" onchange="document.getElementById('periodFilterForm').submit();">
                        <?php foreach ($periodOptions as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt[0]); ?>"<?php echo $selected_period === $opt[0] ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt[1]); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit" class="btn btn-secondary btn-sm">Göster</button></noscript>
                </form>

                <!-- Dönem özeti (düzenli giderler dahil) -->
                <div class="ledger" aria-label="<?php echo htmlspecialchars($periodLabel); ?> gider özeti">
                    <div class="ledger-item is-lead">
                        <span class="ledger-label"><?php echo htmlspecialchars($periodShort); ?> toplamı</span>
                        <span class="ledger-value"><?php echo money($total_expense); ?></span>
                    </div>
                    <div class="ledger-item">
                        <span class="ledger-label">Nakit + Havale/EFT</span>
                        <span class="ledger-value"><?php echo money($cash_total); ?></span>
                    </div>
                    <div class="ledger-item">
                        <span class="ledger-label">Kart</span>
                        <span class="ledger-value"><?php echo money($card_total); ?></span>
                    </div>
                    <?php if (abs($other_total) >= 0.01): ?>
                    <div class="ledger-item">
                        <span class="ledger-label">Diğer</span>
                        <span class="ledger-value"><?php echo money($other_total); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Gider kayıtları -->
                <section class="section" aria-labelledby="expensesTitle">
                    <div class="section-head">
                        <h2 class="section-title" id="expensesTitle">Gider kayıtları</h2>
                        <?php if ($entryCount > 0): ?>
                        <span class="section-note"><?php echo $entryCount; ?> kayıt</span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($display_expenses)): ?>
                    <div class="empty">
                        <p class="empty-title"><?php echo htmlspecialchars($periodLabel); ?> için gider yok</p>
                        <p>Üstteki “Gider ekle” ile ilk gideri kaydedin. Kira gibi her dönem tekrarlanan giderleri aşağıdaki “Düzenli gider ekle” ile bir kez tanımlamanız yeterli.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">Gider ekle</button>
                    </div>
                    <?php else: ?>
                    <div class="list">
                        <?php foreach ($display_expenses as $exp):
                            $isRecurring = isset($exp['__type']) && $exp['__type'] === 'recurring';
                            $ts = strtotime($exp['expense_date']);
                            $metaParts = [];
                            if (!empty($exp['category'])) {
                                $metaParts[] = htmlspecialchars($exp['category']);
                            }
                            if ($isRecurring) {
                                $series = $recurringById[$exp['recurring_id']] ?? null;
                                $metaParts[] = 'Düzenli' . ($series && isset($intervalNames[$series['recurrence_interval']]) ? ' · ' . mb_strtolower($intervalNames[$series['recurrence_interval']], 'UTF-8') : '');
                            }
                            $method = $methodNames[$exp['payment_method']] ?? (string) $exp['payment_method'];
                        ?>
                        <div class="row-item">
                            <span class="row-time"><?php echo date('j', $ts); ?><small><?php echo mb_substr($trMonths[(int) date('n', $ts)], 0, 3); ?></small></span>
                            <div class="row-main">
                                <p class="row-title"><span><?php echo htmlspecialchars($exp['title']); ?></span></p>
                                <?php if (!empty($metaParts)): ?>
                                <p class="row-meta"><?php echo implode(' · ', $metaParts); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="row-trail"><span class="row-amount"><?php echo money($exp['amount']); ?><small><?php echo htmlspecialchars($method); ?></small></span></div>
                            <div class="row-actions">
                                <?php if ($isRecurring): ?>
                                <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editRecurringModal<?php echo (int) $exp['recurring_id']; ?>">Düzenle</button>
                                <?php else: ?>
                                <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editExpenseModal<?php echo (int) $exp['id']; ?>">Düzenle</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteExpenseModal<?php echo (int) $exp['id']; ?>">Sil</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

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
                                if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li class="page-item ' . ($i == $sayfa ? 'active' : '') . '"' . ($i == $sayfa ? ' aria-current="page"' : '') . '>';
                                echo '<a class="page-link" href="' . htmlspecialchars($pageUrl($i)) . '">' . $i . '</a>';
                                echo '</li>';
                            }
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
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
                </section>

                <!-- Düzenli giderler -->
                <section class="section" aria-labelledby="recurringTitle">
                    <div class="section-head">
                        <h2 class="section-title" id="recurringTitle">Düzenli giderler</h2>
                        <?php if (!empty($recurrings)): ?>
                        <button type="button" class="btn btn-quiet btn-sm" data-bs-toggle="modal" data-bs-target="#recurringModal">Düzenli gider ekle</button>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($recurrings)): ?>
                    <div class="empty">
                        <p class="empty-title">Düzenli gider yok</p>
                        <p>Kira, aidat, abonelik gibi tekrarlanan giderleri bir kez tanımlayın; her dönemin listesine ve toplamına kendiliğinden eklenir.</p>
                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#recurringModal">Düzenli gider ekle</button>
                    </div>
                    <?php else: ?>
                    <div class="list">
                        <?php foreach ($recurrings as $r):
                            $rid = (int) $r['id'];
                            $metaParts = [];
                            $metaParts[] = $r['active']
                                ? '<span class="mark mark-confirmed">Etkin</span>'
                                : '<span class="mark mark-pending">Durduruldu</span>';
                            $metaParts[] = htmlspecialchars($methodNames[$r['payment_method']] ?? (string) $r['payment_method']);
                            if (!empty($r['category'])) {
                                $metaParts[] = htmlspecialchars($r['category']);
                            }
                            if (!empty($r['end_date'])) {
                                $metaParts[] = 'Bitiş: ' . htmlspecialchars(trDateMid($r['end_date'], $trMonths));
                            }
                        ?>
                        <div class="row-item no-lead">
                            <div class="row-main">
                                <p class="row-title"><span><?php echo htmlspecialchars($r['title']); ?></span></p>
                                <p class="row-meta"><?php echo implode(' · ', $metaParts); ?></p>
                            </div>
                            <div class="row-trail"><span class="row-amount"><?php echo money($r['amount']); ?><small><?php echo htmlspecialchars($intervalPer[$r['recurrence_interval']] ?? (string) $r['recurrence_interval']); ?></small></span></div>
                            <div class="row-actions">
                                <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editRecurringModal<?php echo $rid; ?>">Düzenle</button>
                                <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#toggleRecurringModal<?php echo $rid; ?>"><?php echo $r['active'] ? 'Durdur' : 'Yeniden başlat'; ?></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteRecurringModal<?php echo $rid; ?>">Sil</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <?php foreach ($expenses as $exp):
        $eid = (int) $exp['id'];
        $ets = strtotime($exp['expense_date']);
    ?>
    <!-- Gideri düzenle -->
    <div class="modal fade" id="editExpenseModal<?php echo $eid; ?>" tabindex="-1" aria-labelledby="editExpenseTitle<?php echo $eid; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="editExpenseTitle<?php echo $eid; ?>">Gideri düzenle</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <form action="process/edit-expense" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="id" value="<?php echo $eid; ?>">
                        <div class="field">
                            <label for="expTitle<?php echo $eid; ?>" class="form-label">Başlık</label>
                            <input type="text" class="form-control" id="expTitle<?php echo $eid; ?>" name="title" value="<?php echo htmlspecialchars($exp['title']); ?>" required>
                            <div class="invalid-feedback">Başlık girin.</div>
                        </div>
                        <div class="field">
                            <label for="expCategory<?php echo $eid; ?>" class="form-label">Kategori <span class="ink-3">(isteğe bağlı)</span></label>
                            <input type="text" class="form-control" id="expCategory<?php echo $eid; ?>" name="category" value="<?php echo htmlspecialchars($exp['category'] ?? ''); ?>">
                        </div>
                        <div class="field-row field">
                            <div>
                                <label for="expAmount<?php echo $eid; ?>" class="form-label">Tutar (₺)</label>
                                <input type="number" inputmode="decimal" class="form-control tnum" id="expAmount<?php echo $eid; ?>" name="amount" step="0.01" min="0" value="<?php echo htmlspecialchars((string) $exp['amount']); ?>" required>
                                <div class="invalid-feedback">Tutarı girin.</div>
                            </div>
                            <div>
                                <label for="expDate<?php echo $eid; ?>" class="form-label">Tarih</label>
                                <input type="date" class="form-control" id="expDate<?php echo $eid; ?>" name="expense_date" value="<?php echo htmlspecialchars((string) $exp['expense_date']); ?>" required>
                                <div class="invalid-feedback">Tarih seçin.</div>
                            </div>
                        </div>
                        <fieldset class="field">
                            <legend class="form-label">Ödeme yöntemi</legend>
                            <?php echo methodChoice('expMethod' . $eid, $exp['payment_method'], $methodNames, $methodIcons); ?>
                        </fieldset>
                        <div class="field">
                            <label for="expNotes<?php echo $eid; ?>" class="form-label">Not <span class="ink-3">(isteğe bağlı)</span></label>
                            <textarea class="form-control" id="expNotes<?php echo $eid; ?>" name="notes" rows="2"><?php echo htmlspecialchars($exp['notes'] ?? ''); ?></textarea>
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

    <!-- Gideri sil -->
    <div class="modal fade" id="deleteExpenseModal<?php echo $eid; ?>" tabindex="-1" aria-labelledby="deleteExpenseTitle<?php echo $eid; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="deleteExpenseTitle<?php echo $eid; ?>">Gideri sil</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="sheet-summary">
                        <span class="row-time"><?php echo date('j', $ets); ?><small><?php echo mb_substr($trMonths[(int) date('n', $ets)], 0, 3); ?></small></span>
                        <div>
                            <p class="row-title"><span><?php echo htmlspecialchars($exp['title']); ?></span></p>
                            <p class="row-meta"><?php echo money($exp['amount']); ?> · <?php echo htmlspecialchars($methodNames[$exp['payment_method']] ?? (string) $exp['payment_method']); ?></p>
                        </div>
                    </div>
                    <p>Bu gider kaydı kalıcı olarak silinir ve dönem toplamından düşülür. Bu işlem geri alınamaz.</p>
                    <form action="process/delete-expense" method="POST">
                        <input type="hidden" name="id" value="<?php echo $eid; ?>">
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-danger">Gideri sil</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Gider ekle -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="addExpenseTitle">Gider ekle</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <form action="process/add-expense" method="POST" class="needs-validation" novalidate>
                        <div class="field">
                            <label for="addExpTitle" class="form-label">Başlık</label>
                            <input type="text" class="form-control" id="addExpTitle" name="title" required>
                            <div class="invalid-feedback">Başlık girin.</div>
                        </div>
                        <div class="field">
                            <label for="addExpCategory" class="form-label">Kategori <span class="ink-3">(isteğe bağlı)</span></label>
                            <input type="text" class="form-control" id="addExpCategory" name="category">
                        </div>
                        <div class="field-row field">
                            <div>
                                <label for="addExpAmount" class="form-label">Tutar (₺)</label>
                                <input type="number" inputmode="decimal" class="form-control tnum" id="addExpAmount" name="amount" step="0.01" min="0" required>
                                <div class="invalid-feedback">Tutarı girin.</div>
                            </div>
                            <div>
                                <label for="addExpDate" class="form-label">Tarih</label>
                                <input type="date" class="form-control" id="addExpDate" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">Tarih seçin.</div>
                            </div>
                        </div>
                        <fieldset class="field">
                            <legend class="form-label">Ödeme yöntemi</legend>
                            <?php echo methodChoice('addExpMethod', 'cash', $methodNames, $methodIcons); ?>
                        </fieldset>
                        <div class="field">
                            <label for="addExpNotes" class="form-label">Not <span class="ink-3">(isteğe bağlı)</span></label>
                            <textarea class="form-control" id="addExpNotes" name="notes" rows="2"></textarea>
                        </div>
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-primary">Gideri kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Düzenli gider ekle -->
    <div class="modal fade" id="recurringModal" tabindex="-1" aria-labelledby="recurringModalTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="recurringModalTitle">Düzenli gider ekle</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <form action="process/add-recurring-expense" method="POST" class="needs-validation" novalidate>
                        <div class="field">
                            <label for="addRecTitle" class="form-label">Başlık</label>
                            <input type="text" class="form-control" id="addRecTitle" name="title" required>
                            <div class="invalid-feedback">Başlık girin.</div>
                        </div>
                        <div class="field">
                            <label for="addRecCategory" class="form-label">Kategori <span class="ink-3">(isteğe bağlı)</span></label>
                            <input type="text" class="form-control" id="addRecCategory" name="category">
                        </div>
                        <div class="field-row field">
                            <div>
                                <label for="addRecAmount" class="form-label">Tutar (₺)</label>
                                <input type="number" inputmode="decimal" class="form-control tnum" id="addRecAmount" name="amount" step="0.01" min="0" required>
                                <div class="invalid-feedback">Tutarı girin.</div>
                            </div>
                            <div>
                                <label for="addRecInterval" class="form-label">Tekrar</label>
                                <select class="form-select" id="addRecInterval" name="recurrence_interval" required>
                                    <option value="weekly">Haftalık</option>
                                    <option value="monthly" selected>Aylık</option>
                                    <option value="quarterly">3 aylık</option>
                                    <option value="yearly">Yıllık</option>
                                </select>
                            </div>
                        </div>
                        <fieldset class="field">
                            <legend class="form-label">Ödeme yöntemi</legend>
                            <?php echo methodChoice('addRecMethod', 'cash', $methodNames, $methodIcons); ?>
                        </fieldset>
                        <div class="field-row field">
                            <div>
                                <label for="addRecStart" class="form-label">Başlangıç</label>
                                <input type="date" class="form-control" id="addRecStart" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">Başlangıç tarihi seçin.</div>
                            </div>
                            <div>
                                <label for="addRecEnd" class="form-label">Bitiş <span class="ink-3">(isteğe bağlı)</span></label>
                                <input type="date" class="form-control" id="addRecEnd" name="end_date" aria-describedby="addRecEndHelp">
                                <div class="form-text" id="addRecEndHelp">Boş kalırsa süresiz tekrarlanır.</div>
                            </div>
                        </div>
                        <div class="field">
                            <label for="addRecNotes" class="form-label">Not <span class="ink-3">(isteğe bağlı)</span></label>
                            <textarea class="form-control" id="addRecNotes" name="notes" rows="2"></textarea>
                        </div>
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-primary">Düzenli gideri kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($recurrings)): foreach ($recurrings as $r):
        $rid = (int) $r['id'];
        $rMethod = $methodNames[$r['payment_method']] ?? (string) $r['payment_method'];
        $rInterval = $intervalNames[$r['recurrence_interval']] ?? (string) $r['recurrence_interval'];
    ?>
    <!-- Düzenli gideri düzenle -->
    <div class="modal fade" id="editRecurringModal<?php echo $rid; ?>" tabindex="-1" aria-labelledby="editRecurringTitle<?php echo $rid; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="editRecurringTitle<?php echo $rid; ?>">Düzenli gideri düzenle</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <p class="form-text mt-0">Değişiklik bu giderin geçmiş dönemlerdeki tekrarlarına da uygulanır. Yalnızca bundan sonrasını değiştirmek için bitiş tarihi girip yeni bir düzenli gider ekleyin.</p>
                    <form action="process/edit-recurring-expense" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="id" value="<?php echo $rid; ?>">
                        <div class="field">
                            <label for="recTitle<?php echo $rid; ?>" class="form-label">Başlık</label>
                            <input type="text" class="form-control" id="recTitle<?php echo $rid; ?>" name="title" value="<?php echo htmlspecialchars($r['title']); ?>" required>
                            <div class="invalid-feedback">Başlık girin.</div>
                        </div>
                        <div class="field">
                            <label for="recCategory<?php echo $rid; ?>" class="form-label">Kategori <span class="ink-3">(isteğe bağlı)</span></label>
                            <input type="text" class="form-control" id="recCategory<?php echo $rid; ?>" name="category" value="<?php echo htmlspecialchars($r['category'] ?? ''); ?>">
                        </div>
                        <div class="field-row field">
                            <div>
                                <label for="recAmount<?php echo $rid; ?>" class="form-label">Tutar (₺)</label>
                                <input type="number" inputmode="decimal" class="form-control tnum" id="recAmount<?php echo $rid; ?>" name="amount" step="0.01" min="0" value="<?php echo htmlspecialchars((string) $r['amount']); ?>" required>
                                <div class="invalid-feedback">Tutarı girin.</div>
                            </div>
                            <div>
                                <label for="recInterval<?php echo $rid; ?>" class="form-label">Tekrar</label>
                                <select class="form-select" id="recInterval<?php echo $rid; ?>" name="recurrence_interval" required>
                                    <option value="weekly" <?php echo $r['recurrence_interval']=='weekly'?'selected':''; ?>>Haftalık</option>
                                    <option value="monthly" <?php echo $r['recurrence_interval']=='monthly'?'selected':''; ?>>Aylık</option>
                                    <option value="quarterly" <?php echo $r['recurrence_interval']=='quarterly'?'selected':''; ?>>3 aylık</option>
                                    <option value="yearly" <?php echo $r['recurrence_interval']=='yearly'?'selected':''; ?>>Yıllık</option>
                                </select>
                            </div>
                        </div>
                        <fieldset class="field">
                            <legend class="form-label">Ödeme yöntemi</legend>
                            <?php echo methodChoice('recMethod' . $rid, $r['payment_method'], $methodNames, $methodIcons); ?>
                        </fieldset>
                        <div class="field-row field">
                            <div>
                                <label for="recStart<?php echo $rid; ?>" class="form-label">Başlangıç</label>
                                <input type="date" class="form-control" id="recStart<?php echo $rid; ?>" name="start_date" value="<?php echo htmlspecialchars((string) $r['start_date']); ?>" required>
                                <div class="invalid-feedback">Başlangıç tarihi seçin.</div>
                            </div>
                            <div>
                                <label for="recEnd<?php echo $rid; ?>" class="form-label">Bitiş <span class="ink-3">(isteğe bağlı)</span></label>
                                <input type="date" class="form-control" id="recEnd<?php echo $rid; ?>" name="end_date" value="<?php echo htmlspecialchars((string) ($r['end_date'] ?? '')); ?>">
                            </div>
                        </div>
                        <div class="field">
                            <label for="recNotes<?php echo $rid; ?>" class="form-label">Not <span class="ink-3">(isteğe bağlı)</span></label>
                            <textarea class="form-control" id="recNotes<?php echo $rid; ?>" name="notes" rows="2"><?php echo htmlspecialchars($r['notes'] ?? ''); ?></textarea>
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

    <!-- Düzenli gideri durdur / yeniden başlat -->
    <div class="modal fade" id="toggleRecurringModal<?php echo $rid; ?>" tabindex="-1" aria-labelledby="toggleRecurringTitle<?php echo $rid; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="toggleRecurringTitle<?php echo $rid; ?>"><?php echo $r['active'] ? 'Düzenli gideri durdur' : 'Düzenli gideri yeniden başlat'; ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="sheet-summary no-lead">
                        <div>
                            <p class="row-title"><span><?php echo htmlspecialchars($r['title']); ?></span></p>
                            <p class="row-meta"><?php echo htmlspecialchars($rInterval . ' · ' . $rMethod); ?></p>
                        </div>
                        <span class="row-amount"><?php echo money($r['amount']); ?></span>
                    </div>
                    <?php if ($r['active']): ?>
                    <p>Durdurulan gider bu sayfadan ve geçmiş dönemler dahil tüm toplamlardan çıkar. Geçmiş ayları koruyup yalnızca bundan sonrasını durdurmak için “Düzenle”den bitiş tarihi girin.</p>
                    <?php else: ?>
                    <p>Gider yeniden her dönemin listesine ve toplamına eklenir.</p>
                    <?php endif; ?>
                    <form action="process/toggle-recurring-expense" method="POST">
                        <input type="hidden" name="id" value="<?php echo $rid; ?>">
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-primary"><?php echo $r['active'] ? 'Gideri durdur' : 'Yeniden başlat'; ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Düzenli gideri sil -->
    <div class="modal fade" id="deleteRecurringModal<?php echo $rid; ?>" tabindex="-1" aria-labelledby="deleteRecurringTitle<?php echo $rid; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="deleteRecurringTitle<?php echo $rid; ?>">Düzenli gideri sil</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="sheet-summary no-lead">
                        <div>
                            <p class="row-title"><span><?php echo htmlspecialchars($r['title']); ?></span></p>
                            <p class="row-meta"><?php echo htmlspecialchars($rInterval . ' · ' . $rMethod); ?></p>
                        </div>
                        <span class="row-amount"><?php echo money($r['amount']); ?></span>
                    </div>
                    <p>Bu düzenli gider, geçmiş dönemlerdeki tekrarlarıyla birlikte kalıcı olarak silinir. Geçmiş ayları korumak için bunun yerine “Düzenle”den bitiş tarihi girin. Bu işlem geri alınamaz.</p>
                    <form action="process/delete-recurring-expense" method="POST">
                        <input type="hidden" name="id" value="<?php echo $rid; ?>">
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-danger">Düzenli gideri sil</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>

<?php include 'includes/footer.php'; ?>
