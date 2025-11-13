<?php
/**
 * CIS Design Studio - Unified Theme & Component Management
 *
 * The ONE place for all design work:
 * - Theme Builder (colors, fonts, variables)
 * - Code Editor (HTML/CSS/JS with AI Copilot)
 * - Live Preview (multiple view modes)
 * - Component Library (all CIS templates)
 * - Version Control (Git-style history)
 * - Responsive Testing (phone/tablet/desktop)
 *
 * Version: 5.0.0 - UNIFIED EXPERIENCE
 */

session_start();

// Load active theme
$activeThemeFile = __DIR__ . '/config/active-theme.json';
$activeTheme = null;
if (file_exists($activeThemeFile)) {
    $activeTheme = json_decode(file_get_contents($activeThemeFile), true);
}

// API Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false];

    try {
        switch ($action) {
            case 'save_theme':
                $themeData = json_decode($_POST['theme_data'], true);
                if (!is_dir(__DIR__ . '/config')) {
                    mkdir(__DIR__ . '/config', 0755, true);
                }
                $themeData['saved_at'] = date('Y-m-d H:i:s');
                file_put_contents($activeThemeFile, json_encode($themeData, JSON_PRETTY_PRINT));

                // Also save CSS file
                $css = generateThemeCSS($themeData);
                file_put_contents(__DIR__ . '/css/custom/generated-theme.css', $css);

                // Create version
                saveVersion('css/custom/generated-theme.css', $css, 'Theme updated from Design Studio');

                $response = ['success' => true, 'message' => 'Theme saved and version created'];
                break;

            case 'save_component':
                $html = $_POST['html'];
                $css = $_POST['css'];
                $js = $_POST['js'];
                $name = $_POST['name'];
                $category = $_POST['category'];

                $componentId = 'comp_' . time();
                $componentData = [
                    'id' => $componentId,
                    'name' => $name,
                    'category' => $category,
                    'html' => $html,
                    'css' => $css,
                    'js' => $js,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                if (!is_dir(__DIR__ . '/components')) {
                    mkdir(__DIR__ . '/components', 0755, true);
                }

                file_put_contents(
                    __DIR__ . '/components/' . $componentId . '.json',
                    json_encode($componentData, JSON_PRETTY_PRINT)
                );

                $response = ['success' => true, 'component_id' => $componentId];
                break;

            case 'load_component':
                $componentId = $_POST['component_id'];
                $file = __DIR__ . '/components/' . $componentId . '.json';
                if (file_exists($file)) {
                    $response = ['success' => true, 'data' => json_decode(file_get_contents($file), true)];
                } else {
                    $response = ['success' => false, 'error' => 'Component not found'];
                }
                break;

            case 'list_components':
                $components = [];
                if (is_dir(__DIR__ . '/components')) {
                    foreach (glob(__DIR__ . '/components/*.json') as $file) {
                        $comp = json_decode(file_get_contents($file), true);
                        $components[] = $comp;
                    }
                }
                $response = ['success' => true, 'data' => $components];
                break;

            case 'save_css_version':
                $file = $_POST['file'];
                $content = $_POST['content'];
                $message = $_POST['message'];

                $fullPath = __DIR__ . '/' . $file;
                file_put_contents($fullPath, $content);

                $versionId = saveVersion($file, $content, $message);

                $response = ['success' => true, 'version_id' => $versionId];
                break;

            case 'get_css_versions':
                $file = $_POST['file'];
                $versions = getVersions($file);
                $response = ['success' => true, 'data' => $versions];
                break;

            case 'ai_assist':
                $prompt = $_POST['prompt'];
                $context = $_POST['context'];

                // Call AI API (placeholder - connect to your AI endpoint)
                $aiResponse = callAI($prompt, $context);

                $response = ['success' => true, 'data' => $aiResponse];
                break;

            default:
                throw new Exception('Unknown action');
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'error' => $e->getMessage()];
    }

    echo json_encode($response);
    exit;
}

// Helper Functions

function generateThemeCSS($theme) {
    return <<<CSS
/* Generated Theme CSS - Auto-created by Design Studio */
:root {
    --cis-primary: {$theme['primary']};
    --cis-secondary: {$theme['secondary']};
    --cis-accent: {$theme['accent']};
    --cis-success: {$theme['success']};
    --cis-warning: {$theme['warning']};
    --cis-danger: {$theme['danger']};
    --font-heading: '{$theme['font_heading']}', sans-serif;
    --font-body: '{$theme['font_body']}', sans-serif;
    --border-radius: {$theme['border_radius']};
    --density: {$theme['density']};
}

h1, h2, h3, h4, h5, h6 {
    font-family: var(--font-heading);
}

body {
    font-family: var(--font-body);
}

CSS;
}

function saveVersion($file, $content, $message) {
    $fileName = basename($file);
    $versionId = time() . '_' . substr(md5($content), 0, 8);
    $versionDir = __DIR__ . '/css-versions/' . str_replace('.css', '', $fileName);

    if (!is_dir($versionDir)) {
        mkdir($versionDir, 0755, true);
    }

    file_put_contents($versionDir . '/' . $versionId . '.css', $content);

    $meta = [
        'id' => $versionId,
        'file' => $fileName,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'size' => strlen($content)
    ];

    file_put_contents($versionDir . '/' . $versionId . '.json', json_encode($meta, JSON_PRETTY_PRINT));

    return $versionId;
}

function getVersions($file) {
    $fileName = basename($file);
    $versionDir = __DIR__ . '/css-versions/' . str_replace('.css', '', $fileName);

    if (!is_dir($versionDir)) {
        return [];
    }

    $versions = [];
    foreach (glob($versionDir . '/*.json') as $metaFile) {
        $versions[] = json_decode(file_get_contents($metaFile), true);
    }

    usort($versions, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    return $versions;
}

function callAI($prompt, $context) {
    // Placeholder for AI integration
    return [
        'suggestion' => '/* AI suggestion would appear here */',
        'explanation' => 'This is a placeholder for AI responses'
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS Design Studio - Unified Theme & Component Management</title>

    <!-- External Dependencies -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/editor/editor.main.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            overflow: hidden;
            height: 100vh;
        }

        /* Main Layout - NO SCROLLBARS */
        .studio-container {
            display: grid;
            grid-template-rows: 60px 1fr;
            height: 100vh;
            overflow: hidden;
        }

        /* Top Navigation Bar */
        .studio-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
        }

        .studio-logo {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .studio-nav {
            display: flex;
            gap: 1rem;
        }

        .nav-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }

        .nav-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .nav-btn.active {
            background: white;
            color: #667eea;
        }

        /* Main Workspace */
        .studio-workspace {
            display: grid;
            grid-template-columns: 60px 400px 1fr 50%;
            height: calc(100vh - 60px);
            overflow: hidden;
        }

        /* Left Sidebar - Tool Icons */
        .tool-sidebar {
            background: #1e1e1e;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem 0;
            gap: 1rem;
            overflow-y: auto;
        }

        .tool-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #888;
            cursor: pointer;
            border-radius: 0.5rem;
            transition: all 0.2s;
            font-size: 1.2rem;
        }

        .tool-icon:hover {
            background: #2d2d2d;
            color: #fff;
        }

        .tool-icon.active {
            background: #667eea;
            color: white;
        }

        /* Left Panel - Controls */
        .control-panel {
            background: #252525;
            color: white;
            overflow-y: auto;
            border-right: 1px solid #3d3d3d;
        }

        .panel-section {
            padding: 1.5rem;
            border-bottom: 1px solid #3d3d3d;
        }

        .panel-section h3 {
            font-size: 0.9rem;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        /* Code Editor Area */
        .code-editor-area {
            background: #1e1e1e;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .editor-tabs {
            background: #2d2d2d;
            display: flex;
            gap: 0.25rem;
            padding: 0.5rem;
            border-bottom: 1px solid #3d3d3d;
        }

        .editor-tab {
            padding: 0.5rem 1rem;
            background: #1e1e1e;
            color: #888;
            border: none;
            border-radius: 0.375rem 0.375rem 0 0;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .editor-tab:hover {
            color: #fff;
            background: #252525;
        }

        .editor-tab.active {
            background: #1e1e1e;
            color: white;
            border-bottom: 2px solid #667eea;
        }

        .editor-container {
            flex: 1;
            overflow: hidden;
        }

        #monaco-editor {
            height: 100%;
            width: 100%;
        }

        /* Live Preview Area */
        .preview-area {
            background: #f5f5f5;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .preview-toolbar {
            background: white;
            padding: 1rem;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .view-mode-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .view-mode-btn {
            padding: 0.5rem 1rem;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .view-mode-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .view-mode-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .preview-content {
            flex: 1;
            overflow: auto;
            position: relative;
        }

        /* Preview Mode: Stage (Presentation) */
        .preview-stage {
            display: none;
            align-items: center;
            justify-content: center;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
        }

        .preview-stage.active {
            display: flex;
        }

        .stage-component {
            background: white;
            padding: 3rem;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            animation: stageEntry 0.6s ease-out;
        }

        @keyframes stageEntry {
            from {
                opacity: 0;
                transform: scale(0.8) translateY(30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        /* Preview Mode: In Context */
        .preview-context {
            display: none;
            background: white;
            padding: 2rem;
        }

        .preview-context.active {
            display: block;
        }

        /* Preview Mode: Responsive Grid */
        .preview-responsive {
            display: none;
            padding: 2rem;
            gap: 2rem;
        }

        .preview-responsive.active {
            display: grid;
            grid-template-columns: 375px 768px 1fr;
        }

        .device-frame {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .device-header {
            background: #2d2d2d;
            color: white;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .device-screen {
            background: white;
            overflow: auto;
        }

        .device-phone .device-screen {
            height: 667px;
        }

        .device-tablet .device-screen {
            height: 1024px;
        }

        .device-desktop .device-screen {
            height: 900px;
        }

        /* Color Picker */
        .color-input-group {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .color-input-group input[type="color"] {
            width: 50px;
            height: 40px;
            border: 2px solid #3d3d3d;
            border-radius: 0.5rem;
            cursor: pointer;
        }

        .color-input-group input[type="text"] {
            flex: 1;
            background: #1e1e1e;
            border: 2px solid #3d3d3d;
            color: white;
            padding: 0.5rem;
            border-radius: 0.5rem;
            font-family: 'Fira Code', monospace;
        }

        /* Sliders */
        .slider-group {
            margin-bottom: 1.5rem;
        }

        .slider-group label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .slider-group input[type="range"] {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #3d3d3d;
            outline: none;
        }

        .slider-group input[type="range"]::-webkit-slider-thumb {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #667eea;
            cursor: pointer;
        }

        /* Component Library Grid */
        .component-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }

        .component-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 0.75rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .component-card:hover {
            border-color: #667eea;
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.2);
        }

        .component-thumbnail {
            background: #f9f9f9;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* AI Copilot Panel */
        .ai-panel {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            width: 400px;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            z-index: 2000;
            display: none;
        }

        .ai-panel.active {
            display: block;
        }

        .ai-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 1rem 1rem 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ai-messages {
            height: 300px;
            overflow-y: auto;
            padding: 1rem;
            background: #f9f9f9;
        }

        .ai-message {
            background: white;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .ai-input-area {
            padding: 1rem;
            border-top: 2px solid #e0e0e0;
        }

        .ai-input-area input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 0.5rem;
            font-size: 0.9rem;
        }

        /* Utility Classes */
        .hidden {
            display: none !important;
        }

        .text-muted {
            color: #888;
        }

        .mb-2 {
            margin-bottom: 0.5rem;
        }

        .mb-3 {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="studio-container">
        <!-- Top Navigation -->
        <div class="studio-header">
            <div class="studio-logo">
                <i class="fas fa-palette"></i>
                <span>CIS Design Studio</span>
            </div>
            <div class="studio-nav">
                <button class="nav-btn" onclick="saveWork()">
                    <i class="fas fa-save"></i> Save All
                </button>
                <button class="nav-btn" onclick="exportTheme()">
                    <i class="fas fa-download"></i> Export
                </button>
                <button class="nav-btn" onclick="toggleAI()">
                    <i class="fas fa-robot"></i> AI Copilot
                </button>
            </div>
        </div>

        <!-- Main Workspace -->
        <div class="studio-workspace">
            <!-- Tool Sidebar -->
            <div class="tool-sidebar">
                <div class="tool-icon active" data-tool="theme" title="Theme Builder">
                    <i class="fas fa-palette"></i>
                </div>
                <div class="tool-icon" data-tool="components" title="Components">
                    <i class="fas fa-cubes"></i>
                </div>
                <div class="tool-icon" data-tool="versions" title="Version Control">
                    <i class="fas fa-code-branch"></i>
                </div>
                <div class="tool-icon" data-tool="responsive" title="Responsive Test">
                    <i class="fas fa-mobile-screen"></i>
                </div>
                <div class="tool-icon" data-tool="css" title="CSS Editor">
                    <i class="fab fa-css3-alt"></i>
                </div>
            </div>

            <!-- Left Control Panel -->
            <div class="control-panel">
                <!-- Theme Controls -->
                <div class="panel-section" id="theme-controls">
                    <h3><i class="fas fa-palette"></i> Theme Colors</h3>

                    <div class="color-input-group">
                        <input type="color" id="color-primary" value="#667eea">
                        <input type="text" id="color-primary-text" value="#667eea" placeholder="#667eea">
                        <label>Primary</label>
                    </div>

                    <div class="color-input-group">
                        <input type="color" id="color-secondary" value="#764ba2">
                        <input type="text" id="color-secondary-text" value="#764ba2">
                        <label>Secondary</label>
                    </div>

                    <div class="color-input-group">
                        <input type="color" id="color-accent" value="#10b981">
                        <input type="text" id="color-accent-text" value="#10b981">
                        <label>Accent</label>
                    </div>

                    <button class="btn btn-sm btn-primary btn-block mb-3" onclick="generateSmartColors()">
                        <i class="fas fa-wand-magic-sparkles"></i> Smart Color Harmony
                    </button>

                    <h3><i class="fas fa-font"></i> Typography</h3>

                    <div class="mb-3">
                        <label class="text-muted mb-2">Heading Font</label>
                        <select id="font-heading" class="form-control">
                            <option>Inter</option>
                            <option>Poppins</option>
                            <option>Montserrat</option>
                            <option>Roboto</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted mb-2">Body Font</label>
                        <select id="font-body" class="form-control">
                            <option>Inter</option>
                            <option>Open Sans</option>
                            <option>Lato</option>
                        </select>
                    </div>

                    <h3><i class="fas fa-border-style"></i> Styling</h3>

                    <div class="slider-group">
                        <label>
                            <span>Border Radius</span>
                            <span id="radius-value">0.75rem</span>
                        </label>
                        <input type="range" id="border-radius" min="0" max="2" step="0.25" value="0.75">
                    </div>

                    <div class="slider-group">
                        <label>
                            <span>Spacing Density</span>
                            <span id="density-value">1x</span>
                        </label>
                        <input type="range" id="density" min="0.75" max="1.5" step="0.25" value="1">
                    </div>

                    <div class="slider-group">
                        <label>
                            <span>Shadow Depth</span>
                            <span id="shadow-value">Medium</span>
                        </label>
                        <input type="range" id="shadow-depth" min="0" max="3" step="1" value="1">
                    </div>
                </div>

                <!-- Component Library -->
                <div class="panel-section hidden" id="component-controls">
                    <h3><i class="fas fa-cubes"></i> Component Library</h3>
                    <div id="component-list"></div>
                </div>
            </div>

            <!-- Code Editor -->
            <div class="code-editor-area">
                <div class="editor-tabs">
                    <button class="editor-tab active" data-lang="html">
                        <i class="fab fa-html5"></i> HTML
                    </button>
                    <button class="editor-tab" data-lang="css">
                        <i class="fab fa-css3-alt"></i> CSS
                    </button>
                    <button class="editor-tab" data-lang="javascript">
                        <i class="fab fa-js"></i> JavaScript
                    </button>
                </div>
                <div class="editor-container">
                    <div id="monaco-editor"></div>
                </div>
            </div>

            <!-- Live Preview -->
            <div class="preview-area">
                <div class="preview-toolbar">
                    <div class="view-mode-buttons">
                        <button class="view-mode-btn active" data-mode="stage">
                            <i class="fas fa-star"></i> Stage
                        </button>
                        <button class="view-mode-btn" data-mode="context">
                            <i class="fas fa-desktop"></i> In Context
                        </button>
                        <button class="view-mode-btn" data-mode="responsive">
                            <i class="fas fa-mobile-screen"></i> Responsive
                        </button>
                    </div>
                    <button class="btn btn-sm btn-success" onclick="refreshPreview()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>

                <div class="preview-content">
                    <!-- Stage View -->
                    <div class="preview-stage active" id="preview-stage">
                        <div class="stage-component" id="stage-component">
                            <h1>Welcome to CIS Design Studio</h1>
                            <p>Start editing to see your component come alive!</p>
                            <button class="btn btn-primary">Example Button</button>
                        </div>
                    </div>

                    <!-- Context View -->
                    <div class="preview-context" id="preview-context">
                        <div id="context-preview"></div>
                    </div>

                    <!-- Responsive View -->
                    <div class="preview-responsive" id="preview-responsive">
                        <div class="device-frame device-phone">
                            <div class="device-header">
                                <i class="fas fa-mobile-screen"></i> iPhone 12 Pro (375px)
                            </div>
                            <div class="device-screen" id="phone-preview"></div>
                        </div>

                        <div class="device-frame device-tablet">
                            <div class="device-header">
                                <i class="fas fa-tablet-screen-button"></i> iPad (768px)
                            </div>
                            <div class="device-screen" id="tablet-preview"></div>
                        </div>

                        <div class="device-frame device-desktop">
                            <div class="device-header">
                                <i class="fas fa-desktop"></i> Desktop (1920px)
                            </div>
                            <div class="device-screen" id="desktop-preview"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Copilot Panel -->
    <div class="ai-panel" id="ai-panel">
        <div class="ai-header">
            <span><i class="fas fa-robot"></i> AI Copilot</span>
            <button style="background: none; border: none; color: white; cursor: pointer;" onclick="toggleAI()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="ai-messages" id="ai-messages">
            <div class="ai-message">
                <strong>AI Copilot:</strong> Hi! I can help you design components, fix CSS, generate code, and more. What would you like to create?
            </div>
        </div>
        <div class="ai-input-area">
            <input type="text" id="ai-input" placeholder="Ask AI to help you design..." onkeypress="handleAIInput(event)">
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.44.0/min/vs/loader.js"></script>

    <script>
        let editor;
        let currentMode = 'stage';
        let currentLang = 'html';
        let currentTheme = {
            primary: '#667eea',
            secondary: '#764ba2',
            accent: '#10b981',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            font_heading: 'Inter',
            font_body: 'Inter',
            border_radius: '0.75rem',
            density: 1,
            shadow_depth: 1
        };

        // Initialize Monaco Editor
        require.config({ paths: { vs: 'https://cdn.jsdelivr.net/npm/monaco-editor@0.44.0/min/vs' } });

        require(['vs/editor/editor.main'], function() {
            editor = monaco.editor.create(document.getElementById('monaco-editor'), {
                value: '<div class="cis-card">\n  <h2>Hello CIS!</h2>\n  <p>Edit this HTML and watch it update live.</p>\n  <button class="btn btn-primary">Click Me</button>\n</div>',
                language: 'html',
                theme: 'vs-dark',
                automaticLayout: true,
                minimap: { enabled: false },
                fontSize: 14
            });

            // Live update on edit
            editor.onDidChangeModelContent(() => {
                refreshPreview();
            });
        });

        // Tool Switching
        $('.tool-icon').click(function() {
            $('.tool-icon').removeClass('active');
            $(this).addClass('active');

            const tool = $(this).data('tool');
            $('.panel-section').addClass('hidden');
            $(`#${tool}-controls`).removeClass('hidden');
        });

        // Editor Tab Switching
        $('.editor-tab').click(function() {
            $('.editor-tab').removeClass('active');
            $(this).addClass('active');

            currentLang = $(this).data('lang');
            monaco.editor.setModelLanguage(editor.getModel(), currentLang);
        });

        // View Mode Switching
        $('.view-mode-btn').click(function() {
            $('.view-mode-btn').removeClass('active');
            $(this).addClass('active');

            currentMode = $(this).data('mode');
            $('.preview-stage, .preview-context, .preview-responsive').removeClass('active');
            $(`#preview-${currentMode}`).addClass('active');

            refreshPreview();
        });

        // Color Inputs - Sync
        $('#color-primary').on('input', function() {
            $('#color-primary-text').val($(this).val());
            currentTheme.primary = $(this).val();
            applyTheme();
        });

        $('#color-secondary').on('input', function() {
            $('#color-secondary-text').val($(this).val());
            currentTheme.secondary = $(this).val();
            applyTheme();
        });

        // Sliders
        $('#border-radius').on('input', function() {
            const value = $(this).val();
            $('#radius-value').text(value + 'rem');
            currentTheme.border_radius = value + 'rem';
            applyTheme();
        });

        $('#density').on('input', function() {
            const value = $(this).val();
            $('#density-value').text(value + 'x');
            currentTheme.density = parseFloat(value);
            applyTheme();
        });

        // Apply Theme to Preview
        function applyTheme() {
            const css = `
                :root {
                    --cis-primary: ${currentTheme.primary};
                    --cis-secondary: ${currentTheme.secondary};
                    --cis-accent: ${currentTheme.accent};
                    --border-radius: ${currentTheme.border_radius};
                }
                * {
                    --density: ${currentTheme.density};
                }
            `;

            // Inject into all preview frames
            ['stage-component', 'context-preview', 'phone-preview', 'tablet-preview', 'desktop-preview'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    let styleEl = el.querySelector('style.theme-style');
                    if (!styleEl) {
                        styleEl = document.createElement('style');
                        styleEl.className = 'theme-style';
                        el.prepend(styleEl);
                    }
                    styleEl.textContent = css;
                }
            });
        }

        // Smart Color Generation
        function generateSmartColors() {
            // Generate harmonious colors using color theory
            const hue = Math.random() * 360;

            // Complementary color (opposite on color wheel)
            const complementaryHue = (hue + 180) % 360;

            // Analogous colors (30° apart)
            const analogous1 = (hue + 30) % 360;
            const analogous2 = (hue - 30 + 360) % 360;

            // Use HSL for better control
            currentTheme.primary = `hsl(${hue}, 70%, 60%)`;
            currentTheme.secondary = `hsl(${complementaryHue}, 70%, 55%)`;
            currentTheme.accent = `hsl(${analogous1}, 75%, 55%)`;

            // Update UI
            $('#color-primary').val(hslToHex(currentTheme.primary));
            $('#color-secondary').val(hslToHex(currentTheme.secondary));
            $('#color-accent').val(hslToHex(currentTheme.accent));

            applyTheme();
            alert('Smart colors generated with color harmony! 🎨');
        }

        // Refresh Preview
        function refreshPreview() {
            const code = editor.getValue();

            if (currentMode === 'stage') {
                document.getElementById('stage-component').innerHTML = code;
            } else if (currentMode === 'context') {
                document.getElementById('context-preview').innerHTML = `
                    <div style="max-width: 1200px; margin: 0 auto;">
                        ${code}
                    </div>
                `;
            } else if (currentMode === 'responsive') {
                ['phone-preview', 'tablet-preview', 'desktop-preview'].forEach(id => {
                    document.getElementById(id).innerHTML = code;
                });
            }

            applyTheme();
        }

        // Save Everything
        function saveWork() {
            $.post('', {
                action: 'save_theme',
                theme_data: JSON.stringify(currentTheme)
            }, function(response) {
                if (response.success) {
                    alert('✅ ' + response.message);
                } else {
                    alert('❌ Error: ' + response.error);
                }
            });
        }

        // AI Copilot
        function toggleAI() {
            $('#ai-panel').toggleClass('active');
        }

        function handleAIInput(event) {
            if (event.key === 'Enter') {
                const input = $('#ai-input').val();
                if (!input.trim()) return;

                $('#ai-messages').append(`
                    <div class="ai-message">
                        <strong>You:</strong> ${input}
                    </div>
                `);

                $.post('', {
                    action: 'ai_assist',
                    prompt: input,
                    context: editor.getValue()
                }, function(response) {
                    if (response.success) {
                        $('#ai-messages').append(`
                            <div class="ai-message">
                                <strong>AI:</strong> ${response.data.explanation}
                            </div>
                        `);

                        // Optionally insert code suggestion
                        if (response.data.suggestion) {
                            editor.setValue(response.data.suggestion);
                        }
                    }
                });

                $('#ai-input').val('');
                $('#ai-messages').scrollTop($('#ai-messages')[0].scrollHeight);
            }
        }

        // Helper: HSL to Hex
        function hslToHex(hsl) {
            const match = hsl.match(/hsl\((\d+),\s*(\d+)%,\s*(\d+)%\)/);
            if (!match) return hsl;

            const h = parseInt(match[1]) / 360;
            const s = parseInt(match[2]) / 100;
            const l = parseInt(match[3]) / 100;

            const hue2rgb = (p, q, t) => {
                if (t < 0) t += 1;
                if (t > 1) t -= 1;
                if (t < 1/6) return p + (q - p) * 6 * t;
                if (t < 1/2) return q;
                if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                return p;
            };

            const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
            const p = 2 * l - q;

            const r = Math.round(hue2rgb(p, q, h + 1/3) * 255);
            const g = Math.round(hue2rgb(p, q, h) * 255);
            const b = Math.round(hue2rgb(p, q, h - 1/3) * 255);

            return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
        }

        // Initialize
        $(document).ready(function() {
            applyTheme();
            refreshPreview();
        });
    </script>
</body>
</html>
