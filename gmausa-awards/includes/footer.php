<footer class="gma-footer">
  <div class="container">
    <div class="row gy-4">
      <div class="col-lg-4">
        <h6><i class="bi bi-star-fill text-warning"></i> <?= e(setting('site_name', 'GMA-USA')) ?></h6>
        <p><?= e(setting('hero_subheading')) ?></p>
        <div>
          <?php if ($fb = setting('facebook_url')): ?><a class="gma-social-icon" href="<?= e($fb) ?>" target="_blank" rel="noopener"><i class="bi bi-facebook"></i></a><?php endif; ?>
          <?php if ($ig = setting('instagram_url')): ?><a class="gma-social-icon" href="<?= e($ig) ?>" target="_blank" rel="noopener"><i class="bi bi-instagram"></i></a><?php endif; ?>
          <?php if ($tw = setting('twitter_url')): ?><a class="gma-social-icon" href="<?= e($tw) ?>" target="_blank" rel="noopener"><i class="bi bi-twitter-x"></i></a><?php endif; ?>
        </div>
      </div>
      <div class="col-lg-2 col-6">
        <h6>Explore</h6>
        <ul class="list-unstyled">
          <li><a href="about.php">About Us</a></li>
          <li><a href="team.php">Team</a></li>
          <li><a href="categories.php">Categories</a></li>
          <li><a href="gallery.php">Gallery</a></li>
          <li><a href="videos.php">Videos</a></li>
        </ul>
      </div>
      <div class="col-lg-2 col-6">
        <h6>Get Involved</h6>
        <ul class="list-unstyled">
          <li><a href="nominate.php">Submit a Nomination</a></li>
          <li><a href="accreditation.php">Accreditation</a></li>
          <li><a href="patrons.php">Life Patrons</a></li>
          <li><a href="charity.php">Charity</a></li>
          <li><a href="contact.php">Contact Us</a></li>
        </ul>
      </div>
      <div class="col-lg-4">
        <h6>Contact</h6>
        <ul class="list-unstyled">
          <?php if ($addr = setting('contact_address')): ?><li class="mb-2"><i class="bi bi-geo-alt-fill me-2"></i><?= e($addr) ?></li><?php endif; ?>
          <?php if ($email = setting('contact_email')): ?><li><i class="bi bi-envelope-fill me-2"></i><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
          <?php if ($phone = setting('contact_phone')): ?><li><i class="bi bi-telephone-fill me-2"></i><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
        </ul>
        <?php if ($newsletterText = setting('newsletter_text')): ?>
          <form method="post" action="newsletter-subscribe.php" class="mt-3">
            <?= csrf_field() ?>
            <label class="form-label small mb-1"><?= e($newsletterText) ?></label>
            <div class="input-group input-group-sm">
              <input type="email" name="email" class="form-control" placeholder="Your email" required>
              <button class="btn gma-btn-gold" type="submit">Subscribe</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <hr class="border-secondary my-4">
    <?php if ($note = setting('footer_note')): ?>
      <p class="gma-disclaimer mb-3"><i class="bi bi-info-circle me-1"></i><?= e($note) ?></p>
    <?php endif; ?>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
      <span>&copy; <?= date('Y') ?> <?= e(setting('site_name', 'GMA-USA')) ?>. All rights reserved.</span>
      <span>Fan site built independently &mdash; not affiliated with grammy.com or the Recording Academy.</span>
    </div>
  </div>
</footer>

<button id="gmaBackToTop" class="gma-back-to-top" aria-label="Back to top"><i class="bi bi-arrow-up"></i></button>

<script src="<?= APP_URL ?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
