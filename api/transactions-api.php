<?php

declare(strict_types=1);

/**
 * Transaction Dashboard API Endpoints
 *
 * This script provides REST API endpoints for retrieving transaction data
 * from the Global Payments Reporting API for dashboard display.
 *
 * Endpoints:
 * - GET /transactions-api.php - Get recent transactions
 * - GET /transactions-api.php?start_date=X&end_date=Y - Get transactions by date range
 * - GET /transactions-api.php?transaction_id=X - Get specific transaction details
 *
 * PHP version 8.0 or higher
 *
 * @category  API
 * @package   GlobalPayments_Examples
 * @author    Global Payments
 * @license   MIT License
 */

require_once 'vendor/autoload.php';

use GlobalPayments\Examples\TransactionReporter;

// Security headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Validate and sanitize query parameters
 *
 * @param array $params Query parameters
 * @return array Sanitized parameters
 */
function validateParams(array $params): array
{
    $validated = [];
    
    // Validate limit parameter
    if (isset($params['limit'])) {
        $limit = filter_var($params['limit'], FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => 100,
                'default' => 25
            ]
        ]);
        $validated['limit'] = $limit ?: 25;
    } else {
        $validated['limit'] = 25;
    }
    
    // Validate page parameter
    if (isset($params['page'])) {
        $page = filter_var($params['page'], FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'default' => 1
            ]
        ]);
        $validated['page'] = $page ?: 1;
    } else {
        $validated['page'] = 1;
    }
    
    // Validate date parameters
    if (isset($params['start_date'])) {
        $startDate = filter_var($params['start_date'], FILTER_SANITIZE_STRING);
        if ($startDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $validated['start_date'] = $startDate;
        }
    }
    
    if (isset($params['end_date'])) {
        $endDate = filter_var($params['end_date'], FILTER_SANITIZE_STRING);
        if ($endDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $validated['end_date'] = $endDate;
        }
    }
    
    // Validate transaction_id parameter
    if (isset($params['transaction_id'])) {
        $transactionId = filter_var($params['transaction_id'], FILTER_SANITIZE_STRING);
        if ($transactionId && preg_match('/^[a-zA-Z0-9\-_]+$/', $transactionId)) {
            $validated['transaction_id'] = $transactionId;
        }
    }
    
    return $validated;
}

/**
 * Handle API request and return appropriate response
 *
 * @param TransactionReporter $reporter The transaction reporter instance
 * @param array $params Validated parameters
 * @return array API response
 */
function handleRequest(TransactionReporter $reporter, array $params): array
{
    try {
        // Handle specific transaction lookup
        if (isset($params['transaction_id'])) {
            return $reporter->getTransactionDetails($params['transaction_id']);
        }
        
        // Handle date range query - merge API and local data
        if (isset($params['start_date']) && isset($params['end_date'])) {
            $apiResult = $reporter->getTransactionsByDateRange(
                $params['start_date'],
                $params['end_date'],
                $params['limit']
            );
            $localTransactions = $reporter->getLocalTransactions(
                $params['start_date'],
                $params['end_date'],
                $params['limit']
            );

            // Merge and sort transactions
            $allTransactions = [];
            
            if ($apiResult['success'] && isset($apiResult['data']['transactions'])) {
                $allTransactions = array_merge($allTransactions, $apiResult['data']['transactions']);
            }
            
            $allTransactions = array_merge($allTransactions, $localTransactions);

            // Sort by timestamp descending (newest first)
            usort($allTransactions, function($a, $b) {
                $timeA = strtotime($a['timestamp'] ?? '');
                $timeB = strtotime($b['timestamp'] ?? '');
                return $timeB - $timeA;
            });

            // Apply limit to merged results
            if (count($allTransactions) > $params['limit']) {
                $allTransactions = array_slice($allTransactions, 0, $params['limit']);
            }

            return [
                'success' => true,
                'data' => [
                    'transactions' => $allTransactions,
                    'total_count' => count($allTransactions),
                    'api_transactions' => $apiResult['success'] ? count($apiResult['data']['transactions'] ?? []) : 0,
                    'local_transactions' => count($localTransactions)
                ],
                'message' => 'Transactions retrieved successfully'
            ];
        }
        
        // Handle recent transactions (default) - merge API and local data
        $apiResult = $reporter->getRecentTransactions($params['limit'], $params['page']);
        $localTransactions = $reporter->getLocalTransactions(null, null, $params['limit']);

        // Merge and sort transactions
        $allTransactions = [];
        
        if ($apiResult['success'] && isset($apiResult['data']['transactions'])) {
            $allTransactions = array_merge($allTransactions, $apiResult['data']['transactions']);
        }
        
        $allTransactions = array_merge($allTransactions, $localTransactions);

        // Sort by timestamp descending (newest first)
        usort($allTransactions, function($a, $b) {
            $timeA = strtotime($a['timestamp'] ?? '');
            $timeB = strtotime($b['timestamp'] ?? '');
            return $timeB - $timeA;
        });

        // Apply limit to merged results
        if (count($allTransactions) > $params['limit']) {
            $allTransactions = array_slice($allTransactions, 0, $params['limit']);
        }

        return [
            'success' => true,
            'data' => [
                'transactions' => $allTransactions,
                'total_count' => count($allTransactions),
                'api_transactions' => $apiResult['success'] ? count($apiResult['data']['transactions'] ?? []) : 0,
                'local_transactions' => count($localTransactions)
            ],
            'message' => 'Transactions retrieved successfully'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Request processing failed',
            'error' => [
                'code' => 'PROCESSING_ERROR',
                'details' => $e->getMessage()
            ]
        ];
    }
}

// Main request processing
try {
    // Only accept GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed. Use GET.',
            'error' => ['code' => 'METHOD_NOT_ALLOWED']
        ]);
        exit;
    }
    
    // Validate parameters
    $params = validateParams($_GET);
    
    // Initialize reporter
    $reporter = new TransactionReporter();
    
    // Handle request
    $response = handleRequest($reporter, $params);
    
    // Set appropriate HTTP status code
    if (!$response['success']) {
        switch ($response['error']['code'] ?? '') {
            case 'TRANSACTION_NOT_FOUND':
                http_response_code(404);
                break;
            case 'REPORTING_API_ERROR':
                http_response_code(502); // Bad Gateway
                break;
            default:
                http_response_code(500);
        }
    }
    
    // Add request metadata
    $response['request_info'] = [
        'endpoint' => $_SERVER['REQUEST_URI'],
        'method' => $_SERVER['REQUEST_METHOD'],
        'timestamp' => date('c'),
        'parameters' => $params
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Handle unexpected errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'details' => 'An unexpected error occurred'
        ],
        'request_info' => [
            'endpoint' => $_SERVER['REQUEST_URI'],
            'method' => $_SERVER['REQUEST_METHOD'],
            'timestamp' => date('c')
        ]
    ], JSON_PRETTY_PRINT);
    
    // Log error for debugging
    error_log('Transaction API error: ' . $e->getMessage());
}