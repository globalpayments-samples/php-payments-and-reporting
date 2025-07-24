<?php

declare(strict_types=1);

/**
 * Global Payments HPP Return Handler - Success
 *
 * This script handles successful returns from the Global Payments
 * Hosted Payment Page and processes the transaction results.
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
 * HPP Return Handler Class
 */
final class HppReturnHandler
{
    private bool $isConfigured = false;

    public function __construct()
    {
        $this->setSecurityHeaders();
    }

    private function setSecurityHeaders(): void
    {
        // Set secure headers
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
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
     * Sanitize and validate GET parameters
     *
     * @param array<string, mixed> $params Raw GET parameters
     * @return array<string, string> Sanitized parameters
     */
    private function sanitizeParams(array $params): array
    {
        $sanitized = [];
        
        // Define expected parameters with their validation rules
        $expectedParams = [
            'txn_id' => FILTER_SANITIZE_SPECIAL_CHARS,
            'reference' => FILTER_SANITIZE_SPECIAL_CHARS,
            'amount' => FILTER_SANITIZE_NUMBER_FLOAT,
            'currency' => FILTER_SANITIZE_SPECIAL_CHARS,
            'response_code' => FILTER_SANITIZE_SPECIAL_CHARS,
            'response_message' => FILTER_SANITIZE_SPECIAL_CHARS,
            'card_type' => FILTER_SANITIZE_SPECIAL_CHARS,
            'card_last4' => FILTER_SANITIZE_NUMBER_INT,
            'card_exp_month' => FILTER_SANITIZE_NUMBER_INT,
            'card_exp_year' => FILTER_SANITIZE_NUMBER_INT,
            'gateway_response_code' => FILTER_SANITIZE_SPECIAL_CHARS,
            'avs_response_code' => FILTER_SANITIZE_SPECIAL_CHARS,
            'avs_response_message' => FILTER_SANITIZE_SPECIAL_CHARS,
            'cvn_response_code' => FILTER_SANITIZE_SPECIAL_CHARS,
            'cvn_response_message' => FILTER_SANITIZE_SPECIAL_CHARS,
        ];

        foreach ($expectedParams as $param => $filter) {
            if (isset($params[$param])) {
                $value = filter_var($params[$param], $filter);
                $sanitized[$param] = $value !== false ? (string)$value : '';
            } else {
                $sanitized[$param] = '';
            }
        }

        return $sanitized;
    }

    /**
     * Process HPP return parameters
     *
     * @param array<string, string> $params Sanitized GET parameters
     * @return array<string, mixed> Processed transaction data
     */
    private function processHppReturn(array $params): array
    {
        $transactionData = [
            'id' => $params['txn_id'] ?: 'Unknown',
            'reference' => $params['reference'] ?: '',
            'status' => 'unknown',
            'amount' => $params['amount'] ?: '0.00',
            'currency' => $params['currency'] ?: 'USD',
            'timestamp' => date('c'),
            'type' => 'payment',
            'card' => [
                'type' => $params['card_type'] ?: 'Unknown',
                'last4' => $params['card_last4'] ?: '0000',
                'exp_month' => $params['card_exp_month'] ?: '',
                'exp_year' => $params['card_exp_year'] ?: ''
            ],
            'response' => [
                'code' => $params['response_code'] ?: 'Unknown',
                'message' => $params['response_message'] ?: 'Transaction processed'
            ],
            'gateway_response_code' => $params['gateway_response_code'] ?: '',
            'avs' => [
                'code' => $params['avs_response_code'] ?: '',
                'message' => $params['avs_response_message'] ?: ''
            ],
            'cvv' => [
                'code' => $params['cvn_response_code'] ?: '',
                'message' => $params['cvn_response_message'] ?: ''
            ]
        ];

        // Determine transaction status based on response codes
        $responseCode = $params['response_code'] ?? '';
        $transactionData['status'] = match ($responseCode) {
            '00' => 'approved',
            '51', '05', '61' => 'declined',
            default => $responseCode ? 'error' : 'unknown'
        };

        return $transactionData;
    }

    /**
     * Store transaction data safely
     *
     * @param array<string, mixed> $transactionData Transaction data to store
     * @return void
     */
    private function storeTransaction(array $transactionData): void
    {
        try {
            $reporter = new TransactionReporter();
            $reporter->recordTransaction($transactionData);
        } catch (Throwable $e) {
            error_log("Failed to store HPP transaction: {$e->getMessage()}");
        }
    }

    /**
     * Generate success page HTML with proper escaping
     *
     * @param array<string, mixed> $transactionData Transaction data
     * @return string HTML content
     */
    private function generateSuccessPage(array $transactionData): string
    {
        $statusColor = match ($transactionData['status']) {
            'approved' => '#10B981',
            'declined' => '#EF4444',
            default => '#F59E0B'
        };
        
        $statusIcon = match ($transactionData['status']) {
            'approved' => '✅',
            'declined' => '❌', 
            default => '⚠️'
        };

        $statusMessage = match ($transactionData['status']) {
            'approved' => 'Payment Successful',
            'declined' => 'Payment Declined',
            default => 'Payment Error'
        };

        // Escape data for HTML output
        $safeData = [
            'id' => htmlspecialchars($transactionData['id'], ENT_QUOTES, 'UTF-8'),
            'reference' => htmlspecialchars($transactionData['reference'], ENT_QUOTES, 'UTF-8'),
            'amount' => htmlspecialchars($transactionData['amount'], ENT_QUOTES, 'UTF-8'),
            'currency' => htmlspecialchars($transactionData['currency'], ENT_QUOTES, 'UTF-8'),
            'status' => htmlspecialchars($transactionData['status'], ENT_QUOTES, 'UTF-8'),
            'card_type' => htmlspecialchars($transactionData['card']['type'], ENT_QUOTES, 'UTF-8'),
            'card_last4' => htmlspecialchars($transactionData['card']['last4'], ENT_QUOTES, 'UTF-8'),
            'response_code' => htmlspecialchars($transactionData['response']['code'], ENT_QUOTES, 'UTF-8'),
            'response_message' => htmlspecialchars($transactionData['response']['message'], ENT_QUOTES, 'UTF-8'),
            'timestamp' => htmlspecialchars($transactionData['timestamp'], ENT_QUOTES, 'UTF-8'),
        ];

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Result - Global Payments</title>
    <link rel="stylesheet" href="css/index.css">
    <style>
        .result-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .status-header {
            color: {$statusColor};
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        .transaction-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #495057;
        }
        .detail-value {
            color: #212529;
            font-family: monospace;
            word-break: break-all;
        }
        .action-buttons {
            margin-top: 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #4285f4;
            color: white;
        }
        .btn-primary:hover {
            background: #3367d6;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="result-container">
            <div class="status-header">
                {$statusIcon} {$statusMessage}
            </div>
            
            <div class="transaction-details">
                <h3>Transaction Details</h3>
                <div class="detail-row">
                    <span class="detail-label">Transaction ID:</span>
                    <span class="detail-value">{$safeData['id']}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Reference:</span>
                    <span class="detail-value">{$safeData['reference']}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Amount:</span>
                    <span class="detail-value">\${$safeData['amount']} {$safeData['currency']}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Card:</span>
                    <span class="detail-value">{$safeData['card_type']} ****{$safeData['card_last4']}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value" style="color: {$statusColor}; text-transform: uppercase;">{$safeData['status']}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Response:</span>
                    <span class="detail-value">{$safeData['response_code']} - {$safeData['response_message']}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date & Time:</span>
                    <span class="detail-value">{$safeData['timestamp']}</span>
                </div>
            </div>

            <div class="action-buttons">
                <a href="dashboard.html" class="btn btn-primary">
                    📊 View All Transactions
                </a>
                <a href="index.html" class="btn btn-secondary">
                    🔙 Back to Home
                </a>
            </div>
        </div>
    </div>

    <script>
        // Auto-redirect to dashboard after 10 seconds for successful payments
        if ('{$safeData['status']}' === 'approved') {
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 10000);
        }
    </script>
</body>
</html>
HTML;
    }

    /**
     * Generate error page HTML
     *
     * @param string $errorMessage Error message to display
     * @return string HTML content
     */
    private function generateErrorPage(string $errorMessage): string
    {
        $safeMessage = htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Error - Global Payments</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="container">
        <div class="result-container result-error">
            <h2>⚠️ Payment Processing Error</h2>
            <p>{$safeMessage}</p>
            <p>Please contact support if you believe this is an error.</p>
            <div style="margin-top: 20px;">
                <a href="dashboard.html" class="btn btn-primary">View Transactions</a>
                <a href="index.html" class="btn btn-secondary">Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Process the HPP return request
     *
     * @return never
     */
    public function processReturn(): never
    {
        try {
            // Configure SDK
            $this->configureSdk();

            // Sanitize and process return parameters
            $sanitizedParams = $this->sanitizeParams($_GET);
            $transactionData = $this->processHppReturn($sanitizedParams);

            // Store transaction
            $this->storeTransaction($transactionData);

            // Generate and display result page
            echo $this->generateSuccessPage($transactionData);
            exit;

        } catch (Throwable $e) {
            // Log error securely
            error_log("HPP return processing error: {$e->getMessage()}");
            
            // Display error page
            echo $this->generateErrorPage('There was an error processing your payment return.');
            exit;
        }
    }
}

// Main execution
try {
    $handler = new HppReturnHandler();
    $handler->processReturn();
} catch (Throwable $e) {
    error_log("Fatal error in HPP return handler: {$e->getMessage()}");
    http_response_code(500);
    echo "<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Server Error</h1><p>Please try again later.</p></body></html>";
}