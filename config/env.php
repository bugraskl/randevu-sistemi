<?php
/**
 * Environment Configuration Loader
 * Bu dosya environment değişkenlerini yükler ve uygulamada kullanılabilir hale getirir
 */

class EnvConfig {
    private static $loaded = false;
    private static $config = [];
    
    /**
     * Environment dosyasını yükle
     */
    public static function load($envFile = null) {
        if (self::$loaded) {
            return;
        }
        
        // Varsayılan env dosyası konumu
        if ($envFile === null) {
            $envFile = dirname(__DIR__) . '/env';
        }
        
        // Eğer env dosyası yoksa env.example'ı kullan
        if (!file_exists($envFile)) {
            $envFile = dirname(__DIR__) . '/env.example';
        }
        
        if (!file_exists($envFile)) {
            throw new Exception('Environment dosyası bulunamadı: ' . $envFile);
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Yorum satırlarını atla
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // KEY=VALUE formatını parse et
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Tırnak işaretlerini temizle
                $value = trim($value, '"\'');
                
                // Değeri sakla
                self::$config[$key] = $value;
                
                // Global $_ENV ve putenv olarak da tanımla
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Environment değişkeni al
     */
    public static function get($key, $default = null) {
        self::load();
        return isset(self::$config[$key]) ? self::$config[$key] : $default;
    }
    
    /**
     * Boolean değer al
     */
    public static function getBool($key, $default = false) {
        $value = self::get($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
    }
    
    /**
     * Integer değer al
     */
    public static function getInt($key, $default = 0) {
        return (int) self::get($key, $default);
    }
    
    /**
     * Tüm konfigürasyonu döndür
     */
    public static function all() {
        self::load();
        return self::$config;
    }
    
    /**
     * Ortam tipini kontrol et
     */
    public static function isProduction() {
        return self::get('APP_ENV') === 'production';
    }
    
    public static function isDevelopment() {
        return self::get('APP_ENV') === 'development';
    }
    
    public static function isDebug() {
        return self::getBool('APP_DEBUG', false);
    }
}

// Otomatik yükleme
EnvConfig::load();

// Zaman dilimini ayarla
$timezone = EnvConfig::get('APP_TIMEZONE', 'Europe/Istanbul');
date_default_timezone_set($timezone);
?> 