<?php
/**
 * CIS ULTIMATE Theme Builder
 * Professional theme editor with live preview, randomization, and font library
 *
 * @package CIS\Modules\AdminUI
 * @version 3.0.0 - ULTIMATE EDITION
 * @date October 2025
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }

// Load saved theme or use default
$savedTheme = $_SESSION['cis_theme'] ?? [
    'primary' => '#A855F7',
    'secondary' => '#3B82F6',
    'accent' => '#F59E0B',
    'success' => '#10B981',
    'warning' => '#F59E0B',
    'danger' => '#EF4444',
    'font_heading' => 'Inter',
    'font_body' => 'Inter',
    'border_radius' => 'medium'
];

// Handle save theme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_theme') {
    $_SESSION['cis_theme'] = json_decode($_POST['theme_data'], true);
    echo json_encode(['success' => true, 'message' => 'Theme saved successfully!']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎨 CIS Ultimate Theme Builder</title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css" crossorigin="anonymous" />

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" />

    <!-- Google Fonts Loader -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link id="google-fonts-heading" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link id="google-fonts-body" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- CIS Brand -->
    <link rel="stylesheet" href="/modules/admin-ui/css/cis-brand.css">

    <style>
        /* Ultimate Theme Builder Styles */
        body {
            font-family: var(--font-body, 'Inter', sans-serif);
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: var(--font-heading, 'Inter', sans-serif);
        }

        .ultimate-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 2rem;
            max-width: 1800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .control-panel {
            position: sticky;
            top: 2rem;
            height: fit-content;
            max-height: calc(100vh - 4rem);
            overflow-y: auto;
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .preview-panel {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .control-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .control-section:last-child {
            border-bottom: none;
        }

        .control-title {
            font-size: 0.875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #A855F7;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .color-preset-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .color-preset {
            aspect-ratio: 1;
            border-radius: 0.5rem;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.2s;
            position: relative;
        }

        .color-preset:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .color-preset.active {
            border-color: #000;
            transform: scale(1.05);
        }

        .color-preset::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 1.2rem;
            opacity: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .color-preset.active::after {
            opacity: 1;
        }

        .font-selector {
            display: grid;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .font-option {
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            font-weight: 600;
        }

        .font-option:hover {
            border-color: #A855F7;
            background: #f9f5ff;
        }

        .font-option.active {
            border-color: #A855F7;
            background: linear-gradient(135deg, #E879F9 0%, #A855F7 100%);
            color: white;
        }

        .style-option-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .style-option {
            padding: 0.75rem 0.5rem;
            border: 2px solid #e0e0e0;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .style-option:hover {
            border-color: #3B82F6;
            background: #f0f9ff;
        }

        .style-option.active {
            border-color: #3B82F6;
            background: linear-gradient(135deg, #60A5FA 0%, #3B82F6 100%);
            color: white;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-randomize {
            background: linear-gradient(135deg, #F59E0B 0%, #EF4444 100%);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .btn-randomize:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
        }

        .btn-save {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .component-showcase {
            display: grid;
            gap: 2rem;
        }

        @media (max-width: 1024px) {
            .ultimate-container {
                grid-template-columns: 1fr;
            }

            .control-panel {
                position: static;
                max-height: none;
            }
        }
    </style>
</head>
<body>

    <!-- HERO -->
    <div class="cis-hero">
        <div class="cis-hero-content" style="max-width: 1800px; margin: 0 auto; padding: 0 2rem;">
            <h1><i class="fas fa-magic"></i> Ultimate Theme Builder</h1>
            <p class="lead">Click styles, randomize, customize fonts - see changes instantly!</p>
        </div>
    </div>

    <!-- ULTIMATE BUILDER -->
    <div class="ultimate-container">

        <!-- CONTROL PANEL -->
        <div class="control-panel">

            <!-- QUICK RANDOMIZE -->
            <div class="control-section">
                <div class="control-title">
                    <i class="fas fa-dice"></i> Quick Actions
                </div>
                <div class="action-buttons">
                    <button class="btn-randomize" onclick="randomizeTheme()">
                        <i class="fas fa-random"></i> Randomize
                    </button>
                    <button class="btn-save" onclick="saveTheme()">
                        <i class="fas fa-save"></i> Save Theme
                    </button>
                </div>
            </div>

            <!-- COLOR SCHEMES -->
            <div class="control-section">
                <div class="control-title">
                    <i class="fas fa-palette"></i> Color Schemes
                </div>
                <div class="color-preset-grid" id="colorSchemes">
                    <!-- Generated by JS -->
                </div>
            </div>

            <!-- FONTS -->
            <div class="control-section">
                <div class="control-title">
                    <i class="fas fa-font"></i> Heading Font
                </div>
                <div class="font-selector" id="headingFonts">
                    <!-- Generated by JS -->
                </div>
            </div>

            <div class="control-section">
                <div class="control-title">
                    <i class="fas fa-paragraph"></i> Body Font
                </div>
                <div class="font-selector" id="bodyFonts">
                    <!-- Generated by JS -->
                </div>
            </div>

            <!-- BORDER RADIUS -->
            <div class="control-section">
                <div class="control-title">
                    <i class="fas fa-square"></i> Border Style
                </div>
                <div class="style-option-grid">
                    <div class="style-option" data-radius="sharp" onclick="setBorderRadius('sharp')">
                        <div style="width: 30px; height: 30px; background: #A855F7; margin: 0 auto;"></div>
                        Sharp
                    </div>
                    <div class="style-option active" data-radius="medium" onclick="setBorderRadius('medium')">
                        <div style="width: 30px; height: 30px; background: #A855F7; border-radius: 6px; margin: 0 auto;"></div>
                        Medium
                    </div>
                    <div class="style-option" data-radius="rounded" onclick="setBorderRadius('rounded')">
                        <div style="width: 30px; height: 30px; background: #A855F7; border-radius: 15px; margin: 0 auto;"></div>
                        Rounded
                    </div>
                </div>
            </div>

            <!-- COMPONENT DENSITY -->
            <div class="control-section">
                <div class="control-title">
                    <i class="fas fa-compress"></i> Component Density
                </div>
                <div class="style-option-grid">
                    <div class="style-option" data-density="compact" onclick="setDensity('compact')">
                        <i class="fas fa-compress-alt"></i><br>Compact
                    </div>
                    <div class="style-option active" data-density="comfortable" onclick="setDensity('comfortable')">
                        <i class="fas fa-grip-horizontal"></i><br>Comfortable
                    </div>
                    <div class="style-option" data-density="spacious" onclick="setDensity('spacious')">
                        <i class="fas fa-expand-alt"></i><br>Spacious
                    </div>
                </div>
            </div>

        </div>

        <!-- PREVIEW PANEL WITH ALL COMPONENTS -->
        <div class="preview-panel">
            <div class="component-showcase" id="componentShowcase">

                <!-- BUTTONS -->
                <div class="cis-card">
                    <div class="cis-card-header">
                        <h2 class="cis-card-title"><i class="fas fa-hand-pointer"></i> Buttons</h2>
                    </div>
                    <div class="cis-card-body">
                        <button class="cis-btn cis-btn-primary mr-2 mb-2">
                            <i class="fas fa-check"></i> Primary Button
                        </button>
                        <button class="cis-btn cis-btn-secondary mr-2 mb-2">
                            <i class="fas fa-info-circle"></i> Secondary Button
                        </button>
                        <button class="cis-btn cis-btn-success mr-2 mb-2">
                            <i class="fas fa-check-circle"></i> Success Button
                        </button>
                        <button class="cis-btn cis-btn-warning mr-2 mb-2">
                            <i class="fas fa-exclamation-triangle"></i> Warning Button
                        </button>
                    </div>
                </div>

                <!-- BADGES -->
                <div class="cis-card">
                    <div class="cis-card-header">
                        <h2 class="cis-card-title"><i class="fas fa-tag"></i> Badges & Labels</h2>
                    </div>
                    <div class="cis-card-body">
                        <span class="cis-badge cis-badge-primary mr-2 mb-2">
                            <i class="fas fa-star"></i> Primary
                        </span>
                        <span class="cis-badge cis-badge-success mr-2 mb-2">
                            <i class="fas fa-check"></i> Success
                        </span>
                        <span class="cis-badge cis-badge-warning mr-2 mb-2">
                            <i class="fas fa-exclamation"></i> Warning
                        </span>
                        <span class="cis-badge cis-badge-danger mr-2 mb-2">
                            <i class="fas fa-times"></i> Danger
                        </span>
                    </div>
                </div>

                <!-- ALERTS -->
                <div class="cis-card">
                    <div class="cis-card-header">
                        <h2 class="cis-card-title"><i class="fas fa-bell"></i> Alerts</h2>
                    </div>
                    <div class="cis-card-body">
                        <div class="cis-alert cis-alert-success mb-3">
                            <div class="cis-alert-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="cis-alert-content"><strong>Success!</strong> Your changes have been saved.</div>
                        </div>
                        <div class="cis-alert cis-alert-warning mb-3">
                            <div class="cis-alert-icon"><i class="fas fa-exclamation-triangle"></i></div>
                            <div class="cis-alert-content"><strong>Warning:</strong> Please review before proceeding.</div>
                        </div>
                        <div class="cis-alert cis-alert-info">
                            <div class="cis-alert-icon"><i class="fas fa-info-circle"></i></div>
                            <div class="cis-alert-content"><strong>Info:</strong> Here's some helpful information.</div>
                        </div>
                    </div>
                </div>

                <!-- FORMS -->
                <div class="cis-card">
                    <div class="cis-card-header">
                        <h2 class="cis-card-title"><i class="fas fa-edit"></i> Form Elements</h2>
                    </div>
                    <div class="cis-card-body">
                        <div class="cis-form-group">
                            <label class="cis-form-label">Input Field</label>
                            <input type="text" class="cis-form-control" placeholder="Enter text here">
                        </div>
                        <div class="cis-form-group">
                            <label class="cis-form-label">Select Menu</label>
                            <select class="cis-form-control">
                                <option>Option 1</option>
                                <option>Option 2</option>
                                <option>Option 3</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- CARDS -->
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="cis-card">
                            <div class="cis-card-header">
                                <h3 class="cis-card-title" style="font-size: 1.125rem;">
                                    <i class="fas fa-star"></i> Card Title
                                </h3>
                            </div>
                            <div class="cis-card-body">
                                <p>This is a sample card with your theme applied.</p>
                                <button class="cis-btn cis-btn-primary cis-btn-sm">Learn More</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="cis-card">
                            <div class="cis-card-header">
                                <h3 class="cis-card-title" style="font-size: 1.125rem;">
                                    <i class="fas fa-chart-line"></i> Analytics
                                </h3>
                            </div>
                            <div class="cis-card-body">
                                <p>Track your metrics with beautiful components.</p>
                                <button class="cis-btn cis-btn-secondary cis-btn-sm">View Stats</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="cis-card">
                            <div class="cis-card-header">
                                <h3 class="cis-card-title" style="font-size: 1.125rem;">
                                    <i class="fas fa-cog"></i> Settings
                                </h3>
                            </div>
                            <div class="cis-card-body">
                                <p>Configure your application settings.</p>
                                <button class="cis-btn cis-btn-success cis-btn-sm">Configure</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TYPOGRAPHY -->
                <div class="cis-card">
                    <div class="cis-card-header">
                        <h2 class="cis-card-title"><i class="fas fa-font"></i> Typography</h2>
                    </div>
                    <div class="cis-card-body">
                        <h1>Heading 1 - Large Display</h1>
                        <h2>Heading 2 - Section Title</h2>
                        <h3>Heading 3 - Subsection</h3>
                        <p class="lead">This is a lead paragraph with larger text for emphasis.</p>
                        <p>This is regular body text demonstrating your chosen font family and styling.</p>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

    <script>
        // THEME DATA
        const currentTheme = <?php echo json_encode($savedTheme); ?>;

        // COLOR SCHEMES
        const colorSchemes = [
            { name: 'Purple Dream', primary: '#A855F7', secondary: '#3B82F6', accent: '#F59E0B' },
            { name: 'Ocean Blue', primary: '#3B82F6', secondary: '#06B6D4', accent: '#10B981' },
            { name: 'Forest Green', primary: '#10B981', secondary: '#059669', accent: '#F59E0B' },
            { name: 'Sunset Glow', primary: '#F59E0B', secondary: '#EF4444', accent: '#EC4899' },
            { name: 'Cherry Blossom', primary: '#EC4899', secondary: '#F472B6', accent: '#A855F7' },
            { name: 'Midnight Blue', primary: '#1E40AF', secondary: '#3B82F6', accent: '#F59E0B' },
            { name: 'Emerald City', primary: '#059669', secondary: '#10B981', accent: '#FBBF24' },
            { name: 'Crimson Red', primary: '#DC2626', secondary: '#EF4444', accent: '#F59E0B' },
            { name: 'Royal Purple', primary: '#7C3AED', secondary: '#8B5CF6', accent: '#EC4899' },
            { name: 'Tangerine', primary: '#F97316', secondary: '#FB923C', accent: '#FBBF24' },
            { name: 'Teal Wave', primary: '#0D9488', secondary: '#14B8A6', accent: '#F59E0B' },
            { name: 'Rose Gold', primary: '#BE185D', secondary: '#EC4899', accent: '#FBBF24' }
        ];

        // GOOGLE FONTS
        const fonts = [
            'Inter', 'Roboto', 'Poppins', 'Montserrat', 'Open Sans',
            'Lato', 'Raleway', 'Ubuntu', 'Nunito', 'Playfair Display',
            'Merriweather', 'Source Sans Pro', 'PT Sans', 'Oswald', 'Mulish'
        ];

        // INITIALIZE
        document.addEventListener('DOMContentLoaded', function() {
            renderColorSchemes();
            renderFontOptions();
            applyTheme();
        });

        // RENDER COLOR SCHEMES
        function renderColorSchemes() {
            const container = document.getElementById('colorSchemes');
            container.innerHTML = colorSchemes.map((scheme, index) => `
                <div class="color-preset ${scheme.primary === currentTheme.primary ? 'active' : ''}"
                     style="background: linear-gradient(135deg, ${scheme.primary} 0%, ${scheme.secondary} 50%, ${scheme.accent} 100%);"
                     onclick="applyColorScheme(${index})"
                     title="${scheme.name}">
                </div>
            `).join('');
        }

        // RENDER FONT OPTIONS
        function renderFontOptions() {
            const headingContainer = document.getElementById('headingFonts');
            const bodyContainer = document.getElementById('bodyFonts');

            const headingHTML = fonts.slice(0, 5).map(font => `
                <div class="font-option ${font === currentTheme.font_heading ? 'active' : ''}"
                     style="font-family: '${font}', sans-serif;"
                     onclick="setHeadingFont('${font}')">
                    ${font}
                </div>
            `).join('');

            const bodyHTML = fonts.slice(0, 5).map(font => `
                <div class="font-option ${font === currentTheme.font_body ? 'active' : ''}"
                     style="font-family: '${font}', sans-serif;"
                     onclick="setBodyFont('${font}')">
                    ${font}
                </div>
            `).join('');

            headingContainer.innerHTML = headingHTML;
            bodyContainer.innerHTML = bodyHTML;
        }

        // APPLY COLOR SCHEME
        function applyColorScheme(index) {
            const scheme = colorSchemes[index];
            currentTheme.primary = scheme.primary;
            currentTheme.secondary = scheme.secondary;
            currentTheme.accent = scheme.accent;

            applyTheme();
            renderColorSchemes();
        }

        // SET FONTS
        function setHeadingFont(font) {
            currentTheme.font_heading = font;
            loadGoogleFont(font, 'heading');
            applyTheme();
            renderFontOptions();
        }

        function setBodyFont(font) {
            currentTheme.font_body = font;
            loadGoogleFont(font, 'body');
            applyTheme();
            renderFontOptions();
        }

        // LOAD GOOGLE FONT
        function loadGoogleFont(font, type) {
            const linkId = `google-fonts-${type}`;
            const link = document.getElementById(linkId);
            const weights = type === 'heading' ? '400;600;700;800' : '400;500;600';
            link.href = `https://fonts.googleapis.com/css2?family=${font.replace(/ /g, '+')}:wght@${weights}&display=swap`;
        }

        // SET BORDER RADIUS
        function setBorderRadius(style) {
            currentTheme.border_radius = style;
            applyTheme();

            document.querySelectorAll('.style-option[data-radius]').forEach(el => {
                el.classList.remove('active');
            });
            document.querySelector(`[data-radius="${style}"]`).classList.add('active');
        }

        // SET DENSITY
        function setDensity(style) {
            currentTheme.density = style;
            applyTheme();

            document.querySelectorAll('.style-option[data-density]').forEach(el => {
                el.classList.remove('active');
            });
            document.querySelector(`[data-density="${style}"]`).classList.add('active');
        }

        // APPLY THEME TO DOM
        function applyTheme() {
            const root = document.documentElement;

            // Colors
            root.style.setProperty('--brand-primary', currentTheme.primary);
            root.style.setProperty('--brand-secondary', currentTheme.secondary);
            root.style.setProperty('--brand-accent', currentTheme.accent);

            // Gradients
            root.style.setProperty('--gradient-purple', `linear-gradient(135deg, ${lightenColor(currentTheme.primary, 20)} 0%, ${currentTheme.primary} 100%)`);
            root.style.setProperty('--gradient-blue', `linear-gradient(135deg, ${lightenColor(currentTheme.secondary, 20)} 0%, ${currentTheme.secondary} 100%)`);
            root.style.setProperty('--gradient-sunset', `linear-gradient(135deg, ${lightenColor(currentTheme.accent, 20)} 0%, ${currentTheme.accent} 100%)`);

            // Fonts
            root.style.setProperty('--font-heading', `'${currentTheme.font_heading}', sans-serif`);
            root.style.setProperty('--font-body', `'${currentTheme.font_body}', sans-serif`);

            // Border Radius
            const radiusMap = {
                'sharp': '0.25rem',
                'medium': '0.75rem',
                'rounded': '1.5rem'
            };
            root.style.setProperty('--radius-lg', radiusMap[currentTheme.border_radius] || '0.75rem');

            // Density
            const densityMap = {
                'compact': { sm: '0.25rem', md: '0.5rem', lg: '1rem' },
                'comfortable': { sm: '0.5rem', md: '1rem', lg: '1.5rem' },
                'spacious': { sm: '0.75rem', md: '1.5rem', lg: '2rem' }
            };
            const density = densityMap[currentTheme.density] || densityMap.comfortable;
            root.style.setProperty('--spacing-sm', density.sm);
            root.style.setProperty('--spacing-md', density.md);
            root.style.setProperty('--spacing-lg', density.lg);
        }

        // RANDOMIZE THEME
        function randomizeTheme() {
            const randomScheme = colorSchemes[Math.floor(Math.random() * colorSchemes.length)];
            const randomHeadingFont = fonts[Math.floor(Math.random() * fonts.length)];
            const randomBodyFont = fonts[Math.floor(Math.random() * fonts.length)];
            const randomRadius = ['sharp', 'medium', 'rounded'][Math.floor(Math.random() * 3)];

            currentTheme.primary = randomScheme.primary;
            currentTheme.secondary = randomScheme.secondary;
            currentTheme.accent = randomScheme.accent;
            currentTheme.font_heading = randomHeadingFont;
            currentTheme.font_body = randomBodyFont;
            currentTheme.border_radius = randomRadius;

            loadGoogleFont(randomHeadingFont, 'heading');
            loadGoogleFont(randomBodyFont, 'body');

            applyTheme();
            renderColorSchemes();
            renderFontOptions();

            // Update border radius UI
            document.querySelectorAll('.style-option[data-radius]').forEach(el => el.classList.remove('active'));
            document.querySelector(`[data-radius="${randomRadius}"]`).classList.add('active');

            // Show notification
            alert('🎲 Theme randomized! Click Save to keep this theme.');
        }

        // SAVE THEME
        function saveTheme() {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=save_theme&theme_data=${encodeURIComponent(JSON.stringify(currentTheme))}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                }
            });
        }

        // HELPER: Lighten Color
        function lightenColor(color, percent) {
            const num = parseInt(color.replace('#', ''), 16);
            const amt = Math.round(2.55 * percent);
            const R = Math.min(255, (num >> 16) + amt);
            const G = Math.min(255, (num >> 8 & 0x00FF) + amt);
            const B = Math.min(255, (num & 0x0000FF) + amt);
            return '#' + (0x1000000 + R * 0x10000 + G * 0x100 + B).toString(16).slice(1);
        }
    </script>

</body>
</html>
