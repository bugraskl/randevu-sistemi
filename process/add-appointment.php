<?php
session_start();
require_once '../config/env.php';
require_once '../config/database.php';
require_once '../includes/sms.php';
require_once '../includes/phone.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index');
    exit();
}

$redirect = '../appointments';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = filter_input(INPUT_POST, 'client_id', FILTER_SANITIZE_NUMBER_INT);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    $hour = filter_input(INPUT_POST, 'hour', FILTER_SANITIZE_STRING);
    $minute = filter_input(INPUT_POST, 'minute', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    $view = filter_input(INPUT_POST, 'view', FILTER_SANITIZE_STRING); // Hangi görünümden eklendiğini al

    // Randevu ekranından yeni danışan (kayıtlı değilse)
    $newClientName = trim(strip_tags((string) ($_POST['new_client_name'] ?? '')));
    $newClientName = mb_substr(preg_replace('/\s+/u', ' ', $newClientName), 0, 100, 'UTF-8');
    $newClientPhone = trim((string) ($_POST['new_client_phone'] ?? ''));
    $isNewClient = empty($client_id) && ($newClientName !== '' || $newClientPhone !== '');

    // Dönüş sayfası: izinli bir kaynak sayfa verildiyse oraya, yoksa randevular görünümüne
    $redirect = '../appointments' . ($view === 'calendar' ? '?view=calendar' : '');
    if (in_array($_POST['return_to'] ?? '', ['dashboard'], true)) {
        $redirect = '../' . $_POST['return_to'];
    }

    if ((!$isNewClient && empty($client_id)) || empty($date) || empty($hour) || empty($minute)) {
        $_SESSION['error'] = "Lütfen tüm zorunlu alanları doldurun.";
        header('Location: ' . $redirect);
        exit();
    }

    $normalizedPhone = null;
    if ($isNewClient) {
        if ($newClientName === '' || $newClientPhone === '') {
            $_SESSION['error'] = "Yeni danışan için ad soyad ve telefon gerekli.";
            header('Location: ' . $redirect);
            exit();
        }
        $normalizedPhone = normalizePhone($newClientPhone);
        if ($normalizedPhone === null) {
            $_SESSION['error'] = PHONE_FORMAT_HINT;
            header('Location: ' . $redirect);
            exit();
        }
    }

    // Saat ve dakikayı birleştir
    $time = $hour . ':' . $minute . ':00';

    try {
        $db->beginTransaction();

        $clientNotice = '';
        $clientWarning = '';
        if ($isNewClient) {
            // Aynı numara zaten kayıtlıysa yeni kayıt açma, randevuyu o danışana ekle
            $stmt = $db->prepare("SELECT id, name FROM clients WHERE phone = ?");
            $stmt->execute([$normalizedPhone]);
            $existing = $stmt->fetch();

            if ($existing) {
                $client_id = $existing['id'];
                $clientWarning = "Bu numara zaten “" . $existing['name'] . "” adına kayıtlıydı; randevu bu danışana eklendi.";
            } else {
                $stmt = $db->prepare("INSERT INTO clients (name, phone) VALUES (?, ?)");
                $stmt->execute([$newClientName, $normalizedPhone]);
                $client_id = $db->lastInsertId();
                $clientNotice = "Yeni danışan “" . $newClientName . "” kaydedildi. ";
            }
        }

        // Danışan bilgilerini al
        $stmt = $db->prepare("SELECT name, phone FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch();

        if (!$client) {
            $db->rollBack();
            $_SESSION['error'] = "Danışan bulunamadı.";
            header('Location: ' . $redirect);
            exit();
        }

        // Randevuyu ekle
        $stmt = $db->prepare("INSERT INTO appointments (client_id, appointment_date, appointment_time, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$client_id, $date, $time, $notes]);

        $db->commit();

        if ($clientWarning !== '') {
            $_SESSION['warning'] = $clientWarning;
        }

        // Randevu tarihini kontrol et
        $appointment_datetime = strtotime($date . ' ' . $time);
        $current_datetime = strtotime('now');

        if ($appointment_datetime < $current_datetime) {
            // Geçmiş tarih için SMS gönderme
            $_SESSION['success'] = $clientNotice . "Geçmiş tarihli randevu başarıyla eklendi.";
        } else {
            // SMS aktif mi kontrol et
            if (!EnvConfig::getBool('SMS_ENABLED', true)) {
                $_SESSION['success'] = $clientNotice . "Randevu başarıyla eklendi. (SMS gönderimi devre dışı)";
            } else {
                // Sadece gelecek randevular için SMS gönder
                if (sendAppointmentConfirmation($client['name'], $client['phone'], $date, $time)) {
                    $_SESSION['success'] = $clientNotice . "Randevu başarıyla eklendi ve danışana SMS gönderildi.";
                } else {
                    $_SESSION['warning'] = trim($clientWarning . " Randevu eklendi fakat SMS gönderilemedi.");
                    if ($clientNotice !== '') {
                        $_SESSION['success'] = trim($clientNotice);
                    }
                }
            }
        }
    } catch(PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error'] = "Bir hata oluştu: " . $e->getMessage();
    }
}

// Yönlendirme
header('Location: ' . $redirect);
exit();
?>
