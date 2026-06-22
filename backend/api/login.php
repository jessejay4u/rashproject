<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);

$b    = body();
$email = strtolower(trim($b['email'] ?? ''));
$pass  = $b['password'] ?? '';

if (!$email || !$pass) fail('Email and password are required');

$stmt = db()->prepare('
    SELECT u.*, r.name AS role
    FROM users u
    JOIN roles r ON r.id = u.role_id
    WHERE u.email = :email AND u.is_active = 1
');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if (!$user || !password_verify($pass, $user['password_hash'])) {
    // Increment failed attempts
    if ($user) {
        db()->prepare('UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = :id')
           ->execute(['id' => $user['id']]);
    }
    fail('Invalid email or password', 401);
}

if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
    fail('Account temporarily locked. Try again later.', 423);
}

// Load permissions
$permStmt = db()->prepare('
    SELECT p.name
    FROM permissions p
    JOIN role_permissions rp ON rp.permission_id = p.id
    WHERE rp.role_id = :rid
');
$permStmt->execute(['rid' => $user['role_id']]);
$permissions = array_column($permStmt->fetchAll(), 'name');

// Update last login
db()->prepare('UPDATE users SET last_login_at = NOW(), failed_login_attempts = 0, locked_until = NULL WHERE id = :id')
   ->execute(['id' => $user['id']]);

$_SESSION['user'] = [
    'id'          => $user['id'],
    'name'        => $user['name'],
    'email'       => $user['email'],
    'role'        => $user['role'],
    'role_id'     => $user['role_id'],
    'hospital_id' => $user['hospital_id'],
    'region_id'   => $user['region_id'],
    'permissions' => $permissions,
];
session_regenerate_id(true);

ok($_SESSION['user'], 'Login successful');
