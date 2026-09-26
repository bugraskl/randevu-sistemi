<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index');
    exit();
}

// İsteğe bağlı dönüş sayfası (yalnızca izin verilen sayfalar)
$redirect = '../payments';
if (in_array($_POST['return_to'] ?? '', ['dashboard', 'payments'], true)) {
    $redirect = '../' . $_POST['return_to'];
} elseif (($_POST['return_to'] ?? '') === 'client-details' && !empty($_POST['client_id'])) {
    $redirect = '../client-details?id=' . (int) $_POST['client_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = $_POST['appointment_id'] ?? null;
    $amount = $_POST['amount'] ?? null;
    $payment_method = $_POST['payment_method'] ?? null;
    $notes = $_POST['notes'] ?? null;

    if (!$appointment_id || !$amount || !$payment_method) {
        $_SESSION['error'] = "Lütfen tüm gerekli alanları doldurun.";
        header('Location: ' . $redirect);
        exit();
    }

    try {
        // Ödeme kaydı oluştur
        $stmt = $db->prepare("
            INSERT INTO payments (appointment_id, amount, payment_method, payment_date, notes)
            VALUES (?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([$appointment_id, $amount, $payment_method, $notes]);

        $_SESSION['success'] = "Ödeme başarıyla kaydedildi.";

        // Finans asistanına (n8n) otomatik gelir bildirimi. Fire-and-forget:
        // hata olsa bile ödeme kaydını asla etkilemez.
        notifyFinansAsistani($amount, $payment_method, $notes);
    } catch(PDOException $e) {
        $_SESSION['error'] = "Ödeme kaydedilirken bir hata oluştu: " . $e->getMessage();
    }
}

header('Location: ' . $redirect);
exit();

/**
 * Ödeme girildiğinde n8n webhook'una gelir bildirimi gönderir.
 * Örn. 3000 TL ödeme → Telegram finans asistanına "3000 TL seanstan kazanç" olarak kaydedilir.
 * Bilinçli olarak fire-and-forget: her türlü hata sessizce yutulur, ödeme akışını bozmaz.
 */
function notifyFinansAsistani($amount, $method, $note) {
    $webhookUrl = EnvConfig::get('N8N_WEBHOOK_URL', '');
    $token      = EnvConfig::get('N8N_WEBHOOK_TOKEN', '');

    // env'de tanımlı değilse sessizce çık (özellik kapalı sayılır).
    if ($webhookUrl === '' || $token === '' || !function_exists('curl_init')) {
        return;
    }

    try {
        $payload = json_encode([
            'token'  => $token,
            'amount' => (float)$amount,
            'method' => (string)$method,
            'note'   => (string)$note,
            'user'   => EnvConfig::get('N8N_WEBHOOK_USER', ''),
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        curl_exec($ch);
        curl_close($ch);
    } catch (\Throwable $e) {
        // Sessizce yut — bildirim başarısız olsa da ödeme kaydı geçerli kalır.
    }
}
