<?php
/**
 * Simplified Heartland Payment Processor
 * Fixed version with better error handling
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to user
ini_set('log_errors', 1);

// Set headers first
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed. Use POST.', 405);
    }

    // Load dependencies
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../src/Logger.php';

    // Load environment variables
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    // Get and validate input
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('Empty request body', 400);
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON in request body', 400);
    }

    // Validate required fields
    $tokenValue = $data['token_value'] ?? $data['payment_token'] ?? null;
    $amount = $data['amount'] ?? null;
    $currency = $data['currency'] ?? 'USD';

    if (empty($tokenValue)) {
        throw new Exception('Payment token is required', 400);
    }

    if (!is_numeric($amount) || (float)$amount <= 0) {
        throw new Exception('Valid payment amount is required', 400);
    }

    if ((float)$amount > 999999.99) {
        throw new Exception('Payment amount too large', 400);
    }

    // Configure GlobalPayments SDK
    $secretApiKey = $_ENV['SECRET_API_KEY'] ?? null;
    if (empty($secretApiKey)) {
        throw new Exception('Payment system not configured', 500);
    }

    $config = new \GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig();
    $config->secretApiKey = $secretApiKey;
    $config->developerId = $_ENV['DEVELOPER_ID'] ?? '000000';
    $config->versionNumber = $_ENV['VERSION_NUMBER'] ?? '0000';
    $config->serviceUrl = $_ENV['SERVICE_URL'] ?? 'https://cert.api2.heartlandportico.com';

    \GlobalPayments\Api\ServicesContainer::configureService($config);

    // Create card data from token
    $card = new \GlobalPayments\Api\PaymentMethods\CreditCardData();
    $card->token = $tokenValue;

    // Create billing address if provided
    $address = null;
    if (!empty($data['billing_address'])) {
        $billingData = $data['billing_address'];
        $address = new \GlobalPayments\Api\Entities\Address();
        $address->streetAddress1 = $billingData['street'] ?? '';
        $address->city = $billingData['city'] ?? '';
        $address->state = $billingData['state'] ?? '';
        $address->postalCode = $billingData['postal_code'] ?? '';
        $address->country = $billingData['country'] ?? 'US';
    }

    // Generate unique order ID
    $orderId = 'ORDER-' . time() . '-' . strtoupper(substr(uniqid(), -8));

    // Process the charge
    $chargeBuilder = $card->charge((float)$amount)
        ->withCurrency($currency)
        ->withOrderId($orderId);

    if ($address) {
        $chargeBuilder = $chargeBuilder->withAddress($address);
    }

    // Add customer information if provided
    if (!empty($data['customer'])) {
        $customer = $data['customer'];
        $customerId = 'CUST_' . uniqid();
        $description = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
        
        if (!empty($description)) {
            $chargeBuilder = $chargeBuilder
                ->withCustomerId($customerId)
                ->withDescription("Payment for $description");
        }
    }

    // Execute the charge
    $result = $chargeBuilder->execute();

    // Log successful transaction
    $logger = new \GlobalPayments\Examples\Logger();
    $logger->info(
        'Payment processed successfully',
        [
            'type' => 'payment',
            'amount' => (float)$amount,
            'currency' => $currency,
            'order_id' => $orderId,
            'transaction_id' => $result->transactionId,
            'response_code' => $result->responseCode,
            'response_message' => $result->responseMessage,
            'status' => 'approved',
            'timestamp' => date('c')
        ],
        'payment'
    );

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'data' => [
            'transaction_id' => $result->transactionId,
            'order_id' => $orderId,
            'amount' => (float)$amount,
            'currency' => $currency,
            'response_code' => $result->responseCode,
            'response_message' => $result->responseMessage,
            'authorization_code' => $result->authorizationCode ?? '',
            'avs_response_code' => $result->avsResponseCode ?? '',
            'avs_response_message' => $result->avsResponseMessage ?? '',
            'cvv_response_code' => $result->cvnResponseCode ?? '',
            'cvv_response_message' => $result->cvnResponseMessage ?? '',
            'processed_at' => date('c'),
            'status' => 'approved'
        ]
    ], JSON_THROW_ON_ERROR);

} catch (\GlobalPayments\Api\Entities\Exceptions\ApiException $e) {
    // Log API errors
    error_log("GlobalPayments API error: " . $e->getMessage());
    
    // Check if it's a declined transaction vs system error
    $statusCode = (strpos($e->getMessage(), 'declined') !== false) ? 400 : 500;
    
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => 'Payment processing failed',
        'error' => [
            'code' => 'PAYMENT_ERROR',
            'details' => $e->getMessage()
        ]
    ], JSON_THROW_ON_ERROR);

} catch (Exception $e) {
    // Log general errors
    error_log("Payment processing error: " . $e->getMessage());
    
    $statusCode = $e->getCode() ?: 500;
    if ($statusCode < 400 || $statusCode >= 600) {
        $statusCode = 500;
    }
    
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => [
            'code' => 'PROCESSING_ERROR',
            'details' => 'Payment processing failed'
        ]
    ], JSON_THROW_ON_ERROR);

} catch (Throwable $e) {
    // Log fatal errors
    error_log("Fatal payment error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => [
            'code' => 'FATAL_ERROR',
            'details' => 'An unexpected error occurred'
        ]
    ], JSON_THROW_ON_ERROR);
}
?>