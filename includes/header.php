<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title><?php echo !empty($pageTitle) ? htmlspecialchars($pageTitle) . ' · ' : ''; ?>Randevu Yönetim Sistemi</title>

    <?php
    // Assets dosyasını dahil et
    require_once 'includes/assets.php';

    // Cache kontrol header'ları
    renderCacheHeaders();

    // PWA ve meta tag'ler
    renderPWAHeaders($themeColor ?? null);

    // CSS dosyalarını dahil et
    renderCSS();
    ?>
</head>
