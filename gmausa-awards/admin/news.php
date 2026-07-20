<?php
$pageTitle = 'News Articles';
require_once __DIR__ . '/includes/admin-header.php';

$articles = db()->query('SELECT * FROM news_articles ORDER BY updated_at DESC')->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <p class="text-secondary mb-0"><?= count($articles) ?> article(s)</p>
  <a href="news-form.php" class="btn btn-dark btn-sm"><i class="bi bi-plus-lg"></i> New Article</a>
</div>

<div class="stat-card p-0">
  <table class="table align-middle mb-0">
    <thead>
      <tr><th>Image</th><th>Title</th><th>Status</th><th>Published</th><th class="text-end">Actions</th></tr>
    </thead>
    <tbody>
      <?php if (!$articles): ?>
        <tr><td colspan="5" class="text-center text-secondary py-4">No articles yet. Create your first one.</td></tr>
      <?php endif; ?>
      <?php foreach ($articles as $a): ?>
        <tr>
          <td>
            <?php if ($a['image_path']): ?>
              <img src="<?= e(uploads_url($a['image_path'])) ?>" class="thumb-preview" alt="">
            <?php else: ?>
              <div class="thumb-preview bg-light d-flex align-items-center justify-content-center text-secondary"><i class="bi bi-image"></i></div>
            <?php endif; ?>
          </td>
          <td><?= e($a['title']) ?></td>
          <td><span class="badge text-bg-<?= $a['status'] === 'published' ? 'success' : 'secondary' ?>"><?= e($a['status']) ?></span></td>
          <td class="text-secondary small"><?= e(format_date($a['published_at'])) ?></td>
          <td class="text-end">
            <a href="news-form.php?id=<?= (int) $a['id'] ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
            <form method="post" action="news-delete.php" class="d-inline" onsubmit="return confirm('Delete this article? This cannot be undone.');">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
