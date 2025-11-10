<?php
/**
 * Intelligence API Client
 * 
 * Lightweight API client for accessing centralized intelligence
 * Deploy this on all client applications: jcepnzzkmj, dvaxgvsxmz, fhrehrpjmu
 * 
 * Usage:
 *   $client = new IntelligenceAPIClient('your_api_key');
 *   $results = $client->search('keyword');
 *   $doc = $client->getDocument('path/to/file.md');
 *   $tree = $client->getTree('documentation/jcepnzzkmj');
 *   $stats = $client->getStats();
 * 
 * @package Intelligence_API_Client
 * @version 1.0.0
 */

class IntelligenceAPIClient {
    private $api_key;
    private $base_url = 'https://gpt.ecigdis.co.nz/api/intelligence';
    private $cache_enabled = true;
    private $cache_duration = 300; // 5 minutes
    private $cache_dir;
    
    /**
     * Constructor
     * 
     * @param string $api_key API key for authentication
     * @param bool $cache_enabled Enable client-side caching
     */
    public function __construct($api_key, $cache_enabled = true) {
        $this->api_key = $api_key;
        $this->cache_enabled = $cache_enabled;
        $this->cache_dir = sys_get_temp_dir() . '/intelligence_cache';
        
        if ($this->cache_enabled && !is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    /**
     * Search centralized intelligence
     * 
     * @param string $query Search query
     * @param string $type Type filter: all, docs, code, business
     * @param int $limit Maximum results
     * @return array Search results
     */
    public function search($query, $type = 'all', $limit = 50) {
        $params = [
            'q' => $query,
            'type' => $type,
            'limit' => $limit
        ];
        
        return $this->request('GET', '/search', $params);
    }
    
    /**
     * Get specific document
     * 
     * @param string $path Document path relative to intelligence root
     * @return array Document data with content
     */
    public function getDocument($path) {
        $params = ['path' => $path];
        return $this->request('GET', '/document', $params, true); // Cache documents
    }
    
    /**
     * Get directory tree
     * 
     * @param string $path Directory path
     * @param int $depth Tree depth (max 10)
     * @return array Directory tree structure
     */
    public function getTree($path = '', $depth = 3) {
        $params = [
            'path' => $path,
            'depth' => $depth
        ];
        
        return $this->request('GET', '/tree', $params, true);
    }
    
    /**
     * Get system statistics
     * 
     * @return array System stats
     */
    public function getStats() {
        return $this->request('GET', '/stats', [], true);
    }
    
    /**
     * Extract intelligence (admin only)
     * 
     * @param string $path File path to extract
     * @param string $server Source server
     * @return array Extraction result
     */
    public function extract($path, $server) {
        $data = [
            'path' => $path,
            'server' => $server
        ];
        
        return $this->request('POST', '/extract', [], false, $data);
    }
    
    /**
     * Trigger neural scanner (admin only)
     * 
     * @param string $server Server to scan (or 'all')
     * @param bool $full_scan Full or incremental scan
     * @return array Scan result with scan_id
     */
    public function triggerScan($server = 'all', $full_scan = false) {
        $data = [
            'server' => $server,
            'full' => $full_scan
        ];
        
        return $this->request('POST', '/scan', [], false, $data);
    }
    
    /**
     * Make API request
     * 
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @param bool $use_cache Use cache for this request
     * @param array $data POST data
     * @return array API response
     */
    private function request($method, $endpoint, $params = [], $use_cache = false, $data = null) {
        // Check cache first
        if ($use_cache && $this->cache_enabled && $method === 'GET') {
            $cached = $this->getFromCache($endpoint, $params);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        // Build URL
        $url = $this->base_url . $endpoint;
        
        // Add query parameters
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Set API key header
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $this->api_key,
            'Content-Type: application/json'
        ]);
        
        // Set method and data for POST requests
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        // Execute request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Handle errors
        if ($error) {
            return [
                'success' => false,
                'error' => [
                    'message' => 'cURL error: ' . $error,
                    'code' => 0
                ]
            ];
        }
        
        // Parse response
        $result = json_decode($response, true);
        
        if ($result === null) {
            return [
                'success' => false,
                'error' => [
                    'message' => 'Invalid JSON response',
                    'code' => $http_code
                ]
            ];
        }
        
        // Cache successful GET requests
        if ($use_cache && $this->cache_enabled && $method === 'GET' && isset($result['success']) && $result['success']) {
            $this->saveToCache($endpoint, $params, $result);
        }
        
        return $result;
    }
    
    /**
     * Get from cache
     */
    private function getFromCache($endpoint, $params) {
        if (!$this->cache_enabled) {
            return null;
        }
        
        $cache_key = $this->getCacheKey($endpoint, $params);
        $cache_file = $this->cache_dir . '/' . $cache_key . '.json';
        
        if (file_exists($cache_file)) {
            $cache_time = filemtime($cache_file);
            if (time() - $cache_time < $this->cache_duration) {
                $content = file_get_contents($cache_file);
                return json_decode($content, true);
            }
        }
        
        return null;
    }
    
    /**
     * Save to cache
     */
    private function saveToCache($endpoint, $params, $data) {
        if (!$this->cache_enabled) {
            return;
        }
        
        $cache_key = $this->getCacheKey($endpoint, $params);
        $cache_file = $this->cache_dir . '/' . $cache_key . '.json';
        
        file_put_contents($cache_file, json_encode($data));
    }
    
    /**
     * Generate cache key
     */
    private function getCacheKey($endpoint, $params) {
        return md5($endpoint . serialize($params));
    }
    
    /**
     * Clear cache
     */
    public function clearCache() {
        if (!$this->cache_enabled || !is_dir($this->cache_dir)) {
            return;
        }
        
        $files = glob($this->cache_dir . '/*.json');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    /**
     * Enable/disable cache
     */
    public function setCacheEnabled($enabled) {
        $this->cache_enabled = $enabled;
    }
    
    /**
     * Set cache duration
     */
    public function setCacheDuration($seconds) {
        $this->cache_duration = $seconds;
    }
}

/**
 * Bot Command Handler
 * 
 * Handles bot commands like !doc, !search, !tree
 * Use this in your chatbot interface
 */
class IntelligenceBotCommands {
    private $client;
    
    public function __construct($api_key) {
        $this->client = new IntelligenceAPIClient($api_key);
    }
    
    /**
     * Handle bot command
     * 
     * @param string $command Full command string (e.g., "!doc file.md")
     * @return string Response message
     */
    public function handleCommand($command) {
        $command = trim($command);
        
        // Parse command
        if (!preg_match('/^!(\w+)\s*(.*)$/', $command, $matches)) {
            return "❌ Invalid command format. Use: !doc, !search, !tree, !stats";
        }
        
        $action = strtolower($matches[1]);
        $args = trim($matches[2]);
        
        switch ($action) {
            case 'doc':
                return $this->handleDocCommand($args);
                
            case 'search':
                return $this->handleSearchCommand($args);
                
            case 'tree':
                return $this->handleTreeCommand($args);
                
            case 'stats':
                return $this->handleStatsCommand();
                
            default:
                return "❌ Unknown command: !$action\nAvailable: !doc, !search, !tree, !stats";
        }
    }
    
    /**
     * Handle !doc command
     */
    private function handleDocCommand($filename) {
        if (empty($filename)) {
            return "❌ Usage: !doc filename.md";
        }
        
        // Search for the document first
        $search_result = $this->client->search($filename, 'docs', 10);
        
        if (!$search_result['success']) {
            return "❌ Error: " . $search_result['error']['message'];
        }
        
        if (empty($search_result['data']['results'])) {
            return "❌ Document not found: $filename";
        }
        
        // Get first matching document
        $doc = $search_result['data']['results'][0];
        $doc_result = $this->client->getDocument($doc['intelligence_path']);
        
        if (!$doc_result['success']) {
            return "❌ Error retrieving document: " . $doc_result['error']['message'];
        }
        
        $content = $doc_result['data']['content'];
        $preview = substr($content, 0, 500);
        if (strlen($content) > 500) {
            $preview .= "\n\n... (truncated, " . strlen($content) . " bytes total)";
        }
        
        return "📄 **{$doc['filename']}**\n\n$preview";
    }
    
    /**
     * Handle !search command
     */
    private function handleSearchCommand($query) {
        if (empty($query)) {
            return "❌ Usage: !search keyword";
        }
        
        $result = $this->client->search($query);
        
        if (!$result['success']) {
            return "❌ Error: " . $result['error']['message'];
        }
        
        $count = $result['data']['count'];
        if ($count === 0) {
            return "🔍 No results found for: $query";
        }
        
        $response = "🔍 **Found $count results for: $query**\n\n";
        
        foreach (array_slice($result['data']['results'], 0, 5) as $item) {
            $response .= "📄 **{$item['filename']}**\n";
            $response .= "   Category: {$item['category']}\n";
            $response .= "   Path: {$item['path']}\n";
            $response .= "   Server: {$item['source_server']}\n\n";
        }
        
        if ($count > 5) {
            $response .= "... and " . ($count - 5) . " more results";
        }
        
        return $response;
    }
    
    /**
     * Handle !tree command
     */
    private function handleTreeCommand($path) {
        $result = $this->client->getTree($path);
        
        if (!$result['success']) {
            return "❌ Error: " . $result['error']['message'];
        }
        
        $tree = $result['data']['tree'];
        $response = "🌳 **Directory Tree: " . ($path ?: '/') . "**\n\n";
        $response .= $this->formatTree($tree, 0);
        
        return $response;
    }
    
    /**
     * Format tree structure
     */
    private function formatTree($items, $level) {
        if (empty($items)) {
            return "";
        }
        
        $output = "";
        $indent = str_repeat("  ", $level);
        
        foreach ($items as $item) {
            $icon = $item['type'] === 'directory' ? '📁' : '📄';
            $output .= $indent . "$icon {$item['name']}\n";
            
            if ($item['type'] === 'directory' && !empty($item['children'])) {
                $output .= $this->formatTree($item['children'], $level + 1);
            }
        }
        
        return $output;
    }
    
    /**
     * Handle !stats command
     */
    private function handleStatsCommand() {
        $result = $this->client->getStats();
        
        if (!$result['success']) {
            return "❌ Error: " . $result['error']['message'];
        }
        
        $stats = $result['data'];
        
        $response = "📊 **Intelligence System Statistics**\n\n";
        $response .= "Total Files: " . number_format($stats['total_files']) . "\n";
        $response .= "Total Size: " . $this->formatBytes($stats['total_size']) . "\n";
        $response .= "API Version: {$stats['api_version']}\n";
        $response .= "Client: {$stats['client_name']}\n\n";
        
        $response .= "**By Category:**\n";
        foreach ($stats['by_category'] as $category => $count) {
            $response .= "  - $category: " . number_format($count) . "\n";
        }
        
        $response .= "\n**By Server:**\n";
        foreach ($stats['by_server'] as $server => $count) {
            $response .= "  - $server: " . number_format($count) . "\n";
        }
        
        return $response;
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }
        return round($bytes, 2) . ' ' . $units[$index];
    }
}

// Example usage documentation
if (basename(__FILE__) === 'IntelligenceAPIClient.php' && php_sapi_name() === 'cli') {
    echo <<<USAGE

Intelligence API Client - Usage Examples
========================================

Basic Search:
-------------
\$client = new IntelligenceAPIClient('your_api_key');
\$results = \$client->search('neural scanner');
print_r(\$results);

Get Document:
------------
\$doc = \$client->getDocument('documentation/jcepnzzkmj/README.md');
echo \$doc['data']['content'];

Browse Directory:
----------------
\$tree = \$client->getTree('documentation/jcepnzzkmj', 2);
print_r(\$tree['data']['tree']);

Bot Commands:
------------
\$bot = new IntelligenceBotCommands('your_api_key');
echo \$bot->handleCommand('!search neural');
echo \$bot->handleCommand('!doc README.md');
echo \$bot->handleCommand('!tree documentation');
echo \$bot->handleCommand('!stats');

USAGE;
}
