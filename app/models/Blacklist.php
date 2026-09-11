<?php
/**
 * Gaggle NFT — Blacklist Model
 */

class Blacklist {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Add entry to blacklist.
     */
    public function add(string $type, string $value, ?string $reason = null): bool {
        $value = strtolower(trim($value));
        try {
            $stmt = $this->db->prepare(
                "INSERT IGNORE INTO blacklist (type, value, reason) VALUES (?, ?, ?)"
            );
            return $stmt->execute([$type, $value, $reason]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Remove entry from blacklist.
     */
    public function remove(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM blacklist WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Check if a value is blacklisted.
     */
    public function isBlacklisted(string $type, string $value): bool {
        $value = strtolower(trim($value));
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM blacklist WHERE type = ? AND LOWER(value) = ?");
        $stmt->execute([$type, $value]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Check all relevant blacklist entries for an application.
     */
    public function checkApplication(array $data): ?string {
        if (!empty($data['wallet_address']) && $this->isBlacklisted('wallet', $data['wallet_address'])) {
            return 'This wallet address has been blacklisted.';
        }
        if (!empty($data['ip_address']) && $this->isBlacklisted('ip', $data['ip_address'])) {
            return 'Your IP address has been blacklisted.';
        }
        if (!empty($data['twitter_username']) && $this->isBlacklisted('twitter', $data['twitter_username'])) {
            return 'This Twitter/X account has been blacklisted.';
        }
        if (!empty($data['discord_username']) && $this->isBlacklisted('discord', $data['discord_username'])) {
            return 'This Discord account has been blacklisted.';
        }
        return null;
    }

    /**
     * Get all blacklist entries with pagination.
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 25): array {
        $where = [];
        $params = [];

        if (!empty($filters['type'])) {
            $where[] = "type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['search'])) {
            $where[] = "value LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM blacklist $whereClause");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = max(1, ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            "SELECT * FROM blacklist $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?"
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
