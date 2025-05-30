<?php
/**
 * Application Configuration
 * Bu dosya uygulamanın ana konfigürasyon değerlerini içerir
 */

require_once __DIR__ . '/env.php';

// Uygulama konfigürasyonu
$appConfig = [
    'name' => EnvConfig::get('APP_NAME', 'Randevu Yönetim Sistemi'),
    'env' => EnvConfig::get('APP_ENV', 'development'),
    'debug' => EnvConfig::getBool('APP_DEBUG', false),
    'url' => EnvConfig::get('APP_URL', 'http://localhost'),
    'timezone' => EnvConfig::get('APP_TIMEZONE', 'Europe/Istanbul'),
    'charset' => 'utf8mb4',
];

// Database konfigürasyonu
$dbConfig = [
    'host' => EnvConfig::get('DB_HOST', 'localhost'),
    'database' => EnvConfig::get('DB_NAME'),
    'username' => EnvConfig::get('DB_USERNAME'),
    'password' => EnvConfig::get('DB_PASSWORD', ''),
    'charset' => EnvConfig::get('DB_CHARSET', 'utf8mb4'),
];

// SMS konfigürasyonu
$smsConfig = [
    'netgsm' => [
        'username' => EnvConfig::get('NETGSM_USERNAME'),
        'password' => EnvConfig::get('NETGSM_PASSWORD'),
        'header' => EnvConfig::get('NETGSM_HEADER'),
    ],
    'debug' => EnvConfig::getBool('SMS_DEBUG', false),
    'security_token' => EnvConfig::get('SMS_SECURITY_TOKEN'),
];

// Security konfigürasyonu
$securityConfig = [
    'key' => EnvConfig::get('SECURITY_KEY'),
    'session_lifetime' => EnvConfig::getInt('SESSION_LIFETIME', 1440), // dakika
];

// Logging konfigürasyonu
$logConfig = [
    'enabled' => EnvConfig::getBool('ENABLE_LOGGING', true),
    'path' => dirname(__DIR__) . '/logs',
];

/**
 * Konfigürasyon değeri al
 */
function config($key, $default = null) {
    global $appConfig, $dbConfig, $smsConfig, $securityConfig, $logConfig;
    
    $keys = explode('.', $key);
    
    switch($keys[0]) {
        case 'app':
            $config = $appConfig;
            break;
        case 'database':
            $config = $dbConfig;
            break;
        case 'sms':
            $config = $smsConfig;
            break;
        case 'security':
            $config = $securityConfig;
            break;
        case 'log':
            $config = $logConfig;
            break;
        default:
            return $default;
    }
    
    // Nested key desteği (örn: sms.netgsm.username)
    for ($i = 1; $i < count($keys); $i++) {
        if (isset($config[$keys[$i]])) {
            $config = $config[$keys[$i]];
        } else {
            return $default;
        }
    }
    
    return $config;
}

/**
 * Production ortamında mı kontrol et
 */
function isProduction() {
    return config('app.env') === 'production';
}

/**
 * Development ortamında mı kontrol et
 */
function isDevelopment() {
    return config('app.env') === 'development';
}

/**
 * Debug modu açık mı kontrol et
 */
function isDebugMode() {
    return config('app.debug', false);
}
?> 