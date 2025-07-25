<?php

declare(strict_types=1);

/**
 * Card Tokenization Endpoint
 *
 * This script handles server-side card tokenization for secure storage
 * and future verification. Useful for subscription setups and recurring
 * verification scenarios.
 *
 * PHP version 8.0 or higher
 *
 * @category  Tokenization
 * @package   GlobalPayments_Examples
 * @author    Global Payments
 * @license   MIT License
 * @link      https://github.com/globalpayments
 */

require_once 'vendor/autoload.php';

use Dotenv\Dotenv;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\Customer;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig;
use GlobalPayments\Api\ServicesContainer;

// Set headers
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
 * Configure the SDK
 */
function configureSdk(): void
{
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $config = new PorticoConfig();
    $config->secretApiKey = $_ENV['SECRET_API_KEY'];
    $config->developerId = $_ENV['DEVELOPER_ID'] ?? '000000';
    $config->versionNumber = $_ENV['VERSION_NUMBER'] ?? '0000';
    $config->serviceUrl = $_ENV['SERVICE_URL'] ?? 'https://cert.api2.heartlandportico.com';
    
    ServicesContainer::configureService($config);
}

/**
 * Validate tokenization request
 */
function validateTokenizeRequest(array $data): array
{
    $errors = [];

    if (empty($data['card_number'])) {
        $errors[] = 'Card number is required';
    }

    if (empty($data['exp_month']) || !is_numeric($data['exp_month']) || 
        $data['exp_month'] < 1 || $data['exp_month'] > 12) {
        $errors[] = 'Valid expiration month is required (1-12)';
    }

    if (empty($data['exp_year']) || !is_numeric($data['exp_year'])) {
        $errors[] = 'Valid expiration year is required';
    }

    // Optional CVV validation
    if (!empty($data['cvv']) && !preg_match('/^\d{3,4}$/', $data['cvv'])) {
        $errors[] = 'CVV must be 3 or 4 digits';
    }

    return $errors;
}

/**
 * Tokenize card with optional verification
 */
function tokenizeCard(array $cardData, bool $verifyCard = false): array
{
    $card = new CreditCardData();
    $card->number = preg_replace('/\s+/', '', $cardData['card_number']);
    $card->expMonth = (int)$cardData['exp_month'];
    $card->expYear = (int)$cardData['exp_year'];
    
    if (!empty($cardData['cvv'])) {
        $card->cvn = $cardData['cvv'];
    }
    
    if (!empty($cardData['cardholder_name'])) {
        $card->cardHolderName = substr(trim($cardData['cardholder_name']), 0, 50);
    }

    // Create address if provided
    $address = null;
    if (!empty($cardData['billing_address'])) {
        $address = new Address();
        $addressData = $cardData['billing_address'];
        
        if (!empty($addressData['street'])) {
            $address->streetAddress1 = substr(trim($addressData['street']), 0, 50);
        }
        if (!empty($addressData['city'])) {
            $address->city = substr(trim($addressData['city']), 0, 30);
        }
        if (!empty($addressData['state'])) {
            $address->state = substr(trim($addressData['state']), 0, 20);
        }
        if (!empty($addressData['postal_code'])) {
            $address->postalCode = substr(preg_replace('/[^a-zA-Z0-9\-\s]/', '', $addressData['postal_code']), 0, 10);
        }
    }

    if ($verifyCard) {
        // Tokenize with verification
        $builder = $card->verify()
            ->withRequestMultiUseToken(true)
            ->withAllowDuplicates(true);
            
        if ($address) {
            $builder->withAddress($address);
        }
        
        $response = $builder->execute();
        
        return [
            'token' => $response->token,
            'transaction_id' => $response->transactionReference->transactionId,
            'verification_result' => [
                'response_code' => $response->responseCode,
                'response_message' => $response->responseMessage,
                'avs_response_code' => $response->avsResponseCode ?? null,
                'cvn_response_code' => $response->cvnResponseCode ?? null,
                'card_type' => $response->cardType ?? null
            ],
            'verified' => $response->responseCode === '00'
        ];
    } else {
        // Tokenize only (no verification)
        $response = $card->tokenize()->execute();
        
        return [
            'token' => $response->token,
            'verified' => false
        ];
    }
}

// Main processing
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed. Use POST.',
            'error' => ['code' => 'METHOD_NOT_ALLOWED']
        ]);
        exit;
    }

    configureSdk();

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new ApiException('Invalid JSON data');
    }

    // Validate request
    $errors = validateTokenizeRequest($data);
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors
        ]);
        exit;
    }

    // Check if verification is requested
    $verifyCard = !empty($data['verify_card']) && $data['verify_card'] === true;

    // Tokenize the card
    $tokenResult = tokenizeCard($data, $verifyCard);

    // Prepare response
    $response = [
        'success' => true,
        'message' => $verifyCard ? 'Card tokenized and verified' : 'Card tokenized successfully',
        'data' => [
            'token' => $tokenResult['token'],
            'tokenized_at' => date('c'),
            'verified' => $tokenResult['verified']
        ]
    ];

    // Add verification details if card was verified
    if ($verifyCard) {
        $response['data']['verification_result'] = $tokenResult['verification_result'];
        $response['data']['transaction_id'] = $tokenResult['transaction_id'];
        
        if (!$tokenResult['verified']) {
            http_response_code(422);
            $response['success'] = false;
            $response['message'] = 'Card tokenized but verification failed';
        }
    }

    echo json_encode($response);

} catch (ApiException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Tokenization failed',
        'error' => [
            'code' => 'API_ERROR',
            'details' => $e->getMessage()
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'details' => 'An unexpected error occurred'
        ]
    ]);

    error_log('Tokenization error: ' . $e->getMessage());
}