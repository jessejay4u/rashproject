<?php
$pageTitle = 'Team';
require_once __DIR__ . '/includes/header.php';

$coreTeam = db()->query(
    "SELECT * FROM team_members WHERE member_type = 'core_team' ORDER BY display_order ASC"
)->fetchAll();
$board = db()->query(
    "SELECT * FROM team_members WHERE member_type = 'board' ORDER BY display_order ASC"
)->fetchAll();

function team_card(array $m): void
{
    $initial = strtoupper(substr($m['name'], 0, 1));
    echo '<div class="col-md-6 col-lg-4"><div class="gma-card p-3 h-100 d-flex align-items-center gap-3">';
    if (!empty($m['photo_path'])) {
        echo '<img src="' . e(uploads_url($m['photo_path'])) . '" alt="' . e($m['name']) . '" class="rounded-circle" style="width:64px;height:64px;object-fit:cover;">';
    } else {
        echo '<div class="gma-avatar-placeholder">' . e($initial) . '</div>';
    }
    echo '<div><div class="fw-bold">' . e($m['name']) . '</div><div class="text-secondary small">' . e($m['role']) . '</div></div>';
    echo '</div></div>';
}
?>

<header class="gma-hero text-center py-5">
  <div class="container">
    <p class="gma-eyebrow mb-2">About GMA-USA</p>
    <h1 class="mb-0">Our Team</h1>
  </div>
</header>

<section class="gma-section">
  <div class="container">
    <p class="gma-eyebrow mb-1"><i class="bi bi-people-fill"></i> Core Team</p>
    <h2 class="gma-section-title mb-4">Leadership</h2>
    <?php if (!$coreTeam): ?>
      <p class="text-secondary">No team members added yet.</p>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($coreTeam as $m): team_card($m); endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="gma-section border-bottom-0">
  <div class="container">
    <p class="gma-eyebrow mb-1"><i class="bi bi-people-fill"></i> Board</p>
    <h2 class="gma-section-title mb-4">Board Members</h2>
    <?php if (!$board): ?>
      <p class="text-secondary">No board members added yet.</p>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($board as $m): team_card($m); endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
