<?php
/**
 * Auto Asset Loader for CIS Modules (CSS + JavaScript)
 * 
 * Automatically discovers and loads CSS and JS files from module folders in the correct order:
 * 1. Shared/common assets (from /modules/shared/css/ and /modules/shared/js/)
 * 2. Module-specific assets (from /modules/{module}/css/ and /modules/{module}/js/)
 * 3. Page-specific assets (from /modules/{module}/{subfolder}/css/ and /modules/{module}/{subfolder}/js/)
 * 
 * USAGE:
 * ------
 * // CSS only
 * $page_head_extra = autoLoadModuleCSS(__FILE__);
 * 
 * // JavaScript only
 * $page_scripts_before_footer = autoLoadModuleJS(__FILE__);
 * 
 * // Both at once
 * list($css, $js) = autoLoadModuleAssets(__FILE__);
 * $page_head_extra = $css;
 * $page_scripts_before_footer = $js;
 * 
 * // With custom paths
 * $css = autoLoadModuleCSS(__FILE__, [
 *     'additional' => ['/modules/custom/css/extra.css']
 * ]);
 * 
 * @package CIS\Shared\Functions
 * @version 2.0.0
 */

/**
 * Auto-load CSS files from module directory structure
 * 
 * Scans the calling file's directory tree and automatically includes:
 * - All shared CSS files
 * - All module CSS files
 * - All page-specific CSS files
 * 
 * @param string $callingFile The __FILE__ constant from calling script
 * @param array $options Options array:
 *                       - 'additional' => array of extra CSS paths to include
 *                       - 'exclude' => array of filenames to exclude
 *                       - 'minified' => bool, prefer .min.css files (default: false)
 *                       - 'cache_bust' => bool, add ?v=timestamp (default: true)
 * @return string HTML <link> tags ready for $page_head_extra
 */
function autoLoadModuleCSS(string $callingFile, array $options = []): string
{
    // Default options
    $options = array_merge([
        'additional' => [],
        'exclude' => [],
        'minified' => false,
        'cache_bust' => true,
    ], $options);
    
    // Get document root
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if (empty($docRoot)) {
        $docRoot = defined('ROOT_PATH') ? ROOT_PATH : realpath(__DIR__ . '/../../..');
    }
    
    // Get calling file's directory structure
    $callingDir = dirname($callingFile);
    $relativePath = str_replace($docRoot, '', $callingDir);
    
    // Parse path to find module structure
    // Example: /modules/consignments/stock-transfers/pack.php
    // Results in: module=consignments, subfolder=stock-transfers
    preg_match('#/modules/([^/]+)(?:/([^/]+))?#', $relativePath, $matches);
    $moduleName = $matches[1] ?? null;
    $subFolder = $matches[2] ?? null;
    
    $cssFiles = [];
    
    // ========================================================================
    // 1. SHARED CSS (Highest Priority - Loads First)
    // ========================================================================
    $sharedCssDir = $docRoot . '/modules/shared/css';
    if (is_dir($sharedCssDir)) {
        $sharedFiles = glob($sharedCssDir . '/*.css');
        foreach ($sharedFiles as $file) {
            $filename = basename($file);
            if (!in_array($filename, $options['exclude'])) {
                $cssFiles[] = [
                    'path' => '/modules/shared/css/' . $filename,
                    'priority' => 1,
                    'label' => 'Shared: ' . $filename
                ];
            }
        }
    }
    
    // ========================================================================
    // 2. MODULE CSS (Medium Priority)
    // ========================================================================
    if ($moduleName) {
        $moduleCssDir = $docRoot . '/modules/' . $moduleName . '/css';
        if (is_dir($moduleCssDir)) {
            $moduleFiles = glob($moduleCssDir . '/*.css');
            foreach ($moduleFiles as $file) {
                $filename = basename($file);
                if (!in_array($filename, $options['exclude'])) {
                    $cssFiles[] = [
                        'path' => '/modules/' . $moduleName . '/css/' . $filename,
                        'priority' => 2,
                        'label' => 'Module: ' . $filename
                    ];
                }
            }
        }
        
        // Module shared CSS (if exists)
        $moduleSharedCssDir = $docRoot . '/modules/' . $moduleName . '/shared/css';
        if (is_dir($moduleSharedCssDir)) {
            $moduleSharedFiles = glob($moduleSharedCssDir . '/*.css');
            foreach ($moduleSharedFiles as $file) {
                $filename = basename($file);
                if (!in_array($filename, $options['exclude'])) {
                    $cssFiles[] = [
                        'path' => '/modules/' . $moduleName . '/shared/css/' . $filename,
                        'priority' => 2,
                        'label' => 'Module Shared: ' . $filename
                    ];
                }
            }
        }
    }
    
    // ========================================================================
    // 3. SUBFOLDER/PAGE-SPECIFIC CSS (Lowest Priority - Loads Last)
    // ========================================================================
    if ($moduleName && $subFolder) {
        $subfolderCssDir = $docRoot . '/modules/' . $moduleName . '/' . $subFolder . '/css';
        if (is_dir($subfolderCssDir)) {
            $subfolderFiles = glob($subfolderCssDir . '/*.css');
            foreach ($subfolderFiles as $file) {
                $filename = basename($file);
                if (!in_array($filename, $options['exclude'])) {
                    $cssFiles[] = [
                        'path' => '/modules/' . $moduleName . '/' . $subFolder . '/css/' . $filename,
                        'priority' => 3,
                        'label' => 'Page: ' . $filename
                    ];
                }
            }
        }
    }
    
    // ========================================================================
    // 4. ADDITIONAL CSS (Custom paths from options)
    // ========================================================================
    foreach ($options['additional'] as $additionalPath) {
        $cssFiles[] = [
            'path' => $additionalPath,
            'priority' => 4,
            'label' => 'Additional: ' . basename($additionalPath)
        ];
    }
    
    // ========================================================================
    // 5. SORT BY PRIORITY (Shared → Module → Page → Additional)
    // ========================================================================
    usort($cssFiles, function($a, $b) {
        return $a['priority'] <=> $b['priority'];
    });
    
    // ========================================================================
    // 6. GENERATE HTML <link> TAGS
    // ========================================================================
    $html = "\n<!-- Auto-loaded Module CSS -->\n";
    
    foreach ($cssFiles as $css) {
        $path = $css['path'];
        
        // Check for minified version if requested
        if ($options['minified']) {
            $minPath = str_replace('.css', '.min.css', $path);
            $minFile = $docRoot . $minPath;
            if (file_exists($minFile)) {
                $path = $minPath;
            }
        }
        
        // Add cache busting
        $cacheBust = '';
        if ($options['cache_bust']) {
            $fullPath = $docRoot . $path;
            if (file_exists($fullPath)) {
                $mtime = filemtime($fullPath);
                $cacheBust = '?v=' . $mtime;
            }
        }
        
        // Generate <link> tag
        $html .= sprintf(
            '<link rel="stylesheet" href="%s%s" data-source="%s">' . "\n",
            htmlspecialchars($path, ENT_QUOTES, 'UTF-8'),
            $cacheBust,
            htmlspecialchars($css['label'], ENT_QUOTES, 'UTF-8')
        );
    }
    
    $html .= "<!-- End Auto-loaded CSS -->\n";
    
    return $html;
}

/**
 * Auto-load JavaScript files from module directory structure
 * 
 * Same as autoLoadModuleCSS but for JavaScript files
 * Follows same priority: Shared → Module → Page → Additional
 * 
 * @param string $callingFile The __FILE__ constant from calling script
 * @param array $options Options array (same as autoLoadModuleCSS)
 * @return string HTML <script> tags ready for $page_scripts_before_footer
 */
function autoLoadModuleJS(string $callingFile, array $options = []): string
{
    // Default options
    $options = array_merge([
        'additional' => [],
        'exclude' => [],
        'minified' => false,
        'cache_bust' => true,
        'defer' => false,
        'async' => false,
    ], $options);
    
    // Get document root
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if (empty($docRoot)) {
        $docRoot = defined('ROOT_PATH') ? ROOT_PATH : realpath(__DIR__ . '/../../..');
    }
    
    // Get calling file's directory structure
    $callingDir = dirname($callingFile);
    $relativePath = str_replace($docRoot, '', $callingDir);
    
    // Parse path
    preg_match('#/modules/([^/]+)(?:/([^/]+))?#', $relativePath, $matches);
    $moduleName = $matches[1] ?? null;
    $subFolder = $matches[2] ?? null;
    
    $jsFiles = [];
    
    // 1. SHARED JS
    $sharedJsDir = $docRoot . '/modules/shared/js';
    if (is_dir($sharedJsDir)) {
        $sharedFiles = glob($sharedJsDir . '/*.js');
        foreach ($sharedFiles as $file) {
            $filename = basename($file);
            if (!in_array($filename, $options['exclude'])) {
                $jsFiles[] = [
                    'path' => '/modules/shared/js/' . $filename,
                    'priority' => 1,
                    'label' => 'Shared: ' . $filename
                ];
            }
        }
    }
    
    // 2. MODULE JS
    if ($moduleName) {
        $moduleJsDir = $docRoot . '/modules/' . $moduleName . '/js';
        if (is_dir($moduleJsDir)) {
            $moduleFiles = glob($moduleJsDir . '/*.js');
            foreach ($moduleFiles as $file) {
                $filename = basename($file);
                if (!in_array($filename, $options['exclude'])) {
                    $jsFiles[] = [
                        'path' => '/modules/' . $moduleName . '/js/' . $filename,
                        'priority' => 2,
                        'label' => 'Module: ' . $filename
                    ];
                }
            }
        }
        
        // Module shared JS
        $moduleSharedJsDir = $docRoot . '/modules/' . $moduleName . '/shared/js';
        if (is_dir($moduleSharedJsDir)) {
            $moduleSharedFiles = glob($moduleSharedJsDir . '/*.js');
            foreach ($moduleSharedFiles as $file) {
                $filename = basename($file);
                if (!in_array($filename, $options['exclude'])) {
                    $jsFiles[] = [
                        'path' => '/modules/' . $moduleName . '/shared/js/' . $filename,
                        'priority' => 2,
                        'label' => 'Module Shared: ' . $filename
                    ];
                }
            }
        }
    }
    
    // 3. SUBFOLDER/PAGE-SPECIFIC JS
    if ($moduleName && $subFolder) {
        $subfolderJsDir = $docRoot . '/modules/' . $moduleName . '/' . $subFolder . '/js';
        if (is_dir($subfolderJsDir)) {
            $subfolderFiles = glob($subfolderJsDir . '/*.js');
            foreach ($subfolderFiles as $file) {
                $filename = basename($file);
                if (!in_array($filename, $options['exclude'])) {
                    $jsFiles[] = [
                        'path' => '/modules/' . $moduleName . '/' . $subFolder . '/js/' . $filename,
                        'priority' => 3,
                        'label' => 'Page: ' . $filename
                    ];
                }
            }
        }
    }
    
    // 4. ADDITIONAL JS
    foreach ($options['additional'] as $additionalPath) {
        $jsFiles[] = [
            'path' => $additionalPath,
            'priority' => 4,
            'label' => 'Additional: ' . basename($additionalPath)
        ];
    }
    
    // 5. SORT BY PRIORITY
    usort($jsFiles, function($a, $b) {
        return $a['priority'] <=> $b['priority'];
    });
    
    // 6. GENERATE HTML <script> TAGS
    $html = "\n<!-- Auto-loaded Module JavaScript -->\n";
    
    $deferAttr = $options['defer'] ? ' defer' : '';
    $asyncAttr = $options['async'] ? ' async' : '';
    
    foreach ($jsFiles as $js) {
        $path = $js['path'];
        
        // Check for minified version if requested
        if ($options['minified']) {
            $minPath = str_replace('.js', '.min.js', $path);
            $minFile = $docRoot . $minPath;
            if (file_exists($minFile)) {
                $path = $minPath;
            }
        }
        
        // Add cache busting
        $cacheBust = '';
        if ($options['cache_bust']) {
            $fullPath = $docRoot . $path;
            if (file_exists($fullPath)) {
                $mtime = filemtime($fullPath);
                $cacheBust = '?v=' . $mtime;
            }
        }
        
        // Generate <script> tag
        $html .= sprintf(
            '<script src="%s%s"%s%s data-source="%s"></script>' . "\n",
            htmlspecialchars($path, ENT_QUOTES, 'UTF-8'),
            $cacheBust,
            $deferAttr,
            $asyncAttr,
            htmlspecialchars($js['label'], ENT_QUOTES, 'UTF-8')
        );
    }
    
    $html .= "<!-- End Auto-loaded JavaScript -->\n";
    
    return $html;
}

/**
 * Quick helper: Auto-load both CSS and JS
 * 
 * @param string $callingFile The __FILE__ constant
 * @param array $options Options for both CSS and JS
 * @return array ['css' => string, 'js' => string]
 */
function autoLoadModuleAssets(string $callingFile, array $options = []): array
{
    return [
        'css' => autoLoadModuleCSS($callingFile, $options['css'] ?? []),
        'js' => autoLoadModuleJS($callingFile, $options['js'] ?? []),
    ];
}
