<?php
/**
 * Gaggle NFT — Admin Login
 */
define('GAGGLE_ROOT', dirname(__DIR__));
require_once GAGGLE_ROOT . '/app/config/config.php';
require_once APP_PATH . '/models/Admin.php';
require_once APP_PATH . '/models/AdminLog.php';

// Already logged in?
if (is_admin_logged_in()) {
    redirect('/admin/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $adminModel = new Admin();
            $result = $adminModel->authenticate($username, $password);

            if (is_array($result)) {
                // Success
                $_SESSION['admin_id'] = $result['id'];
                $_SESSION['admin_username'] = $result['username'];
                $_SESSION['admin_last_activity'] = time();
                regenerate_csrf();
                session_regenerate_id(true);

                // Log
                $log = new AdminLog();
                $log->log('login', 'admin', (string) $result['id']);

                redirect('/admin/index.php');
            } else {
                $error = $result;
            }
        }
    }
}

$projectName = get_setting('project_name', 'general', 'Gaggle');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= e($projectName) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="icon" type="image/jpeg" href="/assets/images/hero-pfp.jpg">
</head>
<body class="admin-body">
    <div class="login-page">
        <div class="login-card">
            <div class="login-logo">
                <img src="/assets/images/hero-pfp.jpg" alt="<?= e($projectName) ?>" style="width: 64px; height: 64px; border-radius: 16px;">
                <h2><?= e($projectName) ?> Admin</h2>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($msg = get_flash('error')): ?>
                <div class="alert alert-error"><?= e($msg) ?></div>
            <?php endif; ?>
            <?php if ($msg = get_flash('success')): ?>
                <div class="alert alert-success"><?= e($msg) ?></div>
            <?php endif; ?>

            <form method="POST" class="admin-form">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn-admin btn-admin-primary" style="width: 100%; justify-content: center; padding: 12px;">
                    <i class="bi bi-box-arrow-in-right"></i> Log In
                </button>
            </form>
        </div>
    </div>
</body>
</html>
