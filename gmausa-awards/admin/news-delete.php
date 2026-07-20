<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: news.php');
    exit;
}

csrf_verify();

$id = (int) ($_POST['id'] ?? 0);
if ($id) {
    $stmt = db()->prepare('SELECT image_path FROM news_articles WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    $del = db()->prepare('DELETE FROM news_articles WHERE id = ?');
    $del->execute([$id]);

    if ($row && $row['image_path']) {
        $full = UPLOADS_PATH . '/' . $row['image_path'];
        if (is_file($full)) {
            @unlink($full);
        }
    }

    flash_set('success', 'Article deleted.');
}

header('Location: news.php');
exit;
