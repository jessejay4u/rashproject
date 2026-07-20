<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editing = ['name' => '', 'role' => '', 'member_type' => 'core_team', 'photo_path' => null];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $stmt = db()->prepare('SELECT photo_path FROM team_members WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        $row = $stmt->fetch();
        db()->prepare('DELETE FROM team_members WHERE id = ?')->execute([(int) $_POST['id']]);
        if ($row && $row['photo_path']) {
            $full = UPLOADS_PATH . '/' . $row['photo_path'];
            if (is_file($full)) @unlink($full);
        }
        flash_set('success', 'Team member removed.');
        header('Location: team.php');
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $memberType = ($_POST['member_type'] ?? 'core_team') === 'board' ? 'board' : 'core_team';
    $order = (int) ($_POST['display_order'] ?? 0);

    if ($name === '') $errors[] = 'Name is required.';
    if ($role === '') $errors[] = 'Role is required.';

    $photoPath = null;
    if ($id) {
        $existing = db()->prepare('SELECT photo_path FROM team_members WHERE id = ?');
        $existing->execute([$id]);
        $photoPath = $existing->fetchColumn() ?: null;
    }

    if (!$errors) {
        try {
            $uploaded = handle_image_upload('photo', 'team');
            if ($uploaded) {
                $photoPath = $uploaded;
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        if ($id) {
            $stmt = db()->prepare('UPDATE team_members SET name=?, role=?, member_type=?, display_order=?, photo_path=? WHERE id=?');
            $stmt->execute([$name, $role, $memberType, $order, $photoPath, $id]);
            flash_set('success', 'Team member updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO team_members (name, role, member_type, display_order, photo_path) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $role, $memberType, $order, $photoPath]);
            flash_set('success', 'Team member added.');
        }
        header('Location: team.php');
        exit;
    }

    $editing = compact('name', 'role') + ['member_type' => $memberType, 'display_order' => $order, 'photo_path' => $photoPath];
}

if ($editId && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = db()->prepare('SELECT * FROM team_members WHERE id = ?');
    $stmt->execute([$editId]);
    $found = $stmt->fetch();
    if ($found) {
        $editing = $found;
    }
}

$pageTitle = 'Team';
require_once __DIR__ . '/includes/admin-header.php';

$members = db()->query('SELECT * FROM team_members ORDER BY member_type ASC, display_order ASC')->fetchAll();
?>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="stat-card">
      <h2 class="h6 mb-3"><?= $editId ? 'Edit Team Member' : 'Add Team Member' ?></h2>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $editId ?>">
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input type="text" name="name" class="form-control" value="<?= e($editing['name']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Role</label>
          <input type="text" name="role" class="form-control" value="<?= e($editing['role']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Group</label>
          <select name="member_type" class="form-select">
            <option value="core_team" <?= $editing['member_type'] === 'core_team' ? 'selected' : '' ?>>Core Team</option>
            <option value="board" <?= $editing['member_type'] === 'board' ? 'selected' : '' ?>>Board</option>
          </select>
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
        <button type="submit" class="btn gma-btn-gold px-4"><?= $editId ? 'Save' : 'Add Member' ?></button>
        <?php if ($editId): ?><a href="team.php" class="btn btn-outline-dark">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="stat-card p-0">
      <table class="table align-middle mb-0">
        <thead><tr><th>Name</th><th>Role</th><th>Group</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php if (!$members): ?>
            <tr><td colspan="4" class="text-center text-secondary py-4">No team members yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($members as $m): ?>
            <tr>
              <td><?= e($m['name']) ?></td>
              <td><?= e($m['role']) ?></td>
              <td><span class="badge text-bg-<?= $m['member_type'] === 'board' ? 'success' : 'warning' ?>"><?= $m['member_type'] === 'board' ? 'Board' : 'Core Team' ?></span></td>
              <td class="text-end">
                <a href="team.php?edit=<?= (int) $m['id'] ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Remove this team member?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
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
