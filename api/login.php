<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);

$b     = body();
$email = strtolower(trim($b['email'] ?? ''));
$pass  = $b['password'] ?? '';

if (!$email || !$pass) fail('Email and password are required');

$stmt = db()->prepare('SELECT * FROM users WHERE email = :email AND is_active = 1');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if (!$user || !password_verify($pass, $user['password_hash'])) {
    fail('Invalid email or password', 401);
}

// Load permissions via role name
$permStmt = db()->prepare('
    SELECT p.name
    FROM permissions p
    JOIN role_permissions rp ON rp.permission_id = p.id
    JOIN roles r ON r.id = rp.role_id
    WHERE r.name = :role
');
$permStmt->execute(['role' => $user['role']]);
$permissions = array_column($permStmt->fetchAll(), 'name');

// Update last login
db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
   ->execute(['id' => $user['id']]);

$_SESSION['user'] = [
    'id'          => $user['id'],
    'name'        => $user['name'],
    'email'       => $user['email'],
    'role'        => $user['role'],
    'hospital_id' => $user['hospital_id'],
    'region_id'   => $user['region_id'],
    'permissions' => $permissions,
];
session_regenerate_id(true);

ok($_SESSION['user'], 'Login successful');
