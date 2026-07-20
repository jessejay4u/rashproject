<?php
$pageTitle = 'Gallery';
require_once __DIR__ . '/includes/header.php';

$groups = [
    'gmausa_at_6'          => 'GMAUSA @ 6 Pictures',
    'nominee_announcement' => 'Nominee Announcement Pictures',
    'more_recent'          => 'More Recent Pictures',
    'recent'               => 'Recent Pictures',
    'old'                  => 'Old Pictures',
];

$stmt = db()->query("SELECT * FROM gallery_images WHERE group_key != 'charity' ORDER BY group_key ASC, display_order ASC, id DESC");
$byGroup = [];
foreach ($stmt->fetchAll() as $row) {
    $byGroup[$row['group_key']][] = $row;
}
?>

<header class="gma-hero text-center py-5">
  <div class="container">
    <p class="gma-eyebrow mb-2">Photo Archive</p>
    <h1 class="mb-0">Gallery</h1>
  </div>
</header>

<?php foreach ($groups as $key => $label): ?>
  <section class="gma-section <?= $key === 'old' ? 'border-bottom-0' : '' ?>">
    <div class="container">
      <h2 class="gma-section-title fs-4 mb-4"><?= e($label) ?></h2>
      <?php if (empty($byGroup[$key])): ?>
        <div class="gma-card p-4 text-center">
          <p class="text-secondary small mb-0"><i class="bi bi-camera me-1"></i> Photos coming soon &mdash; upload them via the admin dashboard.</p>
        </div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($byGroup[$key] as $img): ?>
            <div class="col-6 col-md-4 col-lg-3">
              <div class="gma-card p-0">
                <img src="<?= e(uploads_url($img['image_path'])) ?>" alt="<?= e($img['caption']) ?>" class="img-fluid" style="aspect-ratio:1/1;object-fit:cover;width:100%;">
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
