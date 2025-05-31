<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Randevu Yönetim Sistemi</title>
    
    <?php 
    // Assets dosyasını dahil et
    require_once 'includes/assets.php';
    
    // Cache kontrol header'ları
    renderCacheHeaders();
    
    // PWA ve meta tag'ler
    renderPWAHeaders();
    
    // CSS dosyalarını dahil et
    renderCSS();
    ?>
</head>
<body> 