<?php
require_once __DIR__ . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

csrf_verify();

$email = trim($_POST['email'] ?? '');
$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';

if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $stmt = db()->prepare(
        'INSERT INTO newsletter_subscribers (email) VALUES (?) ON DUPLICATE KEY UPDATE email = email'
    );
    $stmt->execute([$email]);
    flash_set('success', 'Thanks for subscribing to GMA-USA updates!');
} else {
    flash_set('error', 'Please enter a valid email address to subscribe.');
}

header('Location: ' . $referer);
exit;
