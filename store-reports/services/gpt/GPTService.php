<?php
/**
 * GPT Service - Base OpenAI GPT Integration
 *
 * Handles communication with OpenAI API for text generation,
 * analysis, and intelligent insights.
 *
 * @package StoreReports
 * @subpackage Services\GPT
 */

class GPTService
{
    private $apiKey;
    private $baseUrl = 'https://api.openai.com/v1';
    private $model = 'gpt-4-turbo-preview';
    private $maxTokens = 4096;
    private $temperature = 0.7;

    /**
     * Initialize GPT Service
     */
    public function __construct($config = [])
    {
        $this->apiKey = $config['openai_api_key'] ?? getenv('OPENAI_API_KEY');

        if (empty($this->apiKey)) {
            throw new Exception('OpenAI API key not configured');
        }

        // Override defaults with config
        if (isset($config['model'])) $this->model = $config['model'];
        if (isset($config['max_tokens'])) $this->maxTokens = $config['max_tokens'];
        if (isset($config['temperature'])) $this->temperature = $config['temperature'];
    }

    /**
     * Generate text completion
     *
     * @param string $prompt The prompt to send
     * @param array $options Optional parameters
     * @return array Response with 'content' and 'usage'
     */
    public function complete($prompt, $options = [])
    {
        $messages = [
            ['role' => 'system', 'content' => $options['system'] ?? 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => $prompt]
        ];

        return $this->chat($messages, $options);
    }

    /**
     * Chat completion with message history
     *
     * @param array $messages Array of message objects with 'role' and 'content'
     * @param array $options Optional parameters
     * @return array Response with 'content' and 'usage'
     */
    public function chat($messages, $options = [])
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
            'temperature' => $options['temperature'] ?? $this->temperature,
        ];

        // Add optional parameters
        if (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        if (isset($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }

        $response = $this->makeRequest('/chat/completions', $payload);

        return [
            'content' => $response['choices'][0]['message']['content'] ?? '',
            'usage' => $response['usage'] ?? [],
            'finish_reason' => $response['choices'][0]['finish_reason'] ?? null,
            'raw' => $response
        ];
    }

    /**
     * Generate structured JSON response
     *
     * @param string $prompt The prompt
     * @param array $schema JSON schema for response
     * @return array Parsed JSON response
     */
    public function generateJSON($prompt, $schema = null, $options = [])
    {
        $options['response_format'] = ['type' => 'json_object'];

        $systemPrompt = 'You are a helpful assistant that responds in JSON format.';
        if ($schema) {
            $systemPrompt .= "\n\nExpected JSON structure: " . json_encode($schema);
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt]
        ];

        $result = $this->chat($messages, $options);

        try {
            $json = json_decode($result['content'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response: ' . json_last_error_msg());
            }
            return $json;
        } catch (Exception $e) {
            error_log("GPT JSON parse error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Analyze text and extract insights
     *
     * @param string $text Text to analyze
     * @param string $analysisType Type of analysis (sentiment, summary, keywords, etc.)
     * @return array Analysis results
     */
    public function analyzeText($text, $analysisType = 'general')
    {
        $prompts = [
            'sentiment' => "Analyze the sentiment of this text and provide a sentiment score from -1 (very negative) to 1 (very positive), along with reasoning:\n\n{$text}",
            'summary' => "Provide a concise summary of the following text:\n\n{$text}",
            'keywords' => "Extract the key topics, themes, and important keywords from this text:\n\n{$text}",
            'general' => "Analyze this text and provide key insights, themes, and important points:\n\n{$text}"
        ];

        $prompt = $prompts[$analysisType] ?? $prompts['general'];

        $schema = [
            'sentiment' => ['score' => 'float', 'label' => 'string', 'reasoning' => 'string'],
            'summary' => ['summary' => 'string', 'key_points' => 'array'],
            'keywords' => ['keywords' => 'array', 'themes' => 'array'],
            'general' => ['insights' => 'array', 'themes' => 'array', 'recommendations' => 'array']
        ];

        return $this->generateJSON($prompt, $schema[$analysisType] ?? null);
    }

    /**
     * Generate executive summary from multiple inputs
     *
     * @param array $data Multiple data points to summarize
     * @param array $options Summary options
     * @return string Executive summary
     */
    public function generateExecutiveSummary($data, $options = [])
    {
        $dataJson = json_encode($data, JSON_PRETTY_PRINT);

        $prompt = "Generate a concise executive summary from the following data:\n\n{$dataJson}";

        if (isset($options['focus'])) {
            $prompt .= "\n\nFocus on: " . $options['focus'];
        }

        if (isset($options['max_length'])) {
            $prompt .= "\n\nMaximum length: {$options['max_length']} words";
        }

        $result = $this->complete($prompt, [
            'system' => 'You are an executive assistant who creates clear, concise summaries for business leaders.',
            'temperature' => 0.5
        ]);

        return $result['content'];
    }

    /**
     * Compare and contrast multiple items
     *
     * @param array $items Items to compare
     * @param array $criteria Comparison criteria
     * @return array Comparison results
     */
    public function compare($items, $criteria = [])
    {
        $itemsJson = json_encode($items, JSON_PRETTY_PRINT);
        $criteriaJson = json_encode($criteria, JSON_PRETTY_PRINT);

        $prompt = "Compare and contrast the following items:\n\n{$itemsJson}";

        if (!empty($criteria)) {
            $prompt .= "\n\nUse these comparison criteria:\n{$criteriaJson}";
        }

        $schema = [
            'comparison' => 'string',
            'similarities' => 'array',
            'differences' => 'array',
            'rankings' => 'array',
            'recommendations' => 'array'
        ];

        return $this->generateJSON($prompt, $schema);
    }

    /**
     * Generate actionable recommendations
     *
     * @param array $context Context data
     * @param string $goal Goal or objective
     * @return array Recommendations
     */
    public function generateRecommendations($context, $goal = '')
    {
        $contextJson = json_encode($context, JSON_PRETTY_PRINT);

        $prompt = "Based on this context:\n\n{$contextJson}";

        if ($goal) {
            $prompt .= "\n\nGoal: {$goal}";
        }

        $prompt .= "\n\nProvide specific, actionable recommendations with priority levels (high/medium/low) and expected impact.";

        $schema = [
            'recommendations' => [
                [
                    'title' => 'string',
                    'description' => 'string',
                    'priority' => 'string',
                    'expected_impact' => 'string',
                    'effort_required' => 'string',
                    'steps' => 'array'
                ]
            ]
        ];

        return $this->generateJSON($prompt, $schema);
    }

    /**
     * Extract structured data from unstructured text
     *
     * @param string $text Unstructured text
     * @param array $fields Fields to extract
     * @return array Extracted data
     */
    public function extractData($text, $fields = [])
    {
        $fieldsJson = json_encode($fields, JSON_PRETTY_PRINT);

        $prompt = "Extract the following information from this text:\n\n{$text}\n\nFields to extract:\n{$fieldsJson}";

        return $this->generateJSON($prompt, array_fill_keys($fields, 'string'));
    }

    /**
     * Calculate estimated cost for a request
     *
     * @param int $inputTokens Input tokens
     * @param int $outputTokens Output tokens
     * @param string $model Model name
     * @return float Cost in USD
     */
    public function estimateCost($inputTokens, $outputTokens, $model = null)
    {
        $model = $model ?? $this->model;

        // Pricing as of 2024 (per 1K tokens)
        $pricing = [
            'gpt-4-turbo-preview' => ['input' => 0.01, 'output' => 0.03],
            'gpt-4' => ['input' => 0.03, 'output' => 0.06],
            'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
        ];

        $prices = $pricing[$model] ?? $pricing['gpt-4-turbo-preview'];

        $inputCost = ($inputTokens / 1000) * $prices['input'];
        $outputCost = ($outputTokens / 1000) * $prices['output'];

        return round($inputCost + $outputCost, 4);
    }

    /**
     * Make API request to OpenAI
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
     */
    private function makeRequest($endpoint, $data)
    {
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("OpenAI API request failed: {$error}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMsg = $decoded['error']['message'] ?? 'Unknown error';
            throw new Exception("OpenAI API error ({$httpCode}): {$errorMsg}");
        }

        return $decoded;
    }
}
