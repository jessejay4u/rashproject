<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editing = ['title' => '', 'video_url' => '', 'display_order' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        db()->prepare('DELETE FROM videos WHERE id = ?')->execute([(int) $_POST['id']]);
        flash_set('success', 'Video removed.');
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $videoUrl = trim($_POST['video_url'] ?? '');
        $order = (int) ($_POST['display_order'] ?? 0);

        if ($title !== '') {
            if ($id) {
                $stmt = db()->prepare('UPDATE videos SET title=?, video_url=?, display_order=? WHERE id=?');
                $stmt->execute([$title, $videoUrl ?: null, $order, $id]);
                flash_set('success', 'Video updated.');
            } else {
                $stmt = db()->prepare('INSERT INTO videos (title, video_url, display_order) VALUES (?, ?, ?)');
                $stmt->execute([$title, $videoUrl ?: null, $order]);
                flash_set('success', 'Video added.');
            }
        } else {
            flash_set('error', 'Title is required.');
        }
    }

    header('Location: videos.php');
    exit;
}

if ($editId) {
    $stmt = db()->prepare('SELECT * FROM videos WHERE id = ?');
    $stmt->execute([$editId]);
    $found = $stmt->fetch();
    if ($found) {
        $editing = $found;
    }
}

$pageTitle = 'Videos';
require_once __DIR__ . '/includes/admin-header.php';

$videos = db()->query('SELECT * FROM videos ORDER BY display_order ASC, id ASC')->fetchAll();
?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="stat-card">
      <h2 class="h6 mb-3"><?= $editId ? 'Edit Video' : 'Add Video' ?></h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $editId ?>">
        <div class="mb-3">
          <label class="form-label">Title</label>
          <input type="text" name="title" class="form-control" value="<?= e($editing['title']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Video URL (YouTube)</label>
          <input type="text" name="video_url" class="form-control" value="<?= e($editing['video_url']) ?>" placeholder="https://www.youtube.com/watch?v=...">
        </div>
        <div class="mb-3">
          <label class="form-label">Display Order</label>
          <input type="number" name="display_order" class="form-control" value="<?= (int) $editing['display_order'] ?>">
        </div>
        <button type="submit" class="btn gma-btn-gold px-4"><?= $editId ? 'Save' : 'Add Video' ?></button>
        <?php if ($editId): ?><a href="videos.php" class="btn btn-outline-dark">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="stat-card p-0">
      <table class="table align-middle mb-0">
        <thead><tr><th>Title</th><th>URL</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php if (!$videos): ?>
            <tr><td colspan="3" class="text-center text-secondary py-4">No videos yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($videos as $v): ?>
            <tr>
              <td><?= e($v['title']) ?></td>
              <td class="small text-secondary text-truncate" style="max-width:220px;"><?= e($v['video_url']) ?: '—' ?></td>
              <td class="text-end">
                <a href="videos.php?edit=<?= (int) $v['id'] ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Remove this video?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
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
