<?php

namespace IntelligenceHub\MCP\Tools;

use MCP\Tools\CrawlerTool as LegacyCrawlerTool;

class CrawlerTools extends BaseTool {
    private LegacyCrawlerTool $legacy;

    public function __construct() {
        $this->legacy = new LegacyCrawlerTool();
    }

    public function getName(): string {
        return 'crawler';
    }

    public function getSchema(): array {
        return [
            'crawler.crawl' => [
                'description' => 'Crawl website pages',
                'parameters' => [
                    'url' => ['type' => 'string', 'required' => true],
                    'depth' => ['type' => 'integer', 'required' => false],
                    'max_pages' => ['type' => 'integer', 'required' => false]
                ]
            ],
            'crawler.analyze' => [
                'description' => 'Analyze website structure',
                'parameters' => [
                    'url' => ['type' => 'string', 'required' => true]
                ]
            ]
        ];
    }

    public function execute(array $args): array {
        $method = $args['_method'] ?? 'crawl';

        try {
            $result = $this->legacy->execute(array_merge(['action' => $method], $args));

            if (isset($result['success'])) {
                $status = $result['success'] ? 200 : 400;
                unset($result['success']);

                if (isset($result['error'])) {
                    return $this->fail($result['error'], $status);
                }

                return $this->ok($result, $status);
            }

            return $this->ok($result);

        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }
    }
}
