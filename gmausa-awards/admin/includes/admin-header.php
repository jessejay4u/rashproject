<?php
require_once __DIR__ . '/auth.php';
require_admin();

$currentPage = basename($_SERVER['SCRIPT_NAME']);
$pageTitle = $pageTitle ?? 'Dashboard';

function nav_active(string $file, string $current): string
{
    return $file === $current ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | Admin | <?= e(setting('site_name', 'GMA-USA')) ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/admin/assets/css/admin.css">
</head>
<body>

<div class="offcanvas offcanvas-start admin-sidebar" tabindex="-1" id="mobileSidebar">
  <div class="offcanvas-header">
    <span class="brand d-flex align-items-center gap-2"><i class="bi bi-star-fill text-warning"></i> GMA-USA Admin</span>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body d-flex flex-column">
    <nav class="d-flex flex-column gap-1">
      <a href="index.php" class="<?= nav_active('index.php', $currentPage) ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a href="news.php" class="<?= nav_active('news.php', $currentPage) ?>"><i class="bi bi-newspaper"></i> News Articles</a>
      <a href="updates.php" class="<?= nav_active('updates.php', $currentPage) ?>"><i class="bi bi-megaphone-fill"></i> Latest Updates</a>
      <a href="categories.php" class="<?= nav_active('categories.php', $currentPage) ?>"><i class="bi bi-trophy-fill"></i> Categories</a>
      <a href="nominees.php" class="<?= nav_active('nominees.php', $currentPage) ?>"><i class="bi bi-people-fill"></i> Nominees</a>
      <a href="team.php" class="<?= nav_active('team.php', $currentPage) ?>"><i class="bi bi-person-badge-fill"></i> Team</a>
      <a href="patrons.php" class="<?= nav_active('patrons.php', $currentPage) ?>"><i class="bi bi-award-fill"></i> Life Patrons</a>
      <a href="partners-sponsors.php" class="<?= nav_active('partners-sponsors.php', $currentPage) ?>"><i class="bi bi-handshake"></i> Partners &amp; Sponsors</a>
      <a href="videos.php" class="<?= nav_active('videos.php', $currentPage) ?>"><i class="bi bi-camera-reels"></i> Videos</a>
      <a href="gallery.php" class="<?= nav_active('gallery.php', $currentPage) ?>"><i class="bi bi-images"></i> Gallery</a>
      <a href="media.php" class="<?= nav_active('media.php', $currentPage) ?>"><i class="bi bi-image"></i> Media Library</a>
      <a href="accreditation.php" class="<?= nav_active('accreditation.php', $currentPage) ?>"><i class="bi bi-card-checklist"></i> Accreditation</a>
      <a href="messages.php" class="<?= nav_active('messages.php', $currentPage) ?>"><i class="bi bi-envelope-fill"></i> Contact Messages</a>
      <a href="nominations.php" class="<?= nav_active('nominations.php', $currentPage) ?>"><i class="bi bi-send-fill"></i> Nomination Submissions</a>
      <a href="newsletter.php" class="<?= nav_active('newsletter.php', $currentPage) ?>"><i class="bi bi-envelope-paper-fill"></i> Newsletter</a>
      <a href="settings.php" class="<?= nav_active('settings.php', $currentPage) ?>"><i class="bi bi-gear-fill"></i> Site Settings</a>
    </nav>
    <div class="mt-auto pt-3 border-top border-secondary">
      <a href="../index.php" target="_blank"><i class="bi bi-box-arrow-up-right"></i> View Site</a>
      <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a>
    </div>
  </div>
</div>

<div class="d-flex">
  <div class="admin-sidebar p-3 d-none d-lg-flex flex-column" style="width:260px;">
    <div class="brand d-flex align-items-center gap-2 px-2 py-3 mb-2">
      <i class="bi bi-star-fill text-warning"></i>
      <span>GMA-USA Admin</span>
    </div>
    <nav class="d-flex flex-column gap-1">
      <a href="index.php" class="<?= nav_active('index.php', $currentPage) ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
      <a href="news.php" class="<?= nav_active('news.php', $currentPage) ?> <?= nav_active('news-form.php', $currentPage) ?>"><i class="bi bi-newspaper"></i> News Articles</a>
      <a href="updates.php" class="<?= nav_active('updates.php', $currentPage) ?>"><i class="bi bi-megaphone-fill"></i> Latest Updates</a>
      <a href="categories.php" class="<?= nav_active('categories.php', $currentPage) ?>"><i class="bi bi-trophy-fill"></i> Categories</a>
      <a href="nominees.php" class="<?= nav_active('nominees.php', $currentPage) ?>"><i class="bi bi-people-fill"></i> Nominees</a>
      <a href="team.php" class="<?= nav_active('team.php', $currentPage) ?>"><i class="bi bi-person-badge-fill"></i> Team</a>
      <a href="patrons.php" class="<?= nav_active('patrons.php', $currentPage) ?>"><i class="bi bi-award-fill"></i> Life Patrons</a>
      <a href="partners-sponsors.php" class="<?= nav_active('partners-sponsors.php', $currentPage) ?>"><i class="bi bi-handshake"></i> Partners &amp; Sponsors</a>
      <a href="videos.php" class="<?= nav_active('videos.php', $currentPage) ?>"><i class="bi bi-camera-reels"></i> Videos</a>
      <a href="gallery.php" class="<?= nav_active('gallery.php', $currentPage) ?>"><i class="bi bi-images"></i> Gallery</a>
      <a href="media.php" class="<?= nav_active('media.php', $currentPage) ?>"><i class="bi bi-image"></i> Media Library</a>
      <a href="accreditation.php" class="<?= nav_active('accreditation.php', $currentPage) ?>"><i class="bi bi-card-checklist"></i> Accreditation</a>
      <a href="messages.php" class="<?= nav_active('messages.php', $currentPage) ?>"><i class="bi bi-envelope-fill"></i> Contact Messages</a>
      <a href="nominations.php" class="<?= nav_active('nominations.php', $currentPage) ?>"><i class="bi bi-send-fill"></i> Nomination Submissions</a>
      <a href="newsletter.php" class="<?= nav_active('newsletter.php', $currentPage) ?>"><i class="bi bi-envelope-paper-fill"></i> Newsletter</a>
      <a href="settings.php" class="<?= nav_active('settings.php', $currentPage) ?>"><i class="bi bi-gear-fill"></i> Site Settings</a>
    </nav>
    <div class="mt-auto pt-3 border-top border-secondary">
      <a href="../index.php" target="_blank"><i class="bi bi-box-arrow-up-right"></i> View Site</a>
      <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a>
    </div>
  </div>

  <div class="flex-grow-1">
    <div class="admin-topbar d-flex justify-content-between align-items-center px-3 px-lg-4 py-3">
      <button class="btn btn-outline-dark d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar"><i class="bi bi-list"></i></button>
      <h1 class="h5 mb-0"><?= e($pageTitle) ?></h1>
      <span class="text-secondary small"><i class="bi bi-person-circle"></i> <?= e($_SESSION['admin_name'] ?? 'Admin') ?></span>
    </div>

    <div class="p-3 p-lg-4">
      <?php if ($flash = flash_get()): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= e($flash['message']) ?></div>
      <?php endif; ?>
