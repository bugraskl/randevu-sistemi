<?php
session_start();
require_once 'config/database.php';

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

// Header'ı dahil et
include 'includes/header.php';
?>

<body class="<?php echo $themeClass; ?>" data-page="clients">
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Page Content -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-secondary">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="ms-auto">
                        <button type="button" id="themeToggle" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-moon-fill"></i>
                        </button>
                        <a href="auth/logout" class="btn btn-outline-danger">
                            <i class="bi bi-box-arrow-right"></i> Çıkış Yap
                        </a>
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Danışanlarım</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
                                <i class="bi bi-person-plus"></i> Yeni Danışan
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="input-group">
                                <input type="text" id="searchInput" class="form-control" placeholder="Danışan ara..." autocomplete="off">
                                <button class="btn btn-primary" type="button" id="searchButton">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Ad Soyad</th>
                                        <th>Telefon</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td>
                                            <a href="client-details?id=<?php echo $client['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($client['name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($client['phone']); ?></td>
                                        <td>
                                            <a href="client-details?id=<?php echo $client['id']; ?>" type="button" class="btn btn-sm btn-dark">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editClientModal<?php echo $client['id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteClientModal<?php echo $client['id']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Sayfalama -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Sayfalama" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Önceki">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);

                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
                                    echo '<a class="page-link" href="?page=' . $i . '">' . $i . '</a>';
                                    echo '</li>';
                                }

                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                                }
                                ?>

                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Sonraki">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <?php foreach ($clients as $client): ?>
    <!-- Düzenleme Modal -->
    <div class="modal fade" id="editClientModal<?php echo $client['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Danışan Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="process/edit-client" method="POST" class="needs-validation" novalidate id="editClientForm<?php echo $client['id']; ?>">
                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                        <div class="mb-3">
                            <label for="name<?php echo $client['id']; ?>" class="form-label">Ad Soyad</label>
                            <input type="text" class="form-control" id="name<?php echo $client['id']; ?>" name="name" value="<?php echo htmlspecialchars($client['name']); ?>" required>
                            <div class="invalid-feedback">
                                Lütfen ad soyad giriniz.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="phone<?php echo $client['id']; ?>" class="form-label">Telefon</label>
                            <input type="tel" class="form-control" id="phone<?php echo $client['id']; ?>" name="phone" value="<?php echo htmlspecialchars($client['phone']); ?>" required pattern="[0-9]{10,11}">
                            <div class="invalid-feedback">
                                Lütfen geçerli bir telefon numarası giriniz (10-11 haneli).
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email<?php echo $client['id']; ?>" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="email<?php echo $client['id']; ?>" name="email" value="<?php echo htmlspecialchars($client['email']); ?>">
                            <div class="invalid-feedback">
                                Lütfen geçerli bir e-posta adresi giriniz.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address<?php echo $client['id']; ?>" class="form-label">Adres</label>
                            <input type="text" class="form-control" id="address<?php echo $client['id']; ?>" name="address" value="<?php echo htmlspecialchars($client['address']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="notes<?php echo $client['id']; ?>" class="form-label">Notlar</label>
                            <textarea class="form-control" id="notes<?php echo $client['id']; ?>" name="notes" rows="3"><?php echo htmlspecialchars($client['notes']); ?></textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" class="btn btn-primary" data-original-text="Kaydet">Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Silme Onay Modal -->
    <div class="modal fade" id="deleteClientModal<?php echo $client['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Danışan Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Bu danışanı silmek istediğinizden emin misiniz?</p>
                    <p><strong>Danışan:</strong> <?php echo htmlspecialchars($client['name']); ?></p>
                    <p><strong>Telefon:</strong> <?php echo htmlspecialchars($client['phone']); ?></p>
                    <p><strong>E-posta:</strong> <?php echo htmlspecialchars($client['email']); ?></p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Uyarı:</strong> Bu işlem geri alınamaz ve danışanın tüm randevuları da silinecektir.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <form action="process/delete-client" method="POST" class="d-inline">
                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                        <button type="submit" class="btn btn-danger">Sil</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Yeni Danışan Modal -->
    <div class="modal fade" id="addClientModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Danışan Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="process/add-client" method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label">Ad Soyad</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="invalid-feedback">
                                Lütfen danışanın adını ve soyadını giriniz.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Telefon</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required pattern="[0-9]{10,11}">
                            <div class="invalid-feedback">
                                Lütfen geçerli bir telefon numarası giriniz (10-11 haneli).
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="email" name="email">
                            <div class="invalid-feedback">
                                Lütfen geçerli bir e-posta adresi giriniz.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Adres</label>
                            <input type="text" class="form-control" id="address" name="address">
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notlar</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">İptal</button>
                            <button type="submit" class="btn btn-success" data-original-text="Ekle">Ekle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Arama Sonuçları Modal -->
    <div class="modal fade" id="searchResultsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Danışan Arama Sonuçları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="searchResults" class="list-group">
                        <!-- Arama sonuçları buraya dinamik olarak eklenecek -->
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?> 