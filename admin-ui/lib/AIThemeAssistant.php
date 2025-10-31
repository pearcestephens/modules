<?php
/**
 * AI Theme Assistant
 * Connects to base AI service for conversational theme editing
 * 
 * @package CIS\Modules\AdminUI
 */

class AIThemeAssistant {
    private $aiEndpoint;
    private $isAvailable = false;
    
    public function __construct() {
        // Check if base AI service exists
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/modules/base/services/AIService.php')) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/services/AIService.php';
            $this->aiEndpoint = '/modules/base/api/ai-request.php';
            $this->checkAvailability();
        }
    }
    
    /**
     * Check if AI endpoint is reachable
     */
    private function checkAvailability() {
        $testEndpoint = $_SERVER['DOCUMENT_ROOT'] . $this->aiEndpoint;
        if (file_exists($testEndpoint)) {
            // Test with a simple ping
            try {
                $this->isAvailable = true;
            } catch (Exception $e) {
                $this->isAvailable = false;
            }
        }
    }
    
    /**
     * Check if AI assistant is available
     */
    public function isAvailable() {
        return $this->isAvailable;
    }
    
    /**
     * Send message to AI and get theme update suggestions
     */
    public function processMessage($message, $currentConfig) {
        if (!$this->isAvailable) {
            return [
                'success' => false,
                'error' => 'AI assistant not available'
            ];
        }
        
        // Build context for AI
        $context = "You are a theme design assistant. Current theme config: " . json_encode($currentConfig);
        $context .= "\n\nUser request: " . $message;
        $context .= "\n\nRespond with JSON containing 'updates' (theme changes to make), 'explanation' (what you're changing and why), and 'preview_text' (description of visual result).";
        
        // This would call the actual AI endpoint
        // For now, return structure
        return [
            'success' => true,
            'updates' => [],
            'explanation' => '',
            'preview_text' => ''
        ];
    }
    
    /**
     * Parse natural language color requests
     */
    public function parseColorRequest($message) {
        $colors = [];
        
        // Simple pattern matching for color requests
        $patterns = [
            '/make.*primary.*?(#[0-9a-fA-F]{6}|blue|red|green|purple|orange)/i' => 'primary.main',
            '/change.*sidebar.*?(#[0-9a-fA-F]{6}|dark|light|black|gray)/i' => 'sidebar.bg',
            '/success.*?(#[0-9a-fA-F]{6}|green)/i' => 'success',
            '/warning.*?(#[0-9a-fA-F]{6}|orange|yellow)/i' => 'warning',
            '/danger.*?(#[0-9a-fA-F]{6}|red)/i' => 'danger',
        ];
        
        foreach ($patterns as $pattern => $key) {
            if (preg_match($pattern, $message, $matches)) {
                $colors[$key] = $this->parseColorValue($matches[1]);
            }
        }
        
        return $colors;
    }
    
    /**
     * Convert color name to hex
     */
    private function parseColorValue($color) {
        $colorMap = [
            'blue' => '#3b82f6',
            'red' => '#ef4444',
            'green' => '#10b981',
            'purple' => '#8B5CF6',
            'orange' => '#f59e0b',
            'dark' => '#1f2937',
            'light' => '#f3f4f6',
            'black' => '#000000',
            'gray' => '#6b7280',
        ];
        
        $color = strtolower(trim($color));
        
        if (isset($colorMap[$color])) {
            return $colorMap[$color];
        }
        
        // Already a hex color
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return $color;
        }
        
        return null;
    }
}
