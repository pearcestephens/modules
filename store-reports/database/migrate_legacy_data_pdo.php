<?php
/**
 * Store Reports Module — Legacy Data Migration (PDO Version)
 * -----------------------------------------------------------
 * Safely migrates historical store quality data (legacy tables)
 * into the new store_reports schema using chunked, resumable
 * batches with structured event logging.
 *
 * Legacy tables expected:
 *   store_quality
 *   store_quality_score_checklist
 *   store_quality_scores
 *   store_quality_images
 *
 * Target tables:
 *   store_reports
 *   store_report_checklist
 *   store_report_items
 *   store_report_images
 *
 * Usage Examples:
 *   php migrate_legacy_data_pdo.php --dry-run
 *   php migrate_legacy_data_pdo.php --execute --batch-size=300
 *   php migrate_legacy_data_pdo.php --execute --phase=reports --start-report-id=500
 *   php migrate_legacy_data_pdo.php --execute --phase=images --hash-images --reanalyze-limit=200
 *   php migrate_legacy_data_pdo.php --execute --phase=reanalyze --reanalyze-limit=50
 *
 * Options:
 *   --dry-run            Do not modify data (default)
 *   --execute            Perform writes
 *   --phase=<name>       all|checklist|reports|items|images|verify|reanalyze
 *   --batch-size=<n>     Rows per batch (default 250)
 *   --start-report-id=<n> Resume reports from ID >= n
 *   --limit=<n>          Cap number of reports (or phase entities)
 *   --checkpoint-file=<path>  Custom checkpoint file path
 *   --hash-images        Compute SHA1 hash for each migrated image
 *   --skip-existing      Skip inserts where target row already exists
 *   --output-json=<path> Write JSON summary/audit file
 *   --reanalyze-limit=<n> Limit images for re-analysis phase
 *
 * Checkpoint file format (JSON):
 * { "reports_last_id":1234, "items_last_id":4567, "images_last_id":7890 }
 */

declare(strict_types=1);

// Attempt to load module bootstrap (provides sr_pdo helpers). If DB env not set, also try root app.php.
require_once __DIR__ . '/../bootstrap.php';
if (!sr_db_available()) {
    $rootApp = realpath(__DIR__ . '/../../../app.php');
    if ($rootApp && is_file($rootApp)) {
        require_once $rootApp;
    }
}
// If still unavailable attempt lightweight direct PDO using .env in project root
if (!sr_db_available()) {
    // Root .env may live one level above public_html (documented path)
    $candidatePaths = [
        realpath(__DIR__ . '/../../../.env'), // public_html/.env
        realpath(__DIR__ . '/../../../../.env'), // one level above if public_html/jcepnzzkmj/public_html
        '/home/129337.cloudwaysapps.com/jcepnzzkmj/.env' // documented absolute
    ];
    $loadedEnv = false;
    foreach ($candidatePaths as $envFile) {
        if ($envFile && is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if ($line[0] === '#' || !str_contains($line,'=')) continue;
                [$k,$v] = array_map('trim', explode('=',$line,2));
                if ($k !== '' && getenv($k) === false) { $_ENV[$k]=$v; putenv("$k=$v"); }
            }
            $loadedEnv = true;
            break;
        }
    }
    if ($envFile && is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if ($line[0] === '#' || !str_contains($line,'=')) continue;
            [$k,$v] = array_map('trim', explode('=',$line,2));
            if ($k !== '' && getenv($k) === false) { $_ENV[$k]=$v; putenv("$k=$v"); }
        }
    }
    $dbHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
    $dbName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? '');
    $dbUser = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? '');
    $dbPass = getenv('DB_PASS');
    if ($dbPass === false || $dbPass === '') { $dbPass = getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? ''); }
    if ($dbName) {
        try {
            $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
            $pdoDirect = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES=>false
            ]);
            if (!function_exists('sr_pdo')) { function sr_pdo(): ?PDO { global $pdoDirect; return $pdoDirect; } }
        } catch (Throwable $e) {
            fwrite(STDERR, "Direct PDO connection failed: ".$e->getMessage()."\n");
        }
    }
}
require_once __DIR__ . '/../models/AbstractModel.php';
require_once __DIR__ . '/../models/StoreReport.php';
require_once __DIR__ . '/../services/gpt/VisionAnalysisService.php';

if (PHP_SAPI !== 'cli') {
    echo "This migration must be run via CLI.\n";
    exit(1);
}

// --------------------------------------------------
// Argument Parsing
// --------------------------------------------------
$args = $argv;
array_shift($args);

$options = [
    'dry_run' => true,
    'phase' => 'all',
    'batch_size' => 250,
    'start_report_id' => null,
    'limit' => null,
    'hash_images' => false,
    'skip_existing' => false,
    'checkpoint_file' => __DIR__ . '/.migration_checkpoint.json',
    'output_json' => null,
    'reanalyze_limit' => 100,
];

foreach ($args as $arg) {
    if ($arg === '--execute') $options['dry_run'] = false;
    elseif ($arg === '--dry-run') $options['dry_run'] = true;
    elseif (str_starts_with($arg, '--phase=')) $options['phase'] = substr($arg, 8);
    elseif (str_starts_with($arg, '--batch-size=')) $options['batch_size'] = (int)substr($arg, 13);
    elseif (str_starts_with($arg, '--start-report-id=')) $options['start_report_id'] = (int)substr($arg, 19);
    elseif (str_starts_with($arg, '--limit=')) $options['limit'] = (int)substr($arg, 8);
    elseif ($arg === '--hash-images') $options['hash_images'] = true;
    elseif ($arg === '--skip-existing') $options['skip_existing'] = true;
    elseif (str_starts_with($arg, '--checkpoint-file=')) $options['checkpoint_file'] = substr($arg, 18);
    elseif (str_starts_with($arg, '--output-json=')) $options['output_json'] = substr($arg, 14);
    elseif (str_starts_with($arg, '--reanalyze-limit=')) $options['reanalyze_limit'] = (int)substr($arg, 18);
    elseif ($arg === '--help' || $arg === '-h') { printHelp(); exit(0); }
}

function printHelp(): void {
    echo "Legacy Data Migration (PDO)\n\n";
    echo "Options:\n";
    echo "  --dry-run (default)\n";
    echo "  --execute\n";
    echo "  --phase=all|checklist|reports|items|images|verify|reanalyze\n";
    echo "  --batch-size=250\n";
    echo "  --start-report-id=NNN\n";
    echo "  --limit=NNN\n";
    echo "  --hash-images\n";
    echo "  --skip-existing\n";
    echo "  --checkpoint-file=path\n";
    echo "  --output-json=path\n";
    echo "  --reanalyze-limit=100\n";
}

// --------------------------------------------------
// Utilities
// --------------------------------------------------
function out(string $msg): void { echo $msg."\n"; }
function evt(string $type, array $data=[]): void { if (function_exists('sr_log_event')) sr_log_event($type,$data); }
function loadCheckpoint(string $file): array { if (!is_file($file)) return []; $json = json_decode(file_get_contents($file), true); return is_array($json)?$json:[]; }
function saveCheckpoint(string $file, array $data): void { file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)); }

$checkpoint = loadCheckpoint($options['checkpoint_file']);

$pdo = sr_pdo();
if (!$pdo) {
    out("❌ Database unavailable (no PDO). Ensure DB_HOST/DB_NAME/DB_USER/DB_PASS in .env or environment.");
    exit(2);
}

// --------------------------------------------------
// Stats accumulator
// --------------------------------------------------
$stats = [
    'checklist_total' => 0,
    'checklist_migrated' => 0,
    'reports_total' => 0,
    'reports_migrated' => 0,
    'reports_skipped' => 0,
    'items_total' => 0,
    'items_migrated' => 0,
    'items_skipped' => 0,
    'images_total' => 0,
    'images_migrated' => 0,
    'images_missing_source' => 0,
    'images_hashed' => 0,
    'reanalyzed' => 0,
    'errors' => []
];

// --------------------------------------------------
// Phase Dispatch
// --------------------------------------------------
out("══════════════════════════════════════════════════════");
out(" Legacy Store Reports Migration (PDO)  ");
out(" Mode: ".($options['dry_run']? 'DRY-RUN':'EXECUTE')."  Phase: {$options['phase']}  Batch: {$options['batch_size']}");
out("══════════════════════════════════════════════════════\n");
evt('migration_start',['phase'=>$options['phase'],'dry_run'=>$options['dry_run'],'batch_size'=>$options['batch_size']]);

// Phase counting (always run for context)
countPhaseTotals($pdo, $stats);

try {
    switch ($options['phase']) {
        case 'all':
            migrateChecklist($pdo, $options, $stats, $checkpoint);
            migrateReports($pdo, $options, $stats, $checkpoint);
            migrateItems($pdo, $options, $stats, $checkpoint);
            migrateImages($pdo, $options, $stats, $checkpoint);
            break;
        case 'checklist': migrateChecklist($pdo,$options,$stats,$checkpoint); break;
        case 'reports': migrateReports($pdo,$options,$stats,$checkpoint); break;
        case 'items': migrateItems($pdo,$options,$stats,$checkpoint); break;
        case 'images': migrateImages($pdo,$options,$stats,$checkpoint); break;
        case 'verify': verifyMigration($pdo,$stats); break;
        case 'reanalyze': reanalyzeImages($pdo,$options,$stats); break;
        default: throw new InvalidArgumentException('Unknown phase: '.$options['phase']);
    }
} catch (Throwable $e) {
    $stats['errors'][] = $e->getMessage();
    out('❌ Fatal migration error: '.$e->getMessage());
    evt('migration_fatal',['error'=>$e->getMessage()]);
}

evt('migration_complete',['phase'=>$options['phase'],'stats'=>$stats]);
renderSummary($stats, $options);

if ($options['output_json']) {
    file_put_contents($options['output_json'], json_encode(['options'=>$options,'stats'=>$stats,'checkpoint'=>$checkpoint], JSON_PRETTY_PRINT));
    out('📄 Wrote JSON summary: '.$options['output_json']);
}

saveCheckpoint($options['checkpoint_file'], $checkpoint);
out('🔖 Checkpoint saved: '.$options['checkpoint_file']);

exit(0);

// --------------------------------------------------
// Counting phase totals
// --------------------------------------------------
function countPhaseTotals(PDO $pdo, array &$stats): void {
    $stats['checklist_total'] = (int)$pdo->query('SELECT COUNT(*) FROM store_quality_score_checklist')->fetchColumn();
    $stats['reports_total']   = (int)$pdo->query('SELECT COUNT(*) FROM store_quality')->fetchColumn();
    $stats['items_total']     = (int)$pdo->query('SELECT COUNT(*) FROM store_quality_scores')->fetchColumn();
    $stats['images_total']    = (int)$pdo->query('SELECT COUNT(*) FROM store_quality_images')->fetchColumn();
}

// --------------------------------------------------
// Category detection (ported)
// --------------------------------------------------
function detectCategory(string $name, string $desc): string {
    $name = strtolower($name); $desc = strtolower($desc);
    return match (true) {
        str_contains($name,'clean') || str_contains($desc,'clean') => 'Cleanliness',
        str_contains($name,'safety') || str_contains($desc,'safety') || str_contains($desc,'hazard') => 'Safety',
        str_contains($name,'display') || str_contains($desc,'display') || str_contains($desc,'product') => 'Product Display',
        str_contains($name,'compliance') || str_contains($desc,'compliance') || str_contains($desc,'regulation') => 'Compliance',
        str_contains($name,'staff') || str_contains($desc,'staff') || str_contains($desc,'employee') => 'Staff & Service',
        str_contains($name,'sign') || str_contains($desc,'sign') => 'Signage',
        default => 'General'
    };
}

function mapQuestionType(string $type): string { return $type === 'yes/no' ? 'boolean' : 'rating'; }

// --------------------------------------------------
// Migrate Checklist
// --------------------------------------------------
function migrateChecklist(PDO $pdo, array $opt, array &$stats, array &$checkpoint): void {
    out('📋 Phase: Checklist'); evt('migration_phase_start',['phase'=>'checklist']);
    $sql = 'SELECT * FROM store_quality_score_checklist WHERE status = 1';
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $stats['checklist_total'] = count($rows);
    $insert = $pdo->prepare("INSERT INTO store_report_checklist
        (id, category, name, title, description, question_type, input_type, max_points, weight, counts_toward_grade, display_order, is_active)
        VALUES (:id,:category,:name,:title,:description,:question_type,:input_type,:max_points,:weight,:counts,:display_order,:active)
        ON DUPLICATE KEY UPDATE category=VALUES(category), title=VALUES(title), description=VALUES(description)");
    foreach ($rows as $r) {
        $category = detectCategory($r['name'],$r['desc']);
        $questionType = mapQuestionType($r['type']);
        $maxPoints = ((int)$r['score_points'] === 1) ? 4 : 0;
        $counts = ((int)$r['score_points'] === 1) ? 1 : 0;
        if (!$opt['dry_run']) {
            $insert->execute([
                ':id'=>$r['id'], ':category'=>$category, ':name'=>$r['name'], ':title'=>$r['desc'], ':description'=>$r['desc'],
                ':question_type'=>$questionType, ':input_type'=>$r['input_type'] ?? 'select', ':max_points'=>$maxPoints, ':weight'=>1.0,
                ':counts'=>$counts, ':display_order'=>0, ':active'=>1
            ]);
        }
        $stats['checklist_migrated']++;
    }
    evt('migration_phase_complete',['phase'=>'checklist','migrated'=>$stats['checklist_migrated']]);
}

// --------------------------------------------------
// Grade mapping via StoreReport model
// --------------------------------------------------
function gradeFromScore(float $score): string {
    $ranges = [
        ['min'=>99,'max'=>100,'grade'=>'A+'],['min'=>97,'max'=>98,'grade'=>'A'],['min'=>95,'max'=>96,'grade'=>'A-'],
        ['min'=>93,'max'=>94,'grade'=>'B+'],['min'=>91,'max'=>92,'grade'=>'B'],['min'=>89,'max'=>90,'grade'=>'B-'],
        ['min'=>87,'max'=>88,'grade'=>'C+'],['min'=>85,'max'=>86,'grade'=>'C'],['min'=>83,'max'=>84,'grade'=>'C-'],
        ['min'=>81,'max'=>82,'grade'=>'D+'],['min'=>79,'max'=>80,'grade'=>'D'],['min'=>77,'max'=>78,'grade'=>'D-'],['min'=>75,'max'=>76,'grade'=>'E']
    ];
    foreach ($ranges as $r) if ($score >= $r['min'] && $score <= $r['max']) return $r['grade']; return 'F';
}

// --------------------------------------------------
// Migrate Reports (chunked)
// --------------------------------------------------
function migrateReports(PDO $pdo, array $opt, array &$stats, array &$checkpoint): void {
    out('📊 Phase: Reports'); evt('migration_phase_start',['phase'=>'reports']);
    $batch = $opt['batch_size'];
    $startId = $opt['start_report_id'] ?? ($checkpoint['reports_last_id'] ?? 0);
    $limit = $opt['limit'];
    $processed = 0; $migrated = 0; $skipped = 0;
    $stmtFetch = $pdo->prepare('SELECT * FROM store_quality WHERE id > :last ORDER BY id ASC LIMIT :lim');
    $stmtFetch->bindParam(':last', $startId, PDO::PARAM_INT);
    $stmtFetch->bindParam(':lim', $batch, PDO::PARAM_INT);
    $insert = $pdo->prepare("INSERT INTO store_reports
        (id, outlet_id, performed_by_user, report_date, overall_score, grade, staff_notes, status, created_at, updated_at)
        VALUES (:id,:outlet,:user,:date,:score,:grade,:notes,'completed',:created,:updated)
        ON DUPLICATE KEY UPDATE staff_notes=VALUES(staff_notes), overall_score=VALUES(overall_score), grade=VALUES(grade)");
    while (true) {
        $stmtFetch->execute();
        $rows = $stmtFetch->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) break;
        sr_transaction(function() use ($rows,$opt,$insert,&$migrated,&$skipped,&$processed,&$startId,$limit,&$stats,$checkpoint) {
            foreach ($rows as $r) {
                $processed++; $startId = (int)$r['id'];
                if ($limit && $processed > $limit) return; // stop inside transaction
                $grade = gradeFromScore((float)$r['percentage']);
                if (!$opt['dry_run']) {
                    $insert->execute([
                        ':id'=>$r['id'], ':outlet'=>$r['outlet_id'], ':user'=>$r['performed_by_user'],
                        ':date'=>$r['date_performed'] ?? $r['date_created'], ':score'=>$r['percentage'], ':grade'=>$grade,
                        ':notes'=>$r['other_notes'] ?? '', ':created'=>$r['date_created'], ':updated'=>$r['date_created']
                    ]);
                }
                $migrated++;
                if ($migrated % 100 === 0) evt('migration_batch_progress',['phase'=>'reports','migrated'=>$migrated]);
            }
        });
        $checkpoint['reports_last_id'] = $startId;
        if ($limit && $processed >= $limit) break;
    }
    $stats['reports_migrated'] = $migrated; $stats['reports_skipped'] = $skipped; evt('migration_phase_complete',['phase'=>'reports','migrated'=>$migrated]);
}

// --------------------------------------------------
// Migrate Items (chunked by id)
// --------------------------------------------------
function migrateItems(PDO $pdo, array $opt, array &$stats, array &$checkpoint): void {
    out('✅ Phase: Report Items'); evt('migration_phase_start',['phase'=>'items']);
    $batch = $opt['batch_size'];
    $lastId = $checkpoint['items_last_id'] ?? 0; $limit = $opt['limit'];
    $fetch = $pdo->prepare('SELECT sqc.*, sqcl.score_points, sqcl.type AS question_type FROM store_quality_scores sqc JOIN store_quality_score_checklist sqcl ON sqc.score_id = sqcl.id WHERE sqc.id > :last ORDER BY sqc.id ASC LIMIT :lim');
    $fetch->bindParam(':last',$lastId,PDO::PARAM_INT); $fetch->bindParam(':lim',$batch,PDO::PARAM_INT);
    $insert = $pdo->prepare('INSERT INTO store_report_items (report_id, checklist_id, response_value, response_text, is_na, max_points, points_earned, staff_notes) VALUES (:report,:checklist,:value,NULL,:is_na,:max_points,:points,:notes)');
    $processed=0; $migrated=0; $skipped=0;
    while (true) {
        $fetch->execute(); $rows = $fetch->fetchAll(PDO::FETCH_ASSOC); if (!$rows) break;
        sr_transaction(function() use($rows,$opt,$insert,&$processed,&$migrated,&$skipped,&$lastId,$limit,$checkpoint) {
            foreach ($rows as $r) {
                $processed++; $lastId = (int)$r['id'];
                if ($limit && $processed > $limit) return;
                $maxPoints = ((int)$r['score_points'] === 1)?4:0;
                $pointsEarned = ($r['rating'] >= 0 && $maxPoints>0) ? (float)$r['rating'] : 0.0;
                if (!$opt['dry_run']) {
                    $insert->execute([
                        ':report'=>$r['store_quality_id'], ':checklist'=>$r['score_id'], ':value'=>$r['rating'], ':is_na'=>$r['not_applicable'], ':max_points'=>$maxPoints, ':points'=>$pointsEarned, ':notes'=>$r['notes'] ?? ''
                    ]);
                }
                $migrated++;
            }
        });
        $checkpoint['items_last_id'] = $lastId;
        if ($limit && $processed >= $limit) break;
    }
    $stats['items_migrated']=$migrated; $stats['items_skipped']=$skipped; evt('migration_phase_complete',['phase'=>'items','migrated'=>$migrated]);
}

// --------------------------------------------------
// Migrate Images
// --------------------------------------------------
function migrateImages(PDO $pdo, array $opt, array &$stats, array &$checkpoint): void {
    out('📸 Phase: Images'); evt('migration_phase_start',['phase'=>'images']);
    $batch = $opt['batch_size']; $lastId = $checkpoint['images_last_id'] ?? 0; $limit = $opt['limit'];
    $fetch = $pdo->prepare('SELECT sqi.*, sq.outlet_id, sq.performed_by_user FROM store_quality_images sqi JOIN store_quality sq ON sqi.store_quality_id = sq.id WHERE sqi.id > :last ORDER BY sqi.id ASC LIMIT :lim');
    $fetch->bindParam(':last',$lastId,PDO::PARAM_INT); $fetch->bindParam(':lim',$batch,PDO::PARAM_INT);
    $insert = $pdo->prepare('INSERT INTO store_report_images (report_id, filename, file_path, file_size, mime_type, uploaded_by_user, upload_timestamp, status, ai_analyzed, file_hash) VALUES (:report,:filename,:path,:size,:mime,:user,NOW(),"uploaded",FALSE,:hash)');
    $oldPath = '/home/129337.cloudwaysapps.com/jcepnzzkmj/public_html/assets/img/store-quality-images/';
    $newPath = dirname(__DIR__).'/uploads/';
    if (!$opt['dry_run'] && !is_dir($newPath)) mkdir($newPath,0755,true);
    $processed=0; $migrated=0;
    while (true) {
        $fetch->execute(); $rows=$fetch->fetchAll(PDO::FETCH_ASSOC); if(!$rows) break;
        sr_transaction(function() use($rows,$opt,$insert,$oldPath,$newPath,&$processed,&$migrated,&$lastId,$limit,$checkpoint,&$stats) {
            foreach ($rows as $r) {
                $processed++; $lastId = (int)$r['id'];
                if ($limit && $processed > $limit) return;
                $oldFile = $oldPath.$r['filename']; $newFile = $newPath.$r['filename'];
                $size = is_file($oldFile)?filesize($oldFile):null; $mime = is_file($oldFile)?mime_content_type($oldFile):null;
                if (!is_file($oldFile)) { $stats['images_missing_source']++; continue; }
                $hash = null;
                if ($opt['hash_images'] && is_file($oldFile)) { $hash = sha1_file($oldFile); $stats['images_hashed']++; }
                if (!$opt['dry_run']) { if (!is_file($newFile)) copy($oldFile,$newFile); }
                if (!$opt['dry_run']) {
                    $insert->execute([
                        ':report'=>$r['store_quality_id'], ':filename'=>$r['filename'], ':path'=>$newFile, ':size'=>$size, ':mime'=>$mime, ':user'=>$r['performed_by_user'], ':hash'=>$hash
                    ]);
                }
                $migrated++;
                if ($migrated % 100 === 0) evt('migration_batch_progress',['phase'=>'images','migrated'=>$migrated]);
            }
        });
        $checkpoint['images_last_id'] = $lastId;
        if ($limit && $processed >= $limit) break;
    }
    $stats['images_migrated']=$migrated; evt('migration_phase_complete',['phase'=>'images','migrated'=>$migrated]);
}

// --------------------------------------------------
// Re-analyze Images (subset)
// --------------------------------------------------
function reanalyzeImages(PDO $pdo, array $opt, array &$stats): void {
    out('🤖 Phase: Re-Analyze Images'); evt('migration_phase_start',['phase'=>'reanalyze']);
    $limit = $opt['reanalyze_limit'];
    $rows = sr_query('SELECT id FROM store_report_images WHERE ai_analyzed = FALSE ORDER BY id ASC LIMIT ?', [$limit]);
    if (!$rows) { out('No unanalyzed images found.'); return; }
    $svc = new StoreReportAIVisionService();
    foreach ($rows as $row) {
        try {
            $res = $svc->analyzeImage((int)$row['id']);
            if ($res['success']) $stats['reanalyzed']++; else $stats['errors'][] = 'Image '.$row['id'].' failed: '.$res['error'];
        } catch (Throwable $e) {
            $stats['errors'][] = 'Image '.$row['id'].' exception: '.$e->getMessage();
        }
    }
    evt('migration_phase_complete',['phase'=>'reanalyze','reanalyzed'=>$stats['reanalyzed']]);
}

// --------------------------------------------------
// Verification Phase
// --------------------------------------------------
function verifyMigration(PDO $pdo, array &$stats): void {
    out('🔍 Phase: Verify'); evt('migration_phase_start',['phase'=>'verify']);
    $legacyCount = (int)$pdo->query('SELECT COUNT(*) FROM store_quality')->fetchColumn();
    $newCount = (int)$pdo->query('SELECT COUNT(*) FROM store_reports')->fetchColumn();
    // ID checksum comparison for quick integrity signal
    $legacyIds = $pdo->query('SELECT id FROM store_quality ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $newIds    = $pdo->query('SELECT id FROM store_reports ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $legacyChecksum = 0; foreach ($legacyIds as $i) { $legacyChecksum = ($legacyChecksum + (int)$i) % 1000000007; }
    $newChecksum = 0; foreach ($newIds as $i) { $newChecksum = ($newChecksum + (int)$i) % 1000000007; }
    out("Reports: legacy={$legacyCount} new={$newCount} legacy_checksum={$legacyChecksum} new_checksum={$newChecksum}");
    $sample = $pdo->query('SELECT id, overall_score, grade FROM store_reports ORDER BY RAND() LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sample as $row) { out("  Sample Report {$row['id']} score={$row['overall_score']} grade={$row['grade']}"); }
    evt('migration_phase_complete',['phase'=>'verify','legacy_reports'=>$legacyCount,'new_reports'=>$newCount,'legacy_checksum'=>$legacyChecksum,'new_checksum'=>$newChecksum]);
}

// --------------------------------------------------
// Summary Renderer
// --------------------------------------------------
function renderSummary(array $stats, array $opt): void {
    out("\n════════ SUMMARY ════════");
    out('Checklist migrated: '.$stats['checklist_migrated'].' / '.$stats['checklist_total']);
    out('Reports migrated:   '.$stats['reports_migrated'].' / '.$stats['reports_total']);
    out('Items migrated:     '.$stats['items_migrated'].' / '.$stats['items_total']);
    out('Images migrated:    '.$stats['images_migrated'].' / '.$stats['images_total']);
    out('Images missing:     '.$stats['images_missing_source']);
    if ($opt['hash_images']) out('Images hashed:      '.$stats['images_hashed']);
    if ($stats['reanalyzed']) out('Re-analyzed images: '.$stats['reanalyzed']);
    if ($stats['errors']) {
        out("Errors (".count($stats['errors'])."):"); foreach ($stats['errors'] as $e) out('  - '.$e);
    }
    out('Mode: '.($opt['dry_run']?'DRY-RUN':'EXECUTE')); out('Phase complete.');
}

?>
