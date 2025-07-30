<?php

/**
 * Heartland Payment Processing
 *
 * Processes payments using Heartland's tokenization system with iframe integration
 *
 * PHP version 8.1 or higher
 *
 * @category  Payments
 * @package   Heartland_Payments
 * @author    Heartland Integration
 * @license   MIT License
 */

declare(strict_types=1);

namespace GlobalPayments\Examples;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Logger.php';

use Dotenv\Dotenv;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Examples\Logger;
use Throwable;

/**
 * Heartland Payment Processor Class
 */
final class HeartlandPaymentProcessor
{
    private bool $isConfigured = false;
    private Logger $logger;

    public function __construct()
    {
        $this->setSecurityHeaders();
        $this->handlePreflight();
        $this->logger = new Logger();
    }

    private function setSecurityHeaders(): void
    {
        // Disable error display for production security
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        // Set secure headers
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');

        // CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }

    private function handlePreflight(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Configure the Heartland/Portico SDK
     *
     * @throws ApiException If configuration fails
     */
    private function configureSdk(): void
    {
        if ($this->isConfigured) {
            return;
        }

        try {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();

            $secretApiKey = $_ENV['SECRET_API_KEY'] ?? throw new ApiException('SECRET_API_KEY not configured');

            $config = new PorticoConfig();
            $config->secretApiKey = $secretApiKey;
            $config->developerId = $_ENV['DEVELOPER_ID'] ?? '000000';
            $config->versionNumber = $_ENV['VERSION_NUMBER'] ?? '0000';
            $config->serviceUrl = $_ENV['SERVICE_URL'] ?? 'https://cert.api2.heartlandportico.com';

            ServicesContainer::configureService($config);
            $this->isConfigured = true;
        } catch (Throwable $e) {
            throw new ApiException("SDK configuration failed: {$e->getMessage()}");
        }
    }

    /**
     * Validate payment request data
     *
     * @param array<string, mixed> $data Request data
     * @return array<string> Validation errors
     */
    private function validatePaymentRequest(array $data): array
    {
        $errors = [];

        // Validate token - accept both payment_token and token_value for compatibility
        $tokenValue = $data['token_value'] ?? $data['payment_token'] ?? null;
        if (empty($tokenValue)) {
            $errors[] = 'Payment token is required';
        }

        // Validate amount
        if (!isset($data['amount']) || !is_numeric($data['amount']) || (float)$data['amount'] <= 0) {
            $errors[] = 'Valid transaction amount greater than 0 is required';
        } elseif ((float)$data['amount'] > 999999.99) {
            $errors[] = 'Transaction amount cannot exceed 999,999.99';
        }

        // Validate currency
        $currency = $data['currency'] ?? 'USD';
        if (!in_array($currency, ['USD', 'CAD'], true)) {
            $errors[] = 'Invalid currency. Supported: USD, CAD';
        }

        return $errors;
    }

    /**
     * Process payment using Heartland tokenization
     *
     * @param array<string, mixed> $data Payment data
     * @return array<string, mixed> Payment result
     * @throws ApiException
     */
    private function processPayment(array $data): array
    {
        $this->configureSdk();

        $amount = (float)$data['amount'];
        $currency = $data['currency'] ?? 'USD';
        $tokenValue = $data['token_value'] ?? $data['payment_token'];

        // Create card data from token
        $card = new CreditCardData();
        $card->token = $tokenValue;

        // Create billing address if provided
        $address = null;
        if (!empty($data['billing_address'])) {
            $billingData = $data['billing_address'];
            $address = new Address();
            $address->streetAddress1 = $billingData['street'] ?? '';
            $address->city = $billingData['city'] ?? '';
            $address->state = $billingData['state'] ?? '';
            $address->postalCode = $billingData['postal_code'] ?? '';
            $address->country = $billingData['country'] ?? 'US';
        }

        // Generate unique order ID
        $orderId = 'ORDER-' . time() . '-' . strtoupper(substr(uniqid(), -8));

        try {
            // Process the charge
            $response = $card->charge($amount)
                ->withCurrency($currency)
                ->withOrderId($orderId)
                ->withAddress($address);

            // Add customer information if provided
            if (!empty($data['customer'])) {
                $customer = $data['customer'];
                $customerId = $customer['customer_id'] ?? $customer['id'] ?? 'CUST_' . uniqid();
                $description = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
                
                if (!empty($description)) {
                    $response = $response
                        ->withCustomerId($customerId)
                        ->withDescription("Payment for $description");
                }
            }

            $result = $response->execute();

            // Log successful transaction
            $this->logger->info(
                'Payment processed successfully',
                [
                    'type' => 'payment',
                    'amount' => $amount,
                    'currency' => $currency,
                    'order_id' => $orderId,
                    'transaction_id' => $result->transactionId,
                    'response_code' => $result->responseCode,
                    'response_message' => $result->responseMessage,
                    'status' => 'success',
                    'timestamp' => date('c')
                ],
                'payment'
            );

            return [
                'transaction_id' => $result->transactionId,
                'order_id' => $orderId,
                'amount' => $amount,
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
            ];
        } catch (ApiException $e) {
            // Log failed transaction
            $this->logger->error(
                'Payment processing failed',
                [
                    'type' => 'payment',
                    'amount' => $amount,
                    'currency' => $currency,
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                    'status' => 'failed',
                    'timestamp' => date('c')
                ],
                'payment'
            );

            throw $e;
        }
    }

    /**
     * Send JSON response
     *
     * @param array<string, mixed> $data Response data
     * @param int $statusCode HTTP status code
     * @return void
     */
    private function sendJsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode($data, JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * Process payment request
     *
     * @return void
     */
    public function processRequest(): void
    {
        try {
            // Only accept POST requests
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Method not allowed. Use POST.',
                    'error' => ['code' => 'METHOD_NOT_ALLOWED']
                ], 405);
            }

            // Get and decode request data
            $input = file_get_contents('php://input');
            if ($input === false || $input === '') {
                throw new ApiException('Empty request body');
            }

            $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);

            // Validate request
            $errors = $this->validatePaymentRequest($data);
            if (!empty($errors)) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
                ], 400);
            }

            // Process payment
            $paymentResult = $this->processPayment($data);

            // Success response
            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => $paymentResult
            ]);
        } catch (ApiException $e) {
            error_log("Heartland payment API error: {$e->getMessage()}");

            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => [
                    'code' => 'PAYMENT_ERROR',
                    'details' => $e->getMessage()
                ]
            ], 400);
        } catch (Throwable $e) {
            error_log("Heartland payment error: {$e->getMessage()}");

            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Internal server error',
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'details' => 'An unexpected error occurred'
                ]
            ], 500);
        }
    }
}

// Main execution
try {
    $processor = new HeartlandPaymentProcessor();
    $processor->processRequest();
} catch (Throwable $e) {
    error_log("Fatal error in Heartland payment processing: {$e->getMessage()}");
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fatal server error',
        'error' => ['code' => 'FATAL_ERROR']
    ], JSON_THROW_ON_ERROR);
}
