<?php
$pageTitle = 'Charity';
require_once __DIR__ . '/includes/header.php';

$photos = db()->query(
    "SELECT * FROM gallery_images WHERE group_key = 'charity' ORDER BY display_order ASC, id DESC"
)->fetchAll();
?>

<header class="gma-hero text-center py-5">
  <div class="container">
    <p class="gma-eyebrow mb-2">Giving Back</p>
    <h1 class="mb-0">Charity</h1>
  </div>
</header>

<section class="gma-section border-bottom-0">
  <div class="container">
    <div class="row justify-content-center mb-5">
      <div class="col-lg-8 text-center">
        <p class="fs-5 text-secondary"><?= e(setting('charity_text')) ?></p>
      </div>
    </div>

    <p class="gma-eyebrow mb-1"><i class="bi bi-images"></i> Pictures</p>
    <h2 class="gma-section-title mb-4">Charity Activity</h2>
    <?php if (!$photos): ?>
      <div class="gma-card p-5 text-center">
        <i class="bi bi-camera display-4 text-warning mb-3"></i>
        <p class="text-secondary mb-0">Photos from GMA-USA's charitable activities are coming soon. Upload them via the admin dashboard.</p>
      </div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($photos as $img): ?>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="gma-card p-0">
              <img src="<?= e(uploads_url($img['image_path'])) ?>" alt="<?= e($img['caption']) ?>" class="img-fluid" style="aspect-ratio:1/1;object-fit:cover;width:100%;">
              <?php if ($img['caption']): ?><div class="p-2 small text-secondary"><?= e($img['caption']) ?></div><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
