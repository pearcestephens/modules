<?php
/**
 * Configuration Manager Service
 *
 * Manages all CIS configuration settings with versioning
 *
 * @package CIS\Modules\ControlPanel
 * @version 1.0.0
 */

namespace ControlPanel\Services;

class ConfigManager {

    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->initializeTable();
    }

    /**
     * Initialize configuration table (replaces old config table)
     */
    private function initializeTable() {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `cis_configuration` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `config_key` VARCHAR(200) NOT NULL,
                    `config_value` LONGTEXT,
                    `config_type` ENUM('string','int','float','bool','json','array') DEFAULT 'string',
                    `category` VARCHAR(100) DEFAULT 'general',
                    `description` TEXT,
                    `is_sensitive` TINYINT(1) DEFAULT 0,
                    `is_readonly` TINYINT(1) DEFAULT 0,
                    `validation_rule` VARCHAR(500),
                    `default_value` TEXT,
                    `module_name` VARCHAR(100),
                    `version` INT(11) DEFAULT 1,
                    `created_by` INT(11),
                    `updated_by` INT(11),
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `unique_key` (`config_key`),
                    KEY `idx_category` (`category`),
                    KEY `idx_module` (`module_name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Configuration history for auditing
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS `cis_configuration_history` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `config_id` INT(11) UNSIGNED NOT NULL,
                    `config_key` VARCHAR(200) NOT NULL,
                    `old_value` LONGTEXT,
                    `new_value` LONGTEXT,
                    `changed_by` INT(11),
                    `changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `change_reason` TEXT,
                    PRIMARY KEY (`id`),
                    KEY `idx_config_id` (`config_id`),
                    KEY `idx_changed_at` (`changed_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

        } catch (\Exception $e) {
            error_log("Config Manager Init Error: " . $e->getMessage());
        }
    }

    /**
     * Get configuration value
     */
    public function get($key, $default = null) {
        try {
            $stmt = $this->db->prepare("
                SELECT config_value, config_type
                FROM cis_configuration
                WHERE config_key = ?
            ");
            $stmt->execute([$key]);
            $config = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$config) {
                return $default;
            }

            // Cast to appropriate type
            return $this->castValue($config['config_value'], $config['config_type']);

        } catch (\Exception $e) {
            error_log("Config Get Error: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Set configuration value
     */
    public function set($key, $value, $options = []) {
        try {
            // Get current value for history
            $oldValue = $this->get($key);

            // Detect type
            $type = $options['type'] ?? $this->detectType($value);

            // Convert to string for storage
            $valueString = $this->valueToString($value, $type);

            // Insert or update
            $stmt = $this->db->prepare("
                INSERT INTO cis_configuration (
                    config_key, config_value, config_type, category,
                    description, module_name, updated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    config_value = VALUES(config_value),
                    config_type = VALUES(config_type),
                    version = version + 1,
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP
            ");

            $stmt->execute([
                $key,
                $valueString,
                $type,
                $options['category'] ?? 'general',
                $options['description'] ?? '',
                $options['module'] ?? null,
                $options['user_id'] ?? null
            ]);

            // Log to history
            $this->logChange($key, $oldValue, $value, $options['user_id'] ?? null, $options['reason'] ?? '');

            return true;

        } catch (\Exception $e) {
            error_log("Config Set Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all configurations
     */
    public function getAll($category = null) {
        try {
            if ($category) {
                $stmt = $this->db->prepare("
                    SELECT * FROM cis_configuration
                    WHERE category = ?
                    ORDER BY config_key ASC
                ");
                $stmt->execute([$category]);
            } else {
                $stmt = $this->db->query("
                    SELECT * FROM cis_configuration
                    ORDER BY category, config_key ASC
                ");
            }

            $configs = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $configs[$row['config_key']] = [
                    'value' => $this->castValue($row['config_value'], $row['config_type']),
                    'type' => $row['config_type'],
                    'category' => $row['category'],
                    'description' => $row['description'],
                    'module' => $row['module_name'],
                    'version' => $row['version'],
                    'is_sensitive' => (bool)$row['is_sensitive'],
                    'is_readonly' => (bool)$row['is_readonly']
                ];
            }

            return $configs;

        } catch (\Exception $e) {
            error_log("Config GetAll Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete configuration
     */
    public function delete($key, $userId = null) {
        try {
            $oldValue = $this->get($key);

            $stmt = $this->db->prepare("DELETE FROM cis_configuration WHERE config_key = ?");
            $stmt->execute([$key]);

            $this->logChange($key, $oldValue, null, $userId, 'Configuration deleted');

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get configuration history
     */
    public function getHistory($key, $limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT ch.*, sa.full_name as changed_by_name
                FROM cis_configuration_history ch
                LEFT JOIN staff_accounts sa ON ch.changed_by = sa.staff_id
                WHERE ch.config_key = ?
                ORDER BY ch.changed_at DESC
                LIMIT ?
            ");
            $stmt->execute([$key, $limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Log configuration change
     */
    private function logChange($key, $oldValue, $newValue, $userId, $reason = '') {
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM cis_configuration WHERE config_key = ?
            ");
            $stmt->execute([$key]);
            $configId = $stmt->fetchColumn();

            if ($configId) {
                $stmt = $this->db->prepare("
                    INSERT INTO cis_configuration_history (
                        config_id, config_key, old_value, new_value,
                        changed_by, change_reason
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $configId,
                    $key,
                    json_encode($oldValue),
                    json_encode($newValue),
                    $userId,
                    $reason
                ]);
            }
        } catch (\Exception $e) {
            error_log("Config Log Change Error: " . $e->getMessage());
        }
    }

    /**
     * Cast value to appropriate type
     */
    private function castValue($value, $type) {
        switch ($type) {
            case 'int':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'bool':
                return (bool)$value;
            case 'json':
                return json_decode($value, true);
            case 'array':
                return unserialize($value);
            default:
                return $value;
        }
    }

    /**
     * Detect value type
     */
    private function detectType($value) {
        if (is_bool($value)) return 'bool';
        if (is_int($value)) return 'int';
        if (is_float($value)) return 'float';
        if (is_array($value)) return 'json';
        return 'string';
    }

    /**
     * Convert value to string for storage
     */
    private function valueToString($value, $type) {
        if ($type === 'json') {
            return json_encode($value);
        }
        if ($type === 'array') {
            return serialize($value);
        }
        if ($type === 'bool') {
            return $value ? '1' : '0';
        }
        return (string)$value;
    }

    /**
     * Export configuration to JSON file
     */
    public function exportToFile($filepath) {
        try {
            $configs = $this->getAll();
            $export = [
                'version' => '1.0',
                'exported_at' => date('Y-m-d H:i:s'),
                'configurations' => $configs
            ];

            file_put_contents($filepath, json_encode($export, JSON_PRETTY_PRINT));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Import configuration from JSON file
     */
    public function importFromFile($filepath, $userId = null) {
        try {
            $json = file_get_contents($filepath);
            $import = json_decode($json, true);

            if (!isset($import['configurations'])) {
                return false;
            }

            foreach ($import['configurations'] as $key => $config) {
                $this->set($key, $config['value'], [
                    'type' => $config['type'],
                    'category' => $config['category'],
                    'description' => $config['description'],
                    'module' => $config['module'],
                    'user_id' => $userId,
                    'reason' => 'Imported from file'
                ]);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
