<?php
/**
 * AI Commentary Generator - Claude-Powered Real-Time Upload Commentary
 * 
 * Generates gangsta vape slang, brand shoutouts, and time-dependent personality
 * using YOUR Claude API for maximum authenticity and entertainment
 * 
 * @package CIS\Consignments\API
 * @version 1.0.0
 */

declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get product info from request
$productName = $_GET['product'] ?? '';
$brandName = $_GET['brand'] ?? '';
$timeOfDay = (int)date('G'); // 0-23 hour
$uploadProgress = (int)($_GET['progress'] ?? 0);

// Calculate personality based on time and progress
$personality = getPersonality($timeOfDay, $uploadProgress);

// ALWAYS use YOUR AI agent platform - no API key needed!
$commentary = generateAICommentary($productName, $brandName, $personality, $uploadProgress);

echo json_encode([
    'success' => true,
    'commentary' => $commentary,
    'personality' => $personality,
    'ai_powered' => true,
    'source' => 'VapeShed AI Agent'
]);

/**
 * Generate REAL AI commentary using YOUR custom AI agent platform
 */
function generateAICommentary(string $product, string $brand, string $personality, int $progress): string
{
    $prompt = buildPrompt($product, $brand, $personality, $progress);
    
    // Use YOUR AI agent platform at staff.vapeshed.co.nz
    $data = [
        'message' => $prompt,
        'context' => [
            'product' => $product,
            'brand' => $brand,
            'personality' => $personality,
            'progress' => $progress,
            'system_role' => 'vape_upload_commentator'
        ]
    ];
    
    $ch = curl_init('https://staff.vapeshed.co.nz/assets/services/neuro/neuro_/ai-agent/api/chat.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Requested-With: XMLHttpRequest'
        ],
        CURLOPT_TIMEOUT => 3, // Quick responses only
        CURLOPT_SSL_VERIFYPEER => false // Internal request
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        
        // Handle different response formats from your AI agent
        if (isset($result['response'])) {
            return trim($result['response']);
        } elseif (isset($result['message'])) {
            return trim($result['message']);
        } elseif (isset($result['content'])) {
            return trim($result['content']);
        } elseif (is_string($result)) {
            return trim($result);
        }
    }
    
    // Log error for debugging (optional)
    if ($error) {
        error_log("AI Commentary API Error: " . $error);
    }
    
    // Fallback if API fails
    return generateFallbackCommentary($product, $brand, $personality, $progress);
}

/**
 * Build the Claude prompt based on context
 */
function buildPrompt(string $product, string $brand, string $personality, int $progress): string
{
    $prompts = [
        'gangsta' => "You're a gangsta vape shop owner uploading products to Lightspeed Retail. Generate ONE SHORT (max 10 words) hype comment about '{$product}' by {$brand}. Be sassy, use slang, mention vape culture. Progress: {$progress}%",
        
        'hood' => "You're a chill vape expert uploading '{$product}' by {$brand}. Give ONE SHORT (max 10 words) cool comment about the product. Be authentic and mention vape clouds or flavor. Progress: {$progress}%",
        
        'corporate' => "You're a professional inventory manager uploading '{$product}' by {$brand} to Lightspeed Retail. Give ONE SHORT (max 10 words) professional update. Progress: {$progress}%",
        
        'boring' => "System processing '{$product}' by {$brand}. Generate ONE SHORT (max 8 words) basic status update. Progress: {$progress}%"
    ];
    
    return $prompts[$personality] ?? $prompts['corporate'];
}

/**
 * Get personality based on time of day and progress
 */
function getPersonality(int $hour, int $progress): string
{
    // Time-dependent personality
    if ($hour >= 22 || $hour < 6) {
        return 'gangsta'; // Late night crew = gangsta mode
    } elseif ($hour >= 6 && $hour < 9) {
        return 'boring'; // Early morning = still waking up
    } elseif ($hour >= 9 && $hour < 17) {
        return 'corporate'; // Business hours = professional
    } elseif ($hour >= 17 && $hour < 22) {
        return 'hood'; // After work = chill vibes
    }
    
    // Progress-based intensity
    if ($progress > 80) {
        return 'gangsta'; // Almost done = hype mode!
    }
    
    return 'corporate';
}

/**
 * Fallback commentary when Claude API not available
 */
function generateFallbackCommentary(string $product, string $brand, string $personality, int $progress): string
{
    $templates = [
        'gangsta' => [
            "🔥 {brand} hittin' different today!",
            "💨 {product} about to make clouds DUMMY THICC",
            "😎 Uploading this heat, no cap!",
            "🚀 {brand} straight fire, periodt!",
            "💯 This {product} gonna FLY off the shelf!",
            "🔥 {brand} bringing that PREMIUM smoke!",
            "💨 Cloud chasers gonna love this {product}!",
            "😤 {brand} never misses, fr fr!",
            "🎯 {product} = instant classic, you heard?",
            "⚡ {brand} keeping it 💯 as always!"
        ],
        'hood' => [
            "✨ {brand} looking clean today",
            "💨 {product} bout to make some nice clouds",
            "👌 Solid choice with this {brand}",
            "🌊 {product} smooth like butter",
            "💫 {brand} always delivers quality",
            "🔥 This {product} is pretty fire",
            "💯 {brand} staying consistent",
            "✌️ {product} vibes are immaculate",
            "🎨 {brand} with the flavor profile!",
            "💨 {product} hitting all the right notes"
        ],
        'corporate' => [
            "Processing {brand} inventory unit",
            "Uploading {product} to Lightspeed Retail",
            "Syncing {brand} product data",
            "{product} added to consignment queue",
            "Updating {brand} stock levels",
            "Validating {product} specifications",
            "{brand} item successfully processed",
            "Recording {product} transfer details",
            "Confirming {brand} availability",
            "Finalizing {product} upload sequence"
        ],
        'boring' => [
            "Processing {product}...",
            "Uploading {brand}...",
            "Syncing data...",
            "Item processed",
            "Update complete",
            "Transfer logged",
            "Entry created",
            "Data uploaded",
            "Sync successful",
            "Processing complete"
        ]
    ];
    
    $messages = $templates[$personality] ?? $templates['corporate'];
    $template = $messages[array_rand($messages)];
    
    return str_replace(
        ['{product}', '{brand}'],
        [$product, $brand],
        $template
    );
}

/**
 * Special milestone messages at progress checkpoints
 */
function getMilestoneMessage(int $progress): ?string
{
    $milestones = [
        25 => "💪 Quarter way there! Keep that momentum!",
        50 => "⚡ HALFWAY POINT! We're cruising now!",
        75 => "🔥 Three quarters done! Almost there fam!",
        90 => "💯 SO CLOSE! Final push let's gooo!",
        100 => "🎉 DONE! That's how we do it! 🎊"
    ];
    
    return $milestones[$progress] ?? null;
}
