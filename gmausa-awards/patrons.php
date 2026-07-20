<?php
$pageTitle = 'Life Patrons';
require_once __DIR__ . '/includes/header.php';

$patrons = db()->query('SELECT * FROM patrons ORDER BY display_order ASC')->fetchAll();
?>

<header class="gma-hero text-center py-5">
  <div class="container">
    <p class="gma-eyebrow mb-2">Ghana Music Awards USA Pillars</p>
    <h1 class="mb-0">Life Patrons</h1>
  </div>
</header>

<section class="gma-section border-bottom-0">
  <div class="container">
    <?php if (!$patrons): ?>
      <p class="text-secondary text-center">No patrons added yet.</p>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($patrons as $p): ?>
          <div class="col-lg-6">
            <div class="gma-card p-4 h-100">
              <div class="d-flex align-items-start gap-3">
                <?php if ($p['photo_path']): ?>
                  <img src="<?= e(uploads_url($p['photo_path'])) ?>" alt="<?= e($p['name']) ?>" class="rounded-circle flex-shrink-0" style="width:72px;height:72px;object-fit:cover;">
                <?php else: ?>
                  <div class="gma-avatar-placeholder flex-shrink-0" style="width:72px;height:72px;font-size:1.3rem;"><?= e(strtoupper(substr($p['name'], 0, 1))) ?></div>
                <?php endif; ?>
                <div>
                  <div class="fw-bold fs-5"><?= e($p['name']) ?></div>
                  <?php if ($p['role_affiliation']): ?><div class="text-warning small mb-2"><?= e($p['role_affiliation']) ?></div><?php endif; ?>
                  <?php if ($p['bio']): ?><p class="text-secondary small mb-0"><?= e($p['bio']) ?></p><?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
