# GPT Services Directory

This directory contains all OpenAI GPT-powered services for the Store Reports module.

## Services Overview

### 1. **GPTService.php** - Base Text Generation Service
Core GPT integration for text generation, analysis, and intelligent insights.

**Key Features:**
- Text completion and chat
- Structured JSON responses
- Text analysis (sentiment, summary, keywords)
- Executive summaries
- Comparison analysis
- Actionable recommendations
- Data extraction from unstructured text
- Cost estimation

**Example Usage:**
```php
$gpt = new GPTService(['openai_api_key' => $apiKey]);

// Generate text
$response = $gpt->complete("Explain quantum computing in simple terms");

// Analyze sentiment
$sentiment = $gpt->analyzeText($customerFeedback, 'sentiment');

// Generate recommendations
$recommendations = $gpt->generateRecommendations($storeData, 'Improve store performance');

// Extract structured data
$data = $gpt->extractData($unstructuredText, ['date', 'amount', 'category']);
```

**Models Supported:**
- `gpt-4-turbo-preview` (default) - Most capable, higher cost
- `gpt-4` - High capability, balanced cost
- `gpt-3.5-turbo` - Fast, economical

**Cost Estimation:**
- GPT-4 Turbo: $0.01/1K input tokens, $0.03/1K output tokens
- GPT-4: $0.03/1K input tokens, $0.06/1K output tokens
- GPT-3.5 Turbo: $0.0005/1K input tokens, $0.0015/1K output tokens

---

### 2. **VisionAnalysisService.php** - Image Analysis Service
GPT-4 Vision integration for analyzing store photos with AI-powered scoring and insights.

**Key Features:**
- Multi-dimensional image scoring (0-100):
  - Cleanliness
  - Organization
  - Safety
  - Compliance
  - Visual Appeal
- Object detection
- Issue identification
- Recommendations generation
- Automatic follow-up photo requests
- Batch processing
- Executive summary generation

**Example Usage:**
```php
$vision = new VisionAnalysisService(['openai_api_key' => $apiKey]);

// Analyze single image
$analysis = $vision->analyzeImage($imagePath, [
    'context' => 'Retail vape store bathroom area',
    'focus' => 'cleanliness and safety'
]);

// Analyze multiple images for a report
$reportAnalysis = $vision->analyzeReportImages($reportId);

// Generate executive summary
$summary = $vision->generateExecutiveSummary($reportId);
```

**Analysis Output:**
```json
{
    "scores": {
        "cleanliness": 85,
        "organization": 90,
        "safety": 95,
        "compliance": 88,
        "visual_appeal": 92
    },
    "overall_score": 90,
    "objects_detected": ["display cabinet", "products", "counter", "signage"],
    "issues": [
        {
            "severity": "warning",
            "category": "cleanliness",
            "description": "Minor dust on top shelf",
            "recommendation": "Weekly dusting schedule recommended"
        }
    ],
    "recommendations": [
        "Consider reorganizing product layout for better visual flow",
        "Add more lighting to highlight premium products"
    ],
    "follow_up_requests": [
        {
            "type": "close_up",
            "reason": "Need clearer view of age restriction signage",
            "priority": "medium"
        }
    ]
}
```

**Cost Estimation:**
- GPT-4 Vision: ~$0.01-0.03 per image
- Typical store report (10-20 images): $0.10-0.60
- Monthly cost (50 reports): $5-30

---

## Configuration

All services require OpenAI API key configuration:

**Method 1: Environment Variable**
```bash
export OPENAI_API_KEY=sk-...
```

**Method 2: Config Array**
```php
$service = new GPTService([
    'openai_api_key' => 'sk-...',
    'model' => 'gpt-4-turbo-preview',
    'max_tokens' => 4096,
    'temperature' => 0.7
]);
```

**Method 3: Database Config**
```sql
INSERT INTO system_config (key, value) VALUES
('openai_api_key', 'sk-...'),
('openai_model', 'gpt-4-turbo-preview');
```

---

## Error Handling

All services implement comprehensive error handling:

```php
try {
    $result = $gpt->complete("Your prompt");
} catch (Exception $e) {
    error_log("GPT Service Error: " . $e->getMessage());
    // Fallback logic here
}
```

**Common Errors:**
- `API key not configured` - Missing OpenAI API key
- `Rate limit exceeded` - Too many requests
- `Invalid model` - Unsupported model name
- `Token limit exceeded` - Input too large
- `Timeout` - Request took too long

---

## Best Practices

### 1. **API Key Security**
- Never commit API keys to version control
- Use environment variables or secure config
- Rotate keys periodically
- Monitor usage and set spending limits

### 2. **Cost Optimization**
- Use GPT-3.5 Turbo for simple tasks
- Cache results when appropriate
- Batch requests where possible
- Set appropriate max_tokens limits
- Monitor and log API usage

### 3. **Error Handling**
- Always use try-catch blocks
- Implement retry logic for transient failures
- Provide fallback mechanisms
- Log errors for debugging

### 4. **Prompt Engineering**
- Be specific and clear
- Provide context when needed
- Use examples for complex tasks
- Request structured output (JSON)
- Test prompts thoroughly

### 5. **Performance**
- Use async processing for batch operations
- Implement request queuing for large volumes
- Cache frequently requested results
- Monitor response times

---

## Testing

Test both services before deployment:

**Test GPTService:**
```php
$gpt = new GPTService(['openai_api_key' => $testKey]);
$result = $gpt->complete("Say 'Hello World' if you can hear me");
assert($result['content'] !== '');
```

**Test VisionAnalysisService:**
```php
$vision = new VisionAnalysisService(['openai_api_key' => $testKey]);
$analysis = $vision->analyzeImage('/path/to/test/image.jpg');
assert(isset($analysis['scores']));
assert($analysis['overall_score'] >= 0 && $analysis['overall_score'] <= 100);
```

---

## Support & Documentation

- OpenAI API Docs: https://platform.openai.com/docs
- GPT-4 Vision: https://platform.openai.com/docs/guides/vision
- Pricing: https://openai.com/pricing
- Rate Limits: https://platform.openai.com/docs/guides/rate-limits

---

## Future Enhancements

Planned additions to GPT services:

- [ ] **Speech-to-Text Service** - Transcribe store visit audio notes
- [ ] **Embeddings Service** - Semantic search across historical reports
- [ ] **Fine-tuning Service** - Custom models for domain-specific tasks
- [ ] **Moderation Service** - Content filtering for user-submitted data
- [ ] **Assistant API Integration** - Persistent conversational AI for store managers
- [ ] **Batch Processing API** - Cost-effective batch analysis (50% cheaper)

---

Last Updated: November 5, 2025
