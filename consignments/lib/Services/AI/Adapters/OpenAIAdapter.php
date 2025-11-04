<?php
declare(strict_types=1);

namespace CIS\Consignments\Services\AI\Adapters;

use Exception;

/**
 * OpenAI API Adapter
 *
 * Supports: GPT-4o, GPT-4, GPT-3.5-turbo
 *
 * @package CIS\Consignments\Services\AI\Adapters
 */
class OpenAIAdapter
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function chat(string $prompt, array $context = [], array $options = []): array
    {
        $messages = $this->buildMessages($prompt, $context);

        $payload = [
            'model' => $options['model'] ?? $this->config['model'],
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 2000,
        ];

        if (!empty($options['functions'])) {
            $payload['functions'] = $options['functions'];
        }

        $response = $this->callAPI('/chat/completions', $payload);

        return $this->parseResponse($response);
    }

    private function buildMessages(string $prompt, array $context): array
    {
        $messages = [];

        // System message
        $messages[] = [
            'role' => 'system',
            'content' => 'You are an AI assistant for Consignment Management at The Vape Shed. Provide accurate, helpful guidance for transfers, carriers, and logistics.',
        ];

        // Context messages
        if (!empty($context['transfer_id'])) {
            $messages[] = [
                'role' => 'system',
                'content' => 'Transfer Context: ' . json_encode($context),
            ];
        }

        // User prompt
        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        return $messages;
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
                'Authorization: Bearer ' . $this->config['api_key'],
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("OpenAI API error: HTTP {$httpCode} - {$response}");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from OpenAI');
        }

        return $data;
    }

    private function parseResponse(array $response): array
    {
        $choice = $response['choices'][0] ?? null;
        if (!$choice) {
            throw new Exception('No choices in OpenAI response');
        }

        $message = $choice['message']['content'] ?? '';
        $functionCall = $choice['message']['function_call'] ?? null;

        return [
            'message' => $message,
            'function_call' => $functionCall,
            'model' => $response['model'] ?? 'unknown',
            'tokens_used' => $response['usage']['total_tokens'] ?? 0,
            'prompt_tokens' => $response['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
            'cost_usd' => $this->calculateCost($response['usage'] ?? []),
        ];
    }

    private function calculateCost(array $usage): float
    {
        $model = $this->config['model'];

        // Pricing per 1K tokens (as of Nov 2024)
        $pricing = [
            'gpt-4o' => ['prompt' => 0.0025, 'completion' => 0.01],
            'gpt-4' => ['prompt' => 0.03, 'completion' => 0.06],
            'gpt-3.5-turbo' => ['prompt' => 0.0005, 'completion' => 0.0015],
        ];

        $rates = $pricing[$model] ?? ['prompt' => 0.001, 'completion' => 0.002];

        $promptCost = ($usage['prompt_tokens'] ?? 0) / 1000 * $rates['prompt'];
        $completionCost = ($usage['completion_tokens'] ?? 0) / 1000 * $rates['completion'];

        return round($promptCost + $completionCost, 6);
    }
}
