<?php
declare(strict_types=1);

namespace CIS\HumanResources\Payroll\Services;

use Throwable;

/**
 * VendService
 *
 * Handles all Vend-related operations including:
 * - Vend account payment snapshot management
 * - Register ID resolution
 * - Payment type resolution
 *
 * Extracted from payroll-process.php (Lines 66-213)
 *
 * @package CIS\HumanResources\Payroll\Services
 * @version 2.0.0
 * @author CIS Development Team
 */
class VendService
{
    /**
     * Default register name for Vend payments
     */
    private const DEFAULT_REGISTER_NAME = 'Hamilton East';

    /**
     * Default payment type name for account payments
     */
    private const DEFAULT_PAYMENT_TYPE_NAME = 'Internet Banking';

    /**
     * Fallback register ID if resolution fails
     */
    private const FALLBACK_REGISTER_ID = 'efdf9bc5-20b8-11e4-8c21-b8ca3a64f8f4';

    /**
     * Fallback payment type ID if resolution fails
     */
    private const FALLBACK_PAYMENT_TYPE_ID = '5';

    /**
     * @var array Cached snapshot directories
     */
    private array $snapshotDirs = [];

    /**
     * Get list of directories where Vend snapshots might be stored
     *
     * Searches multiple locations:
     * 1. Custom env PAYROLL_SNAPSHOT_DIRS (JSON array)
     * 2. private_html/modules/payroll_snapshot/snapshots/vend_account_payments/
     * 3. modules/payroll_snapshot/snapshots/vend_account_payments/
     *
     * @return array List of absolute directory paths
     */
    public function getSnapshotDirectories(): array
    {
        if (!empty($this->snapshotDirs)) {
            return $this->snapshotDirs;
        }

        $dirs = [];

        // 1. Check environment variable for custom paths
        $envDirs = getenv('PAYROLL_SNAPSHOT_DIRS');
        if ($envDirs) {
            $decoded = json_decode((string)$envDirs, true);
            if (is_array($decoded)) {
                foreach ($decoded as $d) {
                    if (is_dir((string)$d)) {
                        $dirs[] = rtrim((string)$d, '/');
                    }
                }
            }
        }

        // 2. Check private_html location
        $privateBase = defined('BASE_PATH')
            ? BASE_PATH . '../private_html/modules/payroll_snapshot/snapshots/vend_account_payments/'
            : $_SERVER['DOCUMENT_ROOT'] . '/../private_html/modules/payroll_snapshot/snapshots/vend_account_payments/';

        if (is_dir($privateBase)) {
            $dirs[] = rtrim(realpath($privateBase), '/');
        }

        // 3. Check public_html location (fallback)
        $publicBase = defined('BASE_PATH')
            ? BASE_PATH . 'modules/payroll_snapshot/snapshots/vend_account_payments/'
            : $_SERVER['DOCUMENT_ROOT'] . '/modules/payroll_snapshot/snapshots/vend_account_payments/';

        if (is_dir($publicBase)) {
            $dirs[] = rtrim(realpath($publicBase), '/');
        }

        // Remove duplicates
        $this->snapshotDirs = array_values(array_unique($dirs));

        return $this->snapshotDirs;
    }

    /**
     * Scan all snapshot directories for saved Vend account payment snapshots
     *
     * Returns array with:
     * - snapshots: Array of snapshot metadata (run_id, started, ended, users_count, path)
     * - dirs_scanned: Array of directories that were scanned
     * - skips: Array of skipped directories (not found)
     * - errors: Array of error messages
     *
     * @return array Scan results
     */
    public function scanSnapshots(): array
    {
        $dirs = $this->getSnapshotDirectories();
        $items = [];
        $skips = [];
        $errors = [];
        $dirsScanned = [];

        foreach ($dirs as $d) {
            if (!is_dir($d)) {
                $skips[] = $d;
                continue;
            }

            $dirsScanned[] = $d;

            try {
                $files = glob($d . '/summary_*.json');
                if ($files === false) {
                    $errors[] = 'glob() failed for ' . $d;
                    continue;
                }

                foreach ($files as $f) {
                    if (!is_file($f)) continue;

                    $txt = @file_get_contents($f);
                    if ($txt === false) {
                        $errors[] = 'Could not read ' . basename($f);
                        continue;
                    }

                    $j = json_decode($txt, true);
                    if (!is_array($j)) {
                        $errors[] = 'Invalid JSON in ' . basename($f);
                        continue;
                    }

                    $items[] = [
                        'run_id'      => (string)($j['run_id'] ?? ''),
                        'started'     => (string)($j['started'] ?? ''),
                        'ended'       => (string)($j['ended'] ?? ''),
                        'users_count' => (int)count($j['users'] ?? []),
                        'path'        => $f,
                    ];
                }
            } catch (Throwable $e) {
                $errors[] = 'Scan error in ' . $d . ': ' . $e->getMessage();
            }
        }

        // Sort by ended DESC (most recent first), then by started DESC
        usort($items, static fn($a, $b) => strcmp(
            $b['ended'] ?: $b['started'],
            $a['ended'] ?: $a['started']
        ));

        return [
            'snapshots'    => $items,
            'dirs_scanned' => $dirsScanned,
            'skips'        => $skips,
            'errors'       => $errors,
        ];
    }

    /**
     * Load a specific snapshot by run_id, or the latest if no ID provided
     *
     * @param string|null $runId The run ID to load, or null for latest
     * @return array|null Snapshot data with '_summary_path' key, or null if not found
     */
    public function loadSnapshotByRun(?string $runId = null): ?array
    {
        $scan = $this->scanSnapshots();
        $rows = $scan['snapshots'];

        if (empty($rows)) {
            return null;
        }

        // If run ID specified, find exact match
        if ($runId) {
            foreach ($rows as $r) {
                if (strcasecmp($r['run_id'], $runId) === 0) {
                    return $this->loadSnapshotFile($r['path']);
                }
            }
            return null; // Not found
        }

        // No run ID specified: load latest (first in sorted array)
        $r = $rows[0];
        return $this->loadSnapshotFile($r['path']);
    }

    /**
     * Load and parse a snapshot file
     *
     * @param string $path Absolute path to snapshot file
     * @return array|null Parsed snapshot data with '_summary_path' key, or null on error
     */
    private function loadSnapshotFile(string $path): ?array
    {
        $txt = @file_get_contents($path);
        if ($txt === false) {
            return null;
        }

        $j = json_decode($txt, true);
        if (!is_array($j)) {
            return null;
        }

        // Add metadata about where this came from
        $j['_summary_path'] = $path;

        return $j;
    }

    /**
     * Resolve register name to Vend register UUID
     *
     * Resolution order:
     * 1. Environment variable VEND_REGISTER_ID_MAP (JSON: {"name": "uuid"})
     * 2. Live Vend API call (via getVendRegisters() if available)
     * 3. Defined constant VEND_REGISTER_ID
     * 4. Fallback constant
     *
     * @param string $preferredName Register name to resolve (case-insensitive)
     * @return string|null Register UUID or null if not found
     */
    public function resolveRegisterId(string $preferredName = self::DEFAULT_REGISTER_NAME): ?string
    {
        $name = strtolower(trim($preferredName));

        // 1. Check environment map
        $map = json_decode((string)(getenv('VEND_REGISTER_ID_MAP') ?: '{}'), true) ?: [];
        $map = array_change_key_case($map, CASE_LOWER);

        if (!empty($map[$name])) {
            return (string)$map[$name];
        }

        // 2. Try live API lookup
        if (function_exists('getVendRegisters')) {
            try {
                $regs = getVendRegisters(); // Expect list with ->name / ->id
                foreach ((array)$regs as $r) {
                    $rname = strtolower(trim((string)($r->name ?? $r['name'] ?? '')));
                    $rid   = (string)($r->id ?? $r['id'] ?? '');

                    if ($rid && $rname === $name) {
                        return $rid;
                    }
                }
            } catch (Throwable $e) {
                // Fall through to constants
                error_log('VendService: Failed to fetch registers from API: ' . $e->getMessage());
            }
        }

        // 3. Check defined constant
        if (defined('VEND_REGISTER_ID')) {
            return VEND_REGISTER_ID;
        }

        // 4. Fallback
        return self::FALLBACK_REGISTER_ID;
    }

    /**
     * Resolve payment type name to Vend payment type ID
     *
     * Resolution order:
     * 1. Environment variable VEND_PAYMENT_TYPE_ID_MAP (JSON: {"name": "id"})
     * 2. Live Vend API call (via getVendPaymentTypes() if available)
     * 3. Defined constant VEND_PAYMENT_TYPE_ID
     * 4. Fallback constant
     *
     * @param string $preferredName Payment type name to resolve (case-insensitive)
     * @return string|null Payment type ID or null if not found
     */
    public function resolvePaymentType(string $preferredName = self::DEFAULT_PAYMENT_TYPE_NAME): ?string
    {
        $name = strtolower(trim($preferredName));

        // 1. Check environment map
        $map = json_decode((string)(getenv('VEND_PAYMENT_TYPE_ID_MAP') ?: '{}'), true) ?: [];
        $map = array_change_key_case($map, CASE_LOWER);

        if (!empty($map[$name])) {
            return (string)$map[$name];
        }

        // 2. Try live API lookup
        if (function_exists('getVendPaymentTypes')) {
            try {
                $types = getVendPaymentTypes(); // Expect list with ->name / ->id
                foreach ((array)$types as $t) {
                    $tname = strtolower(trim((string)($t->name ?? $t['name'] ?? '')));
                    $tid   = (string)($t->id ?? $t['id'] ?? '');

                    if ($tid && $tname === $name) {
                        return $tid;
                    }
                }
            } catch (Throwable $e) {
                // Fall through to constants
                error_log('VendService: Failed to fetch payment types from API: ' . $e->getMessage());
            }
        }

        // 3. Check defined constant
        if (defined('VEND_PAYMENT_TYPE_ID')) {
            return VEND_PAYMENT_TYPE_ID;
        }

        // 4. Fallback
        return self::FALLBACK_PAYMENT_TYPE_ID;
    }

    /**
     * Get default register name
     *
     * @return string
     */
    public function getDefaultRegisterName(): string
    {
        return self::DEFAULT_REGISTER_NAME;
    }

    /**
     * Get default payment type name
     *
     * @return string
     */
    public function getDefaultPaymentTypeName(): string
    {
        return self::DEFAULT_PAYMENT_TYPE_NAME;
    }
}
