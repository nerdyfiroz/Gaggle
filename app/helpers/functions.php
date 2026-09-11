<?php
/**
 * Gaggle NFT — Global Helper Functions
 * 
 * Utility functions used throughout the application.
 */

// ---- XSS Protection ----

/**
 * Escape output for HTML context.
 */
function e(?string $value): string {
    if ($value === null) return '';
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---- CSRF Protection ----

/**
 * Generate or retrieve CSRF token.
 */
function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Generate hidden CSRF input field.
 */
function csrf_field(): string {
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify CSRF token from request.
 */
function verify_csrf(?string $token = null): bool {
    $token = $token ?? ($_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($token) || empty($_SESSION['_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['_csrf_token'], $token);
}

/**
 * Regenerate CSRF token (call after successful form submission).
 */
function regenerate_csrf(): void {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}

// ---- Redirect ----

/**
 * Redirect to URL and exit.
 */
function redirect(string $url, int $code = 302): void {
    header("Location: $url", true, $code);
    exit;
}

// ---- Flash Messages ----

/**
 * Set a flash message.
 */
function flash(string $key, $value): void {
    $_SESSION['_flash'][$key] = $value;
}

/**
 * Get and remove a flash message.
 */
function get_flash(string $key, $default = null) {
    $value = $_SESSION['_flash'][$key] ?? $default;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

/**
 * Check if flash message exists.
 */
function has_flash(string $key): bool {
    return isset($_SESSION['_flash'][$key]);
}

// ---- Form Value Preservation ----

/**
 * Store form data in session for repopulation.
 */
function store_old(array $data): void {
    $_SESSION['_old_input'] = $data;
}

/**
 * Get old input value.
 */
function old(string $key, string $default = ''): string {
    $value = $_SESSION['_old_input'][$key] ?? $default;
    return $value;
}

/**
 * Clear old input.
 */
function clear_old(): void {
    unset($_SESSION['_old_input']);
}

// ---- Application ID ----

/**
 * Generate unique application ID (WL-XXXXXXXX).
 */
function generate_application_id(): string {
    return 'WL-' . strtoupper(bin2hex(random_bytes(4)));
}

// ---- Settings ----

/**
 * Get a setting value.
 */
function get_setting(string $key, ?string $group = null, $default = null): ?string {
    static $cache = [];
    $cacheKey = ($group ?? 'any') . '.' . $key;
    
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    
    try {
        $db = Database::getConnection();
        if ($group) {
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_group = ? AND setting_key = ? LIMIT 1");
            $stmt->execute([$group, $key]);
        } else {
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
        }
        $result = $stmt->fetchColumn();
        $value = $result !== false ? $result : $default;
        $cache[$cacheKey] = $value;
        return $value;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Set a setting value.
 */
function set_setting(string $key, ?string $value, string $group = 'general'): bool {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            "INSERT INTO settings (setting_group, setting_key, setting_value) 
             VALUES (?, ?, ?) 
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        return $stmt->execute([$group, $key, $value]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get all settings in a group.
 */
function get_settings_group(string $group): array {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_group = ?");
        $stmt->execute([$group]);
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (Exception $e) {
        return [];
    }
}

// ---- IP Detection ----

/**
 * Get client IP address.
 */
function get_client_ip(): string {
    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $trustedProxies = array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))));

    // Proxy headers are only trustworthy when the direct peer is explicitly trusted.
    if (in_array($remoteAddress, $trustedProxies, true)) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $header) {
            if (empty($_SERVER[$header])) continue;
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }

    return filter_var($remoteAddress, FILTER_VALIDATE_IP) ? $remoteAddress : '0.0.0.0';
}

// ---- Date Formatting ----

/**
 * Format a date string.
 */
function format_date(?string $date, string $format = 'M j, Y g:i A'): string {
    if (empty($date)) return 'N/A';
    try {
        return (new DateTime($date))->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Time ago helper.
 */
function time_ago(string $datetime): string {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

// ---- AJAX ----

/**
 * Check if request is AJAX.
 */
function is_ajax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send JSON response and exit.
 */
function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- Miscellaneous ----

/**
 * Mask a wallet address for display.
 */
function mask_wallet(string $wallet): string {
    if (strlen($wallet) < 10) return $wallet;
    return substr($wallet, 0, 6) . '...' . substr($wallet, -4);
}

/**
 * Sanitize string input.
 */
function sanitize(string $value): string {
    return trim(strip_tags($value));
}

/**
 * Get status badge HTML.
 */
function status_badge(string $status): string {
    $classes = [
        'pending'     => 'badge-warning',
        'approved'    => 'badge-success',
        'rejected'    => 'badge-danger',
        'blacklisted' => 'badge-dark',
        'review'      => 'badge-info',
    ];
    $class = $classes[$status] ?? 'badge-secondary';
    return '<span class="badge ' . $class . '">' . e(ucfirst($status)) . '</span>';
}

/**
 * Check if admin is logged in.
 */
function is_admin_logged_in(): bool {
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_username']);
}

/**
 * Get logged-in admin ID.
 */
function admin_id(): ?int {
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Get logged-in admin username.
 */
function admin_username(): ?string {
    return $_SESSION['admin_username'] ?? null;
}

/**
 * Get task type icon class.
 */
function task_type_icon(string $type): string {
    $icons = [
        'twitter_follow'  => 'bi-twitter-x',
        'twitter_like'    => 'bi-heart',
        'twitter_repost'  => 'bi-arrow-repeat',
        'discord_join'    => 'bi-discord',
        'telegram_join'   => 'bi-telegram',
        'website_visit'   => 'bi-globe',
        'custom'          => 'bi-check-circle',
    ];
    return $icons[$type] ?? 'bi-check-circle';
}

/**
 * Get task type label.
 */
function task_type_label(string $type): string {
    $labels = [
        'twitter_follow'  => 'Twitter/X Follow',
        'twitter_like'    => 'Twitter/X Like',
        'twitter_repost'  => 'Twitter/X Repost',
        'discord_join'    => 'Discord Join',
        'telegram_join'   => 'Telegram Join',
        'website_visit'   => 'Website Visit',
        'custom'          => 'Custom Task',
    ];
    return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

/**
 * Create storage directories if they don't exist.
 */
function ensure_storage(): void {
    $dirs = [
        STORAGE_PATH,
        STORAGE_PATH . '/logs',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
