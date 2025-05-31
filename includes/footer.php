<?php
// Assets dosyasını dahil et (eğer dahil edilmemişse)
if (!function_exists('renderJS')) {
    require_once 'includes/assets.php';
}

// JavaScript dosyalarını dahil et
renderJS();

// Session mesajlarını render et
renderSessionMessages();
?>

<!-- Service Worker için -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('sw.js')
            .then(function(registration) {
                console.log('SW registration successful');
            }, function(err) {
                console.log('SW registration failed');
            });
    });
}
</script>

</body>
</html> 