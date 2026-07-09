<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

// Session setup
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['lifetime' => SESSION_LIFETIME, 'samesite' => 'Lax']);
session_start();

// ─── Database ────────────────────────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => true,
                ]
            );
            $pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $e) {
            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database connection failed. Check config.php']);
            exit;
        }
    }
    return $pdo;
}

// ─── Response helpers ────────────────────────────────────────────────────────
function send(mixed $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ok(mixed $data = null, string $msg = 'OK', int $code = 200): void {
    send(['success' => true, 'message' => $msg, 'data' => $data], $code);
}

function fail(string $msg, int $code = 400): void {
    send(['success' => false, 'error' => $msg], $code);
}

function paginate(array $rows, int $total, int $page, int $perPage): void {
    send([
        'success' => true,
        'data'    => $rows,
        'meta'    => [
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil($total / max(1, $perPage)),
        ],
    ]);
}

// ─── Auth helpers ─────────────────────────────────────────────────────────────
function auth(): array {
    if (empty($_SESSION['user'])) fail('Unauthorized. Please log in.', 401);
    return $_SESSION['user'];
}

function can(array $user, string $perm): bool {
    return in_array($perm, $user['permissions'] ?? [], true);
}

function need(array $user, string $perm): void {
    if (!can($user, $perm)) fail('Access denied', 403);
}

// ─── Input helpers ───────────────────────────────────────────────────────────
function body(): array {
    static $parsed = null;
    if ($parsed === null) {
        $raw = file_get_contents('php://input');
        $parsed = json_decode($raw ?: '{}', true) ?? [];
    }
    return $parsed;
}

function qp(string $key, mixed $default = null): mixed {
    return $_GET[$key] ?? $default;
}

// ─── UUID ─────────────────────────────────────────────────────────────────────
function uid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Block non-OPTIONS direct access to bootstrap
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
