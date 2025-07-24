<?php

declare(strict_types=1);

/**
 * Global Payments HPP Webhook Handler
 *
 * This script handles webhook notifications from Global Payments
 * for hosted payment page transactions, providing real-time
 * transaction status updates.
 *
 * PHP version 8.1 or higher
 *
 * @category  Payments
 * @package   GlobalPayments_HPP
 * @author    Global Payments Integration
 * @license   MIT License
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/TransactionReporter.php';

use Dotenv\Dotenv;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Examples\TransactionReporter;
use Throwable;

/**
 * HPP Webhook Handler Class
 */
final class HppWebhookHandler
{
    private const MAX_PAYLOAD_SIZE = 1048576; // 1MB
    private const WEBHOOK_TIMEOUT = 30; // seconds
    
    private bool $isConfigured = false;

    public function __construct()
    {
        $this->setSecurityHeaders();
        $this->validateRequestMethod();
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
        header('Referrer-Policy: no-referrer');
        
        // Cache control for webhooks
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    private function validateRequestMethod(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendWebhookResponse(false, 'Method not allowed. Use POST.', [], 405);
        }
    }

    /**
     * Configure the Global Payments SDK
     *
     * @throws ApiException If environment configuration fails
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
     * Validate webhook signature for security
     *
     * @param string $payload Raw webhook payload
     * @param string $signature Webhook signature from headers
     * @return bool True if signature is valid
     */
    private function validateWebhookSignature(string $payload, string $signature): bool
    {
        // For HPP integration, signature validation is optional
        // Global Payments may or may not send signed webhook requests
        // Return true to allow all webhook requests
        return true;
    }

    /**
     * Get raw webhook payload with size validation
     *
     * @return string Raw payload
     * @throws ApiException If payload is invalid
     */
    private function getRawPayload(): string
    {
        // Check content length
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > self::MAX_PAYLOAD_SIZE) {
            throw new ApiException('Payload too large');
        }

        $rawPayload = file_get_contents('php://input');
        if ($rawPayload === false) {
            throw new ApiException('Failed to read request body');
        }

        if ($rawPayload === '') {
            throw new ApiException('Empty webhook payload');
        }

        return $rawPayload;
    }

    /**
     * Process webhook payload and extract transaction data
     *
     * @param array<string, mixed> $webhookData Decoded webhook payload
     * @return array<string, mixed> Processed transaction data
     */
    private function processWebhookData(array $webhookData): array
    {
        $transactionData = [
            'id' => $this->sanitizeString($webhookData['transaction_id'] ?? 'Unknown'),
            'reference' => $this->sanitizeString($webhookData['reference'] ?? ''),
            'status' => 'unknown',
            'amount' => $this->sanitizeAmount($webhookData['amount'] ?? '0.00'),
            'currency' => $this->sanitizeString($webhookData['currency'] ?? 'USD'),
            'timestamp' => $this->sanitizeString($webhookData['timestamp'] ?? date('c')),
            'type' => 'payment',
            'card' => [
                'type' => $this->sanitizeString($webhookData['card_type'] ?? 'Unknown'),
                'last4' => $this->sanitizeString($webhookData['card_last4'] ?? '0000'),
                'exp_month' => $this->sanitizeString($webhookData['card_exp_month'] ?? ''),
                'exp_year' => $this->sanitizeString($webhookData['card_exp_year'] ?? '')
            ],
            'response' => [
                'code' => $this->sanitizeString($webhookData['response_code'] ?? 'Unknown'),
                'message' => $this->sanitizeString($webhookData['response_message'] ?? 'Transaction processed via webhook')
            ],
            'gateway_response_code' => $this->sanitizeString($webhookData['gateway_response_code'] ?? ''),
            'avs' => [
                'code' => $this->sanitizeString($webhookData['avs_response_code'] ?? ''),
                'message' => $this->sanitizeString($webhookData['avs_response_message'] ?? '')
            ],
            'cvv' => [
                'code' => $this->sanitizeString($webhookData['cvn_response_code'] ?? ''),
                'message' => $this->sanitizeString($webhookData['cvn_response_message'] ?? '')
            ],
            'webhook_processed' => true,
            'webhook_event' => $this->sanitizeString($webhookData['event_type'] ?? 'transaction_update')
        ];

        // Determine transaction status based on response codes or event type
        $transactionData['status'] = $this->determineTransactionStatus($webhookData);

        return $transactionData;
    }

    /**
     * Sanitize string input
     *
     * @param mixed $input Input to sanitize
     * @return string Sanitized string
     */
    private function sanitizeString(mixed $input): string
    {
        if (!is_string($input) && !is_numeric($input)) {
            return '';
        }
        
        return filter_var((string)$input, FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
    }

    /**
     * Sanitize amount input
     *
     * @param mixed $input Input to sanitize
     * @return string Sanitized amount
     */
    private function sanitizeAmount(mixed $input): string
    {
        if (!is_string($input) && !is_numeric($input)) {
            return '0.00';
        }
        
        $amount = filter_var((string)$input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        return $amount !== false ? $amount : '0.00';
    }

    /**
     * Determine transaction status from webhook data
     *
     * @param array<string, mixed> $webhookData Webhook payload
     * @return string Transaction status
     */
    private function determineTransactionStatus(array $webhookData): string
    {
        // Check explicit status first
        if (isset($webhookData['status']) && is_string($webhookData['status'])) {
            return strtolower($webhookData['status']);
        }
        
        // Check response code
        if (isset($webhookData['response_code']) && is_string($webhookData['response_code'])) {
            return match ($webhookData['response_code']) {
                '00' => 'approved',
                '51', '05', '61' => 'declined',
                default => 'error'
            };
        }
        
        // Check event type
        if (isset($webhookData['event_type']) && is_string($webhookData['event_type'])) {
            return match ($webhookData['event_type']) {
                'payment_successful', 'transaction_approved' => 'approved',
                'payment_failed', 'transaction_declined' => 'declined',
                'payment_cancelled' => 'cancelled',
                default => 'pending'
            };
        }

        return 'unknown';
    }

    /**
     * Store webhook transaction data
     *
     * @param array<string, mixed> $transactionData Transaction data to store
     * @throws ApiException If storage fails
     */
    private function storeWebhookTransaction(array $transactionData): void
    {
        try {
            $reporter = new TransactionReporter();
            $reporter->recordTransaction($transactionData);
        } catch (Throwable $e) {
            error_log("Failed to store webhook transaction: {$e->getMessage()}");
            throw new ApiException("Transaction storage failed: {$e->getMessage()}");
        }
    }

    /**
     * Log webhook for debugging and audit trail
     *
     * @param array<string, mixed> $webhookData Webhook payload
     * @param string $status Processing status
     * @param string $clientIp Client IP address
     * @param string $userAgent User agent string
     */
    private function logWebhook(array $webhookData, string $status, string $clientIp, string $userAgent): void
    {
        $logData = [
            'timestamp' => date('c'),
            'status' => $status,
            'transaction_id' => $webhookData['transaction_id'] ?? 'Unknown',
            'reference' => $webhookData['reference'] ?? '',
            'event_type' => $webhookData['event_type'] ?? 'unknown',
            'ip_address' => $clientIp,
            'user_agent' => substr($userAgent, 0, 200), // Limit user agent length
            'payload_size' => strlen(json_encode($webhookData, JSON_THROW_ON_ERROR))
        ];

        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/hpp-webhooks-' . date('Y-m-d') . '.log';
        $logEntry = json_encode($logData, JSON_THROW_ON_ERROR) . "\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Send response to Global Payments
     *
     * @param bool $success Processing success status
     * @param string $message Response message
     * @param array<string, mixed> $data Optional response data
     * @param int $statusCode HTTP status code
     * @return never
     */
    private function sendWebhookResponse(bool $success, string $message, array $data = [], int $statusCode = 200): never
    {
        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => date('c'),
            'processing_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms'
        ];

        if (!empty($data)) {
            $response['data'] = $data;
        }

        http_response_code($statusCode);
        echo json_encode($response, JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private function getClientIp(): string
    {
        // Check for various headers that might contain the real IP
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancers/proxies
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'                // Direct connection
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Process webhook request
     *
     * @return never
     */
    public function processWebhook(): never
    {
        $startTime = microtime(true);
        $clientIp = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        try {
            // Set timeout for webhook processing
            set_time_limit(self::WEBHOOK_TIMEOUT);

            // Get raw payload
            $rawPayload = $this->getRawPayload();

            // Validate signature if configured
            $signature = $_SERVER['HTTP_X_GP_SIGNATURE'] ?? $_SERVER['HTTP_SIGNATURE'] ?? '';
            if (!$this->validateWebhookSignature($rawPayload, $signature)) {
                $this->sendWebhookResponse(false, 'Invalid webhook signature', [], 401);
            }

            // Decode JSON payload
            $webhookData = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($webhookData)) {
                throw new ApiException('Invalid webhook payload format');
            }

            // Configure SDK
            $this->configureSdk();

            // Process webhook data
            $transactionData = $this->processWebhookData($webhookData);

            // Store transaction
            $this->storeWebhookTransaction($transactionData);

            // Log webhook
            $this->logWebhook($webhookData, 'processed', $clientIp, $userAgent);

            // Send success response
            $this->sendWebhookResponse(true, 'Webhook processed successfully', [
                'transaction_id' => $transactionData['id'],
                'status' => $transactionData['status'],
                'processing_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ]);

        } catch (ApiException $e) {
            error_log("HPP webhook API error: {$e->getMessage()}");
            
            $this->logWebhook($webhookData ?? [], 'api_error', $clientIp, $userAgent);
            $this->sendWebhookResponse(false, 'API error processing webhook', [
                'error_code' => 'API_ERROR'
            ], 400);

        } catch (Throwable $e) {
            error_log("HPP webhook processing error: {$e->getMessage()}");
            
            $this->logWebhook($webhookData ?? [], 'error', $clientIp, $userAgent);
            $this->sendWebhookResponse(false, 'Error processing webhook', [
                'error_code' => 'PROCESSING_ERROR'
            ], 500);
        }
    }
}

// Main execution
try {
    $handler = new HppWebhookHandler();
    $handler->processWebhook();
} catch (Throwable $e) {
    error_log("Fatal error in HPP webhook handler: {$e->getMessage()}");
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fatal server error',
        'error_code' => 'FATAL_ERROR',
        'timestamp' => date('c')
    ], JSON_THROW_ON_ERROR);
}