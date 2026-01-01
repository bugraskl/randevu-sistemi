<?php
require_once '../config/database.php';
require_once '../includes/sms.php';

// Debug modu
$debug = EnvConfig::getBool('SMS_DEBUG', false);

// Log dosyası yolu
$logFile = dirname(__DIR__) . '/logs/sms_reminder.log';

// Debug log fonksiyonu
function debugLog($message) {
    global $logFile, $debug;
    if ($debug && EnvConfig::getBool('ENABLE_LOGGING', true)) {
        // Logs klasörü yoksa oluştur
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - DEBUG: " . $message . "\n", FILE_APPEND);
    }
}

// Güvenlik kontrolü - sadece belirli bir IP'den veya token ile erişime izin ver
$allowedIPs = ['127.0.0.1', '::1']; // localhost IP'leri
$securityToken = EnvConfig::get('SMS_SECURITY_TOKEN'); // Güvenlik token'ını env'den al

// IP kontrolü
$clientIP = $_SERVER['REMOTE_ADDR'];
$isLocalhost = in_array($clientIP, $allowedIPs);

// Token kontrolü
$providedToken = $_GET['token'] ?? '';

debugLog("İstek başladı - IP: {$clientIP}, Token: {$providedToken}");

if (!$isLocalhost && $providedToken !== $securityToken) {
    debugLog("Erişim reddedildi - IP veya token geçersiz");
    header('HTTP/1.0 403 Forbidden');
    echo "Erişim reddedildi.";
    exit();
}

try {
    // SMS gönderimi aktif mi kontrol et
    if (!EnvConfig::getBool('SMS_ENABLED', true)) {
        debugLog("SMS gönderimi devre dışı - SMS_ENABLED=false");
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => "SMS gönderimi devre dışı",
            'details' => [
                'successful' => 0,
                'failed' => 0,
                'total' => 0
            ]
        ]);
        exit();
    }

    // Yarının tarihini al
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    debugLog("Yarının tarihi: {$tomorrow}");
    
    // Yarınki randevuları getir
    $stmt = $db->prepare("
        SELECT a.*, c.name as client_name, c.phone 
        FROM appointments a 
        JOIN clients c ON a.client_id = c.id 
        WHERE a.appointment_date = ? 
        AND a.status != 'iptal'
    ");
    $stmt->execute([$tomorrow]);
    $appointments = $stmt->fetchAll();
    
    debugLog("Bulunan randevu sayısı: " . count($appointments));

    $successCount = 0;
    $failCount = 0;

    foreach ($appointments as $appointment) {
        try {
            debugLog("Randevu işleniyor - ID: {$appointment['id']}, Danışan: {$appointment['client_name']}, Telefon: {$appointment['phone']}");
            
            // Benzersiz teyit token'ı oluştur (12 karakter - SMS için kısa)
            $token = bin2hex(random_bytes(6));
            $tokenExpires = date('Y-m-d H:i:s', strtotime('+2 days')); // 2 gün geçerli
            
            // Token'ı veritabanına kaydet
            $updateStmt = $db->prepare("
                UPDATE appointments 
                SET confirmation_token = ?, token_expires_at = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$token, $tokenExpires, $appointment['id']]);
            
            debugLog("Token oluşturuldu: " . substr($token, 0, 10) . "...");
            
            // Teyit linkini oluştur (kısa format)
            $appUrl = EnvConfig::get('APP_URL', 'https://randevu.iklimakcaglayan.com');
            $confirmLink = rtrim($appUrl, '/') . '/ca?t=' . $token;
            
            // SMS şablonunu veritabanından al
            $template = getSMSTemplate('randevu_hatirlatma');
            
            if (!$template) {
                // Şablon bulunamazsa varsayılan mesajı kullan
                $message = "Sayin {$appointment['client_name']}, yarin " . 
                          date('d.m.Y', strtotime($appointment['appointment_date'])) . " tarihinde " . 
                          date('H:i', strtotime($appointment['appointment_time'])) . 
                          " saatinde randevunuz bulunmaktadir. Teyit/iptal: " . $confirmLink;
            } else {
                // Şablonu kullan
                $message = str_replace(
                    ['{danisan_adi}', '{saat}', '{teyit_linki}'],
                    [
                        $appointment['client_name'], 
                        date('H:i', strtotime($appointment['appointment_time'])),
                        $confirmLink
                    ],
                    $template
                );
            }
            
            debugLog("SMS mesajı hazırlandı: " . substr($message, 0, 50) . "...");

            // SMS gönderme öncesi telefon numarası kontrolü
            $phone = $appointment['phone'];
            if (empty($phone)) {
                debugLog("HATA: Telefon numarası boş - Randevu ID: {$appointment['id']}");
                $failCount++;
                continue;
            }

            // SMS gönderme işlemi
            $smsResult = sendSMS($phone, $message);
            debugLog("SMS gönderim sonucu: " . ($smsResult ? "Başarılı" : "Başarısız"));

            if ($smsResult) {
                $successCount++;
                debugLog("SMS başarıyla gönderildi - Telefon: {$phone}");
            } else {
                $failCount++;
                debugLog("SMS gönderilemedi - Telefon: {$phone}");
            }
        } catch (Exception $e) {
            $failCount++;
            debugLog("SMS gönderim hatası: " . $e->getMessage() . " - Telefon: {$appointment['phone']}");
        }
    }

    // Özet log
    $logMessage = date('Y-m-d H:i:s') . " - Hatırlatma SMS'leri gönderildi. Başarılı: {$successCount}, Başarısız: {$failCount}, Toplam: " . count($appointments) . "\n";
    debugLog($logMessage);

    // JSON yanıt döndür
    header('Content-Type: application/json');
    $response = [
        'success' => true,
        'message' => "Hatırlatma SMS'leri gönderildi",
        'details' => [
            'successful' => $successCount,
            'failed' => $failCount,
            'total' => count($appointments)
        ],
        'debug' => $debug ? [
            'ip' => $clientIP,
            'date' => $tomorrow,
            'appointments' => count($appointments)
        ] : null
    ];
    echo json_encode($response);

} catch(Exception $e) {
    // Hata durumunda log tut
    $errorMessage = date('Y-m-d H:i:s') . " - Genel Hata: " . $e->getMessage() . "\n";
    debugLog("Kritik hata: " . $e->getMessage());

    // Hata yanıtı döndür
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "Bir hata oluştu",
        'error' => $e->getMessage(),
        'debug' => $debug ? [
            'ip' => $clientIP,
            'date' => $tomorrow ?? null
        ] : null
    ]);
}
?> 