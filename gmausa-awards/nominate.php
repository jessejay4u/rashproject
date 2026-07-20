<?php
$pageTitle = 'Submit a Nomination';
require_once __DIR__ . '/includes/header.php';

$categories = db()->query('SELECT id, name FROM award_categories ORDER BY display_order ASC, name ASC')->fetchAll();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $nomineeName = trim($_POST['nominee_name'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $submittedBy = trim($_POST['submitted_by'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if ($nomineeName === '') $errors[] = 'Please enter the nominee\'s name.';
    if (!$categoryId) $errors[] = 'Please choose a category.';
    if ($submittedBy === '') $errors[] = 'Please enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO nomination_submissions (nominee_name, category_id, submitted_by, email, reason)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$nomineeName, $categoryId, $submittedBy, $email, $reason]);
        $success = true;
    }
}
?>

<header class="gma-hero text-center py-5">
  <div class="container">
    <p class="gma-eyebrow mb-2">Get Involved</p>
    <h1 class="mb-0">Submit a Nomination</h1>
  </div>
</header>

<section class="gma-section border-bottom-0">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="gma-form-panel">
          <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>Your nomination has been submitted. Thank you for taking part in GMA-USA!</div>
          <?php else: ?>
            <?php if ($errors): ?>
              <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" novalidate>
              <?= csrf_field() ?>
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">Nominee Name</label>
                  <input type="text" name="nominee_name" class="form-control" value="<?= e($_POST['nominee_name'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Category</label>
                  <select name="category_id" class="form-select" required>
                    <option value="">Choose a category&hellip;</option>
                    <?php foreach ($categories as $cat): ?>
                      <option value="<?= (int) $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Your Name</label>
                  <input type="text" name="submitted_by" class="form-control" value="<?= e($_POST['submitted_by'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Your Email</label>
                  <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                  <label class="form-label">Why do they deserve this nomination?</label>
                  <textarea name="reason" rows="4" class="form-control"><?= e($_POST['reason'] ?? '') ?></textarea>
                </div>
                <div class="col-12">
                  <button type="submit" class="btn gma-btn-gold px-4">Submit Nomination</button>
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
