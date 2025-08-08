<?php

declare(strict_types=1);

namespace GlobalPayments\Examples;

use Dotenv\Dotenv;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\ServiceConfigs\Gateways\GpApiConfig;
use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\Channel;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\Services\ReportingService;
use GlobalPayments\Api\Utils\Logging\Logger;
use GlobalPayments\Api\Utils\Logging\SampleRequestLogger;

/**
 * Transaction Reporter - Handles Global Payments Reporting API integration
 *
 * This class provides methods to retrieve transaction data from the Global Payments
 * Reporting API for dashboard display purposes.
 *
 * @category  Reporting
 * @package   GlobalPayments_Examples
 * @author    Global Payments
 * @license   MIT License
 */
class TransactionReporter
{
    private bool $isConfigured = false;

    /**
     * Configure the Global Payments SDK for reporting
     *
     * @return void
     * @throws ApiException If configuration fails
     */
    public function configureSdk(): void
    {
        if ($this->isConfigured) {
            return;
        }

        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();

        if (empty($_ENV['GP_API_APP_ID']) || empty($_ENV['GP_API_APP_KEY'])) {
            throw new ApiException('GP-API credentials not configured in environment');
        }

        $config = new GpApiConfig();
        $config->appId = $_ENV['GP_API_APP_ID'];
        $config->appKey = $_ENV['GP_API_APP_KEY'];
        $config->environment = $_ENV['GP_API_ENVIRONMENT'] === 'production'
            ? Environment::PRODUCTION
            : Environment::TEST;
        $config->channel = Channel::CardNotPresent;

        // Enable request logging for debugging (optional)
        if (isset($_ENV['ENABLE_REQUEST_LOGGING']) && $_ENV['ENABLE_REQUEST_LOGGING'] === 'true') {
            $config->requestLogger = new SampleRequestLogger(new Logger(__DIR__ . '/../logs'));
        }

        ServicesContainer::configureService($config);
        $this->isConfigured = true;
    }

    /**
     * Get recent transactions for dashboard display
     *
     * @param int $limit Maximum number of transactions to retrieve
     * @param int $page Page number for pagination
     * @return array Transaction data formatted for dashboard
     * @throws ApiException If API call fails
     */
    public function getRecentTransactions(int $limit = 50, int $page = 1): array
    {
        $this->configureSdk();

        try {
            $reportingService = new ReportingService();
            // Default to last 3 days to balance freshness with finding transactions
            $startDate = new \DateTime('3 days ago');
            $endDate = new \DateTime('now');

            // Format dates as strings - SDK has internal issues with DateTime objects
            $startDateStr = $startDate->format('Y-m-d\TH:i:s.000\Z');
            $endDateStr = $endDate->format('Y-m-d\TH:i:s.999\Z');

            $response = $reportingService->findTransactions()
                ->withStartDate($startDate)
                ->withEndDate($endDate)
                ->execute();

            $transactions = [];
            if ($response && is_array($response)) {
                foreach ($response as $transaction) {
                    // Validate transaction authenticity before adding
                    if ($this->validateTransactionAuthenticity($transaction)) {
                        $transactions[] = $this->formatTransactionForDashboard($transaction);
                    }
                }

                // Sort transactions by ID (newer IDs are typically higher numbers) - descending
                usort($transactions, function ($a, $b) {
                    $idA = is_numeric($a['id']) ? (int)$a['id'] : 0;
                    $idB = is_numeric($b['id']) ? (int)$b['id'] : 0;
                    return $idB - $idA; // Descending order (newest first)
                });

                // Apply user-specified limit with performance cap
                $actualLimit = min($limit, 100); // Cap at 100 for performance
                $totalResults = count($transactions);
                $transactions = array_slice($transactions, 0, $actualLimit);
            }

            return [
                'success' => true,
                'data' => [
                    'transactions' => $transactions,
                    'pagination' => [
                        'page' => $page,
                        'pageSize' => $actualLimit ?? $limit,
                        'totalCount' => $totalResults ?? count($transactions)
                    ],
                    'source' => 'global_payments_sandbox_api',
                    'authenticity' => 'verified'
                ],
                'message' => 'Authenticated sandbox transactions retrieved successfully'
            ];
        } catch (ApiException $e) {
            $this->logError('API Exception in getRecentTransactions', $e);
            return [
                'success' => false,
                'message' => 'Failed to retrieve transactions from Global Payments API: ' . $e->getMessage(),
                'error' => [
                    'code' => 'REPORTING_API_ERROR',
                    'details' => $e->getMessage(),
                    'retry' => true
                ]
            ];
        } catch (\Exception $e) {
            $this->logError('General Exception in getRecentTransactions', $e);
            return [
                'success' => false,
                'message' => 'Failed to retrieve transactions: ' . $e->getMessage(),
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'details' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Get transactions filtered by date range
     *
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @param int $limit Maximum number of transactions
     * @return array Transaction data
     * @throws ApiException If API call fails
     */
    public function getTransactionsByDateRange(string $startDate, string $endDate, int $limit = 50): array
    {
        $this->configureSdk();

        try {
            $reportingService = new ReportingService();

            // Format dates as strings - SDK has internal issues with DateTime objects
            $startDateObj = new \DateTime($startDate);
            $endDateObj = new \DateTime($endDate);
            $startDateStr = $startDateObj->format('Y-m-d\TH:i:s.000\Z');
            $endDateStr = $endDateObj->format('Y-m-d\TH:i:s.999\Z');

            $response = $reportingService->findTransactions()
                ->withStartDate($startDate)
                ->withEndDate($endDate)
                ->execute();

            $transactions = [];
            if ($response && is_array($response)) {
                foreach ($response as $transaction) {
                    // Apply same authenticity validation as getRecentTransactions
                    if ($this->validateTransactionAuthenticity($transaction)) {
                        $transactions[] = $this->formatTransactionForDashboard($transaction);
                    }
                }

                // Apply same sorting and limiting as getRecentTransactions
                usort($transactions, function ($a, $b) {
                    $idA = is_numeric($a['id']) ? (int)$a['id'] : 0;
                    $idB = is_numeric($b['id']) ? (int)$b['id'] : 0;
                    return $idB - $idA; // Descending order (newest first)
                });

                // Apply user-specified limit with performance cap
                $actualLimit = min($limit, 100); // Cap at 100 for performance
                $totalResults = count($transactions);
                $transactions = array_slice($transactions, 0, $actualLimit);
            }

            return [
                'success' => true,
                'data' => [
                    'transactions' => $transactions,
                    'pagination' => [
                        'totalCount' => $totalResults ?? count($transactions),
                        'pageSize' => $actualLimit ?? $limit
                    ],
                    'dateRange' => [
                        'startDate' => $startDate,
                        'endDate' => $endDate
                    ],
                    'source' => 'global_payments_sandbox_api',
                    'authenticity' => 'verified'
                ],
                'message' => 'Authenticated sandbox transactions retrieved successfully'
            ];
        } catch (ApiException $e) {
            $this->logError('API Exception in getTransactionsByDateRange', $e);
            return [
                'success' => false,
                'message' => 'Failed to retrieve transactions: ' . $e->getMessage(),
                'error' => [
                    'code' => 'REPORTING_API_ERROR',
                    'details' => $e->getMessage(),
                    'retry' => true
                ]
            ];
        } catch (\Exception $e) {
            $this->logError('General Exception in getTransactionsByDateRange', $e);
            return [
                'success' => false,
                'message' => 'Failed to retrieve transactions: ' . $e->getMessage(),
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'details' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Get transaction details by transaction ID
     *
     * @param string $transactionId The transaction ID to retrieve
     * @return array Transaction details
     * @throws ApiException If API call fails
     */
    public function getTransactionDetails(string $transactionId): array
    {
        $this->configureSdk();

        try {
            $response = ReportingService::transactionDetail($transactionId)
                ->execute();

            if (!$response) {
                return [
                    'success' => false,
                    'message' => 'Transaction not found',
                    'error' => [
                        'code' => 'TRANSACTION_NOT_FOUND',
                        'details' => "Transaction with ID '{$transactionId}' not found"
                    ]
                ];
            }

            return [
                'success' => true,
                'data' => $this->formatTransactionForDashboard($response),
                'message' => 'Transaction details retrieved successfully'
            ];
        } catch (ApiException $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve transaction details: ' . $e->getMessage(),
                'error' => [
                    'code' => 'REPORTING_API_ERROR',
                    'details' => $e->getMessage()
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve transaction details: ' . $e->getMessage(),
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'details' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Format transaction data for dashboard display
     *
     * @param object $transaction Raw transaction data from API
     * @return array Formatted transaction data
     */
    private function formatTransactionForDashboard($transaction): array
    {
        // Use transaction ID if available, otherwise use reference number as fallback
        $displayId = $transaction->transactionId ?? $transaction->referenceNumber ?? null;

        // Determine amount - for card verification, show as verification instead of $0
        $amount = isset($transaction->amount) && $transaction->amount > 0 ?
                  $transaction->amount : 'VERIFY';

        // Get the actual transaction timestamp from Global Payments API responseDate field
        $timestamp = null;

        // Priority order for timestamp fields from Global Payments API
        if (isset($transaction->responseDate) && !empty($transaction->responseDate)) {
            // responseDate is in ISO format like "2025-07-23T12:12:55.52Z"
            try {
                $dateTime = new \DateTime($transaction->responseDate);
                // Preserve exact timestamp with seconds precision for accuracy
                $timestamp = $dateTime->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                error_log('Error parsing responseDate: ' . $e->getMessage());
            }
        } elseif (isset($transaction->transactionDate) && $transaction->transactionDate instanceof \DateTime) {
            $timestamp = $transaction->transactionDate->format('Y-m-d H:i:s');
        } elseif (isset($transaction->transactionDate) && !empty($transaction->transactionDate)) {
            // Handle case where transactionDate is a string
            try {
                $dateTime = new \DateTime($transaction->transactionDate);
                $timestamp = $dateTime->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                error_log('Error parsing transactionDate string: ' . $e->getMessage());
            }
        } elseif (isset($transaction->transactionLocalDate) && !empty($transaction->transactionLocalDate)) {
            try {
                $dateTime = new \DateTime($transaction->transactionLocalDate);
                $timestamp = $dateTime->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                error_log('Error parsing transactionLocalDate: ' . $e->getMessage());
            }
        }

        return [
            'id' => $displayId,
            'amount' => $amount,
            'currency' => $transaction->currency ?? 'USD',
            'status' => $this->getTransactionStatus($transaction),
            'type' => $this->getTransactionType($transaction, $amount),
            'timestamp' => $timestamp,
            'card' => [
                'type' => $transaction->cardType ?? null,
                'last4' => $transaction->maskedCardNumber ?
                    substr($transaction->maskedCardNumber, -4) :
                    (isset($transaction->cardNumber) ? substr($transaction->cardNumber, -4) : null),
                'exp_month' => $transaction->cardExpMonth ?? null,
                'exp_year' => $transaction->cardExpYear ?? null
            ],
            'response' => [
                'code' => $this->getResponseCode($transaction),
                'message' => $this->getResponseMessage($transaction)
            ],
            'avs' => [
                'code' => $transaction->avsResponseCode ?? null,
                'message' => $transaction->avsResponseMessage ?? null
            ],
            'cvv' => [
                'code' => $transaction->cvnResponseCode ?? null,
                'message' => $transaction->cvnResponseMessage ?? null
            ],
            'reference' => $transaction->referenceNumber ?? null,
            'batch_id' => $transaction->batchId ?? null,
            'gateway_response_code' => $transaction->gatewayResponseCode ?? null,
            'gateway_response_message' => $transaction->gatewayResponseMessage ?? null
        ];
    }


    /**
     * Determine transaction type based on API data and amount
     *
     * @param object $transaction Transaction data
     * @param mixed $amount Transaction amount
     * @return string Transaction type
     */
    private function getTransactionType($transaction, $amount): string
    {
        // Check if it's a verification transaction (amount is 0 or VERIFY)
        if ($amount === 'VERIFY' || ($amount === 0 || $amount === '0' || $amount === '0.00')) {
            return 'verification';
        }

        // Check Global Payments service names to determine type
        $serviceName = $transaction->serviceName ?? '';

        // Map Global Payments service names to our transaction types
        switch ($serviceName) {
            case 'CreditSale':
            case 'CreditAuth':
            case 'CreditCapture':
                return 'payment';
            case 'CreditAccountVerify':
            case 'Tokenize':
                return 'verification';
            case 'CreditVoid':
                return 'void';
            case 'CreditReturn':
            case 'DebitReturn':
                return 'refund';
            case 'DebitSale':
                return 'payment';
            default:
                // Fallback: if amount > 0, it's likely a payment
                if (is_numeric($amount) && (float)$amount > 0) {
                    return 'payment';
                }
                return 'verification';
        }
    }

    /**
     * Get response code with fallbacks
     *
     * @param object $transaction Transaction data
     * @return string|null Response code
     */
    private function getResponseCode($transaction): ?string
    {
        // Try multiple possible response code fields
        return $transaction->responseCode ??
               $transaction->gatewayResponseCode ??
               $transaction->authCode ??
               ($transaction->transactionStatus === 'A' ? '00' : null);
    }

    /**
     * Get response message with fallbacks
     *
     * @param object $transaction Transaction data
     * @return string|null Response message
     */
    private function getResponseMessage($transaction): ?string
    {
        // Try multiple possible response message fields
        return $transaction->responseMessage ??
               $transaction->gatewayResponseMessage ??
               ($transaction->transactionStatus === 'A' ? 'Approved' :
                ($transaction->transactionStatus === 'D' ? 'Declined' : null));
    }

    /**
     * Determine transaction status from response data
     *
     * @param object $transaction Transaction data
     * @return string Status string
     */
    private function getTransactionStatus($transaction): string
    {
        $responseCode = $transaction->responseCode ?? null;

        // Handle GP-API and legacy response codes
        switch ($responseCode) {
            case '00':
            case 'SUCCESS':
            case 'APPROVED':
                return 'approved';
            case '10':
            case 'PARTIAL':
                return 'partially_approved';
            case '96':
            case 'DECLINED':
                return 'declined';
            case '91':
            case 'ERROR':
            case 'FAILED':
                return 'error';
            default:
                // Check gateway response code as fallback
                $gatewayCode = $transaction->gatewayResponseCode ?? null;
                if ($gatewayCode === '00' || $gatewayCode === 'SUCCESS') {
                    return 'approved';
                }

                // For card verification transactions, check AVS/CVV results
                if (
                    (isset($transaction->transactionType) && $transaction->transactionType === 'VERIFY') ||
                    (!isset($transaction->amount) || !$transaction->amount || $transaction->amount == 0)
                ) {
                    // Check if verification was successful based on AVS/CVV
                    $avsCode = $transaction->avsResponseCode ?? null;
                    $cvvCode = $transaction->cvnResponseCode ?? null;

                    // If we have positive CVV match, consider it approved verification
                    if ($cvvCode === 'M' || $avsCode === '0') {
                        return 'approved';
                    }
                }

                return $responseCode ? 'declined' : 'unknown';
        }
    }

    /**
     * Validate transaction authenticity against Global Payments requirements
     *
     * @param object $transaction Transaction data from API
     * @return bool True if transaction is authentic, false if mock/invalid
     */
    private function validateTransactionAuthenticity($transaction): bool
    {
        // Global Payments sometimes returns data with missing transactionId but valid referenceNumber
        $hasValidId = (isset($transaction->transactionId) && !empty($transaction->transactionId)) ||
                     (isset($transaction->referenceNumber) && !empty($transaction->referenceNumber));

        if (!$hasValidId) {
            return false;
        }

        // Check for obvious mock data patterns first
        $identifier = $transaction->transactionId ?? $transaction->referenceNumber ?? '';
        $mockIndicators = ['MOCK', 'TEST', 'DEMO', 'SAMPLE', '0000000000', '1111111111'];
        foreach ($mockIndicators as $indicator) {
            if (stripos($identifier, $indicator) !== false) {
                return false;
            }
        }

        // Enhanced authenticity checks for Global Payments API data

        // Check for required Global Payments specific fields
        if (!isset($transaction->responseDate) || empty($transaction->responseDate)) {
            error_log(
                'Suspicious transaction: Missing responseDate field - ' .
                ($transaction->transactionId ?? 'unknown')
            );
            return false;
        }

        // Check for Global Payments specific service names (expanded list)
        $validServices = [
            'CreditSale', 'CreditAuth', 'CreditCapture', 'CreditVoid', 'CreditAccountVerify',
            'CreditCPCEdit', 'CheckSale', 'CheckVoid', 'CheckQuery', 'RecurringBilling',
            'RecurringBillingAuth', 'Tokenize', 'DebitSale', 'DebitReturn'
        ];
        if (!isset($transaction->serviceName) || !in_array($transaction->serviceName, $validServices)) {
            error_log('Suspicious transaction: Invalid serviceName - ' . ($transaction->serviceName ?? 'unknown'));
            return false;
        }

        // Check for Global Payments username pattern (should not be generic)
        if (!isset($transaction->username) || empty($transaction->username)) {
            error_log('Suspicious transaction: Missing username field');
            return false;
        }

        // Verify responseDate format matches Global Payments ISO format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z$/', $transaction->responseDate)) {
            error_log('Suspicious transaction: Invalid responseDate format - ' . $transaction->responseDate);
            return false;
        }

        // Check for realistic transaction ID pattern (Global Payments uses numeric or alphanumeric IDs)
        if (isset($transaction->transactionId) && !empty($transaction->transactionId)) {
            // Allow numeric or reasonable alphanumeric transaction IDs
            if (!preg_match('/^[a-zA-Z0-9\-_]{5,}$/', $transaction->transactionId)) {
                error_log('Suspicious transaction: Invalid transactionId format - ' . $transaction->transactionId);
                return false;
            }
        }

        return true;
    }

    /**
     * Log errors for debugging and monitoring
     *
     * @param string $context Context/location of the error
     * @param \Exception $exception The exception that occurred
     * @return void
     */
    private function logError(string $context, \Exception $exception): void
    {
        $logMessage = sprintf(
            '[%s] %s: %s (File: %s, Line: %d)',
            date('Y-m-d H:i:s'),
            $context,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        error_log($logMessage, 3, __DIR__ . '/../logs/transaction-errors.log');

        // Also log to system error log as fallback
        error_log('TransactionReporter Error: ' . $logMessage);
    }

    /**
     * Record transaction data locally for HPP transactions
     *
     * @param array $transactionData Transaction data to record
     * @return void
     * @throws \Exception If recording fails
     */
    public function recordTransaction(array $transactionData): void
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Store in daily transaction log
        $logFile = $logDir . '/transactions-' . date('Y-m-d') . '.json';
        $transactions = [];

        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $transactions = json_decode($content, true) ?: [];
        }

        // Add timestamp if not present
        if (!isset($transactionData['timestamp'])) {
            $transactionData['timestamp'] = date('c');
        }

        // Ensure required fields are present
        $transactionData = array_merge([
            'id' => 'Unknown',
            'reference' => '',
            'status' => 'unknown',
            'amount' => '0.00',
            'currency' => 'USD',
            'type' => 'verification',
            'card' => [
                'type' => 'Unknown',
                'last4' => '0000',
                'exp_month' => '',
                'exp_year' => ''
            ],
            'response' => [
                'code' => 'Unknown',
                'message' => 'Transaction processed'
            ],
            'gateway_response_code' => '',
            'avs' => [
                'code' => '',
                'message' => ''
            ],
            'cvv' => [
                'code' => '',
                'message' => ''
            ]
        ], $transactionData);

        $transactions[] = $transactionData;

        // Keep only the most recent transactions (last 1000 per day)
        if (count($transactions) > 1000) {
            $transactions = array_slice($transactions, -1000);
        }

        file_put_contents($logFile, json_encode($transactions, JSON_PRETTY_PRINT));

        // Also maintain a master transaction log
        $masterLogFile = $logDir . '/all-transactions.json';
        $allTransactions = [];

        if (file_exists($masterLogFile)) {
            $content = file_get_contents($masterLogFile);
            $allTransactions = json_decode($content, true) ?: [];
        }

        $allTransactions[] = $transactionData;

        // Keep only the most recent 5000 transactions in master log
        if (count($allTransactions) > 5000) {
            $allTransactions = array_slice($allTransactions, -5000);
        }

        file_put_contents($masterLogFile, json_encode($allTransactions, JSON_PRETTY_PRINT));
    }

    /**
     * Get local transaction data for dashboard
     *
     * @param string|null $startDate Start date filter
     * @param string|null $endDate End date filter
     * @param int $limit Maximum number of transactions to return
     * @return array Local transaction data
     */
    public function getLocalTransactions(?string $startDate = null, ?string $endDate = null, int $limit = 100): array
    {
        $logDir = __DIR__ . '/../logs';
        $masterLogFile = $logDir . '/all-transactions.json';

        if (!file_exists($masterLogFile)) {
            return [];
        }

        $content = file_get_contents($masterLogFile);
        $transactions = json_decode($content, true) ?: [];

        // Filter out test/mock transactions - allow genuine Portico API transactions and verification transactions
        $transactions = array_filter($transactions, function ($transaction) {
            $transactionId = $transaction['id'] ?? '';
            $transactionType = $transaction['type'] ?? '';

            // Allow verification transactions (they have alphanumeric IDs starting with VER_)
            if ($transactionType === 'verification' && strpos($transactionId, 'VER_') === 0) {
                return true;
            }

            // Portico only accepts numeric transaction IDs
            // Filter out alphanumeric test IDs like LIMIT_TEST_*, INTEGRATION_*, etc.
            if (!is_numeric($transactionId)) {
                return false;
            }

            // Additional validation for genuine transactions
            $mockIndicators = ['MOCK', 'TEST', 'DEMO', 'SAMPLE', 'INTEGRATION', 'LIMIT'];
            foreach ($mockIndicators as $indicator) {
                if (stripos($transactionId, $indicator) !== false) {
                    return false;
                }
            }

            return true;
        });

        // Apply date filters if provided
        if ($startDate || $endDate) {
            $transactions = array_filter($transactions, function ($transaction) use ($startDate, $endDate) {
                $transactionDate = $transaction['timestamp'] ?? '';
                if (!$transactionDate) {
                    return false;
                }

                $txnTime = strtotime($transactionDate);

                if ($startDate && $txnTime < strtotime($startDate)) {
                    return false;
                }

                if ($endDate && $txnTime > strtotime($endDate . ' 23:59:59')) {
                    return false;
                }

                return true;
            });
        }

        // Sort by timestamp descending (newest first)
        usort($transactions, function ($a, $b) {
            $timeA = strtotime($a['timestamp'] ?? '');
            $timeB = strtotime($b['timestamp'] ?? '');
            return $timeB - $timeA;
        });

        // Apply limit
        if ($limit > 0) {
            $transactions = array_slice($transactions, 0, $limit);
        }

        return [
            'success' => true,
            'data' => [
                'transactions' => $transactions,
                'total_count' => count($transactions),
                'source' => 'local_storage'
            ],
            'message' => 'Local transactions retrieved successfully'
        ];
    }
}
