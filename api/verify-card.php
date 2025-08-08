<?php

declare(strict_types=1);

/**
 * Card Verification Script
 *
 * This script demonstrates card verification using the Global Payments SDK.
 * It validates cards without processing charges, perfect for verification
 * scenarios, subscription setups, and card validation workflows.
 *
 * PHP version 8.0 or higher
 *
 * @category  Verification
 * @package   GlobalPayments_Examples
 * @author    Global Payments
 * @license   MIT License
 * @link      https://github.com/globalpayments
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\Customer;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\ServiceConfigs\Gateways\GpApiConfig;
use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\Channel;
use GlobalPayments\Api\ServicesContainer;

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
 * Configure the Global Payments SDK
 *
 * @return void
 * @throws Exception If environment configuration fails
 */
function configureSdk(): void
{
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    if (empty($_ENV['GP_API_APP_ID']) || empty($_ENV['GP_API_APP_KEY'])) {
        throw new Exception('GP-API credentials not configured in environment');
    }

    $config = new GpApiConfig();
    $config->appId = $_ENV['GP_API_APP_ID'];
    $config->appKey = $_ENV['GP_API_APP_KEY'];
    $config->environment = $_ENV['GP_API_ENVIRONMENT'] === 'production' 
        ? Environment::PRODUCTION 
        : Environment::TEST;
    $config->channel = Channel::CardNotPresent;
    // Request transaction permission so SDK populates transactionProcessingAccount in token
    $config->permissions = ['TRN_POST_Authorize', 'PMT_POST_Create_Single'];
    $config->country = $_ENV['GP_API_COUNTRY'] ?? 'US';
    
    // Optional: if a GP-API merchant ID is configured, set it; otherwise omit
    if (!empty($_ENV['GP_API_MERCHANT_ID'])) {
        $config->merchantId = $_ENV['GP_API_MERCHANT_ID'];
    }
    
    ServicesContainer::configureService($config);

    // Preflight: ensure token has transaction processing account scope
    try {
        $access = \GlobalPayments\Api\Services\GpApiService::generateTransactionKey($config);
        if (empty($access->transactionProcessingAccountID) || empty($access->transactionProcessingAccountName)) {
            throw new Exception('GP-API access token lacks transaction processing account scope. Check TRN_POST_Authorize permission and merchant/account assignment.');
        }
    } catch (Exception $e) {
        throw new Exception('GP-API token preflight failed: ' . $e->getMessage());
    }
}

/**
 * Sanitize and validate postal code
 *
 * @param string|null $postalCode The postal code to sanitize
 * @return string Sanitized postal code
 */
function sanitizePostalCode(?string $postalCode): string
{
    if ($postalCode === null) {
        return '';
    }
    
    // Remove non-alphanumeric characters except hyphens and spaces
    $sanitized = preg_replace('/[^a-zA-Z0-9\-\s]/', '', $postalCode);
    
    // Limit to reasonable length
    return substr(trim($sanitized), 0, 10);
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

    if (empty($data['payment_token'])) {
        $errors[] = 'Payment token is required';
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
 * Create address object for AVS verification
 *
 * @param array $data Request data
 * @return Address|null
 */
function createAddress(array $data): ?Address
{
    if (empty($data['billing_address'])) {
        return null;
    }

    $addressData = $data['billing_address'];
    $address = new Address();
    
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
        $address->postalCode = sanitizePostalCode($addressData['postal_code']);
    }
    
    if (!empty($addressData['country'])) {
        $address->country = substr(trim($addressData['country']), 0, 2);
    }

    return $address;
}

/**
 * Create customer object for enhanced verification
 *
 * @param array $data Request data
 * @return Customer|null
 */
function createCustomer(array $data): ?Customer
{
    if (empty($data['customer'])) {
        return null;
    }

    $customerData = $data['customer'];
    $customer = new Customer();
    
    if (!empty($customerData['id'])) {
        $customer->id = substr(trim($customerData['id']), 0, 50);
    }
    
    if (!empty($customerData['email'])) {
        $customer->email = substr(trim($customerData['email']), 0, 100);
    }
    
    if (!empty($customerData['phone'])) {
        $customer->homePhone = preg_replace('/[^0-9]/', '', $customerData['phone']);
    }

    return $customer;
}

/**
 * Perform card verification based on type
 *
 * @param CreditCardData $card The credit card to verify
 * @param string $verificationType Type of verification
 * @param Address|null $address Billing address for AVS
 * @param Customer|null $customer Customer data
 * @return array Verification result
 */
function performVerification(
    CreditCardData $card, 
    string $verificationType, 
    string $currency = 'USD',
    ?Address $address = null,
    ?Customer $customer = null
): array {
    // Generate a stable reference for idempotency and tracing
    $clientReference = 'VER_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
    switch ($verificationType) {
        case 'basic':
            // Basic card verification without additional checks
            $builder = $card->verify()
                ->withCurrency($currency)
                ->withAllowDuplicates(true)
                ->withClientTransactionId($clientReference)
                ->withIdempotencyKey($clientReference);
                
            
            $response = $builder->execute();
            break;

        case 'avs':
            // Address Verification Service check
            $response = $card->verify()
                ->withCurrency($currency)
                ->withAllowDuplicates(true)
                ->withClientTransactionId($clientReference)
                ->withIdempotencyKey($clientReference)
                ->withAddress($address)
                ->execute();
            break;

        case 'cvv':
            // CVV verification (CVV already included in token)
            $response = $card->verify()
                ->withCurrency($currency)
                ->withAllowDuplicates(true)
                ->withClientTransactionId($clientReference)
                ->withIdempotencyKey($clientReference)
                ->execute();
            break;

        case 'full':
            // Full verification with all available checks
            $builder = $card->verify()
                ->withCurrency($currency)
                ->withAllowDuplicates(true)
                ->withClientTransactionId($clientReference)
                ->withIdempotencyKey($clientReference);
                
            if ($address) {
                $builder->withAddress($address);
            }
            
            if ($customer) {
                $builder->withCustomerData($customer);
            }
            
            $response = $builder->execute();
            break;

        default:
            throw new ApiException('Invalid verification type');
    }

    // Avoid deprecated fields: derive brand/last4 strictly from mapped payment method
    $cardBrand = null;
    $cardLast4 = null;
    if (isset($response->paymentMethod) && isset($response->paymentMethod->card)) {
        $cardBrand = $response->paymentMethod->card->brand ?? null;
        $cardLast4 = $response->paymentMethod->card->maskedNumberLast4 ?? null;
    }

    return [
        'transaction_id' => $response->transactionReference->transactionId,
        'response_code' => $response->responseCode,
        'response_message' => $response->responseMessage,
        'avs_response_code' => $response->avsResponseCode ?? null,
        'avs_response_message' => $response->avsResponseMessage ?? null,
        'cvn_response_code' => $response->cvnResponseCode ?? null,
        'cvn_response_message' => $response->cvnResponseMessage ?? null,
        'commercial_indicator' => $response->commercialIndicator ?? null,
        'card_brand' => $cardBrand,
        'card_last4' => $cardLast4
    ];
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

    // Initialize SDK
    configureSdk();

    // Get and decode request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new ApiException('Invalid JSON data');
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

    // Initialize payment method with token from Drop-In UI
    $card = new CreditCardData();
    $card->token = $data['payment_token'];

    // Create address and customer objects if provided
    $address = createAddress($data);
    $customer = createCustomer($data);

    // Perform verification
    $verificationResult = performVerification(
        $card, 
        $data['verification_type'], 
        $data['currency'] ?? ($_ENV['GP_API_CURRENCY'] ?? 'USD'),
        $address, 
        $customer
    );

    // Check if verification was successful
    $isSuccessful = $verificationResult['response_code'] === '00';

    if (!$isSuccessful) {
        http_response_code(422); // Unprocessable Entity
        echo json_encode([
            'success' => false,
            'message' => 'Card verification failed',
            'verification_result' => $verificationResult,
            'error' => [
                'code' => 'VERIFICATION_FAILED',
                'details' => $verificationResult['response_message']
            ]
        ]);
        exit;
    }

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Card verification successful',
        'verification_result' => $verificationResult,
        'data' => [
            'verified' => true,
            'verification_type' => $data['verification_type'],
            'transaction_id' => $verificationResult['transaction_id']
        ]
    ]);

} catch (ApiException $e) {
    // Handle API-specific errors with clearer detail
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Card verification processing failed',
        'error' => [
            'code' => 'API_ERROR',
            'details' => $e->getMessage()
        ]
    ]);

} catch (Exception $e) {
    // Handle unexpected errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'details' => 'An unexpected error occurred'
        ]
    ]);

    // Log error for debugging (not exposed to client)
    error_log('Card verification error: ' . $e->getMessage());
}