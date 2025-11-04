<?php
declare(strict_types=1);

namespace CIS\Consignments\Services\AI\Adapters;

use Exception;

/**
 * Anthropic Claude API Adapter
 *
 * Supports: Claude 3.5 Sonnet, Claude 3 Opus, Claude 3 Sonnet
 *
 * @package CIS\Consignments\Services\AI\Adapters
 */
class AnthropicAdapter
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function chat(string $prompt, array $context = [], array $options = []): array
    {
        $systemPrompt = $this->buildSystemPrompt($context);

        $payload = [
            'model' => $options['model'] ?? $this->config['model'],
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'system' => $systemPrompt,
        ];

        if (!empty($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        $response = $this->callAPI('/messages', $payload);

        return $this->parseResponse($response);
    }

    private function buildSystemPrompt(array $context): string
    {
        $system = 'You are Claude, an AI assistant for Consignment Management at The Vape Shed. ';
        $system .= 'Provide accurate, detailed guidance for transfers, carriers, and logistics. ';
        $system .= 'Be thorough in your reasoning and analysis.';

        if (!empty($context['transfer_id'])) {
            $system .= "\n\nTransfer Context:\n" . json_encode($context, JSON_PRETTY_PRINT);
        }

        return $system;
    }

    private function callAPI(string $endpoint, array $payload): array
    {
        $url = rtrim($this->config['endpoint'], '/') . $endpoint;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->config['api_key'],
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT => 60, // Claude can be slower but more thorough
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Anthropic API error: HTTP {$httpCode} - {$response}");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from Anthropic');
        }

        return $data;
    }

    private function parseResponse(array $response): array
    {
        $content = $response['content'][0] ?? null;
        if (!$content) {
            throw new Exception('No content in Anthropic response');
        }

        $message = $content['text'] ?? '';

        return [
            'message' => $message,
            'model' => $response['model'] ?? 'unknown',
            'tokens_used' => ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0),
            'prompt_tokens' => $response['usage']['input_tokens'] ?? 0,
            'completion_tokens' => $response['usage']['output_tokens'] ?? 0,
            'cost_usd' => $this->calculateCost($response['usage'] ?? []),
            'stop_reason' => $response['stop_reason'] ?? 'unknown',
        ];
    }

    private function calculateCost(array $usage): float
    {
        $model = $this->config['model'];

        // Pricing per 1M tokens (as of Nov 2024)
        $pricing = [
            'claude-3-5-sonnet-20241022' => ['input' => 3.00, 'output' => 15.00],
            'claude-3-opus-20240229' => ['input' => 15.00, 'output' => 75.00],
            'claude-3-sonnet-20240229' => ['input' => 3.00, 'output' => 15.00],
        ];

        $rates = $pricing[$model] ?? ['input' => 3.00, 'output' => 15.00];

        $inputCost = ($usage['input_tokens'] ?? 0) / 1_000_000 * $rates['input'];
        $outputCost = ($usage['output_tokens'] ?? 0) / 1_000_000 * $rates['output'];

        return round($inputCost + $outputCost, 6);
    }
}
