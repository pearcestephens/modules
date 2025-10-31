<?php
/**
 * BaseAPI - Standard Response Envelope
 *
 * Provides consistent JSON response format across all API endpoints
 * Following base class inheritance model
 *
 * @package CIS\AdminUI
 * @version 6.0.0
 */

class BaseAPI {
    protected $config;
    protected $errors = [];
    protected $data = [];

    public function __construct($config = []) {
        $this->config = array_merge([
            'base_path' => dirname(__DIR__),
            'debug_mode' => false,
            'max_execution_time' => 30
        ], $config);

        set_time_limit($this->config['max_execution_time']);
    }

    /**
     * Standard success response envelope
     */
    protected function success($data = null, $message = 'Operation successful', $meta = []) {
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => $this->generateRequestId()
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return $response;
    }

    /**
     * Standard error response envelope
     */
    protected function error($message, $code = 'ERROR', $details = null) {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'request_id' => $this->generateRequestId()
        ];

        if ($details !== null) {
            $response['error']['details'] = $details;
        }

        if ($this->config['debug_mode'] && !empty($this->errors)) {
            $response['debug'] = $this->errors;
        }

        return $response;
    }

    /**
     * Validate required fields
     */
    protected function validateRequired($data, $required) {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new Exception('Missing required fields: ' . implode(', ', $missing));
        }

        return true;
    }

    /**
     * Log error for debugging
     */
    protected function logError($message, $context = []) {
        $this->errors[] = [
            'message' => $message,
            'context' => $context,
            'time' => microtime(true)
        ];

        // Also log to file
        $logFile = $this->config['base_path'] . '/logs/api-errors.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logEntry = date('Y-m-d H:i:s') . ' | ' . $message;
        if (!empty($context)) {
            $logEntry .= ' | ' . json_encode($context);
        }
        $logEntry .= PHP_EOL;

        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Generate unique request ID
     */
    protected function generateRequestId() {
        return 'req_' . time() . '_' . substr(md5(uniqid()), 0, 8);
    }

    /**
     * Ensure directory exists
     */
    protected function ensureDirectory($path) {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        return $path;
    }

    /**
     * Sanitize filename
     */
    protected function sanitizeFilename($filename) {
        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    }

    /**
     * Send JSON response
     */
    protected function sendResponse($response) {
        header('Content-Type: application/json');
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Handle API request
     */
    public function handleRequest() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Only POST requests allowed');
            }

            if (!isset($_POST['action'])) {
                throw new Exception('Action parameter required');
            }

            $action = $_POST['action'];
            $methodName = 'handle' . str_replace('_', '', ucwords($action, '_'));

            if (!method_exists($this, $methodName)) {
                throw new Exception('Unknown action: ' . $action);
            }

            $result = $this->$methodName($_POST);
            $this->sendResponse($result);

        } catch (Exception $e) {
            $this->logError($e->getMessage(), [
                'action' => $_POST['action'] ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);

            $this->sendResponse($this->error(
                $e->getMessage(),
                'API_ERROR',
                $this->config['debug_mode'] ? $e->getTrace() : null
            ));
        }
    }
}
