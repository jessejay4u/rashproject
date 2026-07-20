<?php
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';

$news = db()->query(
    "SELECT title, slug, excerpt, image_path, published_at
     FROM news_articles
     WHERE status = 'published'
     ORDER BY published_at DESC
     LIMIT 3"
)->fetchAll();

$categories = db()->query(
    'SELECT name, category_group FROM award_categories ORDER BY display_order ASC LIMIT 8'
)->fetchAll();
?>

<header class="gma-hero text-center">
  <div class="container">
    <p class="gma-eyebrow mb-3"><i class="bi bi-star-fill"></i> Celebrating Ghanaian Music &amp; Culture</p>
    <h1 class="mb-4"><?= e(setting('hero_heading', 'Ghana Music Awards USA')) ?><span class="gma-star">.</span></h1>
    <p class="lead mx-auto mb-4"><?= e(setting('hero_subheading')) ?></p>
    <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
      <a href="nominees.php" class="btn gma-btn-gold btn-lg px-4">View Nominees</a>
      <a href="nominate.php" class="btn gma-btn-outline btn-lg px-4">Submit a Nomination</a>
    </div>
  </div>
</header>

<section class="gma-section">
  <div class="container">
    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
      <div>
        <p class="gma-eyebrow mb-1">Newsroom</p>
        <h2 class="gma-section-title mb-0">Latest News</h2>
      </div>
      <a href="news.php" class="btn gma-btn-outline btn-sm">All News <i class="bi bi-arrow-right"></i></a>
    </div>

    <?php if (!$news): ?>
      <p class="text-secondary">No published articles yet. Check back soon.</p>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($news as $article): ?>
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
    <?php endif; ?>
  </div>
</section>

<section class="gma-section">
  <div class="container">
    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
      <div>
        <p class="gma-eyebrow mb-1">The Awards</p>
        <h2 class="gma-section-title mb-0">Award Categories</h2>
      </div>
      <a href="categories.php" class="btn gma-btn-outline btn-sm">All Categories <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <?php foreach ($categories as $cat): ?>
        <span class="gma-category-pill <?= $cat['category_group'] === 'ghana_based' ? 'ghana-based' : 'us-based' ?>">
          <i class="bi bi-trophy-fill"></i> <?= e($cat['name']) ?>
        </span>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="gma-section border-bottom-0">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <p class="gma-eyebrow mb-1">Who We Are</p>
        <h2 class="gma-section-title">About GMA-USA</h2>
        <p class="text-secondary"><?= e(setting('about_text')) ?></p>
        <p class="mb-4"><strong>CEO:</strong> <?= e(setting('ceo_name')) ?></p>
        <a href="about.php" class="btn gma-btn-gold">Learn More</a>
      </div>
      <div class="col-lg-6">
        <div class="gma-card p-5 text-center">
          <i class="bi bi-mic-fill display-1 text-warning mb-3"></i>
          <p class="text-secondary mb-0">Official artwork and photography coming soon &mdash; upload media via the admin dashboard.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="gma-cta-band text-center">
  <div class="container">
    <h3 class="gma-heading mb-3">Know an Artiste Who Deserves Recognition?</h3>
    <a href="nominate.php" class="btn gma-btn-gold btn-lg px-4">Submit a Nomination</a>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
