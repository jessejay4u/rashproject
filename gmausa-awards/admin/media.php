<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'upload';

    if ($action === 'delete') {
        $stmt = db()->prepare('SELECT file_path FROM media_library WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        $row = $stmt->fetch();
        db()->prepare('DELETE FROM media_library WHERE id = ?')->execute([(int) $_POST['id']]);
        if ($row && $row['file_path']) {
            $full = UPLOADS_PATH . '/' . $row['file_path'];
            if (is_file($full)) @unlink($full);
        }
        flash_set('success', 'Image deleted.');
        header('Location: media.php');
        exit;
    }

    try {
        $uploaded = handle_image_upload('image', 'media');
        if ($uploaded) {
            $alt = trim($_POST['alt_text'] ?? '');
            $stmt = db()->prepare('INSERT INTO media_library (filename, file_path, alt_text) VALUES (?, ?, ?)');
            $stmt->execute([basename($uploaded), $uploaded, $alt]);
            flash_set('success', 'Image uploaded.');
        } else {
            flash_set('error', 'Please choose an image to upload.');
        }
    } catch (RuntimeException $e) {
        flash_set('error', $e->getMessage());
    }

    header('Location: media.php');
    exit;
}

$pageTitle = 'Media Library';
require_once __DIR__ . '/includes/admin-header.php';

$media = db()->query('SELECT * FROM media_library ORDER BY uploaded_at DESC')->fetchAll();
?>

<div class="stat-card mb-4">
  <h2 class="h6 mb-3">Upload Image</h2>
  <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
    <?= csrf_field() ?>
    <div class="col-md-6">
      <label class="form-label">Image</label>
      <input type="file" name="image" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Alt Text</label>
      <input type="text" name="alt_text" class="form-control" placeholder="Describe the image">
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn gma-btn-gold w-100">Upload</button>
    </div>
  </form>
</div>

<div class="row g-3">
  <?php if (!$media): ?>
    <p class="text-secondary">No images uploaded yet.</p>
  <?php endif; ?>
  <?php foreach ($media as $m): ?>
    <div class="col-6 col-md-3">
      <div class="stat-card p-2">
        <img src="<?= e(uploads_url($m['file_path'])) ?>" class="img-fluid rounded mb-2" style="aspect-ratio:1/1;object-fit:cover;width:100%;" alt="<?= e($m['alt_text']) ?>">
        <input type="text" class="form-control form-control-sm mb-2" readonly value="<?= e(uploads_url($m['file_path'])) ?>" onclick="this.select();">
        <form method="post" onsubmit="return confirm('Delete this image?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash"></i> Delete</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
