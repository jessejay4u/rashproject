<?php
$pageTitle = 'Contact';
require_once __DIR__ . '/includes/header.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '') $errors[] = 'Please enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if ($message === '') $errors[] = 'Please enter a message.';

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $subject, $message]);
        $success = true;
    }
}
?>

<header class="gma-hero text-center py-5">
  <div class="container">
    <p class="gma-eyebrow mb-2">Get In Touch</p>
    <h1 class="mb-0">Contact Us</h1>
  </div>
</header>

<section class="gma-section border-bottom-0">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-5">
        <h2 class="gma-section-title fs-4 mb-3">Reach GMA-USA</h2>
        <ul class="list-unstyled text-secondary">
          <?php if ($email = setting('contact_email')): ?>
            <li class="mb-3"><i class="bi bi-envelope-fill text-warning me-2"></i><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
          <?php endif; ?>
          <?php if ($fb = setting('facebook_url')): ?>
            <li class="mb-3"><i class="bi bi-facebook text-warning me-2"></i><a href="<?= e($fb) ?>" target="_blank" rel="noopener">Facebook</a></li>
          <?php endif; ?>
          <?php if ($ig = setting('instagram_url')): ?>
            <li class="mb-3"><i class="bi bi-instagram text-warning me-2"></i><a href="<?= e($ig) ?>" target="_blank" rel="noopener">Instagram</a></li>
          <?php endif; ?>
        </ul>
      </div>
      <div class="col-lg-7">
        <div class="gma-form-panel">
          <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>Thanks for reaching out — we'll get back to you soon.</div>
          <?php else: ?>
            <?php if ($errors): ?>
              <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" novalidate>
              <?= csrf_field() ?>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Name</label>
                  <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Subject</label>
                  <input type="text" name="subject" class="form-control" value="<?= e($_POST['subject'] ?? '') ?>">
                </div>
                <div class="col-12">
                  <label class="form-label">Message</label>
                  <textarea name="message" rows="5" class="form-control" required><?= e($_POST['message'] ?? '') ?></textarea>
                </div>
                <div class="col-12">
                  <button type="submit" class="btn gma-btn-gold px-4">Send Message</button>
                </div>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
