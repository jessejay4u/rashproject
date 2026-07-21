<?php
$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';

$news = db()->query(
    "SELECT title, slug, excerpt, image_path, published_at
     FROM news_articles
     WHERE status = 'published'
     ORDER BY published_at DESC
     LIMIT 6"
)->fetchAll();

$heroSlides = array_slice($news, 0, 3);
$featureMain = $news[0] ?? null;
$featureList = array_slice($news, 1, 4);

$videos = db()->query('SELECT * FROM videos ORDER BY display_order ASC, id ASC LIMIT 8')->fetchAll();

$categories = db()->query(
    'SELECT name, category_group FROM award_categories ORDER BY display_order ASC LIMIT 8'
)->fetchAll();

$nominationsOpen = setting('nominations_open', '0') === '1';
$voteUrl = setting('vote_url');

$exploreLinks = [
    ['icon' => 'bi-images', 'label' => 'Gallery', 'href' => 'gallery.php'],
    ['icon' => 'bi-camera-reels', 'label' => 'Videos', 'href' => 'videos.php'],
    ['icon' => 'bi-people-fill', 'label' => 'Team', 'href' => 'team.php'],
    ['icon' => 'bi-award-fill', 'label' => 'Life Patrons', 'href' => 'patrons.php'],
    ['icon' => 'bi-heart-fill', 'label' => 'Charity', 'href' => 'charity.php'],
    ['icon' => 'bi-card-checklist', 'label' => 'Accreditation', 'href' => 'accreditation.php'],
];

function gma_media(?string $path, string $alt, string $icon = 'bi-image', string $label = 'Image placeholder — add via admin dashboard', string $size = ''): void
{
    if ($path) {
        echo '<img src="' . e(uploads_url($path)) . '" alt="' . e($alt) . '" loading="lazy">';
        return;
    }
    echo '<div class="gma-placeholder ' . e($size) . '"><i class="bi ' . e($icon) . '"></i><span>' . e($label) . '</span></div>';
}
?>

<!-- Hero carousel (mirrors grammy.com's featured-story carousel) -->
<section class="gma-hero-carousel">
  <div id="gmaHeroCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="6000">
    <?php if ($heroSlides): ?>
      <div class="carousel-indicators">
        <?php foreach ($heroSlides as $i => $slide): ?>
          <button type="button" data-bs-target="#gmaHeroCarousel" data-bs-slide-to="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="Slide <?= $i + 1 ?>"></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="carousel-inner">
      <?php if (!$heroSlides): ?>
        <div class="carousel-item active">
          <div class="gma-slide-media"><?php gma_media(null, 'GMA-USA', 'bi-star-fill', 'Hero image placeholder — add via admin dashboard'); ?></div>
          <div class="gma-slide-overlay">
            <div class="container">
              <div class="gma-slide-caption">
                <p class="gma-eyebrow mb-2">GMA-USA</p>
                <h2><?= e(setting('hero_heading', 'Ghana Music Awards USA')) ?></h2>
                <p class="lead text-secondary mb-4"><?= e(setting('hero_subheading')) ?></p>
                <a href="nominees.php" class="btn gma-btn-gold btn-lg px-4">View Nominees</a>
              </div>
            </div>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($heroSlides as $i => $slide): ?>
          <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
            <div class="gma-slide-media"><?php gma_media($slide['image_path'], $slide['title'], 'bi-image', 'Hero image placeholder — add via admin dashboard'); ?></div>
            <div class="gma-slide-overlay">
              <div class="container">
                <div class="gma-slide-caption">
                  <p class="gma-eyebrow mb-2">News</p>
                  <h2><?= e($slide['title']) ?></h2>
                  <p class="lead text-secondary mb-4 d-none d-md-block"><?= e($slide['excerpt']) ?></p>
                  <a href="news-article.php?slug=<?= urlencode($slide['slug']) ?>" class="btn gma-btn-gold btn-lg px-4">Read More</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php if (count($heroSlides) > 1): ?>
      <button class="carousel-control-prev" type="button" data-bs-target="#gmaHeroCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      </button>
      <button class="carousel-control-next" type="button" data-bs-target="#gmaHeroCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
      </button>
    <?php endif; ?>
  </div>
</section>

<!-- Latest News: one large feature + smaller list, like grammy.com's news module -->
<section class="gma-section">
  <div class="container">
    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
      <div>
        <p class="gma-eyebrow mb-1">Newsroom</p>
        <h2 class="gma-section-title mb-0">Latest News</h2>
      </div>
      <a href="news.php" class="btn gma-btn-outline btn-sm">All News <i class="bi bi-arrow-right"></i></a>
    </div>

    <?php if (!$featureMain): ?>
      <p class="text-secondary">No published articles yet. Check back soon.</p>
    <?php else: ?>
      <div class="row g-4">
        <div class="col-lg-7">
          <a href="news-article.php?slug=<?= urlencode($featureMain['slug']) ?>" class="gma-feature-card text-decoration-none">
            <div class="gma-feature-media"><?php gma_media($featureMain['image_path'], $featureMain['title']); ?></div>
            <div class="gma-card__meta mt-3"><?= e(format_date($featureMain['published_at'])) ?></div>
            <div class="fs-3 fw-bold text-white mt-1"><?= e($featureMain['title']) ?></div>
            <p class="text-secondary"><?= e($featureMain['excerpt']) ?></p>
          </a>
        </div>
        <div class="col-lg-5">
          <?php if (!$featureList): ?>
            <p class="text-secondary small">More articles will appear here as they're published.</p>
          <?php else: ?>
            <?php foreach ($featureList as $article): ?>
              <a href="news-article.php?slug=<?= urlencode($article['slug']) ?>" class="gma-feature-list-item text-decoration-none">
                <div class="gma-feature-thumb"><?php gma_media($article['image_path'], $article['title'], 'bi-image', 'No image', 'gma-placeholder--sm'); ?></div>
                <div>
                  <div class="gma-card__meta"><?= e(format_date($article['published_at'])) ?></div>
                  <div class="fw-bold text-white"><?= e($article['title']) ?></div>
                </div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Watch: horizontal video rail, like grammy.com's video carousel -->
<section class="gma-section">
  <div class="container">
    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
      <div>
        <p class="gma-eyebrow mb-1">Watch</p>
        <h2 class="gma-section-title mb-0">Videos</h2>
      </div>
      <a href="videos.php" class="btn gma-btn-outline btn-sm">All Videos <i class="bi bi-arrow-right"></i></a>
    </div>
    <?php if (!$videos): ?>
      <p class="text-secondary">No videos added yet.</p>
    <?php else: ?>
      <div class="gma-rail">
        <?php foreach ($videos as $v): ?>
          <a href="videos.php" class="gma-rail-item text-decoration-none">
            <div class="gma-feature-media">
              <div class="gma-placeholder gma-placeholder--play">
                <i class="bi bi-play-circle-fill"></i>
                <span>Video placeholder</span>
              </div>
            </div>
            <div class="fw-semibold text-white small mt-2"><?= e($v['title']) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Awards promo band, like grammy.com's Awards CTA -->
<section class="position-relative" style="min-height: 340px;">
  <div class="position-absolute top-0 start-0 w-100 h-100">
    <?php gma_media(null, 'GMA-USA Awards', 'bi-trophy-fill', 'Awards background image placeholder — add via admin dashboard'); ?>
  </div>
  <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(0deg, rgba(11,11,13,0.92), rgba(11,11,13,0.55));"></div>
  <div class="container position-relative py-5 text-center" style="min-height: 340px; display:flex; flex-direction:column; align-items:center; justify-content:center;">
    <p class="gma-eyebrow mb-2">The Awards</p>
    <h2 class="gma-heading mb-3" style="font-size: clamp(1.8rem, 4vw, 3rem);">GMA-USA Nominees &amp; Winners</h2>
    <p class="text-secondary mb-4 col-lg-6">Explore the categories, meet the nominees, and see who takes home the honors.</p>
    <div class="d-flex flex-column flex-sm-row gap-3">
      <a href="nominees.php" class="btn gma-btn-gold btn-lg px-4">View Nominees</a>
      <?php if ($nominationsOpen): ?>
        <a href="nominate.php" class="btn gma-btn-outline btn-lg px-4">Submit a Nomination</a>
      <?php elseif ($voteUrl): ?>
        <a href="<?= e($voteUrl) ?>" target="_blank" rel="noopener" class="btn gma-btn-outline btn-lg px-4">Vote Now</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Explore by category, like grammy.com's genre tile grid -->
<section class="gma-section">
  <div class="container">
    <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
      <div>
        <p class="gma-eyebrow mb-1">The Awards</p>
        <h2 class="gma-section-title mb-0">Explore Categories</h2>
      </div>
      <a href="categories.php" class="btn gma-btn-outline btn-sm">All Categories <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="row g-3">
      <?php foreach ($categories as $cat): ?>
        <div class="col-6 col-md-3">
          <a href="categories.php" class="gma-genre-tile <?= $cat['category_group'] === 'ghana_based' ? 'ghana-based' : 'us-based' ?>">
            <div class="gma-placeholder"><i class="bi bi-trophy-fill"></i></div>
            <div class="gma-genre-label"><?= e($cat['name']) ?></div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- About teaser -->
<section class="gma-section">
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
        <div class="gma-feature-media" style="aspect-ratio: 4/3;">
          <?php gma_media(null, 'GMA-USA', 'bi-mic-fill', 'Official artwork/photography placeholder — add via admin dashboard'); ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Explore more (site-wide quick links) -->
<section class="gma-section border-bottom-0">
  <div class="container">
    <p class="gma-eyebrow mb-1">Explore</p>
    <h2 class="gma-section-title mb-4">More From GMA-USA</h2>
    <div class="row g-3">
      <?php foreach ($exploreLinks as $link): ?>
        <div class="col-6 col-md-4 col-lg-2">
          <a href="<?= e($link['href']) ?>" class="text-decoration-none">
            <div class="gma-card p-3 text-center h-100">
              <i class="bi <?= e($link['icon']) ?> fs-2 text-warning mb-2 d-block"></i>
              <span class="text-white small fw-semibold"><?= e($link['label']) ?></span>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Newsletter band -->
<section class="gma-newsletter-band text-center">
  <div class="container">
    <p class="gma-eyebrow mb-2">Stay In The Know</p>
    <h3 class="gma-heading mb-3">Get GMA-USA News In Your Inbox</h3>
    <form method="post" action="newsletter-subscribe.php" class="mx-auto" style="max-width: 32rem;">
      <?= csrf_field() ?>
      <div class="input-group input-group-lg">
        <input type="email" name="email" class="form-control" placeholder="Your email address" required>
        <button class="btn gma-btn-gold" type="submit">Subscribe</button>
      </div>
    </form>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
