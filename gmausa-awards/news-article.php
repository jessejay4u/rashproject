<?php
require_once __DIR__ . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare("SELECT * FROM news_articles WHERE slug = ? AND status = 'published' LIMIT 1");
$stmt->execute([$slug]);
$article = $stmt->fetch();

if (!$article) {
    http_response_code(404);
    $pageTitle = 'Article Not Found';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container gma-section text-center"><h1>404</h1><p class="text-secondary">This article could not be found.</p><a href="news.php" class="btn gma-btn-gold">Back to News</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $article['title'];
require_once __DIR__ . '/includes/header.php';
?>

<header class="gma-hero py-5">
  <div class="container">
    <p class="gma-eyebrow mb-2"><?= e(format_date($article['published_at'])) ?></p>
    <h1 class="mb-0"><?= e($article['title']) ?></h1>
  </div>
</header>

<article class="gma-section border-bottom-0">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <?php if ($article['image_path']): ?>
          <img src="<?= e(uploads_url($article['image_path'])) ?>" alt="<?= e($article['title']) ?>" class="img-fluid rounded-4 mb-4">
        <?php endif; ?>
        <div class="fs-5 text-secondary">
          <?= $article['body'] ?>
        </div>
        <a href="news.php" class="btn gma-btn-outline mt-4"><i class="bi bi-arrow-left"></i> Back to News</a>
      </div>
    </div>
  </div>
</article>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
