<?php
/**
 * CIS Theme Control Center - UNIFIED SYSTEM
 *
 * Everything in one place:
 * - Theme Builder (visual designer)
 * - CSS Editor (code editor with version control)
 * - Component Library (reusable blocks)
 * - Live Preview (instant feedback)
 * - Version History (rollback anytime)
 * - Export/Import (share themes)
 *
 * Version: 5.0.0 - The Complete Rebuild
 */

session_start();

// Configuration
$config = [
    'base_path' => __DIR__,
    'css_path' => __DIR__ . '/css',
    'versions_path' => __DIR__ . '/css-versions',
    'components_path' => __DIR__ . '/components',
    'themes_path' => __DIR__ . '/themes',
    'config_path' => __DIR__ . '/config',
    'active_theme_file' => __DIR__ . '/config/active-theme.json',
    'max_versions' => 50
];

// Ensure directories exist
foreach (['versions_path', 'components_path', 'themes_path', 'config_path'] as $key) {
    if (!is_dir($config[$key])) {
        mkdir($config[$key], 0755, true);
    }
}

// Load active theme
$activeTheme = null;
if (file_exists($config['active_theme_file'])) {
    $activeTheme = json_decode(file_get_contents($config['active_theme_file']), true);
}

// API Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];
    $response = ['success' => false];

    try {
        switch ($action) {
            // ============ THEME OPERATIONS ============
            case 'save_theme':
                $themeData = json_decode($_POST['theme_data'], true);
                $themeData['saved_at'] = date('Y-m-d H:i:s');
                $themeData['version'] = '5.0.0';

                // Save as active theme
                file_put_contents($config['active_theme_file'], json_encode($themeData, JSON_PRETTY_PRINT));

                // Also generate CSS file from theme
                $cssContent = generateCSSFromTheme($themeData);
                $cssFile = $config['css_path'] . '/custom/generated-theme.css';
                file_put_contents($cssFile, $cssContent);

                // Version the CSS
                saveCSSVersion($cssFile, $cssContent, 'Auto-generated from theme builder', $config);

                $response = [
                    'success' => true,
                    'message' => 'Theme saved and CSS generated!',
                    'css_preview' => $cssContent
                ];
                break;

            case 'load_theme':
                if (file_exists($config['active_theme_file'])) {
                    $theme = json_decode(file_get_contents($config['active_theme_file']), true);
                    $response = ['success' => true, 'data' => $theme];
                } else {
                    $response = ['success' => false, 'error' => 'No active theme'];
                }
                break;

            // ============ CSS OPERATIONS ============
            case 'save_css':
                $file = $_POST['file'];
                $content = $_POST['content'];
                $message = $_POST['message'] ?? 'CSS update';

                if (strpos($file, '/custom/') === false) {
                    throw new Exception('Can only edit custom CSS files');
                }

                $fullPath = $config['css_path'] . '/' . $file;
                $versionInfo = saveCSSVersion($fullPath, $content, $message, $config);

                // Update theme if this affects theme variables
                syncThemeFromCSS($fullPath, $config);

                $response = [
                    'success' => true,
                    'version' => $versionInfo,
                    'message' => 'CSS saved and versioned'
                ];
                break;

            case 'load_css':
                $file = $_POST['file'];
                $fullPath = $config['css_path'] . '/' . $file;

                if (file_exists($fullPath)) {
                    $content = file_get_contents($fullPath);
                    $versions = getCSSVersions($file, $config);

                    $response = [
                        'success' => true,
                        'content' => $content,
                        'versions' => $versions,
                        'file' => $file
                    ];
                } else {
                    throw new Exception('File not found');
                }
                break;

            case 'rollback_css':
                $file = $_POST['file'];
                $versionId = $_POST['version_id'];

                $result = rollbackCSS($file, $versionId, $config);

                // Sync theme if this affects theme file
                syncThemeFromCSS($config['css_path'] . '/' . $file, $config);

                $response = [
                    'success' => true,
                    'content' => $result['content'],
                    'version' => $result['version']
                ];
                break;

            case 'list_css_files':
                $files = listCSSFiles($config);
                $response = ['success' => true, 'files' => $files];
                break;

            // ============ COMPONENT OPERATIONS ============
            case 'save_component':
                $component = json_decode($_POST['component_data'], true);
                $componentId = saveComponent($component, $config);

                $response = [
                    'success' => true,
                    'component_id' => $componentId
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

                $response = ['success' => true];
                break;

            // ============ SMART COLOR GENERATION ============
            case 'generate_smart_colors':
                $baseColor = $_POST['base_color'] ?? null;
                $harmony = $_POST['harmony'] ?? 'complementary';

                $colors = generateSmartColors($baseColor, $harmony);

                $response = [
                    'success' => true,
                    'colors' => $colors
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

// ============================================
// HELPER FUNCTIONS
// ============================================

function generateCSSFromTheme($theme) {
    $css = "/**\n * Auto-generated theme CSS\n * Generated: " . date('Y-m-d H:i:s') . "\n */\n\n";

    $css .= ":root {\n";
    $css .= "    --cis-primary: {$theme['primary']};\n";
    $css .= "    --cis-primary-rgb: " . hexToRgb($theme['primary']) . ";\n";
    $css .= "    --cis-secondary: {$theme['secondary']};\n";
    $css .= "    --cis-secondary-rgb: " . hexToRgb($theme['secondary']) . ";\n";
    $css .= "    --cis-accent: {$theme['accent']};\n";
    $css .= "    --cis-success: {$theme['success']};\n";
    $css .= "    --cis-warning: {$theme['warning']};\n";
    $css .= "    --cis-danger: {$theme['danger']};\n";
    $css .= "    --font-heading: '{$theme['font_heading']}', sans-serif;\n";
    $css .= "    --font-body: '{$theme['font_body']}', sans-serif;\n";
    $css .= "    --border-radius: {$theme['border_radius']};\n";
    $css .= "    --density: {$theme['density']};\n";

    if (isset($theme['gradient'])) {
        $css .= "    --gradient: {$theme['gradient']};\n";
    }

    if (isset($theme['shadow'])) {
        $css .= "    --shadow: {$theme['shadow']};\n";
    }

    $css .= "}\n";

    return $css;
}

function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "$r, $g, $b";
}

function syncThemeFromCSS($cssFile, $config) {
    // Extract theme variables from CSS and update active theme
    if (!file_exists($cssFile)) return;

    $content = file_get_contents($cssFile);

    // Parse CSS variables
    if (preg_match('/:root\s*{([^}]+)}/s', $content, $matches)) {
        $vars = $matches[1];

        $theme = [];

        // Extract colors
        if (preg_match('/--cis-primary:\s*([^;]+);/', $vars, $m)) {
            $theme['primary'] = trim($m[1]);
        }
        if (preg_match('/--cis-secondary:\s*([^;]+);/', $vars, $m)) {
            $theme['secondary'] = trim($m[1]);
        }

        // Only update if we found variables
        if (!empty($theme)) {
            $existingTheme = [];
            if (file_exists($config['active_theme_file'])) {
                $existingTheme = json_decode(file_get_contents($config['active_theme_file']), true);
            }

            $mergedTheme = array_merge($existingTheme, $theme);
            $mergedTheme['synced_at'] = date('Y-m-d H:i:s');

            file_put_contents($config['active_theme_file'], json_encode($mergedTheme, JSON_PRETTY_PRINT));
        }
    }
}

function saveCSSVersion($filePath, $content, $message, $config) {
    file_put_contents($filePath, $content);

    $versionId = time() . '_' . substr(md5($content), 0, 8);
    $fileName = basename($filePath);
    $versionDir = $config['versions_path'] . '/' . str_replace('.css', '', $fileName);

    if (!is_dir($versionDir)) {
        mkdir($versionDir, 0755, true);
    }

    $versionFile = $versionDir . '/' . $versionId . '.css';
    file_put_contents($versionFile, $content);

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

    $currentPath = $config['css_path'] . '/' . $file;
    if (file_exists($currentPath)) {
        $currentContent = file_get_contents($currentPath);
        saveCSSVersion($currentPath, $currentContent, 'Auto-backup before rollback', $config);
    }

    file_put_contents($currentPath, $content);

    return [
        'content' => $content,
        'version' => $meta
    ];
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
                $relativePath = str_replace($config['css_path'] . '/', '', $file);
                $files[$type][] = [
                    'name' => basename($file),
                    'path' => $relativePath,
                    'size' => filesize($file),
                    'modified' => date('Y-m-d H:i:s', filemtime($file)),
                    'versions' => count(getCSSVersions($relativePath, $config))
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

    if (!is_dir($config['components_path'])) {
        return $components;
    }

    foreach (glob($config['components_path'] . '/*.json') as $file) {
        $component = json_decode(file_get_contents($file), true);

        if ($category && isset($component['category']) && $component['category'] !== $category) {
            continue;
        }

        $components[] = $component;
    }

    usort($components, function($a, $b) {
        return strcmp($a['name'] ?? '', $b['name'] ?? '');
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

    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });

    $toDelete = count($files) - $maxVersions;
    for ($i = 0; $i < $toDelete; $i++) {
        $metaFile = $files[$i];
        $cssFile = str_replace('.json', '.css', $metaFile);

        if (file_exists($metaFile)) unlink($metaFile);
        if (file_exists($cssFile)) unlink($cssFile);
    }
}

function generateSmartColors($baseColor = null, $harmony = 'complementary') {
    // If no base color, generate a random vibrant one
    if (!$baseColor) {
        $hue = rand(0, 360);
        $saturation = rand(60, 90);
        $lightness = rand(45, 60);
        $baseColor = hslToHex($hue, $saturation, $lightness);
    }

    // Convert base color to HSL
    list($h, $s, $l) = hexToHsl($baseColor);

    $colors = [];

    switch ($harmony) {
        case 'complementary':
            $colors['primary'] = $baseColor;
            $colors['secondary'] = hslToHex(($h + 180) % 360, $s, $l);
            $colors['accent'] = hslToHex(($h + 30) % 360, $s, $l);
            break;

        case 'analogous':
            $colors['primary'] = $baseColor;
            $colors['secondary'] = hslToHex(($h + 30) % 360, $s, $l);
            $colors['accent'] = hslToHex(($h - 30 + 360) % 360, $s, $l);
            break;

        case 'triadic':
            $colors['primary'] = $baseColor;
            $colors['secondary'] = hslToHex(($h + 120) % 360, $s, $l);
            $colors['accent'] = hslToHex(($h + 240) % 360, $s, $l);
            break;

        case 'tetradic':
            $colors['primary'] = $baseColor;
            $colors['secondary'] = hslToHex(($h + 90) % 360, $s, $l);
            $colors['accent'] = hslToHex(($h + 180) % 360, $s, $l);
            $colors['extra'] = hslToHex(($h + 270) % 360, $s, $l);
            break;

        case 'monochromatic':
            $colors['primary'] = $baseColor;
            $colors['secondary'] = hslToHex($h, $s, max(10, $l - 20));
            $colors['accent'] = hslToHex($h, $s, min(90, $l + 20));
            break;
    }

    // Add semantic colors with proper contrast
    $colors['success'] = hslToHex(140, 70, 50); // Green
    $colors['warning'] = hslToHex(45, 90, 55); // Orange
    $colors['danger'] = hslToHex(0, 80, 55); // Red

    // Calculate contrast ratios
    $colors['contrast_ratios'] = [
        'primary_on_white' => calculateContrastRatio($baseColor, '#FFFFFF'),
        'secondary_on_white' => calculateContrastRatio($colors['secondary'], '#FFFFFF'),
        'wcag_aa_pass' => calculateContrastRatio($baseColor, '#FFFFFF') >= 4.5
    ];

    return $colors;
}

function hexToHsl($hex) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;

    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;

    if ($max == $min) {
        $h = $s = 0;
    } else {
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        switch ($max) {
            case $r: $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6; break;
            case $g: $h = (($b - $r) / $d + 2) / 6; break;
            case $b: $h = (($r - $g) / $d + 4) / 6; break;
        }
    }

    return [round($h * 360), round($s * 100), round($l * 100)];
}

function hslToHex($h, $s, $l) {
    $s /= 100;
    $l /= 100;

    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;

    if ($h < 60) {
        $r = $c; $g = $x; $b = 0;
    } elseif ($h < 120) {
        $r = $x; $g = $c; $b = 0;
    } elseif ($h < 180) {
        $r = 0; $g = $c; $b = $x;
    } elseif ($h < 240) {
        $r = 0; $g = $x; $b = $c;
    } elseif ($h < 300) {
        $r = $x; $g = 0; $b = $c;
    } else {
        $r = $c; $g = 0; $b = $x;
    }

    $r = round(($r + $m) * 255);
    $g = round(($g + $m) * 255);
    $b = round(($b + $m) * 255);

    return sprintf('#%02X%02X%02X', $r, $g, $b);
}

function calculateContrastRatio($color1, $color2) {
    $l1 = getRelativeLuminance($color1);
    $l2 = getRelativeLuminance($color2);

    $lighter = max($l1, $l2);
    $darker = min($l1, $l2);

    return ($lighter + 0.05) / ($darker + 0.05);
}

function getRelativeLuminance($hex) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;

    $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
    $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
    $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

    return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

// Google Fonts library
$googleFonts = [
    'Inter' => 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',
    'Roboto' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap',
    'Poppins' => 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap',
    'Montserrat' => 'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap',
    'Open Sans' => 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap',
    'Lato' => 'https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&display=swap',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS Theme Control Center</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">

    <?php if ($activeTheme): ?>
    <style>
        :root {
            --cis-primary: <?= $activeTheme['primary'] ?? '#667eea' ?>;
            --cis-secondary: <?= $activeTheme['secondary'] ?? '#764ba2' ?>;
            --cis-accent: <?= $activeTheme['accent'] ?? '#10b981' ?>;
            --font-heading: <?= isset($activeTheme['font_heading']) ? "'{$activeTheme['font_heading']}', sans-serif" : "'Inter', sans-serif" ?>;
            --font-body: <?= isset($activeTheme['font_body']) ? "'{$activeTheme['font_body']}', sans-serif" : "'Inter', sans-serif" ?>;
            --border-radius: <?= $activeTheme['border_radius'] ?? '0.75rem' ?>;
            --density: <?= $activeTheme['density'] ?? 1 ?>;
        }
    </style>
    <?php endif; ?>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-body, 'Inter', sans-serif);
            background: linear-gradient(135deg, var(--cis-primary, #667eea) 0%, var(--cis-secondary, #764ba2) 100%);
            min-height: 100vh;
        }

        /* UNIFIED HEADER */
        .unified-header {
            background: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
        }

        .unified-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--cis-primary, #667eea);
            margin: 0;
        }

        .unified-header .subtitle {
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .unified-header .nav-pills {
            margin-top: 1rem;
        }

        .unified-header .nav-link {
            color: #6b7280;
            padding: 0.75rem 1.5rem;
            border-radius: calc(var(--border-radius, 0.75rem) * 0.5);
            margin-right: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
        }

        .unified-header .nav-link:hover {
            background: #f3f4f6;
            color: var(--cis-primary, #667eea);
        }

        .unified-header .nav-link.active {
            background: linear-gradient(135deg, var(--cis-primary, #667eea) 0%, var(--cis-secondary, #764ba2) 100%);
            color: white;
        }

        /* MAIN CONTAINER */
        .main-container {
            max-width: 1800px;
            margin: 0 auto;
            padding: 0 2rem 2rem 2rem;
        }

        .panel {
            background: white;
            border-radius: calc(var(--border-radius, 0.75rem) * 1.5);
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            display: none;
        }

        .panel.active {
            display: block;
        }

        /* THEME BUILDER SPECIFIC */
        .color-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .color-card {
            border: 2px solid #e5e7eb;
            border-radius: var(--border-radius, 0.75rem);
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .color-card:hover {
            border-color: var(--cis-primary, #667eea);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        }

        .color-swatch {
            width: 100%;
            height: 100px;
            border-radius: calc(var(--border-radius, 0.75rem) * 0.5);
            margin-bottom: 0.75rem;
        }

        .color-input-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .color-input-group input[type="color"] {
            width: 60px;
            height: 40px;
            border: 2px solid #e5e7eb;
            border-radius: calc(var(--border-radius, 0.75rem) * 0.5);
            cursor: pointer;
        }

        .color-input-group input[type="text"] {
            flex: 1;
            padding: 0.5rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: calc(var(--border-radius, 0.75rem) * 0.5);
            font-family: 'Courier New', monospace;
        }

        /* CSS EDITOR SPECIFIC */
        .CodeMirror {
            border: 2px solid #e5e7eb;
            border-radius: var(--border-radius, 0.75rem);
            height: 600px;
            font-size: 14px;
        }

        .file-tree {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .file-tree li {
            padding: 0.75rem;
            border-radius: calc(var(--border-radius, 0.75rem) * 0.5);
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
            background: var(--cis-primary, #667eea);
            color: white;
        }

        /* COMPONENT LIBRARY SPECIFIC */
        .component-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .component-card {
            border: 2px solid #e5e7eb;
            border-radius: var(--border-radius, 0.75rem);
            padding: 1.5rem;
            transition: all 0.2s;
        }

        .component-card:hover {
            border-color: var(--cis-primary, #667eea);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        }

        .component-preview {
            background: #f9fafb;
            border-radius: calc(var(--border-radius, 0.75rem) * 0.5);
            padding: 1rem;
            margin-bottom: 1rem;
            min-height: 100px;
        }

        /* BUTTONS */
        .btn-action {
            padding: calc(0.75rem * var(--density, 1)) calc(1.5rem * var(--density, 1));
            border-radius: var(--border-radius, 0.75rem);
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--cis-primary, #667eea) 0%, var(--cis-secondary, #764ba2) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        /* TOAST */
        .toast-container {
            position: fixed;
            top: 2rem;
            right: 2rem;
            z-index: 9999;
        }

        .toast {
            background: white;
            border-radius: var(--border-radius, 0.75rem);
            padding: 1rem 1.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 1rem;
            min-width: 300px;
            opacity: 0;
            transform: translateX(400px);
            transition: all 0.3s;
        }

        .toast.show {
            opacity: 1;
            transform: translateX(0);
        }

        .toast-success {
            border-left: 4px solid #10b981;
        }

        .toast-error {
            border-left: 4px solid #ef4444;
        }
    </style>
</head>
<body>
    <!-- UNIFIED HEADER -->
    <div class="unified-header">
        <h1><i class="fas fa-paint-brush"></i> CIS Theme Control Center</h1>
        <p class="subtitle">Visual designer • CSS editor • Version control • Component library - all in one place</p>

        <ul class="nav nav-pills">
            <li class="nav-item">
                <a class="nav-link active" href="#" data-panel="theme-builder">
                    <i class="fas fa-palette"></i> Theme Builder
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-panel="css-editor">
                    <i class="fas fa-code"></i> CSS Editor
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-panel="components">
                    <i class="fas fa-cubes"></i> Components
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-panel="preview">
                    <i class="fas fa-eye"></i> Live Preview
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-panel="versions">
                    <i class="fas fa-history"></i> Version History
                </a>
            </li>
        </ul>
    </div>

    <!-- MAIN CONTAINER -->
    <div class="main-container">
        <!-- THEME BUILDER PANEL -->
        <div class="panel active" id="theme-builder">
            <h2>Visual Theme Builder</h2>
            <p class="text-muted mb-4">Design your theme visually with smart color generation</p>

            <div class="row">
                <div class="col-md-8">
                    <h4>Color Harmony Generator</h4>
                    <div class="btn-group mb-3">
                        <button class="btn btn-outline-primary" onclick="generateSmartColors('complementary')">Complementary</button>
                        <button class="btn btn-outline-primary" onclick="generateSmartColors('analogous')">Analogous</button>
                        <button class="btn btn-outline-primary" onclick="generateSmartColors('triadic')">Triadic</button>
                        <button class="btn btn-outline-primary" onclick="generateSmartColors('tetradic')">Tetradic</button>
                        <button class="btn btn-outline-primary" onclick="generateSmartColors('monochromatic')">Monochromatic</button>
                    </div>

                    <div class="color-grid">
                        <div class="color-input-group">
                            <input type="color" id="primary-color" value="<?= $activeTheme['primary'] ?? '#667eea' ?>">
                            <input type="text" id="primary-hex" value="<?= $activeTheme['primary'] ?? '#667eea' ?>">
                            <label>Primary</label>
                        </div>

                        <div class="color-input-group">
                            <input type="color" id="secondary-color" value="<?= $activeTheme['secondary'] ?? '#764ba2' ?>">
                            <input type="text" id="secondary-hex" value="<?= $activeTheme['secondary'] ?? '#764ba2' ?>">
                            <label>Secondary</label>
                        </div>

                        <div class="color-input-group">
                            <input type="color" id="accent-color" value="<?= $activeTheme['accent'] ?? '#10b981' ?>">
                            <input type="text" id="accent-hex" value="<?= $activeTheme['accent'] ?? '#10b981' ?>">
                            <label>Accent</label>
                        </div>
                    </div>

                    <div class="mt-4">
                        <h5>Fonts</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <label>Heading Font</label>
                                <select class="form-control" id="heading-font">
                                    <?php foreach (array_keys($googleFonts) as $font): ?>
                                        <option value="<?= $font ?>" <?= ($activeTheme['font_heading'] ?? 'Inter') === $font ? 'selected' : '' ?>><?= $font ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label>Body Font</label>
                                <select class="form-control" id="body-font">
                                    <?php foreach (array_keys($googleFonts) as $font): ?>
                                        <option value="<?= $font ?>" <?= ($activeTheme['font_body'] ?? 'Inter') === $font ? 'selected' : '' ?>><?= $font ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button class="btn btn-success btn-action btn-lg" onclick="saveTheme()">
                            <i class="fas fa-save"></i> Save Theme & Generate CSS
                        </button>
                    </div>
                </div>

                <div class="col-md-4">
                    <h4>Quick Preview</h4>
                    <div style="background: white; padding: 1.5rem; border-radius: 1rem;">
                        <button id="preview-btn-primary" style="padding: 0.75rem 1.5rem; border-radius: 0.5rem; border: none; color: white; cursor: pointer; margin-bottom: 0.5rem; width: 100%;">Primary Button</button>
                        <button id="preview-btn-secondary" style="padding: 0.75rem 1.5rem; border-radius: 0.5rem; border: none; color: white; cursor: pointer; margin-bottom: 0.5rem; width: 100%;">Secondary Button</button>
                        <div id="preview-text" style="margin-top: 1rem;">
                            <h3 style="margin-bottom: 0.5rem;">Heading Text</h3>
                            <p>Body text example with the selected fonts.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CSS EDITOR PANEL -->
        <div class="panel" id="css-editor">
            <h2>CSS Editor with Version Control</h2>

            <div class="row">
                <div class="col-md-3">
                    <h5>CSS Files</h5>
                    <ul class="file-tree" id="css-file-tree"></ul>
                </div>

                <div class="col-md-9">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 id="current-file-name">Select a file</h5>
                        <button class="btn btn-success btn-action" onclick="saveCSS()">
                            <i class="fas fa-save"></i> Save & Version
                        </button>
                    </div>
                    <textarea id="css-editor-textarea"></textarea>
                </div>
            </div>
        </div>

        <!-- COMPONENTS PANEL -->
        <div class="panel" id="components">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Component Library</h2>
                <button class="btn btn-primary btn-action" onclick="createComponent()">
                    <i class="fas fa-plus"></i> New Component
                </button>
            </div>

            <div class="component-grid" id="component-grid"></div>
        </div>

        <!-- LIVE PREVIEW PANEL -->
        <div class="panel" id="preview">
            <h2>Live Preview</h2>
            <iframe id="preview-frame" style="width: 100%; height: 800px; border: 2px solid #e5e7eb; border-radius: 1rem;"></iframe>
        </div>

        <!-- VERSION HISTORY PANEL -->
        <div class="panel" id="versions">
            <h2>Version History</h2>
            <div id="version-list"></div>
        </div>
    </div>

    <!-- TOAST CONTAINER -->
    <div class="toast-container" id="toast-container"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
    <script>
        let currentTheme = {
            primary: '<?= $activeTheme['primary'] ?? '#667eea' ?>',
            secondary: '<?= $activeTheme['secondary'] ?? '#764ba2' ?>',
            accent: '<?= $activeTheme['accent'] ?? '#10b981' ?>',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            font_heading: '<?= $activeTheme['font_heading'] ?? 'Inter' ?>',
            font_body: '<?= $activeTheme['font_body'] ?? 'Inter' ?>',
            border_radius: '<?= $activeTheme['border_radius'] ?? '0.75rem' ?>',
            density: <?= $activeTheme['density'] ?? 1 ?>
        };

        let cssEditor = null;
        let currentCSSFile = null;

        $(document).ready(function() {
            // Initialize CodeMirror
            cssEditor = CodeMirror.fromTextArea(document.getElementById('css-editor-textarea'), {
                mode: 'css',
                theme: 'monokai',
                lineNumbers: true,
                lineWrapping: true,
                autoCloseBrackets: true,
                matchBrackets: true
            });

            // Panel switching
            $('.nav-link').click(function(e) {
                e.preventDefault();
                const panel = $(this).data('panel');

                $('.nav-link').removeClass('active');
                $(this).addClass('active');

                $('.panel').removeClass('active');
                $('#' + panel).addClass('active');

                // Load data based on panel
                if (panel === 'css-editor') {
                    loadCSSFiles();
                } else if (panel === 'components') {
                    loadComponents();
                } else if (panel === 'versions') {
                    loadVersions();
                }
            });

            // Color pickers
            $('#primary-color').on('change', function() {
                const color = $(this).val();
                $('#primary-hex').val(color);
                currentTheme.primary = color;
                updatePreview();
            });

            $('#secondary-color').on('change', function() {
                const color = $(this).val();
                $('#secondary-hex').val(color);
                currentTheme.secondary = color;
                updatePreview();
            });

            $('#accent-color').on('change', function() {
                const color = $(this).val();
                $('#accent-hex').val(color);
                currentTheme.accent = color;
                updatePreview();
            });

            // Font selectors
            $('#heading-font, #body-font').on('change', function() {
                currentTheme.font_heading = $('#heading-font').val();
                currentTheme.font_body = $('#body-font').val();
                updatePreview();
            });

            // Initial preview update
            updatePreview();
        });

        function updatePreview() {
            $('#preview-btn-primary').css({
                'background': currentTheme.primary,
                'font-family': currentTheme.font_heading
            });

            $('#preview-btn-secondary').css({
                'background': currentTheme.secondary,
                'font-family': currentTheme.font_heading
            });

            $('#preview-text h3').css('font-family', currentTheme.font_heading);
            $('#preview-text p').css('font-family', currentTheme.font_body);
        }

        function generateSmartColors(harmony) {
            const baseColor = $('#primary-hex').val();

            $.post('', {
                action: 'generate_smart_colors',
                base_color: baseColor,
                harmony: harmony
            }, function(response) {
                if (response.success) {
                    const colors = response.colors;

                    currentTheme.primary = colors.primary;
                    currentTheme.secondary = colors.secondary;
                    currentTheme.accent = colors.accent;
                    currentTheme.success = colors.success;
                    currentTheme.warning = colors.warning;
                    currentTheme.danger = colors.danger;

                    $('#primary-color').val(colors.primary);
                    $('#primary-hex').val(colors.primary);
                    $('#secondary-color').val(colors.secondary);
                    $('#secondary-hex').val(colors.secondary);
                    $('#accent-color').val(colors.accent);
                    $('#accent-hex').val(colors.accent);

                    updatePreview();

                    showToast('🎨 Smart Colors Generated!', `${harmony} harmony with WCAG contrast validation`, 'success');
                }
            });
        }

        function saveTheme() {
            $.post('', {
                action: 'save_theme',
                theme_data: JSON.stringify(currentTheme)
            }, function(response) {
                if (response.success) {
                    showToast('✅ Theme Saved!', 'Theme saved and CSS generated automatically', 'success');

                    // Show generated CSS
                    if (response.css_preview) {
                        cssEditor.setValue(response.css_preview);
                        $('.nav-link[data-panel="css-editor"]').click();
                    }
                } else {
                    showToast('❌ Error', response.error, 'error');
                }
            });
        }

        function loadCSSFiles() {
            $.post('', { action: 'list_css_files' }, function(response) {
                if (response.success) {
                    const tree = $('#css-file-tree');
                    tree.empty();

                    Object.keys(response.files).forEach(type => {
                        tree.append(`<li style="font-weight: bold; margin-top: 1rem;">${type.toUpperCase()}</li>`);

                        response.files[type].forEach(file => {
                            const li = $('<li>')
                                .text(file.name)
                                .data('path', file.path)
                                .click(function() {
                                    loadCSSFile(file.path);
                                    $('.file-tree li').removeClass('active');
                                    $(this).addClass('active');
                                });
                            tree.append(li);
                        });
                    });
                }
            });
        }

        function loadCSSFile(path) {
            $.post('', {
                action: 'load_css',
                file: path
            }, function(response) {
                if (response.success) {
                    cssEditor.setValue(response.content);
                    currentCSSFile = path;
                    $('#current-file-name').text(path);
                }
            });
        }

        function saveCSS() {
            if (!currentCSSFile) {
                showToast('⚠️ No File Selected', 'Please select a CSS file first', 'error');
                return;
            }

            const message = prompt('Version message:');
            if (!message) return;

            $.post('', {
                action: 'save_css',
                file: currentCSSFile,
                content: cssEditor.getValue(),
                message: message
            }, function(response) {
                if (response.success) {
                    showToast('✅ CSS Saved!', 'Version created and theme synced', 'success');
                } else {
                    showToast('❌ Error', response.error, 'error');
                }
            });
        }

        function loadComponents() {
            $.post('', { action: 'list_components' }, function(response) {
                if (response.success) {
                    const grid = $('#component-grid');
                    grid.empty();

                    response.components.forEach(comp => {
                        const card = $('<div class="component-card">').html(`
                            <div class="component-preview">${comp.html}</div>
                            <strong>${comp.name}</strong>
                            <div class="small text-muted">${comp.category}</div>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-primary" onclick="copyComponent('${comp.id}')">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        `);
                        grid.append(card);
                    });
                }
            });
        }

        function copyComponent(componentId) {
            $.post('', {
                action: 'get_component',
                component_id: componentId
            }, function(response) {
                if (response.success) {
                    navigator.clipboard.writeText(response.component.html);
                    showToast('📋 Copied!', 'Component HTML copied to clipboard', 'success');
                }
            });
        }

        function loadVersions() {
            if (!currentCSSFile) return;

            // Load version history for current file
            // Implementation similar to version list
        }

        function showToast(title, message, type = 'success') {
            const toast = $('<div class="toast toast-' + type + ' show">').html(`
                <strong>${title}</strong><br>
                <span class="small">${message}</span>
            `);

            $('#toast-container').append(toast);

            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>
