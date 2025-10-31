<?php
/**
 * CSS & Theme Version Control System
 *
 * Features:
 * - Git-style version control for CSS files
 * - Rollback to any previous version
 * - Diff viewer
 * - Component library management
 * - Strict CSS architecture enforcement
 *
 * Architecture:
 * - /css/core/          - Bare minimum (locked)
 * - /css/dependencies/  - Bootstrap, FA, etc. (external)
 * - /css/custom/        - All customizations (version controlled)
 * - /components/        - HTML component blocks
 *
 * Version: 1.0.0
 */

session_start();

// Configuration
$config = [
    'base_path' => __DIR__,
    'css_path' => __DIR__ . '/css',
    'versions_path' => __DIR__ . '/css-versions',
    'components_path' => __DIR__ . '/components',
    'max_versions' => 50, // Keep last 50 versions
    'allowed_extensions' => ['css', 'scss', 'less']
];

// Ensure directories exist
foreach (['versions_path', 'components_path'] as $key) {
    if (!is_dir($config[$key])) {
        mkdir($config[$key], 0755, true);
    }
}

// API Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];
    $response = ['success' => false];

    try {
        switch ($action) {
            case 'save_css_version':
                $file = $_POST['file'];
                $content = $_POST['content'];
                $message = $_POST['message'] ?? 'CSS update';

                // Validate file is in custom directory
                if (strpos($file, '/custom/') === false) {
                    throw new Exception('Can only version control custom CSS files');
                }

                $fullPath = $config['css_path'] . '/' . $file;
                $versionInfo = saveCSSVersion($fullPath, $content, $message, $config);

                $response = [
                    'success' => true,
                    'version' => $versionInfo,
                    'message' => 'Version saved successfully'
                ];
                break;

            case 'get_css_versions':
                $file = $_POST['file'];
                $versions = getCSSVersions($file, $config);

                $response = [
                    'success' => true,
                    'versions' => $versions
                ];
                break;

            case 'rollback_css':
                $file = $_POST['file'];
                $versionId = $_POST['version_id'];

                $result = rollbackCSS($file, $versionId, $config);

                $response = [
                    'success' => true,
                    'content' => $result['content'],
                    'version' => $result['version'],
                    'message' => 'Rolled back successfully'
                ];
                break;

            case 'diff_css':
                $file = $_POST['file'];
                $version1 = $_POST['version1'];
                $version2 = $_POST['version2'];

                $diff = generateCSSDiff($file, $version1, $version2, $config);

                $response = [
                    'success' => true,
                    'diff' => $diff
                ];
                break;

            case 'list_css_files':
                $files = listCSSFiles($config);

                $response = [
                    'success' => true,
                    'files' => $files
                ];
                break;

            case 'save_component':
                $component = json_decode($_POST['component_data'], true);

                $componentId = saveComponent($component, $config);

                $response = [
                    'success' => true,
                    'component_id' => $componentId,
                    'message' => 'Component saved successfully'
                ];
                break;

            case 'list_components':
                $category = $_POST['category'] ?? null;
                $components = listComponents($category, $config);

                $response = [
                    'success' => true,
                    'components' => $components
                ];
                break;

            case 'get_component':
                $componentId = $_POST['component_id'];
                $component = getComponent($componentId, $config);

                $response = [
                    'success' => true,
                    'component' => $component
                ];
                break;

            case 'delete_component':
                $componentId = $_POST['component_id'];
                deleteComponent($componentId, $config);

                $response = [
                    'success' => true,
                    'message' => 'Component deleted'
                ];
                break;

            default:
                throw new Exception('Unknown action');
        }
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }

    echo json_encode($response);
    exit;
}

// Helper Functions

function saveCSSVersion($filePath, $content, $message, $config) {
    // Save current version
    file_put_contents($filePath, $content);

    // Create version snapshot
    $versionId = time() . '_' . substr(md5($content), 0, 8);
    $fileName = basename($filePath);
    $versionDir = $config['versions_path'] . '/' . str_replace('.css', '', $fileName);

    if (!is_dir($versionDir)) {
        mkdir($versionDir, 0755, true);
    }

    $versionFile = $versionDir . '/' . $versionId . '.css';
    file_put_contents($versionFile, $content);

    // Save metadata
    $metaFile = $versionDir . '/' . $versionId . '.json';
    $meta = [
        'id' => $versionId,
        'file' => $fileName,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'size' => strlen($content),
        'hash' => md5($content),
        'user' => $_SESSION['user_name'] ?? 'System'
    ];
    file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT));

    // Cleanup old versions
    cleanupOldVersions($versionDir, $config['max_versions']);

    return $meta;
}

function getCSSVersions($file, $config) {
    $fileName = basename($file);
    $versionDir = $config['versions_path'] . '/' . str_replace('.css', '', $fileName);

    if (!is_dir($versionDir)) {
        return [];
    }

    $versions = [];
    foreach (glob($versionDir . '/*.json') as $metaFile) {
        $meta = json_decode(file_get_contents($metaFile), true);
        $versions[] = $meta;
    }

    // Sort by timestamp descending
    usort($versions, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    return $versions;
}

function rollbackCSS($file, $versionId, $config) {
    $fileName = basename($file);
    $versionDir = $config['versions_path'] . '/' . str_replace('.css', '', $fileName);

    $versionFile = $versionDir . '/' . $versionId . '.css';
    $metaFile = $versionDir . '/' . $versionId . '.json';

    if (!file_exists($versionFile)) {
        throw new Exception('Version not found');
    }

    $content = file_get_contents($versionFile);
    $meta = json_decode(file_get_contents($metaFile), true);

    // Save current state before rollback
    $currentPath = $config['css_path'] . '/' . $file;
    if (file_exists($currentPath)) {
        $currentContent = file_get_contents($currentPath);
        saveCSSVersion($currentPath, $currentContent, 'Auto-backup before rollback', $config);
    }

    // Restore old version
    file_put_contents($currentPath, $content);

    return [
        'content' => $content,
        'version' => $meta
    ];
}

function generateCSSDiff($file, $version1, $version2, $config) {
    $fileName = basename($file);
    $versionDir = $config['versions_path'] . '/' . str_replace('.css', '', $fileName);

    $content1 = file_get_contents($versionDir . '/' . $version1 . '.css');
    $content2 = file_get_contents($versionDir . '/' . $version2 . '.css');

    // Simple line-by-line diff
    $lines1 = explode("\n", $content1);
    $lines2 = explode("\n", $content2);

    $diff = [];
    $maxLines = max(count($lines1), count($lines2));

    for ($i = 0; $i < $maxLines; $i++) {
        $line1 = $lines1[$i] ?? '';
        $line2 = $lines2[$i] ?? '';

        if ($line1 !== $line2) {
            if ($line1 && !$line2) {
                $diff[] = ['type' => 'removed', 'line' => $i + 1, 'content' => $line1];
            } elseif (!$line1 && $line2) {
                $diff[] = ['type' => 'added', 'line' => $i + 1, 'content' => $line2];
            } else {
                $diff[] = ['type' => 'changed', 'line' => $i + 1, 'old' => $line1, 'new' => $line2];
            }
        }
    }

    return $diff;
}

function listCSSFiles($config) {
    $files = [
        'core' => [],
        'dependencies' => [],
        'custom' => []
    ];

    foreach (['core', 'dependencies', 'custom'] as $type) {
        $dir = $config['css_path'] . '/' . $type;
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.css') as $file) {
                $files[$type][] = [
                    'name' => basename($file),
                    'path' => str_replace($config['css_path'] . '/', '', $file),
                    'size' => filesize($file),
                    'modified' => date('Y-m-d H:i:s', filemtime($file)),
                    'versions' => count(getCSSVersions($file, $config))
                ];
            }
        }
    }

    return $files;
}

function saveComponent($component, $config) {
    $componentId = $component['id'] ?? 'comp_' . time();
    $componentFile = $config['components_path'] . '/' . $componentId . '.json';

    $component['id'] = $componentId;
    $component['updated_at'] = date('Y-m-d H:i:s');

    file_put_contents($componentFile, json_encode($component, JSON_PRETTY_PRINT));

    return $componentId;
}

function listComponents($category, $config) {
    $components = [];

    foreach (glob($config['components_path'] . '/*.json') as $file) {
        $component = json_decode(file_get_contents($file), true);

        if ($category && $component['category'] !== $category) {
            continue;
        }

        $components[] = $component;
    }

    // Sort by name
    usort($components, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    return $components;
}

function getComponent($componentId, $config) {
    $componentFile = $config['components_path'] . '/' . $componentId . '.json';

    if (!file_exists($componentFile)) {
        throw new Exception('Component not found');
    }

    return json_decode(file_get_contents($componentFile), true);
}

function deleteComponent($componentId, $config) {
    $componentFile = $config['components_path'] . '/' . $componentId . '.json';

    if (file_exists($componentFile)) {
        unlink($componentFile);
    }
}

function cleanupOldVersions($versionDir, $maxVersions) {
    $files = glob($versionDir . '/*.json');

    if (count($files) <= $maxVersions) {
        return;
    }

    // Sort by modification time
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });

    // Delete oldest versions
    $toDelete = count($files) - $maxVersions;
    for ($i = 0; $i < $toDelete; $i++) {
        $metaFile = $files[$i];
        $cssFile = str_replace('.json', '.css', $metaFile);

        if (file_exists($metaFile)) unlink($metaFile);
        if (file_exists($cssFile)) unlink($cssFile);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSS & Theme Version Control System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .main-container {
            max-width: 1800px;
            margin: 0 auto;
        }

        .hero {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }

        .hero h1 {
            margin: 0;
            color: var(--primary);
            font-size: 2.5rem;
            font-weight: 700;
        }

        .hero p {
            margin: 0.5rem 0 0 0;
            color: #6b7280;
            font-size: 1.1rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
        }

        .sidebar {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            height: fit-content;
        }

        .sidebar h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .file-tree {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .file-tree li {
            padding: 0.75rem;
            margin-bottom: 0.25rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .file-tree li:hover {
            background: #f3f4f6;
        }

        .file-tree li.active {
            background: var(--primary);
            color: white;
        }

        .file-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            font-size: 1.1rem;
        }

        .file-icon.core {
            background: #fee2e2;
            color: #dc2626;
        }

        .file-icon.dependencies {
            background: #dbeafe;
            color: #2563eb;
        }

        .file-icon.custom {
            background: #d1fae5;
            color: #059669;
        }

        .main-panel {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        .tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 2rem;
        }

        .tab {
            padding: 1rem 1.5rem;
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            color: #6b7280;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .tab:hover {
            color: var(--primary);
        }

        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .CodeMirror {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            height: 600px;
            font-size: 14px;
        }

        .version-list {
            max-height: 500px;
            overflow-y: auto;
        }

        .version-item {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
            transition: all 0.2s;
        }

        .version-item:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .component-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .component-card {
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 1.5rem;
            transition: all 0.2s;
            cursor: pointer;
        }

        .component-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        }

        .component-preview {
            background: #f9fafb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            min-height: 100px;
        }

        .btn-action {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .diff-viewer {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 600px;
            overflow-y: auto;
        }

        .diff-line {
            padding: 0.25rem 0.5rem;
            border-left: 3px solid transparent;
        }

        .diff-added {
            background: #d1fae5;
            border-left-color: #059669;
        }

        .diff-removed {
            background: #fee2e2;
            border-left-color: #dc2626;
        }

        .diff-changed {
            background: #fef3c7;
            border-left-color: #f59e0b;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Hero -->
        <div class="hero">
            <h1><i class="fas fa-code-branch"></i> CSS & Theme Version Control</h1>
            <p>Git-style version control • Component library • Strict CSS architecture • Rollback system</p>
        </div>

        <!-- Dashboard -->
        <div class="dashboard-grid">
            <!-- Sidebar -->
            <div class="sidebar">
                <h3><i class="fas fa-folder-tree"></i> CSS Files</h3>

                <div class="mb-3">
                    <strong class="text-danger"><i class="fas fa-lock"></i> Core (Locked)</strong>
                    <ul class="file-tree" id="core-files"></ul>
                </div>

                <div class="mb-3">
                    <strong class="text-primary"><i class="fas fa-puzzle-piece"></i> Dependencies</strong>
                    <ul class="file-tree" id="dependency-files"></ul>
                </div>

                <div class="mb-3">
                    <strong class="text-success"><i class="fas fa-edit"></i> Custom (Editable)</strong>
                    <ul class="file-tree" id="custom-files"></ul>
                </div>

                <button class="btn btn-block btn-primary btn-action mt-3" onclick="createNewCSS()">
                    <i class="fas fa-plus"></i> New CSS File
                </button>
            </div>

            <!-- Main Panel -->
            <div class="main-panel">
                <div class="tabs">
                    <button class="tab active" data-tab="editor">
                        <i class="fas fa-code"></i> Editor
                    </button>
                    <button class="tab" data-tab="versions">
                        <i class="fas fa-history"></i> Versions
                    </button>
                    <button class="tab" data-tab="diff">
                        <i class="fas fa-code-compare"></i> Diff Viewer
                    </button>
                    <button class="tab" data-tab="components">
                        <i class="fas fa-cubes"></i> Components
                    </button>
                </div>

                <!-- Editor Tab -->
                <div class="tab-content active" data-content="editor">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 id="current-file-name">Select a file</h4>
                        <div>
                            <button class="btn btn-success btn-action" onclick="saveCurrentCSS()">
                                <i class="fas fa-save"></i> Save Version
                            </button>
                        </div>
                    </div>
                    <textarea id="css-editor"></textarea>
                </div>

                <!-- Versions Tab -->
                <div class="tab-content" data-content="versions">
                    <h4>Version History</h4>
                    <div class="version-list" id="version-list"></div>
                </div>

                <!-- Diff Tab -->
                <div class="tab-content" data-content="diff">
                    <h4>Compare Versions</h4>
                    <div class="form-row mb-3">
                        <div class="col">
                            <select class="form-control" id="diff-version1">
                                <option>Select version 1...</option>
                            </select>
                        </div>
                        <div class="col">
                            <select class="form-control" id="diff-version2">
                                <option>Select version 2...</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-primary btn-action" onclick="compareDiff()">
                                <i class="fas fa-code-compare"></i> Compare
                            </button>
                        </div>
                    </div>
                    <div class="diff-viewer" id="diff-viewer"></div>
                </div>

                <!-- Components Tab -->
                <div class="tab-content" data-content="components">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>Component Library</h4>
                        <button class="btn btn-primary btn-action" onclick="createNewComponent()">
                            <i class="fas fa-plus"></i> New Component
                        </button>
                    </div>
                    <div class="component-grid" id="component-grid"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
    <script>
        let currentFile = null;
        let editor = null;

        // Initialize CodeMirror
        $(document).ready(function() {
            editor = CodeMirror.fromTextArea(document.getElementById('css-editor'), {
                mode: 'css',
                theme: 'monokai',
                lineNumbers: true,
                lineWrapping: true,
                autoCloseBrackets: true,
                matchBrackets: true
            });

            loadCSSFiles();
            loadComponents();

            // Tab switching
            $('.tab').click(function() {
                const tab = $(this).data('tab');
                $('.tab').removeClass('active');
                $(this).addClass('active');
                $('.tab-content').removeClass('active');
                $(`.tab-content[data-content="${tab}"]`).addClass('active');
            });
        });

        function loadCSSFiles() {
            $.post('', { action: 'list_css_files' }, function(response) {
                if (response.success) {
                    renderFileTree('core-files', response.files.core, 'core');
                    renderFileTree('dependency-files', response.files.dependencies, 'dependencies');
                    renderFileTree('custom-files', response.files.custom, 'custom');
                }
            });
        }

        function renderFileTree(containerId, files, type) {
            const container = $(`#${containerId}`);
            container.empty();

            files.forEach(file => {
                const li = $('<li>')
                    .html(`
                        <div class="file-icon ${type}">
                            <i class="fas fa-file-code"></i>
                        </div>
                        <div>
                            <strong>${file.name}</strong>
                            <div class="small text-muted">${file.versions} versions</div>
                        </div>
                    `)
                    .click(function() {
                        loadCSSFile(file.path, type);
                    });

                container.append(li);
            });
        }

        function loadCSSFile(path, type) {
            currentFile = path;
            $('#current-file-name').text(path);

            $.get('css/' + path, function(content) {
                editor.setValue(content);
                editor.setOption('readOnly', type !== 'custom');

                if (type !== 'custom') {
                    alert('This file is read-only. Only custom CSS files can be edited.');
                }

                loadVersions(path);
            });
        }

        function saveCurrentCSS() {
            if (!currentFile) {
                alert('No file selected');
                return;
            }

            const message = prompt('Version message:');
            if (!message) return;

            $.post('', {
                action: 'save_css_version',
                file: currentFile,
                content: editor.getValue(),
                message: message
            }, function(response) {
                if (response.success) {
                    alert('Version saved successfully!');
                    loadVersions(currentFile);
                } else {
                    alert('Error: ' + response.error);
                }
            });
        }

        function loadVersions(file) {
            $.post('', {
                action: 'get_css_versions',
                file: file
            }, function(response) {
                if (response.success) {
                    renderVersions(response.versions);
                }
            });
        }

        function renderVersions(versions) {
            const container = $('#version-list');
            container.empty();

            versions.forEach(version => {
                const item = $('<div class="version-item">').html(`
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${version.message}</strong>
                            <div class="small text-muted">${version.timestamp} • ${version.user}</div>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-primary" onclick="rollback('${version.id}')">
                                <i class="fas fa-undo"></i> Rollback
                            </button>
                        </div>
                    </div>
                `);

                container.append(item);
            });
        }

        function rollback(versionId) {
            if (!confirm('Rollback to this version? Current changes will be backed up.')) return;

            $.post('', {
                action: 'rollback_css',
                file: currentFile,
                version_id: versionId
            }, function(response) {
                if (response.success) {
                    editor.setValue(response.content);
                    alert('Rolled back successfully!');
                    loadVersions(currentFile);
                } else {
                    alert('Error: ' + response.error);
                }
            });
        }

        function loadComponents() {
            $.post('', {
                action: 'list_components'
            }, function(response) {
                if (response.success) {
                    renderComponents(response.components);
                }
            });
        }

        function renderComponents(components) {
            const container = $('#component-grid');
            container.empty();

            components.forEach(comp => {
                const card = $('<div class="component-card">').html(`
                    <div class="component-preview">${comp.html}</div>
                    <strong>${comp.name}</strong>
                    <div class="small text-muted">${comp.category}</div>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-primary" onclick="useComponent('${comp.id}')">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteComponentConfirm('${comp.id}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `);

                container.append(card);
            });
        }

        function createNewComponent() {
            // Implementation for component creation modal
            alert('Component creation modal coming soon!');
        }

        function useComponent(componentId) {
            $.post('', {
                action: 'get_component',
                component_id: componentId
            }, function(response) {
                if (response.success) {
                    navigator.clipboard.writeText(response.component.html);
                    alert('Component HTML copied to clipboard!');
                }
            });
        }

        function deleteComponentConfirm(componentId) {
            if (!confirm('Delete this component?')) return;

            $.post('', {
                action: 'delete_component',
                component_id: componentId
            }, function(response) {
                if (response.success) {
                    loadComponents();
                }
            });
        }

        function compareDiff() {
            const v1 = $('#diff-version1').val();
            const v2 = $('#diff-version2').val();

            if (!v1 || !v2) {
                alert('Select two versions to compare');
                return;
            }

            $.post('', {
                action: 'diff_css',
                file: currentFile,
                version1: v1,
                version2: v2
            }, function(response) {
                if (response.success) {
                    renderDiff(response.diff);
                }
            });
        }

        function renderDiff(diff) {
            const container = $('#diff-viewer');
            container.empty();

            diff.forEach(line => {
                let html = '';
                if (line.type === 'added') {
                    html = `<div class="diff-line diff-added">+ ${line.content}</div>`;
                } else if (line.type === 'removed') {
                    html = `<div class="diff-line diff-removed">- ${line.content}</div>`;
                } else if (line.type === 'changed') {
                    html = `
                        <div class="diff-line diff-removed">- ${line.old}</div>
                        <div class="diff-line diff-added">+ ${line.new}</div>
                    `;
                }

                container.append(html);
            });
        }

        function createNewCSS() {
            const name = prompt('New CSS file name (without .css):');
            if (!name) return;

            const content = `/**\n * ${name}.css\n * Created: ${new Date().toISOString()}\n */\n\n`;

            $.ajax({
                url: 'css/custom/' + name + '.css',
                method: 'PUT',
                data: content,
                success: function() {
                    alert('File created!');
                    loadCSSFiles();
                }
            });
        }
    </script>
</body>
</html>
