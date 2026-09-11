<?php
/**
 * Gaggle NFT — Admin Change Password
 */
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';

// Load config but skip forced password redirect for this page
define('GAGGLE_ROOT', dirname(__DIR__));
require_once GAGGLE_ROOT . '/app/config/config.php';
require_once APP_PATH . '/models/Admin.php';
require_once APP_PATH . '/models/AdminLog.php';

if (!is_admin_logged_in()) {
    redirect('/admin/login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Invalid security token.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $adminModel = new Admin();
        $admin = $adminModel->findById(admin_id());

        if (!$admin) {
            $error = 'Admin not found.';
        } elseif (!password_verify($current, $admin['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif ($current === $new) {
            $error = 'New password must be different from current password.';
        } else {
            $adminModel->updatePassword(admin_id(), $new);
            $log = new AdminLog();
            $log->log('change_password', 'admin', (string) admin_id());
            flash('success', 'Password changed successfully.');
            redirect('/admin/index.php');
        }
    }
}

$pageTitle = 'Change Password';
ob_start();
?>

<div class="admin-card" style="max-width: 450px;">
    <div class="admin-card-header"><h3>Change Password</h3></div>
    <div class="admin-card-body">
        <?php $adminModel = new Admin(); if ($adminModel->mustChangePassword(admin_id())): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> You must change your default password before continuing.</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="admin-form">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" required>
            </div>
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" required minlength="8">
                <small style="color: var(--admin-text-muted);">Minimum 8 characters</small>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn-admin btn-admin-primary" style="width: 100%; justify-content: center;">
                <i class="bi bi-key"></i> Change Password
            </button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include VIEWS_PATH . '/layouts/admin.php';
?>
