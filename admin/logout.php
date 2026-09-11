<?php
/**
 * Gaggle NFT — Admin Logout
 */
define('GAGGLE_ROOT', dirname(__DIR__));
require_once GAGGLE_ROOT . '/app/config/config.php';
require_once APP_PATH . '/models/AdminLog.php';

if (is_admin_logged_in()) {
    $log = new AdminLog();
    $log->log('logout', 'admin', (string) admin_id());
}

session_unset();
session_destroy();

// Start new session for flash message
session_start();
flash('success', 'You have been logged out.');
redirect('/admin/login.php');
