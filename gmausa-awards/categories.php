<?php
$pageTitle = 'Categories';
require_once __DIR__ . '/includes/header.php';

$usBased = db()->query(
    "SELECT * FROM award_categories WHERE category_group = 'us_based' ORDER BY display_order ASC, name ASC"
)->fetchAll();
$ghanaBased = db()->query(
    "SELECT * FROM award_categories WHERE category_group = 'ghana_based' ORDER BY display_order ASC, name ASC"
)->fetchAll();
?>

<header class="gma-hero text-center py-5">
  <div class="container">
    <p class="gma-eyebrow mb-2">The Awards</p>
    <h1 class="mb-3">Award Categories</h1>
    <p class="lead mx-auto">GMA-USA recognizes excellence across 17 US-based categories and 14 Ghana-based categories, as published on the official Categories &amp; Definitions page.</p>
  </div>
</header>

<section class="gma-section">
  <div class="container">
    <p class="gma-eyebrow mb-1"><i class="bi bi-flag-fill"></i> US-Based Categories</p>
    <h2 class="gma-section-title mb-4">Diaspora Awards</h2>
    <?php if (!$usBased): ?>
      <p class="text-secondary">No categories added yet.</p>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($usBased as $cat): ?>
          <div class="col-md-6 col-lg-4">
            <div class="gma-card p-3 h-100">
              <div class="d-flex align-items-start gap-2">
                <i class="bi bi-trophy-fill text-warning fs-4"></i>
                <div>
                  <div class="fw-bold"><?= e($cat['name']) ?></div>
                  <?php if ($cat['description']): ?><p class="text-secondary small mb-0"><?= e($cat['description']) ?></p><?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="gma-section border-bottom-0">
  <div class="container">
    <p class="gma-eyebrow mb-1"><i class="bi bi-flag-fill"></i> Ghana-Based Categories</p>
    <h2 class="gma-section-title mb-4">Industry Awards</h2>
    <?php if (!$ghanaBased): ?>
      <p class="text-secondary">No categories added yet.</p>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($ghanaBased as $cat): ?>
          <div class="col-md-6 col-lg-4">
            <div class="gma-card p-3 h-100">
              <div class="d-flex align-items-start gap-2">
                <i class="bi bi-trophy-fill" style="color: var(--gma-green);"></i>
                <div>
                  <div class="fw-bold"><?= e($cat['name']) ?></div>
                  <?php if ($cat['description']): ?><p class="text-secondary small mb-0"><?= e($cat['description']) ?></p><?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="gma-disclaimer mt-4">
      <i class="bi bi-info-circle me-1"></i>
      As published on the official Categories &amp; Definitions page, the USA section is headed "19 Categories" though only 17 are listed, and a "Song Writer of the Year" entry appears twice under Ghana categories. These are reproduced as published rather than corrected.
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
