<?php
/**
 * Gaggle NFT — Admin Model
 * 
 * Handles admin authentication, login throttling, and password management.
 */

require_once APP_PATH . '/services/RateLimitService.php';

class Admin {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Authenticate admin by username and password.
     * Returns admin data on success, error string on failure.
     */
    public function authenticate(string $username, string $password): array|string {
        $rateLimiter = new RateLimitService();
        $ip = get_client_ip();
        if ($rateLimiter->checkEndpoint($ip, 'admin_login', 15, 10)) {
            return 'Too many login attempts. Please wait and try again.';
        }

        // Check lockout
        $admin = $this->findByUsername($username);
        
        if (!$admin) {
            $rateLimiter->record($ip, 'admin_login');
            return 'Invalid username or password.';
        }

        // Check if locked out
        if ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
            $remaining = ceil((strtotime($admin['locked_until']) - time()) / 60);
            return "Account is locked. Try again in {$remaining} minute(s).";
        }

        // Verify password
        if (!password_verify($password, $admin['password_hash'])) {
            $rateLimiter->record($ip, 'admin_login');
            $this->recordFailedAttempt($admin['id']);
            return 'Invalid username or password.';
        }

        // Success — reset attempts
        $this->resetLoginAttempts($admin['id']);
        $this->updateLastLogin($admin['id']);

        return $admin;
    }

    /**
     * Find admin by username.
     */
    public function findByUsername(string $username): ?array {
        $stmt = $this->db->prepare("SELECT * FROM admins WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find admin by ID.
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM admins WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Record failed login attempt.
     */
    private function recordFailedAttempt(int $id): void {
        $maxAttempts = (int) env('ADMIN_LOGIN_MAX_ATTEMPTS', 5);
        $lockoutMinutes = (int) env('ADMIN_LOGIN_LOCKOUT_MINUTES', 15);

        $stmt = $this->db->prepare(
            "UPDATE admins SET login_attempts = login_attempts + 1, 
             locked_until = IF(login_attempts + 1 >= ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), locked_until)
             WHERE id = ?"
        );
        $stmt->execute([$maxAttempts, $lockoutMinutes, $id]);
    }

    /**
     * Reset login attempts after successful login.
     */
    private function resetLoginAttempts(int $id): void {
        $stmt = $this->db->prepare("UPDATE admins SET login_attempts = 0, locked_until = NULL WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Update last login timestamp and IP.
     */
    private function updateLastLogin(int $id): void {
        $stmt = $this->db->prepare("UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?");
        $stmt->execute([get_client_ip(), $id]);
    }

    /**
     * Update admin password.
     */
    public function updatePassword(int $id, string $newPassword): bool {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->db->prepare("UPDATE admins SET password_hash = ?, force_password_change = 0 WHERE id = ?");
        return $stmt->execute([$hash, $id]);
    }

    /**
     * Check if admin must change password.
     */
    public function mustChangePassword(int $id): bool {
        $admin = $this->findById($id);
        return $admin && $admin['force_password_change'] == 1;
    }
}
