<?php

declare(strict_types=1);

/**
 * Configuration Endpoint for Card Verification
 *
 * This script provides configuration information for the client-side SDK,
 * including the public API key needed for secure card tokenization.
 * This endpoint is safe to expose publicly as it only returns public keys.
 *
 * PHP version 8.0 or higher
 *
 * @category  Configuration
 * @package   GlobalPayments_Examples
 * @author    Global Payments
 * @license   MIT License
 * @link      https://github.com/globalpayments
 */

require_once 'vendor/autoload.php';

use Dotenv\Dotenv;

// Set security headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Only accept GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed. Use GET.',
            'error' => ['code' => 'METHOD_NOT_ALLOWED']
        ]);
        exit;
    }

    // Load environment variables from .env file
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    // Validate required environment variables
    $publicApiKey = $_ENV['PUBLIC_API_KEY'] ?? null;
    
    if (empty($publicApiKey)) {
        throw new Exception('PUBLIC_API_KEY not configured');
    }

    // Determine environment based on API key
    $environment = 'production';
    if (strpos($publicApiKey, '_cert_') !== false || strpos($publicApiKey, '_test_') !== false) {
        $environment = 'sandbox';
    }

    // Return configuration data
    echo json_encode([
        'success' => true,
        'data' => [
            'publicApiKey' => $publicApiKey,
            'environment' => $environment,
            'apiVersion' => 'v1',
            'supportedCardTypes' => [
                'visa',
                'mastercard', 
                'amex',
                'discover',
                'jcb',
                'diners'
            ],
            'features' => [
                'tokenization' => true,
                'avs_verification' => true,
                'cvv_verification' => true,
                'three_d_secure' => true,
                'stored_credentials' => true
            ],
            'verification_types' => [
                'basic' => 'Basic card validation',
                'avs' => 'Address Verification Service',
                'cvv' => 'Card Verification Value check',
                'full' => 'Complete verification with all checks'
            ]
        ],
        'timestamp' => date('c'),
        'version' => '1.0.0'
    ]);

} catch (Exception $e) {
    // Handle configuration errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Configuration error',
        'error' => [
            'code' => 'CONFIG_ERROR',
            'details' => 'Unable to load configuration'
        ]
    ]);

    // Log error for debugging (don't expose sensitive details)
    error_log('Configuration error: ' . $e->getMessage());
}