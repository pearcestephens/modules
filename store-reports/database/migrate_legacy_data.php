<?php
/**
 * Store Reports Module - Legacy Data Migration
 *
 * Migrates data from old store_quality* tables to new AI-enhanced store_reports tables
 *
 * Usage:
 *   php migrate_legacy_data.php --dry-run      # Preview migration
 *   php migrate_legacy_data.php --execute      # Run migration
 *   php migrate_legacy_data.php --re-analyze   # AI re-analyze migrated images
 */

require_once __DIR__ . '/../../../app.php';
require_once __DIR__ . '/../services/AIVisionService.php';

class StoreReportsDataMigration {

    private $db;
    private $dryRun = true;
    private $reAnalyze = false;
    private $stats = [
        'reports_migrated' => 0,
        'items_migrated' => 0,
        'checklist_migrated' => 0,
        'images_migrated' => 0,
        'images_analyzed' => 0,
        'errors' => []
    ];

    public function __construct(bool $dryRun = true, bool $reAnalyze = false) {
        global $con;
        $this->db = $con;
        $this->dryRun = $dryRun;
        $this->reAnalyze = $reAnalyze;
    }

    /**
     * Run complete migration
     */
    public function migrate(): array {
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║   Store Reports Module - Legacy Data Migration              ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n\n";

        if ($this->dryRun) {
            echo "🔍 DRY RUN MODE - No data will be modified\n\n";
        } else {
            echo "⚠️  LIVE MIGRATION - Data will be modified!\n\n";
        }

        try {
            $this->db->begin_transaction();

            // Step 1: Migrate checklist (questions)
            echo "📋 Step 1: Migrating checklist definitions...\n";
            $this->migrateChecklist();

            // Step 2: Migrate reports
            echo "\n📊 Step 2: Migrating store reports...\n";
            $this->migrateReports();

            // Step 3: Migrate report items (responses)
            echo "\n✅ Step 3: Migrating report items...\n";
            $this->migrateReportItems();

            // Step 4: Migrate images
            echo "\n📸 Step 4: Migrating images...\n";
            $this->migrateImages();

            // Step 5: Optional AI re-analysis
            if ($this->reAnalyze && !$this->dryRun) {
                echo "\n🤖 Step 5: AI re-analyzing migrated images...\n";
                $this->reAnalyzeImages();
            }

            if ($this->dryRun) {
                $this->db->rollback();
                echo "\n✅ Dry run completed - rolled back transaction\n";
            } else {
                $this->db->commit();
                echo "\n✅ Migration completed successfully!\n";
            }

        } catch (Exception $e) {
            $this->db->rollback();
            $this->stats['errors'][] = $e->getMessage();
            echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
        }

        $this->printSummary();

        return $this->stats;
    }

    /**
     * Migrate checklist definitions
     */
    private function migrateChecklist(): void {
        $sql = "SELECT * FROM store_quality_score_checklist WHERE status = 1";
        $result = $this->db->query($sql);

        $count = 0;

        while ($row = $result->fetch_assoc()) {
            $category = $this->detectCategory($row['name'], $row['desc']);

            $insertSql = "INSERT INTO store_report_checklist
                (id, category, name, title, description, question_type, input_type,
                 max_points, weight, counts_toward_grade, display_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                category = VALUES(category),
                title = VALUES(title),
                description = VALUES(description)";

            $questionType = $this->mapQuestionType($row['type']);
            $inputType = $row['input_type'] ?? 'select';
            $maxPoints = ($row['score_points'] == 1) ? 4 : 0;
            $countsTowardGrade = ($row['score_points'] == 1) ? 1 : 0;

            if (!$this->dryRun) {
                $stmt = $this->db->prepare($insertSql);
                $stmt->bind_param(
                    'issssssiidii',
                    $row['id'],
                    $category,
                    $row['name'],
                    $row['desc'],
                    $row['desc'], // description same as title for now
                    $questionType,
                    $inputType,
                    $maxPoints,
                    1.0, // default weight
                    $countsTowardGrade,
                    0, // display order
                    1 // is_active
                );
                $stmt->execute();
            }

            $count++;
            echo "  ✓ Migrated checklist item: {$row['name']}\n";
        }

        $this->stats['checklist_migrated'] = $count;
        echo "  Migrated {$count} checklist items\n";
    }

    /**
     * Migrate store reports
     */
    private function migrateReports(): void {
        $sql = "SELECT * FROM store_quality ORDER BY date_performed DESC";
        $result = $this->db->query($sql);

        $count = 0;

        while ($row = $result->fetch_assoc()) {
            $grade = $this->calculateGrade($row['percentage']);

            $insertSql = "INSERT INTO store_reports
                (id, outlet_id, performed_by_user, report_date, overall_score, grade,
                 staff_notes, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?)";

            if (!$this->dryRun) {
                $stmt = $this->db->prepare($insertSql);
                $stmt->bind_param(
                    'isisdsss',
                    $row['id'],
                    $row['outlet_id'],
                    $row['performed_by_user'],
                    $row['date_performed'] ?? $row['date_created'],
                    $row['percentage'],
                    $grade,
                    $row['other_notes'],
                    $row['date_created'],
                    $row['date_created']
                );
                $stmt->execute();
            }

            $count++;
            if ($count % 10 == 0) {
                echo "  Migrated {$count} reports...\n";
            }
        }

        $this->stats['reports_migrated'] = $count;
        echo "  Total reports migrated: {$count}\n";
    }

    /**
     * Migrate report items (responses)
     */
    private function migrateReportItems(): void {
        $sql = "SELECT sqc.*, sqcl.score_points, sqcl.type as question_type
                FROM store_quality_scores sqc
                JOIN store_quality_score_checklist sqcl ON sqc.score_id = sqcl.id
                ORDER BY sqc.store_quality_id";

        $result = $this->db->query($sql);

        $count = 0;

        while ($row = $result->fetch_assoc()) {
            $maxPoints = ($row['score_points'] == 1) ? 4 : 0;
            $pointsEarned = $this->calculatePointsEarned($row['rating'], $maxPoints);

            $insertSql = "INSERT INTO store_report_items
                (report_id, checklist_id, response_value, response_text, is_na,
                 max_points, points_earned, staff_notes)
                VALUES (?, ?, ?, NULL, ?, ?, ?, ?)";

            if (!$this->dryRun) {
                $stmt = $this->db->prepare($insertSql);
                $stmt->bind_param(
                    'iiiidds',
                    $row['store_quality_id'],
                    $row['score_id'],
                    $row['rating'],
                    $row['not_applicable'],
                    $maxPoints,
                    $pointsEarned,
                    $row['notes']
                );
                $stmt->execute();
            }

            $count++;
        }

        $this->stats['items_migrated'] = $count;
        echo "  Total items migrated: {$count}\n";
    }

    /**
     * Migrate images
     */
    private function migrateImages(): void {
        $sql = "SELECT sqi.*, sq.outlet_id, sq.performed_by_user
                FROM store_quality_images sqi
                JOIN store_quality sq ON sqi.store_quality_id = sq.id
                ORDER BY sqi.store_quality_id, sqi.id";

        $result = $this->db->query($sql);

        $count = 0;
        $oldPath = '/home/129337.cloudwaysapps.com/jcepnzzkmj/public_html/assets/img/store-quality-images/';
        $newPath = '/home/master/applications/jcepnzzkmj/public_html/modules/store-reports/uploads/';

        // Ensure upload directory exists
        if (!$this->dryRun && !is_dir($newPath)) {
            mkdir($newPath, 0755, true);
        }

        while ($row = $result->fetch_assoc()) {
            $oldFile = $oldPath . $row['filename'];
            $newFile = $newPath . $row['filename'];

            // Get file info if exists
            $fileSize = file_exists($oldFile) ? filesize($oldFile) : null;
            $mimeType = file_exists($oldFile) ? mime_content_type($oldFile) : null;

            $insertSql = "INSERT INTO store_report_images
                (report_id, filename, file_path, file_size, mime_type,
                 uploaded_by_user, upload_timestamp, status, ai_analyzed)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), 'uploaded', FALSE)";

            if (!$this->dryRun) {
                // Copy file to new location
                if (file_exists($oldFile)) {
                    copy($oldFile, $newFile);
                }

                $stmt = $this->db->prepare($insertSql);
                $stmt->bind_param(
                    'issisi',
                    $row['store_quality_id'],
                    $row['filename'],
                    $newFile,
                    $fileSize,
                    $mimeType,
                    $row['performed_by_user']
                );
                $stmt->execute();
            }

            $count++;
            if ($count % 50 == 0) {
                echo "  Migrated {$count} images...\n";
            }
        }

        $this->stats['images_migrated'] = $count;
        echo "  Total images migrated: {$count}\n";
    }

    /**
     * Re-analyze migrated images with AI
     */
    private function reAnalyzeImages(): void {
        echo "  Starting AI re-analysis of {$this->stats['images_migrated']} images...\n";
        echo "  This may take a while...\n\n";

        $sql = "SELECT id, report_id FROM store_report_images WHERE ai_analyzed = FALSE LIMIT 100";
        $result = $this->db->query($sql);

        $aiService = new StoreReportAIVisionService();
        $count = 0;
        $success = 0;
        $failed = 0;

        while ($row = $result->fetch_assoc()) {
            try {
                $analysis = $aiService->analyzeImage($row['id']);

                if ($analysis['success']) {
                    $success++;
                    echo "  ✓ Analyzed image {$row['id']}\n";
                } else {
                    $failed++;
                    echo "  ✗ Failed image {$row['id']}: {$analysis['error']}\n";
                }

                $count++;

                // Rate limiting
                if ($count % 10 == 0) {
                    echo "  Progress: {$count} images analyzed ({$success} success, {$failed} failed)\n";
                    sleep(2); // Pause to avoid rate limits
                }

            } catch (Exception $e) {
                $failed++;
                echo "  ✗ Error analyzing image {$row['id']}: " . $e->getMessage() . "\n";
            }
        }

        $this->stats['images_analyzed'] = $success;
        echo "\n  AI re-analysis complete: {$success} successful, {$failed} failed\n";

        if ($count < $this->stats['images_migrated']) {
            echo "  Note: Only analyzed first 100 images. Run again to continue.\n";
        }
    }

    /**
     * Helper: Detect category from question name/desc
     */
    private function detectCategory(string $name, string $desc): string {
        $name = strtolower($name);
        $desc = strtolower($desc);

        if (strpos($name, 'clean') !== false || strpos($desc, 'clean') !== false) {
            return 'Cleanliness';
        }
        if (strpos($name, 'safety') !== false || strpos($desc, 'safety') !== false || strpos($desc, 'hazard') !== false) {
            return 'Safety';
        }
        if (strpos($name, 'display') !== false || strpos($desc, 'display') !== false || strpos($desc, 'product') !== false) {
            return 'Product Display';
        }
        if (strpos($name, 'compliance') !== false || strpos($desc, 'compliance') !== false || strpos($desc, 'regulation') !== false) {
            return 'Compliance';
        }
        if (strpos($name, 'staff') !== false || strpos($desc, 'staff') !== false || strpos($desc, 'employee') !== false) {
            return 'Staff & Service';
        }
        if (strpos($name, 'sign') !== false || strpos($desc, 'sign') !== false) {
            return 'Signage';
        }

        return 'General';
    }

    /**
     * Helper: Map question type
     */
    private function mapQuestionType(string $type): string {
        if ($type === 'ranking') return 'rating';
        if ($type === 'yes/no') return 'boolean';
        return 'rating';
    }

    /**
     * Helper: Calculate grade from percentage
     */
    private function calculateGrade(float $percentage): string {
        if ($percentage >= 99) return 'A+';
        if ($percentage >= 97) return 'A';
        if ($percentage >= 95) return 'A-';
        if ($percentage >= 93) return 'B+';
        if ($percentage >= 91) return 'B';
        if ($percentage >= 89) return 'B-';
        if ($percentage >= 87) return 'C+';
        if ($percentage >= 85) return 'C';
        if ($percentage >= 83) return 'C-';
        if ($percentage >= 81) return 'D+';
        if ($percentage >= 79) return 'D';
        if ($percentage >= 77) return 'D-';
        if ($percentage >= 75) return 'E';
        return 'F';
    }

    /**
     * Helper: Calculate points earned
     */
    private function calculatePointsEarned(int $rating, int $maxPoints): float {
        if ($rating === -1) return 0; // N/A
        if ($maxPoints === 0) return 0; // Doesn't count
        return (float)$rating;
    }

    /**
     * Print migration summary
     */
    private function printSummary(): void {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║                   MIGRATION SUMMARY                          ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n\n";

        echo "Checklist Items:  " . $this->stats['checklist_migrated'] . "\n";
        echo "Reports:          " . $this->stats['reports_migrated'] . "\n";
        echo "Report Items:     " . $this->stats['items_migrated'] . "\n";
        echo "Images:           " . $this->stats['images_migrated'] . "\n";

        if ($this->reAnalyze) {
            echo "Images Analyzed:  " . $this->stats['images_analyzed'] . "\n";
        }

        if (!empty($this->stats['errors'])) {
            echo "\n⚠️  Errors encountered:\n";
            foreach ($this->stats['errors'] as $error) {
                echo "  - {$error}\n";
            }
        }

        echo "\n";
    }
}

// ============================================================================
// CLI Execution
// ============================================================================

if (PHP_SAPI === 'cli') {
    $dryRun = true;
    $reAnalyze = false;

    // Parse command line arguments
    $args = array_slice($argv, 1);

    if (in_array('--execute', $args)) {
        $dryRun = false;
    }

    if (in_array('--re-analyze', $args)) {
        $reAnalyze = true;
        $dryRun = false; // Must be live to re-analyze
    }

    if (in_array('--help', $args) || in_array('-h', $args)) {
        echo "Store Reports Data Migration\n\n";
        echo "Usage:\n";
        echo "  php migrate_legacy_data.php [options]\n\n";
        echo "Options:\n";
        echo "  --dry-run       Preview migration (default)\n";
        echo "  --execute       Perform actual migration\n";
        echo "  --re-analyze    Re-analyze migrated images with AI (implies --execute)\n";
        echo "  --help, -h      Show this help message\n\n";
        echo "Examples:\n";
        echo "  php migrate_legacy_data.php                # Preview\n";
        echo "  php migrate_legacy_data.php --execute      # Run migration\n";
        echo "  php migrate_legacy_data.php --re-analyze   # Run + AI analyze\n\n";
        exit(0);
    }

    // Run migration
    $migration = new StoreReportsDataMigration($dryRun, $reAnalyze);
    $migration->migrate();

} else {
    echo "This script must be run from command line.\n";
    echo "Usage: php migrate_legacy_data.php --help\n";
    exit(1);
}
