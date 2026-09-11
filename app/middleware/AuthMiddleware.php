<?php
/**
 * Gaggle NFT — Auth Middleware
 * 
 * Protects admin pages. Include at the top of every admin page.
 */

// Load application
require_once dirname(__DIR__, 2) . '/app/config/config.php';

// Load models
require_once APP_PATH . '/models/Admin.php';
require_once APP_PATH . '/models/AdminLog.php';

/**
 * Require admin authentication.
 * Redirects to login if not authenticated.
 */
function require_admin_auth(): void {
    // Check session
    if (!is_admin_logged_in()) {
        flash('error', 'Please log in to access the admin panel.');
        redirect('/admin/login.php');
    }

    // Check session timeout
    $timeout = (int) get_setting('session_timeout', 'security', '3600');
    if (isset($_SESSION['admin_last_activity'])) {
        if (time() - $_SESSION['admin_last_activity'] > $timeout) {
            // Session expired
            session_unset();
            session_destroy();
            session_start();
            flash('error', 'Your session has expired. Please log in again.');
            redirect('/admin/login.php');
        }
    }
    $_SESSION['admin_last_activity'] = time();

    // Check forced password change
    $admin = new Admin();
    if ($admin->mustChangePassword(admin_id())) {
        $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
        if ($currentPage !== 'change-password.php' && $currentPage !== 'logout.php') {
            flash('warning', 'You must change your password before continuing.');
            redirect('/admin/change-password.php');
        }
    }

    // Verify CSRF on POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfEnabled = get_setting('csrf_enabled', 'security', '1');
        if ($csrfEnabled === '1' && !verify_csrf()) {
            if (is_ajax()) {
                json_response(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.'], 403);
            }
            flash('error', 'Invalid security token. Please try again.');
            redirect($_SERVER['HTTP_REFERER'] ?? '/admin/');
        }
    }
}
