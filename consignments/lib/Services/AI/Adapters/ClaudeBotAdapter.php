<?php
declare(strict_types=1);

namespace CIS\Consignments\Services\AI\Adapters;

use Exception;

/**
 * Claude Bot Adapter
 *
 * Custom Claude instance configured specifically for your needs.
 * Can be:
 * - Self-hosted Claude via API
 * - Claude via AWS Bedrock
 * - Custom Claude configuration
 * - Claude with specialized prompts/tools
 *
 * @package CIS\Consignments\Services\AI\Adapters
 */
class ClaudeBotAdapter
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Chat with Claude Bot
     *
     * @param string $prompt User prompt
     * @param array $context Transfer/system context
     * @param array $options Additional options
     * @return array Standardized response
     */
    public function chat(string $prompt, array $context = [], array $options = []): array
    {
        // Determine endpoint format (depends on your Claude Bot setup)
        $endpointType = $this->detectEndpointType();

        switch ($endpointType) {
            case 'anthropic':
                return $this->callAnthropicFormat($prompt, $context, $options);

            case 'aws_bedrock':
                return $this->callAWSBedrock($prompt, $context, $options);

            case 'custom':
            default:
                return $this->callCustomFormat($prompt, $context, $options);
        }
    }

    /**
     * Standard Anthropic API format
     */
    private function callAnthropicFormat(string $prompt, array $context, array $options): array
    {
        $systemPrompt = $this->buildSystemPrompt($context);

        $payload = [
            'model' => $options['model'] ?? 'claude-3-5-sonnet-20241022',
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'system' => $systemPrompt,
        ];

        $response = $this->callAPI($payload, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->config['api_key'],
            'anthropic-version: 2023-06-01',
        ]);

        return $this->parseAnthropicResponse($response);
    }

    /**
     * AWS Bedrock format (Claude via AWS)
     */
    private function callAWSBedrock(string $prompt, array $context, array $options): array
    {
        $systemPrompt = $this->buildSystemPrompt($context);

        $payload = [
            'anthropic_version' => 'bedrock-2023-05-31',
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'system' => $systemPrompt,
        ];

        // AWS Bedrock requires AWS signature - simplified here
        $response = $this->callAPI($payload, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config['api_key'],
        ]);

        return $this->parseAnthropicResponse($response);
    }

    /**
     * Custom format (your specific Claude Bot implementation)
     */
    private function callCustomFormat(string $prompt, array $context, array $options): array
    {
        // Flexible payload for custom Claude Bot
        $payload = [
            'prompt' => $prompt,
            'context' => $context,
            'options' => $options,
            'system_prompt' => $this->buildSystemPrompt($context),
            'model' => $options['model'] ?? 'claude-3-5-sonnet-20241022',
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        // Add any custom auth
        $headers = [
            'Content-Type: application/json',
        ];

        if (!empty($this->config['api_key'])) {
            $headers[] = 'Authorization: Bearer ' . $this->config['api_key'];
        }

        $response = $this->callAPI($payload, $headers);

        return $this->parseCustomResponse($response);
    }

    /**
     * Build system prompt with context
     */
    private function buildSystemPrompt(array $context): string
    {
        $system = 'You are Claude, a specialized AI assistant for Consignment Management at The Vape Shed (Ecigdis Limited). ';
        $system .= 'You have deep knowledge of inventory management, logistics, and transfer operations. ';
        $system .= 'Provide thorough, well-reasoned guidance with clear explanations.';

        if (!empty($context['transfer_id'])) {
            $system .= "\n\nCurrent Transfer Context:\n";
            $system .= "Transfer ID: {$context['transfer_id']}\n";

            if (!empty($context['from_outlet'])) {
                $system .= "From: {$context['from_outlet']}\n";
            }

            if (!empty($context['to_outlet'])) {
                $system .= "To: {$context['to_outlet']}\n";
            }

            if (!empty($context['status'])) {
                $system .= "Status: {$context['status']}\n";
            }
        }

        return $system;
    }

    /**
     * Call Claude Bot API
     */
    private function callAPI(array $payload, array $headers): array
    {
        if (empty($this->config['endpoint'])) {
            throw new Exception('Claude Bot endpoint not configured');
        }

        $ch = curl_init($this->config['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Claude Bot connection error: {$curlError}");
        }

        if ($httpCode !== 200) {
            throw new Exception("Claude Bot API error: HTTP {$httpCode} - {$response}");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from Claude Bot');
        }

        return $data;
    }

    /**
     * Parse Anthropic-format response
     */
    private function parseAnthropicResponse(array $response): array
    {
        $content = $response['content'][0] ?? null;
        if (!$content) {
            throw new Exception('No content in response');
        }

        return [
            'message' => $content['text'] ?? '',
            'model' => $response['model'] ?? 'claude-bot',
            'tokens_used' => ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0),
            'prompt_tokens' => $response['usage']['input_tokens'] ?? 0,
            'completion_tokens' => $response['usage']['output_tokens'] ?? 0,
            'cost_usd' => $this->calculateCost($response['usage'] ?? []),
            'stop_reason' => $response['stop_reason'] ?? 'unknown',
        ];
    }

    /**
     * Parse custom-format response
     */
    private function parseCustomResponse(array $response): array
    {
        return [
            'message' => $response['response'] ?? $response['message'] ?? $response['content'] ?? '',
            'model' => $response['model'] ?? 'claude-bot',
            'tokens_used' => $response['tokens_used'] ?? 0,
            'cost_usd' => $response['cost_usd'] ?? 0,
            'processing_time_ms' => $response['processing_time_ms'] ?? 0,
        ];
    }

    /**
     * Calculate cost (Claude pricing)
     */
    private function calculateCost(array $usage): float
    {
        // Claude 3.5 Sonnet pricing (per 1M tokens)
        $inputCost = ($usage['input_tokens'] ?? 0) / 1_000_000 * 3.00;
        $outputCost = ($usage['output_tokens'] ?? 0) / 1_000_000 * 15.00;

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Detect endpoint type from config
     */
    private function detectEndpointType(): string
    {
        $endpoint = $this->config['endpoint'] ?? '';

        if (str_contains($endpoint, 'anthropic.com')) {
            return 'anthropic';
        }

        if (str_contains($endpoint, 'bedrock') || str_contains($endpoint, 'amazonaws.com')) {
            return 'aws_bedrock';
        }

        return 'custom';
    }
}
