<?php
$pageTitle = 'About';
require_once __DIR__ . '/includes/header.php';
?>

<header class="gma-hero text-center py-5">
  <div class="container">
    <p class="gma-eyebrow mb-2">Who We Are</p>
    <h1 class="mb-0">About <?= e(setting('site_name', 'GMA-USA')) ?></h1>
  </div>
</header>

<section class="gma-section border-bottom-0">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-8">
        <p class="fs-5 text-secondary"><?= nl2br(e(setting('about_text'))) ?></p>

        <div class="row g-4 mt-2">
          <div class="col-sm-6">
            <div class="gma-card p-4">
              <i class="bi bi-flag-fill text-warning fs-2"></i>
              <h5 class="mt-3">Our Mission</h5>
              <p class="text-secondary small mb-0">Celebrating Ghanaian culture and promoting Ghanaian music to a global audience, serving as a platform for artistes to showcase their work and connect with fans and industry professionals worldwide.</p>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="gma-card p-4">
              <i class="bi bi-globe-americas text-warning fs-2"></i>
              <h5 class="mt-3">Diaspora &amp; Homeland</h5>
              <p class="text-secondary small mb-0">Recognizing achievement across both the US-based Ghanaian diaspora community and the Ghana-based music industry, with 35 award categories spanning both.</p>
            </div>
          </div>
        </div>

        <div class="gma-disclaimer mt-4">
          <i class="bi bi-info-circle me-1"></i>
          This page reflects publicly available information about GMA-USA gathered during scaffolding. Replace it with the organization's official About copy via the admin dashboard settings.
        </div>
      </div>

      <div class="col-lg-4">
        <div class="gma-card p-4 text-center">
          <div class="gma-avatar-placeholder mx-auto mb-3" style="width:96px;height:96px;font-size:1.5rem;">CEO</div>
          <h5 class="mb-1"><?= e(setting('ceo_name')) ?></h5>
          <p class="text-secondary small">Chief Executive Officer, Ghana Music Awards USA</p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
