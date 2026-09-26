<?php
/**
 * Yerel geliştirme yönlendiricisi (Apache .htaccess'in uzantısız URL davranışını taklit eder).
 * Çalıştırma: C:\xampp\php\php.exe -S localhost:8091 -t . dev/router.php
 * Bu sunucuda config/env.php otomatik olarak dev/env.dev dosyasını kullanır.
 */
$root = realpath(__DIR__ . '/..');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// Geliştirme dosyalarını, dökümleri ve gizli dosyaları servis etme
if (preg_match('#^/(dev|sql|database|logs|graft|\.)#', $path) || preg_match('#/(env|env\.example)$#', $path)) {
    http_response_code(403);
    exit('Erişim yok');
}

$target = $root . $path;

if ($path !== '/' && is_file($target)) {
    if (substr($target, -4) !== '.php') {
        return false; // Statik dosyayı PHP sunucusu servis etsin
    }
    $script = $target;
} elseif ($path !== '/' && is_file($target . '.php')) {
    $script = $target . '.php';
} elseif ($path === '/' || $path === '') {
    $script = $root . DIRECTORY_SEPARATOR . 'index.php';
} else {
    http_response_code(404);
    exit('Bulunamadı');
}

$relative = str_replace('\\', '/', substr($script, strlen($root)));
$_SERVER['SCRIPT_NAME'] = $relative;
$_SERVER['PHP_SELF'] = $relative;
$_SERVER['SCRIPT_FILENAME'] = $script;

chdir(dirname($script));
require $script;
