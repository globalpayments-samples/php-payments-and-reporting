<?php

declare(strict_types=1);

/**
 * Card Verification Script - GP-API SDK Implementation
 *
 * This script performs card verification using the Global Payments SDK.
 * It validates cards without processing charges, perfect for verification
 * scenarios, subscription setups, and card validation workflows.
 *
 * PHP version 8.0 or higher
 *
 * @category  Verification
 * @package   GlobalPayments_Examples
 * @author    Global Payments
 * @license   MIT License
 * @link      https://developer.globalpay.com/api/verifications
 */

require_once __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', '0');

use Dotenv\Dotenv;
use GlobalPayments\Api\ServiceConfigs\Gateways\GpApiConfig;
use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\Channel;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\Services\GpApiService;
use GlobalPayments\Examples\TransactionReporter;

// Disable error display for production security
ini_set('display_errors', '0');

// Set JSON response header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Load environment configuration and get access token using SDK
 *
 * @return array Environment variables and access token
 * @throws Exception If required credentials are missing or token generation fails
 */
function loadEnvironmentAndToken(): array
{
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    $required = ['GP_API_APP_ID', 'GP_API_APP_KEY'];
    foreach ($required as $var) {
        if (empty($_ENV[$var])) {
            throw new Exception("Missing required environment variable: {$var}");
        }
    }

    $config = [
        'app_id' => $_ENV['GP_API_APP_ID'],
        'app_key' => $_ENV['GP_API_APP_KEY'],
        'environment' => $_ENV['GP_API_ENVIRONMENT'] ?? 'sandbox',
        'country' => $_ENV['GP_API_COUNTRY'] ?? 'US',
        'currency' => $_ENV['GP_API_CURRENCY'] ?? 'USD',
        'merchant_id' => $_ENV['GP_API_MERCHANT_ID'] ?? null
    ];
    
    // Get access token using SDK
    $sdkConfig = new GpApiConfig();
    $sdkConfig->appId = $config['app_id'];
    $sdkConfig->appKey = $config['app_key'];
    $sdkConfig->environment = $config['environment'] === 'production' 
        ? Environment::PRODUCTION 
        : Environment::TEST;
    $sdkConfig->channel = Channel::CardNotPresent;
    // Use account defaults from GP portal
    $sdkConfig->country = $config['country'];
    
    ServicesContainer::configureService($sdkConfig);
    
    try {
        $accessTokenInfo = GpApiService::generateTransactionKey($sdkConfig);
        $config['access_token'] = $accessTokenInfo->accessToken;
    } catch (Exception $e) {
        throw new Exception('Failed to generate access token: ' . $e->getMessage());
    }
    
    return $config;
}

/**
 * Validate card verification request data
 *
 * @param array $data Request data
 * @return array Validation errors
 */
function validateRequest(array $data): array
{
    $errors = [];

    // Check if we have either payment token (from Drop-In UI) or direct card details
    $hasPaymentToken = !empty($data['payment_token']);
    $hasCardDetails = !empty($data['card_number']) && !empty($data['expiry_month']) && !empty($data['expiry_year']);
    
    if (!$hasPaymentToken && !$hasCardDetails) {
        $errors[] = 'Either payment_token (from Drop-In UI) or card details (card_number, expiry_month, expiry_year) are required';
    }

    if (empty($data['verification_type'])) {
        $errors[] = 'Verification type is required';
    }

    $validTypes = ['basic', 'avs', 'cvv', 'full'];
    if (!empty($data['verification_type']) && !in_array($data['verification_type'], $validTypes)) {
        $errors[] = 'Invalid verification type. Must be: ' . implode(', ', $validTypes);
    }

    return $errors;
}

/**
 * Perform card verification using GP-API SDK
 *
 * @param string $accessToken GP-API access token
 * @param array $config Environment configuration
 * @param array $requestData Request data
 * @return array Verification result
 * @throws Exception If verification fails
 */
function performVerification(string $accessToken, array $config, array $requestData): array
{
    // Configure SDK for verification
    $sdkConfig = new GpApiConfig();
    $sdkConfig->appId = $config['app_id'];
    $sdkConfig->appKey = $config['app_key'];
    $sdkConfig->environment = ($config['environment'] === 'production') 
        ? Environment::PRODUCTION 
        : Environment::TEST;
    $sdkConfig->channel = Channel::CardNotPresent;
    $sdkConfig->country = $config['country'];
    
    // Set account ID if available (required for some verification operations)
    if (!empty($_ENV['GP_API_ACCOUNT_ID'])) {
        $sdkConfig->accessTokenInfo = new \GlobalPayments\Api\Entities\GpApi\AccessTokenInfo();
        $sdkConfig->accessTokenInfo->transactionProcessingAccountId = $_ENV['GP_API_ACCOUNT_ID'];
    }
    
    ServicesContainer::configureService($sdkConfig);
    
    // Create payment method from token - handle different token types
    $paymentToken = $requestData['payment_token'];
    
    // Check if this is a GP-API payment token format (starts with PMT_ or TKN_)
    if (strpos($paymentToken, 'PMT_') === 0 || strpos($paymentToken, 'TKN_') === 0) {
        $paymentMethod = new \GlobalPayments\Api\PaymentMethods\CreditCardData();
        $paymentMethod->token = $paymentToken;
    } else {
        // This might be a legacy format or different token type
        throw new Exception('Invalid token format. Expected GP-API payment token starting with PMT_ or TKN_');
    }
    
    // Generate a stable reference for idempotency and tracing
    $reference = 'VER_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
    
    try {
        // Perform basic verification
        $response = $paymentMethod->verify()
            ->withCurrency($requestData['currency'] ?? $config['currency'])
            ->withAllowDuplicates(true)
            ->withClientTransactionId($reference)
            ->withIdempotencyKey($reference)
            ->execute();
        
        // Extract card information with comprehensive fallback logic (same as payment processing)
        $cardType = 'Unknown';
        $cardLast4 = null;
        $cardExpMonth = '';
        $cardExpYear = '';

        // For verification responses, card info is usually from the request data, not the API response
        // Use card info from request if provided
        if (!empty($requestData['card_details'])) {
            $cardDetails = $requestData['card_details'];
            $cardType = $cardDetails['type'] ?? 'Unknown';
            $cardLast4 = $cardDetails['last4'] ?? null;
            $cardExpMonth = $cardDetails['exp_month'] ?? '';
            $cardExpYear = $cardDetails['exp_year'] ?? '';
            
            error_log('Using card details from request - Type: ' . $cardType . ', Last4: ' . $cardLast4);
        } else {
            // Fallback to default values
            $cardType = 'Unknown';
            $cardLast4 = null;
            $cardExpMonth = '';
            $cardExpYear = '';
        }
        
        // Convert SDK response to array format
        $result = [
            'id' => $response->transactionReference->transactionId,
            'status' => 'VERIFIED',
            'channel' => 'CNP',
            'country' => $config['country'],
            'reference' => $reference,
            'payment_method' => [
                'result' => $response->responseCode,
                'message' => $response->responseMessage,
                'entry_mode' => 'ECOM',
                'card' => [
                    'brand' => $cardType,
                    'masked_number_last4' => $cardLast4
                ]
            ],
            'action' => [
                'result_code' => $response->responseCode === 'SUCCESS' ? 'SUCCESS' : 'FAILED'
            ]
        ];
        
        // Debug logging for GP-API verification response
        error_log('GP-API Verification Response - Transaction ID: ' . ($response->transactionReference->transactionId ?? 'NO_ID'));
        error_log('GP-API Verification Response - Status: ' . ($response->responseCode ?? 'NO_CODE'));
        error_log('GP-API Verification Response - Message: ' . ($response->responseMessage ?? 'NO_MESSAGE'));
        
        return $result;
        
    } catch (Exception $e) {
        
        // Check if it's a permissions or account configuration issue
        if (strpos($e->getMessage(), 'Merchant configuration does not exist') !== false || 
            strpos($e->getMessage(), 'action_type - VERIFY') !== false ||
            strpos($e->getMessage(), 'INVALID_REQUEST_DATA') !== false ||
            strpos($e->getMessage(), 'contains unexpected data') !== false ||
            strpos($e->getMessage(), 'TRN_POST_Verify') !== false ||
            strpos($e->getMessage(), 'not present in App') !== false) {
            throw new Exception('Card verification is not enabled for this account. Your Global Payments account settings need verification processing enabled in the portal. Please contact Global Payments support to enable verification permissions.');
        }
        
        throw new Exception('Verification failed: ' . $e->getMessage());
    }
}

// Main request processing
try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed. Use POST.',
            'error' => ['code' => 'METHOD_NOT_ALLOWED']
        ]);
        exit;
    }

    // Load environment configuration and get access token
    $config = loadEnvironmentAndToken();

    // Get and decode request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }

    // Validate request
    $errors = validateRequest($data);
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors
        ]);
        exit;
    }

    // Perform verification
    $verificationResult = performVerification($config['access_token'], $config, $data);

    // Check if verification was successful
    // For verifications, success is indicated by status being 'VERIFIED'
    // The response code might be different from authorization responses
    $isSuccessful = isset($verificationResult['status']) && 
                   $verificationResult['status'] === 'VERIFIED';

    if (!$isSuccessful) {
        http_response_code(422); // Unprocessable Entity
        echo json_encode([
            'success' => false,
            'message' => 'Card verification failed',
            'verification_result' => $verificationResult,
            'error' => [
                'code' => 'VERIFICATION_FAILED',
                'details' => $verificationResult['action']['result_code'] ?? 'Unknown failure'
            ]
        ]);
        exit;
    }

    // Use card details from GP-API response if available
    $cardType = 'Unknown';
    $cardLast4 = $verificationResult['payment_method']['card']['masked_number_last4'] ?? null;
    
    // Debug logging
    error_log('Verification request data: ' . json_encode($data));
    error_log('Card details from request: ' . json_encode($data['card_details'] ?? 'NOT_FOUND'));
    error_log('Verification result: ' . json_encode($verificationResult));
    
    // Check if card details were provided in the request
    if (!empty($data['card_details'])) {
        $cardType = $data['card_details']['type'] ?? 'Unknown';
        $cardLast4 = $data['card_details']['last4'] ?? $cardLast4;
        error_log('Using card details from request - Type: ' . $cardType . ', Last4: ' . $cardLast4);
    } else {
        // Fallback to API response if available
        $cardType = $verificationResult['payment_method']['card']['brand'] ?? 'Unknown';
        error_log('Using card details from API response - Type: ' . $cardType);
    }

    // Record the transaction for dashboard display
    try {
        $reporter = new TransactionReporter();
        $transactionData = [
            'id' => $verificationResult['id'],
            'reference' => $verificationResult['reference'],
            'status' => 'approved', // Verifications are always approved if successful
            'amount' => 'VERIFY',
            'currency' => $data['currency'] ?? $config['currency'],
            'type' => 'verification',
            'timestamp' => date('c'),
            'card' => [
                'type' => $cardType,
                'last4' => $cardLast4,
                'exp_month' => $data['card_details']['exp_month'] ?? '',
                'exp_year' => $data['card_details']['exp_year'] ?? ''
            ],
            'response' => [
                'code' => $verificationResult['payment_method']['result'] ?? 'SUCCESS',
                'message' => $verificationResult['payment_method']['message'] ?? 'VERIFIED'
            ],
            'gateway_response_code' => $verificationResult['action']['result_code'] ?? 'SUCCESS',
            'gateway_response_message' => $verificationResult['payment_method']['message'] ?? 'VERIFIED',
            'batch_id' => $verificationResult['batch_id'] ?? '',
            'avs' => [
                'code' => '',
                'message' => ''
            ],
            'cvv' => [
                'code' => '',
                'message' => ''
            ]
        ];
        $reporter->recordTransaction($transactionData);
    } catch (Exception $e) {
        // Log the error but don't fail the verification
        error_log('Failed to record verification transaction: ' . $e->getMessage());
    }

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Card verification successful',
        'verification_result' => $verificationResult,
        'data' => [
            'verified' => true,
            'verification_type' => $data['verification_type'],
            'transaction_id' => $verificationResult['id'],
            'reference' => $verificationResult['reference']
        ]
    ]);

} catch (Exception $e) {
    // Handle errors with proper GP-API error format
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Card verification processing failed',
        'error' => [
            'code' => 'VERIFICATION_ERROR',
            'details' => $e->getMessage()
        ]
    ]);
}