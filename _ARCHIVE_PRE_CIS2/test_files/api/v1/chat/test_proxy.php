<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * BYOK PROXY TEST SUITE
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Comprehensive test suite for the BYOK proxy endpoint
 * Tests security, validation, routing, and performance
 */

require_once __DIR__ . '/../../../mcp/lib/Bootstrap.php';

// Test configuration
define('PROXY_URL', 'https://gpt.ecigdis.co.nz/api/v1/chat/completions');
define('API_KEY', getenv('MCP_API_KEY') ?: '31ce0106609a6c5bc4f7ece0deb2f764df90a06167bda83468883516302a6a35');

class ProxyTester {
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void {
        echo "🧪 BYOK Proxy Test Suite\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";

        // Security tests
        $this->testInvalidMethod();
        $this->testInvalidContentType();
        $this->testMissingApiKey();
        $this->testInvalidApiKey();
        $this->testRequestTooLarge();
        $this->testInvalidJson();

        // Validation tests
        $this->testMissingMessages();
        $this->testEmptyMessages();
        $this->testInvalidMessageFormat();
        $this->testInvalidTemperature();
        $this->testInvalidMaxTokens();

        // Functional tests
        $this->testOpenAISimple();
        $this->testOpenAIStreaming();
        $this->testAnthropicClaude();
        $this->testLongConversation();

        // Performance tests
        $this->testResponseTime();
        $this->testConcurrency();

        // Print results
        $this->printResults();
    }

    private function test(string $name, callable $fn): void {
        echo "Testing: $name... ";
        try {
            $result = $fn();
            if ($result) {
                echo "✅ PASS\n";
                $this->passed++;
                $this->results[$name] = ['status' => 'PASS'];
            } else {
                echo "❌ FAIL\n";
                $this->failed++;
                $this->results[$name] = ['status' => 'FAIL', 'reason' => 'Test returned false'];
            }
        } catch (Throwable $e) {
            echo "❌ FAIL: " . $e->getMessage() . "\n";
            $this->failed++;
            $this->results[$name] = ['status' => 'FAIL', 'reason' => $e->getMessage()];
        }
    }

    private function request(string $method, array $data = null, array $headers = []): array {
        $ch = curl_init(PROXY_URL);

        $defaultHeaders = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . API_KEY
        ];

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
            CURLOPT_TIMEOUT => 30
        ]);

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'body' => json_decode($response, true) ?: $response
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // SECURITY TESTS
    // ═══════════════════════════════════════════════════════════════

    private function testInvalidMethod(): void {
        $this->test('Invalid HTTP Method (GET)', function() {
            $result = $this->request('GET');
            return $result['code'] === 405;
        });
    }

    private function testInvalidContentType(): void {
        $this->test('Invalid Content-Type', function() {
            $ch = curl_init(PROXY_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: text/plain',
                    'Authorization: Bearer ' . API_KEY
                ],
                CURLOPT_POSTFIELDS => 'plain text'
            ]);
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code === 415;
        });
    }

    private function testMissingApiKey(): void {
        $this->test('Missing API Key', function() {
            $ch = curl_init(PROXY_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode(['messages' => [['role' => 'user', 'content' => 'test']]])
            ]);
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code === 401;
        });
    }

    private function testInvalidApiKey(): void {
        $this->test('Invalid API Key', function() {
            $ch = curl_init(PROXY_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer invalid_key_12345'
                ],
                CURLOPT_POSTFIELDS => json_encode(['messages' => [['role' => 'user', 'content' => 'test']]])
            ]);
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code === 401;
        });
    }

    private function testRequestTooLarge(): void {
        $this->test('Request Too Large', function() {
            $hugeContent = str_repeat('A', 2 * 1024 * 1024); // 2MB
            $result = $this->request('POST', [
                'messages' => [['role' => 'user', 'content' => $hugeContent]]
            ]);
            return $result['code'] === 413;
        });
    }

    private function testInvalidJson(): void {
        $this->test('Invalid JSON', function() {
            $ch = curl_init(PROXY_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . API_KEY
                ],
                CURLOPT_POSTFIELDS => '{invalid json}'
            ]);
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code === 400;
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // VALIDATION TESTS
    // ═══════════════════════════════════════════════════════════════

    private function testMissingMessages(): void {
        $this->test('Missing Messages Field', function() {
            $result = $this->request('POST', ['model' => 'gpt-4o']);
            return $result['code'] === 400;
        });
    }

    private function testEmptyMessages(): void {
        $this->test('Empty Messages Array', function() {
            $result = $this->request('POST', ['messages' => []]);
            return $result['code'] === 400;
        });
    }

    private function testInvalidMessageFormat(): void {
        $this->test('Invalid Message Format', function() {
            $result = $this->request('POST', [
                'messages' => [['role' => 'invalid', 'content' => 'test']]
            ]);
            return $result['code'] === 400;
        });
    }

    private function testInvalidTemperature(): void {
        $this->test('Invalid Temperature', function() {
            $result = $this->request('POST', [
                'messages' => [['role' => 'user', 'content' => 'test']],
                'temperature' => 5.0
            ]);
            return $result['code'] === 400;
        });
    }

    private function testInvalidMaxTokens(): void {
        $this->test('Invalid Max Tokens', function() {
            $result = $this->request('POST', [
                'messages' => [['role' => 'user', 'content' => 'test']],
                'max_tokens' => 999999
            ]);
            return $result['code'] === 400;
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // FUNCTIONAL TESTS
    // ═══════════════════════════════════════════════════════════════

    private function testOpenAISimple(): void {
        $this->test('OpenAI Simple Request', function() {
            $result = $this->request('POST', [
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'user', 'content' => 'Say "test successful" and nothing else.']
                ],
                'max_tokens' => 50
            ]);

            if ($result['code'] !== 200) return false;
            if (!isset($result['body']['choices'][0]['message']['content'])) return false;

            $content = $result['body']['choices'][0]['message']['content'];
            return stripos($content, 'test successful') !== false;
        });
    }

    private function testOpenAIStreaming(): void {
        $this->test('OpenAI Streaming Request', function() {
            $ch = curl_init(PROXY_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . API_KEY
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => 'gpt-4o',
                    'messages' => [['role' => 'user', 'content' => 'Count to 3']],
                    'stream' => true,
                    'max_tokens' => 50
                ]),
                CURLOPT_TIMEOUT => 30
            ]);

            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Check for SSE format
            return $code === 200 && strpos($response, 'data:') !== false;
        });
    }

    private function testAnthropicClaude(): void {
        $this->test('Anthropic Claude Request', function() {
            $result = $this->request('POST', [
                'model' => 'claude-3-5-sonnet-20241022',
                'messages' => [
                    ['role' => 'user', 'content' => 'Say "claude works" and nothing else.']
                ],
                'max_tokens' => 50
            ]);

            if ($result['code'] !== 200) return false;
            if (!isset($result['body']['choices'][0]['message']['content'])) return false;

            $content = $result['body']['choices'][0]['message']['content'];
            return stripos($content, 'claude works') !== false;
        });
    }

    private function testLongConversation(): void {
        $this->test('Long Conversation', function() {
            $result = $this->request('POST', [
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                    ['role' => 'user', 'content' => 'What is 2+2?'],
                    ['role' => 'assistant', 'content' => '2+2 equals 4.'],
                    ['role' => 'user', 'content' => 'What about 3+3?'],
                    ['role' => 'assistant', 'content' => '3+3 equals 6.'],
                    ['role' => 'user', 'content' => 'Say "conversation works"']
                ],
                'max_tokens' => 50
            ]);

            return $result['code'] === 200;
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // PERFORMANCE TESTS
    // ═══════════════════════════════════════════════════════════════

    private function testResponseTime(): void {
        $this->test('Response Time < 2s', function() {
            $start = microtime(true);

            $result = $this->request('POST', [
                'model' => 'gpt-4o',
                'messages' => [['role' => 'user', 'content' => 'Hi']],
                'max_tokens' => 10
            ]);

            $latency = microtime(true) - $start;
            echo sprintf(" (%.2fs)", $latency);

            return $result['code'] === 200 && $latency < 2.0;
        });
    }

    private function testConcurrency(): void {
        $this->test('Concurrent Requests (5x)', function() {
            $handles = [];
            $mh = curl_multi_init();

            // Create 5 concurrent requests
            for ($i = 0; $i < 5; $i++) {
                $ch = curl_init(PROXY_URL);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . API_KEY
                    ],
                    CURLOPT_POSTFIELDS => json_encode([
                        'model' => 'gpt-4o',
                        'messages' => [['role' => 'user', 'content' => "Request $i"]],
                        'max_tokens' => 10
                    ])
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[] = $ch;
            }

            // Execute
            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) curl_multi_select($mh);
            } while ($active && $status == CURLM_OK);

            // Check results
            $success = 0;
            foreach ($handles as $ch) {
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($code === 200) $success++;
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }
            curl_multi_close($mh);

            echo " ($success/5 succeeded)";
            return $success >= 4; // Allow 1 failure
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // RESULTS
    // ═══════════════════════════════════════════════════════════════

    private function printResults(): void {
        echo "\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "📊 TEST RESULTS\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "✅ Passed: {$this->passed}\n";
        echo "❌ Failed: {$this->failed}\n";
        echo "📈 Success Rate: " . round(($this->passed / ($this->passed + $this->failed)) * 100, 1) . "%\n";

        if ($this->failed > 0) {
            echo "\n🔴 Failed Tests:\n";
            foreach ($this->results as $name => $result) {
                if ($result['status'] === 'FAIL') {
                    echo "  • $name: {$result['reason']}\n";
                }
            }
        }

        echo "\n";

        if ($this->failed === 0) {
            echo "🎉 ALL TESTS PASSED! Proxy is production-ready.\n";
        } else {
            echo "⚠️  Some tests failed. Review errors above.\n";
        }
    }
}

// Run tests
$tester = new ProxyTester();
$tester->run();
