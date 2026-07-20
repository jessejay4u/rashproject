<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    db()->prepare('DELETE FROM newsletter_subscribers WHERE id = ?')->execute([(int) $_POST['id']]);
    flash_set('success', 'Subscriber removed.');
    header('Location: newsletter.php');
    exit;
}

$pageTitle = 'Newsletter Subscribers';
require_once __DIR__ . '/includes/admin-header.php';

$subscribers = db()->query('SELECT * FROM newsletter_subscribers ORDER BY subscribed_at DESC')->fetchAll();
?>

<p class="text-secondary mb-3"><?= count($subscribers) ?> subscriber(s)</p>

<div class="stat-card p-0">
  <table class="table align-middle mb-0">
    <thead><tr><th>Email</th><th>Subscribed</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
      <?php if (!$subscribers): ?>
        <tr><td colspan="3" class="text-center text-secondary py-4">No subscribers yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($subscribers as $s): ?>
        <tr>
          <td><?= e($s['email']) ?></td>
          <td class="text-secondary small"><?= e(format_date($s['subscribed_at'])) ?></td>
          <td class="text-end">
            <form method="post" class="d-inline" onsubmit="return confirm('Remove this subscriber?');">
              <?= csrf_field() ?>
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
