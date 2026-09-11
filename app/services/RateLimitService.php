<?php
/**
 * Gaggle NFT — Rate Limit Service
 * 
 * Database-backed rate limiting for application submissions.
 */

class RateLimitService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Check short-term rate limit (e.g., 5 submissions per 10 minutes).
     * Returns null if OK, error message if limited.
     */
    public function checkShortTerm(string $ip): ?string {
        $windowMinutes = (int) get_setting('rate_limit_window_minutes', 'application', '10');
        $maxRequests = (int) get_setting('rate_limit_max_requests', 'application', '5');

        if ($windowMinutes <= 0 || $maxRequests <= 0) return null;

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM rate_limits 
             WHERE ip_address = ? AND endpoint = 'apply' 
             AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stmt->execute([$ip, $windowMinutes]);
        $count = (int) $stmt->fetchColumn();

        if ($count >= $maxRequests) {
            return "Too many submissions. Please wait {$windowMinutes} minutes before trying again.";
        }

        return null;
    }

    /**
     * Check an arbitrary endpoint using database-backed counters.
     */
    public function checkEndpoint(string $ip, string $endpoint, int $windowMinutes, int $maxRequests): ?string {
        if ($windowMinutes <= 0 || $maxRequests <= 0) return null;

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM rate_limits
             WHERE ip_address = ? AND endpoint = ?
             AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stmt->execute([$ip, $endpoint, $windowMinutes]);

        if ((int) $stmt->fetchColumn() >= $maxRequests) {
            return "Too many requests. Please wait {$windowMinutes} minutes before trying again.";
        }
        return null;
    }

    /**
     * Check lifetime IP limit (e.g., 100 applications per IP).
     * Uses the applications table as source of truth.
     */
    public function checkLifetime(string $ip): ?string {
        $limit = (int) get_setting('ip_application_limit', 'application', '100');
        if ($limit <= 0) return null;

        $appModel = new Application();
        $count = $appModel->countByIp($ip);

        if ($count >= $limit) {
            return 'Maximum application limit reached for this IP address.';
        }

        return null;
    }

    /**
     * Record a rate limit event.
     */
    public function record(string $ip, string $endpoint = 'apply'): void {
        $stmt = $this->db->prepare(
            "INSERT INTO rate_limits (ip_address, endpoint) VALUES (?, ?)"
        );
        $stmt->execute([$ip, $endpoint]);
    }

    /**
     * Cleanup old rate limit records (older than 24 hours).
     */
    public function cleanup(): int {
        $stmt = $this->db->prepare(
            "DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Get rate limit stats for dashboard.
     */
    public function getStats(): array {
        $today = (int) $this->db->query(
            "SELECT COUNT(*) FROM rate_limits WHERE DATE(created_at) = CURDATE()"
        )->fetchColumn();

        return ['rate_limit_events_today' => $today];
    }
}
