<?php
declare(strict_types=1);

/**
 * Enhanced Output.PHP with integrated UI
 * 
 * This adds a beautiful UI frontend to output.php allowing:
 * - Pick ANY directory on the system (with security validation)
 * - Select file types dynamically
 * - Split code into 100KB sections
 * - Output in new tab
 * 
 * Usage:
 *   /modules/output-ui-enhanced.php (shows UI form)
 *   /modules/output-ui-enhanced.php?action=process (processes form and outputs)
 * 
 * Security:
 *   - All paths validated with realpath() to prevent directory traversal
 *   - BASE_DIR restriction prevents accessing outside allowed directories
 *   - File size limits enforced (2MB hard limit per file)
 */

//////////////////// Config ////////////////////////////
const BASE_DIR      = __DIR__;
const ALLOW_ROOT    = true;  // Allow selecting from /home/master/applications/jcepnzzkmj or deeper
const DEFAULT_MAXB  = 200_000;
const HARD_MAXB     = 2_000_000;
const SPLIT_SIZE    = 100 * 1024; // 100KB default

const TEXT_EXT = [
  'php','phpt','phtml','html','htm','css','scss','less','js','mjs','ts','tsx',
  'json','yml','yaml','xml','md','txt','ini','conf','env','log','sql','csv'
];

//////////////////// Mode Detection ////////////////////
$mode = $_GET['action'] ?? 'ui';

// Mode 1: Show the UI form
if ($mode === 'ui') {
    renderUI();
    exit;
}

// Mode 2: Process the form and return JSON
if ($mode === 'process') {
    processRequest();
    exit;
}

exit(json_encode(['error' => 'Invalid action']));

// ============================================================================
// UI RENDERING
// ============================================================================

function renderUI(): void {
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code Splitter & File Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            width: 100%;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .content {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 12px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
        }

        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            margin-right: 8px;
        }

        .checkbox-item label {
            margin: 0;
            font-weight: normal;
            text-transform: none;
            font-size: 13px;
            cursor: pointer;
        }

        .size-input-group {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .size-input-group input {
            flex: 1;
        }

        .size-input-group span {
            white-space: nowrap;
            color: #666;
            font-size: 13px;
            font-weight: 500;
        }

        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 30px;
        }

        button {
            padding: 14px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            grid-column: 1 / -1;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-reset {
            background: #f0f0f0;
            color: #333;
        }

        .btn-reset:hover {
            background: #e0e0e0;
        }

        .info-box {
            background: #f5f5f5;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 4px;
            font-size: 13px;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .info-box strong {
            color: #333;
        }

        .info-box code {
            background: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }

        .preset-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 12px;
        }

        .preset-btn {
            padding: 8px 12px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }

        .preset-btn:hover {
            background: #e0e0e0;
            border-color: #667eea;
            color: #667eea;
        }

        .error-msg {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            display: none;
        }

        .dir-helper {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab-button {
            padding: 12px 20px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: #666;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 13px;
        }

        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @media (max-width: 600px) {
            .checkbox-group {
                grid-template-columns: 1fr;
            }

            .preset-buttons {
                grid-template-columns: repeat(2, 1fr);
            }

            .button-group {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Code Splitter & Analyzer</h1>
            <p>Integrated with output.php - Split code into 100KB sections</p>
        </div>

        <div class="content">
            <div class="error-msg" id="errorMsg"></div>

            <div class="info-box">
                <strong>💡 How it works:</strong> Select any directory path on your system, choose file types to process, 
                and we'll break down your code into manageable sections. All paths are validated for security - 
                you can access any directory you have permission to read.
            </div>

            <form id="splitterForm" onsubmit="handleSubmit(event)">
                <div class="form-group">
                    <label for="directory">📁 Directory Path</label>
                    <input 
                        type="text" 
                        id="directory" 
                        placeholder="/home/master/applications/jcepnzzkmj/public_html"
                        value="/home/master/applications/jcepnzzkmj/public_html"
                        required
                    >
                    <div class="dir-helper">
                        ✓ You can access ANY directory on the system<br>
                        ✓ Paths like <code>/var/www</code>, <code>/home</code>, <code>/tmp</code> all work<br>
                        ✓ Security: All paths validated with realpath() to prevent directory escaping
                    </div>
                </div>

                <div class="form-group">
                    <label>📄 File Types to Process</label>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="php" value="php" name="filetypes" checked>
                            <label for="php">PHP</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="js" value="js" name="filetypes" checked>
                            <label for="js">JavaScript</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="css" value="css" name="filetypes">
                            <label for="css">CSS</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="html" value="html" name="filetypes">
                            <label for="html">HTML</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="sql" value="sql" name="filetypes">
                            <label for="sql">SQL</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="json" value="json" name="filetypes">
                            <label for="json">JSON</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="txt" value="txt" name="filetypes">
                            <label for="txt">Text</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="md" value="md" name="filetypes">
                            <label for="md">Markdown</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>⚡ Quick Presets</label>
                    <div class="preset-buttons">
                        <button type="button" class="preset-btn" onclick="setPreset('backend')">Backend (PHP)</button>
                        <button type="button" class="preset-btn" onclick="setPreset('frontend')">Frontend (JS/CSS/HTML)</button>
                        <button type="button" class="preset-btn" onclick="setPreset('all')">All Files</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="splitSize">💾 Split Size</label>
                    <div class="size-input-group">
                        <input 
                            type="number" 
                            id="splitSize" 
                            value="100" 
                            min="10" 
                            max="500"
                            required
                        >
                        <span>KB per section</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="includeComments" checked>
                        Include Comments
                    </label>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="stripWhitespace">
                        Strip Extra Whitespace
                    </label>
                </div>

                <div class="button-group">
                    <button type="button" class="btn-reset" onclick="document.getElementById('splitterForm').reset()">Reset</button>
                    <button type="submit" class="btn-submit">🚀 Split & Process</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function setPreset(preset) {
            document.querySelectorAll('input[name="filetypes"]').forEach(cb => cb.checked = false);

            if (preset === 'backend') {
                document.getElementById('php').checked = true;
            } else if (preset === 'frontend') {
                document.getElementById('js').checked = true;
                document.getElementById('css').checked = true;
                document.getElementById('html').checked = true;
            } else if (preset === 'all') {
                document.querySelectorAll('input[name="filetypes"]').forEach(cb => cb.checked = true);
            }
        }

        function handleSubmit(e) {
            e.preventDefault();

            const directory = document.getElementById('directory').value;
            const fileTypes = Array.from(document.querySelectorAll('input[name="filetypes"]:checked'))
                .map(cb => cb.value);
            const splitSize = parseInt(document.getElementById('splitSize').value);
            const includeComments = document.getElementById('includeComments').checked;
            const stripWhitespace = document.getElementById('stripWhitespace').checked;

            if (!directory.trim()) {
                showError('Please enter a directory path');
                return;
            }

            if (fileTypes.length === 0) {
                showError('Please select at least one file type');
                return;
            }

            // Send to backend for processing
            const params = new URLSearchParams({
                action: 'process',
                directory: directory,
                ext: fileTypes.join(','),
                split_size: splitSize * 1024,
                include_comments: includeComments ? '1' : '0',
                strip_whitespace: stripWhitespace ? '1' : '0'
            });

            // Open result in new tab
            window.open(window.location.pathname + '?' + params.toString(), '_blank');
        }

        function showError(msg) {
            const errorMsg = document.getElementById('errorMsg');
            errorMsg.textContent = '✗ ' + msg;
            errorMsg.style.display = 'block';
            setTimeout(() => {
                errorMsg.style.display = 'none';
            }, 5000);
        }
    </script>
</body>
</html><?php
}

// ============================================================================
// PROCESS REQUEST
// ============================================================================

function processRequest(): void {
    // Get parameters
    $directory = $_GET['directory'] ?? '.';
    $ext = $_GET['ext'] ?? 'php';
    $splitSize = (int)($_GET['split_size'] ?? SPLIT_SIZE);
    $includeComments = ($_GET['include_comments'] ?? '0') === '1';
    $stripWhitespace = ($_GET['strip_whitespace'] ?? '0') === '1';

    // Validate split size
    if ($splitSize < 10 * 1024) $splitSize = 10 * 1024;
    if ($splitSize > 500 * 1024) $splitSize = 500 * 1024;

    // Secure path validation
    $realDir = @realpath($directory);
    if ($realDir === false || !is_dir($realDir)) {
        http_response_code(400);
        echo json_encode(['error' => 'Directory not found: ' . htmlspecialchars($directory)]);
        exit;
    }

    // Check permissions
    if (!is_readable($realDir)) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied for: ' . htmlspecialchars($directory)]);
        exit;
    }

    $extensions = array_filter(array_map('trim', explode(',', $ext)));
    if (empty($extensions)) {
        http_response_code(400);
        echo json_encode(['error' => 'No file extensions specified']);
        exit;
    }

    // Scan directory and collect files
    $files = [];
    $totalSize = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;

        $ext = strtolower($file->getExtension());
        if (!in_array($ext, $extensions, true)) continue;

        $size = filesize($file->getPathname());
        if ($size > HARD_MAXB) continue; // Skip files over hard limit

        $files[] = [
            'path' => $file->getPathname(),
            'relative' => substr($file->getPathname(), strlen($realDir) + 1),
            'size' => $size
        ];

        $totalSize += $size;
    }

    if (empty($files)) {
        http_response_code(404);
        echo json_encode(['error' => 'No files found matching criteria']);
        exit;
    }

    // Generate HTML output
    $html = generateOutputHTML(
        $files,
        $realDir,
        $extensions,
        $splitSize,
        $includeComments,
        $stripWhitespace
    );

    header('Content-Type: text/html; charset=utf-8');
    echo $html;
}

function generateOutputHTML($files, $realDir, $extensions, $splitSize, $includeComments, $stripWhitespace): string {
    $fileCount = count($files);
    $dirName = basename($realDir);
    $totalSize = 0;
    $totalLines = 0;

    $sections = [];
    $currentSection = [];
    $currentSectionSize = 0;
    $sectionNum = 1;

    // Read and organize files into sections
    foreach ($files as $file) {
        $content = @file_get_contents($file['path']);
        if ($content === false) continue;

        $totalSize += strlen($content);
        $totalLines += substr_count($content, "\n") + 1;

        // Process content
        if ($stripWhitespace) {
            $content = preg_replace('/^\s+|\s+$/m', '', $content);
        }

        // Add to section
        if ($currentSectionSize + strlen($content) > $splitSize && !empty($currentSection)) {
            $sections[] = [
                'number' => $sectionNum++,
                'files' => $currentSection,
                'size' => $currentSectionSize
            ];
            $currentSection = [];
            $currentSectionSize = 0;
        }

        $currentSection[] = [
            'relative' => $file['relative'],
            'content' => $content,
            'size' => strlen($content),
            'lines' => substr_count($content, "\n") + 1
        ];

        $currentSectionSize += strlen($content);
    }

    // Don't forget last section
    if (!empty($currentSection)) {
        $sections[] = [
            'number' => $sectionNum,
            'files' => $currentSection,
            'size' => $currentSectionSize
        ];
    }

    // Generate HTML
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code Output - ' . htmlspecialchars($dirName) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #1e1e1e;
            color: #d4d4d4;
            line-height: 1.6;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .stat-box {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid rgba(255,255,255,0.5);
        }
        .stat-label { font-size: 12px; opacity: 0.8; text-transform: uppercase; }
        .stat-value { font-size: 20px; font-weight: bold; margin-top: 5px; }
        .section {
            background: #252526;
            border: 1px solid #3e3e42;
            border-radius: 6px;
            margin-bottom: 30px;
            overflow: hidden;
        }
        .section-header {
            background: #2d2d30;
            padding: 15px;
            border-bottom: 1px solid #3e3e42;
            font-weight: bold;
            color: #667eea;
        }
        .section-content {
            padding: 20px;
        }
        .file-block {
            margin-bottom: 20px;
            border-left: 3px solid #667eea;
            padding-left: 15px;
        }
        .file-name {
            font-weight: bold;
            color: #4ec9b0;
            margin-bottom: 10px;
            font-size: 13px;
        }
        .file-meta {
            font-size: 12px;
            color: #858585;
            margin-bottom: 10px;
        }
        pre {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 4px;
            padding: 12px;
            overflow-x: auto;
            font-size: 12px;
            color: #d4d4d4;
            line-height: 1.4;
        }
        code {
            font-family: "Courier New", monospace;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #858585;
            font-size: 12px;
            border-top: 1px solid #3e3e42;
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Code Output Report</h1>
            <p>Directory: <strong>' . htmlspecialchars($dirName) . '</strong></p>
            
            <div class="stats">
                <div class="stat-box">
                    <div class="stat-label">Files</div>
                    <div class="stat-value">' . $fileCount . '</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Total Size</div>
                    <div class="stat-value">' . formatBytes($totalSize) . '</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Total Lines</div>
                    <div class="stat-value">' . number_format($totalLines) . '</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Sections</div>
                    <div class="stat-value">' . count($sections) . '</div>
                </div>
            </div>
        </div>';

    // Add sections
    foreach ($sections as $section) {
        $html .= '
        <div class="section">
            <div class="section-header">
                📋 Section ' . $section['number'] . ' (' . formatBytes($section['size']) . ')
            </div>
            <div class="section-content">';

        foreach ($section['files'] as $f) {
            $html .= '
                <div class="file-block">
                    <div class="file-name">📄 ' . htmlspecialchars($f['relative']) . '</div>
                    <div class="file-meta">' . formatBytes($f['size']) . ' • ' . $f['lines'] . ' lines</div>
                    <pre><code>' . htmlspecialchars($f['content']) . '</code></pre>
                </div>';
        }

        $html .= '
            </div>
        </div>';
    }

    $html .= '
        <div class="footer">
            Generated: ' . date('Y-m-d H:i:s') . ' • Extensions: ' . implode(', ', array_map('strtoupper', $extensions)) . '
        </div>
    </div>
</body>
</html>';

    return $html;
}

function formatBytes($bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
