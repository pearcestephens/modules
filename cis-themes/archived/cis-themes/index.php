<?php
/**
 * CIS Theme System - Main Entry Point
 *
 * Routes to the active theme or theme selector
 */

// Ensure session is started (ThemeEngine uses $_SESSION)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: Prevent direct access if not properly configured
if (!defined('CIS_THEMES_ROOT')) {
    define('CIS_THEMES_ROOT', __DIR__);
}

// Load theme engine
require_once CIS_THEMES_ROOT . '/engine/ThemeEngine.php';

// Initialize
$themeEngine = \CIS\Themes\ThemeEngine::getInstance();

// Get requested theme or default (align with ThemeEngine session key 'cis_theme')
$requestedTheme = $_GET['theme'] ?? ($_SESSION['cis_theme'] ?? 'professional-dark');

// Check if theme exists
$availableThemes = $themeEngine->getAvailableThemes(); // returns [ slug => config ]
$availableSlugs = array_keys($availableThemes);

if (!in_array($requestedTheme, $availableSlugs, true)) {
    // Theme doesn't exist, show theme selector
    showThemeSelector($availableThemes);
    exit;
}

// Switch to requested theme
$themeEngine->switchTheme($requestedTheme);

// Redirect to theme's index
$themeIndexPath = CIS_THEMES_ROOT . '/themes/' . $requestedTheme . '/index.php';

if (file_exists($themeIndexPath)) {
    include $themeIndexPath;
} else {
    die("Theme '{$requestedTheme}' is missing index.php");
}

/**
 * Show theme selector page
 */
function showThemeSelector($themes) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>CIS Theme System - Theme Selector</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                color: #f1f5f9;
                min-height: 100vh;
                padding: 40px 20px;
            }
            .container {
                max-width: 1200px;
                margin: 0 auto;
            }
            h1 {
                font-size: 48px;
                font-weight: 700;
                text-align: center;
                margin-bottom: 16px;
                background: linear-gradient(135deg, #0ea5e9, #6366f1);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            .subtitle {
                text-align: center;
                color: #94a3b8;
                margin-bottom: 60px;
                font-size: 18px;
            }
            .theme-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                gap: 24px;
                margin-bottom: 40px;
            }
            .theme-card {
                background: #1e293b;
                border-radius: 12px;
                padding: 24px;
                border: 2px solid #334155;
                transition: all 0.3s ease;
                cursor: pointer;
                text-decoration: none;
                color: inherit;
                display: block;
            }
            .theme-card:hover {
                transform: translateY(-8px);
                box-shadow: 0 20px 40px rgba(0,0,0,0.4);
                border-color: #0ea5e9;
            }
            .theme-name {
                font-size: 24px;
                font-weight: 700;
                margin-bottom: 8px;
                color: #0ea5e9;
            }
            .theme-version {
                font-size: 12px;
                color: #94a3b8;
                margin-bottom: 12px;
            }
            .theme-description {
                color: #cbd5e1;
                line-height: 1.6;
                margin-bottom: 16px;
            }
            .theme-features {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-bottom: 16px;
            }
            .feature-badge {
                padding: 4px 10px;
                background: rgba(14, 165, 233, 0.15);
                color: #0ea5e9;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
            }
            .view-btn {
                display: inline-block;
                padding: 10px 20px;
                background: #0ea5e9;
                color: white;
                border-radius: 8px;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.2s ease;
            }
            .view-btn:hover {
                background: #0284c7;
                transform: scale(1.05);
            }
            .stats {
                text-align: center;
                margin-top: 60px;
                padding-top: 40px;
                border-top: 1px solid #334155;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 40px;
                max-width: 800px;
                margin: 0 auto;
            }
            .stat-value {
                font-size: 36px;
                font-weight: 700;
                color: #0ea5e9;
            }
            .stat-label {
                color: #94a3b8;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🎨 CIS Theme System</h1>
            <p class="subtitle">Choose a theme to preview and customize your dashboard</p>

            <div class="theme-grid">
                <?php foreach ($themes as $slug => $theme): ?>
                <a href="?theme=<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" class="theme-card">
                    <div class="theme-name"><?php echo htmlspecialchars($theme['name'] ?? $slug, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="theme-version">v<?php echo htmlspecialchars($theme['version'] ?? '1.0.0', ENT_QUOTES, 'UTF-8'); ?> by <?php echo htmlspecialchars($theme['author'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="theme-description"><?php echo htmlspecialchars($theme['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>

                    <div class="theme-features">
                        <?php foreach (($theme['features'] ?? []) as $feature): ?>
                        <span class="feature-badge"><?php echo htmlspecialchars($feature, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    </div>

                    <span class="view-btn">View Theme →</span>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="stats">
                <h2 style="margin-bottom: 40px; font-size: 28px;">System Overview</h2>
                <div class="stats-grid">
                    <div>
                        <div class="stat-value"><?php echo (int)count($themes); ?></div>
                        <div class="stat-label">Themes Available</div>
                    </div>
                    <div>
                        <div class="stat-value">15+</div>
                        <div class="stat-label">Layout Variations</div>
                    </div>
                    <div>
                        <div class="stat-value">100%</div>
                        <div class="stat-label">Responsive</div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
