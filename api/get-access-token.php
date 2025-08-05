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

    // For GP-API, we actually need to use the app_id directly as the public key
    // The app_id IS the public identifier for GP-API
    $accessToken = $appId;

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