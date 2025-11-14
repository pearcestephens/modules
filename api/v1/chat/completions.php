<?php
/**
 * BYOK Chat Completions Proxy - Enterprise Grade
 * OpenAI-compatible endpoint for GitHub Copilot
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../../mcp/lib/Bootstrap.php';

define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');
define('MAX_SIZE', 1048576);
define('TIMEOUT', 120);

function sendError($code, $type, $msg) {
    http_response_code($code);
    die(json_encode(['error' => ['type' => $type, 'message' => $msg, 'code' => $code]]));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendError(405, 'method_not_allowed', 'POST only');
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'json') === false) sendError(415, 'unsupported_media_type', 'JSON only');
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > MAX_SIZE) sendError(413, 'too_large', 'Request too large');

$apiKey = null;
if (isset($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/^Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
    $apiKey = $m[1];
}

$validKey = getenv('MCP_API_KEY') ?: '31ce0106609a6c5bc4f7ece0deb2f764df90a06167bda83468883516302a6a35';
if ($apiKey !== $validKey) sendError(401, 'unauthorized', 'Invalid API key');

$req = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) sendError(400, 'invalid_json', json_last_error_msg());
if (!isset($req['messages']) || !is_array($req['messages']) || empty($req['messages'])) {
    sendError(400, 'invalid_request', 'Invalid messages');
}

$proxy = [
    'model' => $req['model'] ?? 'gpt-4-turbo-preview',
    'messages' => $req['messages'],
    'temperature' => isset($req['temperature']) ? (float)$req['temperature'] : 0.7,
    'stream' => isset($req['stream']) ? (bool)$req['stream'] : false
];

if (isset($req['max_tokens'])) $proxy['max_tokens'] = (int)$req['max_tokens'];

$openaiKey = getenv('OPENAI_API_KEY') ?: $_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? null;
if (!$openaiKey) sendError(500, 'config_error', 'OpenAI key missing');

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => OPENAI_API_URL,
    CURLOPT_RETURNTRANSFER => !$proxy['stream'],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($proxy),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey
    ],
    CURLOPT_TIMEOUT => TIMEOUT,
    CURLOPT_SSL_VERIFYPEER => true
]);

if ($proxy['stream']) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($c, $d) {
        echo $d;
        if (ob_get_level() > 0) ob_flush();
        flush();
        return strlen($d);
    });
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$proxy['stream']) {
    http_response_code($httpCode);
    echo $response;
}
