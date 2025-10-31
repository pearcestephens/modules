<?php
/**
 * Advanced Theme Generator with Color Theory
 * 
 * Generates beautiful theme combinations using:
 * - Complementary colors
 * - Analogous colors
 * - Triadic colors
 * - Split-complementary
 * - Tetradic (square/rectangle)
 * - Monochromatic variations
 * 
 * @package CIS\Modules\AdminUI
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';

class ThemeGenerator {
    
    /**
     * Generate theme from base hue using color theory
     */
    public static function generateTheme(int $baseHue, string $scheme = 'complementary'): array {
        $themes = [];
        
        switch ($scheme) {
            case 'complementary':
                $themes = self::generateComplementary($baseHue);
                break;
            case 'analogous':
                $themes = self::generateAnalogous($baseHue);
                break;
            case 'triadic':
                $themes = self::generateTriadic($baseHue);
                break;
            case 'split-complementary':
                $themes = self::generateSplitComplementary($baseHue);
                break;
            case 'tetradic':
                $themes = self::generateTetradic($baseHue);
                break;
            case 'monochromatic':
                $themes = self::generateMonochromatic($baseHue);
                break;
            default:
                $themes = self::generateComplementary($baseHue);
        }
        
        return $themes;
    }
    
    /**
     * Generate complementary color scheme (opposite on color wheel)
     */
    private static function generateComplementary(int $hue): array {
        $complement = ($hue + 180) % 360;
        
        return [
            'primary' => self::hslToHex($hue, 70, 50),
            'secondary' => self::hslToHex($complement, 70, 50),
            'accent' => self::hslToHex($hue, 80, 45),
            'background' => self::hslToHex($hue, 10, 98),
            'surface' => self::hslToHex($hue, 15, 95),
            'text' => '#1f2937',
            'text_muted' => '#6b7280',
            'border' => self::hslToHex($hue, 15, 90),
            'header_bg' => self::hslToHex($hue, 70, 50),
            'header_text' => '#ffffff',
            'sidebar_bg' => self::hslToHex($hue, 15, 25),
            'sidebar_text' => self::hslToHex($hue, 15, 90),
            'sidebar_hover' => self::hslToHex($hue, 70, 40),
            'button_primary' => self::hslToHex($hue, 70, 50),
            'button_hover' => self::hslToHex($hue, 70, 45),
            'success' => '#10b981',
            'warning' => '#f59e0b',
            'danger' => '#ef4444',
            'info' => '#3b82f6',
        ];
    }
    
    /**
     * Generate analogous color scheme (adjacent colors)
     */
    private static function generateAnalogous(int $hue): array {
        $hue2 = ($hue + 30) % 360;
        $hue3 = ($hue - 30 + 360) % 360;
        
        return [
            'primary' => self::hslToHex($hue, 70, 50),
            'secondary' => self::hslToHex($hue2, 70, 50),
            'accent' => self::hslToHex($hue3, 70, 50),
            'background' => self::hslToHex($hue, 10, 98),
            'surface' => self::hslToHex($hue, 15, 95),
            'text' => '#1f2937',
            'text_muted' => '#6b7280',
            'border' => self::hslToHex($hue, 15, 90),
            'header_bg' => self::hslToHex($hue, 70, 50),
            'header_text' => '#ffffff',
            'sidebar_bg' => self::hslToHex($hue2, 20, 20),
            'sidebar_text' => self::hslToHex($hue2, 15, 90),
            'sidebar_hover' => self::hslToHex($hue, 70, 40),
            'button_primary' => self::hslToHex($hue, 70, 50),
            'button_hover' => self::hslToHex($hue3, 70, 45),
            'success' => '#10b981',
            'warning' => '#f59e0b',
            'danger' => '#ef4444',
            'info' => '#3b82f6',
        ];
    }
    
    /**
     * Generate triadic color scheme (120° apart)
     */
    private static function generateTriadic(int $hue): array {
        $hue2 = ($hue + 120) % 360;
        $hue3 = ($hue + 240) % 360;
        
        return [
            'primary' => self::hslToHex($hue, 70, 50),
            'secondary' => self::hslToHex($hue2, 70, 50),
            'accent' => self::hslToHex($hue3, 70, 50),
            'background' => '#fafafa',
            'surface' => '#ffffff',
            'text' => '#1f2937',
            'text_muted' => '#6b7280',
            'border' => '#e5e7eb',
            'header_bg' => self::hslToHex($hue, 70, 50),
            'header_text' => '#ffffff',
            'sidebar_bg' => self::hslToHex($hue2, 15, 22),
            'sidebar_text' => self::hslToHex($hue2, 10, 85),
            'sidebar_hover' => self::hslToHex($hue3, 70, 45),
            'button_primary' => self::hslToHex($hue, 70, 50),
            'button_hover' => self::hslToHex($hue2, 70, 45),
            'success' => '#10b981',
            'warning' => '#f59e0b',
            'danger' => '#ef4444',
            'info' => '#3b82f6',
        ];
    }
    
    /**
     * Generate split-complementary (complement +/- 30°)
     */
    private static function generateSplitComplementary(int $hue): array {
        $complement = ($hue + 180) % 360;
        $split1 = ($complement + 30) % 360;
        $split2 = ($complement - 30 + 360) % 360;
        
        return [
            'primary' => self::hslToHex($hue, 70, 50),
            'secondary' => self::hslToHex($split1, 70, 50),
            'accent' => self::hslToHex($split2, 70, 50),
            'background' => self::hslToHex($hue, 10, 98),
            'surface' => self::hslToHex($hue, 15, 95),
            'text' => '#1f2937',
            'text_muted' => '#6b7280',
            'border' => self::hslToHex($hue, 15, 90),
            'header_bg' => self::hslToHex($hue, 70, 50),
            'header_text' => '#ffffff',
            'sidebar_bg' => self::hslToHex($split1, 20, 20),
            'sidebar_text' => self::hslToHex($split1, 15, 90),
            'sidebar_hover' => self::hslToHex($split2, 70, 45),
            'button_primary' => self::hslToHex($hue, 70, 50),
            'button_hover' => self::hslToHex($split1, 70, 45),
            'success' => '#10b981',
            'warning' => '#f59e0b',
            'danger' => '#ef4444',
            'info' => '#3b82f6',
        ];
    }
    
    /**
     * Generate tetradic color scheme (90° apart)
     */
    private static function generateTetradic(int $hue): array {
        $hue2 = ($hue + 90) % 360;
        $hue3 = ($hue + 180) % 360;
        $hue4 = ($hue + 270) % 360;
        
        return [
            'primary' => self::hslToHex($hue, 70, 50),
            'secondary' => self::hslToHex($hue2, 70, 50),
            'accent' => self::hslToHex($hue3, 70, 50),
            'background' => '#fafafa',
            'surface' => '#ffffff',
            'text' => '#1f2937',
            'text_muted' => '#6b7280',
            'border' => '#e5e7eb',
            'header_bg' => self::hslToHex($hue, 70, 50),
            'header_text' => '#ffffff',
            'sidebar_bg' => self::hslToHex($hue4, 15, 20),
            'sidebar_text' => self::hslToHex($hue4, 10, 85),
            'sidebar_hover' => self::hslToHex($hue2, 70, 45),
            'button_primary' => self::hslToHex($hue, 70, 50),
            'button_hover' => self::hslToHex($hue3, 70, 45),
            'success' => '#10b981',
            'warning' => '#f59e0b',
            'danger' => '#ef4444',
            'info' => '#3b82f6',
        ];
    }
    
    /**
     * Generate monochromatic scheme (same hue, different saturation/lightness)
     */
    private static function generateMonochromatic(int $hue): array {
        return [
            'primary' => self::hslToHex($hue, 70, 50),
            'secondary' => self::hslToHex($hue, 50, 60),
            'accent' => self::hslToHex($hue, 80, 40),
            'background' => self::hslToHex($hue, 10, 98),
            'surface' => self::hslToHex($hue, 15, 95),
            'text' => self::hslToHex($hue, 15, 20),
            'text_muted' => self::hslToHex($hue, 10, 50),
            'border' => self::hslToHex($hue, 15, 90),
            'header_bg' => self::hslToHex($hue, 70, 50),
            'header_text' => '#ffffff',
            'sidebar_bg' => self::hslToHex($hue, 15, 25),
            'sidebar_text' => self::hslToHex($hue, 10, 85),
            'sidebar_hover' => self::hslToHex($hue, 70, 40),
            'button_primary' => self::hslToHex($hue, 70, 50),
            'button_hover' => self::hslToHex($hue, 80, 45),
            'success' => '#10b981',
            'warning' => '#f59e0b',
            'danger' => '#ef4444',
            'info' => '#3b82f6',
        ];
    }
    
    /**
     * Convert HSL to HEX
     */
    private static function hslToHex(int $h, int $s, int $l): string {
        $h = $h / 360;
        $s = $s / 100;
        $l = $l / 100;
        
        if ($s == 0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hueToRgb($p, $q, $h + 1/3);
            $g = self::hueToRgb($p, $q, $h);
            $b = self::hueToRgb($p, $q, $h - 1/3);
        }
        
        return sprintf("#%02x%02x%02x", round($r * 255), round($g * 255), round($b * 255));
    }
    
    private static function hueToRgb($p, $q, $t) {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1/2) return $q;
        if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
        return $p;
    }
    
    /**
     * Generate CSS variables from theme
     */
    public static function generateCSS(array $theme): string {
        $css = ":root {\n";
        foreach ($theme as $key => $value) {
            $cssVar = '--' . str_replace('_', '-', $key);
            $css .= "    $cssVar: $value;\n";
        }
        $css .= "}\n";
        return $css;
    }
}

// Generate 50+ theme variations
$themes = [];
$schemes = ['complementary', 'analogous', 'triadic', 'split-complementary', 'tetradic', 'monochromatic'];
$baseHues = [0, 20, 40, 60, 80, 100, 140, 180, 200, 240, 260, 280, 300, 320]; // 14 hues

$themeId = 1;
foreach ($baseHues as $hue) {
    foreach ($schemes as $scheme) {
        $theme = ThemeGenerator::generateTheme($hue, $scheme);
        $theme['id'] = $themeId++;
        $theme['name'] = ucfirst($scheme) . " - Hue " . $hue;
        $theme['scheme'] = $scheme;
        $theme['base_hue'] = $hue;
        $themes[] = $theme;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'apply_theme') {
        $themeId = (int)$_POST['theme_id'];
        $selectedTheme = null;
        
        foreach ($themes as $theme) {
            if ($theme['id'] === $themeId) {
                $selectedTheme = $theme;
                break;
            }
        }
        
        if ($selectedTheme) {
            // Save theme to config
            $configPath = __DIR__ . '/config/theme-config.php';
            $configContent = "<?php\nreturn " . var_export($selectedTheme, true) . ";\n";
            file_put_contents($configPath, $configContent);
            
            // Generate CSS
            $css = ThemeGenerator::generateCSS($selectedTheme);
            $cssPath = __DIR__ . '/_templates/css/theme-generated.css';
            file_put_contents($cssPath, $css);
            
            echo json_encode(['success' => true, 'message' => 'Theme applied successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Theme not found']);
        }
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Theme Generator - CIS Admin UI</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background: #f5f7fa; font-family: 'Segoe UI', sans-serif; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem 0; margin-bottom: 2rem; }
        .theme-card { 
            background: white; 
            border-radius: 12px; 
            padding: 1rem; 
            margin-bottom: 1.5rem; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .theme-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .theme-preview { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        .color-swatch { 
            width: 40px; 
            height: 40px; 
            border-radius: 6px; 
            border: 2px solid #e5e7eb;
            position: relative;
        }
        .color-swatch:hover::after {
            content: attr(data-color);
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            background: #1f2937;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            white-space: nowrap;
            z-index: 10;
        }
        .theme-name { font-weight: 600; font-size: 0.9rem; color: #1f2937; }
        .theme-scheme { font-size: 0.75rem; color: #6b7280; text-transform: uppercase; }
        .apply-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .theme-card:hover .apply-btn { opacity: 1; }
        .filter-section { background: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .component-preview { 
            margin-top: 1rem; 
            padding: 1rem; 
            background: white; 
            border-radius: 8px; 
            border: 1px solid #e5e7eb; 
        }
        .sample-header { padding: 1rem; border-radius: 6px; margin-bottom: 0.5rem; }
        .sample-sidebar { padding: 1rem; border-radius: 6px; margin-bottom: 0.5rem; display: flex; flex-direction: column; gap: 0.5rem; }
        .sample-sidebar-item { padding: 0.5rem 1rem; border-radius: 4px; transition: all 0.2s; }
        .sample-button { padding: 0.5rem 1.5rem; border: none; border-radius: 6px; font-weight: 500; transition: all 0.2s; cursor: pointer; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container-fluid">
            <h1><i class="fas fa-palette"></i> Advanced Theme Generator</h1>
            <p class="mb-0">Explore <?= count($themes) ?> professionally crafted themes using color theory</p>
        </div>
    </div>
    
    <div class="container-fluid">
        <div class="filter-section">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <label><strong>Color Scheme:</strong></label>
                    <select class="form-control" id="schemeFilter">
                        <option value="">All Schemes</option>
                        <option value="complementary">Complementary</option>
                        <option value="analogous">Analogous</option>
                        <option value="triadic">Triadic</option>
                        <option value="split-complementary">Split-Complementary</option>
                        <option value="tetradic">Tetradic</option>
                        <option value="monochromatic">Monochromatic</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label><strong>Base Hue:</strong></label>
                    <select class="form-control" id="hueFilter">
                        <option value="">All Hues</option>
                        <?php foreach (array_unique(array_column($themes, 'base_hue')) as $hue): ?>
                            <option value="<?= $hue ?>"><?= $hue ?>° (<?= self::getHueName($hue) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label><strong>Search:</strong></label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Search themes...">
                </div>
            </div>
        </div>
        
        <div class="row" id="themesContainer">
            <?php foreach ($themes as $theme): ?>
                <div class="col-md-6 col-lg-4 col-xl-3 theme-item" 
                     data-scheme="<?= $theme['scheme'] ?>" 
                     data-hue="<?= $theme['base_hue'] ?>"
                     data-name="<?= strtolower($theme['name']) ?>">
                    <div class="theme-card">
                        <button class="btn btn-sm btn-primary apply-btn" onclick="applyTheme(<?= $theme['id'] ?>)">
                            <i class="fas fa-check"></i> Apply
                        </button>
                        
                        <div class="theme-name"><?= htmlspecialchars($theme['name']) ?></div>
                        <div class="theme-scheme"><?= htmlspecialchars($theme['scheme']) ?></div>
                        
                        <div class="theme-preview">
                            <div class="color-swatch" style="background: <?= $theme['primary'] ?>" data-color="<?= $theme['primary'] ?>"></div>
                            <div class="color-swatch" style="background: <?= $theme['secondary'] ?>" data-color="<?= $theme['secondary'] ?>"></div>
                            <div class="color-swatch" style="background: <?= $theme['accent'] ?>" data-color="<?= $theme['accent'] ?>"></div>
                            <div class="color-swatch" style="background: <?= $theme['header_bg'] ?>" data-color="<?= $theme['header_bg'] ?>"></div>
                        </div>
                        
                        <div class="component-preview">
                            <div class="sample-header" style="background: <?= $theme['header_bg'] ?>; color: <?= $theme['header_text'] ?>">
                                <small><i class="fas fa-bars"></i> Header</small>
                            </div>
                            <div class="sample-sidebar" style="background: <?= $theme['sidebar_bg'] ?>; color: <?= $theme['sidebar_text'] ?>">
                                <div class="sample-sidebar-item" style="background: rgba(255,255,255,0.05);">
                                    <small><i class="fas fa-home"></i> Dashboard</small>
                                </div>
                                <div class="sample-sidebar-item" style="background: <?= $theme['sidebar_hover'] ?>;">
                                    <small><i class="fas fa-chart-bar"></i> Reports</small>
                                </div>
                            </div>
                            <button class="sample-button" style="background: <?= $theme['button_primary'] ?>; color: white;">
                                Button
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function applyTheme(themeId) {
        if (!confirm('Apply this theme? This will update your admin UI colors.')) return;
        
        $.post('', {
            action: 'apply_theme',
            theme_id: themeId
        }, function(response) {
            if (response.success) {
                alert(response.message);
                window.location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        }, 'json');
    }
    
    // Filtering
    $('#schemeFilter, #hueFilter, #searchInput').on('change keyup', function() {
        const scheme = $('#schemeFilter').val().toLowerCase();
        const hue = $('#hueFilter').val();
        const search = $('#searchInput').val().toLowerCase();
        
        $('.theme-item').each(function() {
            const $item = $(this);
            const itemScheme = $item.data('scheme');
            const itemHue = $item.data('hue').toString();
            const itemName = $item.data('name');
            
            const matchScheme = !scheme || itemScheme === scheme;
            const matchHue = !hue || itemHue === hue;
            const matchSearch = !search || itemName.includes(search);
            
            if (matchScheme && matchHue && matchSearch) {
                $item.show();
            } else {
                $item.hide();
            }
        });
    });
    </script>
</body>
</html>

<?php
function getHueName($hue) {
    if ($hue >= 0 && $hue < 30) return 'Red';
    if ($hue >= 30 && $hue < 60) return 'Orange';
    if ($hue >= 60 && $hue < 90) return 'Yellow';
    if ($hue >= 90 && $hue < 150) return 'Green';
    if ($hue >= 150 && $hue < 210) return 'Cyan';
    if ($hue >= 210 && $hue < 270) return 'Blue';
    if ($hue >= 270 && $hue < 330) return 'Purple';
    return 'Magenta';
}
?>
