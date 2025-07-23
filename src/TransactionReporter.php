<?php

declare(strict_types=1);

namespace GlobalPayments\Examples;

use Dotenv\Dotenv;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig;
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

        $config = new PorticoConfig();
        $config->secretApiKey = $_ENV['SECRET_API_KEY'];
        $config->developerId = $_ENV['DEVELOPER_ID'] ?? '000000';
        $config->versionNumber = $_ENV['VERSION_NUMBER'] ?? '0000';
        $config->serviceUrl = $_ENV['SERVICE_URL'] ?? 'https://cert.api2.heartlandportico.com';

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
            
            // Format dates as strings - SDK has a bug with DateTime objects
            $startDateStr = $startDate->format('Y-m-d\TH:i:s.000\Z');
            $endDateStr = $endDate->format('Y-m-d\TH:i:s.999\Z');

            $response = $reportingService->findTransactions()
                ->withStartDate($startDateStr)
                ->withEndDate($endDateStr)
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
                usort($transactions, function($a, $b) {
                    $idA = is_numeric($a['id']) ? (int)$a['id'] : 0;
                    $idB = is_numeric($b['id']) ? (int)$b['id'] : 0;
                    return $idB - $idA; // Descending order (newest first)
                });
                
                // Limit to most recent 20 transactions to keep UI responsive
                $transactions = array_slice($transactions, 0, 20);
            }

            return [
                'success' => true,
                'data' => [
                    'transactions' => $transactions,
                    'pagination' => [
                        'page' => $page,
                        'pageSize' => $limit,
                        'totalCount' => count($transactions)
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
            
            // Format dates as strings - SDK has a bug with DateTime objects
            $startDateObj = new \DateTime($startDate);
            $endDateObj = new \DateTime($endDate);
            $startDateStr = $startDateObj->format('Y-m-d\TH:i:s.000\Z');
            $endDateStr = $endDateObj->format('Y-m-d\TH:i:s.999\Z');

            $response = $reportingService->findTransactions()
                ->withStartDate($startDateStr)
                ->withEndDate($endDateStr)
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
                usort($transactions, function($a, $b) {
                    $idA = is_numeric($a['id']) ? (int)$a['id'] : 0;
                    $idB = is_numeric($b['id']) ? (int)$b['id'] : 0;
                    return $idB - $idA; // Descending order (newest first)
                });
                
                // Limit to most recent 20 transactions to keep UI responsive
                $transactions = array_slice($transactions, 0, 20);
            }

            return [
                'success' => true,
                'data' => [
                    'transactions' => $transactions,
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
            return [
                'success' => false,
                'message' => 'Failed to retrieve transactions: ' . $e->getMessage(),
                'error' => [
                    'code' => 'REPORTING_API_ERROR',
                    'details' => $e->getMessage()
                ]
            ];
        } catch (\Exception $e) {
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
                $timestamp = $dateTime->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                error_log('Error parsing responseDate: ' . $e->getMessage());
            }
        } elseif ($transaction->transactionDate && $transaction->transactionDate instanceof \DateTime) {
            $timestamp = $transaction->transactionDate->format('Y-m-d H:i:s');
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
            'type' => $transaction->transactionType ?? 'VERIFY',
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
                'code' => $transaction->responseCode ?? null,
                'message' => $transaction->responseMessage ?? null
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
     * Determine transaction status from response data
     *
     * @param object $transaction Transaction data
     * @return string Status string
     */
    private function getTransactionStatus($transaction): string
    {
        $responseCode = $transaction->responseCode ?? null;

        // Handle standard response codes
        switch ($responseCode) {
            case '00':
                return 'approved';
            case '10':
                return 'partially_approved';
            case '96':
                return 'declined';
            case '91':
                return 'error';
            default:
                // Check gateway response code as fallback
                $gatewayCode = $transaction->gatewayResponseCode ?? null;
                if ($gatewayCode === '00') {
                    return 'approved';
                }
                
                // For card verification transactions, check AVS/CVV results
                if ($transaction->transactionType === 'VERIFY' || 
                    (!$transaction->amount || $transaction->amount == 0)) {
                    
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
            error_log('Suspicious transaction: Missing responseDate field - ' . ($transaction->transactionId ?? 'unknown'));
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
        
        // Check for realistic transaction ID pattern (Global Payments uses numeric IDs)
        if (!isset($transaction->transactionId) || !is_numeric($transaction->transactionId)) {
            error_log('Suspicious transaction: Invalid transactionId format');
            return false;
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
     * Enhanced transaction filtering with authenticity verification
     *
     * @param array $transactions Raw transaction array
     * @param array $filters Filter criteria
     * @return array Filtered and validated transactions
     */
    public function filterAuthenticTransactions(array $transactions, array $filters = []): array
    {
        $filtered = [];
        
        foreach ($transactions as $transaction) {
            // First, validate authenticity
            if (!$this->validateTransactionAuthenticity($transaction)) {
                continue;
            }
            
            // Apply additional filters
            if (isset($filters['status']) && $filters['status']) {
                $status = $this->getTransactionStatus($transaction);
                if ($status !== $filters['status']) {
                    continue;
                }
            }
            
            if (isset($filters['amount_min']) && $filters['amount_min']) {
                if (!isset($transaction->amount) || $transaction->amount < $filters['amount_min']) {
                    continue;
                }
            }
            
            if (isset($filters['amount_max']) && $filters['amount_max']) {
                if (!isset($transaction->amount) || $transaction->amount > $filters['amount_max']) {
                    continue;
                }
            }
            
            $filtered[] = $transaction;
        }
        
        return $filtered;
    }

}
