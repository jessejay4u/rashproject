<?php
$pageTitle = 'Entry Procedures';
require_once __DIR__ . '/includes/header.php';
?>

<header class="gma-hero text-center py-5">
  <div class="container">
    <p class="gma-eyebrow mb-2">Nominee Entry</p>
    <h1 class="mb-0">Entry Procedures</h1>
  </div>
</header>

<section class="gma-section border-bottom-0">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-6">
        <div class="gma-card p-4 h-100">
          <p class="gma-eyebrow mb-1"><i class="bi bi-flag-fill"></i> USA Categories</p>
          <h2 class="fs-5 fw-bold mb-3">How US-Based Entries Work</h2>
          <p class="text-secondary"><?= nl2br(e(setting('entry_usa_text'))) ?></p>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="gma-card p-4 h-100">
          <p class="gma-eyebrow mb-1" style="color: var(--gma-green);"><i class="bi bi-flag-fill"></i> Ghana Categories</p>
          <h2 class="fs-5 fw-bold mb-3">How Ghana-Based Entries Work</h2>
          <p class="text-secondary"><?= nl2br(e(setting('entry_ghana_text'))) ?></p>
        </div>
      </div>
    </div>
    <div class="text-center mt-4">
      <a href="categories.php" class="btn gma-btn-outline">View Categories &amp; Definitions</a>
      <a href="nominate.php" class="btn gma-btn-gold">Submit a Nomination</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
