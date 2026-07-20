<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editing = ['name' => '', 'category_group' => 'us_based', 'description' => '', 'display_order' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM award_categories WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        flash_set('success', 'Category deleted.');
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $group = ($_POST['category_group'] ?? 'us_based') === 'ghana_based' ? 'ghana_based' : 'us_based';
        $description = trim($_POST['description'] ?? '');
        $order = (int) ($_POST['display_order'] ?? 0);

        if ($name !== '') {
            if ($id) {
                $stmt = db()->prepare('UPDATE award_categories SET name=?, category_group=?, description=?, display_order=? WHERE id=?');
                $stmt->execute([$name, $group, $description, $order, $id]);
                flash_set('success', 'Category updated.');
            } else {
                $stmt = db()->prepare('INSERT INTO award_categories (name, category_group, description, display_order) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $group, $description, $order]);
                flash_set('success', 'Category added.');
            }
        } else {
            flash_set('error', 'Category name is required.');
        }
    }

    header('Location: categories.php');
    exit;
}

if ($editId) {
    $stmt = db()->prepare('SELECT * FROM award_categories WHERE id = ?');
    $stmt->execute([$editId]);
    $found = $stmt->fetch();
    if ($found) {
        $editing = $found;
    }
}

$pageTitle = 'Award Categories';
require_once __DIR__ . '/includes/admin-header.php';

$categories = db()->query('SELECT * FROM award_categories ORDER BY category_group ASC, display_order ASC')->fetchAll();
?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="stat-card">
      <h2 class="h6 mb-3"><?= $editId ? 'Edit Category' : 'Add Category' ?></h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $editId ?>">
        <div class="mb-3">
          <label class="form-label">Category Name</label>
          <input type="text" name="name" class="form-control" value="<?= e($editing['name']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Group</label>
          <select name="category_group" class="form-select">
            <option value="us_based" <?= $editing['category_group'] === 'us_based' ? 'selected' : '' ?>>US-Based</option>
            <option value="ghana_based" <?= $editing['category_group'] === 'ghana_based' ? 'selected' : '' ?>>Ghana-Based</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" rows="2" class="form-control"><?= e($editing['description']) ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Display Order</label>
          <input type="number" name="display_order" class="form-control" value="<?= (int) $editing['display_order'] ?>">
        </div>
        <button type="submit" class="btn gma-btn-gold px-4"><?= $editId ? 'Save' : 'Add Category' ?></button>
        <?php if ($editId): ?><a href="categories.php" class="btn btn-outline-dark">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="stat-card p-0">
      <table class="table align-middle mb-0">
        <thead><tr><th>Name</th><th>Group</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php if (!$categories): ?>
            <tr><td colspan="3" class="text-center text-secondary py-4">No categories yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($categories as $c): ?>
            <tr>
              <td><?= e($c['name']) ?></td>
              <td><span class="badge text-bg-<?= $c['category_group'] === 'ghana_based' ? 'success' : 'warning' ?>"><?= $c['category_group'] === 'ghana_based' ? 'Ghana-Based' : 'US-Based' ?></span></td>
              <td class="text-end">
                <a href="categories.php?edit=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this category? Its nominees will also be removed.');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
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
