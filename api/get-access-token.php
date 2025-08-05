<?php

declare(strict_types=1);

/**
 * GP-API Access Token Generation
 *
 * Creates an access token for GP-API JavaScript SDK
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use GlobalPayments\Api\ServiceConfigs\Gateways\GpApiConfig;
use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\Channel;
use GlobalPayments\Api\ServicesContainer;

// Set security headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed. Use POST.'
        ]);
        exit;
    }

    // Load environment variables
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    // Validate required environment variables
    $appId = $_ENV['GP_API_APP_ID'] ?? null;
    $appKey = $_ENV['GP_API_APP_KEY'] ?? null;
    $environment = $_ENV['GP_API_ENVIRONMENT'] ?? 'sandbox';
    
    if (empty($appId) || empty($appKey)) {
        throw new Exception('GP-API credentials not configured');
    }

    // Configure GP-API to get access token
    $config = new GpApiConfig();
    $config->appId = $appId;
    $config->appKey = $appKey;
    $config->environment = $environment === 'production' 
        ? Environment::PRODUCTION 
        : Environment::TEST;
    $config->channel = Channel::CardNotPresent;

    ServicesContainer::configureService($config);

    // Generate GP-API access token following official documentation
    
    // 1. Create a nonce (timestamp)
    $nonce = date('c'); // ISO 8601 format: 2025-01-05T14:30:00+00:00
    
    // 2. Calculate secret key (SHA512 hash of nonce + app_key)
    $secret = hash('sha512', $nonce . $appKey);
    
    // 3. Prepare endpoint URL
    $tokenUrl = $environment === 'production' 
        ? 'https://apis.globalpay.com/ucp/accesstoken'
        : 'https://apis.sandbox.globalpay.com/ucp/accesstoken';
    
    // 4. Prepare payload
    $payload = [
        'app_id' => $appId,
        'nonce' => $nonce,
        'secret' => $secret,
        'grant_type' => 'client_credentials'
    ];
    
    // 5. Make the API call
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $tokenUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-GP-Version: 2021-03-22',
            'Accept-Encoding: gzip, deflate'
        ],
        CURLOPT_ENCODING => '', // Enable automatic decompression
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception('CURL error: ' . $curlError);
    }
    
    if ($httpCode !== 200) {
        throw new Exception('GP-API authentication failed (HTTP ' . $httpCode . '): ' . $response);
    }
    
    $tokenResponse = json_decode($response, true);
    if (!$tokenResponse || !isset($tokenResponse['token'])) {
        throw new Exception('Invalid token response from GP-API');
    }
    
    $accessToken = $tokenResponse['token'];

    // Return the access token
    echo json_encode([
        'success' => true,
        'data' => [
            'accessToken' => $accessToken,
            'environment' => $environment
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Access token generation failed',
        'error' => [
            'code' => 'TOKEN_ERROR',
            'details' => $e->getMessage()
        ]
    ]);

    error_log('GP-API access token error: ' . $e->getMessage());
}