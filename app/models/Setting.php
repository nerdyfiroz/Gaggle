<?php
/**
 * Gaggle NFT — Setting Model
 */

class Setting {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Get a single setting.
     */
    public function get(string $key, ?string $group = null, $default = null): ?string {
        return get_setting($key, $group, $default);
    }

    /**
     * Set a single setting.
     */
    public function set(string $key, ?string $value, string $group = 'general'): bool {
        return set_setting($key, $value, $group);
    }

    /**
     * Get all settings in a group.
     */
    public function getGroup(string $group): array {
        return get_settings_group($group);
    }

    /**
     * Update multiple settings at once.
     */
    public function updateGroup(string $group, array $settings): void {
        foreach ($settings as $key => $value) {
            $this->set($key, $value, $group);
        }
    }

    /**
     * Get all settings as a nested array.
     */
    public function getAll(): array {
        $stmt = $this->db->query("SELECT setting_group, setting_key, setting_value FROM settings ORDER BY setting_group, setting_key");
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_group']][$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }
}
