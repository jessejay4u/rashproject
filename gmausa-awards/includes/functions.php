<?php
require_once __DIR__ . '/../config/db.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'post';
}

function unique_slug(string $base, int $ignoreId = 0): string
{
    $slug = slugify($base);
    $original = $slug;
    $i = 1;
    $stmt = db()->prepare('SELECT COUNT(*) FROM news_articles WHERE slug = ? AND id != ?');
    while (true) {
        $stmt->execute([$slug, $ignoreId]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $original . '-' . (++$i);
    }
}

/** @var array<string,string>|null */
$GLOBALS['__settings_cache'] = null;

function setting(string $key, string $default = ''): string
{
    if ($GLOBALS['__settings_cache'] === null) {
        $GLOBALS['__settings_cache'] = [];
        foreach (db()->query('SELECT setting_key, setting_value FROM site_settings') as $row) {
            $GLOBALS['__settings_cache'][$row['setting_key']] = $row['setting_value'];
        }
    }
    return $GLOBALS['__settings_cache'][$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
    $GLOBALS['__settings_cache'][$key] = $value;
}

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid or expired form submission (CSRF check failed). Go back and try again.');
    }
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Validate and move an uploaded image into $subdir under UPLOADS_PATH.
 * Returns the relative path (e.g. "news/abc123.jpg") or null if no file was uploaded.
 * Throws RuntimeException on validation failure.
 */
function handle_image_upload(string $fieldName, string $subdir): ?string
{
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed with error code ' . $file['error']);
    }

    $maxBytes = 5 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Image must be smaller than 5MB.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP, or GIF images are allowed.');
    }

    if (!getimagesize($file['tmp_name'])) {
        throw new RuntimeException('The uploaded file is not a valid image.');
    }

    $ext = $allowed[$mime];
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destDir = rtrim(UPLOADS_PATH, '/') . '/' . trim($subdir, '/');
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        throw new RuntimeException('Could not create upload directory.');
    }

    $dest = $destDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }

    return trim($subdir, '/') . '/' . $filename;
}

function uploads_url(?string $relativePath): ?string
{
    if (!$relativePath) {
        return null;
    }
    return UPLOADS_URL . '/' . ltrim($relativePath, '/');
}

function format_date(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    return $ts ? date('F j, Y', $ts) : '';
}
