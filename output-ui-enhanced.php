<?php
declare(strict_types=1);

/**
 * Code Splitter with Visual Folder/File Browser
 *
 * Features:
 * - Visual folder tree browser to select directories
 * - Multi-select files within folders
 * - Split code into 100KB sections
 * - Beautiful dark-theme UI
 * - Security-hardened path handling
 */

const BASE_DIR = '/home/master/applications/jcepnzzkmj';
const ALLOW_ROOT = true;
const SPLIT_SIZE = 100 * 1024;
const TEXT_EXT = ['php','js','css','html','json','sql','txt','md'];

$mode = $_GET['action'] ?? 'ui';

if ($mode === 'ui') {
    renderUI();
    exit;
}
if ($mode === 'process') {
    processRequest();
    exit;
}
if ($mode === 'tree') {
    getTreeData();
    exit;
}

exit(json_encode(['error' => 'Invalid action']));

// ============================================================================
// VISUAL FOLDER BROWSER UI
// ============================================================================

function renderUI(): void {
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>📁 Code Splitter - Visual Browser</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .content {
            display: grid;
            grid-template-columns: 350px 1fr;
            min-height: 600px;
        }

        /* Left Side: Folder Browser */
        .sidebar {
            background: #f8f9fa;
            border-right: 1px solid #e0e0e0;
            overflow-y: auto;
            max-height: 600px;
        }

        .sidebar-header {
            padding: 15px;
            background: #2c3e50;
            color: white;
            font-weight: bold;
            font-size: 13px;
        }

        .tree {
            padding: 10px 0;
        }

        .tree-item {
            padding: 8px 15px;
            cursor: pointer;
            user-select: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            transition: background 0.2s;
        }

        .tree-item:hover {
            background: #e8eef7;
        }

        .tree-item.selected {
            background: #3498db;
            color: white;
        }

        .tree-item .icon {
            font-size: 14px;
            min-width: 16px;
        }

        .tree-item .toggle {
            cursor: pointer;
            min-width: 14px;
            text-align: center;
        }

        .tree-children {
            display: none;
        }

        .tree-children.open {
            display: block;
        }

        .tree-item-indent {
            padding-left: calc(20px + 15px);
        }

        /* Right Side: File Selection & Options */
        .main {
            padding: 25px;
            display: flex;
            flex-direction: column;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .path-display {
            background: #2c3e50;
            color: white;
            padding: 12px 15px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
            min-height: 40px;
            display: flex;
            align-items: center;
        }

        .path-display.empty {
            color: #95a5a6;
            font-style: italic;
        }

        .file-types {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 13px;
        }

        .checkbox:hover {
            background: #e8eef7;
        }

        .checkbox input {
            cursor: pointer;
        }

        .presets {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
        }

        .preset-btn {
            padding: 8px 12px;
            background: #ecf0f1;
            border: 1px solid #bdc3c7;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: all 0.2s;
        }

        .preset-btn:hover {
            background: #3498db;
            color: white;
            border-color: #2980b9;
        }

        .split-size {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .split-size input {
            width: 120px;
            padding: 8px;
            border: 1px solid #bdc3c7;
            border-radius: 5px;
            font-size: 13px;
        }

        .split-size-display {
            background: #ecf0f1;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 12px;
            min-width: 100px;
        }

        .file-count {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 5px;
            font-size: 13px;
            color: #555;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-primary:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .loading {
            display: none;
            text-align: center;
            color: #555;
        }

        .loading.show {
            display: block;
        }

        @media (max-width: 1024px) {
            .content {
                grid-template-columns: 1fr;
            }
            .sidebar {
                max-height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📁 Code Splitter</h1>
            <p>Visual folder browser • Multi-file selection • 100KB sections</p>
        </div>

        <div class="content">
            <!-- LEFT: Folder Browser -->
            <div class="sidebar">
                <div class="sidebar-header">📂 BROWSE FOLDERS</div>
                <div class="tree" id="folderTree"></div>
            </div>

            <!-- RIGHT: Selection & Options -->
            <div class="main">
                <!-- Selected Path -->
                <div class="section">
                    <div class="section-title">📍 Selected Directory</div>
                    <div class="path-display empty" id="pathDisplay">
                        Click a folder to select
                    </div>
                </div>

                <!-- File Types -->
                <div class="section">
                    <div class="section-title">📄 File Types</div>
                    <div class="presets">
                        <button class="preset-btn" onclick="setPreset('backend')">💻 Backend (PHP)</button>
                        <button class="preset-btn" onclick="setPreset('frontend')">🎨 Frontend (JS/CSS)</button>
                        <button class="preset-btn" onclick="setPreset('all')">📦 All Types</button>
                        <button class="preset-btn" onclick="setPreset('none')">❌ None</button>
                    </div>
                    <div class="file-types" id="fileTypes"></div>
                </div>

                <!-- Split Size -->
                <div class="section">
                    <div class="section-title">✂️ Split Size</div>
                    <div class="split-size">
                        <input type="range" id="splitSize" min="10" max="500" value="100" step="10">
                        <div class="split-size-display">
                            <span id="splitSizeValue">100</span> KB
                        </div>
                    </div>
                </div>

                <!-- File Count -->
                <div class="section">
                    <div class="file-count" id="fileCount">
                        📊 Select a folder to scan files
                    </div>
                </div>

                <!-- Actions -->
                <div class="actions">
                    <button class="btn btn-secondary" onclick="location.reload()">↻ Reset</button>
                    <button class="btn btn-primary" id="processBtn" onclick="processSplit()" disabled>
                        🚀 Split Code
                    </button>
                </div>

                <div class="loading" id="loading">⏳ Processing...</div>
            </div>
        </div>
    </div>

    <script>
        // Configuration from PHP
        const TEXT_EXT = <?php echo json_encode(TEXT_EXT); ?>;
        const SPLIT_SIZE = <?php echo SPLIT_SIZE; ?>;
        const BASE_DIR = "<?php echo BASE_DIR; ?>";

        // State
        let selectedPath = null;
        const selectedExtensions = new Set();

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadFolderTree();
            initializeFileTypes();
            updateSplitSizeDisplay();
        });

        // Load folder tree
        function loadFolderTree() {
            fetch('?action=tree')
                .then(r => r.json())
                .then(data => {
                    if (data.error) return console.error(data.error);
                    renderTree(data.tree, document.getElementById('folderTree'), 0);
                })
                .catch(e => console.error('Tree load error:', e));
        }

        // Render tree recursively
        function renderTree(items, container, depth) {
            items.forEach(item => {
                const div = document.createElement('div');
                const hasChildren = item.children && item.children.length > 0;

                div.className = 'tree-item' + (depth > 0 ? ' tree-item-indent' : '');
                div.style.paddingLeft = (depth * 16 + 15) + 'px';

                let toggleHtml = '';
                if (hasChildren) {
                    toggleHtml = '<span class="toggle" onclick="event.stopPropagation(); toggleFolder(this)">▶</span>';
                } else {
                    toggleHtml = '<span class="toggle">  </span>';
                }

                const icon = item.type === 'dir' ? '📁' : '📄';
                div.innerHTML = toggleHtml + `<span class="icon">${icon}</span><span>${item.name}</span>`;

                if (item.type === 'dir') {
                    div.onclick = () => selectFolder(item.path);
                }

                container.appendChild(div);

                if (hasChildren) {
                    const childContainer = document.createElement('div');
                    childContainer.className = 'tree-children';
                    childContainer.style.paddingLeft = '16px';
                    container.appendChild(childContainer);

                    div.dataset.childContainer = 'true';
                    div.childContainer = childContainer;
                }
            });
        }

        // Toggle folder expansion
        function toggleFolder(toggle) {
            const item = toggle.parentElement;
            const childContainer = item.childContainer;
            if (!childContainer) return;

            childContainer.classList.toggle('open');
            toggle.textContent = childContainer.classList.contains('open') ? '▼' : '▶';
        }

        // Select folder
        function selectFolder(path) {
            selectedPath = path;
            document.getElementById('pathDisplay').textContent = path;
            document.getElementById('pathDisplay').classList.remove('empty');

            // Highlight
            document.querySelectorAll('.tree-item.selected').forEach(el => {
                el.classList.remove('selected');
            });
            event.target.closest('.tree-item').classList.add('selected');

            // Scan files
            scanFiles(path);
        }

        // Initialize file type checkboxes
        function initializeFileTypes() {
            const container = document.getElementById('fileTypes');
            const types = TEXT_EXT;

            types.forEach(ext => {
                const checkbox = document.createElement('label');
                checkbox.className = 'checkbox';
                checkbox.innerHTML = `
                    <input type="checkbox" value="${ext}" onchange="updateExtensions()">
                    ${ext.toUpperCase()}
                `;
                container.appendChild(checkbox);
            });
        }

        // Update selected extensions
        function updateExtensions() {
            selectedExtensions.clear();
            document.querySelectorAll('#fileTypes input:checked').forEach(input => {
                selectedExtensions.add(input.value);
            });
        }

        // Set preset
        function setPreset(type) {
            const checkboxes = document.querySelectorAll('#fileTypes input');

            if (type === 'backend') {
                checkboxes.forEach(cb => cb.checked = ['php', 'phpt', 'phtml'].includes(cb.value));
            } else if (type === 'frontend') {
                checkboxes.forEach(cb => cb.checked = ['js', 'css', 'html'].includes(cb.value));
            } else if (type === 'all') {
                checkboxes.forEach(cb => cb.checked = true);
            } else if (type === 'none') {
                checkboxes.forEach(cb => cb.checked = false);
            }

            updateExtensions();
        }

        // Update split size display
        document.getElementById('splitSize').addEventListener('input', (e) => {
            document.getElementById('splitSizeValue').textContent = e.target.value;
        });

        function updateSplitSizeDisplay() {
            const val = document.getElementById('splitSize').value;
            document.getElementById('splitSizeValue').textContent = val;
        }

        // Scan files in directory
        function scanFiles(path) {
            const formData = new FormData();
            formData.append('directory', path);

            fetch('?action=process', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.files) {
                        document.getElementById('fileCount').innerHTML =
                            `📊 ${data.files.length} files found | ${formatBytes(data.totalSize)} total`;
                        document.getElementById('processBtn').disabled = false;
                    }
                })
                .catch(e => console.error('Scan error:', e));
        }

        // Process and split
        function processSplit() {
            if (!selectedPath || selectedExtensions.size === 0) {
                alert('Please select a directory and at least one file type');
                return;
            }

            const formData = new FormData();
            formData.append('directory', selectedPath);
            formData.append('extensions', Array.from(selectedExtensions).join(','));
            formData.append('split_size', document.getElementById('splitSize').value * 1024);

            document.getElementById('loading').classList.add('show');

            fetch('?action=process', {
                method: 'POST',
                body: formData
            })
                .then(r => r.text())
                .then(html => {
                    const tab = window.open();
                    tab.document.write(html);
                    tab.document.close();
                    document.getElementById('loading').classList.remove('show');
                })
                .catch(e => {
                    alert('Error: ' + e.message);
                    document.getElementById('loading').classList.remove('show');
                });
        }

        // Utility
        function formatBytes(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let size = bytes;
            let unitIdx = 0;
            while (size >= 1024 && unitIdx < units.length - 1) {
                size /= 1024;
                unitIdx++;
            }
            return size.toFixed(1) + ' ' + units[unitIdx];
        }
    </script>
</body>
</html><?php
}

// ============================================================================
// API: GET FOLDER TREE
// ============================================================================

function getTreeData(): void {
    $root = BASE_DIR;
    $root = @realpath($root);

    if (!$root || !is_dir($root)) {
        exit(json_encode(['error' => 'Invalid root']));
    }

    $tree = buildTree($root, 0, 3); // 3 levels deep
    header('Content-Type: application/json');
    echo json_encode(['tree' => $tree]);
}

function buildTree(string $dir, int $level, int $maxLevel): array {
    $items = [];

    if ($level > $maxLevel) return $items;

    try {
        $files = @scandir($dir);
        if (!$files) return $items;

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $path = $dir . '/' . $file;
            $real = @realpath($path);

            if (!$real || !is_readable($real)) continue;

            $isDir = is_dir($real);
            $item = [
                'name' => $file,
                'path' => $real,
                'type' => $isDir ? 'dir' : 'file',
            ];

            if ($isDir && $level < $maxLevel) {
                $item['children'] = buildTree($real, $level + 1, $maxLevel);
            }

            $items[] = $item;
        }

        usort($items, function ($a, $b) {
            if ($a['type'] === $b['type']) return strcmp($a['name'], $b['name']);
            return $a['type'] === 'dir' ? -1 : 1;
        });

    } catch (Exception $e) {
        // Silent fail
    }

    return $items;
}

// ============================================================================
// PROCESS REQUEST
// ============================================================================

function processRequest(): void {
    $dir = $_POST['directory'] ?? '';
    $exts = array_filter(explode(',', $_POST['extensions'] ?? ''));
    $splitSize = (int)($_POST['split_size'] ?? SPLIT_SIZE);

    // Validate
    $real = @realpath($dir);
    if (!$real || !is_dir($real) || !is_readable($real)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid directory']));
    }

    // Scan files
    $files = scanDirectory($real, $exts);

    if (empty($files)) {
        exit(json_encode(['error' => 'No files found']));
    }

    // Prepare response based on content-type
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($accept, 'application/json') !== false) {
        // API response - just return file list
        $totalSize = array_reduce($files, fn($sum, $f) => $sum + $f['size'], 0);
        exit(json_encode([
            'files' => $files,
            'totalSize' => $totalSize,
            'count' => count($files)
        ]));
    }

    // HTML response - generate full output
    $sections = splitFiles($files, $splitSize);
    generateOutput($sections, $real);
}

function scanDirectory(string $dir, array $extensions): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || !$file->isReadable()) continue;

        $ext = strtolower($file->getExtension());
        if (!in_array($ext, $extensions, true)) continue;

        $size = $file->getSize();
        if ($size > HARD_MAXB) continue;

        $files[] = [
            'path' => $file->getPathname(),
            'name' => $file->getFilename(),
            'size' => $size,
        ];
    }

    usort($files, fn($a, $b) => strcmp($a['path'], $b['path']));
    return $files;
}

function splitFiles(array $files, int $splitSize): array {
    $sections = [];
    $current = [];
    $currentSize = 0;
    $sectionNum = 1;

    foreach ($files as $file) {
        $content = @file_get_contents($file['path']);
        if ($content === false) continue;

        $contentSize = strlen($content);

        if ($currentSize > 0 && $currentSize + $contentSize > $splitSize) {
            $sections[] = [
                'number' => $sectionNum++,
                'files' => $current,
                'size' => $currentSize,
            ];
            $current = [];
            $currentSize = 0;
        }

        $current[] = [
            'name' => $file['name'],
            'path' => $file['path'],
            'content' => $content,
            'size' => $contentSize,
        ];
        $currentSize += $contentSize;
    }

    if (!empty($current)) {
        $sections[] = [
            'number' => $sectionNum,
            'files' => $current,
            'size' => $currentSize,
        ];
    }

    return $sections;
}

function generateOutput(array $sections, string $baseDir): void {
    $totalFiles = array_reduce($sections, fn($sum, $s) => $sum + count($s['files']), 0);
    $totalSize = array_reduce($sections, fn($sum, $s) => $sum + $s['size'], 0);
    $totalLines = 0;
    foreach ($sections as $section) {
        foreach ($section['files'] as $file) {
            $totalLines += substr_count($file['content'], "\n") + 1;
        }
    }

    ?><!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Code Output Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Monaco', 'Courier New', monospace; background: #1e1e1e; color: #d4d4d4; line-height: 1.5; }
        .container { max-width: 100%; padding: 20px; }
        .header { background: #2d2d2d; padding: 20px; border-radius: 5px; margin-bottom: 30px; }
        .header h1 { color: #4ec9b0; margin-bottom: 10px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
        .stat { background: #1e1e1e; padding: 10px; border-left: 3px solid #4ec9b0; }
        .stat-value { color: #4ec9b0; font-size: 18px; font-weight: bold; }
        .stat-label { color: #858585; font-size: 12px; margin-top: 5px; }
        .section { margin-bottom: 40px; }
        .section-header { background: #2d2d2d; padding: 15px; border-radius: 5px; margin-bottom: 15px; color: #4ec9b0; }
        .file { background: #252526; padding: 15px; margin-bottom: 15px; border-left: 3px solid #0e639c; border-radius: 3px; }
        .file-name { color: #ce9178; margin-bottom: 10px; font-weight: bold; }
        .file-meta { color: #858585; font-size: 12px; margin-bottom: 10px; }
        .code { background: #1e1e1e; padding: 15px; border-radius: 3px; overflow-x: auto; }
        pre { margin: 0; }
        @media print { body { background: white; color: black; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Code Output Report</h1>
            <p>Generated: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p>Directory: <code><?php echo htmlspecialchars($baseDir); ?></code></p>
            <div class="stats">
                <div class="stat">
                    <div class="stat-value"><?php echo $totalFiles; ?></div>
                    <div class="stat-label">Total Files</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo number_format($totalSize / 1024 / 1024, 2); ?> MB</div>
                    <div class="stat-label">Total Size</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo number_format($totalLines); ?></div>
                    <div class="stat-label">Total Lines</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo count($sections); ?></div>
                    <div class="stat-label">Sections</div>
                </div>
            </div>
        </div>

        <?php foreach ($sections as $section): ?>
            <div class="section">
                <div class="section-header">
                    📦 Section <?php echo $section['number']; ?>
                    (<?php echo number_format($section['size'] / 1024, 1); ?> KB)
                </div>
                <?php foreach ($section['files'] as $file): ?>
                    <div class="file">
                        <div class="file-name">
                            📄 <?php echo htmlspecialchars($file['name']); ?>
                        </div>
                        <div class="file-meta">
                            Size: <?php echo number_format($file['size'] / 1024, 1); ?> KB
                            | Lines: <?php echo number_format(substr_count($file['content'], "\n") + 1); ?>
                        </div>
                        <div class="code">
                            <pre><?php echo htmlspecialchars($file['content']); ?></pre>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
<?php
}
