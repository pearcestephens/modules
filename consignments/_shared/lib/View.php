<?php
declare(strict_types=1);

namespace Modules\Shared;

/**
 * Minimal view engine with sections, stacks, and partials.
 */
class View
{
    private array $sections = [];
    private array $sectionStack = [];
    private array $stacks = [];

    public function start(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function end(): void
    {
        $name = array_pop($this->sectionStack);
        $this->sections[$name] = ($this->sections[$name] ?? '') . (string)ob_get_clean();
    }

    public function section(string $name, string $default = ''): void
    {
        echo $this->sections[$name] ?? $default;
    }

    public function push(string $stack, string $content): void
    {
        $this->stacks[$stack] = $this->stacks[$stack] ?? [];
        $this->stacks[$stack][] = $content;
    }

    public function stack(string $stack): void
    {
        if (!empty($this->stacks[$stack])) {
            echo implode("\n", $this->stacks[$stack]);
        }
    }

    public function render(string $viewFile, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        return (string)ob_get_clean();
    }

    public function include(string $partialFile, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require $partialFile;
    }
}
