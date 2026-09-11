<?php
/**
 * Gaggle NFT — Task Model
 */

class Task {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Get all tasks ordered by sort_order.
     */
    public function getAll(): array {
        return $this->db->query("SELECT * FROM tasks ORDER BY sort_order ASC, id ASC")->fetchAll();
    }

    /**
     * Get enabled tasks only.
     */
    public function getEnabled(): array {
        return $this->db->query("SELECT * FROM tasks WHERE enabled = 1 ORDER BY sort_order ASC")->fetchAll();
    }

    /**
     * Get required tasks only.
     */
    public function getRequired(): array {
        return $this->db->query("SELECT * FROM tasks WHERE enabled = 1 AND required = 1 ORDER BY sort_order ASC")->fetchAll();
    }

    /**
     * Find task by ID.
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM tasks WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Create a new task.
     */
    public function create(array $data): int {
        // Get next sort order
        $maxOrder = (int) $this->db->query("SELECT COALESCE(MAX(sort_order), 0) FROM tasks")->fetchColumn();

        $url = $this->safeUrl($data['url'] ?? '');
        $stmt = $this->db->prepare(
            "INSERT INTO tasks (title, description, type, url, required, enabled, sort_order) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['title'],
            $data['description'] ?? '',
            $data['type'] ?? 'custom',
            $url,
            isset($data['required']) ? (int) $data['required'] : 1,
            isset($data['enabled']) ? (int) $data['enabled'] : 1,
            $maxOrder + 1,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a task.
     */
    public function update(int $id, array $data): bool {
        $url = $this->safeUrl($data['url'] ?? '');
        $stmt = $this->db->prepare(
            "UPDATE tasks SET title = ?, description = ?, type = ?, url = ?, 
             required = ?, enabled = ?, updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([
            $data['title'],
            $data['description'] ?? '',
            $data['type'] ?? 'custom',
            $url,
            isset($data['required']) ? (int) $data['required'] : 1,
            isset($data['enabled']) ? (int) $data['enabled'] : 1,
            $id,
        ]);
    }

    /**
     * Delete a task.
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM tasks WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Toggle enabled status.
     */
    public function toggle(int $id): bool {
        $stmt = $this->db->prepare("UPDATE tasks SET enabled = NOT enabled, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Update sort order for multiple tasks.
     */
    public function reorder(array $order): void {
        $stmt = $this->db->prepare("UPDATE tasks SET sort_order = ? WHERE id = ?");
        foreach ($order as $position => $id) {
            $stmt->execute([$position + 1, (int) $id]);
        }
    }

    private function safeUrl(?string $url): string {
        $url = trim((string) $url);
        if ($url === '') return '';
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    }
}
