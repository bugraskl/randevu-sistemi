<?php
/**
 * Yerel geliştirme veritabanını (randevu_dev) sıfırdan kurar ve SENTETİK verilerle doldurur.
 * Gerçek danışan verisi içermez. Tarihler çalıştırıldığı güne göre üretilir.
 *
 * Çalıştırma: C:\xampp\php\php.exe dev/seed.php
 *
 * Giriş bilgileri (yalnızca yerel geliştirme):
 *   E-posta: dev@localhost.test
 *   Şifre:   gelistirme-2026
 */

const DEV_DB = 'randevu_dev';
const DEV_EMAIL = 'dev@localhost.test';
const DEV_PASSWORD = 'gelistirme-2026';

date_default_timezone_set('Europe/Istanbul');
mt_srand(42);

$pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec('DROP DATABASE IF EXISTS `' . DEV_DB . '`');
$pdo->exec('CREATE DATABASE `' . DEV_DB . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `' . DEV_DB . '`');
$pdo->exec("SET time_zone = '+03:00'");

// Şema (production dökümünden yalnızca tablo yapıları)
$schema = file_get_contents(__DIR__ . '/schema.sql');
foreach (array_filter(array_map('trim', explode(";\n", str_replace("\r\n", "\n", $schema)))) as $statement) {
    $pdo->exec($statement);
}

// Kullanıcı
$pdo->prepare('INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)')
    ->execute(['Deniz Arslan', DEV_EMAIL, password_hash(DEV_PASSWORD, PASSWORD_DEFAULT), 'admin', 'active']);

// SMS şablonları (repo'daki database/database.sql ile aynı genel metinler)
$pdo->exec("INSERT INTO sms_templates (template_name, template_text) VALUES
    ('randevu_olusturma', 'Sayin {danisan_adi}, {tarih} tarihinde saat {saat} icin randevunuz olusturulmustur. Randevunuzu degistirmek veya iptal etmek icin whatsapp hattimizla iletisime gecebilirsiniz.'),
    ('randevu_hatirlatma', 'Sayin {danisan_adi}, yarin saat {saat} icin randevunuz bulunmaktadir. Randevunuzu degistirmek veya iptal etmek icin whatsapp hattimizla iletisime gecebilirsiniz.')");

// Sentetik danışanlar
$names = [
    'Ayşe Demirtaş', 'Mehmet Kılınç', 'Zeynep Aydemir', 'Can Özkaya', 'Elif Şahinler', 'Burak Tunalı',
    'Selin Karagöz', 'Emre Yücel', 'Derya Işıktaş', 'Kerem Aksoylu', 'Nazlı Çetinkaya', 'Onur Bilgesu',
    'Gizem Altıntepe', 'Tolga Erdemli', 'İpek Soylu', 'Barış Güneri', 'Melis Tanrıverdi', 'Arda Kocabaş',
    'Ece Yalçınkaya', 'Serkan Ulutaş', 'Pınar Ekinci', 'Umut Sarıgül', 'Deniz Kayalı', 'Irmak Özbay',
];
$clientStmt = $pdo->prepare('INSERT INTO clients (name, phone, email, notes, created_at) VALUES (?, ?, ?, ?, ?)');
$clientIds = [];
foreach ($names as $i => $name) {
    $phone = sprintf('0500000%02d%02d', intdiv($i, 10), $i + 10); // gerçek kayıt biçimi: 11 hane, boşluksuz
    $email = $i % 3 === 0 ? null : 'danisan' . ($i + 1) . '@example.test';
    $notes = $i % 4 === 0 ? 'Sentetik test kaydı — haftalık seans.' : null;
    $created = date('Y-m-d H:i:s', strtotime('-' . (120 - $i * 3) . ' days'));
    $clientStmt->execute([$name, $phone, $email, $notes, $created]);
    $clientIds[] = (int) $pdo->lastInsertId();
}

// Randevular: son 60 gün + önümüzdeki 21 gün, hafta içi ve cumartesi
$slots = ['10:00', '11:00', '12:30', '14:00', '15:00', '16:30', '18:00', '19:00'];
$apptStmt = $pdo->prepare('INSERT INTO appointments (client_id, appointment_date, appointment_time, status, notes) VALUES (?, ?, ?, ?, ?)');
$payStmt = $pdo->prepare('INSERT INTO payments (appointment_id, amount, payment_method, payment_date, notes) VALUES (?, ?, ?, ?, ?)');
$methods = ['cash', 'card', 'bank_transfer', 'bank_transfer', 'card'];
$now = time();
$unpaidLeft = 6;

for ($offset = -60; $offset <= 21; $offset++) {
    $day = strtotime(($offset >= 0 ? '+' : '') . $offset . ' days', strtotime('today'));
    $weekday = (int) date('N', $day);
    if ($weekday === 7) {
        continue; // Pazar kapalı
    }
    $count = $weekday === 6 ? mt_rand(1, 3) : mt_rand(3, 6);
    if ($offset === 0) {
        $count = 6; // Bugün dolu bir gün
    }
    $daySlots = $slots;
    shuffle($daySlots);
    $daySlots = array_slice($daySlots, 0, $count);
    sort($daySlots);

    foreach ($daySlots as $slot) {
        $clientId = $clientIds[mt_rand(0, count($clientIds) - 1)];
        $date = date('Y-m-d', $day);
        $when = strtotime("$date $slot");
        $isPast = $when < $now;

        if ($isPast) {
            $status = mt_rand(1, 20) === 1 ? 'iptal' : 'tamamlandı';
        } else {
            $status = mt_rand(1, 3) === 1 ? 'beklemede' : 'onaylandı';
        }
        $apptStmt->execute([$clientId, $date, $slot . ':00', $status, null]);
        $apptId = (int) $pdo->lastInsertId();

        if ($isPast && $status !== 'iptal') {
            // Son günlerde birkaç ödenmemiş seans bırak
            if ($offset >= -10 && $unpaidLeft > 0 && mt_rand(1, 3) === 1) {
                $unpaidLeft--;
                continue;
            }
            $amount = $offset < -30 ? 1700 : 2000;
            $method = $methods[mt_rand(0, count($methods) - 1)];
            $payStmt->execute([$apptId, $amount, $method, date('Y-m-d H:i:s', $when + 3600), null]);
        }
    }
}

// Giderler
$expStmt = $pdo->prepare('INSERT INTO expenses (title, category, amount, payment_method, expense_date, notes) VALUES (?, ?, ?, ?, ?, ?)');
$expenses = [
    ['Muayenehane kirası', 'Kira', 18000, 'bank_transfer', -55],
    ['Elektrik faturası', 'Faturalar', 1240.50, 'card', -48],
    ['Süpervizyon ücreti', 'Mesleki gelişim', 3500, 'bank_transfer', -40],
    ['Kırtasiye ve test formları', 'Ofis', 860, 'card', -33],
    ['Muayenehane kirası', 'Kira', 18000, 'bank_transfer', -25],
    ['Temizlik hizmeti', 'Ofis', 1500, 'cash', -18],
    ['İnternet', 'Faturalar', 649.90, 'card', -12],
    ['Kitap: çocuk ve ergen terapisi', 'Mesleki gelişim', 720, 'card', -6],
    ['Çay, kahve, su', 'Ofis', 430, 'cash', -2],
];
foreach ($expenses as [$title, $category, $amount, $method, $offset]) {
    $expStmt->execute([$title, $category, $amount, $method, date('Y-m-d', strtotime("$offset days")), null]);
}

$recStmt = $pdo->prepare('INSERT INTO recurring_expenses (title, category, amount, payment_method, start_date, recurrence_interval, active, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$recurring = [
    ['Muayenehane kirası', 'Kira', 18000, 'bank_transfer', '-8 months', 'monthly', 1],
    ['Muhasebe hizmeti', 'Hizmet', 2500, 'bank_transfer', '-8 months', 'monthly', 1],
    ['İnternet', 'Faturalar', 649.90, 'card', '-8 months', 'monthly', 1],
    ['Mesleki sorumluluk sigortası', 'Sigorta', 4200, 'card', '-4 months', 'yearly', 1],
    ['Online takvim aboneliği', 'Yazılım', 390, 'card', '-6 months', 'monthly', 0],
];
foreach ($recurring as [$title, $category, $amount, $method, $start, $interval, $active]) {
    $recStmt->execute([$title, $category, $amount, $method, date('Y-m-d', strtotime($start)), $interval, $active, null]);
}

$counts = [];
foreach (['clients', 'appointments', 'payments', 'expenses', 'recurring_expenses', 'users'] as $table) {
    $counts[] = $table . '=' . $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
}
echo 'OK ' . DEV_DB . ': ' . implode(', ', $counts) . PHP_EOL;
