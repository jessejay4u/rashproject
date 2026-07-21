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

<?php if ($flash = flash_get()): ?>
  <div class="container pt-3">
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
      <?= e($flash['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  </div>
<?php endif; ?>

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

<?php if (!empty($showVideoBeforeNav)): ?>
  <?php $bannerVideo = db()->query('SELECT * FROM videos ORDER BY display_order ASC, id ASC LIMIT 1')->fetch(); ?>
  <?php if ($bannerVideo): ?>
    <div class="gma-video-banner">
      <div class="container">
        <a href="videos.php" class="d-flex align-items-center gap-3 text-decoration-none">
          <span class="gma-video-banner__icon"><i class="bi bi-play-circle-fill"></i></span>
          <span class="gma-video-banner__text">
            <span class="gma-eyebrow d-block">Watch Now</span>
            <span class="text-white fw-semibold"><?= e($bannerVideo['title']) ?></span>
          </span>
          <span class="ms-auto text-warning small fw-bold text-uppercase d-none d-sm-flex align-items-center gap-1">Watch <i class="bi bi-arrow-right"></i></span>
        </a>
      </div>
    </div>
  <?php endif; ?>
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
      <ul class="navbar-nav ms-auto align-items-lg-center flex-wrap">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="gallery.php">Gallery</a></li>
        <li class="nav-item"><a class="nav-link" href="accreditation.php">Accreditation</a></li>
        <li class="nav-item"><a class="nav-link" href="videos.php">Videos</a></li>
        <li class="nav-item"><a class="nav-link" href="nominate.php">Nomination</a></li>
        <?php if ($ticketsUrl = setting('tickets_url')): ?>
          <li class="nav-item"><a class="nav-link" href="<?= e($ticketsUrl) ?>" target="_blank" rel="noopener">Online Tickets</a></li>
        <?php endif; ?>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="gmaCatDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Entry &amp; Categories</a>
          <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="gmaCatDropdown">
            <li><a class="dropdown-item" href="entry.php">Entry Procedures</a></li>
            <li><a class="dropdown-item" href="categories.php">Categories &amp; Definitions</a></li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="gmaAboutDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">About GMA-USA</a>
          <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="gmaAboutDropdown">
            <li><a class="dropdown-item" href="about.php">About Us</a></li>
            <li><a class="dropdown-item" href="team.php">Team</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><h6 class="dropdown-header">Nominees &amp; Winners</h6></li>
            <li><a class="dropdown-item" href="nominees.php?year=2021">2021</a></li>
            <li><a class="dropdown-item" href="nominees.php?years=2022,2023">2022 &amp; 2023</a></li>
            <li><a class="dropdown-item" href="nominees.php?year=2024">2024</a></li>
            <li><a class="dropdown-item" href="nominees.php?year=2025">2025</a></li>
          </ul>
        </li>

        <li class="nav-item"><a class="nav-link" href="patrons.php">Life Patrons</a></li>
        <li class="nav-item"><a class="nav-link" href="charity.php">Charity</a></li>
        <li class="nav-item"><a class="nav-link" href="news.php">News</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>

        <?php if ($voteUrl = setting('vote_url')): ?>
          <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
            <a class="btn gma-btn-gold btn-sm px-3" href="<?= e($voteUrl) ?>" target="_blank" rel="noopener">Vote Now</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
