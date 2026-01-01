<?php
/**
 * Randevu Teyit Sayfası
 * Danışanların SMS'teki link ile randevularını onaylaması veya iptal etmesi için
 * Bu sayfa giriş gerektirmez (public)
 */

require_once 'config/database.php';
require_once 'includes/sms.php';

// Token'ı URL'den al (kısa parametre: t)
$token = $_GET['t'] ?? '';
$action = $_POST['action'] ?? '';

// Hata ve başarı mesajları
$error = '';
$success = '';
$appointment = null;
$client = null;
$processed = false;

// Token kontrolü
if (empty($token)) {
    $error = 'Geçersiz veya eksik teyit linki.';
} else {
    try {
        // Token ile randevuyu bul
        $stmt = $db->prepare("
            SELECT a.*, c.name as client_name, c.phone as client_phone
            FROM appointments a
            JOIN clients c ON a.client_id = c.id
            WHERE a.confirmation_token = ?
            AND a.token_expires_at > NOW()
            AND a.status NOT IN ('iptal', 'tamamlandı')
        ");
        $stmt->execute([$token]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            $error = 'Bu teyit linki geçersiz, süresi dolmuş veya randevu zaten işlenmiş.';
        } else {
            $client = $appointment['client_name'];
            
            // Form gönderildi mi?
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action)) {
                if ($action === 'cancel') {
                    // Randevuyu iptal et
                    $stmt = $db->prepare("UPDATE appointments SET status = 'iptal' WHERE id = ?");
                    $stmt->execute([$appointment['id']]);
                    
                    // Yöneticiye SMS gönder
                    $adminPhone = EnvConfig::get('ADMIN_NOTIFICATION_PHONE', '05350210164');
                    $template = getSMSTemplate('randevu_iptal_bildirim');
                    
                    if ($template) {
                        $message = str_replace(
                            ['{danisan_adi}', '{tarih}', '{saat}'],
                            [
                                $appointment['client_name'],
                                date('d.m.Y', strtotime($appointment['appointment_date'])),
                                date('H:i', strtotime($appointment['appointment_time']))
                            ],
                            $template
                        );
                    } else {
                        $message = "{$appointment['client_name']} isimli danışan " .
                                   date('d.m.Y', strtotime($appointment['appointment_date'])) . " " .
                                   date('H:i', strtotime($appointment['appointment_time'])) . 
                                   " randevusunu İPTAL etti.";
                    }
                    
                    sendSMS($adminPhone, $message);
                    
                    $success = 'Randevunuz iptal edilmiştir. Yeni bir randevu almak için bizimle iletişime geçebilirsiniz.';
                    $processed = true;
                    
                } elseif ($action === 'confirm') {
                    // Sadece teşekkür mesajı göster (veritabanında değişiklik yapmıyoruz)
                    $success = 'Teşekkür ederiz! Randevunuzda görüşmek üzere.';
                    $processed = true;
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
        error_log("Randevu teyit hatası: " . $e->getMessage());
    }
}

// Türkçe gün isimleri
$gunler = [
    'Monday' => 'Pazartesi',
    'Tuesday' => 'Salı',
    'Wednesday' => 'Çarşamba',
    'Thursday' => 'Perşembe',
    'Friday' => 'Cuma',
    'Saturday' => 'Cumartesi',
    'Sunday' => 'Pazar'
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Randevu Teyit</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            min-height: 100vh;
            background: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            max-width: 400px;
            width: 100%;
            padding: 30px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .header h1 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .header p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .greeting {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }
        
        .info-box {
            background: #fafafa;
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 25px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }
        
        .info-row:not(:last-child) {
            border-bottom: 1px solid #eee;
        }
        
        .info-row .label {
            color: #666;
        }
        
        .info-row .value {
            font-weight: 500;
            color: #333;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            margin-bottom: 10px;
        }
        
        .btn-confirm {
            background: #333;
            color: #fff;
        }
        
        .btn-confirm:hover {
            background: #444;
        }
        
        .btn-cancel {
            background: #fff;
            color: #666;
            border: 1px solid #ddd;
        }
        
        .btn-cancel:hover {
            background: #f5f5f5;
        }
        
        .message {
            text-align: center;
            padding: 20px 0;
        }
        
        .message.error {
            color: #c00;
        }
        
        .message.success {
            color: #333;
        }
        
        .footer-text {
            text-align: center;
            color: #999;
            font-size: 0.8rem;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Randevu Teyit</h1>
            <p>Psk. İklim Akçağlayan</p>
        </div>
        
        <?php if ($error): ?>
            <div class="message error">
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php elseif ($processed): ?>
            <div class="message success">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php elseif ($appointment): ?>
            <div class="greeting">
                Merhaba <?php echo htmlspecialchars($client); ?>
            </div>
            
            <div class="info-box">
                <div class="info-row">
                    <span class="label">Tarih</span>
                    <span class="value">
                        <?php 
                        $date = new DateTime($appointment['appointment_date']);
                        echo $date->format('d.m.Y') . ' ' . $gunler[$date->format('l')];
                        ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label">Saat</span>
                    <span class="value"><?php echo date('H:i', strtotime($appointment['appointment_time'])); ?></span>
                </div>
            </div>
            
            <form method="POST">
                <button type="submit" name="action" value="confirm" class="btn btn-confirm">
                    Geleceğim
                </button>
                <button type="submit" name="action" value="cancel" class="btn btn-cancel">
                    Gelemeyeceğim
                </button>
            </form>
            
            <p class="footer-text">
                Yanıt vermezseniz randevunuz geçerli kabul edilecektir.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
