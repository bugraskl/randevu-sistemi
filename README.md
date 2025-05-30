# Randevu Yönetim Sistemi

PHP tabanlı profesyonel randevu yönetim sistemi. Psikolog, doktor ve benzeri meslek grupları için geliştirilmiş kapsamlı bir yönetim platformu.

## Özellikler

- 📅 **Randevu Yönetimi**: Kolay randevu oluşturma, düzenleme ve takip
- 📱 **SMS Entegrasyonu**: NetGSM üzerinden otomatik SMS bildirimleri
- 👥 **Danışan Yönetimi**: Kapsamlı müşteri profili ve geçmiş takibi
- 💰 **Ödeme Takibi**: Gelir ve gider yönetimi
- 📊 **Raporlama**: Detaylı istatistik ve analiz raporları
- 🌙 **Tema Desteği**: Açık/koyu mod seçenekleri
- 📱 **Responsive Tasarım**: Mobil uyumlu arayüz
- 🔒 **Güvenlik**: Session tabanlı kullanıcı yönetimi

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

### Production Ayarları

Production ortamında aşağıdaki değerleri güncelleyin:

```env
APP_ENV=production
APP_DEBUG=false
SMS_DEBUG=false
```

## SMS Entegrasyonu

Sistem NetGSM SMS servisi ile entegre çalışır. SMS özellikleri:

- Randevu oluşturulduğunda otomatik SMS
- Randevu hatırlatma SMS'leri
- Özelleştirilebilir SMS şablonları

### Otomatik SMS Hatırlatma

Cron job ekleyerek günlük otomatik hatırlatma SMS'leri gönderebilirsiniz:

```bash
# Her gün saat 10:00'da çalışacak şekilde
0 10 * * * curl "https://yourdomain.com/process/send-reminder-sms.php?token=YOUR_SMS_SECURITY_TOKEN"
```

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