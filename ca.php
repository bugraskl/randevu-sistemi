<?php
/**
 * Randevu Teyit Sayfası
 * Danışanların SMS'teki link ile randevularını onaylaması veya iptal etmesi için
 * Bu sayfa giriş gerektirmez (public)
 */

require_once 'config/database.php';
require_once 'includes/sms.php';

// Token'ı URL'den al (kısa parametre: t)
$token = $_GET['t'] ?? '';
$action = $_POST['action'] ?? '';

// Hata ve başarı mesajları
$error = '';
$success = '';
$appointment = null;
$client = null;
$processed = false;

// Token kontrolü
if (empty($token)) {
    $error = 'Geçersiz veya eksik teyit linki.';
} else {
    try {
        // Token ile randevuyu bul
        $stmt = $db->prepare("
            SELECT a.*, c.name as client_name, c.phone as client_phone
            FROM appointments a
            JOIN clients c ON a.client_id = c.id
            WHERE a.confirmation_token = ?
            AND a.token_expires_at > NOW()
            AND a.status NOT IN ('iptal', 'tamamlandı')
        ");
        $stmt->execute([$token]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            $error = 'Bu teyit linki geçersiz, süresi dolmuş veya randevu zaten işlenmiş.';
        } else {
            $client = $appointment['client_name'];
            
            // Form gönderildi mi?
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action)) {
                if ($action === 'cancel') {
                    // Randevuyu iptal et
                    $stmt = $db->prepare("UPDATE appointments SET status = 'iptal' WHERE id = ?");
                    $stmt->execute([$appointment['id']]);
                    
                    // Yöneticiye SMS gönder
                    $adminPhone = EnvConfig::get('ADMIN_NOTIFICATION_PHONE', '');
                    $template = getSMSTemplate('randevu_iptal_bildirim');
                    
                    if ($template) {
                        $message = str_replace(
                            ['{danisan_adi}', '{tarih}', '{saat}'],
                            [
                                $appointment['client_name'],
                                date('d.m.Y', strtotime($appointment['appointment_date'])),
                                date('H:i', strtotime($appointment['appointment_time']))
                            ],
                            $template
                        );
                    } else {
                        $message = "{$appointment['client_name']} isimli danışan " .
                                   date('d.m.Y', strtotime($appointment['appointment_date'])) . " " .
                                   date('H:i', strtotime($appointment['appointment_time'])) . 
                                   " randevusunu İPTAL etti.";
                    }
                    
                    sendSMS($adminPhone, $message);
                    
                    $success = 'Randevunuz iptal edilmiştir. Yeni bir randevu almak için bizimle iletişime geçebilirsiniz.';
                    $processed = true;
                    
                } elseif ($action === 'confirm') {
                    // Sadece teşekkür mesajı göster (veritabanında değişiklik yapmıyoruz)
                    $success = 'Teşekkür ederiz! Randevunuzda görüşmek üzere.';
                    $processed = true;
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
        error_log("Randevu teyit hatası: " . $e->getMessage());
    }
}

// Türkçe gün isimleri
$gunler = [
    'Monday' => 'Pazartesi',
    'Tuesday' => 'Salı',
    'Wednesday' => 'Çarşamba',
    'Thursday' => 'Perşembe',
    'Friday' => 'Cuma',
    'Saturday' => 'Cumartesi',
    'Sunday' => 'Pazar'
];

// --- Görünüm için yardımcılar (işleme mantığına dokunmaz) ---
$aylar = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
$aptTime = '';
$aptDateText = '';
$aptDatetime = '';
if ($appointment) {
    $date = new DateTime($appointment['appointment_date']);
    $aptTime = date('H:i', strtotime($appointment['appointment_time']));
    $aptDateText = $date->format('j') . ' ' . $aylar[(int) $date->format('n')] . ' ' . $date->format('Y') . ', ' . $gunler[$date->format('l')];
    $aptDatetime = $date->format('Y-m-d') . 'T' . $aptTime;
}
$wasCancelled = $processed && $action === 'cancel';
$styleVersion = @filemtime(__DIR__ . '/assets/css/style.css') ?: '1';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Randevu teyidi</title>
    <link rel="icon" type="image/svg+xml" href="assets/icons/favicon.svg">
    <link rel="preload" href="assets/fonts/figtree-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo htmlspecialchars((string) $styleVersion); ?>">
    <script>
        // Tarayıcı çubuğu üstteki ana renk bölgeyle birleşsin (renk tokendan okunur)
        (function () {
            var c = getComputedStyle(document.documentElement).getPropertyValue('--wool').trim();
            if (c) {
                var m = document.createElement('meta');
                m.name = 'theme-color';
                m.content = c;
                document.head.appendChild(m);
            }
        })();
    </script>
    <style>
        /* Randevu teyidi — yalnızca tokenlar; uygulama iskeleti yok, yalnız açık tema */
        .confirm {
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
        }

        .confirm-hero {
            padding: calc(var(--s-7) + env(safe-area-inset-top, 0px)) var(--s-5) var(--s-6);
            border-radius: 0 0 var(--r-lg) var(--r-lg);
        }

        .confirm-title {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 650;
            letter-spacing: -0.01em;
        }

        .confirm-who {
            margin: 2px 0 0;
            font-size: 0.9375rem;
        }

        .confirm-when {
            margin-top: var(--s-5);
            padding-top: var(--s-5);
            border-top: 1px solid var(--wool-line);
        }

        .confirm-hero.is-cancelled .now-time {
            color: var(--on-wool-2);
            text-decoration: line-through;
            text-decoration-thickness: 3px;
        }

        .confirm-body {
            padding: var(--s-5) var(--gutter) calc(var(--s-7) + env(safe-area-inset-bottom, 0px));
        }

        .confirm-lead {
            margin: 0 0 var(--s-1);
            font-size: 1.0625rem;
            font-weight: 650;
            color: var(--ink);
        }

        .confirm-state {
            margin: 0 0 var(--s-3);
        }

        .confirm-actions {
            display: grid;
            gap: var(--s-3);
            margin-top: var(--s-5);
        }

        .confirm-foot {
            margin: var(--s-4) 0 0;
            font-size: 0.875rem;
            color: var(--ink-3);
        }

        @media (min-width: 560px) {
            .confirm {
                padding: var(--s-8) var(--gutter);
            }

            .confirm-hero {
                padding: var(--s-7) var(--s-6);
                border-radius: var(--r-lg);
            }

            .confirm-body {
                padding: var(--s-4) 0 0;
            }
        }
    </style>
</head>
<body>
    <main class="confirm">
        <header class="confirm-hero wool<?php echo $wasCancelled ? ' is-cancelled' : ''; ?>">
            <h1 class="confirm-title">Randevu teyidi</h1>
            <?php $practitionerName = EnvConfig::get('PRACTITIONER_NAME', ''); ?>
            <?php if ($practitionerName !== ''): ?>
            <p class="confirm-who"><?php echo htmlspecialchars($practitionerName); ?></p>
            <?php endif; ?>
            <?php if ($appointment): ?>
            <div class="confirm-when">
                <time class="now-time" datetime="<?php echo htmlspecialchars($aptDatetime); ?>"><?php echo htmlspecialchars($aptTime); ?></time>
                <span class="now-name"><?php echo htmlspecialchars($aptDateText); ?></span>
            </div>
            <?php endif; ?>
        </header>

        <div class="confirm-body">
            <?php if ($error): ?>
            <section class="panel panel-pad" role="status">
                <p class="confirm-lead">Randevu bu bağlantıyla açılamadı</p>
                <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                <p class="confirm-foot">Randevunuzla ilgili bir değişiklik için lütfen doğrudan bizimle iletişime geçin.</p>
            </section>
            <?php elseif ($processed): ?>
            <section class="panel panel-pad" role="status">
                <p class="confirm-state">
                    <?php if ($wasCancelled): ?>
                    <span class="mark mark-cancelled">Randevu iptal edildi</span>
                    <?php else: ?>
                    <span class="mark mark-confirmed">Yanıtınız alındı</span>
                    <?php endif; ?>
                </p>
                <p class="confirm-lead mb-0"><?php echo htmlspecialchars($success); ?></p>
            </section>
            <?php elseif ($appointment): ?>
            <section class="panel panel-pad">
                <p class="confirm-lead">Merhaba <?php echo htmlspecialchars($client); ?>,</p>
                <p class="mb-0">Yukarıdaki randevunuza gelebilecek misiniz? Lütfen aşağıdan yanıtlayın.</p>
                <form method="POST" class="confirm-actions">
                    <button type="submit" name="action" value="confirm" class="btn btn-primary btn-lg btn-block">
                        Randevumu onaylıyorum
                    </button>
                    <button type="submit" name="action" value="cancel" class="btn btn-outline-danger btn-lg btn-block">
                        Gelemeyeceğim, iptal et
                    </button>
                </form>
                <p class="confirm-foot">Yanıt vermezseniz randevunuz geçerli kabul edilecektir.</p>
            </section>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
