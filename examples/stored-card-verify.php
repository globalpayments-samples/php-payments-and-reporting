<?php

declare(strict_types=1);

/**
 * Stored Card Verification Example
 *
 * This example demonstrates how to verify previously tokenized cards
 * for subscription management, recurring billing setup, and account
 * verification workflows without re-entering card details.
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
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\Customer;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig;
use GlobalPayments\Api\ServicesContainer;

/**
 * Configure the SDK
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
 * First, tokenize a card for demonstration
 */
function tokenizeCardForDemo(array $cardData): array
{
    try {
        $card = new CreditCardData();
        $card->number = $cardData['number'];
        $card->expMonth = (int)$cardData['exp_month'];
        $card->expYear = (int)$cardData['exp_year'];
        $card->cvn = $cardData['cvv'];
        $card->cardHolderName = $cardData['holder_name'] ?? null;

        // Tokenize the card
        $response = $card->tokenize()->execute();

        return [
            'success' => true,
            'token' => $response->token,
            'message' => 'Card tokenized successfully'
        ];

    } catch (ApiException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Verify a stored/tokenized card
 */
function verifyStoredCard(
    string $token, 
    ?array $addressData = null, 
    ?array $customerData = null,
    ?string $newCvv = null,
    ?int $newExpMonth = null,
    ?int $newExpYear = null
): array {
    try {
        // Create credit card object with token
        $card = new CreditCardData();
        $card->token = $token;
        
        // Update expiration if provided (for card updates)
        if ($newExpMonth && $newExpYear) {
            $card->expMonth = $newExpMonth;
            $card->expYear = $newExpYear;
        }
        
        // Update CVV if provided (for re-verification)
        if ($newCvv) {
            $card->cvn = $newCvv;
        }

        // Build verification request
        $builder = $card->verify()->withAllowDuplicates(true);

        // Add address for AVS if provided
        if ($addressData) {
            $address = new Address();
            $address->streetAddress1 = $addressData['street'];
            $address->city = $addressData['city'];
            $address->state = $addressData['state'];
            $address->postalCode = $addressData['postal_code'];
            $address->country = $addressData['country'] ?? 'US';
            
            $builder->withAddress($address);
        }

        // Add customer data if provided
        if ($customerData) {
            $customer = new Customer();
            $customer->id = $customerData['id'] ?? null;
            $customer->email = $customerData['email'] ?? null;
            $customer->homePhone = $customerData['phone'] ?? null;
            
            $builder->withCustomerData($customer);
        }

        // Execute verification
        $response = $builder->execute();

        // Determine verification status and card status
        $cardStatus = determineCardStatus($response);

        return [
            'success' => true,
            'verified' => $response->responseCode === '00',
            'card_status' => $cardStatus,
            'transaction_id' => $response->transactionReference->transactionId,
            'response' => [
                'code' => $response->responseCode,
                'message' => $response->responseMessage,
                'card_type' => $response->cardType ?? 'Unknown'
            ],
            'avs_result' => $response->avsResponseCode ? [
                'code' => $response->avsResponseCode,
                'message' => $response->avsResponseMessage,
                'street_match' => in_array($response->avsResponseCode, ['A', 'B', 'D', 'M', 'P', 'W', 'X', 'Y']),
                'zip_match' => in_array($response->avsResponseCode, ['D', 'M', 'P', 'W', 'X', 'Y', 'Z'])
            ] : null,
            'cvv_result' => $response->cvnResponseCode ? [
                'code' => $response->cvnResponseCode,
                'message' => $response->cvnResponseMessage,
                'matched' => $response->cvnResponseCode === 'M'
            ] : null
        ];

    } catch (ApiException $e) {
        return [
            'success' => false,
            'verified' => false,
            'error' => $e->getMessage(),
            'card_status' => 'ERROR'
        ];
    }
}

/**
 * Determine card status based on response
 */
function determineCardStatus($response): string
{
    $responseCode = $response->responseCode;
    
    switch ($responseCode) {
        case '00':
            return 'ACTIVE';
        case '14':
            return 'INVALID_CARD';
        case '54':
            return 'EXPIRED';
        case '51':
            return 'INSUFFICIENT_FUNDS';
        case '05':
            return 'DECLINED';
        case '41':
            return 'LOST_CARD';
        case '43':
            return 'STOLEN_CARD';
        default:
            return 'UNKNOWN';
    }
}

/**
 * Batch verification of multiple stored cards
 */
function batchVerifyStoredCards(array $tokens): array
{
    $results = [];
    
    foreach ($tokens as $index => $token) {
        echo "Verifying token " . ($index + 1) . "/" . count($tokens) . "...\n";
        
        $result = verifyStoredCard($token);
        $result['token'] = substr($token, 0, 8) . '...'; // Mask token for logging
        $results[] = $result;
        
        // Small delay to prevent rate limiting
        usleep(500000); // 0.5 seconds
    }
    
    return $results;
}

// CLI Example
if (php_sapi_name() === 'cli') {
    echo "💳 Stored Card Verification Example\n";
    echo "==================================\n\n";

    try {
        configureSdk();

        // First, create some test tokens
        echo "1. Creating test tokens...\n";
        $testCards = [
            [
                'number' => '4111111111111111',
                'exp_month' => 12,
                'exp_year' => 2025,
                'cvv' => '123',
                'holder_name' => 'John Doe'
            ],
            [
                'number' => '5454545454545454',
                'exp_month' => 11,
                'exp_year' => 2025,
                'cvv' => '999',
                'holder_name' => 'Jane Smith'
            ]
        ];

        $tokens = [];
        foreach ($testCards as $cardData) {
            $tokenResult = tokenizeCardForDemo($cardData);
            if ($tokenResult['success']) {
                $tokens[] = $tokenResult['token'];
                echo "  ✅ Tokenized: ****" . substr($cardData['number'], -4) . "\n";
            } else {
                echo "  ❌ Failed to tokenize: " . $tokenResult['error'] . "\n";
            }
        }

        if (empty($tokens)) {
            echo "❌ No tokens created. Cannot proceed with verification.\n";
            exit(1);
        }

        echo "\n2. Verifying stored cards...\n";

        // Test different verification scenarios
        $scenarios = [
            [
                'name' => 'Basic verification',
                'token' => $tokens[0],
                'address' => null,
                'customer' => null
            ],
            [
                'name' => 'Verification with AVS',
                'token' => $tokens[0],
                'address' => [
                    'street' => '123 Main St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '12345',
                    'country' => 'US'
                ],
                'customer' => null
            ],
            [
                'name' => 'Full verification with customer data',
                'token' => $tokens[1] ?? $tokens[0],
                'address' => [
                    'street' => '456 Oak Ave',
                    'city' => 'Los Angeles',
                    'state' => 'CA',
                    'postal_code' => '90210',
                    'country' => 'US'
                ],
                'customer' => [
                    'id' => 'CUST_123',
                    'email' => 'customer@example.com',
                    'phone' => '5551234567'
                ]
            ]
        ];

        foreach ($scenarios as $scenario) {
            echo "\nTesting: {$scenario['name']}\n";
            echo "Token: " . substr($scenario['token'], 0, 8) . "...\n";
            
            $result = verifyStoredCard(
                $scenario['token'],
                $scenario['address'],
                $scenario['customer']
            );

            if ($result['success']) {
                $status = $result['verified'] ? '✅ VERIFIED' : '❌ FAILED';
                echo "Result: {$status}\n";
                echo "Card Status: {$result['card_status']}\n";
                echo "Response: {$result['response']['code']} - {$result['response']['message']}\n";
                echo "Transaction ID: {$result['transaction_id']}\n";
                
                if ($result['avs_result']) {
                    $avs = $result['avs_result'];
                    echo "AVS: {$avs['code']} (Street: " . ($avs['street_match'] ? 'Match' : 'No Match') . 
                         ", ZIP: " . ($avs['zip_match'] ? 'Match' : 'No Match') . ")\n";
                }
                
                if ($result['cvv_result']) {
                    $cvv = $result['cvv_result'];
                    echo "CVV: {$cvv['code']} (" . ($cvv['matched'] ? 'Match' : 'No Match') . ")\n";
                }
            } else {
                echo "❌ Error: {$result['error']}\n";
                echo "Card Status: {$result['card_status']}\n";
            }
            
            echo "\n" . str_repeat('-', 50) . "\n";
        }

        // Batch verification example
        if (count($tokens) > 1) {
            echo "\n3. Batch verification example...\n";
            $batchResults = batchVerifyStoredCards($tokens);
            
            echo "\nBatch Results Summary:\n";
            foreach ($batchResults as $index => $result) {
                $status = $result['verified'] ? '✅' : '❌';
                echo "  Token " . ($index + 1) . ": {$status} {$result['card_status']}\n";
            }
        }

    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

// Web API endpoint
if (php_sapi_name() !== 'cli' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        configureSdk();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['token'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Missing required field: token'
            ]);
            exit;
        }

        $result = verifyStoredCard(
            $input['token'],
            $input['address'] ?? null,
            $input['customer'] ?? null,
            $input['new_cvv'] ?? null,
            $input['new_exp_month'] ?? null,
            $input['new_exp_year'] ?? null
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