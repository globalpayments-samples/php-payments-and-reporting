<?php

declare(strict_types=1);

/**
 * Global Payments HPP Cancel Handler
 *
 * This script handles cancelled/abandoned returns from the Global Payments
 * Hosted Payment Page.
 *
 * PHP version 8.0 or higher
 *
 * @category  Payments
 * @package   GlobalPayments_HPP
 * @author    Global Payments Integration
 * @license   MIT License
 */

// Set content type
header('Content-Type: text/html; charset=utf-8');

/**
 * Log cancelled transaction attempt
 *
 * @param array $params GET parameters from HPP cancel
 * @return void
 */
function logCancelledTransaction(array $params): void
{
    $logData = [
        'event' => 'hpp_cancelled',
        'timestamp' => date('c'),
        'reference' => $params['reference'] ?? 'Unknown',
        'amount' => $params['amount'] ?? '0.00',
        'currency' => $params['currency'] ?? 'USD',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/hpp-cancelled-' . date('Y-m-d') . '.log';
    file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Generate cancel page HTML
 *
 * @param array $params Request parameters
 * @return string HTML content
 */
function generateCancelPage(array $params): string
{
    $reference = htmlspecialchars($params['reference'] ?? 'N/A');
    $amount = htmlspecialchars($params['amount'] ?? '0.00');
    $currency = htmlspecialchars($params['currency'] ?? 'USD');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled - Global Payments</title>
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
        .cancel-header {
            color: #F59E0B;
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        .cancel-message {
            color: #6B7280;
            font-size: 1.1rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .transaction-info {
            background: #FEF3C7;
            border: 1px solid #F59E0B;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
        }
        .info-label {
            font-weight: 600;
            color: #92400E;
        }
        .info-value {
            color: #92400E;
            font-family: monospace;
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
        .btn-warning {
            background: #F59E0B;
            color: white;
        }
        .btn-warning:hover {
            background: #D97706;
        }
        .help-section {
            margin-top: 30px;
            padding: 20px;
            background: #F3F4F6;
            border-radius: 8px;
            text-align: left;
        }
        .help-section h4 {
            color: #374151;
            margin-bottom: 10px;
        }
        .help-section ul {
            color: #6B7280;
            margin: 0;
            padding-left: 20px;
        }
        .help-section li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="result-container">
            <div class="cancel-header">
                ⚠️ Payment Cancelled
            </div>
            
            <div class="cancel-message">
                Your payment was cancelled or abandoned. No charges have been made to your account.
            </div>

            <div class="transaction-info">
                <h4 style="margin-top: 0; color: #92400E;">Transaction Information</h4>
                <div class="info-row">
                    <span class="info-label">Reference:</span>
                    <span class="info-value">{$reference}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Amount:</span>
                    <span class="info-value">\${$amount} {$currency}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value">CANCELLED</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date & Time:</span>
                    <span class="info-value">{date('Y-m-d H:i:s T')}</span>
                </div>
            </div>

            <div class="action-buttons">
                <a href="index.html" class="btn btn-warning">
                    🔄 Try Payment Again
                </a>
                <a href="dashboard.html" class="btn btn-primary">
                    📊 View Transactions
                </a>
                <a href="mailto:support@example.com" class="btn btn-secondary">
                    📧 Contact Support
                </a>
            </div>

            <div class="help-section">
                <h4>Why was my payment cancelled?</h4>
                <ul>
                    <li>You clicked the "Cancel" or "Back" button on the payment page</li>
                    <li>You closed the browser window during payment</li>
                    <li>The payment session expired due to inactivity</li>
                    <li>There was a network connectivity issue</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Track cancelled payment for analytics
        console.log('Payment cancelled:', {
            reference: '{$reference}',
            amount: '{$amount}',
            currency: '{$currency}',
            timestamp: new Date().toISOString()
        });
    </script>
</body>
</html>
HTML;
}

// Main processing
try {
    // Log the cancelled transaction
    logCancelledTransaction($_GET);

    // Generate and display cancel page
    echo generateCancelPage($_GET);

} catch (Exception $e) {
    // Error handling
    error_log('HPP cancel processing error: ' . $e->getMessage());
    
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - Global Payments</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="container">
        <div class="result-container result-error">
            <h2>⚠️ Error Processing Request</h2>
            <p>There was an error processing your request. Please try again or contact support.</p>
            <div style="margin-top: 20px;">
                <a href="index.html" class="btn btn-primary">Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
}