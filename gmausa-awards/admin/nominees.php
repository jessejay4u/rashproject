<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editing = ['category_id' => '', 'name' => '', 'year' => (int) date('Y'), 'is_winner' => 0, 'image_path' => null];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $stmt = db()->prepare('SELECT image_path FROM nominees WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        $row = $stmt->fetch();
        db()->prepare('DELETE FROM nominees WHERE id = ?')->execute([(int) $_POST['id']]);
        if ($row && $row['image_path']) {
            $full = UPLOADS_PATH . '/' . $row['image_path'];
            if (is_file($full)) @unlink($full);
        }
        flash_set('success', 'Nominee removed.');
        header('Location: nominees.php');
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $year = (int) ($_POST['year'] ?? date('Y'));
    $isWinner = !empty($_POST['is_winner']) ? 1 : 0;

    if (!$categoryId) $errors[] = 'Please choose a category.';
    if ($name === '') $errors[] = 'Nominee name is required.';

    $imagePath = null;
    if ($id) {
        $existing = db()->prepare('SELECT image_path FROM nominees WHERE id = ?');
        $existing->execute([$id]);
        $imagePath = $existing->fetchColumn() ?: null;
    }

    if (!$errors) {
        try {
            $uploaded = handle_image_upload('image', 'nominees');
            if ($uploaded) {
                $imagePath = $uploaded;
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        if ($id) {
            $stmt = db()->prepare('UPDATE nominees SET category_id=?, name=?, year=?, is_winner=?, image_path=? WHERE id=?');
            $stmt->execute([$categoryId, $name, $year, $isWinner, $imagePath, $id]);
            flash_set('success', 'Nominee updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO nominees (category_id, name, year, is_winner, image_path) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$categoryId, $name, $year, $isWinner, $imagePath]);
            flash_set('success', 'Nominee added.');
        }
        header('Location: nominees.php');
        exit;
    }

    $editing = compact('categoryId', 'name', 'year', 'isWinner') + ['category_id' => $categoryId, 'is_winner' => $isWinner, 'image_path' => $imagePath];
}

if ($editId && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = db()->prepare('SELECT * FROM nominees WHERE id = ?');
    $stmt->execute([$editId]);
    $found = $stmt->fetch();
    if ($found) {
        $editing = $found;
    }
}

$pageTitle = 'Nominees';
require_once __DIR__ . '/includes/admin-header.php';

$categories = db()->query('SELECT id, name FROM award_categories ORDER BY category_group ASC, display_order ASC')->fetchAll();

$stmt = db()->query(
    'SELECT n.*, c.name AS category_name
     FROM nominees n JOIN award_categories c ON c.id = n.category_id
     ORDER BY n.year DESC, c.display_order ASC, n.name ASC'
);
$nominees = $stmt->fetchAll();
?>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="stat-card">
      <h2 class="h6 mb-3"><?= $editId ? 'Edit Nominee' : 'Add Nominee' ?></h2>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $editId ?>">
        <div class="mb-3">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-select" required>
            <option value="">Choose&hellip;</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= (($editing['category_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Nominee Name</label>
          <input type="text" name="name" class="form-control" value="<?= e($editing['name'] ?? '') ?>" required>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Year</label>
            <input type="number" name="year" class="form-control" value="<?= (int) ($editing['year'] ?? date('Y')) ?>">
          </div>
          <div class="col-6 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_winner" value="1" id="isWinner" <?= !empty($editing['is_winner']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="isWinner">Winner</label>
            </div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Photo</label>
          <?php if (!empty($editing['image_path'])): ?>
            <div class="mb-2"><img src="<?= e(uploads_url($editing['image_path'])) ?>" class="thumb-preview" alt=""></div>
          <?php endif; ?>
          <input type="file" name="image" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif">
        </div>
        <button type="submit" class="btn gma-btn-gold px-4"><?= $editId ? 'Save' : 'Add Nominee' ?></button>
        <?php if ($editId): ?><a href="nominees.php" class="btn btn-outline-dark">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="stat-card p-0">
      <table class="table align-middle mb-0">
        <thead><tr><th>Name</th><th>Category</th><th>Year</th><th></th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php if (!$nominees): ?>
            <tr><td colspan="5" class="text-center text-secondary py-4">No nominees yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($nominees as $n): ?>
            <tr>
              <td><?= e($n['name']) ?></td>
              <td><?= e($n['category_name']) ?></td>
              <td><?= (int) $n['year'] ?></td>
              <td><?php if ($n['is_winner']): ?><span class="badge text-bg-warning text-dark">Winner</span><?php endif; ?></td>
              <td class="text-end">
                <a href="nominees.php?edit=<?= (int) $n['id'] ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Remove this nominee?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
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
