<?php
require_once __DIR__ . '/env.php';

try {
    // Environment değişkenlerinden database bilgilerini al
    $host = EnvConfig::get('DB_HOST', 'localhost');
    $dbname = EnvConfig::get('DB_NAME');
    $username = EnvConfig::get('DB_USERNAME');
    $password = EnvConfig::get('DB_PASSWORD', '');
    $charset = EnvConfig::get('DB_CHARSET', 'utf8mb4');
    
    // Gerekli alanların kontrolü
    if (empty($dbname)) {
        throw new Exception('Veritabanı adı belirtilmemiş. Lütfen env dosyasında DB_NAME değerini kontrol edin.');
    }
    
    if (empty($username)) {
        throw new Exception('Veritabanı kullanıcı adı belirtilmemiş. Lütfen env dosyasında DB_USERNAME değerini kontrol edin.');
    }

    // PDO bağlantısını oluştur
    $db = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=$charset",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Türkiye zaman dilimini ayarla
    date_default_timezone_set('Europe/Istanbul');
} catch(PDOException $e) {
    $errorMessage = "Veritabanı bağlantı hatası: " . $e->getMessage();
    
    // Debug modu açıksa detaylı hata göster
    if (EnvConfig::isDebug()) {
        $errorMessage .= "\nHost: $host\nDatabase: $dbname\nUsername: $username";
    }
    
    // Session varsa error set et, yoksa direkt göster
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['error'] = $errorMessage;
        header('Location: ../index.php');
    } else {
        die($errorMessage);
    }
    exit();
} catch(Exception $e) {
    $errorMessage = "Konfigürasyon hatası: " . $e->getMessage();
    
    // Session varsa error set et, yoksa direkt göster
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['error'] = $errorMessage;
        header('Location: ../index.php');
    } else {
        die($errorMessage);
    }
    exit();
}
?> 