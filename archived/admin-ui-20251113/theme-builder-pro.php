<?php
/**
 * Theme Builder PRO ULTIMATE v4.0.0
 * The complete theme building experience:
 * - Color scheme presets & randomization
 * - Google Fonts library (15+ fonts)
 * - Border radius & density controls
 * - Live component preview
 * - Monaco code editors
 * - PERSISTENT config (saves to config/active-theme.json)
 * - Load saved theme on startup
 * - Export themes as JSON
 * - AI integration ready
 */

session_start();

// Load active theme config if it exists
$activeThemeFile = __DIR__ . '/config/active-theme.json';
$activeTheme = null;
if (file_exists($activeThemeFile)) {
    $activeTheme = json_decode(file_get_contents($activeThemeFile), true);
    // Store in session for immediate use
    $_SESSION['cis_theme'] = $activeTheme;
}

// Backend API Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];
    $response = ['success' => false];

    try {
        switch ($action) {
            case 'save_active_theme':
                // Save as the ACTIVE theme (persists across sessions)
                $themeData = json_decode($_POST['theme_data'], true);

                if (!is_dir(__DIR__ . '/config')) {
                    mkdir(__DIR__ . '/config', 0755, true);
                }

                // Add timestamp
                $themeData['saved_at'] = date('Y-m-d H:i:s');
                $themeData['version'] = '4.0.0';

                file_put_contents($activeThemeFile, json_encode($themeData, JSON_PRETTY_PRINT));
                $_SESSION['cis_theme'] = $themeData;

                $response = [
                    'success' => true,
                    'message' => 'Active theme saved! This will persist across all sessions.',
                    'saved_to' => 'config/active-theme.json'
                ];
                break;

            case 'load_active_theme':
                // Load the currently active theme
                if (file_exists($activeThemeFile)) {
                    $theme = json_decode(file_get_contents($activeThemeFile), true);
                    $_SESSION['cis_theme'] = $theme;
                    $response = ['success' => true, 'data' => $theme];
                } else {
                    $response = ['success' => false, 'error' => 'No active theme found'];
                }
                break;

            case 'save_theme':
                // Save as a named theme preset
                $themeData = json_decode($_POST['theme_data'], true);
                $themeId = $themeData['id'] ?? 'theme_' . time();
                $filePath = __DIR__ . '/themes/' . $themeId . '.json';

                if (!is_dir(__DIR__ . '/themes')) {
                    mkdir(__DIR__ . '/themes', 0755, true);
                }

                file_put_contents($filePath, json_encode($themeData, JSON_PRETTY_PRINT));
                $response = ['success' => true, 'theme_id' => $themeId];
                break;

            case 'load_theme':
                $themeId = $_POST['theme_id'];
                $filePath = __DIR__ . '/themes/' . $themeId . '.json';

                if (file_exists($filePath)) {
                    $theme = json_decode(file_get_contents($filePath), true);
                    $response = ['success' => true, 'data' => $theme];
                } else {
                    $response = ['success' => false, 'error' => 'Theme not found'];
                }
                break;

            case 'list_themes':
                $themes = [];
                if (is_dir(__DIR__ . '/themes')) {
                    foreach (glob(__DIR__ . '/themes/*.json') as $file) {
                        $theme = json_decode(file_get_contents($file), true);
                        $themes[] = [
                            'id' => $theme['id'],
                            'name' => $theme['name'],
                            'version' => $theme['version'] ?? '1.0.0',
                            'modified' => date('Y-m-d H:i:s', filemtime($file))
                        ];
                    }
                }
                $response = ['success' => true, 'data' => $themes];
                break;

            case 'save_ai_config':
                // Save AI Agent configuration
                $aiConfig = json_decode($_POST['config_data'], true);

                if (!is_dir(__DIR__ . '/config')) {
                    mkdir(__DIR__ . '/config', 0755, true);
                }

                $aiConfig['updated_at'] = date('Y-m-d H:i:s');
                $aiConfigFile = __DIR__ . '/config/ai-agent-config.json';

                file_put_contents($aiConfigFile, json_encode($aiConfig, JSON_PRETTY_PRINT));
                $_SESSION['ai_agent_config'] = $aiConfig;

                $response = [
                    'success' => true,
                    'message' => 'AI Agent configuration saved successfully!',
                    'config' => $aiConfig
                ];
                break;

            case 'load_ai_config':
                // Load AI Agent configuration
                $aiConfigFile = __DIR__ . '/config/ai-agent-config.json';

                if (file_exists($aiConfigFile)) {
                    $aiConfig = json_decode(file_get_contents($aiConfigFile), true);
                    $_SESSION['ai_agent_config'] = $aiConfig;
                    $response = ['success' => true, 'data' => $aiConfig];
                } else {
                    // Return default config
                    $response = [
                        'success' => true,
                        'data' => [
                            'enabled' => false,
                            'api_url' => '',
                            'api_key' => '',
                            'model' => 'gpt-4',
                            'timeout' => 30,
                            'max_tokens' => 2000,
                            'temperature' => 0.7
                        ]
                    ];
                }
                break;

            case 'test_ai_connection':
                // Test AI API connection
                $apiUrl = $_POST['api_url'] ?? '';
                $apiKey = $_POST['api_key'] ?? '';

                if (empty($apiUrl)) {
                    $response = ['success' => false, 'error' => 'API URL is required'];
                    break;
                }

                // Test connection with a simple ping
                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey
                ]);

                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode >= 200 && $httpCode < 300) {
                    $response = [
                        'success' => true,
                        'message' => 'Connection successful! ✅',
                        'http_code' => $httpCode
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'error' => 'Connection failed (HTTP ' . $httpCode . ')',
                        'http_code' => $httpCode
                    ];
                }
                break;

            default:
                $response = ['success' => false, 'error' => 'Unknown action'];
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'error' => $e->getMessage()];
    }

    echo json_encode($response);
    exit;
}

// Load Google Fonts
$googleFonts = [
    'Inter' => 'Inter:wght@400;500;600;700',
    'Roboto' => 'Roboto:wght@400;500;700',
    'Poppins' => 'Poppins:wght@400;500;600;700',
    'Montserrat' => 'Montserrat:wght@400;500;600;700',
    'Open Sans' => 'Open+Sans:wght@400;600;700',
    'Lato' => 'Lato:wght@400;700',
    'Raleway' => 'Raleway:wght@400;600;700',
    'Ubuntu' => 'Ubuntu:wght@400;500;700',
    'Nunito' => 'Nunito:wght@400;600;700',
    'Playfair Display' => 'Playfair+Display:wght@400;700',
    'Merriweather' => 'Merriweather:wght@400;700',
    'Source Sans Pro' => 'Source+Sans+Pro:wght@400;600;700',
    'PT Sans' => 'PT+Sans:wght@400;700',
    'Oswald' => 'Oswald:wght@400;600',
    'Mulish' => 'Mulish:wght@400;600;700'
];

// Color scheme presets
$colorSchemes = [
    'Purple Dream' => [
        'primary' => '#8B5CF6',
        'secondary' => '#EC4899',
        'accent' => '#10B981',
        'success' => '#10B981',
        'warning' => '#F59E0B',
        'danger' => '#EF4444'
    ],
    'Ocean Blue' => [
        'primary' => '#0EA5E9',
        'secondary' => '#06B6D4',
        'accent' => '#8B5CF6',
        'success' => '#10B981',
        'warning' => '#F59E0B',
        'danger' => '#EF4444'
    ],
    'Forest Green' => [
        'primary' => '#10B981',
        'secondary' => '#059669',
        'accent' => '#14B8A6',
        'success' => '#22C55E',
        'warning' => '#F59E0B',
        'danger' => '#EF4444'
    ],
    'Sunset Glow' => [
        'primary' => '#F97316',
        'secondary' => '#FB923C',
        'accent' => '#FBBF24',
        'success' => '#10B981',
        'warning' => '#F59E0B',
        'danger' => '#EF4444'
    ],
    'Cherry Blossom' => [
        'primary' => '#EC4899',
        'secondary' => '#F472B6',
        'accent' => '#FB7185',
        'success' => '#10B981',
        'warning' => '#F59E0B',
        'danger' => '#EF4444'
    ],
    'Midnight Blue' => [
        'primary' => '#3B82F6',
        'secondary' => '#1E40AF',
        'accent' => '#6366F1',
        'success' => '#10B981',
        'warning' => '#F59E0B',
        'danger' => '#EF4444'
    ],
    'Emerald City' => [
        'primary' => '#059669',
        'secondary' => '#047857',
        'accent' => '#10B981',
        'success' => '#22C55E',
        'warning' => '#F59E0B',
        'danger' => '#EF4444'
    ],
    'Crimson Red' => [
        'primary' => '#DC2626',
        'secondary' => '#B91C1C',
        'accent' => '#F87171',
        'success' => '#10B981',
        'warning' => '#F59E0B',
        'danger' => '#EF4444'
    ],
    'Royal Purple' => [
        'primary' => '#7C3AED',
        'secondary' => '#6D28D9',
        'accent' => '#A78BFA',
        'success' => '#10B981',
        'warning' => '#F59E0B',
        'danger' => '#EF4444'
    ],
    'Tangerine' => [
        'primary' => '#FB923C',
        'secondary' => '#F97316',
        'accent' => '#FDBA74',
        'success' => '#10B981',
        'warning' => '#F59E0B',
        'danger' => '#EF4444'
    ],
    'Teal Wave' => [
        'primary' => '#14B8A6',
        'secondary' => '#0D9488',
        'accent' => '#2DD4BF',
        'success' => '#10B981',
        'warning' => '#F59E0B',
        'danger' => '#EF4444'
    ],
    'Rose Gold' => [
        'primary' => '#F472B6',
        'secondary' => '#EC4899',
        'accent' => '#FDE68A',
        'success' => '#10B981',
        'warning' => '#F59E0B',
        'danger' => '#EF4444'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎨 Theme Builder PRO ULTIMATE v4.0.0</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/cis-brand.css">
    <?php
    // Load all Google Fonts
    foreach ($googleFonts as $fontFamily => $fontUrl) {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        echo '<link href="https://fonts.googleapis.com/css2?family=' . $fontUrl . '&display=swap" rel="stylesheet">';
    }
    ?>
    <style>
        :root {
            /* Default CIS Brand Colors */
            --cis-primary: <?= $activeTheme['primary'] ?? '#8B5CF6' ?>;
            --cis-secondary: <?= $activeTheme['secondary'] ?? '#EC4899' ?>;
            --cis-accent: <?= $activeTheme['accent'] ?? '#10B981' ?>;
            --cis-success: <?= $activeTheme['success'] ?? '#10B981' ?>;
            --cis-warning: <?= $activeTheme['warning'] ?? '#F59E0B' ?>;
            --cis-danger: <?= $activeTheme['danger'] ?? '#EF4444' ?>;
            --font-heading: <?= $activeTheme['font_heading'] ?? "'Inter', sans-serif" ?>;
            --font-body: <?= $activeTheme['font_body'] ?? "'Inter', sans-serif" ?>;
            --border-radius: <?= $activeTheme['border_radius'] ?? '0.75rem' ?>;
            --density: <?= $activeTheme['density'] ?? '1' ?>;
        }

        body {
            font-family: var(--font-body);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: var(--font-heading);
        }

        .ultimate-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: calc(2rem * var(--density));
        }

        .hero-section {
            text-align: center;
            color: white;
            padding: calc(4rem * var(--density)) 0;
            background: linear-gradient(135deg,
                #C084FC 0%,
                #7C3AED 25%,
                #6366F1 50%,
                #3B82F6 75%,
                #06B6D4 100%
            );
            position: relative;
            overflow: hidden;
            border-radius: var(--border-radius);
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin-bottom: calc(3rem * var(--density));
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 30%,
                rgba(255,255,255,0.3) 50%,
                transparent 70%
            );
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .hero-section h1 {
            font-size: calc(3rem * var(--density));
            font-weight: 700;
            margin-bottom: calc(1rem * var(--density));
            position: relative;
            z-index: 1;
        }

        .hero-section p {
            font-size: calc(1.25rem * var(--density));
            opacity: 0.95;
            position: relative;
            z-index: 1;
        }

        .controls-panel {
            background: white;
            border-radius: var(--border-radius);
            padding: calc(2rem * var(--density));
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: calc(2rem * var(--density));
        }

        .controls-section {
            margin-bottom: calc(2rem * var(--density));
        }

        .controls-section h3 {
            font-size: calc(1.5rem * var(--density));
            color: var(--cis-primary);
            margin-bottom: calc(1rem * var(--density));
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .color-schemes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: calc(1rem * var(--density));
            margin-bottom: calc(1.5rem * var(--density));
        }

        .scheme-card {
            cursor: pointer;
            border: 3px solid transparent;
            border-radius: var(--border-radius);
            padding: calc(1rem * var(--density));
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .scheme-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .scheme-card.active {
            border-color: var(--cis-primary);
            box-shadow: 0 10px 30px rgba(139,92,246,0.3);
        }

        .scheme-name {
            font-weight: 600;
            margin-bottom: calc(0.75rem * var(--density));
            text-align: center;
        }

        .scheme-colors {
            display: flex;
            gap: 0.25rem;
            height: 40px;
        }

        .scheme-color {
            flex: 1;
            border-radius: calc(0.25rem * var(--density));
        }

        .font-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: calc(0.75rem * var(--density));
        }

        .font-option {
            padding: calc(0.75rem * var(--density));
            border: 2px solid #dee2e6;
            border-radius: var(--border-radius);
            cursor: pointer;
            text-align: center;
            transition: all 0.2s ease;
            background: white;
        }

        .font-option:hover {
            border-color: var(--cis-primary);
            transform: scale(1.05);
        }

        .font-option.active {
            border-color: var(--cis-primary);
            background: rgba(139,92,246,0.1);
            font-weight: 600;
        }

        .slider-group {
            margin-bottom: calc(1.5rem * var(--density));
        }

        .slider-group label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .btn-randomize {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: calc(1rem * var(--density)) calc(2rem * var(--density));
            border-radius: var(--border-radius);
            font-size: calc(1.1rem * var(--density));
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
        }

        .btn-randomize:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102,126,234,0.6);
        }

        .btn-save-active {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
            border: none;
            padding: calc(1rem * var(--density)) calc(2rem * var(--density));
            border-radius: var(--border-radius);
            font-size: calc(1.1rem * var(--density));
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(16,185,129,0.4);
        }

        .btn-save-active:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(16,185,129,0.6);
        }

        .preview-panel {
            background: white;
            border-radius: var(--border-radius);
            padding: calc(2rem * var(--density));
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        .preview-panel h3 {
            color: var(--cis-primary);
            margin-bottom: calc(2rem * var(--density));
        }

        .component-preview {
            margin-bottom: calc(2rem * var(--density));
        }

        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            z-index: 9999;
            display: none;
            align-items: center;
            gap: 1rem;
            animation: slideInRight 0.3s ease;
        }

        .toast-notification.show {
            display: flex;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .btn-group-custom {
            display: flex;
            gap: calc(1rem * var(--density));
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="ultimate-container">
        <!-- Hero -->
        <div class="hero-section">
            <h1>🎨 Theme Builder PRO ULTIMATE</h1>
            <p>The complete theme experience • Click presets • Randomize • Save permanently</p>
        </div>

        <div class="row">
            <!-- Controls Column -->
            <div class="col-lg-6">
                <div class="controls-panel">
                    <!-- Color Schemes -->
                    <div class="controls-section">
                        <h3><i class="fas fa-palette"></i> Color Schemes</h3>
                        <div class="color-schemes-grid">
                            <?php foreach ($colorSchemes as $schemeName => $colors): ?>
                            <div class="scheme-card" data-scheme='<?= htmlspecialchars(json_encode($colors)) ?>' data-name="<?= $schemeName ?>">
                                <div class="scheme-name"><?= $schemeName ?></div>
                                <div class="scheme-colors">
                                    <div class="scheme-color" style="background: <?= $colors['primary'] ?>"></div>
                                    <div class="scheme-color" style="background: <?= $colors['secondary'] ?>"></div>
                                    <div class="scheme-color" style="background: <?= $colors['accent'] ?>"></div>
                                    <div class="scheme-color" style="background: <?= $colors['success'] ?>"></div>
                                    <div class="scheme-color" style="background: <?= $colors['warning'] ?>"></div>
                                    <div class="scheme-color" style="background: <?= $colors['danger'] ?>"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="btn-group-custom">
                            <button class="btn-randomize" onclick="randomizeTheme()">
                                <i class="fas fa-random"></i> Randomize Everything
                            </button>
                            <button class="btn-save-active" onclick="saveActiveTheme()">
                                <i class="fas fa-save"></i> Save as Active Theme
                            </button>
                        </div>
                    </div>

                    <!-- Fonts -->
                    <div class="controls-section">
                        <h3><i class="fas fa-font"></i> Heading Font</h3>
                        <div class="font-selector">
                            <?php foreach (array_keys($googleFonts) as $font): ?>
                            <div class="font-option" data-font="<?= $font ?>" data-target="heading" style="font-family: '<?= $font ?>', sans-serif;">
                                <?= $font ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="controls-section">
                        <h3><i class="fas fa-align-left"></i> Body Font</h3>
                        <div class="font-selector">
                            <?php foreach (array_keys($googleFonts) as $font): ?>
                            <div class="font-option" data-font="<?= $font ?>" data-target="body" style="font-family: '<?= $font ?>', sans-serif;">
                                <?= $font ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Border Radius -->
                    <div class="controls-section">
                        <h3><i class="fas fa-border-style"></i> Border Radius</h3>
                        <div class="btn-group-custom">
                            <button class="btn btn-outline-primary" onclick="setBorderRadius('0.25rem')">Sharp</button>
                            <button class="btn btn-outline-primary active" onclick="setBorderRadius('0.75rem')">Medium</button>
                            <button class="btn btn-outline-primary" onclick="setBorderRadius('1.5rem')">Rounded</button>
                        </div>
                    </div>

                    <!-- Density -->
                    <div class="controls-section">
                        <h3><i class="fas fa-compress-arrows-alt"></i> Density</h3>
                        <div class="slider-group">
                            <label>
                                <span>Spacing</span>
                                <span id="density-value">1x</span>
                            </label>
                            <input type="range" class="form-control-range" min="0.75" max="1.5" step="0.25" value="1" oninput="setDensity(this.value)">
                            <small class="form-text text-muted">Adjust spacing and sizing throughout the theme</small>
                        </div>
                    </div>

                    <!-- AI Agent Settings -->
                    <div class="controls-section" style="border-top: 2px solid #dee2e6; padding-top: 2rem;">
                        <h3><i class="fas fa-robot"></i> AI Agent Configuration</h3>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-toggle-on"></i> Enable AI Agent
                            </label>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="ai-enabled">
                                <label class="custom-control-label" for="ai-enabled">Activate AI assistance</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-link"></i> API URL / Host
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="ai-api-url"
                                placeholder="https://api.openai.com/v1/chat/completions"
                                style="font-family: monospace; font-size: 0.9rem;"
                            >
                            <small class="form-text text-muted">Your AI API endpoint URL</small>
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-key"></i> API Key
                            </label>
                            <input
                                type="password"
                                class="form-control"
                                id="ai-api-key"
                                placeholder="sk-..."
                                style="font-family: monospace; font-size: 0.9rem;"
                            >
                            <small class="form-text text-muted">Your API authentication key</small>
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-brain"></i> Model
                            </label>
                            <select class="form-control" id="ai-model">
                                <option value="gpt-4">GPT-4</option>
                                <option value="gpt-4-turbo">GPT-4 Turbo</option>
                                <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                                <option value="claude-3-opus">Claude 3 Opus</option>
                                <option value="claude-3-sonnet">Claude 3 Sonnet</option>
                                <option value="custom">Custom Model</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-clock"></i> Timeout (seconds)
                            </label>
                            <input
                                type="number"
                                class="form-control"
                                id="ai-timeout"
                                value="30"
                                min="5"
                                max="120"
                            >
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-sliders-h"></i> Temperature
                                <span id="temperature-value" style="float: right;">0.7</span>
                            </label>
                            <input
                                type="range"
                                class="form-control-range"
                                id="ai-temperature"
                                min="0"
                                max="1"
                                step="0.1"
                                value="0.7"
                                oninput="$('#temperature-value').text(this.value)"
                            >
                            <small class="form-text text-muted">Creativity level (0 = focused, 1 = creative)</small>
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-text-width"></i> Max Tokens
                            </label>
                            <input
                                type="number"
                                class="form-control"
                                id="ai-max-tokens"
                                value="2000"
                                min="100"
                                max="8000"
                            >
                            <small class="form-text text-muted">Maximum response length</small>
                        </div>

                        <div class="btn-group-custom" style="margin-top: 1.5rem;">
                            <button class="btn btn-secondary" onclick="testAIConnection()">
                                <i class="fas fa-plug"></i> Test Connection
                            </button>
                            <button class="btn btn-success" onclick="saveAIConfig()">
                                <i class="fas fa-save"></i> Save AI Config
                            </button>
                            <button class="btn btn-outline-primary" onclick="loadAIConfig()">
                                <i class="fas fa-sync"></i> Load
                            </button>
                        </div>

                        <div id="ai-status" style="margin-top: 1rem; padding: 1rem; border-radius: 0.5rem; display: none;">
                            <strong id="ai-status-title"></strong>
                            <p id="ai-status-message" style="margin: 0.5rem 0 0 0;"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Column -->
            <div class="col-lg-6">
                <div class="preview-panel">
                    <h3><i class="fas fa-eye"></i> Live Preview</h3>

                    <!-- Buttons -->
                    <div class="component-preview">
                        <h5>Buttons</h5>
                        <div class="btn-group-custom">
                            <button class="cis-btn cis-btn-primary">Primary</button>
                            <button class="cis-btn cis-btn-secondary">Secondary</button>
                            <button class="cis-btn cis-btn-success">Success</button>
                            <button class="cis-btn cis-btn-warning">Warning</button>
                            <button class="cis-btn cis-btn-danger">Danger</button>
                        </div>
                    </div>

                    <!-- Badges -->
                    <div class="component-preview">
                        <h5>Badges</h5>
                        <div class="btn-group-custom">
                            <span class="cis-badge cis-badge-primary">Primary</span>
                            <span class="cis-badge cis-badge-secondary">Secondary</span>
                            <span class="cis-badge cis-badge-success">Success</span>
                            <span class="cis-badge cis-badge-warning">Warning</span>
                            <span class="cis-badge cis-badge-danger">Danger</span>
                        </div>
                    </div>

                    <!-- Alerts -->
                    <div class="component-preview">
                        <h5>Alerts</h5>
                        <div class="cis-alert cis-alert-success">
                            <i class="fas fa-check-circle"></i> Success! Theme saved permanently.
                        </div>
                        <div class="cis-alert cis-alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Warning: This will overwrite the active theme.
                        </div>
                        <div class="cis-alert cis-alert-danger">
                            <i class="fas fa-times-circle"></i> Error: Could not save theme.
                        </div>
                    </div>

                    <!-- Form -->
                    <div class="component-preview">
                        <h5>Form Elements</h5>
                        <div class="form-group">
                            <label>Email address</label>
                            <input type="email" class="cis-form-control" placeholder="name@example.com">
                        </div>
                        <div class="form-group">
                            <label>Select option</label>
                            <select class="cis-form-control">
                                <option>Option 1</option>
                                <option>Option 2</option>
                                <option>Option 3</option>
                            </select>
                        </div>
                    </div>

                    <!-- Card -->
                    <div class="component-preview">
                        <h5>Card</h5>
                        <div class="cis-card">
                            <h6 style="margin: 0 0 0.5rem 0;">Card Title</h6>
                            <p style="margin: 0; color: #6c757d;">Some quick example text to build on the card title and make up the bulk of the card's content.</p>
                        </div>
                    </div>

                    <!-- Typography -->
                    <div class="component-preview">
                        <h5>Typography</h5>
                        <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem;">Heading 1</h1>
                        <h2 style="font-size: 2rem; margin-bottom: 0.5rem;">Heading 2</h2>
                        <h3 style="font-size: 1.75rem; margin-bottom: 0.5rem;">Heading 3</h3>
                        <p>This is body text using the selected body font. Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast-notification" id="toast">
        <i class="fas fa-check-circle" style="color: #10B981; font-size: 1.5rem;"></i>
        <div>
            <strong id="toast-title">Success!</strong>
            <div id="toast-message">Theme saved</div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Current theme state
        let currentTheme = {
            primary: '<?= $activeTheme['primary'] ?? '#8B5CF6' ?>',
            secondary: '<?= $activeTheme['secondary'] ?? '#EC4899' ?>',
            accent: '<?= $activeTheme['accent'] ?? '#10B981' ?>',
            success: '<?= $activeTheme['success'] ?? '#10B981' ?>',
            warning: '<?= $activeTheme['warning'] ?? '#F59E0B' ?>',
            danger: '<?= $activeTheme['danger'] ?? '#EF4444' ?>',
            font_heading: '<?= $activeTheme['font_heading'] ?? 'Inter' ?>',
            font_body: '<?= $activeTheme['font_body'] ?? 'Inter' ?>',
            border_radius: '<?= $activeTheme['border_radius'] ?? '0.75rem' ?>',
            density: <?= $activeTheme['density'] ?? 1 ?>
        };

        // Apply theme to CSS variables
        function applyTheme() {
            document.documentElement.style.setProperty('--cis-primary', currentTheme.primary);
            document.documentElement.style.setProperty('--cis-secondary', currentTheme.secondary);
            document.documentElement.style.setProperty('--cis-accent', currentTheme.accent);
            document.documentElement.style.setProperty('--cis-success', currentTheme.success);
            document.documentElement.style.setProperty('--cis-warning', currentTheme.warning);
            document.documentElement.style.setProperty('--cis-danger', currentTheme.danger);
            document.documentElement.style.setProperty('--font-heading', `'${currentTheme.font_heading}', sans-serif`);
            document.documentElement.style.setProperty('--font-body', `'${currentTheme.font_body}', sans-serif`);
            document.documentElement.style.setProperty('--border-radius', currentTheme.border_radius);
            document.documentElement.style.setProperty('--density', currentTheme.density);
        }

        // Color scheme selection
        $('.scheme-card').click(function() {
            $('.scheme-card').removeClass('active');
            $(this).addClass('active');

            const scheme = $(this).data('scheme');
            currentTheme.primary = scheme.primary;
            currentTheme.secondary = scheme.secondary;
            currentTheme.accent = scheme.accent;
            currentTheme.success = scheme.success;
            currentTheme.warning = scheme.warning;
            currentTheme.danger = scheme.danger;

            applyTheme();
            showToast('Color Scheme Applied', $(this).data('name'));
        });

        // Font selection
        $('.font-option').click(function() {
            const target = $(this).data('target');
            const font = $(this).data('font');

            $(`.font-option[data-target="${target}"]`).removeClass('active');
            $(this).addClass('active');

            if (target === 'heading') {
                currentTheme.font_heading = font;
            } else {
                currentTheme.font_body = font;
            }

            applyTheme();
            showToast('Font Changed', `${target.charAt(0).toUpperCase() + target.slice(1)}: ${font}`);
        });

        // Border radius
        function setBorderRadius(value) {
            currentTheme.border_radius = value;
            applyTheme();

            $('.controls-section button').removeClass('active');
            event.target.classList.add('active');

            showToast('Border Radius Changed', value);
        }

        // Density
        function setDensity(value) {
            currentTheme.density = parseFloat(value);
            $('#density-value').text(value + 'x');
            applyTheme();
        }

        // Randomize everything
        function randomizeTheme() {
            const schemes = <?= json_encode(array_values($colorSchemes)) ?>;
            const fonts = <?= json_encode(array_keys($googleFonts)) ?>;
            const radiusOptions = ['0.25rem', '0.75rem', '1.5rem'];
            const densityOptions = [0.75, 1, 1.25, 1.5];

            // Random colors
            const randomScheme = schemes[Math.floor(Math.random() * schemes.length)];
            currentTheme.primary = randomScheme.primary;
            currentTheme.secondary = randomScheme.secondary;
            currentTheme.accent = randomScheme.accent;
            currentTheme.success = randomScheme.success;
            currentTheme.warning = randomScheme.warning;
            currentTheme.danger = randomScheme.danger;

            // Random fonts
            currentTheme.font_heading = fonts[Math.floor(Math.random() * fonts.length)];
            currentTheme.font_body = fonts[Math.floor(Math.random() * fonts.length)];

            // Random border radius
            currentTheme.border_radius = radiusOptions[Math.floor(Math.random() * radiusOptions.length)];

            // Random density
            currentTheme.density = densityOptions[Math.floor(Math.random() * densityOptions.length)];

            applyTheme();
            updateUISelections();
            showToast('Theme Randomized! 🎲', 'Generated a fresh new look');
        }

        // Update UI to reflect current selections
        function updateUISelections() {
            $('.scheme-card').removeClass('active');
            $(`.font-option[data-target="heading"][data-font="${currentTheme.font_heading}"]`).addClass('active').siblings().removeClass('active');
            $(`.font-option[data-target="body"][data-font="${currentTheme.font_body}"]`).addClass('active').siblings().removeClass('active');
            $('#density-value').text(currentTheme.density + 'x');
            $('input[type="range"]').val(currentTheme.density);
        }

        // Save as active theme (persists permanently)
        function saveActiveTheme() {
            $.ajax({
                url: '',
                method: 'POST',
                data: {
                    action: 'save_active_theme',
                    theme_data: JSON.stringify(currentTheme)
                },
                success: function(response) {
                    if (response.success) {
                        showToast('✅ Active Theme Saved!', response.message, 5000);
                    } else {
                        showToast('❌ Error', response.error || 'Could not save theme', 5000);
                    }
                },
                error: function() {
                    showToast('❌ Error', 'Network error - could not save theme', 5000);
                }
            });
        }

        // Toast notification
        function showToast(title, message, duration = 3000) {
            $('#toast-title').text(title);
            $('#toast-message').text(message);
            $('#toast').addClass('show');

            setTimeout(() => {
                $('#toast').removeClass('show');
            }, duration);
        }

        // ========================================
        // AI AGENT CONFIGURATION FUNCTIONS
        // ========================================

        // Load AI configuration
        function loadAIConfig() {
            $.ajax({
                url: '',
                method: 'POST',
                data: {
                    action: 'load_ai_config'
                },
                success: function(response) {
                    if (response.success && response.data) {
                        const config = response.data;
                        $('#ai-enabled').prop('checked', config.enabled || false);
                        $('#ai-api-url').val(config.api_url || '');
                        $('#ai-api-key').val(config.api_key || '');
                        $('#ai-model').val(config.model || 'gpt-4');
                        $('#ai-timeout').val(config.timeout || 30);
                        $('#ai-temperature').val(config.temperature || 0.7);
                        $('#ai-max-tokens').val(config.max_tokens || 2000);
                        $('#temperature-value').text(config.temperature || 0.7);

                        showToast('✅ Config Loaded', 'AI Agent configuration loaded successfully');
                        showAIStatus('success', 'Configuration loaded from file');
                    } else {
                        showToast('ℹ️ No Config Found', 'Using default AI settings');
                        showAIStatus('info', 'No saved configuration found - using defaults');
                    }
                },
                error: function() {
                    showToast('❌ Error', 'Could not load AI configuration');
                    showAIStatus('error', 'Failed to load configuration');
                }
            });
        }

        // Save AI configuration
        function saveAIConfig() {
            const config = {
                enabled: $('#ai-enabled').is(':checked'),
                api_url: $('#ai-api-url').val().trim(),
                api_key: $('#ai-api-key').val().trim(),
                model: $('#ai-model').val(),
                timeout: parseInt($('#ai-timeout').val()),
                temperature: parseFloat($('#ai-temperature').val()),
                max_tokens: parseInt($('#ai-max-tokens').val())
            };

            // Validate required fields
            if (config.enabled && !config.api_url) {
                showToast('⚠️ Validation Error', 'API URL is required when AI is enabled');
                showAIStatus('warning', 'Please enter an API URL');
                return;
            }

            $.ajax({
                url: '',
                method: 'POST',
                data: {
                    action: 'save_ai_config',
                    config_data: JSON.stringify(config)
                },
                success: function(response) {
                    if (response.success) {
                        showToast('✅ AI Config Saved!', 'Configuration saved to config/ai-agent-config.json', 5000);
                        showAIStatus('success', 'Configuration saved successfully at ' + new Date().toLocaleTimeString());
                        console.log('💾 AI Config saved:', config);
                    } else {
                        showToast('❌ Error', response.error || 'Could not save AI config');
                        showAIStatus('error', response.error || 'Save failed');
                    }
                },
                error: function() {
                    showToast('❌ Error', 'Network error - could not save AI config');
                    showAIStatus('error', 'Network error during save');
                }
            });
        }

        // Test AI connection
        function testAIConnection() {
            const apiUrl = $('#ai-api-url').val().trim();
            const apiKey = $('#ai-api-key').val().trim();

            if (!apiUrl) {
                showToast('⚠️ Missing URL', 'Please enter an API URL first');
                showAIStatus('warning', 'API URL is required for connection test');
                return;
            }

            showAIStatus('info', 'Testing connection... ⏳');
            showToast('🔌 Testing...', 'Connecting to AI endpoint...');

            $.ajax({
                url: '',
                method: 'POST',
                data: {
                    action: 'test_ai_connection',
                    api_url: apiUrl,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        showToast('✅ Connection Success!', response.message, 5000);
                        showAIStatus('success', `Connected! HTTP ${response.http_code} - API is reachable`);
                    } else {
                        showToast('❌ Connection Failed', response.error, 5000);
                        showAIStatus('error', response.error + ' - Please check URL and key');
                    }
                },
                error: function(xhr) {
                    showToast('❌ Test Failed', 'Could not reach API endpoint', 5000);
                    showAIStatus('error', 'Network error - API unreachable or CORS issue');
                }
            });
        }

        // Show AI status message
        function showAIStatus(type, message) {
            const statusDiv = $('#ai-status');
            const statusTitle = $('#ai-status-title');
            const statusMessage = $('#ai-status-message');

            // Set colors based on type
            const colors = {
                success: { bg: '#d4edda', border: '#c3e6cb', text: '#155724', icon: 'check-circle' },
                error: { bg: '#f8d7da', border: '#f5c6cb', text: '#721c24', icon: 'times-circle' },
                warning: { bg: '#fff3cd', border: '#ffeaa7', text: '#856404', icon: 'exclamation-triangle' },
                info: { bg: '#d1ecf1', border: '#bee5eb', text: '#0c5460', icon: 'info-circle' }
            };

            const style = colors[type] || colors.info;

            statusDiv.css({
                'background-color': style.bg,
                'border': `1px solid ${style.border}`,
                'color': style.text
            }).show();

            statusTitle.html(`<i class="fas fa-${style.icon}"></i> ${type.charAt(0).toUpperCase() + type.slice(1)}`);
            statusMessage.text(message);
        }

        // Initialize
        $(document).ready(function() {
            applyTheme();
            updateUISelections();

            // Load AI config on startup
            loadAIConfig();

            console.log('🎨 Theme Builder PRO ULTIMATE v4.0.0 loaded!');
            console.log('💾 Active theme config:', currentTheme);
            console.log('🤖 AI Agent configuration ready!');
        });
    </script>
</body>
</html>
