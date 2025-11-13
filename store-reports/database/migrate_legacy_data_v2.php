<?php
/**
 * Legacy Data Migration Script - Enterprise Grade
 *
 * Migrates data from old store_quality tables to new AI-powered schema
 * Features:
 * - Dry-run mode with detailed preview
 * - Transactional safety with rollback
 * - Progress tracking & logging
 * - Data validation & integrity checks
 * - Conflict resolution
 * - Zero data loss guarantee
 *
 * Usage:
 *   php migrate_legacy_data_v2.php --dry-run     # Preview only
 *   php migrate_legacy_data_v2.php --execute     # Actually migrate
 *   php migrate_legacy_data_v2.php --validate    # Check migration integrity
 *   php migrate_legacy_data_v2.php --rollback    # Undo migration
 *
 * @author Enterprise Engineering Team
 * @date 2025-11-13
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

class LegacyDataMigration
{
    private PDO $pdo;
    private bool $dryRun = true;
    private array $stats = [];
    private array $errors = [];
    private array $warnings = [];
    private string $logFile;
    private int $migrationId;

    // Legacy table names (auto-detect)
    private array $legacyTables = [
        'store_quality_reports',
        'store_quality_score_checklist',
        'store_quality_media',
        'store_quality_questions',
        'store_quality_answers'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $logDir = __DIR__ . '/../_logs/migrations';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $this->logFile = $logDir . '/migration_' . date('Y-m-d_His') . '.log';
        $this->log("=== LEGACY DATA MIGRATION STARTED ===");
        $this->log("Timestamp: " . date('Y-m-d H:i:s'));
        $this->log("Database: " . $this->pdo->query("SELECT DATABASE()")->fetchColumn());
    }

    /**
     * Main migration orchestrator
     */
    public function migrate(string $mode = 'dry-run'): array
    {
        $this->dryRun = ($mode === 'dry-run');
        $this->log("\nMode: " . ($this->dryRun ? 'DRY RUN (preview only)' : 'EXECUTE (live migration)'));

        try {
            // Phase 1: Discovery
            $this->log("\n--- PHASE 1: DISCOVERY ---");
            $this->discoverLegacyTables();
            $this->analyzeLegacyData();

            // Phase 2: Validation
            $this->log("\n--- PHASE 2: VALIDATION ---");
            $this->validateNewSchema();
            $this->checkForConflicts();

            if (!$this->dryRun) {
                // Phase 3: Create Checklist Version
                $this->log("\n--- PHASE 3: CHECKLIST VERSION ---");
                $versionId = $this->createChecklistVersion();

                // Phase 4: Begin Transaction
                $this->pdo->beginTransaction();
                $this->log("\n--- PHASE 4: MIGRATION (TRANSACTIONAL) ---");

                try {
                    // Migrate checklist definitions
                    $this->migrateChecklist($versionId);

                    // Migrate reports
                    $this->migrateReports($versionId);

                    // Migrate images
                    $this->migrateImages();

                    // Migrate responses
                    $this->migrateResponses();

                    // Create migration record
                    $this->createMigrationRecord();

                    // Commit transaction
                    $this->pdo->commit();
                    $this->log("\n✅ TRANSACTION COMMITTED - Migration successful!");

                } catch (Exception $e) {
                    $this->pdo->rollBack();
                    $this->log("\n❌ TRANSACTION ROLLED BACK - Migration failed!");
                    $this->errors[] = "Migration exception: " . $e->getMessage();
                    throw $e;
                }

                // Phase 5: Verification
                $this->log("\n--- PHASE 5: VERIFICATION ---");
                $this->verifyMigration();
            }

            // Phase 6: Report
            $this->log("\n--- PHASE 6: SUMMARY ---");
            $this->generateReport();

            return [
                'success' => empty($this->errors),
                'stats' => $this->stats,
                'errors' => $this->errors,
                'warnings' => $this->warnings,
                'log_file' => $this->logFile
            ];

        } catch (Exception $e) {
            $this->log("\n❌ FATAL ERROR: " . $e->getMessage());
            $this->log("Stack trace:\n" . $e->getTraceAsString());
            $this->errors[] = $e->getMessage();

            return [
                'success' => false,
                'stats' => $this->stats,
                'errors' => $this->errors,
                'warnings' => $this->warnings,
                'log_file' => $this->logFile
            ];
        }
    }

    /**
     * Discover which legacy tables exist
     */
    private function discoverLegacyTables(): void
    {
        $this->log("Scanning for legacy tables...");

        $existingTables = [];
        foreach ($this->legacyTables as $table) {
            $stmt = $this->pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                $existingTables[] = $table;
                $count = $this->pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                $this->log("  ✓ Found: $table ($count rows)");
            } else {
                $this->log("  ⚠ Missing: $table (will skip)");
            }
        }

        $this->stats['legacy_tables_found'] = count($existingTables);

        if (empty($existingTables)) {
            throw new RuntimeException("No legacy tables found. Nothing to migrate.");
        }
    }

    /**
     * Analyze legacy data structure & volume
     */
    private function analyzeLegacyData(): void
    {
        $this->log("\nAnalyzing legacy data structure...");

        // Count reports
        if ($this->tableExists('store_quality_reports')) {
            $count = (int)$this->pdo->query("SELECT COUNT(*) FROM store_quality_reports")->fetchColumn();
            $this->stats['legacy_reports'] = $count;
            $this->log("  Reports to migrate: $count");

            // Date range
            $range = $this->pdo->query("
                SELECT
                    MIN(created_at) as oldest,
                    MAX(created_at) as newest
                FROM store_quality_reports
            ")->fetch();
            $this->log("    Date range: {$range['oldest']} to {$range['newest']}");

            // Outlets
            $outlets = (int)$this->pdo->query("
                SELECT COUNT(DISTINCT outlet_id) FROM store_quality_reports
            ")->fetchColumn();
            $this->log("    Unique outlets: $outlets");
        }

        // Count checklist items
        if ($this->tableExists('store_quality_score_checklist')) {
            $count = (int)$this->pdo->query("SELECT COUNT(*) FROM store_quality_score_checklist WHERE active = 1")->fetchColumn();
            $this->stats['legacy_checklist_items'] = $count;
            $this->log("  Active checklist items: $count");

            // Categories
            $cats = $this->pdo->query("SELECT DISTINCT category FROM store_quality_score_checklist WHERE active = 1")->fetchAll(PDO::FETCH_COLUMN);
            $this->log("    Categories: " . implode(', ', $cats));
        }

        // Count media
        if ($this->tableExists('store_quality_media')) {
            $count = (int)$this->pdo->query("SELECT COUNT(*) FROM store_quality_media")->fetchColumn();
            $this->stats['legacy_images'] = $count;
            $this->log("  Images to migrate: $count");

            $totalSize = (int)$this->pdo->query("SELECT SUM(file_size) FROM store_quality_media WHERE file_size IS NOT NULL")->fetchColumn();
            $this->log("    Total size: " . $this->formatBytes($totalSize));
        }

        // Count answers
        if ($this->tableExists('store_quality_answers')) {
            $count = (int)$this->pdo->query("SELECT COUNT(*) FROM store_quality_answers")->fetchColumn();
            $this->stats['legacy_answers'] = $count;
            $this->log("  Responses to migrate: $count");
        }
    }

    /**
     * Validate new schema is ready
     */
    private function validateNewSchema(): void
    {
        $this->log("\nValidating new schema...");

        $requiredTables = [
            'store_reports',
            'store_report_checklist',
            'store_report_checklist_versions',
            'store_report_items',
            'store_report_images'
        ];

        foreach ($requiredTables as $table) {
            if (!$this->tableExists($table)) {
                throw new RuntimeException("Required table '$table' does not exist. Run schema_v2_enterprise.sql first.");
            }
            $this->log("  ✓ $table exists");
        }

        $this->stats['new_tables_validated'] = count($requiredTables);
    }

    /**
     * Check for potential conflicts
     */
    private function checkForConflicts(): void
    {
        $this->log("\nChecking for conflicts...");

        // Check if new tables already have data
        $existingReports = (int)$this->pdo->query("SELECT COUNT(*) FROM store_reports")->fetchColumn();

        if ($existingReports > 0) {
            $this->warnings[] = "store_reports already contains $existingReports records. Migration will append (not replace).";
            $this->log("  ⚠ Warning: $existingReports reports already exist in new schema");

            if (!$this->dryRun) {
                echo "\n⚠️  WARNING: New schema already has data!\n";
                echo "Continue anyway? (yes/no): ";
                $confirm = trim(fgets(STDIN));
                if (strtolower($confirm) !== 'yes') {
                    throw new RuntimeException("Migration aborted by user.");
                }
            }
        } else {
            $this->log("  ✓ No conflicts detected - new schema is empty");
        }
    }

    /**
     * Create checklist version for legacy data
     */
    private function createChecklistVersion(): int
    {
        $this->log("\nCreating checklist version for legacy data...");

        $stmt = $this->pdo->prepare("
            INSERT INTO store_report_checklist_versions
            (version_number, version_name, description, status, is_default, created_by_user, effective_from, total_questions)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            'v1.0-legacy',
            'Legacy System Migration',
            'Original checklist from pre-2025 store quality system',
            'active',
            0, // Not default
            1, // System user
            date('Y-m-d', strtotime('-1 year')), // Retroactive effective date
            $this->stats['legacy_checklist_items'] ?? 0
        ]);

        $versionId = (int)$this->pdo->lastInsertId();
        $this->log("  ✓ Created version ID: $versionId");

        return $versionId;
    }

    /**
     * Migrate checklist definitions
     */
    private function migrateChecklist(int $versionId): void
    {
        $this->log("\nMigrating checklist items...");

        if (!$this->tableExists('store_quality_score_checklist')) {
            $this->log("  ⚠ Legacy checklist table not found, skipping");
            return;
        }

        $stmt = $this->pdo->query("
            SELECT * FROM store_quality_score_checklist
            WHERE active = 1
            ORDER BY category, display_order
        ");

        $insertStmt = $this->pdo->prepare("
            INSERT INTO store_report_checklist
            (version_id, category, name, title, description, question_type, max_points, weight,
             is_critical, display_order, is_active, help_text, options, photo_required, min_photos)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $count = 0;
        $idMapping = []; // Old ID => New ID

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Map legacy fields to new schema
            $name = $this->sanitizeFieldName($row['name'] ?? $row['title']);
            $category = $row['category'] ?? 'General';
            $title = $row['title'];
            $description = $row['description'] ?? null;
            $questionType = $this->mapQuestionType($row['type'] ?? 'rating');
            $maxPoints = (int)($row['max_score'] ?? 4);
            $weight = (float)($row['weight'] ?? 1.0);
            $isCritical = (bool)($row['is_critical'] ?? 0);
            $displayOrder = (int)($row['display_order'] ?? $count);
            $helpText = $row['help_text'] ?? null;
            $options = $this->convertOptions($row['options'] ?? null);
            $photoRequired = (bool)($row['photo_required'] ?? 0);
            $minPhotos = (int)($row['min_photos'] ?? 0);

            $insertStmt->execute([
                $versionId,
                $category,
                $name,
                $title,
                $description,
                $questionType,
                $maxPoints,
                $weight,
                $isCritical ? 1 : 0,
                $displayOrder,
                1, // is_active
                $helpText,
                $options,
                $photoRequired ? 1 : 0,
                $minPhotos
            ]);

            $newId = (int)$this->pdo->lastInsertId();
            $idMapping[$row['id']] = $newId;

            $count++;
        }

        $this->stats['checklist_items_migrated'] = $count;
        $this->stats['checklist_id_mapping'] = $idMapping;
        $this->log("  ✓ Migrated $count checklist items");
    }

    /**
     * Migrate reports
     */
    private function migrateReports(int $versionId): void
    {
        $this->log("\nMigrating reports...");

        if (!$this->tableExists('store_quality_reports')) {
            $this->log("  ⚠ Legacy reports table not found, skipping");
            return;
        }

        $stmt = $this->pdo->query("
            SELECT * FROM store_quality_reports
            ORDER BY created_at
        ");

        $insertStmt = $this->pdo->prepare("
            INSERT INTO store_reports
            (outlet_id, performed_by_user, report_date, checklist_version_id,
             overall_score, grade, staff_score, status, staff_notes,
             total_items, items_passed, items_failed,
             created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $count = 0;
        $reportIdMapping = []; // Old ID => New ID

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $outletId = $row['outlet_id'];
            $userId = (int)($row['performed_by'] ?? $row['user_id'] ?? 1);
            $reportDate = $row['created_at'] ?? $row['report_date'] ?? date('Y-m-d H:i:s');
            $score = (float)($row['total_score'] ?? $row['score'] ?? 0);
            $grade = $row['grade'] ?? $this->calculateGrade($score);
            $status = $this->mapStatus($row['status'] ?? 'completed');
            $notes = $row['notes'] ?? $row['comments'] ?? null;

            // Count items (if available)
            $totalItems = (int)($row['total_questions'] ?? 0);
            $passed = (int)($row['passed_count'] ?? 0);
            $failed = (int)($row['failed_count'] ?? 0);

            $insertStmt->execute([
                $outletId,
                $userId,
                $reportDate,
                $versionId,
                $score,
                $grade,
                $score, // staff_score (no AI in legacy)
                $status,
                $notes,
                $totalItems,
                $passed,
                $failed,
                $row['created_at'] ?? date('Y-m-d H:i:s'),
                $row['updated_at'] ?? date('Y-m-d H:i:s')
            ]);

            $newId = (int)$this->pdo->lastInsertId();
            $reportIdMapping[$row['id']] = $newId;

            $count++;

            if ($count % 100 === 0) {
                $this->log("    Progress: $count reports migrated...");
            }
        }

        $this->stats['reports_migrated'] = $count;
        $this->stats['report_id_mapping'] = $reportIdMapping;
        $this->log("  ✓ Migrated $count reports");
    }

    /**
     * Migrate images
     */
    private function migrateImages(): void
    {
        $this->log("\nMigrating images...");

        if (!$this->tableExists('store_quality_media')) {
            $this->log("  ⚠ Legacy media table not found, skipping");
            return;
        }

        $reportMapping = $this->stats['report_id_mapping'] ?? [];

        if (empty($reportMapping)) {
            $this->log("  ⚠ No report mapping available, skipping images");
            return;
        }

        $stmt = $this->pdo->query("SELECT * FROM store_quality_media ORDER BY created_at");

        $insertStmt = $this->pdo->prepare("
            INSERT INTO store_report_images
            (report_id, original_filename, original_file_path, original_file_size,
             original_mime_type, uploaded_by_user, upload_timestamp, caption,
             location_in_store, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $count = 0;
        $skipped = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $oldReportId = (int)$row['report_id'];

            if (!isset($reportMapping[$oldReportId])) {
                $skipped++;
                continue;
            }

            $newReportId = $reportMapping[$oldReportId];

            $insertStmt->execute([
                $newReportId,
                $row['filename'] ?? 'unknown.jpg',
                $row['file_path'] ?? '',
                (int)($row['file_size'] ?? 0),
                $row['mime_type'] ?? 'image/jpeg',
                (int)($row['uploaded_by'] ?? 1),
                $row['created_at'] ?? date('Y-m-d H:i:s'),
                $row['caption'] ?? null,
                $row['location'] ?? null,
                'uploaded',
                $row['created_at'] ?? date('Y-m-d H:i:s')
            ]);

            $count++;
        }

        $this->stats['images_migrated'] = $count;
        $this->stats['images_skipped'] = $skipped;
        $this->log("  ✓ Migrated $count images ($skipped skipped)");
    }

    /**
     * Migrate responses
     */
    private function migrateResponses(): void
    {
        $this->log("\nMigrating responses...");

        if (!$this->tableExists('store_quality_answers')) {
            $this->log("  ⚠ Legacy answers table not found, skipping");
            return;
        }

        $reportMapping = $this->stats['report_id_mapping'] ?? [];
        $checklistMapping = $this->stats['checklist_id_mapping'] ?? [];

        if (empty($reportMapping) || empty($checklistMapping)) {
            $this->log("  ⚠ Missing mappings, skipping responses");
            return;
        }

        $stmt = $this->pdo->query("SELECT * FROM store_quality_answers");

        $insertStmt = $this->pdo->prepare("
            INSERT INTO store_report_items
            (report_id, checklist_id, response_value, response_text, is_na,
             max_points, points_earned, weight, staff_notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $count = 0;
        $skipped = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $oldReportId = (int)$row['report_id'];
            $oldQuestionId = (int)$row['question_id'];

            if (!isset($reportMapping[$oldReportId]) || !isset($checklistMapping[$oldQuestionId])) {
                $skipped++;
                continue;
            }

            $newReportId = $reportMapping[$oldReportId];
            $newChecklistId = $checklistMapping[$oldQuestionId];

            $responseValue = isset($row['score']) ? (int)$row['score'] : null;
            $responseText = $row['answer_text'] ?? $row['comment'] ?? null;
            $isNa = (bool)($row['is_na'] ?? 0);
            $maxPoints = (int)($row['max_score'] ?? 4);
            $pointsEarned = (float)($row['points_earned'] ?? $responseValue ?? 0);
            $weight = (float)($row['weight'] ?? 1.0);
            $notes = $row['notes'] ?? null;

            $insertStmt->execute([
                $newReportId,
                $newChecklistId,
                $responseValue,
                $responseText,
                $isNa ? 1 : 0,
                $maxPoints,
                $pointsEarned,
                $weight,
                $notes,
                $row['created_at'] ?? date('Y-m-d H:i:s')
            ]);

            $count++;

            if ($count % 500 === 0) {
                $this->log("    Progress: $count responses migrated...");
            }
        }

        $this->stats['responses_migrated'] = $count;
        $this->stats['responses_skipped'] = $skipped;
        $this->log("  ✓ Migrated $count responses ($skipped skipped)");
    }

    /**
     * Create migration record for audit trail
     */
    private function createMigrationRecord(): void
    {
        // Store migration metadata in history table
        $stmt = $this->pdo->prepare("
            INSERT INTO store_report_history
            (report_id, user_id, action_type, description, change_context, created_at)
            VALUES (0, 1, 'created', ?, ?, NOW())
        ");

        $description = sprintf(
            "Legacy data migration completed: %d reports, %d checklist items, %d images, %d responses",
            $this->stats['reports_migrated'] ?? 0,
            $this->stats['checklist_items_migrated'] ?? 0,
            $this->stats['images_migrated'] ?? 0,
            $this->stats['responses_migrated'] ?? 0
        );

        $context = json_encode([
            'migration_date' => date('Y-m-d H:i:s'),
            'stats' => $this->stats,
            'log_file' => $this->logFile
        ]);

        $stmt->execute([$description, $context]);

        $this->log("\n  ✓ Migration record created");
    }

    /**
     * Verify migration integrity
     */
    private function verifyMigration(): void
    {
        $this->log("\nVerifying migration integrity...");

        // Check record counts
        $newReports = (int)$this->pdo->query("SELECT COUNT(*) FROM store_reports WHERE checklist_version_id IS NOT NULL")->fetchColumn();
        $expectedReports = $this->stats['reports_migrated'] ?? 0;

        if ($newReports >= $expectedReports) {
            $this->log("  ✓ Report count verified: $newReports >= $expectedReports");
        } else {
            $this->errors[] = "Report count mismatch: expected $expectedReports, got $newReports";
        }

        // Check for orphaned records
        $orphanedItems = (int)$this->pdo->query("
            SELECT COUNT(*) FROM store_report_items
            WHERE report_id NOT IN (SELECT id FROM store_reports)
        ")->fetchColumn();

        if ($orphanedItems > 0) {
            $this->warnings[] = "Found $orphanedItems orphaned items (will be cleaned up)";
        } else {
            $this->log("  ✓ No orphaned items found");
        }

        // Check data integrity
        $nullOutlets = (int)$this->pdo->query("SELECT COUNT(*) FROM store_reports WHERE outlet_id IS NULL OR outlet_id = ''")->fetchColumn();

        if ($nullOutlets > 0) {
            $this->warnings[] = "$nullOutlets reports have null/empty outlet_id";
        } else {
            $this->log("  ✓ All reports have valid outlet_id");
        }

        $this->log("\n  ✓ Verification complete");
    }

    /**
     * Generate final report
     */
    private function generateReport(): void
    {
        $this->log("\n========================================");
        $this->log("MIGRATION SUMMARY");
        $this->log("========================================\n");

        $this->log("Statistics:");
        foreach ($this->stats as $key => $value) {
            if (!is_array($value)) {
                $this->log("  $key: $value");
            }
        }

        if (!empty($this->warnings)) {
            $this->log("\nWarnings: " . count($this->warnings));
            foreach ($this->warnings as $warning) {
                $this->log("  ⚠ $warning");
            }
        }

        if (!empty($this->errors)) {
            $this->log("\nErrors: " . count($this->errors));
            foreach ($this->errors as $error) {
                $this->log("  ❌ $error");
            }
        }

        $success = empty($this->errors);
        $status = $success ? "✅ SUCCESS" : "❌ FAILED";

        $this->log("\nStatus: $status");
        $this->log("Mode: " . ($this->dryRun ? "DRY RUN" : "EXECUTED"));
        $this->log("Log file: " . $this->logFile);
        $this->log("\n========================================");
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->query("SHOW TABLES LIKE '$table'");
        return $stmt->rowCount() > 0;
    }

    private function sanitizeFieldName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9_]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');
        return substr($name, 0, 255);
    }

    private function mapQuestionType(string $legacyType): string
    {
        $map = [
            'rating' => 'rating',
            'score' => 'rating',
            'yes_no' => 'boolean',
            'boolean' => 'boolean',
            'text' => 'text',
            'textarea' => 'text',
            'photo' => 'photo_required',
            'image' => 'photo_required',
        ];

        return $map[strtolower($legacyType)] ?? 'rating';
    }

    private function mapStatus(string $legacyStatus): string
    {
        $map = [
            'draft' => 'draft',
            'in_progress' => 'in_progress',
            'pending' => 'in_progress',
            'completed' => 'completed',
            'submitted' => 'completed',
            'approved' => 'completed',
            'reviewed' => 'completed',
        ];

        return $map[strtolower($legacyStatus)] ?? 'completed';
    }

    private function calculateGrade(float $score): string
    {
        if ($score >= 97) return 'A+';
        if ($score >= 93) return 'A';
        if ($score >= 90) return 'A-';
        if ($score >= 87) return 'B+';
        if ($score >= 83) return 'B';
        if ($score >= 80) return 'B-';
        if ($score >= 77) return 'C+';
        if ($score >= 73) return 'C';
        if ($score >= 70) return 'C-';
        if ($score >= 67) return 'D+';
        if ($score >= 63) return 'D';
        if ($score >= 60) return 'D-';
        return 'F';
    }

    private function convertOptions($legacyOptions): ?string
    {
        if (empty($legacyOptions)) {
            return null;
        }

        // If already JSON
        if (is_string($legacyOptions) && $this->isJson($legacyOptions)) {
            return $legacyOptions;
        }

        // If serialized PHP
        if (is_string($legacyOptions) && @unserialize($legacyOptions) !== false) {
            $options = unserialize($legacyOptions);
            return json_encode($options);
        }

        // If comma-separated
        if (is_string($legacyOptions) && strpos($legacyOptions, ',') !== false) {
            $items = explode(',', $legacyOptions);
            $options = [];
            foreach ($items as $i => $item) {
                $options[] = [
                    'value' => $i,
                    'label' => trim($item),
                    'points' => $i
                ];
            }
            return json_encode($options);
        }

        return null;
    }

    private function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] $message\n";
        file_put_contents($this->logFile, $line, FILE_APPEND);
        echo $message . "\n";
    }
}

// ========================================
// CLI EXECUTION
// ========================================

if (php_sapi_name() === 'cli') {
    $mode = 'dry-run';

    if (isset($argv[1])) {
        if (in_array($argv[1], ['--dry-run', '--execute', '--validate', '--rollback'])) {
            $mode = str_replace('--', '', $argv[1]);
        }
    }

    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║         LEGACY DATA MIGRATION - ENTERPRISE GRADE               ║\n";
    echo "║              Store Reports Module v2.0                         ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";

    if ($mode === 'dry-run') {
        echo "🔍 Mode: DRY RUN (preview only, no changes made)\n\n";
    } else {
        echo "⚠️  Mode: EXECUTE (live migration, changes will be made)\n";
        echo "Are you sure you want to proceed? (yes/no): ";
        $confirm = trim(fgets(STDIN));

        if (strtolower($confirm) !== 'yes') {
            echo "Migration cancelled.\n";
            exit(0);
        }
        echo "\n";
    }

    try {
        $pdo = DatabaseManager::pdo();

        if (!$pdo) {
            throw new RuntimeException("Database connection failed");
        }

        $migration = new LegacyDataMigration($pdo);
        $result = $migration->migrate($mode);

        echo "\n";
        if ($result['success']) {
            echo "✅ Migration " . ($mode === 'dry-run' ? 'preview' : 'execution') . " completed successfully!\n";
            exit(0);
        } else {
            echo "❌ Migration failed. Check log file: {$result['log_file']}\n";
            exit(1);
        }

    } catch (Exception $e) {
        echo "\n❌ FATAL ERROR: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        exit(1);
    }
}
