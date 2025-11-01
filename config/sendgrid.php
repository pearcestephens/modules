<?php
/**
 * SendGrid API Configuration
 *
 * Configure SendGrid email service credentials
 * IMPORTANT: Set SENDGRID_API_KEY environment variable
 */

// Load environment variables
require_once __DIR__ . '/env-loader.php';

// Get SendGrid API key from environment
$apiKey = env('SENDGRID_API_KEY');

// Ensure key is configured
if (!$apiKey) {
    throw new \Exception('SENDGRID_API_KEY not configured in .env file');
}

return [
    'api_key' => $apiKey,
    'from_email' => 'noreply@vapeshed.co.nz',
    'from_name' => 'The Vape Shed',
];
