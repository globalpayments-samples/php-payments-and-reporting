<?php

/**
 * Global Payments Hosted Payment Page (HPP) Session Creation
 *
 * This script creates a hosted payment page session that redirects users
 * to Global Payments secure payment form for real transaction processing.
 *
 * PHP version 8.1 or higher
 *
 * @category  Payments
 * @package   GlobalPayments_HPP
 * @author    Global Payments Integration
 * @license   MIT License
 */

declare(strict_types=1);

namespace GlobalPayments\Examples;

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig;
use GlobalPayments\Api\ServicesContainer;
use Throwable;

/**
 * HPP Session Creator Class
 */
final class HppSessionCreator
{
    private const VALID_CURRENCIES = ['USD', 'EUR', 'GBP', 'CAD', 'AUD'];

    private bool $isConfigured = false;

    public function __construct()
    {
        $this->setSecurityHeaders();
        $this->handlePreflight();
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

        // CORS headers (restrict in production)
        header('Access-Control-Allow-Origin: *'); // TODO: Restrict to specific domains in production
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
     * Configure the Global Payments SDK for HPP
     *
     * @throws ApiException If configuration fails
     */
    private function configureSdk(): void
    {
        if ($this->isConfigured) {
            return;
        }

        try {
            $dotenv = Dotenv::createImmutable(__DIR__);
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
     * Validate HPP session request data
     *
     * @param array<string, mixed> $data Request data
     * @return array<string> Validation errors
     */
    private function validateHppRequest(array $data): array
    {
        $errors = [];

        // Validate mode
        $mode = $data['mode'] ?? 'payment';
        if (!in_array($mode, ['verification', 'payment'], true)) {
            $errors[] = 'Invalid mode. Supported: verification, payment';
        }

        // For payment mode, amount is required and must be > 0
        // For verification mode, amount can be 0
        if ($mode === 'payment') {
            if (!isset($data['amount']) || !is_numeric($data['amount']) || (float)$data['amount'] <= 0) {
                $errors[] = 'Valid transaction amount greater than 0 is required for payments';
            } elseif ((float)$data['amount'] > 999999.99) {
                $errors[] = 'Transaction amount cannot exceed 999,999.99';
            }
        } else {
            // For verification, amount can be 0 or small amount
            if (isset($data['amount']) && is_numeric($data['amount']) && (float)$data['amount'] > 1.00) {
                $errors[] = 'Verification amount should be 0 or a small amount (max $1.00)';
            }
        }

        // Validate currency
        $currency = $data['currency'] ?? 'USD';
        if (!in_array($currency, self::VALID_CURRENCIES, true)) {
            $errors[] = sprintf('Invalid currency. Supported: %s', implode(', ', self::VALID_CURRENCIES));
        }

        return $errors;
    }


    /**
     * Generate base URL for return URLs
     *
     * @return string Base URL
     */
    private function getBaseUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '');

        return $protocol . $host . $scriptPath;
    }

    /**
     * Generate access token for Global Payments API
     *
     * @return string Access token
     * @throws ApiException If token generation fails
     */
    private function generateAccessToken(): string
    {
        $appId = $_ENV['GP_APP_ID'] ?? throw new ApiException('GP_APP_ID not configured');
        $appKey = $_ENV['GP_APP_KEY'] ?? throw new ApiException('GP_APP_KEY not configured');
        $baseUrl = $_ENV['GP_BASE_URL'] ?? 'https://apis.sandbox.globalpay.com';

        $credentials = base64_encode($appId . ':' . $appKey);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $baseUrl . '/ucp/accesstoken',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . $credentials,
                'Accept: application/json',
                'X-GP-Version: 2021-03-22'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'app_id' => $appId,
                'secret' => $appKey,
                'nonce' => uniqid(),
                'grant_type' => 'client_credentials'
            ], JSON_THROW_ON_ERROR),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new ApiException("cURL error: {$error}");
        }

        if ($httpCode !== 200) {
            throw new ApiException("Token request failed with HTTP {$httpCode}");
        }

        $tokenData = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($tokenData['token'])) {
            throw new ApiException('No token received from API');
        }

        return $tokenData['token'];
    }

    /**
     * Create HPP session using Global Payments API
     *
     * @param array<string, mixed> $data Transaction data
     * @return array<string, mixed> HPP session result
     * @throws ApiException
     */
    private function createHppSession(array $data): array
    {
        $accessToken = $this->generateAccessToken();
        $baseUrl = $this->getBaseUrl();
        $mode = $data['mode'] ?? 'payment';

        // Generate unique reference based on mode
        $prefix = $mode === 'verification' ? 'VERIFY' : 'ORDER';
        $reference = $prefix . '-' . time() . '-' . strtoupper(substr(uniqid(), -8));

        // For verification, use 0 or small amount
        $amount = $mode === 'verification' ? 0 : (float)$data['amount'] * 100; // Convert to cents
        $currency = $data['currency'] ?? 'USD';

        // Prepare HPP request payload
        $payload = [
            'account_name' => $_ENV['GP_ACCOUNT_NAME'] ?? 'transaction_processing',
            'type' => 'HOSTED_PAYMENT_PAGE',
            'reference' => $reference,
            'description' => $mode === 'verification' 
                ? "Card verification for reference: {$reference}"
                : "Payment for reference: {$reference}",
            'order' => [
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $reference
            ],
            'payer' => [
                'reference' => 'customer_' . uniqid(),
                'name' => [
                    'first' => $data['customer']['first_name'] ?? 'Customer',
                    'last' => $data['customer']['last_name'] ?? 'User'
                ],
                'email' => $data['customer']['email'] ?? 'customer@example.com'
            ],
            'notifications' => [
                'return_url' => "{$baseUrl}/hpp-return.php",
                'status_url' => "{$baseUrl}/hpp-webhook.php",
                'cancel_url' => "{$baseUrl}/hpp-cancel.php"
            ],
            'usage_mode' => 'SINGLE',
            'allowed_payment_methods' => ['CARD'],
            'capture_mode' => $mode === 'verification' ? 'AUTO' : 'AUTO' // For verification, we might want to authorize only
        ];

        // Add verification-specific settings
        if ($mode === 'verification') {
            $payload['verification_type'] = $data['verification_type'] ?? 'basic';
            if ($data['verification_type'] === 'avs' || $data['verification_type'] === 'full') {
                $payload['address_verification'] = true;
            }
            if ($data['verification_type'] === 'cvv' || $data['verification_type'] === 'full') {
                $payload['cvv_verification'] = true;
            }
        }

        // Add billing address if provided
        if (!empty($data['billing_address'])) {
            $address = $data['billing_address'];
            $payload['payer']['billing_address'] = [
                'line_1' => $address['street'] ?? '',
                'city' => $address['city'] ?? '',
                'state' => $address['state'] ?? '',
                'country' => $address['country'] ?? 'US',
                'postal_code' => $address['postal_code'] ?? ''
            ];
        }

        // Make API request to create HPP session
        $gpBaseUrl = $_ENV['GP_BASE_URL'] ?? 'https://apis.sandbox.globalpay.com';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $gpBaseUrl . '/ucp/links',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json',
                'X-GP-Version: 2021-03-22'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new ApiException("HPP creation cURL error: {$error}");
        }

        if ($httpCode !== 201 && $httpCode !== 200) {
            $errorResponse = json_decode($response, true);
            error_log("HPP Debug: API Error Response: " . $response);
            $errorMessage = $errorResponse['error_description'] ?? "HTTP {$httpCode}";
            throw new ApiException("HPP creation failed: {$errorMessage}");
        }

        $hppData = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($hppData['url'])) {
            throw new ApiException('No HPP URL received from Global Payments API');
        }

        return [
            'session_id' => $hppData['id'] ?? $reference,
            'hpp_url' => $hppData['url'],
            'iframe_url' => $hppData['url'], // Same URL can be used in iframe
            'reference' => $reference,
            'amount' => $data['amount'] ?? 0,
            'currency' => $currency,
            'mode' => $mode,
            'verification_type' => $data['verification_type'] ?? null,
            'created_at' => date('c'),
            'status' => 'created',
            'expires_at' => date('c', time() + 1800)
        ];
    }

    /**
     * Store HPP session for tracking
     *
     * @param array<string, mixed> $sessionData HPP session data
     * @return void
     */
    private function storeHppSession(array $sessionData): void
    {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $sessionFile = $logDir . '/hpp-sessions.json';
        $sessions = [];

        if (file_exists($sessionFile)) {
            $content = file_get_contents($sessionFile);
            $sessions = $content ? json_decode($content, true) : [];
        }

        $sessions[] = $sessionData;

        // Keep only last 1000 sessions
        if (count($sessions) > 1000) {
            $sessions = array_slice($sessions, -1000);
        }

        file_put_contents($sessionFile, json_encode($sessions, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
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
     * Process HPP session creation request
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

            // Initialize SDK
            $this->configureSdk();

            // Get and decode request data
            $input = file_get_contents('php://input');
            if ($input === false || $input === '') {
                error_log("HPP Debug: Empty request body received");
                throw new ApiException('Empty request body');
            }

            error_log("HPP Debug: Raw request data: " . $input);
            $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            error_log("HPP Debug: Parsed request data: " . json_encode($data));

            // Validate request
            $errors = $this->validateHppRequest($data);
            if (!empty($errors)) {
                error_log("HPP Debug: Validation errors: " . json_encode($errors));
            }
            if (!empty($errors)) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
                ], 400);
            }

            // Create HPP session
            $sessionResult = $this->createHppSession($data);

            // Store session for tracking
            $this->storeHppSession($sessionResult);

            // Success response
            $this->sendJsonResponse([
                'success' => true,
                'message' => 'HPP session created successfully',
                'data' => $sessionResult
            ]);
        } catch (ApiException $e) {
            error_log("HPP session creation API error: {$e->getMessage()}");

            $this->sendJsonResponse([
                'success' => false,
                'message' => 'HPP session creation failed',
                'error' => [
                    'code' => 'API_ERROR',
                    'details' => $e->getMessage()
                ]
            ], 400);
        } catch (Throwable $e) {
            error_log("HPP session creation error: {$e->getMessage()}");

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
    $hppCreator = new HppSessionCreator();
    $hppCreator->processRequest();
} catch (Throwable $e) {
    error_log("Fatal error in HPP session creation: {$e->getMessage()}");
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fatal server error',
        'error' => ['code' => 'FATAL_ERROR']
    ], JSON_THROW_ON_ERROR);
}
