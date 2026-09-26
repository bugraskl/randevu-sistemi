<?php
// Aktif sayfayı belirle
$current_page = substr(basename($_SERVER['PHP_SELF']), 0, -4);

// Kullanıcı rolünü ve adını al
$isAdmin = false;
$currentUserName = '';
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../config/database.php';
        $stmt = $db->prepare("SELECT name, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        $isAdmin = ($user && $user['role'] === 'admin');
        $currentUserName = $user['name'] ?? '';
    } catch(Exception $e) {
        // Hata durumunda admin yetkisi verme
        $isAdmin = false;
    }
}

// Ad baş harfleri (avatar)
$nameParts = preg_split('/\s+/u', trim($currentUserName));
$currentUserInitials = '';
foreach (array_slice(array_filter($nameParts), 0, 2) as $part) {
    $currentUserInitials .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
}

$isDark = isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark';

// Sekme gruplaması: danışan detayı "Danışanlar", giderler "Kasa" altında
$tabFor = [
    'dashboard' => 'today',
    'appointments' => 'appointments',
    'clients' => 'clients',
    'client-details' => 'clients',
    'payments' => 'cash',
    'expenses' => 'cash',
];
$activeTab = $tabFor[$current_page] ?? 'menu';

$railLinks = [
    ['page' => 'dashboard', 'icon' => 'bi-sun', 'label' => 'Bugün', 'admin' => false],
    ['page' => 'appointments', 'icon' => 'bi-calendar3', 'label' => 'Randevular', 'admin' => false],
    ['page' => 'clients', 'icon' => 'bi-people', 'label' => 'Danışanlar', 'admin' => false, 'also' => ['client-details']],
    ['page' => 'payments', 'icon' => 'bi-wallet2', 'label' => 'Ödemeler', 'admin' => false],
    ['page' => 'expenses', 'icon' => 'bi-receipt', 'label' => 'Giderler', 'admin' => false],
];
$manageLinks = [
    ['page' => 'app-settings', 'icon' => 'bi-sliders', 'label' => 'Seans Ayarları', 'admin' => true],
    ['page' => 'reports', 'icon' => 'bi-bar-chart-line', 'label' => 'Raporlar', 'admin' => true],
    ['page' => 'sms-settings', 'icon' => 'bi-chat-left-text', 'label' => 'SMS Ayarları', 'admin' => true],
    ['page' => 'user-management', 'icon' => 'bi-person-gear', 'label' => 'Kullanıcı Yönetimi', 'admin' => true],
    ['page' => 'user-settings', 'icon' => 'bi-gear', 'label' => 'Hesap Ayarları', 'admin' => false],
];

$isActiveLink = function ($link) use ($current_page) {
    return $current_page === $link['page'] || in_array($current_page, $link['also'] ?? [], true);
};

// Yeni randevu: randevular sayfasında formu açar, diğer sayfalarda oraya götürür
$newAppointmentAttrs = $current_page === 'appointments'
    ? 'type="button" data-bs-toggle="modal" data-bs-target="#addAppointmentModal"'
    : 'href="appointments?action=new' . ($current_page === 'dashboard' ? '&amp;from=dashboard' : '') . '"';
$newAppointmentTag = $current_page === 'appointments' ? 'button' : 'a';
?>
<a class="visually-hidden-focusable btn btn-primary position-fixed top-0 start-0 m-2" style="z-index: 2000;" href="#content">İçeriğe geç</a>

<!-- Masaüstü yan rayı -->
<nav id="sidebar" aria-label="Ana menü">
    <a class="rail-brand" href="dashboard">
        <span class="rail-brand-mark" aria-hidden="true"><i class="bi bi-clock"></i></span>
        <span class="rail-brand-name">Randevu<small>Yönetim Sistemi</small></span>
    </a>

    <<?php echo $newAppointmentTag; ?> class="btn btn-on-wool-solid btn-block rail-new" <?php echo $newAppointmentAttrs; ?>>
        <i class="bi bi-plus-lg" aria-hidden="true"></i> Yeni randevu
    </<?php echo $newAppointmentTag; ?>>

    <ul class="rail-nav">
        <?php foreach ($railLinks as $link): ?>
        <li>
            <a href="<?php echo $link['page']; ?>" class="rail-link <?php echo $isActiveLink($link) ? 'active' : ''; ?>" <?php echo $isActiveLink($link) ? 'aria-current="page"' : ''; ?>>
                <i class="bi <?php echo $link['icon']; ?>" aria-hidden="true"></i> <?php echo $link['label']; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <p class="rail-group-label">Yönetim</p>
    <ul class="rail-nav">
        <?php foreach ($manageLinks as $link): if ($link['admin'] && !$isAdmin) continue; ?>
        <li>
            <a href="<?php echo $link['page']; ?>" class="rail-link <?php echo $isActiveLink($link) ? 'active' : ''; ?>" <?php echo $isActiveLink($link) ? 'aria-current="page"' : ''; ?>>
                <i class="bi <?php echo $link['icon']; ?>" aria-hidden="true"></i> <?php echo $link['label']; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="rail-foot">
        <div class="rail-user">
            <strong><?php echo htmlspecialchars($currentUserName); ?></strong>
            <?php echo $isAdmin ? 'Yönetici' : 'Kullanıcı'; ?>
        </div>
        <button type="button" class="rail-link border-0 bg-transparent w-100 text-start" data-theme-toggle>
            <i class="bi <?php echo $isDark ? 'bi-sun' : 'bi-moon'; ?>" aria-hidden="true" data-theme-icon></i>
            <span data-theme-label><?php echo $isDark ? 'Açık tema' : 'Koyu tema'; ?></span>
        </button>
        <a href="auth/logout" class="rail-link">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Çıkış yap
        </a>
    </div>
</nav>

<!-- Telefon: alt sekme çubuğu (ortada yeni randevu; menü üst çubukta) -->
<nav class="tabbar" aria-label="Sekmeler">
    <a href="dashboard" class="tab <?php echo $activeTab === 'today' ? 'active' : ''; ?>" <?php echo $activeTab === 'today' ? 'aria-current="page"' : ''; ?>>
        <span class="tab-icon"><i class="bi bi-sun" aria-hidden="true"></i></span>Bugün
    </a>
    <a href="appointments" class="tab <?php echo $activeTab === 'appointments' ? 'active' : ''; ?>" <?php echo $activeTab === 'appointments' ? 'aria-current="page"' : ''; ?>>
        <span class="tab-icon"><i class="bi bi-calendar3" aria-hidden="true"></i></span>Randevular
    </a>
    <<?php echo $newAppointmentTag; ?> class="tab tab-new" <?php echo $newAppointmentAttrs; ?>>
        <span class="tab-new-icon"><i class="bi bi-plus-lg" aria-hidden="true"></i></span>Randevu
    </<?php echo $newAppointmentTag; ?>>
    <a href="clients" class="tab <?php echo $activeTab === 'clients' ? 'active' : ''; ?>" <?php echo $activeTab === 'clients' ? 'aria-current="page"' : ''; ?>>
        <span class="tab-icon"><i class="bi bi-people" aria-hidden="true"></i></span>Danışanlar
    </a>
    <a href="payments" class="tab <?php echo $activeTab === 'cash' ? 'active' : ''; ?>" <?php echo $activeTab === 'cash' ? 'aria-current="page"' : ''; ?>>
        <span class="tab-icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span>Kasa
    </a>
</nav>

<!-- Telefon: menü paneli -->
<div class="offcanvas offcanvas-bottom menu-sheet" tabindex="-1" id="menuSheet" aria-labelledby="menuSheetTitle">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title" id="menuSheetTitle">Menü</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Kapat"></button>
    </div>
    <div class="offcanvas-body pt-0">
        <div class="menu-user">
            <span class="avatar" aria-hidden="true"><?php echo htmlspecialchars($currentUserInitials); ?></span>
            <div>
                <strong class="d-block"><?php echo htmlspecialchars($currentUserName); ?></strong>
                <small><?php echo $isAdmin ? 'Yönetici' : 'Kullanıcı'; ?></small>
            </div>
        </div>

        <ul class="menu-list">
            <li><a href="payments" class="menu-link <?php echo $current_page === 'payments' ? 'active' : ''; ?>"><i class="bi bi-wallet2" aria-hidden="true"></i> Ödemeler <i class="bi bi-chevron-right row-chevron" aria-hidden="true"></i></a></li>
            <li><a href="expenses" class="menu-link <?php echo $current_page === 'expenses' ? 'active' : ''; ?>"><i class="bi bi-receipt" aria-hidden="true"></i> Giderler <i class="bi bi-chevron-right row-chevron" aria-hidden="true"></i></a></li>
            <?php foreach ($manageLinks as $link): if ($link['admin'] && !$isAdmin) continue; ?>
            <li><a href="<?php echo $link['page']; ?>" class="menu-link <?php echo $current_page === $link['page'] ? 'active' : ''; ?>"><i class="bi <?php echo $link['icon']; ?>" aria-hidden="true"></i> <?php echo $link['label']; ?> <i class="bi bi-chevron-right row-chevron" aria-hidden="true"></i></a></li>
            <?php endforeach; ?>
        </ul>

        <ul class="menu-list">
            <li>
                <button type="button" class="menu-link" data-theme-toggle>
                    <i class="bi <?php echo $isDark ? 'bi-sun' : 'bi-moon'; ?>" aria-hidden="true" data-theme-icon></i>
                    <span data-theme-label><?php echo $isDark ? 'Açık tema' : 'Koyu tema'; ?></span>
                </button>
            </li>
            <li><a href="auth/logout" class="menu-link is-danger"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Çıkış yap</a></li>
        </ul>
    </div>
</div>
