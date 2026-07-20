<?php
$pageTitle = 'Nominees';
require_once __DIR__ . '/includes/header.php';

$years = db()->query('SELECT DISTINCT year FROM nominees ORDER BY year DESC')->fetchAll(PDO::FETCH_COLUMN);
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : ($years[0] ?? (int) date('Y'));

$stmt = db()->prepare(
    'SELECT n.*, c.name AS category_name, c.category_group
     FROM nominees n
     JOIN award_categories c ON c.id = n.category_id
     WHERE n.year = ?
     ORDER BY c.display_order ASC, n.is_winner DESC, n.name ASC'
);
$stmt->execute([$selectedYear]);
$nominees = $stmt->fetchAll();

$grouped = [];
foreach ($nominees as $row) {
    $grouped[$row['category_name']][] = $row;
}
?>

<header class="gma-hero text-center py-5">
  <div class="container">
    <p class="gma-eyebrow mb-2">2026 Nominees</p>
    <h1 class="mb-3">Nominees &amp; Winners</h1>
    <?php if ($years): ?>
      <div class="d-flex justify-content-center gap-2 flex-wrap">
        <?php foreach ($years as $y): ?>
          <a href="nominees.php?year=<?= (int) $y ?>" class="btn btn-sm <?= $y == $selectedYear ? 'gma-btn-gold' : 'gma-btn-outline' ?>"><?= (int) $y ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</header>

<section class="gma-section border-bottom-0">
  <div class="container">
    <?php if (!$grouped): ?>
      <div class="gma-card p-5 text-center">
        <i class="bi bi-hourglass-split display-4 text-warning mb-3"></i>
        <p class="text-secondary mb-1">Nominees haven't been added yet.</p>
        <p class="text-secondary small mb-0">Add nominees per category and year from the admin dashboard once the official list is available.</p>
      </div>
    <?php else: ?>
      <?php foreach ($grouped as $categoryName => $rows): ?>
        <div class="mb-5">
          <h2 class="gma-section-title fs-4 mb-3"><?= e($categoryName) ?></h2>
          <div class="row g-3">
            <?php foreach ($rows as $n): ?>
              <div class="col-md-6 col-lg-4">
                <div class="gma-nominee-card <?= $n['is_winner'] ? 'is-winner' : '' ?> d-flex align-items-center gap-3">
                  <?php if ($n['image_path']): ?>
                    <img src="<?= e(uploads_url($n['image_path'])) ?>" alt="<?= e($n['name']) ?>" class="rounded-circle" style="width:64px;height:64px;object-fit:cover;">
                  <?php else: ?>
                    <div class="gma-avatar-placeholder"><?= e(strtoupper(substr($n['name'], 0, 1))) ?></div>
                  <?php endif; ?>
                  <div>
                    <div class="fw-bold"><?= e($n['name']) ?></div>
                    <?php if ($n['is_winner']): ?><span class="badge text-bg-warning text-dark"><i class="bi bi-trophy-fill"></i> Winner</span><?php else: ?><span class="text-secondary small">Nominee</span><?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
