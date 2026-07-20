<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$article = ['title' => '', 'slug' => '', 'excerpt' => '', 'body' => '', 'image_path' => null, 'status' => 'published'];

if ($id) {
    $stmt = db()->prepare('SELECT * FROM news_articles WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash_set('error', 'Article not found.');
        header('Location: news.php');
        exit;
    }
    $article = $found;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $title = trim($_POST['title'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
    $customSlug = trim($_POST['slug'] ?? '');

    if ($title === '') $errors[] = 'Title is required.';
    if ($body === '') $errors[] = 'Body content is required.';

    $imagePath = $article['image_path'];
    if (!$errors) {
        try {
            $uploaded = handle_image_upload('image', 'news');
            if ($uploaded) {
                $imagePath = $uploaded;
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!empty($_POST['remove_image'])) {
        $imagePath = null;
    }

    if (!$errors) {
        $slug = $customSlug !== '' ? unique_slug($customSlug, $id) : unique_slug($title, $id);
        if ($status === 'published') {
            $publishedAt = !empty($article['published_at']) ? $article['published_at'] : date('Y-m-d H:i:s');
        } else {
            $publishedAt = null;
        }

        if ($id) {
            $stmt = db()->prepare(
                'UPDATE news_articles SET title=?, slug=?, excerpt=?, body=?, image_path=?, status=?, published_at=? WHERE id=?'
            );
            $stmt->execute([$title, $slug, $excerpt, $body, $imagePath, $status, $publishedAt, $id]);
            flash_set('success', 'Article updated.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO news_articles (title, slug, excerpt, body, image_path, status, published_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$title, $slug, $excerpt, $body, $imagePath, $status, $publishedAt]);
            flash_set('success', 'Article created.');
        }
        header('Location: news.php');
        exit;
    }

    $article = array_merge($article, compact('title', 'excerpt', 'body', 'status'), ['image_path' => $imagePath]);
}

$pageTitle = $id ? 'Edit Article' : 'New Article';
require_once __DIR__ . '/includes/admin-header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="stat-card">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-8">
      <label class="form-label">Title</label>
      <input type="text" name="title" class="form-control" value="<?= e($article['title']) ?>" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <option value="published" <?= $article['status'] === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="draft" <?= $article['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
      </select>
    </div>
    <div class="col-md-8">
      <label class="form-label">URL Slug <span class="text-secondary">(optional — auto-generated from title if blank)</span></label>
      <input type="text" name="slug" class="form-control" value="<?= e($article['slug']) ?>" placeholder="auto-generated">
    </div>
    <div class="col-12">
      <label class="form-label">Excerpt</label>
      <textarea name="excerpt" rows="2" class="form-control" maxlength="500"><?= e($article['excerpt']) ?></textarea>
    </div>
    <div class="col-12">
      <label class="form-label">Body (HTML allowed)</label>
      <textarea name="body" rows="12" class="form-control" required><?= e($article['body']) ?></textarea>
    </div>
    <div class="col-12">
      <label class="form-label">Featured Image</label>
      <?php if (!empty($article['image_path'])): ?>
        <div class="mb-2 d-flex align-items-center gap-3">
          <img src="<?= e(uploads_url($article['image_path'])) ?>" class="thumb-preview" alt="">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeImage">
            <label class="form-check-label small" for="removeImage">Remove current image</label>
          </div>
        </div>
      <?php endif; ?>
      <input type="file" name="image" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif">
      <div class="form-text">JPG, PNG, WEBP, or GIF. Max 5MB.</div>
    </div>
    <div class="col-12 d-flex gap-2">
      <button type="submit" class="btn gma-btn-gold px-4"><?= $id ? 'Save Changes' : 'Create Article' ?></button>
      <a href="news.php" class="btn btn-outline-dark">Cancel</a>
    </div>
  </div>
</form>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
