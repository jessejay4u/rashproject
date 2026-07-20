<?php
/**
 * Edit ONLY this file to match your hosting environment.
 */

define('DB_HOST', getenv('GMAUSA_DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('GMAUSA_DB_PORT') ?: '3306');
define('DB_NAME', getenv('GMAUSA_DB_NAME') ?: 'gmausa_awards');
define('DB_USER', getenv('GMAUSA_DB_USER') ?: 'root');
define('DB_PASS', getenv('GMAUSA_DB_PASS') ?: '');

define('APP_NAME', 'GMA-USA | Ghana Music Awards USA');
define('APP_URL', getenv('GMAUSA_APP_URL') ?: 'http://localhost/gmausa-awards');
define('UPLOADS_URL', APP_URL . '/uploads');
define('UPLOADS_PATH', __DIR__ . '/../uploads');

// Session lifetime in seconds (default 8 hours)
define('SESSION_LIFETIME', 28800);

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}
