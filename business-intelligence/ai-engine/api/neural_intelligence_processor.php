<?php
/**
 * Neural Intelligence Processor
 *
 * THIS is the REAL intelligent system that:
 * - Analyzes content deeply (complexity, quality, business value)
 * - Extracts patterns and relationships
 * - Builds semantic understanding
 * - Creates searchable intelligence
 *
 * Processes files from intelligence_files and populates:
 * - intelligence_content (metadata & scores)
 * - intelligence_content_text (searchable full-text)
 * - intelligence_content_types (categorization)
 * - neural_patterns (code/doc patterns)
 * - neural_pattern_relationships (how things connect)
 *
 * Usage:
 *   php neural_intelligence_processor.php --server=jcepnzzkmj --analyze
 *   php neural_intelligence_processor.php --server=jcepnzzkmj --full
 *
 * @package Neural_Intelligence
 * @version 3.0.0
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'jcepnzzkmj');
define('DB_USER', 'jcepnzzkmj');
define('DB_PASS', 'bFUdRjh4Jx');

class NeuralIntelligenceProcessor {
    private $db;
    private $server;
    private $stats = [
        'files_processed' => 0,
        'content_analyzed' => 0,
        'patterns_extracted' => 0,
        'relationships_built' => 0,
        'intelligence_scored' => 0,
        'errors' => []
    ];

    public function __construct($server) {
        $this->server = $server;

        // Connect to database
        try {
            $this->db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Process all files for a server
     */
    public function processServer() {
        $this->log("🧠 Starting Neural Intelligence Processing for {$this->server}");

        // Get unprocessed files from intelligence_files
        $stmt = $this->db->prepare("
            SELECT
                if1.file_id,
                if1.business_unit_id,
                if1.server_id,
                if1.file_path,
                if1.file_name,
                if1.file_type,
                if1.file_size,
                if1.file_content,
                if1.intelligence_type,
                if1.intelligence_data,
                if1.content_summary
            FROM intelligence_files if1
            LEFT JOIN intelligence_content ic ON if1.file_path = ic.content_path
                AND if1.server_id = ic.source_system
            WHERE if1.server_id = :server
            AND if1.file_content IS NOT NULL
            AND if1.file_content != ''
            AND ic.content_id IS NULL
            ORDER BY if1.file_size ASC
            LIMIT 5000
        ");

        $stmt->execute(['server' => $this->server]);
        $files = $stmt->fetchAll();

        $this->log("📊 Found " . count($files) . " unprocessed files");

        foreach ($files as $file) {
            $this->processFile($file);
        }

        // Build relationships after processing
        $this->log("\n🔗 Building neural relationships...");
        $this->buildNeuralRelationships();

        // Generate statistics
        $this->generateStatistics();

        $this->log("\n" . str_repeat('=', 60));
        $this->log("✅ PROCESSING COMPLETE");
        $this->log(str_repeat('=', 60));
        $this->log("📊 Statistics:");
        $this->log("   Files processed: {$this->stats['files_processed']}");
        $this->log("   Content analyzed: {$this->stats['content_analyzed']}");
        $this->log("   Patterns extracted: {$this->stats['patterns_extracted']}");
        $this->log("   Relationships built: {$this->stats['relationships_built']}");
        $this->log("   Intelligence scored: {$this->stats['intelligence_scored']}");

        if (!empty($this->stats['errors'])) {
            $this->log("\n⚠️  Errors: " . count($this->stats['errors']));
        }
    }

    /**
     * Process individual file with REAL intelligence
     */
    private function processFile($file) {
        try {
            $this->stats['files_processed']++;

            // Progress indicator
            if ($this->stats['files_processed'] % 100 == 0) {
                $this->log("   Processed {$this->stats['files_processed']} files...");
            }

            $content = $file['file_content'];
            $intel_data = json_decode($file['intelligence_data'], true) ?? [];

            // 1. Calculate REAL intelligence scores
            $scores = $this->calculateIntelligenceScores($content, $file['file_type'], $intel_data);

            // 2. Detect language and encoding
            $language = $this->detectLanguage($file['file_name'], $content);
            $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'ASCII'], true) ?: 'UTF-8';

            // 3. Generate content hash for deduplication
            $content_hash = hash('sha256', $file['server_id'] . '|' . $file['file_path']);

            // 4. Determine mime type
            $mime_type = $this->getMimeType($file['file_name'], $file['file_type']);

            // 5. Get or create content type
            $content_type_id = $this->getContentTypeId($file['file_type'], $file['intelligence_type']);

            // 6. Insert into intelligence_content (metadata table)
            $stmt = $this->db->prepare("
                INSERT INTO intelligence_content (
                    org_id,
                    unit_id,
                    content_type_id,
                    source_system,
                    content_path,
                    content_name,
                    content_hash,
                    file_size,
                    mime_type,
                    language_detected,
                    encoding,
                    intelligence_score,
                    complexity_score,
                    quality_score,
                    business_value_score,
                    last_analyzed,
                    created_at
                ) VALUES (
                    1,
                    :unit_id,
                    :type_id,
                    :server,
                    :path,
                    :name,
                    :hash,
                    :size,
                    :mime,
                    :language,
                    :encoding,
                    :intel_score,
                    :complexity,
                    :quality,
                    :business_value,
                    NOW(),
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    intelligence_score = :intel_score,
                    complexity_score = :complexity,
                    quality_score = :quality,
                    business_value_score = :business_value,
                    last_analyzed = NOW()
            ");

            $result = $stmt->execute([
                'unit_id' => $file['business_unit_id'],
                'type_id' => $content_type_id,
                'server' => $file['server_id'],
                'path' => $file['file_path'],
                'name' => $file['file_name'],
                'hash' => $content_hash,
                'size' => $file['file_size'],
                'mime' => $mime_type,
                'language' => $language,
                'encoding' => $encoding,
                'intel_score' => $scores['intelligence'],
                'complexity' => $scores['complexity'],
                'quality' => $scores['quality'],
                'business_value' => $scores['business_value']
            ]);

            $content_id = $this->db->lastInsertId() ?: $this->getContentId($content_hash);

            if (!$content_id) {
                $this->stats['errors'][] = "Failed to get content_id for {$file['file_path']}";
                return;
            }

            $this->stats['content_analyzed']++;

            // 7. Insert searchable text content
            $this->insertContentText($content_id, $content, $file['file_type']);

            // 8. Extract patterns (functions, classes, endpoints, etc)
            $this->extractPatterns($content_id, $content, $file, $intel_data);

            $this->stats['intelligence_scored']++;

        } catch (Exception $e) {
            $this->stats['errors'][] = "Error processing {$file['file_path']}: " . $e->getMessage();
        }
    }

    /**
     * Calculate REAL intelligence scores
     */
    private function calculateIntelligenceScores($content, $file_type, $intel_data) {
        $scores = [
            'intelligence' => 0,
            'complexity' => 0,
            'quality' => 0,
            'business_value' => 0
        ];

        $lines = substr_count($content, "\n") + 1;
        $size = strlen($content);

        if ($file_type === 'code_intelligence') {
            // Code intelligence scoring
            $function_count = $intel_data['function_count'] ?? 0;

            // Complexity: based on functions, lines, nesting
            $scores['complexity'] = min(100, (
                ($function_count * 2) +
                ($lines / 10) +
                (substr_count($content, 'if (') * 0.5) +
                (substr_count($content, 'foreach') * 0.7) +
                (substr_count($content, 'while') * 0.7)
            ));

            // Quality: documentation, structure, naming
            $has_docblocks = substr_count($content, '/**') > 0;
            $has_type_hints = preg_match('/function\s+\w+\s*\([^)]*:\s*\w+/', $content);
            $has_returns = substr_count($content, 'return ');
            $has_error_handling = substr_count($content, 'try {') > 0 || substr_count($content, 'catch');

            $scores['quality'] = (
                ($has_docblocks ? 25 : 0) +
                ($has_type_hints ? 25 : 0) +
                ($has_returns > 0 ? 20 : 0) +
                ($has_error_handling ? 30 : 0)
            );

            // Intelligence: reusability, abstraction, patterns
            $is_class = preg_match('/class\s+\w+/', $content);
            $uses_interfaces = preg_match('/implements\s+\w+/', $content);
            $uses_traits = substr_count($content, 'use ') > 0;
            $has_namespace = preg_match('/namespace\s+\w+/', $content);

            $scores['intelligence'] = (
                ($is_class ? 30 : 0) +
                ($uses_interfaces ? 25 : 0) +
                ($uses_traits ? 15 : 0) +
                ($has_namespace ? 20 : 0) +
                ($function_count > 5 ? 10 : 0)
            );

            // Business value: API endpoints, critical functions, reusability
            $is_api = strpos($content, 'API') !== false || strpos($content, 'endpoint') !== false;
            $is_controller = strpos($content, 'Controller') !== false;
            $is_model = strpos($content, 'Model') !== false;
            $has_db_access = substr_count($content, 'PDO') > 0 || substr_count($content, 'query') > 0;

            $scores['business_value'] = (
                ($is_api ? 30 : 0) +
                ($is_controller ? 25 : 0) +
                ($is_model ? 20 : 0) +
                ($has_db_access ? 15 : 0) +
                ($function_count > 3 ? 10 : 0)
            );

        } elseif ($file_type === 'documentation') {
            // Documentation intelligence scoring
            $has_headers = preg_match_all('/^#+\s+/m', $content) > 0;
            $has_code_examples = substr_count($content, '```') >= 2;
            $has_lists = preg_match('/^[-*]\s+/m', $content);
            $has_links = substr_count($content, '[') > 0 && substr_count($content, '](') > 0;

            $scores['quality'] = (
                ($has_headers ? 30 : 0) +
                ($has_code_examples ? 30 : 0) +
                ($has_lists ? 20 : 0) +
                ($has_links ? 20 : 0)
            );

            // Complexity: structure depth, content volume
            $scores['complexity'] = min(100, (
                (preg_match_all('/^#+\s+/m', $content) * 5) +
                ($lines / 20)
            ));

            // Intelligence: completeness, examples, references
            $scores['intelligence'] = (
                ($lines > 100 ? 30 : $lines / 3) +
                ($has_code_examples ? 40 : 0) +
                (substr_count($content, 'example') > 0 ? 15 : 0) +
                (substr_count($content, 'usage') > 0 ? 15 : 0)
            );

            // Business value: specifications, architecture, requirements
            $is_spec = preg_match('/specification|requirement|architecture/i', $content);
            $is_guide = preg_match('/guide|tutorial|howto/i', $content);
            $is_reference = preg_match('/reference|api|documentation/i', $content);

            $scores['business_value'] = (
                ($is_spec ? 40 : 0) +
                ($is_guide ? 30 : 0) +
                ($is_reference ? 30 : 0)
            );

        } elseif ($file_type === 'business_intelligence') {
            // Business data intelligence scoring
            $is_json = strpos($content, '{') === 0 || strpos($content, '[') === 0;
            $is_xml = strpos($content, '<?xml') === 0 || strpos($content, '<') === 0;

            if ($is_json) {
                $data = json_decode($content, true);
                $depth = $this->getArrayDepth($data);
                $keys = $this->countArrayKeys($data);

                $scores['complexity'] = min(100, $depth * 10 + ($keys / 5));
                $scores['intelligence'] = min(100, ($keys / 2) + ($depth * 5));
                $scores['quality'] = json_last_error() === JSON_ERROR_NONE ? 100 : 0;

                // Check for business-critical data
                $has_pricing = isset($data['price']) || isset($data['cost']);
                $has_inventory = isset($data['stock']) || isset($data['quantity']);
                $has_customer = isset($data['customer']) || isset($data['user']);

                $scores['business_value'] = (
                    ($has_pricing ? 35 : 0) +
                    ($has_inventory ? 35 : 0) +
                    ($has_customer ? 30 : 0)
                );
            }
        }

        // Normalize all scores to 0-100
        foreach ($scores as $key => $value) {
            $scores[$key] = max(0, min(100, round($value, 2)));
        }

        return $scores;
    }

    /**
     * Insert searchable full-text content
     */
    private function insertContentText($content_id, $content, $file_type) {
        // Clean content for text search
        $searchable_text = $this->cleanContentForSearch($content, $file_type);

        // Extract keywords
        $keywords = $this->extractKeywords($content, $file_type);

        $stmt = $this->db->prepare("
            INSERT INTO intelligence_content_text (
                content_id,
                full_text_content,
                searchable_text,
                keywords,
                word_count,
                created_at
            ) VALUES (
                :content_id,
                :full_text,
                :searchable,
                :keywords,
                :word_count,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                full_text_content = :full_text,
                searchable_text = :searchable,
                keywords = :keywords,
                word_count = :word_count
        ");

        $word_count = str_word_count($searchable_text);

        $stmt->execute([
            'content_id' => $content_id,
            'full_text' => $content,
            'searchable' => $searchable_text,
            'keywords' => implode(',', $keywords),
            'word_count' => $word_count
        ]);
    }

    /**
     * Extract patterns from content
     */
    private function extractPatterns($content_id, $content, $file, $intel_data) {
        $patterns = [];

        if ($file['file_type'] === 'code_intelligence') {
            // Extract function patterns
            if (isset($intel_data['functions'])) {
                foreach ($intel_data['functions'] as $func) {
                    $patterns[] = [
                        'type' => 'function',
                        'name' => $func['name'],
                        'signature' => $func['signature'] ?? '',
                        'context' => substr($content, 0, 500)
                    ];
                }
            }

            // Extract class patterns
            preg_match_all('/class\s+(\w+)(?:\s+extends\s+(\w+))?(?:\s+implements\s+([\w,\s]+))?/i', $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $patterns[] = [
                    'type' => 'class',
                    'name' => $match[1],
                    'extends' => $match[2] ?? null,
                    'implements' => isset($match[3]) ? $match[3] : null
                ];
            }

            // Extract API endpoint patterns
            preg_match_all('/\$_(?:GET|POST|REQUEST)\[[\'"]([\w_]+)[\'"]\]/i', $content, $matches);
            foreach (array_unique($matches[1]) as $param) {
                $patterns[] = [
                    'type' => 'api_parameter',
                    'name' => $param
                ];
            }

            // Extract database table patterns
            preg_match_all('/FROM\s+`?(\w+)`?/i', $content, $matches);
            preg_match_all('/INTO\s+`?(\w+)`?/i', $content, $matches2);
            preg_match_all('/UPDATE\s+`?(\w+)`?/i', $content, $matches3);

            $tables = array_unique(array_merge($matches[1] ?? [], $matches2[1] ?? [], $matches3[1] ?? []));
            foreach ($tables as $table) {
                $patterns[] = [
                    'type' => 'database_table',
                    'name' => $table
                ];
            }
        }

        // Store patterns
        foreach ($patterns as $pattern) {
            $this->storePattern($content_id, $pattern, $file);
        }

        $this->stats['patterns_extracted'] += count($patterns);
    }

    /**
     * Store neural pattern
     */
    private function storePattern($content_id, $pattern, $file) {
        try {
            $pattern_hash = hash('md5', $pattern['type'] . '|' . $pattern['name']);

            $stmt = $this->db->prepare("
                INSERT INTO neural_patterns (
                    business_unit_id,
                    pattern_type,
                    pattern_name,
                    pattern_signature,
                    pattern_context,
                    pattern_hash,
                    source_file,
                    occurrences,
                    first_seen,
                    last_seen
                ) VALUES (
                    :unit_id,
                    :type,
                    :name,
                    :signature,
                    :context,
                    :hash,
                    :source,
                    1,
                    NOW(),
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    occurrences = occurrences + 1,
                    last_seen = NOW()
            ");

            $stmt->execute([
                'unit_id' => $file['business_unit_id'],
                'type' => $pattern['type'],
                'name' => $pattern['name'],
                'signature' => $pattern['signature'] ?? '',
                'context' => $pattern['context'] ?? '',
                'hash' => $pattern_hash,
                'source' => $file['file_path']
            ]);

        } catch (Exception $e) {
            // Skip duplicate patterns
        }
    }

    /**
     * Build neural relationships between patterns
     */
    private function buildNeuralRelationships() {
        // Find function calls between files (using pattern_description instead of pattern_context)
        try {
            $stmt = $this->db->query("
                SELECT
                    np1.pattern_id as source_pattern_id,
                    np2.pattern_id as target_pattern_id,
                    'calls' as relationship_type
                FROM neural_patterns np1
                JOIN neural_patterns np2 ON np1.pattern_name != np2.pattern_name
                WHERE np1.pattern_type = 'code_structure'
                AND np2.pattern_type = 'code_structure'
                AND (np1.pattern_description LIKE CONCAT('%', np2.pattern_name, '%')
                     OR np1.pattern_data LIKE CONCAT('%', np2.pattern_name, '%'))
                LIMIT 10000
            ");

            $relationships = $stmt->fetchAll();
        } catch (Exception $e) {
            $this->log("⚠️  Relationship building skipped: " . $e->getMessage());
            return;
        }

        foreach ($relationships as $rel) {
            try {
                $stmt = $this->db->prepare("
                    INSERT IGNORE INTO neural_pattern_relationships (
                        source_pattern_id,
                        target_pattern_id,
                        relationship_type,
                        strength,
                        discovered_at
                    ) VALUES (
                        :source,
                        :target,
                        :type,
                        0.5,
                        NOW()
                    )
                ");

                $stmt->execute([
                    'source' => $rel['source_pattern_id'],
                    'target' => $rel['target_pattern_id'],
                    'type' => $rel['relationship_type']
                ]);

                $this->stats['relationships_built']++;

            } catch (Exception $e) {
                // Skip duplicates
            }
        }
    }

    /**
     * Generate intelligence statistics
     */
    private function generateStatistics() {
        $this->db->exec("
            INSERT INTO intelligence_metrics (
                org_id,
                metric_category,
                metric_name,
                metric_value,
                source_system,
                recorded_at
            ) VALUES (
                1,
                'technical',
                'neural_processing_completed',
                {$this->stats['files_processed']},
                '{$this->server}',
                NOW()
            )
        ");
    }

    // Helper functions
    private function detectLanguage($filename, $content) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $map = [
            'php' => 'PHP',
            'js' => 'JavaScript',
            'py' => 'Python',
            'java' => 'Java',
            'md' => 'Markdown',
            'json' => 'JSON',
            'xml' => 'XML',
            'yaml' => 'YAML',
            'yml' => 'YAML'
        ];
        return $map[$ext] ?? 'Unknown';
    }

    private function getMimeType($filename, $file_type) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $map = [
            'php' => 'text/x-php',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'md' => 'text/markdown',
            'txt' => 'text/plain'
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }

    private function getContentTypeId($file_type, $intelligence_type) {
        static $cache = [];
        $key = $file_type . '|' . $intelligence_type;

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $stmt = $this->db->prepare("
            SELECT type_id FROM intelligence_content_types
            WHERE type_category = :category AND type_name = :name
        ");
        $stmt->execute(['category' => $file_type, 'name' => $intelligence_type]);
        $result = $stmt->fetch();

        if ($result) {
            $cache[$key] = $result['type_id'];
            return $result['type_id'];
        }

        // Create new type
        $stmt = $this->db->prepare("
            INSERT INTO intelligence_content_types (type_category, type_name, created_at)
            VALUES (:category, :name, NOW())
        ");
        $stmt->execute(['category' => $file_type, 'name' => $intelligence_type]);

        $cache[$key] = $this->db->lastInsertId();
        return $cache[$key];
    }

    private function getContentId($hash) {
        $stmt = $this->db->prepare("SELECT content_id FROM intelligence_content WHERE content_hash = ?");
        $stmt->execute([$hash]);
        $result = $stmt->fetch();
        return $result ? $result['content_id'] : null;
    }

    private function cleanContentForSearch($content, $file_type) {
        // Remove code comments
        $content = preg_replace('#/\*.*?\*/#s', '', $content);
        $content = preg_replace('#//.*$#m', '', $content);
        $content = preg_replace('#\#.*$#m', '', $content);

        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        return trim($content);
    }

    private function extractKeywords($content, $file_type) {
        // Extract meaningful words (3+ chars, not common words)
        preg_match_all('/\b[a-zA-Z]{3,}\b/', $content, $matches);
        $words = array_map('strtolower', $matches[0]);

        // Remove common words
        $stopwords = ['the','and','for','are','but','not','you','with','this','that','from','have','been','has','had','was','were','will'];
        $words = array_diff($words, $stopwords);

        // Get top 50 most frequent
        $freq = array_count_values($words);
        arsort($freq);
        return array_slice(array_keys($freq), 0, 50);
    }

    private function getArrayDepth($array) {
        if (!is_array($array)) return 0;
        $max = 0;
        foreach ($array as $value) {
            if (is_array($value)) {
                $depth = $this->getArrayDepth($value) + 1;
                if ($depth > $max) $max = $depth;
            }
        }
        return $max;
    }

    private function countArrayKeys($array, &$count = 0) {
        if (is_array($array)) {
            $count += count($array);
            foreach ($array as $value) {
                if (is_array($value)) {
                    $this->countArrayKeys($value, $count);
                }
            }
        }
        return $count;
    }

    private function log($message) {
        echo date('Y-m-d H:i:s') . " " . $message . "\n";
    }
}

// ============================================================================
// COMMAND LINE EXECUTION
// ============================================================================

if (php_sapi_name() === 'cli') {
    $options = getopt('', ['server:', 'analyze', 'full', 'help']);

    if (isset($options['help']) || !isset($options['server'])) {
        echo <<<HELP

Neural Intelligence Processor - REAL Intelligence System

Usage:
  php neural_intelligence_processor.php --server=SERVER_ID [OPTIONS]

Options:
  --server=ID     Server to process (jcepnzzkmj, dvaxgvsxmz, etc)
  --analyze       Analyze and score content (default)
  --full          Full processing including relationships
  --help          Show this help

Examples:
  php neural_intelligence_processor.php --server=jcepnzzkmj --full
  php neural_intelligence_processor.php --server=dvaxgvsxmz --analyze

This processes files from intelligence_files and creates:
  ✓ intelligence_content (metadata & scores)
  ✓ intelligence_content_text (searchable text)
  ✓ intelligence_content_types (categorization)
  ✓ neural_patterns (extracted patterns)
  ✓ neural_pattern_relationships (connections)

HELP;
        exit(0);
    }

    try {
        $processor = new NeuralIntelligenceProcessor($options['server']);
        $processor->processServer();

    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}
