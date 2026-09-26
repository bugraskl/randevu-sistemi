<?php
session_start();
require_once 'config/database.php';
require_once 'includes/phone.php';

// Tema kontrolü
if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
    $themeClass = 'dark';
} else {
    $themeClass = '';
}

if (!isset($_SESSION['user_id'])) {
    header('Location: index');
    exit();
}

// Sayfa numarası
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15; // Sayfa başına gösterilecek kayıt sayısı
$offset = ($page - 1) * $limit;

// Toplam kayıt sayısını al
try {
    $stmt = $db->query("SELECT COUNT(*) FROM clients");
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Danışan listesini veritabanından çek
    $stmt = $db->prepare("SELECT * FROM clients ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $clients = $stmt->fetchAll();
} catch(PDOException $e) {
    $_SESSION['error'] = "Danışan listesi alınırken bir hata oluştu: " . $e->getMessage();
    $clients = [];
    $total_pages = 1;
}

// Görünüm yardımcıları
function clientInitials($name) {
    $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) {
        return '?';
    }
    $picked = count($parts) > 1 ? [reset($parts), end($parts)] : [reset($parts)];
    $out = '';
    foreach ($picked as $part) {
        $ch = mb_substr($part, 0, 1, 'UTF-8');
        $out .= mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $ch), 'UTF-8');
    }
    return $out;
}

function telHref($phone) {
    return 'tel:' . preg_replace('/[^0-9+]/', '', (string) $phone);
}

$total_records = (int) ($total_records ?? 0);

$pageTitle = 'Danışanlar';
$pageSubtitle = $total_records > 0 ? $total_records . ' danışan' : '';
$pageActions = '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addClientModal"><i class="bi bi-plus-lg" aria-hidden="true"></i> Danışan ekle</button>';

// Header'ı dahil et
include 'includes/header.php';
?>
<body class="<?php echo $themeClass; ?>" data-page="clients">
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main id="content" tabindex="-1">
            <?php include 'includes/topbar.php'; ?>

            <div class="page">
                <?php if ($total_records > 0): ?>
                <!-- Arama: yazınca kendiliğinden arar (script.js: initializeClientPage) -->
                <div class="client-search" role="search">
                    <label for="searchInput" class="visually-hidden">Danışan ara</label>
                    <div class="search-row">
                        <div class="search-field">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input type="search" id="searchInput" class="form-control" placeholder="Ad, telefon ya da e-posta" autocomplete="off" enterkeyhint="search" aria-describedby="searchHint">
                        </div>
                        <button class="btn btn-secondary" type="button" id="searchButton">Ara</button>
                    </div>
                    <p class="form-text search-hint" id="searchHint">En az 2 harf yazın; sonuçlar kendiliğinden açılır.</p>
                </div>
                <?php endif; ?>

                <section class="section" aria-labelledby="clientsTitle">
                    <div class="section-head">
                        <h2 class="section-title" id="clientsTitle">Tüm danışanlar</h2>
                        <?php if ($total_records > 0): ?>
                        <span class="section-note">Son eklenen üstte<?php echo $total_pages > 1 ? ' · Sayfa ' . (int) $page . '/' . (int) $total_pages : ''; ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($total_records === 0): ?>
                    <div class="empty">
                        <p class="empty-title">Henüz danışan yok</p>
                        <p>İlk danışanı üstteki “Danışan ekle” ile kaydedin. Kaydettiğiniz danışanlar burada listelenir ve randevu eklerken seçilebilir.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i> Danışan ekle
                        </button>
                    </div>
                    <?php elseif (empty($clients)): ?>
                    <div class="empty">
                        <p class="empty-title">Bu sayfada danışan yok</p>
                        <p>Liste daha kısa; ilk sayfaya dönün.</p>
                        <a href="clients" class="btn btn-secondary">İlk sayfaya dön</a>
                    </div>
                    <?php else: ?>
                    <div class="list client-list">
                        <?php foreach ($clients as $client):
                            $clientId = (int) $client['id'];
                            $clientName = (string) $client['name'];
                            $clientPhone = trim((string) ($client['phone'] ?? ''));
                        ?>
                        <div class="row-item has-avatar">
                            <span class="avatar avatar-sm" aria-hidden="true"><?php echo htmlspecialchars(clientInitials($clientName)); ?></span>
                            <a class="row-main text-decoration-none stretched-link" href="client-details?id=<?php echo $clientId; ?>">
                                <p class="row-title"><span><?php echo htmlspecialchars($clientName); ?></span></p>
                                <p class="row-meta tnum"><?php echo $clientPhone !== '' ? htmlspecialchars(formatPhoneDisplay($clientPhone)) : 'Telefon yok'; ?></p>
                            </a>
                            <div class="row-trail">
                                <?php if ($clientPhone !== ''): ?>
                                <a href="<?php echo htmlspecialchars(telHref($clientPhone)); ?>" class="btn btn-sm btn-secondary" aria-label="<?php echo htmlspecialchars($clientName); ?> adlı danışanı ara">
                                    <i class="bi bi-telephone" aria-hidden="true"></i> Ara
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="list-foot ink-3">Düzenlemek ya da silmek için danışanın kartını açın.</p>
                    <?php endif; ?>

                    <!-- Sayfalama -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Sayfalama" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Önceki</a>
                            </li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
                                echo '<a class="page-link" href="?page=' . $i . '"' . ($i == $page ? ' aria-current="page"' : '') . '>' . $i . '</a>';
                                echo '</li>';
                            }

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                            }
                            ?>

                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Sonraki</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <!-- Modallar: arama sonuçlarındaki "Düzenle" / "Sil" bunları açar (script.js: editClientFromSearch, deleteClientFromSearch) -->
    <?php foreach ($clients as $client): ?>
    <!-- Düzenleme Modal -->
    <div class="modal fade" id="editClientModal<?php echo $client['id']; ?>" tabindex="-1" aria-labelledby="editClientTitle<?php echo $client['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="editClientTitle<?php echo $client['id']; ?>">Danışanı düzenle</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <form action="process/edit-client" method="POST" class="needs-validation" novalidate id="editClientForm<?php echo $client['id']; ?>">
                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                        <div class="field">
                            <label for="name<?php echo $client['id']; ?>" class="form-label">Ad soyad</label>
                            <input type="text" class="form-control" id="name<?php echo $client['id']; ?>" name="name" value="<?php echo htmlspecialchars($client['name']); ?>" required>
                            <div class="invalid-feedback">Ad soyadı yazın.</div>
                        </div>
                        <div class="field">
                            <label for="phone<?php echo $client['id']; ?>" class="form-label">Telefon</label>
                            <input type="tel" class="form-control tnum" id="phone<?php echo $client['id']; ?>" name="phone" value="<?php echo htmlspecialchars($client['phone']); ?>" required>
                            <div class="invalid-feedback">Telefonu 10–11 rakam olarak, boşluksuz yazın (ör. 05321234567).</div>
                        </div>
                        <div class="field">
                            <label for="email<?php echo $client['id']; ?>" class="form-label">E-posta <span class="ink-3">(isteğe bağlı)</span></label>
                            <input type="email" class="form-control" id="email<?php echo $client['id']; ?>" name="email" value="<?php echo htmlspecialchars((string) $client['email']); ?>">
                            <div class="invalid-feedback">Geçerli bir e-posta adresi yazın ya da alanı boş bırakın.</div>
                        </div>
                        <div class="field">
                            <label for="address<?php echo $client['id']; ?>" class="form-label">Adres <span class="ink-3">(isteğe bağlı)</span></label>
                            <input type="text" class="form-control" id="address<?php echo $client['id']; ?>" name="address" value="<?php echo htmlspecialchars((string) $client['address']); ?>">
                        </div>
                        <div class="field">
                            <label for="notes<?php echo $client['id']; ?>" class="form-label">Notlar <span class="ink-3">(isteğe bağlı)</span></label>
                            <textarea class="form-control" id="notes<?php echo $client['id']; ?>" name="notes" rows="3"><?php echo htmlspecialchars((string) $client['notes']); ?></textarea>
                        </div>
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-primary" data-original-text="Kaydet">Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Silme Onay Modal -->
    <div class="modal fade" id="deleteClientModal<?php echo $client['id']; ?>" tabindex="-1" aria-labelledby="deleteClientTitle<?php echo $client['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="deleteClientTitle<?php echo $client['id']; ?>">Danışanı sil</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="sheet-summary">
                        <span class="avatar avatar-sm" aria-hidden="true"><?php echo htmlspecialchars(clientInitials($client['name'])); ?></span>
                        <div>
                            <p class="row-title"><span><?php echo htmlspecialchars($client['name']); ?></span></p>
                            <p class="row-meta tnum"><?php echo htmlspecialchars(formatPhoneDisplay($client['phone'])); ?><?php echo !empty($client['email']) ? ' · ' . htmlspecialchars($client['email']) : ''; ?></p>
                        </div>
                    </div>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>
                        Danışanın bütün randevuları ve ödeme kayıtları da silinir. Bu işlem geri alınamaz.
                    </div>
                    <div class="sheet-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                        <form action="process/delete-client" method="POST">
                            <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                            <button type="submit" class="btn btn-danger">Sil</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Yeni Danışan Modal -->
    <div class="modal fade" id="addClientModal" tabindex="-1" aria-labelledby="addClientTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="addClientTitle">Danışan ekle</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <form action="process/add-client" method="POST" class="needs-validation" novalidate>
                        <div class="field">
                            <label for="name" class="form-label">Ad soyad</label>
                            <input type="text" class="form-control" id="name" name="name" autocomplete="off" required>
                            <div class="invalid-feedback">Danışanın adını ve soyadını yazın.</div>
                        </div>
                        <div class="field">
                            <label for="phone" class="form-label">Telefon</label>
                            <input type="tel" class="form-control tnum" id="phone" name="phone" autocomplete="off" placeholder="0500 123 45 67" required>
                            <div class="invalid-feedback">Telefonu 10–11 rakam olarak, boşluksuz yazın (ör. 05321234567).</div>
                        </div>
                        <div class="field">
                            <label for="email" class="form-label">E-posta <span class="ink-3">(isteğe bağlı)</span></label>
                            <input type="email" class="form-control" id="email" name="email" autocomplete="off">
                            <div class="invalid-feedback">Geçerli bir e-posta adresi yazın ya da alanı boş bırakın.</div>
                        </div>
                        <div class="field">
                            <label for="address" class="form-label">Adres <span class="ink-3">(isteğe bağlı)</span></label>
                            <input type="text" class="form-control" id="address" name="address" autocomplete="off">
                        </div>
                        <div class="field">
                            <label for="notes" class="form-label">Notlar <span class="ink-3">(isteğe bağlı)</span></label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                        <div class="sheet-actions">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-primary" data-original-text="Danışanı kaydet">Danışanı kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Arama Sonuçları Modal (script.js: performClientSearch başlığı ve içeriği doldurur) -->
    <div class="modal fade" id="searchResultsModal" tabindex="-1" aria-labelledby="searchResultsTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="searchResultsTitle">Arama sonuçları</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div id="searchResults">
                        <!-- Arama sonuçları buraya dinamik olarak eklenir -->
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>
