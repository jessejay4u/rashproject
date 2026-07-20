<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/functions.php';

if (admin_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Basic throttle to slow down brute force
    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
    $_SESSION['login_last_attempt'] = $_SESSION['login_last_attempt'] ?? 0;

    if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['login_last_attempt']) < 60) {
        $error = 'Too many failed attempts. Please wait a minute and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = db()->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['display_name'];
            $_SESSION['login_attempts'] = 0;
            $_SESSION['admin_last_activity'] = time();
            header('Location: index.php');
            exit;
        }

        $_SESSION['login_attempts']++;
        $_SESSION['login_last_attempt'] = time();
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login | <?= e(setting('site_name', 'GMA-USA')) ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="d-flex align-items-center" style="min-height:100vh;">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="text-center mb-4">
        <i class="bi bi-star-fill text-warning fs-1"></i>
        <h1 class="h4 mt-2 mb-0"><?= e(setting('site_name', 'GMA-USA')) ?></h1>
        <p class="text-secondary small">Admin Dashboard</p>
      </div>
      <div class="gma-form-panel">
        <?php if (!empty($_GET['timeout'])): ?>
          <div class="alert alert-warning">Your session expired. Please log in again.</div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button type="submit" class="btn gma-btn-gold w-100">Log In</button>
        </form>
      </div>
      <p class="text-center text-secondary small mt-3"><a href="../index.php">&larr; Back to site</a></p>
    </div>
  </div>
</div>
</body>
</html>
