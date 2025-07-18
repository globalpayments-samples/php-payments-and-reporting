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
            $startDate = new \DateTime('30 days ago');
            $endDate = new \DateTime();
            
            // Format dates for API using UTC timezone format required by Global Payments
            $startDateStr = gmdate('Y-m-d\TH:i:s.00\Z', $startDate->getTimestamp());
            $endDateStr = gmdate('Y-m-d\TH:i:s.00\Z', $endDate->getTimestamp());

            $response = $reportingService->findTransactions()
                ->withStartDate($startDateStr)
                ->withEndDate($endDateStr)
                ->execute();

            $transactions = [];
            if ($response && is_array($response)) {
                foreach ($response as $transaction) {
                    $transactions[] = $this->formatTransactionForDashboard($transaction);
                }
            }

            return [
                'success' => true,
                'data' => [
                    'transactions' => $transactions,
                    'pagination' => [
                        'page' => $page,
                        'pageSize' => $limit,
                        'totalCount' => count($transactions)
                    ]
                ],
                'message' => 'Transactions retrieved successfully'
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
        } catch (Exception $e) {
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
            
            // Format dates for API using UTC timezone format required by Global Payments
            $startDateObj = new \DateTime($startDate);
            $endDateObj = new \DateTime($endDate);
            $startDateStr = gmdate('Y-m-d\TH:i:s.00\Z', $startDateObj->getTimestamp());
            $endDateStr = gmdate('Y-m-d\TH:i:s.00\Z', $endDateObj->getTimestamp());

            $response = $reportingService->findTransactions()
                ->withStartDate($startDateStr)
                ->withEndDate($endDateStr)
                ->execute();

            $transactions = [];
            if ($response && is_array($response)) {
                foreach ($response as $transaction) {
                    $transactions[] = $this->formatTransactionForDashboard($transaction);
                }
            }

            return [
                'success' => true,
                'data' => [
                    'transactions' => $transactions,
                    'dateRange' => [
                        'startDate' => $startDate,
                        'endDate' => $endDate
                    ]
                ],
                'message' => 'Transactions retrieved successfully'
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
        } catch (Exception $e) {
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
        } catch (Exception $e) {
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
        return [
            'id' => $transaction->transactionId ?? null,
            'amount' => $transaction->amount ?? null,
            'currency' => $transaction->currency ?? 'USD',
            'status' => $this->getTransactionStatus($transaction),
            'type' => $transaction->transactionType ?? 'VERIFY',
            'timestamp' => $transaction->transactionDate ?
                $transaction->transactionDate->format('Y-m-d H:i:s') : null,
            'card' => [
                'type' => $transaction->cardType ?? null,
                'last4' => $transaction->maskedCardNumber ?
                    substr($transaction->maskedCardNumber, -4) : null,
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
                return $responseCode ? 'declined' : 'unknown';
        }
    }

}
