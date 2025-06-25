<?php

declare(strict_types=1);

/**
 * Basic Card Verification Example
 *
 * This example demonstrates the simplest form of card verification
 * using the Global Payments SDK. It validates card number and
 * expiration date without additional checks.
 *
 * PHP version 8.0 or higher
 *
 * @category  Examples
 * @package   GlobalPayments_Examples
 * @author    Global Payments
 * @license   MIT License
 */

require_once '../vendor/autoload.php';

use Dotenv\Dotenv;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig;
use GlobalPayments\Api\ServicesContainer;

/**
 * Configure the SDK with environment variables
 */
function configureSdk(): void
{
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    $config = new PorticoConfig();
    $config->secretApiKey = $_ENV['SECRET_API_KEY'];
    $config->developerId = $_ENV['DEVELOPER_ID'] ?? '000000';
    $config->versionNumber = $_ENV['VERSION_NUMBER'] ?? '0000';
    $config->serviceUrl = $_ENV['SERVICE_URL'] ?? 'https://cert.api2.heartlandportico.com';
    
    ServicesContainer::configureService($config);
}

/**
 * Perform basic card verification
 *
 * @param string $cardNumber Card number to verify
 * @param int $expMonth Expiration month
 * @param int $expYear Expiration year
 * @param string|null $cvv Optional CVV
 * @return array Verification result
 */
function performBasicVerification(
    string $cardNumber, 
    int $expMonth, 
    int $expYear, 
    ?string $cvv = null
): array {
    try {
        // Create credit card data object
        $card = new CreditCardData();
        $card->number = $cardNumber;
        $card->expMonth = $expMonth;
        $card->expYear = $expYear;
        
        if ($cvv) {
            $card->cvn = $cvv;
        }

        // Perform verification
        $response = $card->verify()
            ->withAllowDuplicates(true)
            ->execute();

        return [
            'success' => true,
            'verified' => $response->responseCode === '00',
            'transaction_id' => $response->transactionReference->transactionId,
            'response_code' => $response->responseCode,
            'response_message' => $response->responseMessage,
            'card_type' => $response->cardType ?? 'Unknown',
            'cvv_result' => $response->cvnResponseCode ?? null,
            'cvv_message' => $response->cvnResponseMessage ?? null
        ];

    } catch (ApiException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'verified' => false
        ];
    }
}

// Example usage
if (php_sapi_name() === 'cli') {
    echo "🔐 Basic Card Verification Example\n";
    echo "=================================\n\n";

    try {
        configureSdk();

        // Test cases with different card scenarios
        $testCases = [
            [
                'name' => 'Valid Visa Card',
                'card_number' => '4111111111111111',
                'exp_month' => 12,
                'exp_year' => 2025,
                'cvv' => '123'
            ],
            [
                'name' => 'Valid MasterCard',
                'card_number' => '5454545454545454',
                'exp_month' => 11,
                'exp_year' => 2025,
                'cvv' => '999'
            ],
            [
                'name' => 'Expired Card (should fail)',
                'card_number' => '4111111111111111',
                'exp_month' => 12,
                'exp_year' => 2020,
                'cvv' => '123'
            ],
            [
                'name' => 'Invalid Card Number',
                'card_number' => '1234567890123456',
                'exp_month' => 12,
                'exp_year' => 2025,
                'cvv' => '123'
            ]
        ];

        foreach ($testCases as $test) {
            echo "Testing: {$test['name']}\n";
            echo "Card: ****" . substr($test['card_number'], -4) . " {$test['exp_month']}/{$test['exp_year']}\n";
            
            $result = performBasicVerification(
                $test['card_number'],
                $test['exp_month'],
                $test['exp_year'],
                $test['cvv']
            );

            if ($result['success']) {
                $status = $result['verified'] ? '✅ VERIFIED' : '❌ FAILED';
                echo "Result: {$status}\n";
                echo "Response: {$result['response_code']} - {$result['response_message']}\n";
                echo "Card Type: {$result['card_type']}\n";
                echo "Transaction ID: {$result['transaction_id']}\n";
                
                if ($result['cvv_result']) {
                    echo "CVV Check: {$result['cvv_result']} - {$result['cvv_message']}\n";
                }
            } else {
                echo "Result: ❌ ERROR\n";
                echo "Error: {$result['error']}\n";
            }
            
            echo "\n" . str_repeat('-', 50) . "\n\n";
        }

    } catch (Exception $e) {
        echo "❌ Configuration Error: " . $e->getMessage() . "\n";
        echo "Make sure your .env file is properly configured.\n";
    }
}

// Example for web usage
if (php_sapi_name() !== 'cli' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        configureSdk();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['card_number'], $input['exp_month'], $input['exp_year'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields: card_number, exp_month, exp_year'
            ]);
            exit;
        }

        $result = performBasicVerification(
            $input['card_number'],
            (int)$input['exp_month'],
            (int)$input['exp_year'],
            $input['cvv'] ?? null
        );

        echo json_encode($result);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }
}