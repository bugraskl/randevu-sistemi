-- Uygulama ayarları tablosu (Seans Ayarları sayfası).
-- Uygulama bu tabloyu ilk kullanımda kendisi oluşturur; veritabanı kullanıcısının
-- CREATE yetkisi yoksa bu dosyayı phpMyAdmin'den bir kez çalıştırın.

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(64) NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Başlangıç değerleri (varsa dokunmaz)
INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
    ('session_fee', '3000'),
    ('session_minutes', '50');
