<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $stmt = db()->prepare('SELECT id_photo_path, media_tag_photo_path FROM accreditation_submissions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        db()->prepare('DELETE FROM accreditation_submissions WHERE id = ?')->execute([$id]);
        foreach (['id_photo_path', 'media_tag_photo_path'] as $col) {
            if ($row && $row[$col]) {
                $full = UPLOADS_PATH . '/' . $row[$col];
                if (is_file($full)) @unlink($full);
            }
        }
        flash_set('success', 'Submission deleted.');
    } elseif (in_array($action, ['new', 'reviewed', 'archived'], true)) {
        db()->prepare('UPDATE accreditation_submissions SET status = ? WHERE id = ?')->execute([$action, $id]);
        flash_set('success', 'Status updated.');
    }

    header('Location: accreditation.php');
    exit;
}

$pageTitle = 'Accreditation Submissions';
require_once __DIR__ . '/includes/admin-header.php';

$submissions = db()->query('SELECT * FROM accreditation_submissions ORDER BY created_at DESC')->fetchAll();
?>

<div class="gma-disclaimer mb-4" style="color:#6b6b70; border-color:#d8d8dc;">
  <i class="bi bi-shield-lock me-1"></i>
  This form collects sensitive personal data (passport details, date of birth, ID photos). Restrict dashboard access, use HTTPS in production, and delete records you no longer need.
</div>

<div class="stat-card p-0">
  <table class="table align-middle mb-0">
    <thead><tr><th>Name</th><th>Institution</th><th>Contact</th><th>Documents</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
      <?php if (!$submissions): ?>
        <tr><td colspan="6" class="text-center text-secondary py-4">No accreditation submissions yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($submissions as $s): ?>
        <tr>
          <td><?= e($s['surname'] . ', ' . $s['first_name']) ?><?= $s['other_names'] ? '<br><span class="text-secondary small">' . e($s['other_names']) . '</span>' : '' ?></td>
          <td><?= e($s['institution']) ?></td>
          <td><?= e($s['email']) ?></td>
          <td>
            <?php if ($s['id_photo_path']): ?><a href="<?= e(uploads_url($s['id_photo_path'])) ?>" target="_blank" class="badge text-bg-dark text-decoration-none">ID Photo</a><?php endif; ?>
            <?php if ($s['media_tag_photo_path']): ?><a href="<?= e(uploads_url($s['media_tag_photo_path'])) ?>" target="_blank" class="badge text-bg-dark text-decoration-none">Media Tag</a><?php endif; ?>
          </td>
          <td><span class="badge text-bg-<?= $s['status'] === 'new' ? 'warning' : ($s['status'] === 'reviewed' ? 'success' : 'secondary') ?>"><?= e($s['status']) ?></span></td>
          <td class="text-end">
            <?php if ($s['status'] !== 'reviewed'): ?>
              <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reviewed">
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-success">Mark Reviewed</button>
              </form>
            <?php endif; ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this submission and its photos?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
