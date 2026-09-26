<?php
session_start();
require_once 'config/database.php';
require_once 'includes/phone.php';
require_once 'includes/settings.php';

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

// ---------------------------------------------------------------------------
// Görünüm hesapları (yalnızca yukarıda çekilen veriden)
// ---------------------------------------------------------------------------
$trMonths = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
$methodNames = ['cash' => 'Nakit', 'card' => 'Kart', 'credit_card' => 'Kart', 'bank_transfer' => 'Havale/EFT'];
$nowTs = time();
$today = date('Y-m-d');            // randevu düzenleme formundaki en erken tarih
$tomorrow = date('Y-m-d', strtotime('+1 day'));

function money($amount) {
    $amount = (float) $amount;
    return '₺' . number_format($amount, (floor($amount) == $amount) ? 0 : 2, ',', '.');
}

function telHref($phone) {
    return 'tel:' . preg_replace('/[^0-9+]/', '', (string) $phone);
}

function clientInitials($name) {
    $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) {
        return '?';
    }
    $picked = count($parts) > 1 ? [reset($parts), end($parts)] : [reset($parts)];
    $out = '';
    foreach ($picked as $part) {
        $ch = mb_substr($part, 0, 1, 'UTF-8');
        $out .= mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $ch), 'UTF-8');
    }
    return $out;
}

// onclick="fn(...)" içine güvenli JS argümanı (tırnak, satır sonu, HTML kaçışı)
function jsArg($value) {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
}

function trDateLong($ts, $gunler, $trMonths) {
    return date('j', $ts) . ' ' . $trMonths[(int) date('n', $ts)] . ' ' . $gunler[date('l', $ts)];
}

// Seansları yaklaşan / geçmiş olarak ayır; özet sayıları çıkar
$upcomingList = [];
$pastList = [];
$heldCount = 0;
$unpaidCount = 0;
foreach ($appointments as $apt) {
    $ts = strtotime($apt['appointment_date'] . ' ' . $apt['appointment_time']);
    $apt['ts'] = $ts;
    $apt['is_cancelled'] = $apt['status'] === 'iptal';
    $apt['is_paid'] = $apt['payment_status'] === 'paid';
    $apt['started'] = $ts <= $nowTs;
    if ($apt['started']) {
        $pastList[] = $apt;
        if (!$apt['is_cancelled']) {
            $heldCount++;
            if (!$apt['is_paid']) {
                $unpaidCount++;
            }
        }
    } else {
        $upcomingList[] = $apt;
    }
}
$upcomingList = array_reverse($upcomingList); // en yakın seans üstte

$paidTotal = 0.0;
foreach ($payments as $p) {
    $paidTotal += (float) $p['amount'];
}
// Ödeme formu için varsayılan tutar: danışanın son ödemesi, yoksa önceki sabit değer
$defaultAmount = getSessionFee($db); // Seans Ayarları'ndaki varsayılan ücret

// Geçmiş seanslar aya göre; ilk ~12 seans açık, daha eskiler katlanır
$pastByMonth = [];
foreach ($pastList as $apt) {
    $pastByMonth[date('Y-m', $apt['ts'])][] = $apt;
}
$visibleLimit = 12;
$monthKeys = array_keys($pastByMonth);
$shownCount = 0;
$splitAt = count($monthKeys);
foreach ($monthKeys as $idx => $key) {
    if ($shownCount >= $visibleLimit) {
        $splitAt = $idx;
        break;
    }
    $shownCount += count($pastByMonth[$key]);
}
$olderCount = count($pastList) - $shownCount;

$clientName = (string) $client['name'];
$clientPhone = trim((string) ($client['phone'] ?? ''));
$clientEmail = trim((string) ($client['email'] ?? ''));
$clientAddress = trim((string) ($client['address'] ?? ''));
$clientNotes = trim((string) ($client['notes'] ?? ''));
$createdTs = !empty($client['created_at']) ? strtotime($client['created_at']) : null;

// Tek seans satırı
function renderSessionRow($apt, $gunler, $trMonths, $methodNames, $today, $tomorrow) {
    $ts = $apt['ts'];
    $time = date('H:i', $ts);
    $shortDate = date('j', $ts) . ' ' . mb_substr($trMonths[(int) date('n', $ts)], 0, 3, 'UTF-8');
    $longDate = trDateLong($ts, $gunler, $trMonths);
    $dateStr = $apt['appointment_date'];
    $title = $dateStr === $today ? 'Bugün' : ($dateStr === $tomorrow ? 'Yarın' : $gunler[date('l', $ts)]);
    $isCancelled = $apt['is_cancelled'];
    $isPaid = $apt['is_paid'];
    $started = $apt['started'];
    $notes = trim((string) ($apt['notes'] ?? ''));
    $method = $methodNames[$apt['payment_method'] ?? ''] ?? '';
    ?>
                            <div class="row-item<?php echo $isCancelled ? ' is-cancelled' : ''; ?>">
                                <span class="row-time"><?php echo $time; ?><small><?php echo htmlspecialchars($shortDate); ?></small></span>
                                <div class="row-main">
                                    <p class="row-title"><span><?php echo htmlspecialchars($title); ?></span></p>
                                    <p class="row-meta"<?php echo $notes !== '' ? ' title="' . htmlspecialchars($notes) . '"' : ''; ?>>
                                        <?php if ($isCancelled): ?>
                                            <span class="mark mark-cancelled">İptal edildi</span>
                                        <?php elseif ($isPaid): ?>
                                            <span class="mark mark-paid">Ödendi<?php echo $method !== '' ? ' · ' . htmlspecialchars($method) : ''; ?></span>
                                        <?php elseif ($started): ?>
                                            <span class="mark mark-unpaid">Ödenmedi</span>
                                        <?php else: ?>
                                            <span>Planlandı</span>
                                        <?php endif; ?>
                                        <?php if ($notes !== ''): ?> · <?php echo htmlspecialchars($notes); ?><?php endif; ?>
                                    </p>
                                </div>
                                <div class="row-trail">
                                    <?php if ($isPaid): ?>
                                    <span class="row-amount"><?php echo money($apt['amount']); ?></span>
                                    <?php elseif ($started && !$isCancelled): ?>
                                    <button type="button" class="btn btn-sm btn-brass" onclick="addPayment(<?php echo (int) $apt['id']; ?>, <?php echo jsArg($time); ?>, <?php echo jsArg($longDate); ?>)">
                                        Ödeme al
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div class="row-actions">
                                    <?php if (!$started && !$isCancelled): ?>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="editAppointment(<?php echo (int) $apt['id']; ?>, <?php echo jsArg($apt['appointment_date']); ?>, <?php echo jsArg($time); ?>, <?php echo jsArg($notes); ?>)">
                                        Düzenle
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($isPaid): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="cancelPayment(<?php echo (int) $apt['payment_id']; ?>, <?php echo jsArg($longDate . ', ' . $time); ?>, <?php echo jsArg(money($apt['amount'])); ?>)">
                                        Ödemeyi iptal et
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteAppointment(<?php echo (int) $apt['id']; ?>, <?php echo jsArg($longDate); ?>, <?php echo jsArg($time); ?>)">
                                        Randevuyu sil
                                    </button>
                                </div>
                            </div>
    <?php
}

$pageTitle = $clientName;
$pageBack = 'clients';

// Header'ı dahil et
include 'includes/header.php';
?>
<body class="<?php echo $themeClass; ?>" data-page="client-details">
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main id="content" tabindex="-1">
            <?php include 'includes/topbar.php'; ?>

            <div class="page client-page">
                <div class="client-side">
                    <!-- Kimlik ve iletişim -->
                    <section class="panel panel-pad client-id" aria-label="İletişim bilgileri">
                        <div class="client-id-head">
                            <span class="avatar client-avatar" aria-hidden="true"><?php echo htmlspecialchars(clientInitials($clientName)); ?></span>
                            <div class="client-id-text">
                                <h2 class="client-id-name"><?php echo htmlspecialchars($clientName); ?></h2>
                                <?php if ($clientPhone !== ''): ?>
                                <p class="client-id-line tnum"><?php echo htmlspecialchars(formatPhoneDisplay($clientPhone)); ?></p>
                                <?php endif; ?>
                                <?php if ($clientEmail !== ''): ?>
                                <p class="client-id-line client-id-email"><?php echo htmlspecialchars($clientEmail); ?></p>
                                <?php endif; ?>
                                <?php if ($createdTs): ?>
                                <p class="client-id-since">Kayıt: <?php echo date('j', $createdTs) . ' ' . $trMonths[(int) date('n', $createdTs)] . ' ' . date('Y', $createdTs); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="client-id-actions">
                            <?php if ($clientPhone !== ''): ?>
                            <a href="<?php echo htmlspecialchars(telHref($clientPhone)); ?>" class="btn btn-primary">
                                <i class="bi bi-telephone" aria-hidden="true"></i> Ara
                            </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#editClientModal">
                                <i class="bi bi-pencil" aria-hidden="true"></i> Düzenle
                            </button>
                        </div>
                    </section>

                    <!-- Danışanın hesabı -->
                    <div class="ledger client-ledger" role="group" aria-label="Seans ve ödeme özeti">
                        <div class="ledger-item is-lead">
                            <span class="ledger-label">Toplam ödenen</span>
                            <span class="ledger-value"><?php echo money($paidTotal); ?></span>
                        </div>
                        <div class="ledger-item">
                            <span class="ledger-label">Yapılan seans</span>
                            <span class="ledger-value"><?php echo $heldCount; ?></span>
                        </div>
                        <div class="ledger-item">
                            <span class="ledger-label">Ödeme bekleyen</span>
                            <span class="ledger-value<?php echo $unpaidCount > 0 ? ' is-due' : ''; ?>"><?php echo $unpaidCount > 0 ? $unpaidCount . ' seans' : 'Yok'; ?></span>
                        </div>
                    </div>
                </div>

                <div class="client-main">
                    <?php if (empty($appointments)): ?>
                    <section class="section" aria-labelledby="sessionsTitle">
                        <div class="section-head">
                            <h2 class="section-title" id="sessionsTitle">Seanslar</h2>
                        </div>
                        <div class="empty">
                            <p class="empty-title">Henüz seans yok</p>
                            <p>Bu danışana ilk seansı “Randevu” düğmesiyle ekleyin; randevular ve ödemeler burada tarih sırasıyla görünür.</p>
                        </div>
                    </section>
                    <?php else: ?>
                    <!-- Yaklaşan seanslar -->
                    <section class="section" aria-labelledby="upcomingTitle">
                        <div class="section-head">
                            <h2 class="section-title" id="upcomingTitle">Yaklaşan seanslar</h2>
                            <?php if (!empty($upcomingList)): ?>
                            <span class="section-note"><?php echo count($upcomingList); ?> seans</span>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($upcomingList)): ?>
                        <p class="panel panel-pad client-quiet">Planlı seans yok. Yeni seans için “Randevu” düğmesine dokunun.</p>
                        <?php else: ?>
                        <div class="list">
                            <?php foreach ($upcomingList as $apt) {
                                renderSessionRow($apt, $gunler, $trMonths, $methodNames, $today, $tomorrow);
                            } ?>
                        </div>
                        <?php endif; ?>
                    </section>

                    <!-- Geçmiş seanslar -->
                    <?php if (!empty($pastList)): ?>
                    <section class="section" aria-labelledby="pastTitle">
                        <div class="section-head">
                            <h2 class="section-title" id="pastTitle">Geçmiş seanslar</h2>
                            <span class="section-note"><?php echo count($pastList); ?> kayıt</span>
                        </div>
                        <?php foreach ($monthKeys as $idx => $key):
                            if ($idx === $splitAt): ?>
                        <details class="older-sessions">
                            <summary class="btn btn-secondary btn-block"><span class="when-closed">Daha eski <?php echo $olderCount; ?> kaydı göster</span><span class="when-open">Eski kayıtları gizle</span></summary>
                            <?php endif;
                            $monthTs = strtotime($key . '-01');
                            $items = $pastByMonth[$key];
                        ?>
                        <div class="list-day"><?php echo $trMonths[(int) date('n', $monthTs)] . ' ' . date('Y', $monthTs); ?><span><?php echo count($items); ?> kayıt</span></div>
                        <div class="list">
                            <?php foreach ($items as $apt) {
                                renderSessionRow($apt, $gunler, $trMonths, $methodNames, $today, $tomorrow);
                            } ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if ($splitAt < count($monthKeys)): ?>
                        </details>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="client-extra">
                    <!-- Notlar ve adres -->
                    <section class="section" aria-labelledby="notesTitle">
                        <div class="section-head">
                            <h2 class="section-title" id="notesTitle">Notlar ve adres</h2>
                            <button type="button" class="btn btn-quiet btn-sm" data-bs-toggle="modal" data-bs-target="#editClientModal">Düzenle</button>
                        </div>
                        <?php if ($clientNotes === '' && $clientAddress === ''): ?>
                        <p class="panel panel-pad client-quiet">Not ya da adres eklenmemiş. “Düzenle” ile ekleyebilirsiniz.</p>
                        <?php else: ?>
                        <dl class="panel panel-pad client-facts">
                            <?php if ($clientNotes !== ''): ?>
                            <dt>Notlar</dt>
                            <dd><?php echo nl2br(htmlspecialchars($clientNotes)); ?></dd>
                            <?php endif; ?>
                            <?php if ($clientAddress !== ''): ?>
                            <dt>Adres</dt>
                            <dd><?php echo nl2br(htmlspecialchars($clientAddress)); ?></dd>
                            <?php endif; ?>
                        </dl>
                        <?php endif; ?>
                    </section>

                    <div class="client-danger">
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteClientModal<?php echo (int) $client['id']; ?>">
                            Danışanı sil
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Danışan Düzenleme Modal -->
    <div class="modal fade" id="editClientModal" tabindex="-1" aria-labelledby="editClientTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="editClientTitle">Danışanı düzenle</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <form action="process/edit-client" method="POST" class="needs-validation" novalidate id="editClientForm">
                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                        <div class="field">
                            <label for="name" class="form-label">Ad soyad</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($client['name']); ?>" required>
                            <div class="invalid-feedback">Ad soyadı yazın.</div>
                        </div>
                        <div class="field">
                            <label for="phone" class="form-label">Telefon</label>
                            <input type="tel" class="form-control tnum" id="phone" name="phone" value="<?php echo htmlspecialchars($client['phone']); ?>" required>
                            <div class="invalid-feedback">Telefonu 10–11 rakam olarak, boşluksuz yazın (ör. 05321234567).</div>
                        </div>
                        <div class="field">
                            <label for="email" class="form-label">E-posta <span class="ink-3">(isteğe bağlı)</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars((string) $client['email']); ?>">
                            <div class="invalid-feedback">Geçerli bir e-posta adresi yazın ya da alanı boş bırakın.</div>
                        </div>
                        <div class="field">
                            <label for="address" class="form-label">Adres <span class="ink-3">(isteğe bağlı)</span></label>
                            <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars((string) $client['address']); ?>">
                        </div>
                        <div class="field">
                            <label for="notes" class="form-label">Notlar <span class="ink-3">(isteğe bağlı)</span></label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars((string) $client['notes']); ?></textarea>
                        </div>
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-primary" data-original-text="Kaydet">Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Danışan Silme Modal (Danışanlar listesindeki ile aynı form) -->
    <div class="modal fade" id="deleteClientModal<?php echo (int) $client['id']; ?>" tabindex="-1" aria-labelledby="deleteClientTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="deleteClientTitle">Danışanı sil</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="sheet-summary">
                        <span class="avatar avatar-sm" aria-hidden="true"><?php echo htmlspecialchars(clientInitials($clientName)); ?></span>
                        <div>
                            <p class="row-title"><span><?php echo htmlspecialchars($clientName); ?></span></p>
                            <p class="row-meta tnum"><?php echo htmlspecialchars(formatPhoneDisplay($clientPhone)); ?><?php echo $clientEmail !== '' ? ' · ' . htmlspecialchars($clientEmail) : ''; ?></p>
                        </div>
                    </div>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>
                        Danışanın bütün randevuları ve ödeme kayıtları da silinir. Bu işlem geri alınamaz.
                    </div>
                    <div class="sheet-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                        <form action="process/delete-client" method="POST">
                            <input type="hidden" name="client_id" value="<?php echo (int) $client['id']; ?>">
                            <button type="submit" class="btn btn-danger">Sil</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Randevu Düzenleme Modal -->
    <div class="modal fade" id="editAppointmentModal" tabindex="-1" aria-labelledby="editAppointmentTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="editAppointmentTitle">Randevuyu düzenle</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <form action="process/edit-appointment" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="appointment_id" id="edit_appointment_id">
                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                        <div class="field">
                            <label for="edit_date" class="form-label">Tarih</label>
                            <input type="date" class="form-control" id="edit_date" name="date" min="<?php echo $today; ?>" required>
                            <div class="invalid-feedback">Bugün ya da sonrası için bir tarih seçin.</div>
                        </div>
                        <fieldset class="field">
                            <legend class="form-label">Saat</legend>
                            <div class="field-row">
                                <select class="form-select tnum" id="edit_hour" name="hour" required aria-label="Saat">
                                    <option value="">Saat</option>
                                    <?php for($i = 9; $i <= 20; $i++): ?>
                                        <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select class="form-select tnum" id="edit_minute" name="minute" required aria-label="Dakika">
                                    <option value="">Dakika</option>
                                    <option value="00">00</option>
                                    <option value="15">15</option>
                                    <option value="30">30</option>
                                    <option value="45">45</option>
                                </select>
                            </div>
                            <div class="invalid-feedback">Saat ve dakikayı seçin.</div>
                        </fieldset>
                        <div class="field">
                            <label for="edit_notes" class="form-label">Not <span class="ink-3">(isteğe bağlı)</span></label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
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

    <!-- Randevu Silme Modal -->
    <div class="modal fade" id="deleteAppointmentModal" tabindex="-1" aria-labelledby="deleteAppointmentTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="deleteAppointmentTitle">Randevuyu sil</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="sheet-summary">
                        <span class="row-time" id="delete_appointment_time"></span>
                        <div>
                            <p class="row-title"><span><?php echo htmlspecialchars($clientName); ?></span></p>
                            <p class="row-meta" id="delete_appointment_date"></p>
                        </div>
                    </div>
                    <p>Randevu ve varsa bu seansa bağlı ödeme kaydı silinir. Bu işlem geri alınamaz.</p>
                    <div class="sheet-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                        <form action="process/delete-appointment" method="POST">
                            <input type="hidden" name="appointment_id" id="delete_appointment_id">
                            <button type="submit" class="btn btn-danger">Sil</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ödeme Ekleme Modal -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1" aria-labelledby="addPaymentTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="addPaymentTitle">Ödeme al</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="sheet-summary">
                        <span class="row-time" id="payment_session_time"></span>
                        <div>
                            <p class="row-title"><span><?php echo htmlspecialchars($clientName); ?></span></p>
                            <p class="row-meta" id="payment_session_date"></p>
                        </div>
                    </div>
                    <form action="process/add-payment" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="appointment_id" id="payment_appointment_id">
                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                        <input type="hidden" name="return_to" value="client-details">
                        <div class="field">
                            <label for="amount" class="form-label">Tutar (₺)</label>
                            <input type="number" inputmode="decimal" class="form-control tnum" id="amount" name="amount" min="0" step="0.01" value="<?php echo htmlspecialchars(feeInputValue($defaultAmount)); ?>" required>
                            <div class="invalid-feedback">Tutarı girin.</div>
                        </div>
                        <fieldset class="field">
                            <legend class="form-label">Ödeme yöntemi</legend>
                            <div class="choice">
                                <input type="radio" name="payment_method" id="pmCash" value="cash" checked>
                                <label for="pmCash"><i class="bi bi-cash-stack" aria-hidden="true"></i>Nakit</label>
                                <input type="radio" name="payment_method" id="pmCard" value="card">
                                <label for="pmCard"><i class="bi bi-credit-card" aria-hidden="true"></i>Kart</label>
                                <input type="radio" name="payment_method" id="pmBank" value="bank_transfer">
                                <label for="pmBank"><i class="bi bi-bank" aria-hidden="true"></i>Havale/EFT</label>
                            </div>
                        </fieldset>
                        <div class="field">
                            <label for="payment_notes" class="form-label">Not <span class="ink-3">(isteğe bağlı)</span></label>
                            <textarea class="form-control" id="payment_notes" name="notes" rows="2"></textarea>
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

    <!-- Ödeme İptal Modal -->
    <div class="modal fade" id="cancelPaymentModal" tabindex="-1" aria-labelledby="cancelPaymentTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="cancelPaymentTitle">Ödemeyi iptal et</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <p>
                        <strong><?php echo htmlspecialchars($clientName); ?></strong> için
                        <strong><span id="cancel_payment_date"></span></strong> seansında alınan
                        <strong class="tnum"><span id="cancel_payment_amount"></span></strong> ödeme silinecek.
                    </p>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>
                        Seans yeniden “Ödenmedi” görünür. Bu işlem geri alınamaz.
                    </div>
                    <form action="process/cancel-payment" method="POST">
                        <input type="hidden" name="payment_id" id="cancel_payment_id">
                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                        <input type="hidden" name="redirect" value="client-details">
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-danger">Ödemeyi iptal et</button>
                        </div>
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

        document.getElementById('edit_notes').value = notes || '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editAppointmentModal')).show();
    }

    // Randevu silme modalını açma fonksiyonu
    function deleteAppointment(id, date, time) {
        document.getElementById('delete_appointment_id').value = id;
        document.getElementById('delete_appointment_date').textContent = date;
        document.getElementById('delete_appointment_time').textContent = time;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteAppointmentModal')).show();
    }

    // Ödeme ekleme modalını açma fonksiyonu (saat ve tarih özet satırı içindir, isteğe bağlı)
    function addPayment(id, time, date) {
        document.getElementById('payment_appointment_id').value = id;
        document.getElementById('payment_session_time').textContent = time || '';
        document.getElementById('payment_session_date').textContent = date || '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('addPaymentModal')).show();
    }

    // Ödeme iptal modalını açma fonksiyonu
    function cancelPayment(paymentId, date, amount) {
        document.getElementById('cancel_payment_id').value = paymentId;
        document.getElementById('cancel_payment_date').textContent = date;
        document.getElementById('cancel_payment_amount').textContent = amount;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('cancelPaymentModal')).show();
    }
    </script>

<?php include 'includes/footer.php'; ?>
