<?php
/**
 * Gaggle NFT — Core Configuration
 * 
 * Loads environment variables, sets up paths, configures PHP settings.
 * This file is included by all entry points.
 */

// Prevent direct access
if (!defined('GAGGLE_ROOT')) {
    define('GAGGLE_ROOT', dirname(__DIR__, 2));
}

// Load .env file
$envFile = GAGGLE_ROOT . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Remove surrounding quotes
        if (preg_match('/^"(.*)"$/', $value, $m)) $value = $m[1];
        if (preg_match("/^'(.*)'$/", $value, $m)) $value = $m[1];
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

/**
 * Get environment variable with optional default.
 */
function env(string $key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null) return $default;
    // Cast common strings
    $lower = strtolower($value);
    if ($lower === 'true') return true;
    if ($lower === 'false') return false;
    if ($lower === 'null') return null;
    return $value;
}

// Application environment
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', env('APP_DEBUG', false));
define('APP_URL', env('APP_URL', 'http://localhost'));

// Baseline headers also apply when Apache serves only the public directory.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

// Error reporting based on environment
if (APP_ENV === 'development' || APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', GAGGLE_ROOT . '/storage/logs/php_errors.log');
}

// Path constants
define('APP_PATH', GAGGLE_ROOT . '/app');
define('PUBLIC_PATH', GAGGLE_ROOT . '/public');
define('VIEWS_PATH', APP_PATH . '/views');
define('STORAGE_PATH', GAGGLE_ROOT . '/storage');

// Session configuration
$sessionLifetime = (int) env('SESSION_LIFETIME', 3600);
$sessionName = env('SESSION_NAME', 'gaggle_session');
$isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', (string) $sessionLifetime);

if ($isSecure) {
    ini_set('session.cookie_secure', '1');
}

session_name($sessionName);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default timezone
date_default_timezone_set('UTC');

// Load helpers
require_once APP_PATH . '/helpers/functions.php';

// Load database config
require_once APP_PATH . '/config/database.php';
