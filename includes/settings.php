<?php
/**
 * Uygulama ayarları (yönetim panelinden değiştirilebilir değerler).
 *
 * Değerler `app_settings` tablosunda anahtar/değer olarak tutulur. Tablo yoksa ilk
 * kullanımda otomatik oluşturulur (elle kurulum için: database/migrations/2026-09-26_app_settings.sql).
 */

require_once __DIR__ . '/../config/env.php';

/**
 * Varsayılan değerler (tabloda kayıt yoksa kullanılır).
 */
function appSettingDefaults() {
    return [
        // Ödeme alırken tutar alanına gelen varsayılan seans ücreti (₺)
        'session_fee' => '3000',
        // Seans süresi (dakika) — Bugün ekranındaki canlı seans halkası bunu kullanır
        'session_minutes' => (string) EnvConfig::getInt('SEANS_SURESI_DK', 50),
    ];
}

function ensureSettingsTable(PDO $db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(64) NOT NULL,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Tüm ayarlar (varsayılanlarla birleştirilmiş). İstek başına bir kez okunur.
 */
function getSettings(PDO $db) {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $values = appSettingDefaults();
    try {
        $rows = $db->query("SELECT setting_key, setting_value FROM app_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($rows as $key => $value) {
            if (array_key_exists($key, $values)) {
                $values[$key] = $value;
            }
        }
    } catch (PDOException $e) {
        // Tablo henüz yoksa oluştur; varsayılanlarla devam et
        if ($e->getCode() === '42S02') {
            try {
                ensureSettingsTable($db);
            } catch (PDOException $ignored) {
                error_log('app_settings tablosu oluşturulamadı: ' . $ignored->getMessage());
            }
        } else {
            error_log('Ayarlar okunamadı: ' . $e->getMessage());
        }
    }

    return $cache = $values;
}

function getSetting(PDO $db, $key) {
    $settings = getSettings($db);
    return $settings[$key] ?? null;
}

/** Varsayılan seans ücreti (₺) */
function getSessionFee(PDO $db) {
    return (float) getSetting($db, 'session_fee');
}

/** Seans süresi (dakika) */
function getSessionMinutes(PDO $db) {
    return max(10, (int) getSetting($db, 'session_minutes'));
}

/**
 * Ayarları kaydet (yalnızca bilinen anahtarlar).
 */
function saveSettings(PDO $db, array $values) {
    ensureSettingsTable($db);
    $allowed = array_keys(appSettingDefaults());
    $stmt = $db->prepare("
        INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    foreach ($values as $key => $value) {
        if (in_array($key, $allowed, true)) {
            $stmt->execute([$key, (string) $value]);
        }
    }
}

/**
 * Tutarı form alanı için yaz: 3000 → "3000", 2750.5 → "2750.50"
 */
function feeInputValue($amount) {
    $amount = (float) $amount;
    return floor($amount) == $amount ? (string) (int) $amount : number_format($amount, 2, '.', '');
}
