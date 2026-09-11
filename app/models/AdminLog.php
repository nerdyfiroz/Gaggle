<?php
/**
 * Gaggle NFT — Admin Log Model (Audit Trail)
 */

class AdminLog {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Log an admin action.
     */
    public function log(string $action, ?string $targetType = null, ?string $targetId = null, ?string $details = null): void {
        $stmt = $this->db->prepare(
            "INSERT INTO admin_logs (admin_id, admin_username, action, target_type, target_id, details, ip_address) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            admin_id(),
            admin_username(),
            $action,
            $targetType,
            $targetId,
            $details,
            get_client_ip(),
        ]);
    }

    /**
     * Get paginated log entries.
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 50): array {
        $where = [];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = "action = ?";
            $params[] = $filters['action'];
        }

        if (!empty($filters['admin_id'])) {
            $where[] = "admin_id = ?";
            $params[] = $filters['admin_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM admin_logs $whereClause");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = max(1, ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            "SELECT * FROM admin_logs $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($params, [$perPage, $offset]));

        return [
            'data'        => $stmt->fetchAll(),
            'total'       => $total,
            'page'        => $page,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Get distinct action types for filter dropdown.
     */
    public function getActionTypes(): array {
        return $this->db->query("SELECT DISTINCT action FROM admin_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
    }
}
