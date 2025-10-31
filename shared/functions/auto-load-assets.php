<?php
/**
 * Auto Asset Loader for CIS Modules (CSS + JavaScript)
 *
 * Discovers and loads CSS/JS from module folders in this priority:
 * 1) /modules/shared/{css|js}
 * 2) /modules/{module}/{css|js} and /modules/{module}/shared/{css|js}
 * 3) /modules/{module}/{subfolder}/{css|js}
 * 4) additional[] (custom)
 *
 * @package CIS\Shared\Functions
 * @version 2.1.0
 */

declare(strict_types=1);

/**
 * Auto-load CSS files from module directory structure
 *
 * @param string $callingFile __FILE__ from the calling script
 * @param array  $options     [
 *   'additional' => array<string|array>,  // supports string paths, map path=>attrs, or arrays with ['path'=>..,'attrs'=>..]
 *   'exclude'    => array<string>,        // filenames to exclude
 *   'minified'   => bool,                 // prefer .min.css
 *   'cache_bust' => bool                  // add ?v=mtime
 * ]
 * @return string HTML <link> tags ready for $page_head_extra
 */
function autoLoadModuleCSS(string $callingFile, array $options = []): string
{
    $options = array_merge([
        'additional' => [],
        'exclude'    => [],
        'minified'   => false,
        'cache_bust' => true,
    ], $options);

    // Document root
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if ($docRoot === '') {
        $docRoot = defined('ROOT_PATH') ? ROOT_PATH : realpath(__DIR__ . '/../../..');
    }

    // Compute module/subfolder from calling file
    $callingDir   = dirname($callingFile);
    $relativePath = str_replace($docRoot, '', $callingDir);
    preg_match('#/modules/([^/]+)(?:/([^/]+))?#', $relativePath, $m);
    $moduleName = $m[1] ?? null;
    $subFolder  = $m[2] ?? null;

    $cssFiles = []; // each item: ['path'=>string,'priority'=>int,'label'=>string,'attrs'=>array]

    // 1) SHARED CSS
    $sharedCssDir = $docRoot . '/modules/shared/css';
    if (is_dir($sharedCssDir)) {
        foreach (glob($sharedCssDir . '/*.css') as $file) {
            $filename = basename($file);
            if (!in_array($filename, $options['exclude'], true)) {
                $cssFiles[] = [
                    'path'     => '/modules/shared/css/' . $filename,
                    'priority' => 1,
                    'label'    => 'Shared: ' . $filename,
                    'attrs'    => []
                ];
            }
        }
    }

    // 2) MODULE CSS (+ shared CSS under module)
    if ($moduleName) {
        $moduleCssDir = $docRoot . '/modules/' . $moduleName . '/css';
        if (is_dir($moduleCssDir)) {
            foreach (glob($moduleCssDir . '/*.css') as $file) {
                $filename = basename($file);
                if (!in_array($filename, $options['exclude'], true)) {
                    $cssFiles[] = [
                        'path'     => '/modules/' . $moduleName . '/css/' . $filename,
                        'priority' => 2,
                        'label'    => 'Module: ' . $filename,
                        'attrs'    => []
                    ];
                }
            }
        }

        $moduleSharedCssDir = $docRoot . '/modules/' . $moduleName . '/shared/css';
        if (is_dir($moduleSharedCssDir)) {
            foreach (glob($moduleSharedCssDir . '/*.css') as $file) {
                $filename = basename($file);
                if (!in_array($filename, $options['exclude'], true)) {
                    $cssFiles[] = [
                        'path'     => '/modules/' . $moduleName . '/shared/css/' . $filename,
                        'priority' => 2,
                        'label'    => 'Module Shared: ' . $filename,
                        'attrs'    => []
                    ];
                }
            }
        }
    }

    // 3) PAGE/SUBFOLDER CSS
    if ($moduleName && $subFolder) {
        $subCssDir = $docRoot . '/modules/' . $moduleName . '/' . $subFolder . '/css';
        if (is_dir($subCssDir)) {
            foreach (glob($subCssDir . '/*.css') as $file) {
                $filename = basename($file);
                if (!in_array($filename, $options['exclude'], true)) {
                    $cssFiles[] = [
                        'path'     => '/modules/' . $moduleName . '/' . $subFolder . '/css/' . $filename,
                        'priority' => 3,
                        'label'    => 'Page: ' . $filename,
                        'attrs'    => []
                    ];
                }
            }
        }
    }

    // 4) ADDITIONAL CSS
    foreach ($options['additional'] as $key => $val) {
        $path  = null;
        $attrs = [];

        if (is_string($val)) {
            // ['.../file.css', ...]
            $path = $val;
        } elseif (is_array($val)) {
            // [
            //   ['path'=>'/x.css','attrs'=>['media'=>'print']],
            //   ['path'=>'/x.css','media'=>'print'], // tolerate flat attrs
            // ]
            $path  = $val['path'] ?? ($val[0] ?? null);
            $attrs = $val['attrs'] ?? array_diff_key($val, ['path'=>true, 0=>true, 'attrs'=>true]);
        } elseif (is_string($key) && is_array($val)) {
            // ['/x.css' => ['media'=>'print']]
            $path  = $key;
            $attrs = $val;
        }

        if (!$path || !is_string($path)) { continue; }

        $cssFiles[] = [
            'path'     => $path,
            'priority' => 4,
            'label'    => 'Additional: ' . basename($path),
            'attrs'    => is_array($attrs) ? $attrs : []
        ];
    }

    // Sort by priority
    usort($cssFiles, fn($a, $b) => $a['priority'] <=> $b['priority']);

    // Build HTML
    $html = "\n<!-- Auto-loaded Module CSS -->\n";

    foreach ($cssFiles as $css) {
        $path = $css['path'];

        // Minified preference
        if ($options['minified']) {
            $minPath = str_replace('.css', '.min.css', $path);
            $minFile = $docRoot . $minPath;
            if (is_file($minFile)) {
                $path = $minPath;
            }
        }

        // Cache-busting
        $cacheBust = '';
        if ($options['cache_bust']) {
            $fullPath = $docRoot . $path;
            if (is_file($fullPath)) {
                $cacheBust = '?v=' . filemtime($fullPath);
            }
        }

        // Extra attributes (safe list + data-*)
        $extra = '';
        $allowed = ['media','integrity','crossorigin','referrerpolicy','title','as','disabled','type']; // plus data-*
        $rel = 'stylesheet';
        if (!empty($css['attrs']) && is_array($css['attrs'])) {
            foreach ($css['attrs'] as $name => $value) {
                $name = strtolower((string)$name);
                if ($name === 'rel') { $rel = (string)$value ?: 'stylesheet'; continue; }
                if (in_array($name, $allowed, true) || str_starts_with($name, 'data-')) {
                    if (is_bool($value)) {
                        if ($value) { $extra .= ' ' . $name; }
                    } else {
                        $extra .= ' ' . $name . '="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"';
                    }
                }
            }
        }

        $html .= sprintf(
            '<link rel="%s" href="%s%s"%s data-source="%s">' . "\n",
            htmlspecialchars($rel, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($path, ENT_QUOTES, 'UTF-8'),
            $cacheBust,
            $extra,
            htmlspecialchars($css['label'], ENT_QUOTES, 'UTF-8')
        );
    }

    $html .= "<!-- End Auto-loaded CSS -->\n";
    return $html;
}

/**
 * Auto-load JavaScript files from module directory structure
 *
 * @param string $callingFile __FILE__ from the calling script
 * @param array  $options     [
 *   'additional' => array<string>, // extra script paths (strings only)
 *   'exclude'    => array<string>,
 *   'minified'   => bool,
 *   'cache_bust' => bool,
 *   'defer'      => bool,
 *   'async'      => bool
 * ]
 * @return string HTML <script> tags ready for $page_scripts_before_footer
 */
function autoLoadModuleJS(string $callingFile, array $options = []): string
{
    $options = array_merge([
        'additional' => [],
        'exclude'    => [],
        'minified'   => false,
        'cache_bust' => true,
        'defer'      => false,
        'async'      => false,
    ], $options);

    // Document root
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if ($docRoot === '') {
        $docRoot = defined('ROOT_PATH') ? ROOT_PATH : realpath(__DIR__ . '/../../..');
    }

    // Compute module/subfolder
    $callingDir   = dirname($callingFile);
    $relativePath = str_replace($docRoot, '', $callingDir);
    preg_match('#/modules/([^/]+)(?:/([^/]+))?#', $relativePath, $m);
    $moduleName = $m[1] ?? null;
    $subFolder  = $m[2] ?? null;

    $jsFiles = [];

    // 1) SHARED JS
    $sharedJsDir = $docRoot . '/modules/shared/js';
    if (is_dir($sharedJsDir)) {
        foreach (glob($sharedJsDir . '/*.js') as $file) {
            $filename = basename($file);
            if (!in_array($filename, $options['exclude'], true)) {
                $jsFiles[] = [
                    'path'     => '/modules/shared/js/' . $filename,
                    'priority' => 1,
                    'label'    => 'Shared: ' . $filename
                ];
            }
        }
    }

    // 2) MODULE JS (+ shared JS under module)
    if ($moduleName) {
        $moduleJsDir = $docRoot . '/modules/' . $moduleName . '/js';
        if (is_dir($moduleJsDir)) {
            foreach (glob($moduleJsDir . '/*.js') as $file) {
                $filename = basename($file);
                if (!in_array($filename, $options['exclude'], true)) {
                    $jsFiles[] = [
                        'path'     => '/modules/' . $moduleName . '/js/' . $filename,
                        'priority' => 2,
                        'label'    => 'Module: ' . $filename
                    ];
                }
            }
        }

        $moduleSharedJsDir = $docRoot . '/modules/' . $moduleName . '/shared/js';
        if (is_dir($moduleSharedJsDir)) {
            foreach (glob($moduleSharedJsDir . '/*.js') as $file) {
                $filename = basename($file);
                if (!in_array($filename, $options['exclude'], true)) {
                    $jsFiles[] = [
                        'path'     => '/modules/' . $moduleName . '/shared/js/' . $filename,
                        'priority' => 2,
                        'label'    => 'Module Shared: ' . $filename
                    ];
                }
            }
        }
    }

    // 3) PAGE/SUBFOLDER JS
    if ($moduleName && $subFolder) {
        $subJsDir = $docRoot . '/modules/' . $moduleName . '/' . $subFolder . '/js';
        if (is_dir($subJsDir)) {
            foreach (glob($subJsDir . '/*.js') as $file) {
                $filename = basename($file);
                if (!in_array($filename, $options['exclude'], true)) {
                    $jsFiles[] = [
                        'path'     => '/modules/' . $moduleName . '/' . $subFolder . '/js/' . $filename,
                        'priority' => 3,
                        'label'    => 'Page: ' . $filename
                    ];
                }
            }
        }
    }

    // 4) ADDITIONAL JS (strings only)
    foreach ($options['additional'] as $additionalPath) {
        if (!is_string($additionalPath)) { continue; }
        $jsFiles[] = [
            'path'     => $additionalPath,
            'priority' => 4,
            'label'    => 'Additional: ' . basename($additionalPath)
        ];
    }

    // Sort by priority
    usort($jsFiles, fn($a, $b) => $a['priority'] <=> $b['priority']);

    // Build HTML
    $html = "\n<!-- Auto-loaded Module JavaScript -->\n";
    $deferAttr = $options['defer'] ? ' defer' : '';
    $asyncAttr = $options['async'] ? ' async' : '';

    foreach ($jsFiles as $js) {
        $path = $js['path'];

        // Minified preference
        if ($options['minified']) {
            $minPath = str_replace('.js', '.min.js', $path);
            $minFile = $docRoot . $minPath;
            if (is_file($minFile)) {
                $path = $minPath;
            }
        }

        // Cache-busting
        $cacheBust = '';
        if ($options['cache_bust']) {
            $fullPath = $docRoot . $path;
            if (is_file($fullPath)) {
                $cacheBust = '?v=' . filemtime($fullPath);
            }
        }

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
 * Quick helper: load both CSS and JS
 *
 * @return array{css:string, js:string}
 */
function autoLoadModuleAssets(string $callingFile, array $options = []): array
{
    return [
        'css' => autoLoadModuleCSS($callingFile, $options['css'] ?? []),
        'js'  => autoLoadModuleJS($callingFile, $options['js'] ?? []),
    ];
}
