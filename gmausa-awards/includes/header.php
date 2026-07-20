<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? setting('site_name', 'Ghana Music Awards USA');
$activeUpdates = db()->query(
    'SELECT message, link_url FROM updates WHERE is_active = 1 ORDER BY display_order ASC, id DESC LIMIT 8'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | <?= e(setting('site_name', 'GMA-USA')) ?></title>
<meta name="description" content="<?= e(setting('hero_subheading')) ?>">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>

<?php if ($activeUpdates): ?>
<div class="gma-ticker d-flex align-items-center">
  <span class="gma-ticker__label">Latest Updates</span>
  <div class="gma-ticker__track">
    <?php foreach ($activeUpdates as $u): ?>
      <span><?php if (!empty($u['link_url'])): ?><a href="<?= e($u['link_url']) ?>" class="text-white text-decoration-none"><?= e($u['message']) ?></a><?php else: ?><?= e($u['message']) ?><?php endif; ?></span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<nav class="navbar navbar-expand-lg gma-navbar sticky-top py-3">
  <div class="container">
    <a class="navbar-brand" href="<?= APP_URL ?>/index.php">
      <i class="bi bi-star-fill gma-logo-mark"></i>
      <?= e(setting('site_name', 'GMA-USA')) ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#gmaNav" aria-controls="gmaNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="gmaNav">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
        <li class="nav-item"><a class="nav-link" href="categories.php">Categories</a></li>
        <li class="nav-item"><a class="nav-link" href="nominees.php">Nominees</a></li>
        <li class="nav-item"><a class="nav-link" href="news.php">News</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
        <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
          <a class="btn gma-btn-gold btn-sm px-3" href="nominate.php">Nominate</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
