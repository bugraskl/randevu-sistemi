<?php
require_once __DIR__ . '/../config/database.php';


function smsDebugLog($message) {
    // Debug modu açık mı kontrol et
    if (!EnvConfig::getBool('SMS_DEBUG', false)) {
        return;
    }
    
    $logFile = dirname(__DIR__) . '/logs/sms_debug.log';
    
    // Logs klasörü yoksa oluştur
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - SMS DEBUG: " . $message . "\n", FILE_APPEND);
}


function sendSMS($phone, $message) {
    // SMS gönderimi aktif mi kontrol et
    if (!EnvConfig::getBool('SMS_ENABLED', true)) {
        smsDebugLog("SMS gönderimi devre dışı - SMS_ENABLED=false");
        return false;
    }
    
    // NetGSM API bilgilerini env dosyasından al
    $username = EnvConfig::get('NETGSM_USERNAME');
    $password = EnvConfig::get('NETGSM_PASSWORD');
    $header = EnvConfig::get('NETGSM_HEADER');
    
    // Gerekli bilgilerin kontrolü
    if (empty($username) || empty($password) || empty($header)) {
        smsDebugLog("HATA: NetGSM API bilgileri eksik. Username: $username, Header: $header");
        return false;
    }

    smsDebugLog("SMS gönderimi başladı - Telefon: {$phone}");

    // Telefon numarasını formatla (başında 0 olmadan)
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) === '0') {
        $phone = substr($phone, 1);
    }
    smsDebugLog("Formatlanmış telefon: {$phone}");

    // API endpoint
    $apiUrl = 'https://api.netgsm.com.tr/sms/rest/v2/send';

    // POST verileri
    $data = [
        "msgheader" => $header,
        "messages" => [
            [
                "msg" => $message,
                "no" => $phone
            ]
        ],
        "encoding" => "TR",
        "iysfilter" => "",
        "partnercode" => ""
    ];

    smsDebugLog("API isteği hazırlandı: " . json_encode($data));

    // cURL isteği
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($username . ':' . $password)
    ]);
    
    // SSL doğrulamasını devre dışı bırak
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    smsDebugLog("cURL isteği gönderiliyor...");
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // cURL hata kontrolü
    if ($response === false) {
        $error = curl_error($ch);
        smsDebugLog("cURL Hatası: " . $error);
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);

    smsDebugLog("API Yanıtı - HTTP Kodu: {$httpCode}, Yanıt: {$response}");

    // Hata durumunda log tut
    if ($httpCode !== 200) {
        smsDebugLog("HTTP Hata Kodu: " . $httpCode);
        return false;
    }

    // API yanıtını JSON olarak parse et
    $responseData = json_decode($response, true);
    
    // Başarılı yanıt kontrolü
    if (isset($responseData['code']) && $responseData['code'] === '00') {
        smsDebugLog("SMS başarıyla gönderildi");
        return true;
    } else {
        smsDebugLog("SMS gönderilemedi. API Yanıtı: " . $response);
        return false;
    }
}

function getSMSTemplate($templateName) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT template_text FROM sms_templates WHERE template_name = ?");
        $stmt->execute([$templateName]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        return $template ? $template['template_text'] : null;
    } catch(PDOException $e) {
        error_log("SMS şablonu alınırken hata: " . $e->getMessage());
        return null;
    }
}

function sendAppointmentConfirmation($clientName, $phone, $date, $time) {
    // SMS gönderimi aktif mi kontrol et
    if (!EnvConfig::getBool('SMS_ENABLED', true)) {
        smsDebugLog("Randevu onay SMS'i gönderilmedi - SMS_ENABLED=false");
        return false;
    }
    
    // Şablonu veritabanından al
    $template = getSMSTemplate('randevu_olusturma');
    
    if (!$template) {
        // Şablon bulunamazsa varsayılan mesajı kullan
        $message = "Sayin {$clientName}, randevunuz basariyla olusturulmustur. Tarih: " . 
                   date('d.m.Y', strtotime($date)) . " Saat: " . 
                   date('H:i', strtotime($time)) . 
                   ". Randevunuzu degistirmek veya iptal etmek icin lutfen bizimle iletisime gecin.";
    } else {
        // Şablonu kullan
        $message = str_replace(
            ['{danisan_adi}', '{tarih}', '{saat}'],
            [$clientName, date('d.m.Y', strtotime($date)), date('H:i', strtotime($time))],
            $template
        );
    }

    return sendSMS($phone, $message);
}

function sendAppointmentReminder($clientName, $phone, $time) {
    // SMS gönderimi aktif mi kontrol et
    if (!EnvConfig::getBool('SMS_ENABLED', true)) {
        smsDebugLog("Randevu hatırlatma SMS'i gönderilmedi - SMS_ENABLED=false");
        return false;
    }
    
    // Şablonu veritabanından al
    $template = getSMSTemplate('randevu_hatirlatma');
    
    if (!$template) {
        // Şablon bulunamazsa varsayılan mesajı kullan
        $message = "Sayin {$clientName}, yarin saat " . date('H:i', strtotime($time)) . 
                   " icin randevunuz bulunmaktadir.";
    } else {
        // Şablonu kullan
        $message = str_replace(
            ['{danisan_adi}', '{saat}'],
            [$clientName, date('H:i', strtotime($time))],
            $template
        );
    }

    return sendSMS($phone, $message);
} 