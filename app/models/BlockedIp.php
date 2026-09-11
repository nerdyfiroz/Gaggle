<?php
/**
 * Gaggle NFT — Blocked IP Model
 */

class BlockedIp {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Block an IP address.
     */
    public function block(string $ip, ?string $reason = null): bool {
        try {
            $stmt = $this->db->prepare("INSERT IGNORE INTO blocked_ips (ip_address, reason) VALUES (?, ?)");
            return $stmt->execute([$ip, $reason]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Unblock an IP address.
     */
    public function unblock(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM blocked_ips WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Check if an IP is blocked.
     */
    public function isBlocked(string $ip): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM blocked_ips WHERE ip_address = ?");
        $stmt->execute([$ip]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get all blocked IPs with application stats.
     */
    public function getAll(int $page = 1, int $perPage = 25): array {
        $total = (int) $this->db->query("SELECT COUNT(*) FROM blocked_ips")->fetchColumn();
        $totalPages = max(1, ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            "SELECT bi.*, 
                    (SELECT COUNT(*) FROM applications a WHERE a.ip_address = bi.ip_address) as app_count,
                    (SELECT MIN(a.created_at) FROM applications a WHERE a.ip_address = bi.ip_address) as first_submission,
                    (SELECT MAX(a.created_at) FROM applications a WHERE a.ip_address = bi.ip_address) as last_submission
             FROM blocked_ips bi 
             ORDER BY bi.blocked_at DESC 
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$perPage, $offset]);

        return [
            'data'        => $stmt->fetchAll(),
            'total'       => $total,
            'page'        => $page,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Get IP stats from applications table (for IP management page).
     */
    public function getIpStats(int $page = 1, int $perPage = 25, ?string $search = null): array {
        $where = '';
        $params = [];

        if ($search) {
            $where = "WHERE a.ip_address LIKE ?";
            $params[] = '%' . $search . '%';
        }

        $countStmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT a.ip_address) FROM applications a $where"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = max(1, ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            "SELECT a.ip_address,
                    COUNT(*) as app_count,
                    MIN(a.created_at) as first_submission,
                    MAX(a.created_at) as last_submission,
                    (SELECT COUNT(*) FROM blocked_ips bi WHERE bi.ip_address = a.ip_address) as is_blocked
             FROM applications a 
             $where
             GROUP BY a.ip_address
             ORDER BY app_count DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($params, [$perPage, $offset]));

        return [
            'data'        => $stmt->fetchAll(),
            'total'       => $total,
            'page'        => $page,
            'total_pages' => $totalPages,
        ];
    }
}
