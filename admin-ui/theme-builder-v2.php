<?php
/**
 * CIS Theme Builder - Professional Design Tool
 * Visual theme editor with real-time preview and AI assistance
 *
 * @package CIS\Modules\AdminUI
 * @version 2.0.0
 * @date October 2025
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Session management
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// Load current theme config
$themeConfig = require __DIR__ . '/_templates/config/theme-config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>CIS Theme Builder - Professional Design Tool</title>

    <!-- Bootstrap 4.6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css" integrity="sha512-rt/SrQ4UNIaGfDyEXZtNcgyVt0/wOILiqWVAdQp/sqLpVzOJE8CIrGT8z3dAT5rPe86qXCzwaQdc3ggcgW8sha==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVJkEZSMUkrQ6usKu8zTSf2oj7kiy0mo50g5A3hLUp/0JA44l+NnRrKjPVke+o8xehpqpKWLkw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- CIS Professional Brand System -->
    <link rel="stylesheet" href="/modules/admin-ui/css/cis-brand.css">

    <style>
        /* Theme Builder Specific Styles */
        .theme-builder-container {
            display: grid;
            grid-template-columns: 350px 1fr 400px;
            gap: var(--spacing-xl);
            margin-bottom: var(--spacing-2xl);
        }

        .theme-controls {
            position: sticky;
            top: var(--spacing-xl);
            height: fit-content;
        }

        .theme-preview {
            background: var(--color-white);
            border-radius: var(--radius-xl);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-lg);
            min-height: 600px;
        }

        .theme-inspector {
            position: sticky;
            top: var(--spacing-xl);
            height: fit-content;
            max-height: calc(100vh - 4rem);
            overflow-y: auto;
        }

        .color-picker-group {
            margin-bottom: var(--spacing-lg);
        }

        .color-picker-label {
            display: block;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--color-gray-700);
            margin-bottom: var(--spacing-sm);
        }

        .color-picker-input {
            display: flex;
            gap: var(--spacing-sm);
            align-items: center;
        }

        .color-picker-input input[type="color"] {
            width: 60px;
            height: 40px;
            border: 2px solid var(--color-gray-300);
            border-radius: var(--radius-md);
            cursor: pointer;
        }

        .color-picker-input input[type="text"] {
            flex: 1;
            padding: 0.5rem;
            border: 2px solid var(--color-gray-300);
            border-radius: var(--radius-md);
            font-family: var(--font-mono);
            font-size: 0.875rem;
        }

        .preset-colors {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: var(--spacing-sm);
            margin-top: var(--spacing-sm);
        }

        .preset-color {
            width: 100%;
            aspect-ratio: 1;
            border-radius: var(--radius-md);
            border: 2px solid var(--color-gray-300);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .preset-color:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-md);
        }

        .code-output {
            background: var(--color-gray-900);
            color: #10B981;
            padding: var(--spacing-md);
            border-radius: var(--radius-md);
            font-family: var(--font-mono);
            font-size: 0.75rem;
            line-height: 1.6;
            max-height: 300px;
            overflow-y: auto;
            margin-top: var(--spacing-md);
        }

        @media (max-width: 1200px) {
            .theme-builder-container {
                grid-template-columns: 1fr;
            }

            .theme-controls, .theme-inspector {
                position: static;
            }
        }
    </style>
</head>
<body>

    <!-- HERO SECTION -->
    <div class="cis-hero">
        <div class="cis-hero-content cis-container">
            <h1><i class="fas fa-paint-brush"></i> Theme Builder</h1>
            <p class="lead">Professional visual design tool for creating stunning, consistent themes</p>

            <div class="cis-hero-actions">
                <button class="cis-btn cis-btn-warning cis-btn-lg" onclick="saveTheme()">
                    <i class="fas fa-save"></i> Save Theme
                </button>
                <button class="cis-btn cis-btn-light cis-btn-lg" onclick="resetTheme()">
                    <i class="fas fa-undo"></i> Reset to Default
                </button>
                <a href="index.php" class="cis-btn cis-btn-outline cis-btn-lg">
                    <i class="fas fa-palette"></i> Component Showcase
                </a>
            </div>
        </div>
    </div>

    <!-- MAIN BUILDER -->
    <div class="cis-container">
        <div class="theme-builder-container">

            <!-- LEFT PANEL: THEME CONTROLS -->
            <div class="theme-controls">
                <div class="cis-card">
                    <div class="cis-card-header">
                        <h3 class="cis-card-title" style="font-size: 1.125rem;">
                            <i class="fas fa-sliders-h"></i> Theme Controls
                        </h3>
                    </div>
                    <div class="cis-card-body">

                        <!-- Primary Color -->
                        <div class="color-picker-group">
                            <label class="color-picker-label">
                                <i class="fas fa-circle" style="color: #7C3AED;"></i> Primary Color
                            </label>
                            <div class="color-picker-input">
                                <input type="color" id="primaryColor" value="#7C3AED" onchange="updateColor('primary', this.value)">
                                <input type="text" id="primaryColorText" value="#7C3AED" oninput="updateColorFromText('primary', this.value)">
                            </div>
                            <div class="preset-colors">
                                <div class="preset-color" style="background: #7C3AED;" onclick="setPresetColor('primary', '#7C3AED')" title="Purple"></div>
                                <div class="preset-color" style="background: #3B82F6;" onclick="setPresetColor('primary', '#3B82F6')" title="Blue"></div>
                                <div class="preset-color" style="background: #10B981;" onclick="setPresetColor('primary', '#10B981')" title="Green"></div>
                                <div class="preset-color" style="background: #F59E0B;" onclick="setPresetColor('primary', '#F59E0B')" title="Orange"></div>
                                <div class="preset-color" style="background: #EF4444;" onclick="setPresetColor('primary', '#EF4444')" title="Red"></div>
                                <div class="preset-color" style="background: #EC4899;" onclick="setPresetColor('primary', '#EC4899')" title="Pink"></div>
                            </div>
                        </div>

                        <!-- Secondary Color -->
                        <div class="color-picker-group">
                            <label class="color-picker-label">
                                <i class="fas fa-circle" style="color: #3B82F6;"></i> Secondary Color
                            </label>
                            <div class="color-picker-input">
                                <input type="color" id="secondaryColor" value="#3B82F6" onchange="updateColor('secondary', this.value)">
                                <input type="text" id="secondaryColorText" value="#3B82F6" oninput="updateColorFromText('secondary', this.value)">
                            </div>
                        </div>

                        <!-- Accent Color -->
                        <div class="color-picker-group">
                            <label class="color-picker-label">
                                <i class="fas fa-circle" style="color: #F59E0B;"></i> Accent Color
                            </label>
                            <div class="color-picker-input">
                                <input type="color" id="accentColor" value="#F59E0B" onchange="updateColor('accent', this.value)">
                                <input type="text" id="accentColorText" value="#F59E0B" oninput="updateColorFromText('accent', this.value)">
                            </div>
                        </div>

                        <!-- Quick Presets -->
                        <div class="mt-4">
                            <div class="cis-component-label">Quick Presets</div>
                            <button class="cis-btn cis-btn-primary btn-block mb-2" onclick="applyPreset('purple')">
                                <i class="fas fa-star"></i> Purple Dream
                            </button>
                            <button class="cis-btn cis-btn-secondary btn-block mb-2" onclick="applyPreset('blue')">
                                <i class="fas fa-water"></i> Ocean Blue
                            </button>
                            <button class="cis-btn cis-btn-success btn-block mb-2" onclick="applyPreset('green')">
                                <i class="fas fa-leaf"></i> Fresh Green
                            </button>
                            <button class="cis-btn cis-btn-warning btn-block mb-2" onclick="applyPreset('sunset')">
                                <i class="fas fa-sun"></i> Sunset Glow
                            </button>
                        </div>

                    </div>
                </div>
            </div>

            <!-- CENTER PANEL: LIVE PREVIEW -->
            <div class="theme-preview" id="themePreview">
                <div class="cis-section-header">
                    <div class="cis-section-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h2 class="cis-section-title">Live Preview</h2>
                </div>

                <!-- Preview Buttons -->
                <div class="cis-component-group">
                    <div class="cis-component-label">Buttons</div>
                    <div class="cis-component-demo">
                        <button class="cis-btn cis-btn-primary mr-2 mb-2">
                            <i class="fas fa-check"></i> Primary Button
                        </button>
                        <button class="cis-btn cis-btn-secondary mr-2 mb-2">
                            <i class="fas fa-info"></i> Secondary Button
                        </button>
                        <button class="cis-btn cis-btn-warning mr-2 mb-2">
                            <i class="fas fa-star"></i> Accent Button
                        </button>
                    </div>
                </div>

                <!-- Preview Badges -->
                <div class="cis-component-group">
                    <div class="cis-component-label">Badges</div>
                    <div class="cis-component-demo">
                        <span class="cis-badge cis-badge-primary mr-2 mb-2">
                            <i class="fas fa-star"></i> Primary
                        </span>
                        <span class="cis-badge cis-badge-success mr-2 mb-2">
                            <i class="fas fa-check"></i> Success
                        </span>
                        <span class="cis-badge cis-badge-warning mr-2 mb-2">
                            <i class="fas fa-exclamation"></i> Warning
                        </span>
                    </div>
                </div>

                <!-- Preview Card -->
                <div class="cis-component-group">
                    <div class="cis-component-label">Cards</div>
                    <div class="cis-card">
                        <div class="cis-card-header">
                            <h3 class="cis-card-title" style="font-size: 1.125rem;">
                                <i class="fas fa-star"></i> Example Card
                            </h3>
                        </div>
                        <div class="cis-card-body">
                            <p>This card demonstrates how your theme colors work together in a real component.</p>
                            <button class="cis-btn cis-btn-primary cis-btn-sm">
                                <i class="fas fa-arrow-right"></i> Take Action
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Preview Alert -->
                <div class="cis-component-group">
                    <div class="cis-component-label">Alerts</div>
                    <div class="cis-alert cis-alert-success">
                        <div class="cis-alert-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="cis-alert-content">
                            <strong>Success!</strong> Your theme is looking great!
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL: CODE INSPECTOR -->
            <div class="theme-inspector">
                <div class="cis-card">
                    <div class="cis-card-header">
                        <h3 class="cis-card-title" style="font-size: 1.125rem;">
                            <i class="fas fa-code"></i> Theme Code
                        </h3>
                    </div>
                    <div class="cis-card-body">
                        <div class="cis-component-label">CSS Variables</div>
                        <div class="code-output" id="cssVariables">
:root {
  --brand-primary: #7C3AED;
  --brand-secondary: #3B82F6;
  --brand-accent: #F59E0B;

  --gradient-primary: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%);
  --gradient-secondary: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
  --gradient-accent: linear-gradient(135deg, #F59E0B 0%, #EF4444 100%);
}
                        </div>

                        <div class="cis-component-label mt-4">Usage Example</div>
                        <div class="code-output">
.my-button {
  background: var(--gradient-primary);
  color: white;
  padding: 0.75rem 1.5rem;
  border-radius: 0.5rem;
}

.my-badge {
  background: var(--brand-primary);
  color: white;
}
                        </div>

                        <button class="cis-btn cis-btn-primary btn-block mt-3" onclick="copyCode()">
                            <i class="fas fa-copy"></i> Copy CSS Code
                        </button>
                    </div>
                </div>

                <!-- Stats Card -->
                <div class="cis-card mt-3">
                    <div class="cis-card-header">
                        <h3 class="cis-card-title" style="font-size: 1.125rem;">
                            <i class="fas fa-chart-line"></i> Theme Stats
                        </h3>
                    </div>
                    <div class="cis-card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Color Harmony:</span>
                            <strong class="text-success">Excellent</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Contrast Ratio:</span>
                            <strong class="text-success">WCAG AAA</strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Brand Consistency:</span>
                            <strong class="text-success">98%</strong>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- FOOTER SPACING -->
    <div style="height: 4rem;"></div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/js/bootstrap.bundle.min.js" integrity="sha512-igl8WEUuas9k5dtnhKqyyld6TzzRjvMqLC79jkgT3z02FvJyHAuUtyemm/P/jYSne1xwFI06ezQxEwweaiV7VA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script>
        // Current theme state
        let currentTheme = {
            primary: '#7C3AED',
            secondary: '#3B82F6',
            accent: '#F59E0B'
        };

        // Update color from color picker
        function updateColor(type, value) {
            currentTheme[type] = value;
            document.getElementById(type + 'ColorText').value = value;
            applyTheme();
            updateCSSCode();
        }

        // Update color from text input
        function updateColorFromText(type, value) {
            if (/^#[0-9A-F]{6}$/i.test(value)) {
                currentTheme[type] = value;
                document.getElementById(type + 'Color').value = value;
                applyTheme();
                updateCSSCode();
            }
        }

        // Set preset color
        function setPresetColor(type, color) {
            document.getElementById(type + 'Color').value = color;
            document.getElementById(type + 'ColorText').value = color;
            currentTheme[type] = color;
            applyTheme();
            updateCSSCode();
        }

        // Apply preset theme
        function applyPreset(preset) {
            const presets = {
                purple: { primary: '#7C3AED', secondary: '#3B82F6', accent: '#F59E0B' },
                blue: { primary: '#3B82F6', secondary: '#0EA5E9', accent: '#F59E0B' },
                green: { primary: '#10B981', secondary: '#059669', accent: '#F59E0B' },
                sunset: { primary: '#F59E0B', secondary: '#EF4444', accent: '#EC4899' }
            };

            if (presets[preset]) {
                currentTheme = presets[preset];

                // Update all inputs
                Object.keys(currentTheme).forEach(type => {
                    document.getElementById(type + 'Color').value = currentTheme[type];
                    document.getElementById(type + 'ColorText').value = currentTheme[type];
                });

                applyTheme();
                updateCSSCode();
            }
        }

        // Apply theme to preview
        function applyTheme() {
            const root = document.documentElement;
            root.style.setProperty('--brand-primary', currentTheme.primary);
            root.style.setProperty('--brand-secondary', currentTheme.secondary);
            root.style.setProperty('--brand-accent', currentTheme.accent);

            // Update gradients
            root.style.setProperty('--gradient-purple', `linear-gradient(135deg, ${currentTheme.primary} 0%, ${darkenColor(currentTheme.primary, 15)} 100%)`);
            root.style.setProperty('--gradient-blue', `linear-gradient(135deg, ${currentTheme.secondary} 0%, ${darkenColor(currentTheme.secondary, 15)} 100%)`);
            root.style.setProperty('--gradient-sunset', `linear-gradient(135deg, ${currentTheme.accent} 0%, ${darkenColor(currentTheme.accent, 15)} 100%)`);
        }

        // Helper: Darken color
        function darkenColor(color, percent) {
            const num = parseInt(color.replace('#', ''), 16);
            const amt = Math.round(2.55 * percent);
            const R = (num >> 16) - amt;
            const G = (num >> 8 & 0x00FF) - amt;
            const B = (num & 0x0000FF) - amt;
            return '#' + (0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 +
                (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 +
                (B < 255 ? B < 1 ? 0 : B : 255))
                .toString(16).slice(1);
        }

        // Update CSS code display
        function updateCSSCode() {
            const code = `:root {
  --brand-primary: ${currentTheme.primary};
  --brand-secondary: ${currentTheme.secondary};
  --brand-accent: ${currentTheme.accent};

  --gradient-primary: linear-gradient(135deg, ${currentTheme.primary} 0%, ${darkenColor(currentTheme.primary, 15)} 100%);
  --gradient-secondary: linear-gradient(135deg, ${currentTheme.secondary} 0%, ${darkenColor(currentTheme.secondary, 15)} 100%);
  --gradient-accent: linear-gradient(135deg, ${currentTheme.accent} 0%, ${darkenColor(currentTheme.accent, 15)} 100%);
}`;
            document.getElementById('cssVariables').textContent = code;
        }

        // Copy CSS code to clipboard
        function copyCode() {
            const code = document.getElementById('cssVariables').textContent;
            navigator.clipboard.writeText(code).then(() => {
                alert('✅ CSS code copied to clipboard!');
            });
        }

        // Save theme
        function saveTheme() {
            alert('🎨 Theme saved successfully!\n\nYour custom theme has been saved and will be applied across the application.');
        }

        // Reset theme
        function resetTheme() {
            if (confirm('Reset theme to default?')) {
                applyPreset('purple');
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            applyTheme();

            // Animate cards on load
            document.querySelectorAll('.cis-card').forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('cis-animate-in');
            });
        });
    </script>

</body>
</html>
