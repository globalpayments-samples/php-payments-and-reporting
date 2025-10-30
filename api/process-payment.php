<?php
/**
 * Simplified Heartland Payment Processor
 * Fixed version with better error handling
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to user
ini_set('log_errors', 1);

/**
 * Helper function to safely access object properties without triggering magic method warnings
 * @param object $object The object to check
 * @param string $property The property name to access
 * @return mixed|null The property value or null if not accessible
 */
function safeGetProperty($object, $property) {
    return property_exists($object, $property) ? $object->$property : null;
}

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
    $currency = $data['currency'] ?? ($_ENV['GP_API_CURRENCY'] ?? 'USD');

    if (empty($tokenValue)) {
        throw new Exception('Payment token is required', 400);
    }

    if (!is_numeric($amount) || (float)$amount <= 0) {
        throw new Exception('Valid payment amount is required', 400);
    }

    if ((float)$amount > 999999.99) {
        throw new Exception('Payment amount too large', 400);
    }

    // Configure GP-API SDK
    $appId = $_ENV['GP_API_APP_ID'] ?? null;
    $appKey = $_ENV['GP_API_APP_KEY'] ?? null;
    $environment = $_ENV['GP_API_ENVIRONMENT'] ?? 'sandbox';
    
    if (empty($appId) || empty($appKey)) {
        throw new Exception('GP-API credentials not configured', 500);
    }

    $config = new \GlobalPayments\Api\ServiceConfigs\Gateways\GpApiConfig();
    $config->appId = $appId;
    $config->appKey = $appKey;
    $config->environment = $environment === 'production' 
        ? \GlobalPayments\Api\Entities\Enums\Environment::PRODUCTION 
        : \GlobalPayments\Api\Entities\Enums\Environment::TEST;
    $config->channel = \GlobalPayments\Api\Entities\Enums\Channel::CardNotPresent;
    // Set processing country from env to match merchant setup (defaults to US)
    $config->country = $_ENV['GP_API_COUNTRY'] ?? 'US';
    // Note: Account ID is automatically populated from the access token
    // The account ID from environment is used for validation
    // Optional: if merchant id is configured, set it to improve routing (not required)
    if (!empty($_ENV['GP_API_MERCHANT_ID'])) {
        $config->merchantId = $_ENV['GP_API_MERCHANT_ID'];
    }

    \GlobalPayments\Api\ServicesContainer::configureService($config);

    // Preflight: ensure token has transaction processing account scope
    try {
        $tokenInfo = \GlobalPayments\Api\Services\GpApiService::generateTransactionKey($config);
        
        // Log account information for debugging
        error_log("GP-API Token Info - Account ID: " . ($tokenInfo->transactionProcessingAccountID ?? 'NOT_SET'));
        error_log("GP-API Token Info - Account Name: " . ($tokenInfo->transactionProcessingAccountName ?? 'NOT_SET'));
        error_log("GP-API Token Info - Token: " . ($tokenInfo->accessToken ?? 'NOT_SET'));
        
        if (empty($tokenInfo->transactionProcessingAccountID) || empty($tokenInfo->transactionProcessingAccountName)) {
            throw new Exception('GP-API access token lacks transaction processing account scope. Check merchant/account assignment.');
        }
        
        // Validate that the account ID matches what we expect
        $expectedAccountId = $_ENV['GP_API_ACCOUNT_ID'] ?? null;
        if ($expectedAccountId && $tokenInfo->transactionProcessingAccountID !== $expectedAccountId) {
            error_log("Account ID mismatch - Expected: $expectedAccountId, Got: " . $tokenInfo->transactionProcessingAccountID);
        }
        
    } catch (Exception $e) {
        throw new Exception('GP-API token preflight failed: ' . $e->getMessage(), 500);
    }

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

    // Generate unique order and client reference for idempotency/tracing
    $orderId = 'ORDER-' . time() . '-' . strtoupper(substr(uniqid('', true), -8));
    $clientReference = $data['client_reference'] ?? ('REF-' . bin2hex(random_bytes(6)));

    // Process the charge
    $chargeBuilder = $card->charge((float)$amount)
        ->withCurrency($currency)
        ->withOrderId($orderId)
        ->withClientTransactionId($clientReference)
        ->withIdempotencyKey($clientReference);

    if ($address) {
        $chargeBuilder = $chargeBuilder->withAddress($address);
    }

    // Execute the charge
    $result = $chargeBuilder->execute();

    // Debug: Log the full response structure to understand card data
    $logger = new \GlobalPayments\Examples\Logger('logs', 'DEBUG');

    $logger->info(
        'Payment response structure debug',
        [
            'result_class' => get_class($result),
            'result_properties' => get_object_vars($result),
            'has_paymentMethod' => property_exists($result, 'paymentMethod'),
            'paymentMethod_properties' => safeGetProperty($result, 'paymentMethod') ? get_object_vars(safeGetProperty($result, 'paymentMethod')) : null,
            'has_card' => property_exists($result, 'paymentMethod') && property_exists(safeGetProperty($result, 'paymentMethod'), 'card'),
            'card_properties' => (property_exists($result, 'paymentMethod') && property_exists(safeGetProperty($result, 'paymentMethod'), 'card')) ? get_object_vars(safeGetProperty(safeGetProperty($result, 'paymentMethod'), 'card')) : null,
            'request_card_info' => $data['card_info'] ?? null,
            'token_value' => $tokenValue,
        ],
        'system'
    );

    // Check if the transaction was declined
    $responseCode = safeGetProperty($result, 'responseCode');
    $responseMessage = safeGetProperty($result, 'responseMessage');
    
    // GlobalPayments decline response codes
    $declineCodes = ['DECLINED', 'CARD_DECLINED', 'INSUFFICIENT_FUNDS', 'CARD_EXPIRED', 'INVALID_CARD', 'CARD_NOT_SUPPORTED'];
    
    if (in_array($responseCode, $declineCodes)) {
        // Log declined transaction
        $logger->info(
            'Payment declined',
            [
                'type' => 'payment_declined',
                'amount' => (float)$amount,
                'currency' => $currency,
                'order_id' => $orderId,
                'transaction_id' => safeGetProperty(safeGetProperty($result, 'transactionReference'), 'transactionId'),
                'response_code' => $responseCode,
                'response_message' => $responseMessage,
                'status' => 'declined',
                'timestamp' => date('c')
            ],
            'payment'
        );

        // Record declined transaction for dashboard
        try {
            $reporter = new GlobalPayments\Examples\TransactionReporter();
            $transactionData = [
                'id' => safeGetProperty(safeGetProperty($result, 'transactionReference'), 'transactionId') ?: $orderId,
                'reference' => $orderId,
                'status' => 'declined',
                'amount' => (string)$amount,
                'currency' => $currency,
                'type' => 'payment',
                'timestamp' => date('c'),
                'response' => [
                    'code' => $responseCode,
                    'message' => $responseMessage
                ],
                'gateway_response_code' => $responseCode,
                'gateway_response_message' => $responseMessage,
                'processed_at' => date('c')
            ];
            $reporter->recordTransaction($transactionData);
        } catch (Exception $e) {
            error_log('Failed to record declined transaction: ' . $e->getMessage());
        }

        // Return decline response
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Payment declined',
            'error' => [
                'code' => $responseCode,
                'details' => $responseMessage,
                'gp_response_code' => $responseCode,
                'gp_response_message' => $responseMessage
            ]
        ], JSON_THROW_ON_ERROR);
        return;
    }

    // Log successful transaction
    $logger->info(
        'Payment processed successfully',
        [
            'type' => 'payment',
            'amount' => (float)$amount,
            'currency' => $currency,
            'order_id' => $orderId,
            'transaction_id' => safeGetProperty(safeGetProperty($result, 'transactionReference'), 'transactionId'),
            'response_code' => safeGetProperty($result, 'responseCode'),
            'response_message' => safeGetProperty($result, 'responseMessage'),
            'status' => 'approved',
            'timestamp' => date('c')
        ],
        'payment'
    );

    // Record transaction for dashboard display
    try {
        $reporter = new GlobalPayments\Examples\TransactionReporter();
        // Extract card information with fallback options
        $cardType = 'Unknown';
        $cardLast4 = null;
        $cardExpMonth = '';
        $cardExpYear = '';

        // Try multiple possible locations for card data - based on actual GP-API response structure
        if (property_exists($result, 'cardType') && property_exists($result, 'cardLast4')) {
            // Direct properties on response object (as seen in logs)
            $cardType = safeGetProperty($result, 'cardType') ?? 'Unknown';
            $cardLast4 = safeGetProperty($result, 'cardLast4') ?? null;
        } elseif (property_exists($result, 'cardDetails')) {
            // Card details object (as seen in logs)
            $cardDetails = safeGetProperty($result, 'cardDetails');
            $cardType = safeGetProperty($cardDetails, 'brand') ?? safeGetProperty($cardDetails, 'cardType') ?? safeGetProperty($cardDetails, 'type') ?? 'Unknown';
            $cardLast4 = safeGetProperty($cardDetails, 'maskedNumberLast4') ?? safeGetProperty($cardDetails, 'lastFourDigits') ?? safeGetProperty($cardDetails, 'last4') ?? safeGetProperty($cardDetails, 'maskedNumber') ?? null;
            $cardExpMonth = safeGetProperty($cardDetails, 'cardExpMonth') ?? safeGetProperty($cardDetails, 'expMonth') ?? safeGetProperty($cardDetails, 'expiryMonth') ?? '';
            $cardExpYear = safeGetProperty($cardDetails, 'cardExpYear') ?? safeGetProperty($cardDetails, 'expYear') ?? safeGetProperty($cardDetails, 'expiryYear') ?? '';
        } elseif (property_exists($result, 'paymentMethod') && property_exists(safeGetProperty($result, 'paymentMethod'), 'card')) {
            // Payment method card object
            $card = safeGetProperty($result, 'paymentMethod');
            $card = safeGetProperty($card, 'card');
            $cardType = safeGetProperty($card, 'brand') ?? safeGetProperty($card, 'cardType') ?? safeGetProperty($card, 'type') ?? 'Unknown';
            $cardLast4 = safeGetProperty($card, 'maskedNumberLast4') ?? safeGetProperty($card, 'lastFourDigits') ?? safeGetProperty($card, 'last4') ?? safeGetProperty($card, 'maskedNumber') ?? null;
            $cardExpMonth = safeGetProperty($card, 'expMonth') ?? safeGetProperty($card, 'expiryMonth') ?? '';
            $cardExpYear = safeGetProperty($card, 'expYear') ?? safeGetProperty($card, 'expiryYear') ?? '';
        } elseif (property_exists($result, 'card')) {
            // Direct card property
            $card = safeGetProperty($result, 'card');
            $cardType = safeGetProperty($card, 'brand') ?? safeGetProperty($card, 'cardType') ?? safeGetProperty($card, 'type') ?? 'Unknown';
            $cardLast4 = safeGetProperty($card, 'maskedNumberLast4') ?? safeGetProperty($card, 'lastFourDigits') ?? safeGetProperty($card, 'last4') ?? safeGetProperty($card, 'maskedNumber') ?? null;
            $cardExpMonth = safeGetProperty($card, 'expMonth') ?? safeGetProperty($card, 'expiryMonth') ?? '';
            $cardExpYear = safeGetProperty($card, 'expYear') ?? safeGetProperty($card, 'expiryYear') ?? '';
        }

        // Also try to extract from payment token if available
        if ($cardLast4 === null && isset($tokenValue)) {
            // Log the token for debugging
            $logger->info(
                'Attempting to extract card info from token',
                [
                    'token_value' => $tokenValue,
                    'token_length' => strlen($tokenValue),
                ],
                'system'
            );
            
            // The payment token might contain card information
            // This is a fallback - the actual implementation depends on GP-API token structure
            // For now, we'll rely on the card_info from the request
        }

        // Use card information from request if available (from tokenization response)
        $cardInfo = null;
        if (isset($data['card_details'])) {
            $cardInfo = $data['card_details'];
            $logger->info(
                'Card details from request',
                [
                    'card_details' => $cardInfo,
                    'current_card_type' => $cardType,
                    'current_card_last4' => $cardLast4,
                ],
                'system'
            );
        } elseif (isset($data['card_info'])) {
            // Backward compatibility
            $cardInfo = $data['card_info'];
            $logger->info(
                'Card info from request (legacy)',
                [
                    'card_info' => $cardInfo,
                    'current_card_type' => $cardType,
                    'current_card_last4' => $cardLast4,
                ],
                'system'
            );
        }
        
        if ($cardInfo) {
            // Also log to error_log for immediate debugging
            error_log('Card info from request: ' . json_encode($cardInfo));
            
            // Always prefer card info from request over API response for consistency
            if (!empty($cardInfo['type'])) {
                $cardType = $cardInfo['type'];
            }
            if (!empty($cardInfo['last4'])) {
                $cardLast4 = $cardInfo['last4'];
            }
            if (!empty($cardInfo['exp_month'])) {
                $cardExpMonth = $cardInfo['exp_month'];
            }
            if (!empty($cardInfo['exp_year'])) {
                $cardExpYear = $cardInfo['exp_year'];
            }
        } else {
            error_log('No card details found in request data');
        }

        // Log the final card information that will be stored
        $logger->info(
            'Final card information for storage',
            [
                'card_type' => $cardType,
                'card_last4' => $cardLast4,
                'card_exp_month' => $cardExpMonth,
                'card_exp_year' => $cardExpYear,
            ],
            'system'
        );

        $transactionData = [
            'id' => safeGetProperty(safeGetProperty($result, 'transactionReference'), 'transactionId'),
            'reference' => $orderId,
            'status' => 'approved',
            'amount' => (string)$amount,
            'currency' => $currency,
            'type' => 'payment',
            'timestamp' => date('c'),
            'card' => [
                'type' => $cardType,
                'last4' => $cardLast4,
                'exp_month' => $cardExpMonth,
                'exp_year' => $cardExpYear
            ],
            'response' => [
                'code' => safeGetProperty($result, 'responseCode') ?? 'SUCCESS',
                'message' => safeGetProperty($result, 'responseMessage') ?? 'Approved'
            ],
            'gateway_response_code' => safeGetProperty($result, 'responseCode') ?? 'SUCCESS',
            'gateway_response_message' => safeGetProperty($result, 'responseMessage') ?? 'Approved',
            'batch_id' => safeGetProperty($result, 'batchSummary') ? safeGetProperty(safeGetProperty($result, 'batchSummary'), 'batchReference') : '',
            'auth_code' => safeGetProperty(safeGetProperty($result, 'transactionReference'), 'authCode') ?? '',
            'avs' => [
                'code' => safeGetProperty($result, 'avsResponseCode') ?? '',
                'message' => safeGetProperty($result, 'avsResponseMessage') ?? ''
            ],
            'cvv' => [
                'code' => safeGetProperty($result, 'cvnResponseCode') ?? '',
                'message' => safeGetProperty($result, 'cvnResponseMessage') ?? ''
            ]
        ];
        $reporter->recordTransaction($transactionData);
    } catch (Exception $e) {
        // Log the error but don't fail the payment
        error_log('Failed to record payment transaction: ' . $e->getMessage());
    }

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'data' => [
            'transaction_id' => safeGetProperty(safeGetProperty($result, 'transactionReference'), 'transactionId'),
            'amount' => (string)$amount,
            'currency' => $currency,
            'status' => 'approved',
            'authorization_code' => safeGetProperty(safeGetProperty($result, 'transactionReference'), 'authCode'),
            'response_code' => safeGetProperty($result, 'responseCode'),
            'response_message' => safeGetProperty($result, 'responseMessage'),
            'processed_at' => date('c'),
            'card_type' => safeGetProperty($result, 'cardType'),
            'card_last4' => safeGetProperty($result, 'cardLast4'),
            'avs_code' => safeGetProperty($result, 'avsResponseCode'),
            'cvv_code' => safeGetProperty($result, 'cvnResponseCode')
        ]
    ], JSON_THROW_ON_ERROR);

} catch (\GlobalPayments\Api\Entities\Exceptions\GatewayException $e) {
    // Log API errors
    error_log("GlobalPayments API error: " . $e->getMessage());
    
    // Extract the actual GP-API error code and details
    $gpErrorCode = $e->responseCode ?? 'UNKNOWN_ERROR';
    $errorDetails = $e->getMessage();
    $status = 500;
    
    // Set appropriate HTTP status based on error type
    if (in_array($gpErrorCode, ['INVALID_REQUEST_DATA', 'MANDATORY_DATA_MISSING'])) {
        $status = 400;
    } elseif (in_array($gpErrorCode, ['ACTION_NOT_AUTHORIZED', 'PERMISSION_NOT_ENABLED'])) {
        $status = 403;
    } elseif (in_array($gpErrorCode, ['DECLINED', 'CARD_DECLINED'])) {
        $status = 422;
    }
    
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'message' => 'Payment processing failed',
        'error' => [
            'code' => $gpErrorCode,
            'details' => $errorDetails,
            'gp_response_code' => $e->responseCode ?? null,
            'gp_response_message' => $e->responseMessage ?? null
        ]
    ], JSON_THROW_ON_ERROR);

} catch (\GlobalPayments\Api\Entities\Exceptions\ApiException $e) {
    // Fallback for generic API exceptions without responseCode
    error_log("GlobalPayments API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Payment processing failed',
        'error' => [
            'code' => 'API_EXCEPTION',
            'details' => $e->getMessage(),
            'gp_response_code' => null,
            'gp_response_message' => null
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