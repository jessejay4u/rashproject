<?php

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Set error handling based on environment
if ($_ENV['APP_ENV'] === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// ── Security Headers ─────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ── CORS ─────────────────────────────────────────────────────
$allowedOrigins = array_filter(explode(',', $_ENV['CORS_ORIGINS'] ?? $_ENV['APP_URL'] ?? ''));
$origin         = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins, true) || $_ENV['APP_ENV'] !== 'production') {
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, X-Device-ID');
    header('Access-Control-Max-Age: 86400');
}

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Route ─────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Only handle /api/* routes here; static files served by nginx
if (!str_starts_with($path, '/api/') && $path !== '/api') {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

try {
    require_once __DIR__ . '/../src/Routes/api.php';
    route($method, $path);
} catch (\Throwable $e) {
    $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';

    error_log('Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $isProduction ? 'An internal server error occurred.' : $e->getMessage(),
        'trace'   => $isProduction ? null : $e->getTraceAsString(),
    ]);
}
