<?php
require_once 'SessionManager.php';
require_once 'Database.php';

function checkAuth() {
    if (isset($_SESSION['user_id'])) {
        return true;
    }

    if (isset($_COOKIE['remember_token'])) {
        $db = new Database();
        $sessionManager = new SessionManager($db->getConnection());
        
        $userId = $sessionManager->validateRememberToken($_COOKIE['remember_token']);
        if ($userId) {
            $_SESSION['user_id'] = $userId;
            return true;
        }
    }

    return false;
}

// Kullanıcının cihazlarını yönetmek için bir sayfa oluşturalım
function renderDevicesPage($userId) {
    $db = new Database();
    $sessionManager = new SessionManager($db->getConnection());
    $devices = $sessionManager->getUserDevices($userId);
    
    echo '<div class="container mt-4">';
    echo '<h2>Bağlı Cihazlar</h2>';
    echo '<div class="table-responsive">';
    echo '<table class="table">';
    echo '<thead><tr><th>Cihaz Bilgisi</th><th>Son Giriş</th><th>Geçerlilik</th><th>İşlem</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($devices as $device) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($device['device_info']) . '</td>';
        echo '<td>' . date('d.m.Y H:i', strtotime($device['created_at'])) . '</td>';
        echo '<td>' . date('d.m.Y H:i', strtotime($device['expires_at'])) . '</td>';
        echo '<td>';
        echo '<form method="POST" action="remove_device.php" style="display: inline;">';
        echo '<input type="hidden" name="token_id" value="' . $device['id'] . '">';
        echo '<button type="submit" class="btn btn-danger btn-sm">Cihazı Kaldır</button>';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table></div></div>';
} 