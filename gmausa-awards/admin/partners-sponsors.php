<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editing = ['name' => '', 'partner_type' => 'sponsor', 'website_url' => '', 'display_order' => 0, 'logo_path' => null];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $stmt = db()->prepare('SELECT logo_path FROM partners WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        $row = $stmt->fetch();
        db()->prepare('DELETE FROM partners WHERE id = ?')->execute([(int) $_POST['id']]);
        if ($row && $row['logo_path']) {
            $full = UPLOADS_PATH . '/' . $row['logo_path'];
            if (is_file($full)) @unlink($full);
        }
        flash_set('success', 'Partner removed.');
        header('Location: partners-sponsors.php');
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $partnerType = ($_POST['partner_type'] ?? 'sponsor') === 'partner' ? 'partner' : 'sponsor';
    $websiteUrl = trim($_POST['website_url'] ?? '');
    $order = (int) ($_POST['display_order'] ?? 0);

    if ($name === '') $errors[] = 'Name is required.';

    $logoPath = null;
    if ($id) {
        $existing = db()->prepare('SELECT logo_path FROM partners WHERE id = ?');
        $existing->execute([$id]);
        $logoPath = $existing->fetchColumn() ?: null;
    }

    if (!$errors) {
        try {
            $uploaded = handle_image_upload('logo', 'partners');
            if ($uploaded) {
                $logoPath = $uploaded;
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        if ($id) {
            $stmt = db()->prepare('UPDATE partners SET name=?, partner_type=?, website_url=?, display_order=?, logo_path=? WHERE id=?');
            $stmt->execute([$name, $partnerType, $websiteUrl, $order, $logoPath, $id]);
            flash_set('success', 'Partner updated.');
        } else {
            $stmt = db()->prepare('INSERT INTO partners (name, partner_type, website_url, display_order, logo_path) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $partnerType, $websiteUrl, $order, $logoPath]);
            flash_set('success', 'Partner added.');
        }
        header('Location: partners-sponsors.php');
        exit;
    }

    $editing = compact('name', 'partnerType', 'websiteUrl', 'order') + [
        'partner_type' => $partnerType, 'website_url' => $websiteUrl, 'display_order' => $order, 'logo_path' => $logoPath,
    ];
}

if ($editId && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = db()->prepare('SELECT * FROM partners WHERE id = ?');
    $stmt->execute([$editId]);
    $found = $stmt->fetch();
    if ($found) {
        $editing = $found;
    }
}

$pageTitle = 'Partners & Sponsors';
require_once __DIR__ . '/includes/admin-header.php';

$partners = db()->query('SELECT * FROM partners ORDER BY partner_type ASC, display_order ASC')->fetchAll();
?>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="stat-card">
      <h2 class="h6 mb-3"><?= $editId ? 'Edit Partner' : 'Add Partner / Sponsor' ?></h2>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $editId ?>">
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input type="text" name="name" class="form-control" value="<?= e($editing['name']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Type</label>
          <select name="partner_type" class="form-select">
            <option value="sponsor" <?= $editing['partner_type'] === 'sponsor' ? 'selected' : '' ?>>Sponsor</option>
            <option value="partner" <?= $editing['partner_type'] === 'partner' ? 'selected' : '' ?>>Partner</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Website URL</label>
          <input type="text" name="website_url" class="form-control" value="<?= e($editing['website_url']) ?>" placeholder="https://">
        </div>
        <div class="mb-3">
          <label class="form-label">Display Order</label>
          <input type="number" name="display_order" class="form-control" value="<?= (int) ($editing['display_order'] ?? 0) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Logo</label>
          <?php if (!empty($editing['logo_path'])): ?>
            <div class="mb-2"><img src="<?= e(uploads_url($editing['logo_path'])) ?>" class="thumb-preview" alt=""></div>
          <?php endif; ?>
          <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif">
        </div>
        <button type="submit" class="btn gma-btn-gold px-4"><?= $editId ? 'Save' : 'Add' ?></button>
        <?php if ($editId): ?><a href="partners-sponsors.php" class="btn btn-outline-dark">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="stat-card p-0">
      <table class="table align-middle mb-0">
        <thead><tr><th>Logo</th><th>Name</th><th>Type</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php if (!$partners): ?>
            <tr><td colspan="4" class="text-center text-secondary py-4">No partners or sponsors yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($partners as $p): ?>
            <tr>
              <td>
                <?php if ($p['logo_path']): ?>
                  <img src="<?= e(uploads_url($p['logo_path'])) ?>" class="thumb-preview" alt="">
                <?php else: ?>
                  <div class="thumb-preview bg-light d-flex align-items-center justify-content-center text-secondary"><i class="bi bi-image"></i></div>
                <?php endif; ?>
              </td>
              <td><?= e($p['name']) ?></td>
              <td><span class="badge text-bg-<?= $p['partner_type'] === 'partner' ? 'success' : 'warning' ?>"><?= ucfirst($p['partner_type']) ?></span></td>
              <td class="text-end">
                <a href="partners-sponsors.php?edit=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Remove this partner?');">
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
