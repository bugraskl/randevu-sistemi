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

// Şablonları veritabanından çek
try {
    $stmt = $db->query("SELECT * FROM sms_templates ORDER BY id");
    $templates = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "SMS şablonları alınırken bir hata oluştu: " . $e->getMessage();
    $templates = [];
}

// Şablon güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_template'])) {
    // Debug: POST verilerini kontrol et
    error_log("SMS Settings POST Data: " . print_r($_POST, true));
    
    // Veri kontrolü
    if (empty($_POST['template_text']) || empty($_POST['template_id'])) {
        $_SESSION['error'] = "Gerekli alanlar eksik.";
        header('Location: sms-settings');
        exit();
    }
    
    try {
        // Debug: SQL sorgusunu logla
        error_log("SMS Template Update - ID: " . $_POST['template_id'] . ", Text: " . $_POST['template_text']);
        
        $stmt = $db->prepare("UPDATE sms_templates SET template_text = ? WHERE id = ?");
        $result = $stmt->execute([$_POST['template_text'], $_POST['template_id']]);
        
        // Debug: Etkilenen satır sayısını kontrol et
        $rowCount = $stmt->rowCount();
        error_log("SMS Template Update - Affected rows: " . $rowCount);
        
        if ($rowCount > 0) {
            $_SESSION['success'] = "SMS şablonu başarıyla güncellendi.";
        } else {
            $_SESSION['warning'] = "Herhangi bir değişiklik yapılmadı veya şablon bulunamadı.";
        }
        
        header('Location: sms-settings');
        exit();
    } catch(PDOException $e) {
        error_log("SMS Template Update Error: " . $e->getMessage());
        $_SESSION['error'] = "SMS şablonu güncellenirken bir hata oluştu: " . $e->getMessage();
    }
}

// --- Görünüm için yardımcılar (işleme mantığına dokunmaz) ---
$smsEnabled = EnvConfig::getBool('SMS_ENABLED', true);

// Şablon adı → başlık ve ne zaman gönderildiği
$templateInfo = [
    'randevu_olusturma' => [
        'title' => 'Randevu oluşturma',
        'note' => 'Yeni randevu eklendiğinde ya da randevunun tarihi veya saati değiştiğinde danışana gider.',
    ],
    'randevu_hatirlatma' => [
        'title' => 'Randevu hatırlatma',
        'note' => 'Randevudan bir gün önce danışana gider. Kullanılabilen değişkenler: <code class="sms-var">{danisan_adi}</code>, <code class="sms-var">{tarih}</code>, <code class="sms-var">{saat}</code>.',
    ],
    'randevu_iptal_bildirim' => [
        'title' => 'İptal bildirimi',
        'note' => 'Danışan SMS’teki bağlantıdan randevusunu iptal ettiğinde size gelir.',
    ],
];

$pageTitle = 'SMS Ayarları';
$pageSubtitle = 'Danışanlara giden mesaj şablonları';

// Header'ı dahil et
include 'includes/header.php';
?>
<body class="<?php echo $themeClass; ?>" data-page="sms-settings">
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main id="content" tabindex="-1">
            <?php include 'includes/topbar.php'; ?>

            <div class="page page-narrow">
                <?php if (!$smsEnabled): ?>
                <div class="alert alert-info mb-0" role="status">
                    SMS gönderimi şu an kapalı. Şablonlarda yaptığınız değişiklikler kaydedilir, ancak gönderim açılana kadar danışanlara mesaj gitmez.
                </div>
                <?php endif; ?>

                <!-- Kullanılabilir değişkenler -->
                <section class="section" aria-labelledby="smsVarsTitle">
                    <div class="section-head">
                        <h2 class="section-title" id="smsVarsTitle">Değişkenler</h2>
                    </div>
                    <div class="panel panel-pad">
                        <p class="sms-vars-intro">Metne yazdığınız bu alanlar, SMS gönderilirken randevunun bilgisiyle değişir.</p>
                        <dl class="sms-vars">
                            <div>
                                <dt><code class="sms-var">{danisan_adi}</code></dt>
                                <dd>Danışanın adı</dd>
                            </div>
                            <div>
                                <dt><code class="sms-var">{tarih}</code></dt>
                                <dd>Randevu tarihi</dd>
                            </div>
                            <div>
                                <dt><code class="sms-var">{saat}</code></dt>
                                <dd>Randevu saati</dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <?php if (empty($templates)): ?>
                <section class="section">
                    <div class="empty">
                        <p class="empty-title">SMS şablonu bulunamadı</p>
                        <p>Şablon kaydı olmadığında mesajlar sistemin varsayılan metniyle gönderilir.</p>
                    </div>
                </section>
                <?php endif; ?>

                <?php foreach ($templates as $template):
                    $tplId = (int) $template['id'];
                    $info = $templateInfo[$template['template_name']] ?? null;
                    $tplTitle = $info['title'] ?? ucfirst(str_replace('_', ' ', $template['template_name']));
                ?>
                <section class="section" aria-labelledby="tplTitle<?php echo $tplId; ?>">
                    <div class="section-head">
                        <h2 class="section-title" id="tplTitle<?php echo $tplId; ?>"><?php echo htmlspecialchars($tplTitle); ?></h2>
                    </div>
                    <form method="POST" class="sms-template-form panel panel-pad">
                        <input type="hidden" name="template_id" value="<?php echo $tplId; ?>">
                        <?php if ($info): ?>
                        <p class="sms-note" id="tplNote<?php echo $tplId; ?>"><?php echo $info['note']; ?></p>
                        <?php endif; ?>
                        <div class="field">
                            <label for="tplText<?php echo $tplId; ?>" class="form-label">Mesaj metni</label>
                            <textarea class="form-control" id="tplText<?php echo $tplId; ?>" name="template_text" rows="4" required<?php echo $info ? ' aria-describedby="tplNote' . $tplId . '"' : ''; ?>><?php echo htmlspecialchars($template['template_text']); ?></textarea>
                        </div>
                        <div class="d-grid d-sm-flex justify-content-sm-end">
                            <button type="submit" name="update_template" class="btn btn-primary">
                                Şablonu kaydet
                            </button>
                        </div>
                    </form>
                </section>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

<?php include 'includes/footer.php'; ?>
