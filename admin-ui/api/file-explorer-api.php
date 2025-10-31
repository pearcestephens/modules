<?php
/**
 * File Explorer API
 * List files, read, write, create, delete files
 * Supports browsing entire application
 *
 * @version 1.0.0
 */

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

// =====================================================================
// SECURITY CHECKS
// =====================================================================

// Base directories that can be accessed
$allowed_dirs = [
    realpath($_SERVER['DOCUMENT_ROOT'] . '/modules'),
    realpath($_SERVER['DOCUMENT_ROOT'] . '/private_html'),
    realpath($_SERVER['DOCUMENT_ROOT'] . '/conf'),
];

function is_safe_path($path) {
    global $allowed_dirs;

    $real_path = realpath($path);
    if (!$real_path) return false;

    foreach ($allowed_dirs as $allowed) {
        if (strpos($real_path, $allowed) === 0) {
            return true;
        }
    }
    return false;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch ($action) {
        case 'list':
            listFiles();
            break;

        case 'read':
            readFile();
            break;

        case 'write':
            writeFile();
            break;

        case 'create':
            createFile();
            break;

        case 'delete':
            deleteFile();
            break;

        case 'tree':
            getFileTree();
            break;

        case 'search':
            searchFiles();
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// =====================================================================
// ACTION HANDLERS
// =====================================================================

function listFiles() {
    $dir = $_GET['dir'] ?? $_SERVER['DOCUMENT_ROOT'] . '/modules';

    if (!is_safe_path($dir) || !is_dir($dir)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $files = [];
    $dirs = [];

    try {
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if ($item[0] === '.') continue; // Skip hidden files

            $path = $dir . '/' . $item;

            if (!is_safe_path($path)) continue;

            $file_info = [
                'name' => $item,
                'path' => $path,
                'type' => is_dir($path) ? 'directory' : 'file',
                'ext' => pathinfo($path, PATHINFO_EXTENSION),
                'size' => is_file($path) ? filesize($path) : 0,
                'modified' => filemtime($path),
            ];

            if (is_dir($path)) {
                $dirs[] = $file_info;
            } else {
                $files[] = $file_info;
            }
        }

        // Sort: directories first, then by name
        usort($dirs, fn($a, $b) => strcmp($a['name'], $b['name']));
        usort($files, fn($a, $b) => strcmp($a['name'], $b['name']));

        echo json_encode([
            'success' => true,
            'directory' => $dir,
            'items' => array_merge($dirs, $files),
            'count' => count($dirs) + count($files)
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function readFile() {
    $file = $_GET['file'] ?? null;

    if (!$file || !is_safe_path($file) || !is_file($file)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied or file not found']);
        return;
    }

    // Check file size (don't read huge files)
    $size = filesize($file);
    if ($size > 5 * 1024 * 1024) { // 5MB limit
        http_response_code(413);
        echo json_encode(['success' => false, 'error' => 'File too large (> 5MB)']);
        return;
    }

    try {
        $content = file_get_contents($file);

        echo json_encode([
            'success' => true,
            'file' => $file,
            'content' => $content,
            'size' => $size,
            'ext' => pathinfo($file, PATHINFO_EXTENSION),
            'lines' => count(explode("\n", $content))
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function writeFile() {
    $file = $_POST['file'] ?? null;
    $content = $_POST['content'] ?? null;

    if (!$file || !is_safe_path($file) || !is_file($file)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied or file not found']);
        return;
    }

    if ($content === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No content provided']);
        return;
    }

    try {
        // Create backup
        $backup = $file . '.backup_' . date('Y-m-d_H-i-s');
        copy($file, $backup);

        // Write new content
        $bytes = file_put_contents($file, $content);

        echo json_encode([
            'success' => true,
            'file' => $file,
            'bytes_written' => $bytes,
            'backup' => $backup
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createFile() {
    $dir = $_POST['directory'] ?? null;
    $name = $_POST['name'] ?? null;
    $content = $_POST['content'] ?? '';

    if (!$dir || !is_safe_path($dir) || !is_dir($dir)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied to directory']);
        return;
    }

    if (!$name || preg_match('/[\/\\\]/', $name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid filename']);
        return;
    }

    try {
        $file = $dir . '/' . $name;

        if (file_exists($file)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'File already exists']);
            return;
        }

        $bytes = file_put_contents($file, $content);

        echo json_encode([
            'success' => true,
            'file' => $file,
            'bytes_written' => $bytes
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteFile() {
    $file = $_POST['file'] ?? null;

    if (!$file || !is_safe_path($file) || !is_file($file)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied or file not found']);
        return;
    }

    try {
        // Create backup instead of delete
        $backup = $file . '.deleted_' . date('Y-m-d_H-i-s');
        rename($file, $backup);

        echo json_encode([
            'success' => true,
            'file' => $file,
            'backup' => $backup,
            'message' => 'File moved to backup (not permanently deleted)'
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getFileTree() {
    $dir = $_GET['dir'] ?? $_SERVER['DOCUMENT_ROOT'] . '/modules';
    $depth = $_GET['depth'] ?? 3;
    $current = $_GET['current'] ?? 0;

    if (!is_safe_path($dir) || !is_dir($dir)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    function buildTree($dir, $maxDepth, $currentDepth = 0) {
        if ($currentDepth >= $maxDepth) return [];

        $tree = [];

        try {
            $items = array_diff(scandir($dir), ['.', '..']);

            foreach ($items as $item) {
                if ($item[0] === '.') continue;

                $path = $dir . '/' . $item;
                if (!is_safe_path($path)) continue;

                if (is_dir($path)) {
                    $tree[] = [
                        'name' => $item,
                        'path' => $path,
                        'type' => 'directory',
                        'children' => buildTree($path, $maxDepth, $currentDepth + 1)
                    ];
                } else {
                    $tree[] = [
                        'name' => $item,
                        'path' => $path,
                        'type' => 'file',
                        'ext' => pathinfo($path, PATHINFO_EXTENSION),
                        'size' => filesize($path)
                    ];
                }
            }
        } catch (Exception $e) {
            // Skip directories we can't read
        }

        return $tree;
    }

    try {
        $tree = buildTree($dir, $depth);

        echo json_encode([
            'success' => true,
            'root' => $dir,
            'tree' => $tree
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function searchFiles() {
    $query = $_GET['q'] ?? null;
    $dir = $_GET['dir'] ?? $_SERVER['DOCUMENT_ROOT'] . '/modules';
    $ext = $_GET['ext'] ?? null; // Filter by extension

    if (!$query || !is_safe_path($dir)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing query or invalid directory']);
        return;
    }

    $results = [];
    $pattern = '/' . preg_quote($query) . '/i';

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                if ($ext && $file->getExtension() !== $ext) continue;
                if (!is_safe_path($file->getPathname())) continue;

                if (preg_match($pattern, $file->getFilename())) {
                    $results[] = [
                        'name' => $file->getFilename(),
                        'path' => $file->getPathname(),
                        'size' => $file->getSize(),
                        'ext' => $file->getExtension()
                    ];
                }
            }

            // Limit results
            if (count($results) >= 50) break;
        }

        echo json_encode([
            'success' => true,
            'query' => $query,
            'results' => array_slice($results, 0, 50),
            'total' => count($results)
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
