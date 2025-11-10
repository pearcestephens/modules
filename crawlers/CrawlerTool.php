<?php

namespace MCP\Tools;

/**
 * CrawlerTool - Comprehensive Web Crawler
 *
 * Wraps the existing deep-crawler.js (tested and working)
 * Provides multiple modes for different use cases
 *
 * Modes:
 *   - quick: Basic crawl only
 *   - authenticated: Login + crawl
 *   - interactive: Full interaction (clicks, forms, screenshots)
 *   - full: Everything (crawl, login, interact, screenshots, errors, AI)
 *   - errors_only: Just check 404s and JS errors
 *
 * @version 1.0.0
 */
class CrawlerTool
{
    private string $crawlerScript;
    private string $reportsDir;
    private int $timeout = 300; // 5 minutes default

    public function __construct()
    {
        // Use absolute path from application root
        $appRoot = dirname(dirname(dirname(__DIR__)));
        $this->crawlerScript = $appRoot . '/frontend-tools/scripts/deep-crawler.js';
        $this->reportsDir = $appRoot . '/frontend-tools/reports';

        // Ensure reports directory exists
        if (!is_dir($this->reportsDir)) {
            @mkdir($this->reportsDir, 0755, true);
        }
    }    /**
     * Execute crawler
     *
     * @param array $params {
     *   url: string (required) - URL to crawl
     *   mode: string (optional) - quick|authenticated|interactive|full|errors_only (default: quick)
     *   profile: string (optional) - cis_desktop|cis_mobile|cis_tablet|gpt_hub|customer (default: cis_desktop)
     *   depth: int (optional) - Max crawl depth (default: 2)
     *   timeout: int (optional) - Max execution time in seconds (default: 300)
     *   viewport: string (optional) - desktop|mobile|tablet|WIDTHxHEIGHT
     *   options: array (optional) - Additional crawler options
     * }
     * @return array
     */
    public function execute(array $params): array
    {
        // Validate required params
        if (empty($params['url'])) {
            return [
                'success' => false,
                'error' => 'Parameter "url" is required'
            ];
        }

        $url = filter_var($params['url'], FILTER_VALIDATE_URL);
        if (!$url) {
            return [
                'success' => false,
                'error' => 'Invalid URL provided'
            ];
        }

        // Check if crawler script exists
        if (!file_exists($this->crawlerScript)) {
            return [
                'success' => false,
                'error' => 'Crawler script not found at: ' . $this->crawlerScript
            ];
        }

        // Get mode (default: quick)
        $mode = $params['mode'] ?? 'quick';
        $validModes = ['quick', 'authenticated', 'interactive', 'full', 'errors_only'];
        if (!in_array($mode, $validModes)) {
            return [
                'success' => false,
                'error' => 'Invalid mode. Must be one of: ' . implode(', ', $validModes)
            ];
        }

        // Build command arguments based on mode
        $args = $this->buildCommandArgs($url, $mode, $params);

        // Build full command
        $command = sprintf(
            'cd %s && node %s %s 2>&1',
            escapeshellarg(dirname($this->crawlerScript)),
            escapeshellarg(basename($this->crawlerScript)),
            $args
        );

        // Execute crawler (non-blocking for long runs)
        $startTime = microtime(true);
        $output = [];
        $returnCode = 0;

        exec($command, $output, $returnCode);

        $duration = round(microtime(true) - $startTime, 2);

        // Parse output
        $result = $this->parseOutput($output, $returnCode, $mode);
        $result['execution_time'] = $duration;
        $result['mode'] = $mode;
        $result['url'] = $url;

        return $result;
    }

    /**
     * Build command arguments based on mode
     */
    private function buildCommandArgs(string $url, string $mode, array $params): string
    {
        $args = ['--url=' . escapeshellarg($url)];

        // Get profile
        $profile = $params['profile'] ?? 'cis_desktop';
        $validProfiles = ['cis_desktop', 'cis_mobile', 'cis_tablet', 'gpt_hub', 'customer'];
        if (in_array($profile, $validProfiles)) {
            $args[] = '--profile=' . escapeshellarg($profile);
        }

        // Get depth
        $depth = isset($params['depth']) ? (int)$params['depth'] : 2;
        if ($depth > 0 && $depth <= 10) {
            $args[] = '--max-depth=' . $depth;
        }

        // Get viewport
        if (!empty($params['viewport'])) {
            $args[] = '--viewport=' . escapeshellarg($params['viewport']);
        }

        // Get output directory
        $args[] = '--output=' . escapeshellarg($this->reportsDir);

        // Add mode-specific flags
        switch ($mode) {
            case 'quick':
                // Just basic crawl, no extras
                break;

            case 'authenticated':
                // Login + basic crawl
                $args[] = '--crawl-links';
                break;

            case 'interactive':
                // Full interaction but no AI
                $args[] = '--crawl-links';
                $args[] = '--click-all-buttons';
                $args[] = '--click-all-links';
                $args[] = '--fill-forms';
                break;

            case 'full':
                // EVERYTHING (your original workflow)
                $args[] = '--crawl-links';
                $args[] = '--click-all-buttons';
                $args[] = '--click-all-links';
                $args[] = '--fill-forms';
                // deep-crawler.js automatically captures:
                // - screenshots
                // - 404s
                // - JS errors
                // - console logs
                // - HAR files
                // - performance metrics
                break;

            case 'errors_only':
                // Just check for errors
                $args[] = '--crawl-links';
                // Crawler automatically captures errors
                break;
        }

        // Add any custom options
        if (!empty($params['options']) && is_array($params['options'])) {
            foreach ($params['options'] as $key => $value) {
                if (is_bool($value)) {
                    $args[] = '--' . escapeshellarg($key);
                } else {
                    $args[] = '--' . escapeshellarg($key) . '=' . escapeshellarg($value);
                }
            }
        }

        return implode(' ', $args);
    }

    /**
     * Parse crawler output
     */
    private function parseOutput(array $output, int $returnCode, string $mode): array
    {
        $outputText = implode("\n", $output);

        if ($returnCode !== 0) {
            return [
                'success' => false,
                'error' => 'Crawler failed with exit code ' . $returnCode,
                'output' => $outputText
            ];
        }

        // Try to parse JSON output if present
        $jsonMatch = [];
        if (preg_match('/\{.*"success".*\}/s', $outputText, $jsonMatch)) {
            $parsed = json_decode($jsonMatch[0], true);
            if ($parsed) {
                return $parsed;
            }
        }

        // Build result from output analysis
        $result = [
            'success' => true,
            'data' => []
        ];

        // Extract pages crawled count
        if (preg_match('/Crawled (\d+) pages?/i', $outputText, $matches)) {
            $result['data']['pages_crawled'] = (int)$matches[1];
        }

        // Extract screenshots
        if (preg_match_all('/Screenshot saved: (.+\.png)/i', $outputText, $matches)) {
            $result['data']['screenshots'] = $matches[1];
        }

        // Extract 404 errors
        if (preg_match_all('/404.*?:\s*(.+)/i', $outputText, $matches)) {
            $result['data']['errors_404'] = $matches[1];
        }

        // Extract JS errors
        if (preg_match_all('/JS Error.*?:\s*(.+)/i', $outputText, $matches)) {
            $result['data']['js_errors'] = $matches[1];
        }

        // Extract report path
        if (preg_match('/Report saved.*?:\s*(.+\.html)/i', $outputText, $matches)) {
            $result['data']['report_path'] = $matches[1];
            // Convert to URL if possible
            $reportFile = basename($matches[1]);
            $result['data']['report_url'] = 'https://gpt.ecigdis.co.nz/frontend-tools/reports/' . $reportFile;
        }

        // Add raw output for debugging
        $result['data']['raw_output'] = $outputText;

        // Add mode-specific messages
        $result['message'] = $this->getModeMessage($mode, $result['data']);

        return $result;
    }

    /**
     * Get mode-specific message
     */
    private function getModeMessage(string $mode, array $data): string
    {
        $pageCount = $data['pages_crawled'] ?? 0;

        switch ($mode) {
            case 'quick':
                return "Quick crawl completed. {$pageCount} pages analyzed.";

            case 'authenticated':
                return "Authenticated crawl completed. {$pageCount} pages accessed.";

            case 'interactive':
                $screenshots = count($data['screenshots'] ?? []);
                return "Interactive crawl completed. {$pageCount} pages, {$screenshots} screenshots captured.";

            case 'full':
                $screenshots = count($data['screenshots'] ?? []);
                $errors404 = count($data['errors_404'] ?? []);
                $jsErrors = count($data['js_errors'] ?? []);
                return "Full site audit completed. {$pageCount} pages, {$screenshots} screenshots, {$errors404} 404s, {$jsErrors} JS errors found.";

            case 'errors_only':
                $errors404 = count($data['errors_404'] ?? []);
                $jsErrors = count($data['js_errors'] ?? []);
                return "Error scan completed. {$errors404} 404s and {$jsErrors} JS errors found.";

            default:
                return "Crawl completed successfully.";
        }
    }

    /**
     * Get list of available profiles
     */
    public function getAvailableProfiles(): array
    {
        return [
            'cis_desktop' => [
                'name' => 'CIS Staff - Desktop',
                'viewport' => '1920x1080',
                'device' => 'desktop',
                'description' => 'Windows desktop browser with CIS login'
            ],
            'cis_mobile' => [
                'name' => 'CIS Staff - Mobile',
                'viewport' => '390x844',
                'device' => 'mobile',
                'description' => 'iPhone 14 Pro with CIS login'
            ],
            'cis_tablet' => [
                'name' => 'CIS Staff - Tablet',
                'viewport' => '1024x1366',
                'device' => 'tablet',
                'description' => 'iPad Pro with CIS login'
            ],
            'gpt_hub' => [
                'name' => 'GPT Hub User',
                'viewport' => '1440x900',
                'device' => 'desktop',
                'description' => 'Mac desktop with GPT Hub admin access'
            ],
            'customer' => [
                'name' => 'Customer Browser',
                'viewport' => '412x915',
                'device' => 'mobile',
                'description' => 'Samsung Galaxy mobile (anonymous)'
            ]
        ];
    }

    /**
     * Get tool metadata
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'crawler',
            'description' => 'Comprehensive web crawler with authentication, interaction testing, and error detection',
            'version' => '1.0.0',
            'modes' => [
                'quick' => 'Basic crawl only (fast)',
                'authenticated' => 'Login + crawl',
                'interactive' => 'Full interaction (clicks, forms, screenshots)',
                'full' => 'Complete audit (crawl, login, interact, screenshots, errors, AI analysis)',
                'errors_only' => 'Check 404s and JS errors only'
            ],
            'profiles' => array_keys($this->getAvailableProfiles()),
            'features' => [
                'Authentication with multiple profiles',
                'Deep site crawling with configurable depth',
                'Button and link clicking',
                'Form filling and submission',
                'Screenshot capture at every interaction',
                '404 error detection',
                'JavaScript error capture',
                'Console log monitoring',
                'Network request logging (HAR format)',
                'Performance metrics',
                'HTML report generation'
            ]
        ];
    }
}
