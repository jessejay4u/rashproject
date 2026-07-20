<?php
$pageTitle = 'Accreditation';
require_once __DIR__ . '/includes/header.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $surname = trim($_POST['surname'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $otherNames = trim($_POST['other_names'] ?? '');
    $institution = trim($_POST['institution'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $dob = trim($_POST['date_of_birth'] ?? '');
    $passportNumber = trim($_POST['passport_number'] ?? '');
    $countryOfBirth = trim($_POST['country_of_birth'] ?? '');
    $passportDetails = trim($_POST['passport_details'] ?? '');
    $dateIssued = trim($_POST['date_issued'] ?? '');
    $expirationDate = trim($_POST['expiration_date'] ?? '');

    if ($surname === '') $errors[] = 'Surname is required.';
    if ($firstName === '') $errors[] = 'First name is required.';
    if ($institution === '') $errors[] = 'Name of institution is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

    $idPhotoPath = null;
    $mediaTagPath = null;
    if (!$errors) {
        try {
            $idPhotoPath = handle_image_upload('id_photo', 'accreditation');
            $mediaTagPath = handle_image_upload('media_tag_photo', 'accreditation');
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO accreditation_submissions
             (surname, first_name, other_names, institution, email, date_of_birth, passport_number,
              country_of_birth, passport_details, date_issued, expiration_date, id_photo_path, media_tag_photo_path)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $surname, $firstName, $otherNames ?: null, $institution, $email,
            $dob ?: null, $passportNumber ?: null, $countryOfBirth ?: null, $passportDetails ?: null,
            $dateIssued ?: null, $expirationDate ?: null, $idPhotoPath, $mediaTagPath,
        ]);
        $success = true;
    }
}
?>

<header class="gma-hero text-center py-5">
  <div class="container">
    <p class="gma-eyebrow mb-2">Media &amp; Event Accreditation</p>
    <h1 class="mb-0">Accreditation</h1>
  </div>
</header>

<section class="gma-section border-bottom-0">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="gma-form-panel">
          <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>Your accreditation application has been submitted.</div>
          <?php else: ?>
            <?php if ($errors): ?>
              <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" novalidate>
              <?= csrf_field() ?>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Surname</label>
                  <input type="text" name="surname" class="form-control" value="<?= e($_POST['surname'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">First Name</label>
                  <input type="text" name="first_name" class="form-control" value="<?= e($_POST['first_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Other Names</label>
                  <input type="text" name="other_names" class="form-control" value="<?= e($_POST['other_names'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Name of Institution</label>
                  <input type="text" name="institution" class="form-control" value="<?= e($_POST['institution'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Date of Birth</label>
                  <input type="date" name="date_of_birth" class="form-control" value="<?= e($_POST['date_of_birth'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Passport Number</label>
                  <input type="text" name="passport_number" class="form-control" value="<?= e($_POST['passport_number'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Country of Birth</label>
                  <input type="text" name="country_of_birth" class="form-control" value="<?= e($_POST['country_of_birth'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Passport Details</label>
                  <input type="text" name="passport_details" class="form-control" value="<?= e($_POST['passport_details'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Date Issued</label>
                  <input type="date" name="date_issued" class="form-control" value="<?= e($_POST['date_issued'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Expiration Date</label>
                  <input type="date" name="expiration_date" class="form-control" value="<?= e($_POST['expiration_date'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">ID Photo</label>
                  <input type="file" name="id_photo" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Media TAG Photo</label>
                  <input type="file" name="media_tag_photo" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif">
                </div>
                <div class="col-12">
                  <button type="submit" class="btn gma-btn-gold px-4">Submit</button>
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
