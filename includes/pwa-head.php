<?php
// PWA Meta Etiketleri
?>
<!-- PWA Meta Etiketleri -->
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#212529">
<meta name="description" content="Randevu Yönetim Sistemi">
<meta name="application-name" content="Randevu Yönetim Sistemi">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<meta name="apple-mobile-web-app-title" content="Randevu Yönetim Sistemi">

<!-- Windows için meta etiketleri -->
<meta name="msapplication-TileColor" content="#212529">
<meta name="msapplication-TileImage" content="/assets/icons/icon-144x144.png">
<meta name="msapplication-config" content="/browserconfig.xml">

<!-- İkonlar -->
<link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-icon.png">
<link rel="apple-touch-icon" sizes="152x152" href="/assets/icons/icon-152x152.png">
<link rel="apple-touch-icon" sizes="144x144" href="/assets/icons/icon-144x144.png">
<link rel="apple-touch-icon" sizes="120x120" href="/assets/icons/icon-120x120.png">
<link rel="apple-touch-icon" sizes="114x114" href="/assets/icons/icon-114x114.png">
<link rel="apple-touch-icon" sizes="76x76" href="/assets/icons/icon-76x76.png">
<link rel="apple-touch-icon" sizes="72x72" href="/assets/icons/icon-72x72.png">
<link rel="apple-touch-icon" sizes="60x60" href="/assets/icons/icon-60x60.png">
<link rel="apple-touch-icon" sizes="57x57" href="/assets/icons/icon-57x57.png">
<link rel="mask-icon" href="/assets/icons/safari-pinned-tab.svg" color="#212529">

<!-- Splash Screen -->
<link rel="apple-touch-startup-image" href="/assets/splash/apple-splash-2048-2732.png" media="(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/assets/splash/apple-splash-1668-2388.png" media="(device-width: 834px) and (device-height: 1194px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/assets/splash/apple-splash-1536-2048.png" media="(device-width: 768px) and (device-height: 1024px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/assets/splash/apple-splash-1125-2436.png" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/assets/splash/apple-splash-1242-2688.png" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/assets/splash/apple-splash-828-1792.png" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/assets/splash/apple-splash-1242-2208.png" media="(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/assets/splash/apple-splash-750-1334.png" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image" href="/assets/splash/apple-splash-640-1136.png" media="(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">

<!-- Service Worker -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                // Service worker güncellemesi varsa
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // Yeni versiyon hazır, sayfayı yenile
                            window.location.reload();
                        }
                    });
                });
            })
            .catch(error => {
                console.error('Service Worker registration failed:', error);
            });
    });
}
</script> 