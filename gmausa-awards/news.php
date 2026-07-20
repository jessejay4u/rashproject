<?php
$pageTitle = 'News';
require_once __DIR__ . '/includes/header.php';

$perPage = 9;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$total = (int) db()->query("SELECT COUNT(*) FROM news_articles WHERE status = 'published'")->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$stmt = db()->prepare(
    "SELECT title, slug, excerpt, image_path, published_at
     FROM news_articles
     WHERE status = 'published'
     ORDER BY published_at DESC
     LIMIT :limit OFFSET :offset"
);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll();
?>

<header class="gma-hero text-center py-5">
  <div class="container">
    <p class="gma-eyebrow mb-2">Newsroom</p>
    <h1 class="mb-0">Latest News &amp; Updates</h1>
  </div>
</header>

<section class="gma-section border-bottom-0">
  <div class="container">
    <?php if (!$articles): ?>
      <p class="text-secondary text-center">No published articles yet. Check back soon.</p>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($articles as $article): ?>
          <div class="col-md-6 col-lg-4">
            <a href="news-article.php?slug=<?= urlencode($article['slug']) ?>" class="text-decoration-none">
              <div class="gma-card">
                <div class="gma-card__img">
                  <?php if ($article['image_path']): ?>
                    <img src="<?= e(uploads_url($article['image_path'])) ?>" alt="<?= e($article['title']) ?>">
                  <?php else: ?>
                    <i class="bi bi-image"></i>
                  <?php endif; ?>
                </div>
                <div class="gma-card__body">
                  <div class="gma-card__meta"><?= e(format_date($article['published_at'])) ?></div>
                  <div class="gma-card__title text-white"><?= e($article['title']) ?></div>
                  <p class="text-secondary small mb-0"><?= e($article['excerpt']) ?></p>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <nav class="mt-5" aria-label="News pagination">
          <ul class="pagination justify-content-center">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
              <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="news.php?page=<?= $p ?>"><?= $p ?></a>
              </li>
            <?php endfor; ?>
          </ul>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
