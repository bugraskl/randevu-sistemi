# Kurulum Kılavuzu

Bu belge, Randevu Yönetim Sistemi'nin ayrıntılı kurulum adımlarını içerir.

## Sistem Gereksinimleri

### Minimum Gereksinimler
- **PHP:** 7.4 veya üzeri (8.0+ önerilir)
- **MySQL:** 5.7 veya üzeri (8.0+ önerilir)
- **Web Sunucusu:** Apache 2.4+ veya Nginx 1.18+
- **PHP Uzantıları:**
  - PDO
  - PDO_MySQL
  - cURL
  - JSON
  - Session
  - mbstring

### Önerilen Sunucu Ayarları
- **Memory Limit:** 128MB minimum
- **Upload Max Filesize:** 32MB
- **Post Max Size:** 32MB
- **Max Execution Time:** 120 saniye

## Adım Adım Kurulum

### 1. Projeyi İndirin

#### Git ile indirme:
```bash
git clone https://github.com/bugraskl/randevu-sistemi.git
cd randevu-sistemi
```

#### ZIP dosyası ile indirme:
1. [GitHub sayfasından](https://github.com/bugraskl/randevu-sistemi) "Code" > "Download ZIP" seçin
2. İndirilen dosyayı web sunucunuzun klasörüne çıkarın

### 2. Environment Konfigürasyonu

```bash
cp env.example env
```

`env` dosyasını açın ve aşağıdaki değerleri kendi bilgilerinizle güncelleyin:

```env
# Uygulama Ayarları
APP_ENV=production
APP_DEBUG=false
APP_DOMAIN=yourdomain.com

# Veritabanı Ayarları
DB_HOST=localhost
DB_NAME=randevu_db
DB_USERNAME=your_db_username
DB_PASSWORD=your_db_password

# NetGSM SMS Ayarları
NETGSM_USERNAME=your_netgsm_username
NETGSM_PASSWORD=your_netgsm_password
NETGSM_HEADER=YOUR_COMPANY

# SMS Kontrol
SMS_ENABLED=true
SMS_DEBUG=false
SMS_SECURITY_TOKEN=your_secure_random_token_here
```

### 3. Veritabanı Kurulumu

#### MySQL'de veritabanı oluşturun:
```sql
CREATE DATABASE randevu_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### Kullanıcı oluşturun (opsiyonel):
```sql
CREATE USER 'randevu_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON randevu_db.* TO 'randevu_user'@'localhost';
FLUSH PRIVILEGES;
```

#### Tabloları import edin:
```bash
mysql -u username -p randevu_db < database/database.sql
```

### 4. Web Sunucusu Konfigürasyonu

#### Apache Konfigürasyonu

Proje `.htaccess` dosyası ile gelir. Apache'de mod_rewrite'ın aktif olduğundan emin olun:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

Virtual Host örneği:
```apache
<VirtualHost *:80>
    ServerName randevu.yourdomain.com
    DocumentRoot /var/www/html/randevu-sistemi
    
    <Directory /var/www/html/randevu-sistemi>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/randevu_error.log
    CustomLog ${APACHE_LOG_DIR}/randevu_access.log combined
</VirtualHost>
```

#### Nginx Konfigürasyonu

```nginx
server {
    listen 80;
    server_name randevu.yourdomain.com;
    root /var/www/html/randevu-sistemi;
    index index.php index.html;

    # PHP işleme
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_index index.php;
    }

    # URL rewriting
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Güvenlik
    location ~ /\.env {
        deny all;
    }

    location ~ /config/ {
        deny all;
    }
}
```

### 5. Dosya İzinleri

```bash
# Genel dizin izinleri
chmod 755 -R .

# Log dizini yazılabilir olmalı
chmod 775 logs/
chown www-data:www-data logs/

# Environment dosyası güvenli olmalı
chmod 600 env
```

### 6. SSL Sertifikası (Önerilen)

#### Let's Encrypt ile ücretsiz SSL:
```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d randevu.yourdomain.com
```

### 7. Cron Job Kurulumu (Opsiyonel)

Otomatik SMS hatırlatmaları için:

```bash
crontab -e
```

Aşağıdaki satırı ekleyin:
```cron
# Her gün saat 10:00'da randevu hatırlatma SMS'i gönder
0 10 * * * curl -s "https://yourdomain.com/process/send-reminder-sms.php?token=YOUR_SMS_SECURITY_TOKEN" > /dev/null 2>&1
```

## İlk Kurulum Sonrası

### 1. Sisteme Giriş
- URL: `https://yourdomain.com`
- E-posta: `admin@gmail.com`
- Şifre: `123456789`

### 2. Admin Bilgilerini Güncelleyin
1. Sağ üst köşedeki profil menüsünden "Profil" seçin
2. E-posta ve şifrenizi güncelleyin
3. Güvenlik için varsayılan şifreyi mutlaka değiştirin

### 3. SMS Testi
1. "Ayarlar" bölümünden SMS ayarlarını kontrol edin
2. Test SMS göndererek entegrasyonu doğrulayın

## Sorun Giderme

### Veritabanı Bağlantı Hatası
```
Environment dosyasındaki DB_* değerlerini kontrol edin
MySQL servisinin çalıştığından emin olun: sudo systemctl status mysql
```

### SMS Gönderim Sorunu
```
NetGSM hesap bilgilerinizi kontrol edin
SMS_ENABLED=true olduğundan emin olun
İnternet bağlantısını kontrol edin
```

### 404 Hataları
```
Apache: mod_rewrite aktif mi?
Nginx: try_files direktifi doğru mu?
.htaccess dosyası mevcut mu?
```

### İzin Hataları
```
Web sunucu kullanıcısının dosyalara erişimi var mı?
logs/ klasörü yazılabilir mi?
```

## Güvenlik Kontrolleri

### 1. Environment Dosyası
```bash
# Dosya web'den erişilebilir olmamalı
curl https://yourdomain.com/env
# Bu 403 veya 404 döndürmelidir
```

### 2. Config Klasörü
```bash
# Config klasörü web'den erişilebilir olmamalı
curl https://yourdomain.com/config/database.php
# Bu 403 veya 404 döndürmelidir
```

### 3. Database Dosyası
```bash
# SQL dosyası web'den erişilebilir olmamalı
curl https://yourdomain.com/database/database.sql
# Bu 403 veya 404 döndürmelidir
```

## Performans Optimizasyonu

### 1. PHP OPcache
```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
```

### 2. MySQL Optimizasyonu
```sql
-- my.cnf
[mysqld]
innodb_buffer_pool_size = 256M
query_cache_size = 64M
tmp_table_size = 64M
max_heap_table_size = 64M
```

### 3. Gzip Sıkıştırma
Apache `.htaccess`:
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
```

## Yedekleme

### 1. Veritabanı Yedeği
```bash
# Otomatik yedekleme scripti
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u username -p randevu_db > backup_$DATE.sql
```

### 2. Dosya Yedeği
```bash
# Proje dosyalarını yedekle
tar -czf randevu_backup_$(date +%Y%m%d).tar.gz /var/www/html/randevu-sistemi
```

## Destek

Kurulum sırasında sorun yaşarsanız:

1. [GitHub Issues](https://github.com/bugraskl/randevu-sistemi/issues) sayfasından yeni bir issue oluşturun
2. Hata mesajını ve sistem bilgilerinizi ekleyin
3. Adım adım ne yaptığınızı belirtin

---

Bu kılavuzu takip ederek sistemi başarıyla kurabilirsiniz. Güvenlik ve performans önerilerini dikkate almayı unutmayın. 