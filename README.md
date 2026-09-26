# Randevu Yönetim Sistemi

PHP tabanlı profesyonel randevu yönetim sistemi. Psikolog, doktor ve benzeri meslek grupları için geliştirilmiş kapsamlı bir yönetim platformu.

## Özellikler

- 📅 **Randevu Yönetimi**: Kolay randevu oluşturma, düzenleme ve takip; telefonda da çalışan aylık takvim
- ☀️ **Bugün Ekranı**: Sıradaki seans, canlı seans halkası, saat ekseninde günün programı ve ödeme bekleyen seanslar
- 📱 **SMS Entegrasyonu**: NetGSM üzerinden randevu oluşturma ve hatırlatma SMS'leri
- 👥 **Danışan Yönetimi**: Kapsamlı müşteri profili ve geçmiş takibi; randevu ekranından yeni danışan ekleme
- ☎️ **Tek Telefon Biçimi**: `+90 (500) 123 45 67`, `05001234567`, `5001234567` gibi tüm girişler `05001234567` olarak kaydedilir
- 💰 **Ödeme Takibi**: Gelir ve gider yönetimi, tek dokunuşla ödeme alma
- ⚙️ **Seans Ayarları**: Varsayılan seans ücreti ve süresi yönetim panelinden
- 📊 **Raporlama**: Detaylı istatistik ve analiz raporları
- 🌙 **Tema Desteği**: Açık/koyu mod seçenekleri
- 📱 **Telefon Öncelikli Tasarım**: Alt sekme çubuğu, alttan açılan paneller, PWA desteği
- 🔒 **Güvenlik**: Session tabanlı kullanıcı yönetimi, hassas dosyalara web erişimi kapalı

## 2.0 ile gelenler

- Arayüz telefon öncelikli olarak baştan tasarlandı; tasarım sistemi `DESIGN.md` dosyasında belgelidir.
- Randevular onay beklemez: oluşturulan randevu planlanmış sayılır. Danışana yalnızca randevunun oluşturulduğu SMS'i ve bir gün önce hatırlatma SMS'i gider (teyit linki üretilmez).
- Varsayılan seans ücreti ve süresi **Menü → Seans Ayarları** sayfasından yönetilir (`app_settings` tablosu).
- Gider işlemlerine giriş kontrolü eklendi; kullanıcı araması artık şifre özetlerini tarayıcıya göndermez; `.htaccess` `env`, `.sql`, `.log`, `.zip` dosyalarına ve `logs/`, `database/` klasörlerine web erişimini kapatır.

### 1.x sürümünden güncelleme

1. Dosyaları güncelleyin (`env` dosyanıza dokunmayın).
2. `app_settings` tablosu ilk açılışta otomatik oluşturulur. Veritabanı kullanıcınızın tablo oluşturma yetkisi yoksa `database/migrations/2026-09-26_app_settings.sql` dosyasını bir kez çalıştırın.
3. Kayıtlı telefon numaraları zaten `05XXXXXXXXX` biçimindeyse ek işlem gerekmez.

## Kurulum

### Gereksinimler

- PHP 7.4 veya üzeri
- MySQL 5.7 veya üzeri
- Apache/Nginx web sunucusu
- cURL PHP uzantısı (SMS entegrasyonu için)

### 1. Projeyi İndirin

```bash
git clone https://github.com/bugraskl/randevu-sistemi.git
cd randevu-sistemi
```

### 2. Environment Dosyasını Hazırlayın

```bash
cp env.example env
```

`env` dosyasını düzenleyerek kendi bilgilerinizi girin:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=randevu_db
DB_USERNAME=root
DB_PASSWORD=your_password

# NetGSM SMS Configuration
NETGSM_USERNAME=your_netgsm_username
NETGSM_PASSWORD=your_netgsm_password
NETGSM_HEADER=your_sender_name

# SMS Security Token
SMS_SECURITY_TOKEN=your_secure_random_token
```

### 3. Veritabanını Oluşturun

```sql
CREATE DATABASE randevu_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Veritabanı tablolarını oluşturmak için `database/database.sql` dosyasını import edin:

```bash
mysql -u username -p randevu_db < database/database.sql
```

### 4. Web Sunucusu Ayarları

Apache için `.htaccess` dosyası zaten mevcuttur. Nginx kullanıyorsanız aşağıdaki konfigürasyonu ekleyin:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

### 5. Dizin İzinleri

```bash
chmod 755 -R .
chmod 775 logs/
```

### 6. Giriş Bilgileri

Sistem varsayılan admin kullanıcısı ile gelir:
- **E-posta:** admin@gmail.com
- **Şifre:** 123456789

İlk girişten sonra bu bilgileri güvenlik açısından mutlaka değiştirin.

## Konfigürasyon

### Environment Değişkenleri

| Değişken | Açıklama | Varsayılan |
|----------|----------|------------|
| `APP_ENV` | Uygulama ortamı (development/production) | development |
| `APP_DEBUG` | Debug modu (true/false) | true |
| `DB_HOST` | Veritabanı sunucusu | localhost |
| `DB_NAME` | Veritabanı adı | - |
| `DB_USERNAME` | Veritabanı kullanıcı adı | - |
| `DB_PASSWORD` | Veritabanı şifresi | - |
| `NETGSM_USERNAME` | NetGSM kullanıcı adı | - |
| `NETGSM_PASSWORD` | NetGSM şifresi | - |
| `NETGSM_HEADER` | SMS gönderici adı | - |
| `SMS_ENABLED` | SMS gönderimini aktif/pasif yapar | true |
| `SMS_DEBUG` | SMS debug modu | false |
| `SMS_SECURITY_TOKEN` | SMS endpoint güvenlik token'ı | - |
| `SEANS_SURESI_DK` | Seans süresi için ilk varsayılan (sonra Seans Ayarları'ndan) | 50 |
| `PRACTITIONER_NAME` | Teyit sayfasında görünen uzman adı (isteğe bağlı) | - |
| `N8N_WEBHOOK_URL` / `N8N_WEBHOOK_TOKEN` / `N8N_WEBHOOK_USER` | Ödeme alındığında gelir bildirimi gönderilecek webhook (isteğe bağlı) | - |

### Production Ayarları

Production ortamında aşağıdaki değerleri güncelleyin:

```env
APP_ENV=production
APP_DEBUG=false
SMS_DEBUG=false
```

## SMS Entegrasyonu

Sistem NetGSM SMS servisi ile entegre çalışır. SMS özellikleri:

- Randevu oluşturulduğunda otomatik SMS (tarih/saat değiştirilirse güncel bilgiyle tekrar)
- Randevudan bir gün önce hatırlatma SMS'i (onay/teyit linki içermez)
- Özelleştirilebilir SMS şablonları (`{danisan_adi}`, `{tarih}`, `{saat}`)

### Otomatik SMS Hatırlatma

Cron job ekleyerek günlük otomatik hatırlatma SMS'leri gönderebilirsiniz:

```bash
# Her gün saat 10:00'da çalışacak şekilde
0 10 * * * curl "https://yourdomain.com/process/send-reminder-sms.php?token=YOUR_SMS_SECURITY_TOKEN"
```

## Yerel Geliştirme

`dev/` klasörü, gerçek `env` dosyanıza ve verilerinize dokunmadan çalışan bir geliştirme ortamı sağlar:

```bash
# Sentetik verili ayrı bir veritabanı (randevu_dev) kurar; giriş bilgileri dev/seed.php içindedir
php dev/seed.php

# PHP yerleşik sunucusu: bu sunucuda config/env.php otomatik olarak dev/env.dev dosyasını kullanır (SMS kapalı)
php -S localhost:8091 -t . dev/router.php
```

`dev/.htaccess` bu klasörü web'den erişilemez yapar; production'da `dev/env.dev` hiçbir zaman okunmaz.

## Kullanım

1. Sisteme giriş yapın
2. **Danışanlar** bölümünden yeni danışan ekleyin
3. **Randevular** bölümünden randevu oluşturun
4. **Ödemeler** bölümünden finansal takip yapın
5. **Raporlar** bölümünden istatistikleri görüntüleyin

## Güvenlik

- Tüm database sorguları prepared statement kullanır
- XSS koruması için output filtering
- CSRF koruması (form token'ları)
- Session tabanlı kimlik doğrulama
- Environment değişkenleri ile hassas bilgi yönetimi

## Katkıda Bulunma

1. Fork edin
2. Feature branch oluşturun (`git checkout -b feature/amazing-feature`)
3. Commit edin (`git commit -m 'Add amazing feature'`)
4. Push edin (`git push origin feature/amazing-feature`)
5. Pull Request oluşturun

## Lisans

Bu proje MIT lisansı altında lisanslanmıştır. Detaylar için `LICENSE` dosyasına bakın.

## Destek

Herhangi bir sorun veya öneri için GitHub Issues kullanabilirsiniz.

---

⭐ Eğer bu proje işinize yaradıysa, yıldız vermeyi unutmayın! 