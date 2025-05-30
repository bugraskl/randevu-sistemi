<?php
// Aktif sayfayı belirle
$current_page = substr(basename($_SERVER['PHP_SELF']), 0, -4);

// Kullanıcı rolünü kontrol et
$isAdmin = false;
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        $isAdmin = ($user && $user['role'] === 'admin');
    } catch(Exception $e) {
        // Hata durumunda admin yetkisi verme
        $isAdmin = false;
    }
}
?>
<!-- Sidebar Overlay -->
<div class="sidebar-overlay"></div>

<!-- Sidebar -->
<nav id="sidebar" class="bg-dark ">
    <div class="sidebar-header p-4">
        <h3><a href="dashboard">Randevu Yönetim Sistemi</a></h3>
    </div>

    <ul class="list-unstyled components">
        <li class="<?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
            <a href="dashboard" class="d-flex align-items-center p-3  text-decoration-none">
                <i class="bi bi-speedometer2 me-2"></i>
                Anasayfa
            </a>
        </li>
        <li class="<?php echo $current_page === 'appointments' ? 'active' : ''; ?>">
            <a href="appointments" class="d-flex align-items-center p-3  text-decoration-none">
                <i class="bi bi-calendar-check me-2"></i>
                Randevularım
            </a>
        </li>
        <li class="<?php echo $current_page === 'clients' ? 'active' : ''; ?>">
            <a href="clients" class="d-flex align-items-center p-3  text-decoration-none">
                <i class="bi bi-people me-2"></i>
                Danışanlarım
            </a>
        </li>
        <li class="<?php echo $current_page === 'payments' ? 'active' : ''; ?>">
            <a href="payments" class="d-flex align-items-center p-3  text-decoration-none">
                <i class="bi bi-cash me-2"></i>
                Ödemeler
            </a>
        </li>
        <?php if ($isAdmin): ?>
        <li class="<?php echo $current_page === 'reports' ? 'active' : ''; ?>">
            <a href="reports" class="d-flex align-items-center p-3  text-decoration-none">
                <i class="bi bi-graph-up me-2"></i>
                Raporlar
            </a>
        </li>
        <li class="<?php echo $current_page === 'sms-settings' ? 'active' : ''; ?>">
            <a href="sms-settings" class="d-flex align-items-center p-3  text-decoration-none">
                <i class="bi bi-chat-dots me-2"></i>
                SMS Ayarları
            </a>
        </li>
        <?php endif; ?>
        <li class="<?php echo $current_page === 'user-settings' ? 'active' : ''; ?>">
            <a href="user-settings" class="d-flex align-items-center p-3  text-decoration-none">
                <i class="bi bi-gear me-2"></i>
                Kullanıcı Ayarları
            </a>
        </li>
        <?php if ($isAdmin): ?>
        <li class="<?php echo $current_page === 'user-management' ? 'active' : ''; ?>">
            <a href="user-management" class="d-flex align-items-center p-3  text-decoration-none">
                <i class="bi bi-person me-2"></i>
                Kullanıcı Yönetimi
            </a>
        </li>
        <?php endif; ?>
    </ul>
</nav> 