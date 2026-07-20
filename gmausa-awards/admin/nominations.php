<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        db()->prepare('DELETE FROM nomination_submissions WHERE id = ?')->execute([$id]);
        flash_set('success', 'Submission deleted.');
    } elseif (in_array($action, ['reviewed', 'archived', 'new'], true)) {
        db()->prepare('UPDATE nomination_submissions SET status = ? WHERE id = ?')->execute([$action, $id]);
        flash_set('success', 'Status updated.');
    }

    header('Location: nominations.php');
    exit;
}

$pageTitle = 'Nomination Submissions';
require_once __DIR__ . '/includes/admin-header.php';

$stmt = db()->query(
    'SELECT ns.*, c.name AS category_name
     FROM nomination_submissions ns
     LEFT JOIN award_categories c ON c.id = ns.category_id
     ORDER BY ns.created_at DESC'
);
$submissions = $stmt->fetchAll();
?>

<div class="stat-card p-0">
  <table class="table align-middle mb-0">
    <thead><tr><th>Nominee</th><th>Category</th><th>Submitted By</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
      <?php if (!$submissions): ?>
        <tr><td colspan="5" class="text-center text-secondary py-4">No nomination submissions yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($submissions as $s): ?>
        <tr>
          <td><?= e($s['nominee_name']) ?></td>
          <td><?= e($s['category_name'] ?? '—') ?></td>
          <td><?= e($s['submitted_by']) ?><br><span class="text-secondary small"><?= e($s['email']) ?></span></td>
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
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this submission?');">
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
