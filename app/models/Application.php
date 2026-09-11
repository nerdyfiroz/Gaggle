<?php
/**
 * Gaggle NFT — Application Model
 * 
 * Handles whitelist application CRUD, filtering, statistics, and export.
 */

class Application {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Create a new application.
     */
    public function create(array $data): ?array {
        $applicationId = generate_application_id();
        
        // Ensure unique application ID
        while ($this->findByApplicationId($applicationId)) {
            $applicationId = generate_application_id();
        }

        $stmt = $this->db->prepare(
            "INSERT INTO applications (application_id, wallet_address, wallet_normalized, twitter_username, discord_username, 
             telegram_username, email, ip_address, user_agent, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );

        $walletNormalized = $this->normalizeWallet($data['wallet_address']);

        $stmt->execute([
            $applicationId,
            $data['wallet_address'],
            $walletNormalized,
            $data['twitter_username'] ?? null,
            $data['discord_username'] ?? null,
            $data['telegram_username'] ?? null,
            $data['email'] ?? null,
            $data['ip_address'],
            $data['user_agent'] ?? null,
        ]);

        $id = (int) $this->db->lastInsertId();
        return $this->findById($id);
    }

    /**
     * Find by internal ID.
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM applications WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find by public application ID (WL-XXXXXXXX).
     */
    public function findByApplicationId(string $applicationId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM applications WHERE application_id = ? LIMIT 1");
        $stmt->execute([$applicationId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find by wallet address.
     */
    public function findByWallet(string $wallet): ?array {
        $stmt = $this->db->prepare("SELECT * FROM applications WHERE wallet_normalized = ? LIMIT 1");
        $stmt->execute([$this->normalizeWallet($wallet)]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Check if wallet already applied.
     */
    public function walletExists(string $wallet): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM applications WHERE wallet_normalized = ?");
        $stmt->execute([$this->normalizeWallet($wallet)]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Check if Twitter username already applied.
     */
    public function twitterExists(string $twitter): bool {
        $twitter = strtolower(trim($twitter));
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM applications WHERE LOWER(twitter_username) = ?");
        $stmt->execute([$twitter]);
        return $stmt->fetchColumn() > 0;
    }

    public function findByIds(array $ids): array {
        $ids = array_values(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0));
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT * FROM applications WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        return $stmt->fetchAll();
    }

    /**
     * Check if Discord username already applied.
     */
    public function discordExists(string $discord): bool {
        $discord = strtolower(trim($discord));
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM applications WHERE LOWER(discord_username) = ?");
        $stmt->execute([$discord]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Count applications from an IP.
     */
    public function countByIp(string $ip): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM applications WHERE ip_address = ?");
        $stmt->execute([$ip]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count applications from an IP within a time window.
     */
    public function countByIpInWindow(string $ip, int $minutes): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM applications WHERE ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stmt->execute([$ip, $minutes]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get paginated list of applications with filtering.
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 25): array {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "a.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(a.application_id LIKE ? OR a.wallet_address LIKE ? OR a.twitter_username LIKE ? OR a.discord_username LIKE ? OR a.ip_address LIKE ? OR a.email LIKE ?)";
            $params = array_merge($params, [$search, $search, $search, $search, $search, $search]);
        }

        if (!empty($filters['date_from'])) {
            $where[] = "a.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = "a.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['ip'])) {
            $where[] = "a.ip_address = ?";
            $params[] = $filters['ip'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM applications a $whereClause");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Calculate pagination
        $totalPages = max(1, ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        // Fetch page
        $stmt = $this->db->prepare(
            "SELECT a.* FROM applications a $whereClause ORDER BY a.created_at DESC LIMIT ? OFFSET ?"
        );
        $allParams = array_merge($params, [$perPage, $offset]);
        $stmt->execute($allParams);

        return [
            'data'        => $stmt->fetchAll(),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Update application status.
     */
    public function updateStatus(int $id, string $status): bool {
        $valid = ['pending', 'approved', 'rejected', 'blacklisted', 'review'];
        if (!in_array($status, $valid)) return false;

        $stmt = $this->db->prepare("UPDATE applications SET status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    /**
     * Add admin note.
     */
    public function addNote(int $id, string $note): bool {
        $stmt = $this->db->prepare(
            "UPDATE applications SET admin_notes = CONCAT(IFNULL(admin_notes, ''), ?) WHERE id = ?"
        );
        $timestamp = date('Y-m-d H:i');
        $entry = "\n[{$timestamp}] {$note}";
        return $stmt->execute([$entry, $id]);
    }

    /**
     * Delete application.
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM applications WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Bulk update status.
     */
    public function bulkUpdateStatus(array $ids, string $status): int {
        $valid = ['pending', 'approved', 'rejected', 'blacklisted', 'review'];
        if (!in_array($status, $valid) || empty($ids)) return 0;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "UPDATE applications SET status = ?, updated_at = NOW() WHERE id IN ({$placeholders})"
        );
        $params = array_merge([$status], array_map('intval', $ids));
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Bulk delete.
     */
    public function bulkDelete(array $ids): int {
        if (empty($ids)) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("DELETE FROM applications WHERE id IN ({$placeholders})");
        $stmt->execute(array_map('intval', $ids));
        return $stmt->rowCount();
    }

    /**
     * Get dashboard statistics.
     */
    public function getStats(): array {
        $stats = [];

        // Total
        $stats['total'] = (int) $this->db->query("SELECT COUNT(*) FROM applications")->fetchColumn();
        
        // By status
        $stmt = $this->db->query("SELECT status, COUNT(*) as cnt FROM applications GROUP BY status");
        $byStatus = [];
        foreach ($stmt->fetchAll() as $row) {
            $byStatus[$row['status']] = (int) $row['cnt'];
        }
        $stats['pending']     = $byStatus['pending'] ?? 0;
        $stats['approved']    = $byStatus['approved'] ?? 0;
        $stats['rejected']    = $byStatus['rejected'] ?? 0;
        $stats['blacklisted'] = $byStatus['blacklisted'] ?? 0;
        $stats['review']      = $byStatus['review'] ?? 0;

        // Today
        $stats['today'] = (int) $this->db->query(
            "SELECT COUNT(*) FROM applications WHERE DATE(created_at) = CURDATE()"
        )->fetchColumn();

        // This week
        $stats['this_week'] = (int) $this->db->query(
            "SELECT COUNT(*) FROM applications WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
        )->fetchColumn();

        // Unique wallets
        $stats['unique_wallets'] = (int) $this->db->query(
            "SELECT COUNT(DISTINCT LOWER(wallet_address)) FROM applications"
        )->fetchColumn();

        // Unique IPs
        $stats['unique_ips'] = (int) $this->db->query(
            "SELECT COUNT(DISTINCT ip_address) FROM applications"
        )->fetchColumn();

        return $stats;
    }

    /**
     * Get daily application counts for chart (last N days).
     */
    public function getDailyStats(int $days = 30): array {
        $stmt = $this->db->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM applications 
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(created_at) 
             ORDER BY date ASC"
        );
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    /**
     * Get applications for CSV export.
     */
    public function getForExport(array $filters = []): array {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "a.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['date_from'])) { $where[] = 'a.created_at >= ?'; $params[] = $filters['date_from'] . ' 00:00:00'; }
        if (!empty($filters['date_to'])) { $where[] = 'a.created_at <= ?'; $params[] = $filters['date_to'] . ' 23:59:59'; }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = '(a.application_id LIKE ? OR a.wallet_address LIKE ? OR a.twitter_username LIKE ? OR a.discord_username LIKE ? OR a.ip_address LIKE ?)';
            array_push($params, $search, $search, $search, $search, $search);
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->db->prepare(
                "SELECT a.application_id, a.wallet_address, a.twitter_username, a.discord_username, 
                    telegram_username, email, ip_address, status, admin_notes, created_at 
                 FROM applications a $whereClause ORDER BY a.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get applications from a specific IP.
     */
    public function getByIp(string $ip, int $page = 1, int $perPage = 25): array {
        return $this->getAll(['ip' => $ip], $page, $perPage);
    }

    /**
     * Get application with its completed tasks.
     */
    public function getWithTasks(int $id): ?array {
        $app = $this->findById($id);
        if (!$app) return null;

        $stmt = $this->db->prepare(
            "SELECT at.*, t.title, t.description, t.type, t.url 
             FROM application_tasks at 
             JOIN tasks t ON at.task_id = t.id 
             WHERE at.application_id = ?
             ORDER BY t.sort_order ASC"
        );
        $stmt->execute([$id]);
        $app['tasks'] = $stmt->fetchAll();

        return $app;
    }

    /**
     * Store task completion records for an application.
     */
    public function storeTasks(int $applicationId, array $taskIds): void {
        $stmt = $this->db->prepare(
            "INSERT INTO application_tasks (application_id, task_id, completed) VALUES (?, ?, 1)"
        );
        foreach ($taskIds as $taskId) {
            $stmt->execute([$applicationId, (int) $taskId]);
        }
    }

    private function normalizeWallet(string $wallet): string {
        $wallet = trim($wallet);
        $blockchain = strtolower((string) get_setting('blockchain', 'general', 'ethereum'));
        return in_array($blockchain, ['solana', 'sol', 'bitcoin', 'btc'], true) ? $wallet : strtolower($wallet);
    }
}
