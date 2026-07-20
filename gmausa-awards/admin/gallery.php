<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$groups = [
    'gmausa_at_6'          => 'GMAUSA @ 6 Pictures',
    'nominee_announcement' => 'Nominee Announcement Pictures',
    'more_recent'          => 'More Recent Pictures',
    'recent'               => 'Recent Pictures',
    'old'                  => 'Old Pictures',
    'charity'              => 'Charity Pictures',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'upload';

    if ($action === 'delete') {
        $stmt = db()->prepare('SELECT image_path FROM gallery_images WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        $row = $stmt->fetch();
        db()->prepare('DELETE FROM gallery_images WHERE id = ?')->execute([(int) $_POST['id']]);
        if ($row && $row['image_path']) {
            $full = UPLOADS_PATH . '/' . $row['image_path'];
            if (is_file($full)) @unlink($full);
        }
        flash_set('success', 'Photo deleted.');
    } else {
        $groupKey = $_POST['group_key'] ?? '';
        if (!isset($groups[$groupKey])) {
            flash_set('error', 'Please choose a valid gallery group.');
        } else {
            try {
                $uploaded = handle_image_upload('image', 'gallery');
                if ($uploaded) {
                    $caption = trim($_POST['caption'] ?? '');
                    $stmt = db()->prepare('INSERT INTO gallery_images (group_key, image_path, caption) VALUES (?, ?, ?)');
                    $stmt->execute([$groupKey, $uploaded, $caption]);
                    flash_set('success', 'Photo uploaded.');
                } else {
                    flash_set('error', 'Please choose an image to upload.');
                }
            } catch (RuntimeException $e) {
                flash_set('error', $e->getMessage());
            }
        }
    }

    header('Location: gallery.php');
    exit;
}

$pageTitle = 'Gallery';
require_once __DIR__ . '/includes/admin-header.php';

$images = db()->query('SELECT * FROM gallery_images ORDER BY group_key ASC, display_order ASC, id DESC')->fetchAll();
$byGroup = [];
foreach ($images as $img) {
    $byGroup[$img['group_key']][] = $img;
}
?>

<div class="stat-card mb-4">
  <h2 class="h6 mb-3">Upload Photo</h2>
  <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
    <?= csrf_field() ?>
    <div class="col-md-3">
      <label class="form-label">Group</label>
      <select name="group_key" class="form-select" required>
        <?php foreach ($groups as $key => $label): ?>
          <option value="<?= e($key) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Image</label>
      <input type="file" name="image" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Caption (optional)</label>
      <input type="text" name="caption" class="form-control">
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn gma-btn-gold w-100">Upload</button>
    </div>
  </form>
</div>

<?php foreach ($groups as $key => $label): ?>
  <div class="mb-4">
    <h2 class="h6 mb-3"><?= e($label) ?></h2>
    <div class="row g-3">
      <?php if (empty($byGroup[$key])): ?>
        <div class="col-12"><p class="text-secondary small">No photos in this group yet.</p></div>
      <?php else: ?>
        <?php foreach ($byGroup[$key] as $img): ?>
          <div class="col-6 col-md-3 col-lg-2">
            <div class="stat-card p-2">
              <img src="<?= e(uploads_url($img['image_path'])) ?>" class="img-fluid rounded mb-2" style="aspect-ratio:1/1;object-fit:cover;width:100%;" alt="<?= e($img['caption']) ?>">
              <form method="post" onsubmit="return confirm('Delete this photo?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $img['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
