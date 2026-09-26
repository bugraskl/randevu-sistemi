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

// Admin rolü kontrolü
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error'] = 'Bu sayfaya erişim yetkiniz bulunmuyor.';
    header('Location: dashboard');
    exit();
}

$settings = getSettings($db);
$sessionFee = (float) $settings['session_fee'];
$sessionMinutes = (int) $settings['session_minutes'];

$pageTitle = 'Seans Ayarları';
$pageSubtitle = 'Varsayılan ücret ve seans süresi';

include 'includes/header.php';
?>
<body class="<?php echo $themeClass; ?>" data-page="app-settings">
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main id="content" tabindex="-1">
            <?php include 'includes/topbar.php'; ?>

            <div class="page page-narrow">
                <form action="process/save-settings" method="POST" class="needs-validation" novalidate>
                    <section class="panel panel-pad" aria-labelledby="feeTitle">
                        <h2 class="section-title" id="feeTitle">Seans ücreti</h2>
                        <p class="mt-1">Ödeme alırken tutar alanına bu ücret otomatik gelir. Ödeme sırasında yine değiştirebilirsiniz; daha önce alınan ödemeler etkilenmez.</p>
                        <div class="field mb-0">
                            <label for="session_fee" class="form-label">Varsayılan ücret (₺)</label>
                            <input type="number" inputmode="decimal" class="form-control tnum" id="session_fee" name="session_fee"
                                   min="1" max="1000000" step="0.01" value="<?php echo htmlspecialchars(feeInputValue($sessionFee)); ?>" required>
                            <div class="invalid-feedback">1 ile 1.000.000 ₺ arasında bir tutar girin.</div>
                            <div class="form-text">Şu an: <?php echo '₺' . number_format($sessionFee, floor($sessionFee) == $sessionFee ? 0 : 2, ',', '.'); ?></div>
                        </div>
                    </section>

                    <section class="panel panel-pad mt-4" aria-labelledby="durationTitle">
                        <h2 class="section-title" id="durationTitle">Seans süresi</h2>
                        <p class="mt-1">Bugün ekranındaki seans halkası ve “seansta” durumu bu süreye göre hesaplanır.</p>
                        <div class="field mb-0">
                            <label for="session_minutes" class="form-label">Süre (dakika)</label>
                            <input type="number" inputmode="numeric" class="form-control tnum" id="session_minutes" name="session_minutes"
                                   min="10" max="240" step="5" value="<?php echo $sessionMinutes; ?>" required>
                            <div class="invalid-feedback">10 ile 240 dakika arasında bir süre girin.</div>
                        </div>
                    </section>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg btn-block">Ayarları kaydet</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

<?php include 'includes/footer.php'; ?>
