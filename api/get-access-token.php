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
use GlobalPayments\Api\Services\GpApiService;

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

    // Load environment variables from .env file
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    $config = new GpApiConfig();
    $config->appId = $_ENV['GP_API_APP_ID'];
    $config->appKey = $_ENV['GP_API_APP_KEY'];
    $config->environment = ($_ENV['GP_API_ENVIRONMENT'] === 'production') 
        ? Environment::PRODUCTION 
        : Environment::TEST;
    $config->channel = Channel::CardNotPresent;
    // Set permissions for tokenization and potential verification
    $config->permissions = ['PMT_POST_Create_Single'];
    
    ServicesContainer::configureService($config);
    
    // Generate access token for frontend tokenization
    $accessTokenInfo = GpApiService::generateTransactionKey($config);
    
    // Set response content type to JSON
    header('Content-Type: application/json');
    
    // Return public API key in JSON response
    $rawEnv = $_ENV['GP_API_ENVIRONMENT'] ?? 'sandbox';
    $jsEnv = ($rawEnv === 'test') ? 'sandbox' : $rawEnv; // Map 'test' to JS 'sandbox'
    echo json_encode([
        'success' => true,
        'data' => [
            'accessToken' => $accessTokenInfo->accessToken,
            'environment' => $jsEnv
        ],
    ]);

} catch (Exception $e) {
    // Handle configuration errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error loading configuration: ' . $e->getMessage()
    ]);
}