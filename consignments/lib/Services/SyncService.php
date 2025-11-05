<?php
/**
 * SyncService - Lightspeed sync state management
 *
 * Handles Lightspeed integration sync operations:
 * - Sync state management (enabled/disabled)
 * - Sync status monitoring
 * - Sync history and logs
 *
 * Extracted from TransferManagerAPI to follow proper MVC pattern.
 *
 * @package CIS\Consignments\Services
 * @version 1.0.0
 * @author CIS Development Team
 * @created 2025-11-05
 */

declare(strict_types=1);

namespace CIS\Consignments\Services;

use RuntimeException;

class SyncService
{
    /**
     * Sync state file path
     * @var string
     */
    private string $syncFile;

    /**
     * Lightspeed API token
     * @var string
     */
    private string $lsToken;

    /**
     * Constructor
     *
     * @param string|null $syncFilePath Custom sync file path (for testing)
     */
    public function __construct(?string $syncFilePath = null)
    {
        // Set sync file path
        $this->syncFile = $syncFilePath ?? __DIR__ . '/../../.sync_enabled';

        // Get Lightspeed token from environment
        $this->lsToken = $_ENV['LS_API_TOKEN'] ?? '';
    }

    /**
     * Factory method
     *
     * @return self
     */
    public static function make(): self
    {
        return new self();
    }

    // ========================================================================
    // SYNC STATE MANAGEMENT
    // ========================================================================

    /**
     * Check if sync is enabled
     *
     * @return bool True if sync is enabled
     */
    public function isEnabled(): bool
    {
        if (!file_exists($this->syncFile)) {
            return false;
        }

        $content = @file_get_contents($this->syncFile);

        return $content === '1';
    }

    /**
     * Enable sync
     *
     * @return bool Success
     * @throws RuntimeException If unable to write sync state
     */
    public function enable(): bool
    {
        $result = @file_put_contents($this->syncFile, '1');

        if ($result === false) {
            throw new RuntimeException(
                'Failed to enable sync: Unable to write to ' . $this->syncFile
            );
        }

        return true;
    }

    /**
     * Disable sync
     *
     * @return bool Success
     * @throws RuntimeException If unable to write sync state
     */
    public function disable(): bool
    {
        $result = @file_put_contents($this->syncFile, '0');

        if ($result === false) {
            throw new RuntimeException(
                'Failed to disable sync: Unable to write to ' . $this->syncFile
            );
        }

        return true;
    }

    /**
     * Toggle sync state
     *
     * @param bool $enabled New state
     * @return bool Success
     */
    public function toggle(bool $enabled): bool
    {
        return $enabled ? $this->enable() : $this->disable();
    }

    // ========================================================================
    // SYNC STATUS & VERIFICATION
    // ========================================================================

    /**
     * Get comprehensive sync status
     *
     * @return array {
     *     @type bool $enabled Whether sync is enabled
     *     @type bool $token_set Whether LS API token is configured
     *     @type bool $file_exists Whether sync file exists
     *     @type bool $file_writable Whether sync file directory is writable
     *     @type string|null $file_path Path to sync file
     *     @type string|null $last_modified Last modification time
     * }
     */
    public function getStatus(): array
    {
        $fileExists = file_exists($this->syncFile);
        $dirWritable = is_writable(dirname($this->syncFile));

        $lastModified = null;
        if ($fileExists) {
            $mtime = @filemtime($this->syncFile);
            if ($mtime !== false) {
                $lastModified = date('Y-m-d H:i:s', $mtime);
            }
        }

        return [
            'enabled' => $this->isEnabled(),
            'token_set' => !empty($this->lsToken),
            'file_exists' => $fileExists,
            'file_writable' => $dirWritable,
            'file_path' => $this->syncFile,
            'last_modified' => $lastModified
        ];
    }

    /**
     * Verify sync is operational
     *
     * @return array {
     *     @type bool $operational Whether sync is fully operational
     *     @type array $checks Individual check results
     *     @type array $issues List of issues found
     * }
     */
    public function verify(): array
    {
        $checks = [
            'sync_enabled' => $this->isEnabled(),
            'token_configured' => !empty($this->lsToken),
            'file_exists' => file_exists($this->syncFile),
            'file_writable' => is_writable(dirname($this->syncFile))
        ];

        $issues = [];

        if (!$checks['sync_enabled']) {
            $issues[] = 'Sync is currently disabled';
        }

        if (!$checks['token_configured']) {
            $issues[] = 'Lightspeed API token is not configured';
        }

        if (!$checks['file_exists']) {
            $issues[] = 'Sync state file does not exist';
        }

        if (!$checks['file_writable']) {
            $issues[] = 'Sync state directory is not writable';
        }

        $operational = empty($issues);

        return [
            'operational' => $operational,
            'checks' => $checks,
            'issues' => $issues
        ];
    }

    // ========================================================================
    // UTILITY METHODS
    // ========================================================================

    /**
     * Get sync file path
     *
     * @return string Sync file path
     */
    public function getSyncFilePath(): string
    {
        return $this->syncFile;
    }

    /**
     * Check if API token is configured
     *
     * @return bool True if token is set
     */
    public function hasToken(): bool
    {
        return !empty($this->lsToken);
    }

    /**
     * Get masked API token (for display purposes)
     *
     * @return string|null Masked token or null if not set
     */
    public function getMaskedToken(): ?string
    {
        if (empty($this->lsToken)) {
            return null;
        }

        $length = strlen($this->lsToken);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        $start = substr($this->lsToken, 0, 4);
        $end = substr($this->lsToken, -4);

        return $start . str_repeat('*', $length - 8) . $end;
    }

    /**
     * Create sync file if it doesn't exist
     *
     * @param bool $defaultState Default state (enabled/disabled)
     * @return bool Success
     */
    public function initialize(bool $defaultState = false): bool
    {
        if (file_exists($this->syncFile)) {
            return true; // Already exists
        }

        // Ensure directory exists
        $dir = dirname($this->syncFile);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                return false;
            }
        }

        // Create file with default state
        return $this->toggle($defaultState);
    }

    /**
     * Delete sync file (reset sync state)
     *
     * @return bool Success
     */
    public function reset(): bool
    {
        if (!file_exists($this->syncFile)) {
            return true; // Already doesn't exist
        }

        return @unlink($this->syncFile);
    }
}
