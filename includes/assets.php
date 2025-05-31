<?php
/**
 * Ortak CSS ve JavaScript Dosyaları
 * Cache busting özelliği ile
 */

// Cache busting için version
$assetsVersion = '1.0.0';

// Geliştirme modunda her seferinde yeni timestamp kullan
if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE === true) {
    $assetsVersion = time();
}

// CSS dosyalarını dahil et
function includeCSS($files, $version) {
    foreach ($files as $file) {
        echo '<link rel="stylesheet" href="' . $file . '?v=' . $version . '">' . "\n";
    }
}

// JavaScript dosyalarını dahil et
function includeJS($files, $version) {
    foreach ($files as $file) {
        echo '<script src="' . $file . '?v=' . $version . '"></script>' . "\n";
    }
}

// Ortak CSS dosyaları
$commonCSS = [
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css',
    'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
    'https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css',
    'assets/css/style.css'
];

// Ortak JavaScript dosyaları (footer'da yüklenecek)
$commonJS = [
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
    'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
    'assets/js/toast.js',
    'assets/js/script.js'
];

// Sayfa spesifik CSS dosyaları (isteğe bağlı)
$pageSpecificCSS = [];

// Sayfa spesifik JavaScript dosyaları (isteğe bağlı)
$pageSpecificJS = [];

// Sayfa adını tespit et
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Sayfa spesifik dosyaları ekle
switch ($currentPage) {
    case 'dashboard':
        $pageSpecificJS[] = 'assets/js/dashboard.js';
        break;
    case 'user-management':
        $pageSpecificJS[] = 'assets/js/user-management.js';
        break;
    case 'reports':
        // Chart.js'yi sadece reports sayfası için ekle
        $pageSpecificJS[] = 'https://cdn.jsdelivr.net/npm/chart.js';
        $pageSpecificJS[] = 'assets/js/reports.js';
        break;
    // Diğer sayfalar için gerektiğinde eklenebilir
}

/**
 * CSS dosyalarını head bölümünde dahil et
 */
function renderCSS() {
    global $commonCSS, $pageSpecificCSS, $assetsVersion;
    includeCSS($commonCSS, $assetsVersion);
    if (!empty($pageSpecificCSS)) {
        includeCSS($pageSpecificCSS, $assetsVersion);
    }
}

/**
 * JavaScript dosyalarını footer'da dahil et
 */
function renderJS() {
    global $commonJS, $pageSpecificJS, $assetsVersion;
    includeJS($commonJS, $assetsVersion);
    if (!empty($pageSpecificJS)) {
        includeJS($pageSpecificJS, $assetsVersion);
    }
}

/**
 * Inline JavaScript için session mesajlarını hazırla
 */
function renderSessionMessages() {
    $messages = [];
    
    if (isset($_SESSION['success'])) {
        $messages[] = "window.sessionSuccess = '" . addslashes($_SESSION['success']) . "';";
        unset($_SESSION['success']);
    }
    
    if (isset($_SESSION['error'])) {
        $messages[] = "window.sessionError = '" . addslashes($_SESSION['error']) . "';";
        unset($_SESSION['error']);
    }
    
    if (isset($_SESSION['warning'])) {
        $messages[] = "window.sessionWarning = '" . addslashes($_SESSION['warning']) . "';";
        unset($_SESSION['warning']);
    }
    
    if (!empty($messages)) {
        echo '<script>' . "\n";
        foreach ($messages as $message) {
            echo '    ' . $message . "\n";
        }
        echo '    document.addEventListener("DOMContentLoaded", function() {' . "\n";
        echo '        if (window.sessionSuccess) window.showToastMessage(window.sessionSuccess, "success");' . "\n";
        echo '        if (window.sessionError) window.showToastMessage(window.sessionError, "error");' . "\n";
        echo '        if (window.sessionWarning) window.showToastMessage(window.sessionWarning, "warning");' . "\n";
        echo '    });' . "\n";
        echo '</script>' . "\n";
    }
}

/**
 * PWA ve meta tag'leri dahil et
 */
function renderPWAHeaders() {
    global $assetsVersion;
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
    echo '<meta name="theme-color" content="#212529">' . "\n";
    echo '<link rel="manifest" href="manifest.json?v=' . $assetsVersion . '">' . "\n";
    echo '<link rel="icon" type="image/x-icon" href="assets/images/favicon.ico?v=' . $assetsVersion . '">' . "\n";
    echo '<link rel="apple-touch-icon" href="assets/images/icon-192x192.png?v=' . $assetsVersion . '">' . "\n";
}

/**
 * Cache kontrolü için meta tag'ler
 */
function renderCacheHeaders() {
    global $assetsVersion;
    echo '<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">' . "\n";
    echo '<meta http-equiv="Pragma" content="no-cache">' . "\n";
    echo '<meta http-equiv="Expires" content="0">' . "\n";
    echo '<meta name="app-version" content="' . $assetsVersion . '">' . "\n";
} 