<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editing = ['name' => '', 'role_affiliation' => '', 'bio' => '', 'display_order' => 0, 'photo_path' => null];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $stmt = db()->prepare('SELECT photo_path FROM patrons WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        $row = $stmt->fetch();
        db()->prepare('DELETE FROM patrons WHERE id = ?')->execute([(int) $_POST['id']]);
        if ($row && $row['photo_path']) {
            $full = UPLOADS_PATH . '/' . $row['photo_path'];
            if (is_file($full)) @unlink($full);
        }
        flash_set('success', 'Patron removed.');
        header('Location: patrons.php');
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $roleAffiliation = trim($_POST['role_affiliation'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $order = (int) ($_POST['display_order'] ?? 0);

    if ($name === '') $errors[] = 'Name is required.';

    $photoPath = null;
    if ($id) {
        $existing = db()->prepare('SELECT photo_path FROM patrons WHERE id = ?');
        $existing->execute([$id]);
        $photoPath = $existing->fetchColumn() ?: null;
    }

    if (!$errors) {
        try {
            $uploaded = handle_image_upload('photo', 'patrons');
            if ($uploaded) {
                $photoPath = $uploaded;
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        if ($id) {
            $stmt = db()->prepare('UPDATE patrons SET name=?, role_affiliation=?, bio=?, display_order=?, photo_path=? WHERE id=?');
            $stmt->execute([$name, $roleAffiliation, $bio, $order, $photoPath, $id]);
            flash_set('success', 'Patron updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO patrons (name, role_affiliation, bio, display_order, photo_path) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $roleAffiliation, $bio, $order, $photoPath]);
            flash_set('success', 'Patron added.');
        }
        header('Location: patrons.php');
        exit;
    }

    $editing = compact('name', 'roleAffiliation', 'bio', 'order') + ['role_affiliation' => $roleAffiliation, 'display_order' => $order, 'photo_path' => $photoPath];
}

if ($editId && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = db()->prepare('SELECT * FROM patrons WHERE id = ?');
    $stmt->execute([$editId]);
    $found = $stmt->fetch();
    if ($found) {
        $editing = $found;
    }
}

$pageTitle = 'Life Patrons';
require_once __DIR__ . '/includes/admin-header.php';

$patrons = db()->query('SELECT * FROM patrons ORDER BY display_order ASC')->fetchAll();
?>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="stat-card">
      <h2 class="h6 mb-3"><?= $editId ? 'Edit Patron' : 'Add Patron' ?></h2>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $editId ?>">
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input type="text" name="name" class="form-control" value="<?= e($editing['name']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Role / Affiliation</label>
          <input type="text" name="role_affiliation" class="form-control" value="<?= e($editing['role_affiliation']) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Bio</label>
          <textarea name="bio" rows="4" class="form-control"><?= e($editing['bio']) ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Display Order</label>
          <input type="number" name="display_order" class="form-control" value="<?= (int) ($editing['display_order'] ?? 0) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Photo</label>
          <?php if (!empty($editing['photo_path'])): ?>
            <div class="mb-2"><img src="<?= e(uploads_url($editing['photo_path'])) ?>" class="thumb-preview" alt=""></div>
          <?php endif; ?>
          <input type="file" name="photo" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif">
        </div>
        <button type="submit" class="btn gma-btn-gold px-4"><?= $editId ? 'Save' : 'Add Patron' ?></button>
        <?php if ($editId): ?><a href="patrons.php" class="btn btn-outline-dark">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="stat-card p-0">
      <table class="table align-middle mb-0">
        <thead><tr><th>Name</th><th>Role / Affiliation</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php if (!$patrons): ?>
            <tr><td colspan="3" class="text-center text-secondary py-4">No patrons yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($patrons as $p): ?>
            <tr>
              <td><?= e($p['name']) ?></td>
              <td class="small text-secondary"><?= e($p['role_affiliation']) ?></td>
              <td class="text-end">
                <a href="patrons.php?edit=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Remove this patron?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
