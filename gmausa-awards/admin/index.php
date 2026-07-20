<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/admin-header.php';

$stats = [
    'Published Articles' => (int) db()->query("SELECT COUNT(*) FROM news_articles WHERE status = 'published'")->fetchColumn(),
    'Draft Articles'      => (int) db()->query("SELECT COUNT(*) FROM news_articles WHERE status = 'draft'")->fetchColumn(),
    'Active Updates'      => (int) db()->query('SELECT COUNT(*) FROM updates WHERE is_active = 1')->fetchColumn(),
    'Categories'          => (int) db()->query('SELECT COUNT(*) FROM award_categories')->fetchColumn(),
    'Nominees'            => (int) db()->query('SELECT COUNT(*) FROM nominees')->fetchColumn(),
    'Unread Messages'     => (int) db()->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn(),
    'New Nominations'     => (int) db()->query("SELECT COUNT(*) FROM nomination_submissions WHERE status = 'new'")->fetchColumn(),
    'Media Files'         => (int) db()->query('SELECT COUNT(*) FROM media_library')->fetchColumn(),
];

$recentArticles = db()->query('SELECT title, status, updated_at FROM news_articles ORDER BY updated_at DESC LIMIT 5')->fetchAll();
?>

<div class="row g-3 mb-4">
  <?php foreach ($stats as $label => $value): ?>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-number"><?= $value ?></div>
        <div class="text-secondary small"><?= e($label) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="stat-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 mb-0">Recently Updated Articles</h2>
        <a href="news-form.php" class="btn btn-sm btn-dark"><i class="bi bi-plus-lg"></i> New Article</a>
      </div>
      <?php if (!$recentArticles): ?>
        <p class="text-secondary small mb-0">No articles yet.</p>
      <?php else: ?>
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Title</th><th>Status</th><th>Updated</th></tr></thead>
          <tbody>
            <?php foreach ($recentArticles as $a): ?>
              <tr>
                <td><?= e($a['title']) ?></td>
                <td><span class="badge text-bg-<?= $a['status'] === 'published' ? 'success' : 'secondary' ?>"><?= e($a['status']) ?></span></td>
                <td class="text-secondary small"><?= e(format_date($a['updated_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="stat-card">
      <h2 class="h6 mb-3">Quick Actions</h2>
      <div class="d-grid gap-2">
        <a href="news-form.php" class="btn btn-outline-dark btn-sm"><i class="bi bi-newspaper"></i> Post News Article</a>
        <a href="updates.php" class="btn btn-outline-dark btn-sm"><i class="bi bi-megaphone-fill"></i> Post Latest Update</a>
        <a href="media.php" class="btn btn-outline-dark btn-sm"><i class="bi bi-images"></i> Upload Images</a>
        <a href="nominees.php" class="btn btn-outline-dark btn-sm"><i class="bi bi-people-fill"></i> Manage Nominees</a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
