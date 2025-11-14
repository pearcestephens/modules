<?php
/**
 * HTTP Test Client for Consignments API
 *
 * Provides a reusable HTTP client for making API requests in tests.
 * Handles authentication, CSRF tokens, cookie management, and error handling.
 *
 * @package ConsignmentsModule\Tests
 */

namespace ConsignmentsModule\Tests;

class HttpTestClient
{
    private $baseUrl;
    private $cookieJar = [];
    private $csrfToken = '';
    private $sessionId = '';
    private $lastResponse = null;
    private $lastStatusCode = 0;
    private $lastHeaders = [];
    private $authenticatedUser = null;

    public function __construct($baseUrl = 'https://staff.vapeshed.co.nz')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Make an HTTP request
     */
    public function request($method, $endpoint, $data = null, $headers = [])
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        // Create cURL handle
        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];

        // Set method
        switch (strtoupper($method)) {
            case 'GET':
                $options[CURLOPT_HTTPGET] = true;
                break;
            case 'POST':
                $options[CURLOPT_POST] = true;
                if ($data) {
                    $options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
                }
                break;
            case 'PUT':
                $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                if ($data) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;
            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
            case 'PATCH':
                $options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
                if ($data) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;
        }

        // Add CSRF token if available
        if ($this->csrfToken) {
            $headers['X-CSRF-Token'] = $this->csrfToken;
        }

        // Add content type for JSON
        if (is_array($data) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            if (!isset($headers['Content-Type'])) {
                $headers['Content-Type'] = 'application/json';
                if (isset($options[CURLOPT_POSTFIELDS]) && is_array($data)) {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
            }
        }

        // Add cookies
        if (!empty($this->cookieJar)) {
            $cookieStr = $this->cookiesToString();
            if ($cookieStr) {
                $options[CURLOPT_COOKIE] = $cookieStr;
            }
        }

        // Convert headers to curl format
        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = "$key: $value";
        }
        if (!empty($curlHeaders)) {
            $options[CURLOPT_HTTPHEADER] = $curlHeaders;
        }

        // Set options
        curl_setopt_array($ch, $options);

        // Execute request
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0) {
            throw new \RuntimeException("cURL error ($errno): $error");
        }

        // Parse response
        return $this->parseResponse($response);
    }

    /**
     * Parse HTTP response into headers and body
     */
    private function parseResponse($response)
    {
        // Split headers and body
        $parts = explode("\r\n\r\n", $response, 2);
        $headerText = $parts[0];
        $body = $parts[1] ?? '';

        // Parse headers
        $lines = explode("\r\n", $headerText);
        $statusLine = array_shift($lines);

        preg_match('/HTTP\/\d\.\d (\d+)/', $statusLine, $matches);
        $this->lastStatusCode = (int)($matches[1] ?? 0);

        $this->lastHeaders = [];
        foreach ($lines as $line) {
            if (strpos($line, ':') === false) continue;
            [$key, $value] = explode(':', $line, 2);
            $this->lastHeaders[strtolower(trim($key))] = trim($value);
        }

        // Extract and store cookies
        $this->extractCookies($this->lastHeaders);

        // Store response
        $this->lastResponse = $body;

        // Try to decode JSON
        if ($this->isJson($body)) {
            try {
                return json_decode($body, true);
            } catch (\Exception $e) {
                return $body;
            }
        }

        return $body;
    }

    /**
     * Check if content is JSON
     */
    private function isJson($string)
    {
        if (!is_string($string)) return false;
        $string = trim($string);
        return (($string[0] ?? '') === '{' || ($string[0] ?? '') === '[') &&
               json_decode($string) !== null;
    }

    /**
     * Extract cookies from response headers
     */
    private function extractCookies($headers)
    {
        if (!isset($headers['set-cookie'])) {
            return;
        }

        $cookies = $headers['set-cookie'];
        if (!is_array($cookies)) {
            $cookies = [$cookies];
        }

        foreach ($cookies as $cookie) {
            // Parse cookie: name=value; Path=/; ...
            $parts = explode(';', $cookie);
            if (empty($parts[0])) continue;

            [$name, $value] = explode('=', $parts[0], 2);
            $this->cookieJar[trim($name)] = trim($value);

            // Store session ID if present
            if (strtolower(trim($name)) === 'phpsessid') {
                $this->sessionId = trim($value);
            }
        }
    }

    /**
     * Convert cookie jar to Cookie header string
     */
    private function cookiesToString()
    {
        $parts = [];
        foreach ($this->cookieJar as $name => $value) {
            $parts[] = "$name=$value";
        }
        return implode('; ', $parts);
    }

    /**
     * Authenticate with username/password
     */
    public function authenticate($username, $password)
    {
        // First, get the login page to extract CSRF token
        $loginPage = $this->request('GET', 'auth/login');

        // Extract CSRF token from login form
        if (is_string($loginPage)) {
            if (preg_match('/name="csrf".*?value="([^"]+)"/', $loginPage, $matches)) {
                $this->csrfToken = $matches[1];
            }
        }

        // Attempt login
        $response = $this->request('POST', 'auth/login', [
            'username' => $username,
            'password' => $password,
            'csrf' => $this->csrfToken
        ]);

        // Check for success
        if (is_array($response) && ($response['success'] ?? false)) {
            $this->authenticatedUser = $username;
            return true;
        }

        throw new \RuntimeException('Authentication failed');
    }

    /**
     * Authenticate using API token
     */
    public function authenticateWithToken($token)
    {
        // Store token for future requests
        $this->bearerToken = $token;
        $this->authenticatedUser = 'token-auth';
        return true;
    }

    /**
     * Get request with query parameters
     */
    public function get($endpoint, $params = [])
    {
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }
        return $this->request('GET', $endpoint);
    }

    /**
     * Post request
     */
    public function post($endpoint, $data = [])
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Put request
     */
    public function put($endpoint, $data = [])
    {
        return $this->request('PUT', $endpoint, $data);
    }

    /**
     * Delete request
     */
    public function delete($endpoint)
    {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Patch request
     */
    public function patch($endpoint, $data = [])
    {
        return $this->request('PATCH', $endpoint, $data);
    }

    /**
     * Get last response status code
     */
    public function getStatusCode()
    {
        return $this->lastStatusCode;
    }

    /**
     * Get last response headers
     */
    public function getHeaders()
    {
        return $this->lastHeaders;
    }

    /**
     * Get last response body
     */
    public function getBody()
    {
        return $this->lastResponse;
    }

    /**
     * Get full last response
     */
    public function getLastResponse()
    {
        return [
            'status_code' => $this->lastStatusCode,
            'headers' => $this->lastHeaders,
            'body' => $this->lastResponse
        ];
    }

    /**
     * Check if last request was successful (2xx)
     */
    public function isSuccess()
    {
        return $this->lastStatusCode >= 200 && $this->lastStatusCode < 300;
    }

    /**
     * Check if last request was a client error (4xx)
     */
    public function isClientError()
    {
        return $this->lastStatusCode >= 400 && $this->lastStatusCode < 500;
    }

    /**
     * Check if last request was a server error (5xx)
     */
    public function isServerError()
    {
        return $this->lastStatusCode >= 500;
    }

    /**
     * Get CSRF token
     */
    public function getCSRFToken()
    {
        return $this->csrfToken;
    }

    /**
     * Set CSRF token manually
     */
    public function setCSRFToken($token)
    {
        $this->csrfToken = $token;
    }

    /**
     * Get session ID
     */
    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
     * Assert response status code
     */
    public function assertStatus($expectedCode, $message = '')
    {
        if ($this->lastStatusCode !== $expectedCode) {
            throw new \AssertionError(
                "Expected HTTP $expectedCode, got {$this->lastStatusCode}. $message\n" .
                "Response: {$this->lastResponse}"
            );
        }
        return true;
    }

    /**
     * Assert response contains value
     */
    public function assertContains($value, $message = '')
    {
        if (strpos($this->lastResponse, $value) === false) {
            throw new \AssertionError(
                "Response does not contain '$value'. $message\n" .
                "Response: {$this->lastResponse}"
            );
        }
        return true;
    }

    /**
     * Assert JSON response has key
     */
    public function assertJsonHasKey($key, $message = '')
    {
        $data = json_decode($this->lastResponse, true);
        if (!is_array($data) || !array_key_exists($key, $data)) {
            throw new \AssertionError(
                "JSON response does not have key '$key'. $message"
            );
        }
        return true;
    }

    /**
     * Assert JSON response value
     */
    public function assertJsonValue($key, $value, $message = '')
    {
        $data = json_decode($this->lastResponse, true);
        if (!is_array($data) || !isset($data[$key]) || $data[$key] !== $value) {
            $actual = $data[$key] ?? 'undefined';
            throw new \AssertionError(
                "Expected JSON $key='$value', got '$actual'. $message"
            );
        }
        return true;
    }

    /**
     * Dump response for debugging
     */
    public function dump()
    {
        echo "Status: {$this->lastStatusCode}\n";
        echo "Headers: " . json_encode($this->lastHeaders, JSON_PRETTY_PRINT) . "\n";
        echo "Body: {$this->lastResponse}\n";
    }
}
