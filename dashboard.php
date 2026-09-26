<?php
session_start();
require_once 'config/database.php';
require_once 'includes/settings.php';
require_once 'includes/phone.php';

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

// Seans süresi ve varsayılan ücret: yönetim panelindeki Seans Ayarları
$sessionMinutes = getSessionMinutes($db);
$defaultAmount = getSessionFee($db);

$trDays = ['Monday' => 'Pazartesi', 'Tuesday' => 'Salı', 'Wednesday' => 'Çarşamba', 'Thursday' => 'Perşembe', 'Friday' => 'Cuma', 'Saturday' => 'Cumartesi', 'Sunday' => 'Pazar'];
$trMonths = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
$methodNames = ['cash' => 'Nakit', 'card' => 'Kart', 'bank_transfer' => 'Havale/EFT'];

$nowTs = time();
$todayStr = date('Y-m-d');
$tomorrowStr = date('Y-m-d', strtotime('+1 day'));

function trDateLong($ts, $trDays, $trMonths) {
    return date('j', $ts) . ' ' . $trMonths[(int) date('n', $ts)] . ' ' . $trDays[date('l', $ts)];
}

function dayLabel($dateStr, $todayStr, $tomorrowStr, $trDays, $trMonths) {
    $ts = strtotime($dateStr);
    if ($dateStr === $todayStr) return 'Bugün';
    if ($dateStr === $tomorrowStr) return 'Yarın, ' . $trDays[date('l', $ts)];
    return $trDays[date('l', $ts)] . ', ' . date('j', $ts) . ' ' . $trMonths[(int) date('n', $ts)];
}

function telHref($phone) {
    return 'tel:' . preg_replace('/[^0-9+]/', '', (string) $phone);
}

function money($amount) {
    return '₺' . number_format((float) $amount, (floor($amount) == $amount) ? 0 : 2, ',', '.');
}

$userName = '';
$todayList = [];
$upcoming = [];
$unpaid = [];
$unpaidCount = 0;
$nextFuture = null;
try {
    $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userName = (string) ($stmt->fetchColumn() ?: '');

    // Bugünün tüm randevuları
    $stmt = $db->prepare("
        SELECT a.id, a.client_id, a.appointment_date, a.appointment_time, a.status, a.notes,
               c.name AS client_name, c.phone AS client_phone,
               p.id AS payment_id, p.amount AS paid_amount, p.payment_method
        FROM appointments a
        JOIN clients c ON a.client_id = c.id
        LEFT JOIN payments p ON p.appointment_id = a.id
        WHERE a.appointment_date = CURDATE()
        ORDER BY a.appointment_time ASC
    ");
    $stmt->execute();
    $todayList = $stmt->fetchAll();

    // Önümüzdeki 7 gün (yarından itibaren)
    $stmt = $db->prepare("
        SELECT a.id, a.client_id, a.appointment_date, a.appointment_time, a.status,
               c.name AS client_name, c.phone AS client_phone
        FROM appointments a
        JOIN clients c ON a.client_id = c.id
        WHERE a.appointment_date > CURDATE()
          AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          AND (a.status IS NULL OR a.status <> 'iptal')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 14
    ");
    $stmt->execute();
    $upcoming = $stmt->fetchAll();

    // Bugünden sonraki ilk seans (bugün başka seans yoksa gösterilir)
    $stmt = $db->prepare("
        SELECT a.id, a.client_id, a.appointment_date, a.appointment_time, c.name AS client_name
        FROM appointments a
        JOIN clients c ON a.client_id = c.id
        WHERE a.appointment_date > CURDATE() AND (a.status IS NULL OR a.status <> 'iptal')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 1
    ");
    $stmt->execute();
    $nextFuture = $stmt->fetch() ?: null;

    // Ödeme bekleyen geçmiş seanslar (iptaller hariç)
    $unpaidWhere = "
        FROM appointments a
        JOIN clients c ON a.client_id = c.id
        LEFT JOIN payments p ON a.id = p.appointment_id
        WHERE (a.appointment_date < CURDATE() OR (a.appointment_date = CURDATE() AND a.appointment_time < CURTIME()))
          AND p.id IS NULL
          AND (a.status IS NULL OR a.status <> 'iptal')
    ";
    $unpaidCount = (int) $db->query("SELECT COUNT(*) " . $unpaidWhere)->fetchColumn();
    $stmt = $db->query("
        SELECT a.id, a.client_id, a.appointment_date, a.appointment_time, c.name AS client_name
        " . $unpaidWhere . "
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 5
    ");
    $unpaid = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "Veritabanı hatası: " . $e->getMessage();
}

// Şimdi / sıradaki seans
$current = null;
$next = null;
$doneCount = 0;
$activeCount = 0;
$prevEndBeforeFocus = null;
foreach ($todayList as $apt) {
    if ($apt['status'] === 'iptal') {
        continue;
    }
    $activeCount++;
    $start = strtotime($apt['appointment_date'] . ' ' . $apt['appointment_time']);
    $end = $start + $sessionMinutes * 60;
    if ($end <= $nowTs) {
        $doneCount++;
        $prevEndBeforeFocus = $end;
    } elseif ($start <= $nowTs && $nowTs < $end) {
        $current = $apt;
    } elseif ($start > $nowTs && $next === null) {
        $next = $apt;
    }
}
$remainingCount = $activeCount - $doneCount - ($current ? 1 : 0);

$nowMode = $current ? 'current' : ($next ? 'next' : 'none');
$focus = $current ?: $next;
$focusStart = $focus ? strtotime($focus['appointment_date'] . ' ' . $focus['appointment_time']) : null;

// Pirinç halkanın bekleme penceresi: önceki seansın bitişi (yoksa en fazla 4 saat öncesi) → sıradaki seans
$windowStart = null;
if ($nowMode === 'next') {
    $windowStart = max($prevEndBeforeFocus ?: 0, $focusStart - 4 * 3600, strtotime('today 07:00'));
    $windowStart = min($windowStart, $nowTs - 60);
}

// Bekleme etiketi (sunucu tarafı ilk değer; app.js canlı günceller)
$ringValue = '';
$ringUnit = '';
$statusText = '';
if ($nowMode === 'current') {
    $left = max(0, (int) ceil(($focusStart + $sessionMinutes * 60 - $nowTs) / 60));
    $ringValue = (string) $left;
    $ringUnit = 'dk kaldı';
    $statusText = 'Seans sürüyor · <strong>' . $left . ' dakika kaldı</strong>';
} elseif ($nowMode === 'next') {
    $mins = max(1, (int) ceil(($focusStart - $nowTs) / 60));
    if ($mins < 60) {
        $ringValue = (string) $mins;
        $ringUnit = 'dk sonra';
        $statusText = '<strong>' . $mins . ' dakika sonra başlıyor</strong>';
    } else {
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        $ringValue = $m === 0 ? $h . ' sa' : $h . ':' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
        $ringUnit = 'sonra';
        $statusText = '<strong>' . ($m === 0 ? "$h saat" : "$h saat $m dakika") . ' sonra başlıyor</strong>';
    }
}

// Şu anki seans ödenmiş mi (Ödeme al düğmesi için)
$focusPaid = $focus && !empty($focus['payment_id']);

// Saat ekseni: seanslar arası boşluklar süreleriyle orantılı yer kaplar, "şimdi" çizgisi dakikasına oturur
function gapLabel($minutes) {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h === 0) return $m . ' dk boş';
    return $h . ' sa' . ($m ? ' ' . $m . ' dk' : '') . ' boş';
}

$dayItems = [];
$nowPlaced = false;
$prevEnd = null;
foreach ($todayList as $apt) {
    $start = strtotime($apt['appointment_date'] . ' ' . $apt['appointment_time']);
    $end = $start + $sessionMinutes * 60;

    if ($prevEnd !== null && $start - $prevEnd >= 15 * 60) {
        $gapMinutes = (int) round(($start - $prevEnd) / 60);
        $nowInGap = !$nowPlaced && $nowTs >= $prevEnd && $nowTs < $start;
        $dayItems[] = [
            'type' => 'gap',
            'from' => $prevEnd,
            'to' => $start,
            'minutes' => $gapMinutes,
            'height' => max(28, min(120, (int) round($gapMinutes * 0.5))),
            'now' => $nowInGap,
            'pct' => $nowInGap ? round(($nowTs - $prevEnd) / ($start - $prevEnd) * 100, 1) : null,
        ];
        if ($nowInGap) {
            $nowPlaced = true;
        }
    } elseif (!$nowPlaced && $nowTs < $start && ($prevEnd === null || $nowTs >= $prevEnd - 60)) {
        $dayItems[] = ['type' => 'now'];
        $nowPlaced = true;
    }

    if (!$nowPlaced && $nowTs >= $start && $nowTs < $end && $apt['status'] !== 'iptal') {
        $nowPlaced = true; // Şu an seansta: satırın kendisi vurgulanır
    }

    $dayItems[] = ['type' => 'row', 'apt' => $apt, 'start' => $start, 'end' => $end];
    $prevEnd = max($prevEnd ?? 0, $end);
}
if (!$nowPlaced && !empty($todayList)) {
    $dayItems[] = ['type' => 'now'];
}

// Yaklaşanları güne göre grupla
$upcomingByDay = [];
foreach ($upcoming as $apt) {
    $upcomingByDay[$apt['appointment_date']][] = $apt;
}

$pageTitle = 'Bugün';
$pageSubtitle = trDateLong($nowTs, $trDays, $trMonths);
$appbarClass = 'appbar--wool';
$themeColor = '#4B1D35';

// Header'ı dahil et
include 'includes/header.php';
?>
<body class="<?php echo $themeClass; ?>" data-page="dashboard">
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main id="content" tabindex="-1">
            <?php include 'includes/topbar.php'; ?>

            <div class="page dash">
                <!-- Şimdi / sıradaki: ekranın baskın alanı -->
                <div class="dash-now page-top">
                    <section class="now wool" data-now data-now-mode="<?php echo $nowMode; ?>"
                             <?php if ($focusStart): ?>data-start="<?php echo date('Y-m-d\TH:i:s', $focusStart); ?>"<?php endif; ?>
                             <?php if ($windowStart): ?>data-window-start="<?php echo date('Y-m-d\TH:i:s', $windowStart); ?>"<?php endif; ?>
                             data-length="<?php echo $sessionMinutes; ?>"
                             aria-label="<?php echo $nowMode === 'current' ? 'Şu anki seans' : 'Sıradaki seans'; ?>">
                        <?php if ($focus): ?>
                        <div class="now-grid">
                            <div>
                                <time class="now-time" datetime="<?php echo date('Y-m-d\TH:i', $focusStart); ?>"><?php echo date('H:i', $focusStart); ?></time>
                                <span class="now-name"><?php echo htmlspecialchars($focus['client_name']); ?></span>
                                <p class="now-status" data-now-status aria-live="polite"><?php echo $statusText; ?></p>
                            </div>
                            <div class="ring" aria-hidden="true">
                                <svg viewBox="0 0 120 120">
                                    <circle class="ring-track" cx="60" cy="60" r="52"></circle>
                                    <circle class="ring-fill" cx="60" cy="60" r="52"></circle>
                                </svg>
                                <div class="ring-label">
                                    <span class="ring-value" data-ring-value><?php echo htmlspecialchars($ringValue); ?></span>
                                    <span class="ring-unit" data-ring-unit><?php echo htmlspecialchars($ringUnit); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="now-actions">
                            <?php if ($nowMode === 'current' && !$focusPaid): ?>
                            <button type="button" class="btn btn-on-wool-solid" data-bs-toggle="modal" data-bs-target="#quickPaymentModal"
                                    data-pay="<?php echo (int) $focus['id']; ?>"
                                    data-pay-name="<?php echo htmlspecialchars($focus['client_name']); ?>"
                                    data-pay-time="<?php echo date('H:i', $focusStart); ?>"
                                    data-pay-date="Bugün"
                                    data-pay-amount="<?php echo htmlspecialchars(feeInputValue($defaultAmount)); ?>">
                                <i class="bi bi-wallet2" aria-hidden="true"></i> Ödeme al
                            </button>
                            <?php elseif ($nowMode === 'next' && !empty($focus['client_phone'])): ?>
                            <a href="<?php echo htmlspecialchars(telHref($focus['client_phone'])); ?>" class="btn btn-on-wool">
                                <i class="bi bi-telephone" aria-hidden="true"></i> Ara
                            </a>
                            <?php endif; ?>
                            <a href="client-details?id=<?php echo (int) $focus['client_id']; ?>" class="btn btn-on-wool">
                                <i class="bi bi-person" aria-hidden="true"></i> Danışan kartı
                            </a>
                        </div>
                        <?php else: ?>
                        <p class="now-empty-title"><?php echo $activeCount > 0 ? 'Bugünün seansları bitti' : 'Bugün seans yok'; ?></p>
                        <?php if ($nextFuture): $nfTs = strtotime($nextFuture['appointment_date'] . ' ' . $nextFuture['appointment_time']); ?>
                        <p class="now-status">
                            Sıradaki: <strong><?php echo htmlspecialchars(dayLabel($nextFuture['appointment_date'], $todayStr, $tomorrowStr, $trDays, $trMonths)); ?>, <?php echo date('H:i', $nfTs); ?></strong>
                            · <?php echo htmlspecialchars($nextFuture['client_name']); ?>
                        </p>
                        <?php else: ?>
                        <p class="now-status">Takvimde yaklaşan seans görünmüyor.</p>
                        <?php endif; ?>
                        <div class="now-actions">
                            <a href="appointments?view=calendar" class="btn btn-on-wool">
                                <i class="bi bi-calendar3" aria-hidden="true"></i> Takvimi aç
                            </a>
                        </div>
                        <?php endif; ?>

                        <div class="now-summary">
                            <span><b><?php echo $activeCount; ?></b> seans bugün</span>
                            <span><b><?php echo $doneCount; ?></b> tamamlandı</span>
                            <span><b><?php echo max(0, $remainingCount); ?></b> kaldı</span>
                        </div>
                    </section>
                </div>

                <?php if ($unpaidCount > 0): ?>
                <!-- Açık para kalmaz -->
                <details class="due-box dash-due" data-open-desktop>
                    <summary class="due">
                        <span class="due-count"><?php echo $unpaidCount; ?></span>
                        <div>
                            <p class="due-title"><?php echo $unpaidCount; ?> seans ödeme bekliyor</p>
                            <p class="due-meta">
                                <?php
                                $names = array_map(function ($a) { return explode(' ', trim($a['client_name']))[0]; }, array_slice($unpaid, 0, 3));
                                echo htmlspecialchars(implode(', ', $names)) . ($unpaidCount > 3 ? ' ve ' . ($unpaidCount - 3) . ' kişi daha' : '');
                                ?>
                            </p>
                        </div>
                        <i class="bi bi-chevron-right row-chevron" aria-hidden="true"></i>
                    </summary>
                    <div class="list">
                        <?php foreach ($unpaid as $apt):
                            $ts = strtotime($apt['appointment_date'] . ' ' . $apt['appointment_time']);
                        ?>
                        <div class="row-item">
                            <span class="row-time"><?php echo date('H:i', $ts); ?><small><?php echo date('j', $ts) . ' ' . mb_substr($trMonths[(int) date('n', $ts)], 0, 3); ?></small></span>
                            <a class="row-main text-decoration-none" href="client-details?id=<?php echo (int) $apt['client_id']; ?>">
                                <p class="row-title"><span><?php echo htmlspecialchars($apt['client_name']); ?></span></p>
                                <p class="row-meta"><span class="mark mark-unpaid">Ödenmedi</span></p>
                            </a>
                            <div class="row-trail">
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#quickPaymentModal"
                                        data-pay="<?php echo (int) $apt['id']; ?>"
                                        data-pay-name="<?php echo htmlspecialchars($apt['client_name']); ?>"
                                        data-pay-time="<?php echo date('H:i', $ts); ?>"
                                        data-pay-date="<?php echo htmlspecialchars(trDateLong($ts, $trDays, $trMonths)); ?>"
                                        data-pay-amount="<?php echo htmlspecialchars(feeInputValue($defaultAmount)); ?>">
                                    Ödeme al
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($unpaidCount > count($unpaid)): ?>
                    <p class="due-foot"><a href="payments">Kalan <?php echo $unpaidCount - count($unpaid); ?> seans Kasa’da <i class="bi bi-arrow-right" aria-hidden="true"></i></a></p>
                    <?php endif; ?>
                </details>
                <?php endif; ?>

                <!-- Günün programı: saat ekseni -->
                <section class="section dash-today" aria-labelledby="todayTitle">
                    <div class="section-head">
                        <h2 class="section-title" id="todayTitle">Günün programı</h2>
                        <a href="appointments" class="btn btn-quiet btn-sm">Tüm randevular</a>
                    </div>

                    <?php if (empty($todayList)): ?>
                    <div class="empty">
                        <p class="empty-title">Bugün için randevu yok</p>
                        <p>Yeni bir seans eklemek için alttaki “Randevu” düğmesine dokunun; danışana randevu bilgisi SMS’le otomatik gider.</p>
                    </div>
                    <?php else: ?>
                    <div class="list day-rail">
                        <?php foreach ($dayItems as $item): ?>
                            <?php if ($item['type'] === 'now'): ?>
                        <div class="now-line" role="presentation"><span>Şimdi <span data-now-clock><?php echo date('H:i', $nowTs); ?></span></span></div>
                            <?php elseif ($item['type'] === 'gap'): ?>
                        <div class="day-gap" style="height: <?php echo (int) $item['height']; ?>px"
                             data-gap-from="<?php echo date('Y-m-d\TH:i:s', $item['from']); ?>"
                             data-gap-to="<?php echo date('Y-m-d\TH:i:s', $item['to']); ?>">
                            <span class="day-gap-label"><?php echo htmlspecialchars(gapLabel($item['minutes'])); ?></span>
                            <?php if ($item['now']): ?>
                            <div class="now-line" role="presentation" style="top: <?php echo $item['pct']; ?>%"><span>Şimdi <span data-now-clock><?php echo date('H:i', $nowTs); ?></span></span></div>
                            <?php endif; ?>
                        </div>
                            <?php else:
                                $apt = $item['apt'];
                                $start = $item['start'];
                                $end = $item['end'];
                                $isCancelled = $apt['status'] === 'iptal';
                                $isCurrent = !$isCancelled && $start <= $nowTs && $nowTs < $end;
                                $isPast = !$isCurrent && $start < $nowTs;
                                $isPaid = !empty($apt['payment_id']);
                            ?>
                        <div class="row-item<?php echo $isPast ? ' is-past' : ''; ?><?php echo $isCurrent ? ' is-current' : ''; ?><?php echo $isCancelled ? ' is-cancelled' : ''; ?>">
                            <span class="row-time"><?php echo date('H:i', $start); ?></span>
                            <a class="row-main text-decoration-none" href="client-details?id=<?php echo (int) $apt['client_id']; ?>">
                                <p class="row-title"><span><?php echo htmlspecialchars($apt['client_name']); ?></span></p>
                                <p class="row-meta">
                                    <?php if ($isCancelled): ?>
                                        <span class="mark mark-cancelled">İptal edildi</span>
                                    <?php elseif ($isCurrent): ?>
                                        <span class="mark mark-confirmed">Seansta · <?php echo date('H:i', $end); ?>’e kadar</span>
                                    <?php elseif ($isPaid): ?>
                                        <span class="mark mark-paid">Ödendi · <?php echo $methodNames[$apt['payment_method']] ?? ''; ?> <?php echo money($apt['paid_amount']); ?></span>
                                    <?php elseif ($isPast): ?>
                                        <span class="mark mark-unpaid">Ödenmedi</span>
                                    <?php else: ?>
                                        <span class="tnum"><?php echo htmlspecialchars(formatPhoneDisplay($apt['client_phone'])); ?></span>
                                    <?php endif; ?>
                                </p>
                            </a>
                            <div class="row-trail">
                                <?php if (($isPast || $isCurrent) && !$isPaid && !$isCancelled): ?>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#quickPaymentModal"
                                        data-pay="<?php echo (int) $apt['id']; ?>"
                                        data-pay-name="<?php echo htmlspecialchars($apt['client_name']); ?>"
                                        data-pay-time="<?php echo date('H:i', $start); ?>"
                                        data-pay-date="Bugün"
                                        data-pay-amount="<?php echo htmlspecialchars(feeInputValue($defaultAmount)); ?>">
                                    Ödeme al
                                </button>
                                <?php elseif (!$isPast && !$isCancelled && !empty($apt['client_phone'])): ?>
                                <a href="<?php echo htmlspecialchars(telHref($apt['client_phone'])); ?>" class="btn btn-sm btn-secondary" aria-label="<?php echo htmlspecialchars($apt['client_name']); ?> adlı danışanı ara">
                                    <i class="bi bi-telephone" aria-hidden="true"></i> Ara
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>

                <!-- Önümüzdeki günler -->
                <section class="section dash-upcoming" aria-labelledby="upcomingTitle">
                    <div class="section-head">
                        <h2 class="section-title" id="upcomingTitle">Önümüzdeki günler</h2>
                        <a href="appointments?view=calendar" class="btn btn-quiet btn-sm">Takvim</a>
                    </div>
                    <?php if (empty($upcomingByDay)): ?>
                    <div class="empty">
                        <p class="empty-title">Önümüzdeki 7 gün boş</p>
                        <p>Yeni seanslar eklendikçe burada gün gün listelenir.</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($upcomingByDay as $date => $items): ?>
                        <div class="list-day"><?php echo htmlspecialchars(dayLabel($date, $todayStr, $tomorrowStr, $trDays, $trMonths)); ?><span><?php echo count($items); ?> seans</span></div>
                        <div class="list">
                            <?php foreach ($items as $apt): ?>
                            <a class="row-item" href="client-details?id=<?php echo (int) $apt['client_id']; ?>">
                                <span class="row-time"><?php echo date('H:i', strtotime($apt['appointment_time'])); ?></span>
                                <span class="row-main">
                                    <span class="row-title"><span><?php echo htmlspecialchars($apt['client_name']); ?></span></span>
                                    <span class="row-meta d-block tnum"><?php echo htmlspecialchars(formatPhoneDisplay($apt['client_phone'])); ?></span>
                                </span>
                                <span class="row-trail"><i class="bi bi-chevron-right row-chevron" aria-hidden="true"></i></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <!-- Hızlı ödeme paneli -->
    <div class="modal fade" id="quickPaymentModal" tabindex="-1" aria-labelledby="quickPaymentTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="quickPaymentTitle">Ödeme al</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="sheet-summary">
                        <span class="row-time" data-pay-time></span>
                        <div>
                            <p class="row-title"><span data-pay-name></span></p>
                            <p class="row-meta" data-pay-date></p>
                        </div>
                    </div>
                    <form action="process/add-payment" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="appointment_id" value="">
                        <input type="hidden" name="return_to" value="dashboard">
                        <div class="field">
                            <label for="qpAmount" class="form-label">Tutar (₺)</label>
                            <input type="number" inputmode="decimal" class="form-control tnum" id="qpAmount" name="amount"
                                   value="<?php echo htmlspecialchars(feeInputValue($defaultAmount)); ?>" data-default="<?php echo htmlspecialchars(feeInputValue($defaultAmount)); ?>"
                                   step="0.01" min="0" required>
                            <div class="invalid-feedback">Tutarı girin.</div>
                        </div>
                        <fieldset class="field">
                            <legend class="form-label">Ödeme yöntemi</legend>
                            <div class="choice">
                                <input type="radio" name="payment_method" id="qpCash" value="cash" checked>
                                <label for="qpCash"><i class="bi bi-cash-stack" aria-hidden="true"></i>Nakit</label>
                                <input type="radio" name="payment_method" id="qpCard" value="card">
                                <label for="qpCard"><i class="bi bi-credit-card" aria-hidden="true"></i>Kart</label>
                                <input type="radio" name="payment_method" id="qpBank" value="bank_transfer">
                                <label for="qpBank"><i class="bi bi-bank" aria-hidden="true"></i>Havale/EFT</label>
                            </div>
                        </fieldset>
                        <div class="field">
                            <label for="qpNotes" class="form-label">Not <span class="ink-3">(isteğe bağlı)</span></label>
                            <textarea class="form-control" id="qpNotes" name="notes" rows="2"></textarea>
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

<?php include 'includes/footer.php'; ?>
