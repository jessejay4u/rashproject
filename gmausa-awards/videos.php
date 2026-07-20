<?php
$pageTitle = 'Videos';
require_once __DIR__ . '/includes/header.php';

$videos = db()->query('SELECT * FROM videos ORDER BY display_order ASC, id ASC')->fetchAll();

function video_embed_url(string $url): ?string
{
    if (preg_match('~youtu\.be/([A-Za-z0-9_-]+)~', $url, $m) || preg_match('~youtube\.com/watch\?v=([A-Za-z0-9_-]+)~', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    if (preg_match('~youtube\.com/embed/~', $url)) {
        return $url;
    }
    return null;
}
?>

<header class="gma-hero text-center py-5">
  <div class="container">
    <p class="gma-eyebrow mb-2">Watch</p>
    <h1 class="mb-0">Videos</h1>
  </div>
</header>

<section class="gma-section border-bottom-0">
  <div class="container">
    <?php if (!$videos): ?>
      <p class="text-secondary text-center">No videos added yet.</p>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($videos as $v): $embed = $v['video_url'] ? video_embed_url($v['video_url']) : null; ?>
          <div class="col-md-6 col-lg-4">
            <div class="gma-card h-100">
              <?php if ($embed): ?>
                <div class="ratio ratio-16x9">
                  <iframe src="<?= e($embed) ?>" title="<?= e($v['title']) ?>" allowfullscreen></iframe>
                </div>
              <?php elseif ($v['video_url']): ?>
                <a class="gma-card__img text-decoration-none" href="<?= e($v['video_url']) ?>" target="_blank" rel="noopener">
                  <i class="bi bi-play-circle"></i>
                </a>
              <?php else: ?>
                <div class="gma-card__img"><i class="bi bi-camera-reels"></i></div>
              <?php endif; ?>
              <div class="gma-card__body">
                <div class="gma-card__title text-white mb-0"><?= e($v['title']) ?></div>
                <?php if (!$v['video_url']): ?><p class="text-secondary small mb-0 mt-1">Video link coming soon.</p><?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
