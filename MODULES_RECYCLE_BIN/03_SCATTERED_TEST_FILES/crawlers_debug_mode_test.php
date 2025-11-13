<?php
require_once __DIR__ . '/CrawlerTool.php';
use MCP\Tools\CrawlerTool;

$crawler = new CrawlerTool();
$result = $crawler->execute([
    'url' => 'https://example.com',
    'mode' => 'invalid_mode'
]);

echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "Error: " . ($result['error'] ?? 'none') . "\n";
echo "Full result:\n";
print_r($result);
