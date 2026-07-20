<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $form = $_POST['form'] ?? '';

    if ($form === 'settings') {
        $fields = [
            'site_name', 'site_tagline', 'hero_heading', 'hero_subheading', 'about_text',
            'ceo_name', 'contact_email', 'contact_phone', 'contact_address', 'facebook_url',
            'instagram_url', 'twitter_url', 'vote_url', 'tickets_url', 'nominations_notice',
            'entry_usa_text', 'entry_ghana_text', 'charity_text', 'newsletter_text', 'footer_note',
        ];
        foreach ($fields as $field) {
            set_setting($field, trim($_POST[$field] ?? ''));
        }
        set_setting('nominations_open', !empty($_POST['nominations_open']) ? '1' : '0');
        flash_set('success', 'Settings saved.');
        header('Location: settings.php');
        exit;
    }

    if ($form === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = db()->prepare('SELECT * FROM admins WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($current, $admin['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 10) {
            $errors[] = 'New password must be at least 10 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New password and confirmation do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([$hash, $_SESSION['admin_id']]);
            flash_set('success', 'Password changed successfully.');
            header('Location: settings.php');
            exit;
        }
    }
}

$pageTitle = 'Site Settings';
require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="stat-card">
      <h2 class="h6 mb-3">Site Content</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="settings">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Site Name</label>
            <input type="text" name="site_name" class="form-control" value="<?= e(setting('site_name')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Tagline</label>
            <input type="text" name="site_tagline" class="form-control" value="<?= e(setting('site_tagline')) ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Hero Heading</label>
            <input type="text" name="hero_heading" class="form-control" value="<?= e(setting('hero_heading')) ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Hero Subheading</label>
            <textarea name="hero_subheading" rows="2" class="form-control"><?= e(setting('hero_subheading')) ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">About Text</label>
            <textarea name="about_text" rows="4" class="form-control"><?= e(setting('about_text')) ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">CEO Name</label>
            <input type="text" name="ceo_name" class="form-control" value="<?= e(setting('ceo_name')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Contact Email</label>
            <input type="email" name="contact_email" class="form-control" value="<?= e(setting('contact_email')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Contact Phone</label>
            <input type="text" name="contact_phone" class="form-control" value="<?= e(setting('contact_phone')) ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Contact Address</label>
            <input type="text" name="contact_address" class="form-control" value="<?= e(setting('contact_address')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Facebook URL</label>
            <input type="text" name="facebook_url" class="form-control" value="<?= e(setting('facebook_url')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Instagram URL</label>
            <input type="text" name="instagram_url" class="form-control" value="<?= e(setting('instagram_url')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Twitter / X URL</label>
            <input type="text" name="twitter_url" class="form-control" value="<?= e(setting('twitter_url')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Vote Now URL (external)</label>
            <input type="text" name="vote_url" class="form-control" value="<?= e(setting('vote_url')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Online Tickets URL (external)</label>
            <input type="text" name="tickets_url" class="form-control" value="<?= e(setting('tickets_url')) ?>" placeholder="Eventbrite or other ticket link">
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="nominations_open" value="1" id="nomOpen" <?= setting('nominations_open') === '1' ? 'checked' : '' ?>>
              <label class="form-check-label" for="nomOpen">Nominations are currently open</label>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Nominations Closed Notice</label>
            <input type="text" name="nominations_notice" class="form-control" value="<?= e(setting('nominations_notice')) ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Entry Procedures &mdash; USA</label>
            <textarea name="entry_usa_text" rows="3" class="form-control"><?= e(setting('entry_usa_text')) ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Entry Procedures &mdash; Ghana</label>
            <textarea name="entry_ghana_text" rows="3" class="form-control"><?= e(setting('entry_ghana_text')) ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Charity Text</label>
            <textarea name="charity_text" rows="3" class="form-control"><?= e(setting('charity_text')) ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Newsletter Prompt Text</label>
            <input type="text" name="newsletter_text" class="form-control" value="<?= e(setting('newsletter_text')) ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Footer Disclaimer Note</label>
            <textarea name="footer_note" rows="2" class="form-control"><?= e(setting('footer_note')) ?></textarea>
          </div>
        </div>
        <button type="submit" class="btn gma-btn-gold px-4 mt-3">Save Settings</button>
      </form>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="stat-card">
      <h2 class="h6 mb-3">Change Password</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="password">
        <div class="mb-3">
          <label class="form-label">Current Password</label>
          <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">New Password</label>
          <input type="password" name="new_password" class="form-control" minlength="10" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-control" minlength="10" required>
        </div>
        <button type="submit" class="btn btn-dark px-4">Update Password</button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
