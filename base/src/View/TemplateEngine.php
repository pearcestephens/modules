<?php
/**
 * Modern Template Engine
 *
 * Blade/Twig-inspired template engine with:
 * - Layout inheritance (@extends, @section, @yield)
 * - Component system (@component, @slot)
 * - Auto-escaping for security
 * - Template caching
 * - Clean syntax
 *
 * @package CIS\Base\View
 * @version 2.0.0
 */

declare(strict_types=1);

namespace CIS\Base\View;

class TemplateEngine
{
    /**
     * Template paths
     */
    private array $paths = [];

    /**
     * Data to pass to templates
     */
    private array $data = [];

    /**
     * Sections defined in templates
     */
    private array $sections = [];

    /**
     * Current section being captured
     */
    private ?string $currentSection = null;

    /**
     * Extended layout
     */
    private ?string $extendsLayout = null;

    /**
     * Cache enabled
     */
    private bool $cacheEnabled = true;

    /**
     * Cache path
     */
    private string $cachePath;

    /**
     * Auto-escape output
     */
    private bool $autoEscape = true;

    /**
     * Application instance
     */
    private $app;

    /**
     * Constructor
     */
    public function __construct($app)
    {
        $this->app = $app;
        $this->paths = $app->config('view.paths', []);
        $this->cacheEnabled = $app->config('view.cache.enabled', true);
        $this->cachePath = $app->config('view.cache.path', $_SERVER['DOCUMENT_ROOT'] . '/storage/cache/views');
        $this->autoEscape = $app->config('view.auto_escape', true);

        // Create cache directory if needed
        if ($this->cacheEnabled && !is_dir($this->cachePath)) {
            @mkdir($this->cachePath, 0755, true);
        }
    }

    /**
     * Render a template
     */
    public function render(string $template, array $data = []): string
    {
        $this->data = array_merge($this->data, $data);

        // Find template file
        $templatePath = $this->findTemplate($template);

        if (!$templatePath) {
            throw new \Exception("Template not found: {$template}");
        }

        // Check cache
        if ($this->cacheEnabled) {
            $cacheFile = $this->getCacheFile($templatePath);

            if ($this->isCacheValid($templatePath, $cacheFile)) {
                return $this->renderCached($cacheFile);
            }
        }

        // Compile template
        $compiled = $this->compile($templatePath);

        // Cache compiled template
        if ($this->cacheEnabled) {
            file_put_contents($cacheFile, $compiled);
        }

        // Render
        return $this->renderCompiled($compiled);
    }

    /**
     * Find template file
     */
    private function findTemplate(string $template): ?string
    {
        $template = str_replace('.', '/', $template);

        foreach ($this->paths as $path) {
            $fullPath = $path . '/' . $template . '.php';
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        return null;
    }

    /**
     * Compile template
     */
    private function compile(string $templatePath): string
    {
        $content = file_get_contents($templatePath);

        // Reset state
        $this->sections = [];
        $this->extendsLayout = null;

        // Compile directives
        $content = $this->compileExtends($content);
        $content = $this->compileSections($content);
        $content = $this->compileYields($content);
        $content = $this->compileComponents($content);
        $content = $this->compileEchos($content);
        $content = $this->compilePhp($content);

        return $content;
    }

    /**
     * Compile @extends directive
     */
    private function compileExtends(string $content): string
    {
        return preg_replace_callback(
            '/@extends\([\'"](.+?)[\'"]\)/',
            function($matches) {
                $this->extendsLayout = $matches[1];
                return '<?php $__layout = "' . $matches[1] . '"; ?>';
            },
            $content
        );
    }

    /**
     * Compile @section directive
     */
    private function compileSections(string $content): string
    {
        // @section('name')
        $content = preg_replace(
            '/@section\([\'"](.+?)[\'"]\)/',
            '<?php $__engine->startSection("$1"); ?>',
            $content
        );

        // @endsection
        $content = preg_replace(
            '/@endsection/',
            '<?php $__engine->endSection(); ?>',
            $content
        );

        return $content;
    }

    /**
     * Compile @yield directive
     */
    private function compileYields(string $content): string
    {
        return preg_replace(
            '/@yield\([\'"](.+?)[\'"](?:,\s*[\'"](.+?)[\'"]\s*)?\)/',
            '<?php echo $__engine->yieldSection("$1", "$2"); ?>',
            $content
        );
    }

    /**
     * Compile @component directive
     */
    private function compileComponents(string $content): string
    {
        // @component('name')
        $content = preg_replace(
            '/@component\([\'"](.+?)[\'"]\)/',
            '<?php echo $__engine->renderComponent("$1", get_defined_vars()); ?>',
            $content
        );

        return $content;
    }

    /**
     * Compile {{ }} echo statements
     */
    private function compileEchos(string $content): string
    {
        // {{ $var }} - escaped
        $content = preg_replace(
            '/\{\{\s*(.+?)\s*\}\}/',
            '<?php echo htmlspecialchars($1, ENT_QUOTES, \'UTF-8\'); ?>',
            $content
        );

        // {!! $var !!} - unescaped
        $content = preg_replace(
            '/\{!!\s*(.+?)\s*!!\}/',
            '<?php echo $1; ?>',
            $content
        );

        return $content;
    }

    /**
     * Compile @php directive
     */
    private function compilePhp(string $content): string
    {
        $content = preg_replace('/@php/', '<?php', $content);
        $content = preg_replace('/@endphp/', '?>', $content);
        return $content;
    }

    /**
     * Render compiled template
     */
    private function renderCompiled(string $compiled): string
    {
        extract($this->data);
        $__engine = $this;

        ob_start();
        eval('?>' . $compiled);
        $content = ob_get_clean();

        // If template extends layout, render layout with content
        if ($this->extendsLayout) {
            $this->sections['content'] = $content;
            return $this->render($this->extendsLayout, $this->data);
        }

        return $content;
    }

    /**
     * Render cached template
     */
    private function renderCached(string $cacheFile): string
    {
        return $this->renderCompiled(file_get_contents($cacheFile));
    }

    /**
     * Start capturing section
     */
    public function startSection(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }

    /**
     * End capturing section
     */
    public function endSection(): void
    {
        if ($this->currentSection) {
            $this->sections[$this->currentSection] = ob_get_clean();
            $this->currentSection = null;
        }
    }

    /**
     * Yield section content
     */
    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Render component
     */
    public function renderComponent(string $component, array $data = []): string
    {
        $componentPath = $this->findTemplate('components.' . $component);

        if (!$componentPath) {
            return "<!-- Component not found: {$component} -->";
        }

        extract(array_merge($this->data, $data));

        ob_start();
        include $componentPath;
        return ob_get_clean();
    }

    /**
     * Get cache file path
     */
    private function getCacheFile(string $templatePath): string
    {
        $hash = md5($templatePath);
        return $this->cachePath . '/' . $hash . '.php';
    }

    /**
     * Check if cache is valid
     */
    private function isCacheValid(string $templatePath, string $cacheFile): bool
    {
        if (!file_exists($cacheFile)) {
            return false;
        }

        return filemtime($cacheFile) >= filemtime($templatePath);
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        if (is_dir($this->cachePath)) {
            $files = glob($this->cachePath . '/*.php');
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Share data with all templates
     */
    public function share(string $key, $value): void
    {
        $this->data[$key] = $value;
    }
}
