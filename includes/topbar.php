<?php
/**
 * Sayfa üst çubuğu.
 * Kullanım (include etmeden önce):
 *   $pageTitle     Başlık (zorunlu)
 *   $pageSubtitle  Başlığın altındaki kısa satır (isteğe bağlı)
 *   $pageActions   Sağdaki eylemler için HTML (isteğe bağlı)
 *   $pageBack      Geri bağlantısı URL'si (isteğe bağlı)
 *   $appbarClass   Ek sınıf, ör. 'appbar--wool' (isteğe bağlı)
 */
$pageTitle = $pageTitle ?? '';
$pageSubtitle = $pageSubtitle ?? '';
$pageActions = $pageActions ?? '';
$pageBack = $pageBack ?? '';
$appbarClass = $appbarClass ?? '';
?>
<header class="appbar <?php echo htmlspecialchars($appbarClass); ?>" data-appbar>
    <?php if ($pageBack): ?>
    <a href="<?php echo htmlspecialchars($pageBack); ?>" class="btn btn-quiet btn-icon appbar-back" aria-label="Geri">
        <i class="bi bi-chevron-left" aria-hidden="true"></i>
    </a>
    <?php endif; ?>
    <h1 class="appbar-title">
        <?php echo htmlspecialchars($pageTitle); ?>
        <?php if ($pageSubtitle): ?>
        <span class="appbar-sub"><?php echo htmlspecialchars($pageSubtitle); ?></span>
        <?php endif; ?>
    </h1>
    <div class="appbar-actions">
        <?php echo $pageActions; ?>
        <button type="button" class="btn btn-quiet btn-sm appbar-menu" data-bs-toggle="offcanvas" data-bs-target="#menuSheet" aria-controls="menuSheet">
            <i class="bi bi-grid" aria-hidden="true"></i> Menü
        </button>
    </div>
</header>
