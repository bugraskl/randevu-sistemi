<?php
session_start();
require_once 'config/database.php';
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
               TIME_FORMAT(a.appointment_time, '%H:%i') as formatted_time,
               (SELECT p.id FROM payments p WHERE p.appointment_id = a.id LIMIT 1) as payment_id
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
               TIME_FORMAT(a.appointment_time, '%H:%i') as formatted_time,
               (SELECT p.id FROM payments p WHERE p.appointment_id = a.id LIMIT 1) as payment_id
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
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$nowTs = time();

// Gün ve ay isimleri
$gunler = [
    'Monday' => 'Pazartesi',
    'Tuesday' => 'Salı',
    'Wednesday' => 'Çarşamba',
    'Thursday' => 'Perşembe',
    'Friday' => 'Cuma',
    'Saturday' => 'Cumartesi',
    'Sunday' => 'Pazar'
];
$aylar = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

function randevuGunEtiketi($date, $today, $tomorrow, $gunler, $aylar) {
    $ts = strtotime($date);
    $long = date('j', $ts) . ' ' . $aylar[(int) date('n', $ts)];
    if ($date === $today) return 'Bugün, ' . $long;
    if ($date === $tomorrow) return 'Yarın, ' . $long;
    return $gunler[date('l', $ts)] . ', ' . $long;
}

function randevuTarihUzun($date, $gunler, $aylar) {
    $ts = strtotime($date);
    return date('j', $ts) . ' ' . $aylar[(int) date('n', $ts)] . ' ' . $gunler[date('l', $ts)];
}

// Randevular onay beklemez: oluşturulan randevu planlanmış sayılır. Yalnızca iptal ayrıca gösterilir.
$durumlar = [
    'iptal' => ['mark-cancelled', 'İptal edildi'],
];

// Listeyi güne göre grupla
$gruplar = [];
foreach ($appointments as $appointment) {
    $gruplar[$appointment['appointment_date']][] = $appointment;
}
$aktifSayisi = count(array_filter($appointments, function ($a) {
    return $a['status'] !== 'iptal';
}));

// Görünüm seçeneğini URL'den al
$view = isset($_GET['view']) ? $_GET['view'] : 'list';
$activeTab = $view === 'calendar' ? 'calendar' : 'list';

// Yeni randevu kaydedilince dönülecek sayfa (yalnızca izinli sayfalar)
$returnTo = in_array($_GET['from'] ?? '', ['dashboard'], true) ? $_GET['from'] : '';

$pageTitle = 'Randevular';
$pageSubtitle = $aktifSayisi > 0 ? $aktifSayisi . ' yaklaşan seans' : 'Yaklaşan seans yok';

// Header'ı dahil et
include 'includes/header.php';
?>
<body class="<?php echo $themeClass; ?>" data-page="appointments">
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main id="content" tabindex="-1">
            <?php include 'includes/topbar.php'; ?>

            <div class="page">
                <!-- Görünüm Seçenekleri -->
                <ul class="nav nav-tabs seg seg-block mb-4" id="viewTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $activeTab === 'list' ? 'active' : ''; ?>"
                                id="list-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#list-view"
                                type="button"
                                role="tab"
                                aria-controls="list-view"
                                aria-selected="<?php echo $activeTab === 'list' ? 'true' : 'false'; ?>"
                                onclick="changeView('list')">
                            <i class="bi bi-list-ul" aria-hidden="true"></i> Liste
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $activeTab === 'calendar' ? 'active' : ''; ?>"
                                id="calendar-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#calendar-view"
                                type="button"
                                role="tab"
                                aria-controls="calendar-view"
                                aria-selected="<?php echo $activeTab === 'calendar' ? 'true' : 'false'; ?>"
                                onclick="changeView('calendar')">
                            <i class="bi bi-calendar3" aria-hidden="true"></i> Takvim
                        </button>
                    </li>
                </ul>

                <!-- Tab İçerikleri -->
                <div class="tab-content" id="viewTabsContent">
                    <!-- Liste Görünümü -->
                    <div class="tab-pane fade <?php echo $activeTab === 'list' ? 'show active' : ''; ?>"
                         id="list-view"
                         role="tabpanel"
                         aria-labelledby="list-tab">

                        <div class="mb-4">
                            <label for="searchDate" class="form-label">Başka bir güne bak <span class="ink-3">(geçmiş günler dahil)</span></label>
                            <div class="input-group">
                                <input type="date" id="searchDate" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                <button class="btn btn-secondary" type="button" id="searchButton">
                                    <i class="bi bi-search" aria-hidden="true"></i> Göster
                                </button>
                            </div>
                        </div>

                        <?php if (empty($appointments)): ?>
                        <div class="empty">
                            <p class="empty-title">Yaklaşan randevu yok</p>
                            <p>“Randevu” düğmesiyle ilk seansı ekleyin; danışana randevu bilgisi SMS’le otomatik gider.</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAppointmentModal">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i> Randevu ekle
                            </button>
                        </div>
                        <?php else: ?>
                            <?php
                            // Yakın 14 gün açık; sonrası "sonraki günler" altında katlanır
                            $katlamaSiniri = date('Y-m-d', strtotime('+14 days'));
                            $sonrakiSeansSayisi = 0;
                            foreach ($gruplar as $date => $items) {
                                if ($date > $katlamaSiniri) {
                                    $sonrakiSeansSayisi += count($items);
                                }
                            }
                            $katlamaAcildi = false;
                            ?>
                            <?php foreach ($gruplar as $date => $items): ?>
                            <?php if (!$katlamaAcildi && $date > $katlamaSiniri): $katlamaAcildi = true; ?>
                            <div class="mt-4" data-later-toggle-wrap>
                                <button type="button" class="btn btn-secondary btn-block" data-later-toggle aria-expanded="false" aria-controls="laterDays">
                                    Sonraki günleri göster <span class="ink-3 tnum">(<?php echo $sonrakiSeansSayisi; ?> seans)</span>
                                </button>
                            </div>
                            <div id="laterDays" hidden>
                            <?php endif; ?>
                            <div class="list-day">
                                <?php echo htmlspecialchars(randevuGunEtiketi($date, $today, $tomorrow, $gunler, $aylar)); ?>
                                <span><?php echo count($items); ?> seans</span>
                            </div>
                            <div class="list">
                                <?php foreach ($items as $appointment):
                                    $start = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
                                    $isPast = $start < $nowTs;
                                    $isCancelled = $appointment['status'] === 'iptal';
                                    $durum = $durumlar[$appointment['status']] ?? null;
                                ?>
                                <button type="button"
                                        class="row-item<?php echo $isPast ? ' is-past' : ''; ?><?php echo $isCancelled ? ' is-cancelled' : ''; ?>"
                                        data-edit-appointment="<?php echo (int) $appointment['id']; ?>"
                                        aria-label="<?php echo htmlspecialchars($appointment['formatted_time'] . ' ' . $appointment['client_name'] . ', düzenle'); ?>">
                                    <span class="row-time"><?php echo htmlspecialchars($appointment['formatted_time']); ?></span>
                                    <span class="row-main">
                                        <span class="row-title"><span><?php echo htmlspecialchars($appointment['client_name']); ?></span></span>
                                        <span class="row-meta d-block">
                                            <?php if ($isPast && !$isCancelled): ?>
                                                <?php echo !empty($appointment['payment_id']) ? '<span class="mark mark-paid">Ödendi</span>' : '<span class="mark mark-unpaid">Ödenmedi</span>'; ?> ·
                                            <?php elseif ($durum): ?>
                                                <span class="mark <?php echo $durum[0]; ?>"><?php echo $durum[1]; ?></span> ·
                                            <?php endif; ?>
                                            <span class="tnum"><?php echo htmlspecialchars(formatPhoneDisplay($appointment['client_phone'])); ?></span>
                                        </span>
                                    </span>
                                    <span class="row-trail"><i class="bi bi-chevron-right row-chevron" aria-hidden="true"></i></span>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php if ($katlamaAcildi): ?>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Takvim Görünümü -->
                    <div class="tab-pane fade <?php echo $activeTab === 'calendar' ? 'show active' : ''; ?>"
                         id="calendar-view"
                         role="tabpanel"
                         aria-labelledby="calendar-tab">
                        <div class="calendar-container">
                            <div class="calendar-header">
                                <button type="button" class="btn btn-secondary btn-icon" id="prevMonth" aria-label="Önceki ay">
                                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                </button>
                                <h2 id="currentMonth" aria-live="polite"></h2>
                                <button type="button" class="btn btn-secondary btn-icon" id="nextMonth" aria-label="Sonraki ay">
                                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="calendar-grid">
                                <div class="calendar-weekdays" aria-hidden="true">
                                    <div>Pzt</div>
                                    <div>Sal</div>
                                    <div>Çar</div>
                                    <div>Per</div>
                                    <div>Cum</div>
                                    <div>Cmt</div>
                                    <div>Paz</div>
                                </div>
                                <div id="calendarDays" class="calendar-days"></div>
                            </div>
                        </div>
                        <div id="calendarDayDetail" class="calendar-day-detail" aria-live="polite"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Randevu düzenleme: tek ortak panel, satırın verisiyle doldurulur (script.js → openAppointmentEditor) -->
    <div class="modal fade" id="editAppointmentModal" tabindex="-1" aria-labelledby="editAppointmentTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="editAppointmentTitle">Randevuyu düzenle</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="sheet-summary">
                        <span class="row-time" data-edit-time></span>
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div class="row-main">
                                <p class="row-title"><span data-edit-name></span></p>
                                <p class="row-meta" data-edit-date></p>
                            </div>
                            <a href="#" class="btn btn-sm btn-secondary" data-edit-tel hidden>
                                <i class="bi bi-telephone" aria-hidden="true"></i> Ara
                            </a>
                        </div>
                    </div>
                    <form action="process/edit-appointment" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="appointment_id" id="editAppointmentId" value="">
                        <div class="field">
                            <label for="editAppointmentClient" class="form-label">Danışan</label>
                            <select class="form-select" id="editAppointmentClient" name="client_id" required>
                                <option value="">Danışan seçin</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?php echo (int) $client['id']; ?>"><?php echo htmlspecialchars($client['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Lütfen danışan seçin.</div>
                        </div>
                        <div class="field">
                            <label for="editAppointmentDate" class="form-label">Tarih</label>
                            <input type="date" class="form-control" id="editAppointmentDate" name="date" value="" required>
                            <div class="invalid-feedback">Lütfen tarih seçin.</div>
                        </div>
                        <div class="field">
                            <span class="form-label" id="editTimeLabel">Saat</span>
                            <div class="field-row" role="group" aria-labelledby="editTimeLabel">
                                <select class="form-select" id="editAppointmentHour" name="hour" required aria-label="Saat">
                                    <option value="">Saat</option>
                                    <?php for ($i = 9; $i <= 20; $i++): $hour = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                    <option value="<?php echo $hour; ?>"><?php echo $hour; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select class="form-select" id="editAppointmentMinute" name="minute" required aria-label="Dakika">
                                    <option value="">Dakika</option>
                                    <?php foreach (['00', '15', '30', '45'] as $minute): ?>
                                    <option value="<?php echo $minute; ?>"><?php echo $minute; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="field">
                            <label for="editAppointmentNotes" class="form-label">Not <span class="ink-3">(isteğe bağlı)</span></label>
                            <textarea class="form-control" id="editAppointmentNotes" name="notes" rows="3"></textarea>
                        </div>
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-primary">Kaydet</button>
                        </div>
                    </form>
                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-sm btn-quiet quiet-ink" data-bs-toggle="modal" data-bs-target="#deleteAppointmentModal">
                            <i class="bi bi-trash3" aria-hidden="true"></i> Randevuyu sil
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Silme onayı: tek ortak panel -->
    <div class="modal fade" id="deleteAppointmentModal" tabindex="-1" aria-labelledby="deleteAppointmentTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="deleteAppointmentTitle">Randevu silinsin mi?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="sheet-summary">
                        <span class="row-time" data-delete-time></span>
                        <div>
                            <p class="row-title"><span data-delete-name></span></p>
                            <p class="row-meta" data-delete-date></p>
                        </div>
                    </div>
                    <p>Randevu ve varsa ona bağlı ödeme kaydı kalıcı olarak silinir. Bu işlem geri alınamaz.</p>
                    <div class="sheet-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                        <form action="process/delete-appointment" method="POST">
                            <input type="hidden" name="appointment_id" id="deleteAppointmentId" value="">
                            <button type="submit" class="btn btn-danger">Randevuyu sil</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Yeni Randevu Modal -->
    <div class="modal fade" id="addAppointmentModal" tabindex="-1" aria-labelledby="addAppointmentTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="addAppointmentTitle">Yeni randevu</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <form action="process/add-appointment" method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
                        <?php if ($returnTo): ?>
                        <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($returnTo); ?>">
                        <?php endif; ?>
                        <div class="field" data-client-existing>
                            <div class="field-head">
                                <label for="client" class="form-label">Danışan</label>
                                <button type="button" class="btn btn-quiet btn-sm" data-client-mode="new">
                                    <i class="bi bi-person-plus" aria-hidden="true"></i> Yeni danışan
                                </button>
                            </div>
                            <select class="form-select" id="client" name="client_id" required>
                                <option value="">Danışan seçin</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Lütfen danışan seçin ya da yeni danışan ekleyin.</div>
                        </div>
                        <fieldset class="field" data-client-new hidden>
                            <div class="field-head">
                                <legend class="form-label">Yeni danışan</legend>
                                <button type="button" class="btn btn-quiet btn-sm" data-client-mode="existing">Kayıtlı danışan seç</button>
                            </div>
                            <div class="field-box">
                                <div class="field">
                                    <label for="newClientName" class="form-label">Ad soyad</label>
                                    <input type="text" class="form-control" id="newClientName" name="new_client_name" maxlength="100" autocomplete="off" autocapitalize="words" required disabled>
                                    <div class="invalid-feedback">Ad soyad girin.</div>
                                </div>
                                <div class="field mb-0">
                                    <label for="newClientPhone" class="form-label">Telefon</label>
                                    <input type="tel" class="form-control tnum" id="newClientPhone" name="new_client_phone" inputmode="tel" autocomplete="off" placeholder="0500 123 45 67" data-phone required disabled>
                                    <div class="invalid-feedback">Telefon numarasını girin.</div>
                                    <div class="form-text">Nasıl yazarsanız yazın 05001234567 biçiminde kaydedilir. Numara zaten kayıtlıysa randevu o danışana eklenir.</div>
                                </div>
                            </div>
                        </fieldset>
                        <div class="field">
                            <label for="date" class="form-label">Tarih</label>
                            <input type="date" class="form-control" id="date" name="date" required>
                            <div class="invalid-feedback">Lütfen geçerli bir tarih seçin.</div>
                        </div>
                        <div class="field">
                            <span class="form-label" id="addTimeLabel">Saat</span>
                            <div class="field-row" role="group" aria-labelledby="addTimeLabel">
                                <select class="form-select" id="hour" name="hour" required aria-label="Saat">
                                    <option value="">Saat</option>
                                    <?php for($i = 9; $i <= 20; $i++): ?>
                                        <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select class="form-select" id="minute" name="minute" required aria-label="Dakika">
                                    <option value="">Dakika</option>
                                    <option value="00">00</option>
                                    <option value="15">15</option>
                                    <option value="30">30</option>
                                    <option value="45">45</option>
                                </select>
                            </div>
                            <div class="invalid-feedback">Lütfen saat ve dakika seçin.</div>
                        </div>
                        <div class="field">
                            <label for="notes" class="form-label">Not <span class="ink-3">(isteğe bağlı)</span></label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                        <p class="form-text mb-0">İleri tarihli randevularda danışana randevunun oluşturulduğu SMS’le bildirilir.</p>
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-primary">Randevuyu kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Arama Sonuçları Modal -->
    <div class="modal fade" id="searchResultsModal" tabindex="-1" aria-labelledby="searchResultsTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="searchResultsTitle">Randevular</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div id="searchResults">
                        <!-- Arama sonuçları buraya dinamik olarak eklenecek -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Randevuları global değişkene aktar
    window.appointments = <?php echo json_encode($calendar_appointments, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        var addModal = document.getElementById('addAppointmentModal');
        var clientSelect = document.getElementById('client');
        var newNameInput = document.getElementById('newClientName');
        var newPhoneInput = document.getElementById('newClientPhone');

        // Kayıtlı danışan / yeni danışan arasında geçiş
        function setClientMode(mode, prefillName) {
            var isNew = mode === 'new';
            addModal.querySelector('[data-client-existing]').hidden = isNew;
            addModal.querySelector('[data-client-new]').hidden = !isNew;
            clientSelect.required = !isNew;
            $(clientSelect).prop('disabled', isNew);
            newNameInput.disabled = !isNew;
            newPhoneInput.disabled = !isNew;
            if (isNew) {
                $(clientSelect).val(null).trigger('change');
                if (prefillName) newNameInput.value = prefillName;
                (prefillName ? newPhoneInput : newNameInput).focus();
            } else {
                newNameInput.value = '';
                newPhoneInput.value = '';
                newPhoneInput.setCustomValidity('');
            }
        }

        addModal.querySelectorAll('[data-client-mode]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                setClientMode(btn.getAttribute('data-client-mode'));
            });
        });
        addModal.addEventListener('hidden.bs.modal', function() {
            setClientMode('existing');
        });

        // Aramada bulunamayan isimden yeni danışan
        function searchTerm() {
            return ($('#addAppointmentModal .select2-search__field').val() || '').trim();
        }
        $(document).on('mousedown', '.js-new-client-from-search', function(e) {
            e.preventDefault();
        });
        $(document).on('click', '.js-new-client-from-search', function() {
            var term = searchTerm();
            $(clientSelect).select2('close');
            setClientMode('new', term);
        });

        var select2Options = {
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Danışan seçin veya arayın',
            allowClear: true,
            dropdownParent: $('#addAppointmentModal'),
            escapeMarkup: function(markup) {
                return markup;
            },
            language: {
                noResults: function() {
                    var term = searchTerm();
                    var button = $('<button type="button" class="btn btn-sm btn-secondary w-100 js-new-client-from-search"></button>');
                    button.append($('<i class="bi bi-person-plus" aria-hidden="true"></i>'));
                    button.append(document.createTextNode(term ? ' “' + term + '” adıyla yeni danışan ekle' : ' Yeni danışan ekle'));
                    return button;
                },
                searching: function() {
                    return "Aranıyor...";
                }
            },
            templateResult: function(data) {
                if (!data.id) return data.text;
                return $('<span>').text(data.text);
            },
            templateSelection: function(data) {
                if (!data.id) return data.text;
                return $('<span>').text(data.text);
            }
        };

        // Select2'yi başlat - sadece yeni randevu ekleme modalı için
        $('#addAppointmentModal #client').select2(select2Options);

        // Modal açıldığında Select2'yi yeniden başlat
        $('#addAppointmentModal').on('shown.bs.modal', function () {
            $('#addAppointmentModal #client').select2(select2Options);
        });

        // Tarih boşsa bugünü öner
        $('#addAppointmentModal').on('show.bs.modal', function () {
            var dateInput = document.getElementById('date');
            if (dateInput && !dateInput.value) {
                var t = new Date();
                dateInput.value = t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0');
            }
        });
    });
    </script>

<?php include 'includes/footer.php'; ?>
