<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        db()->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([$id]);
        flash_set('success', 'Message deleted.');
    } elseif ($action === 'mark_read') {
        db()->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = ?')->execute([$id]);
        flash_set('success', 'Marked as read.');
    }

    header('Location: messages.php');
    exit;
}

$pageTitle = 'Contact Messages';
require_once __DIR__ . '/includes/admin-header.php';

$messages = db()->query('SELECT * FROM contact_messages ORDER BY created_at DESC')->fetchAll();
?>

<div class="stat-card p-0">
  <table class="table align-middle mb-0">
    <thead><tr><th>From</th><th>Subject</th><th>Message</th><th>Received</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
      <?php if (!$messages): ?>
        <tr><td colspan="5" class="text-center text-secondary py-4">No messages yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($messages as $m): ?>
        <tr class="<?= $m['is_read'] ? '' : 'fw-bold' ?>">
          <td><?= e($m['name']) ?><br><span class="text-secondary small fw-normal"><?= e($m['email']) ?></span></td>
          <td><?= e($m['subject']) ?></td>
          <td style="max-width:320px;" class="text-truncate"><?= e($m['message']) ?></td>
          <td class="text-secondary small fw-normal"><?= e(format_date($m['created_at'])) ?></td>
          <td class="text-end">
            <?php if (!$m['is_read']): ?>
              <form method="post" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_read">
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-dark"><i class="bi bi-envelope-open"></i></button>
              </form>
            <?php endif; ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this message?');">
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

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
