<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editing = ['message' => '', 'link_url' => '', 'is_active' => 1, 'display_order' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $stmt = db()->prepare('DELETE FROM updates WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        flash_set('success', 'Update removed.');
    } elseif ($action === 'toggle') {
        $stmt = db()->prepare('UPDATE updates SET is_active = 1 - is_active WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        flash_set('success', 'Update status changed.');
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $link = trim($_POST['link_url'] ?? '');
        $order = (int) ($_POST['display_order'] ?? 0);
        $active = !empty($_POST['is_active']) ? 1 : 0;

        if ($message !== '') {
            if ($id) {
                $stmt = db()->prepare('UPDATE updates SET message=?, link_url=?, display_order=?, is_active=? WHERE id=?');
                $stmt->execute([$message, $link, $order, $active, $id]);
                flash_set('success', 'Update saved.');
            } else {
                $stmt = db()->prepare('INSERT INTO updates (message, link_url, display_order, is_active) VALUES (?, ?, ?, ?)');
                $stmt->execute([$message, $link, $order, $active]);
                flash_set('success', 'Update posted.');
            }
        } else {
            flash_set('error', 'Message is required.');
        }
    }

    header('Location: updates.php');
    exit;
}

if ($editId) {
    $stmt = db()->prepare('SELECT * FROM updates WHERE id = ?');
    $stmt->execute([$editId]);
    $found = $stmt->fetch();
    if ($found) {
        $editing = $found;
    }
}

$pageTitle = 'Latest Updates';
require_once __DIR__ . '/includes/admin-header.php';

$updates = db()->query('SELECT * FROM updates ORDER BY display_order ASC, id DESC')->fetchAll();
?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="stat-card">
      <h2 class="h6 mb-3"><?= $editId ? 'Edit Update' : 'Post a New Update' ?></h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $editId ?>">
        <div class="mb-3">
          <label class="form-label">Message</label>
          <textarea name="message" rows="3" class="form-control" required maxlength="500"><?= e($editing['message']) ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Link (optional)</label>
          <input type="text" name="link_url" class="form-control" value="<?= e($editing['link_url']) ?>" placeholder="/news.php">
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Display Order</label>
            <input type="number" name="display_order" class="form-control" value="<?= (int) $editing['display_order'] ?>">
          </div>
          <div class="col-6 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" <?= $editing['is_active'] ? 'checked' : '' ?>>
              <label class="form-check-label" for="isActive">Active</label>
            </div>
          </div>
        </div>
        <button type="submit" class="btn gma-btn-gold px-4"><?= $editId ? 'Save' : 'Post Update' ?></button>
        <?php if ($editId): ?><a href="updates.php" class="btn btn-outline-dark">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="stat-card p-0">
      <table class="table align-middle mb-0">
        <thead><tr><th>Message</th><th>Active</th><th>Order</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php if (!$updates): ?>
            <tr><td colspan="4" class="text-center text-secondary py-4">No updates posted yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($updates as $u): ?>
            <tr>
              <td><?= e($u['message']) ?></td>
              <td>
                <form method="post" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>"><?= $u['is_active'] ? 'Active' : 'Hidden' ?></button>
                </form>
              </td>
              <td><?= (int) $u['display_order'] ?></td>
              <td class="text-end">
                <a href="updates.php?edit=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this update?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
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
