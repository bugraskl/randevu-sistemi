-- SMS Link ile Randevu Teyit Sistemi
-- Veritabanı Güncellemeleri

-- Appointments tablosuna token alanları ekle
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS confirmation_token VARCHAR(16) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS token_expires_at DATETIME DEFAULT NULL;

-- Token için index ekle (hızlı arama için)
CREATE INDEX IF NOT EXISTS idx_confirmation_token ON appointments(confirmation_token);

-- Hatırlatma SMS şablonunu güncelle (teyit linki ile)
UPDATE sms_templates 
SET template_text = 'Sayin {danisan_adi}, yarin saat {saat} icin randevunuz bulunmaktadir. Teyit veya iptal icin: {teyit_linki}'
WHERE template_name = 'randevu_hatirlatma';

-- Admin bildirim şablonu ekle
INSERT INTO sms_templates (template_name, template_text) 
VALUES ('randevu_iptal_bildirim', '{danisan_adi} isimli danisman {tarih} {saat} randevusunu IPTAL etti.')
ON DUPLICATE KEY UPDATE template_text = VALUES(template_text);
