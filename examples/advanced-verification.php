<?php

declare(strict_types=1);

/**
 * Advanced Card Verification Example
 *
 * This example demonstrates comprehensive card verification including
 * Address Verification Service (AVS) and CVV checks for enhanced
 * security and fraud prevention.
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
 * Perform advanced verification with AVS and CVV checks
 *
 * @param array $cardData Card information
 * @param array $addressData Billing address information
 * @param array|null $customerData Optional customer information
 * @return array Comprehensive verification result
 */
function performAdvancedVerification(
    array $cardData, 
    array $addressData, 
    ?array $customerData = null
): array {
    try {
        // Create credit card data object
        $card = new CreditCardData();
        $card->number = $cardData['number'];
        $card->expMonth = (int)$cardData['exp_month'];
        $card->expYear = (int)$cardData['exp_year'];
        $card->cvn = $cardData['cvv'];
        
        if (!empty($cardData['holder_name'])) {
            $card->cardHolderName = $cardData['holder_name'];
        }

        // Create billing address for AVS
        $address = new Address();
        $address->streetAddress1 = $addressData['street'];
        $address->city = $addressData['city'];
        $address->state = $addressData['state'];
        $address->postalCode = $addressData['postal_code'];
        $address->country = $addressData['country'] ?? 'US';

        // Create customer data if provided
        $customer = null;
        if ($customerData) {
            $customer = new Customer();
            $customer->id = $customerData['id'] ?? null;
            $customer->email = $customerData['email'] ?? null;
            $customer->homePhone = $customerData['phone'] ?? null;
        }

        // Build verification request
        $builder = $card->verify()
            ->withAllowDuplicates(true)
            ->withAddress($address);
            
        if ($customer) {
            $builder->withCustomerData($customer);
        }

        // Execute verification
        $response = $builder->execute();

        // Analyze results
        $verification = [
            'card_verified' => $response->responseCode === '00',
            'avs_match' => analyzeAvsResponse($response->avsResponseCode),
            'cvv_match' => analyzeCvvResponse($response->cvnResponseCode),
            'overall_risk' => calculateRiskScore($response)
        ];

        return [
            'success' => true,
            'verification' => $verification,
            'transaction_id' => $response->transactionReference->transactionId,
            'response_details' => [
                'code' => $response->responseCode,
                'message' => $response->responseMessage,
                'card_type' => $response->cardType ?? 'Unknown'
            ],
            'avs_details' => [
                'code' => $response->avsResponseCode ?? null,
                'message' => $response->avsResponseMessage ?? null,
                'street_match' => in_array($response->avsResponseCode, ['A', 'B', 'D', 'M', 'P', 'W', 'X', 'Y']),
                'zip_match' => in_array($response->avsResponseCode, ['D', 'M', 'P', 'W', 'X', 'Y', 'Z'])
            ],
            'cvv_details' => [
                'code' => $response->cvnResponseCode ?? null,
                'message' => $response->cvnResponseMessage ?? null,
                'matched' => $response->cvnResponseCode === 'M'
            ]
        ];

    } catch (ApiException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'verification' => [
                'card_verified' => false,
                'avs_match' => false,
                'cvv_match' => false,
                'overall_risk' => 'HIGH'
            ]
        ];
    }
}

/**
 * Analyze AVS response code
 */
function analyzeAvsResponse(?string $avsCode): bool
{
    if (!$avsCode) return false;
    
    // AVS codes indicating a match
    $matchCodes = ['A', 'B', 'D', 'M', 'P', 'W', 'X', 'Y', 'Z'];
    return in_array($avsCode, $matchCodes);
}

/**
 * Analyze CVV response code
 */
function analyzeCvvResponse(?string $cvvCode): bool
{
    return $cvvCode === 'M'; // 'M' indicates match
}

/**
 * Calculate overall risk score based on verification results
 */
function calculateRiskScore($response): string
{
    $score = 0;
    
    // Card verification
    if ($response->responseCode === '00') {
        $score += 40;
    }
    
    // AVS check
    if (analyzeAvsResponse($response->avsResponseCode)) {
        $score += 30;
    }
    
    // CVV check
    if (analyzeCvvResponse($response->cvnResponseCode)) {
        $score += 30;
    }
    
    if ($score >= 90) return 'LOW';
    if ($score >= 70) return 'MEDIUM';
    if ($score >= 40) return 'MEDIUM-HIGH';
    return 'HIGH';
}

// CLI Example
if (php_sapi_name() === 'cli') {
    echo "🔒 Advanced Card Verification Example\n";
    echo "====================================\n\n";

    try {
        configureSdk();

        $testScenarios = [
            [
                'name' => 'Perfect Match Scenario',
                'card' => [
                    'number' => '4111111111111111',
                    'exp_month' => 12,
                    'exp_year' => 2025,
                    'cvv' => '123',
                    'holder_name' => 'John Doe'
                ],
                'address' => [
                    'street' => '123 Main St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '12345',
                    'country' => 'US'
                ],
                'customer' => [
                    'id' => 'CUST_001',
                    'email' => 'john.doe@example.com',
                    'phone' => '5551234567'
                ]
            ],
            [
                'name' => 'Address Mismatch Scenario',
                'card' => [
                    'number' => '5454545454545454',
                    'exp_month' => 11,
                    'exp_year' => 2025,
                    'cvv' => '999',
                    'holder_name' => 'Jane Smith'
                ],
                'address' => [
                    'street' => '456 Wrong St',
                    'city' => 'Wrong City',
                    'state' => 'CA',
                    'postal_code' => '90210',
                    'country' => 'US'
                ]
            ],
            [
                'name' => 'CVV Mismatch Scenario',
                'card' => [
                    'number' => '4111111111111111',
                    'exp_month' => 12,
                    'exp_year' => 2025,
                    'cvv' => '000', // Wrong CVV
                    'holder_name' => 'Test User'
                ],
                'address' => [
                    'street' => '123 Main St',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '12345',
                    'country' => 'US'
                ]
            ]
        ];

        foreach ($testScenarios as $scenario) {
            echo "Testing: {$scenario['name']}\n";
            echo "Card: ****" . substr($scenario['card']['number'], -4) . "\n";
            echo "Address: {$scenario['address']['street']}, {$scenario['address']['city']}\n";
            
            $result = performAdvancedVerification(
                $scenario['card'],
                $scenario['address'],
                $scenario['customer'] ?? null
            );

            if ($result['success']) {
                $v = $result['verification'];
                echo "\n📊 Verification Results:\n";
                echo "  Card Verified: " . ($v['card_verified'] ? '✅ Yes' : '❌ No') . "\n";
                echo "  AVS Match: " . ($v['avs_match'] ? '✅ Yes' : '❌ No') . "\n";
                echo "  CVV Match: " . ($v['cvv_match'] ? '✅ Yes' : '❌ No') . "\n";
                echo "  Risk Level: {$v['overall_risk']}\n";
                
                echo "\n🔍 Detailed Results:\n";
                echo "  Response: {$result['response_details']['code']} - {$result['response_details']['message']}\n";
                echo "  Card Type: {$result['response_details']['card_type']}\n";
                echo "  Transaction ID: {$result['transaction_id']}\n";
                
                if ($result['avs_details']['code']) {
                    echo "  AVS Code: {$result['avs_details']['code']} - {$result['avs_details']['message']}\n";
                    echo "    Street Match: " . ($result['avs_details']['street_match'] ? 'Yes' : 'No') . "\n";
                    echo "    ZIP Match: " . ($result['avs_details']['zip_match'] ? 'Yes' : 'No') . "\n";
                }
                
                if ($result['cvv_details']['code']) {
                    echo "  CVV Code: {$result['cvv_details']['code']} - {$result['cvv_details']['message']}\n";
                }
            } else {
                echo "❌ Error: {$result['error']}\n";
            }
            
            echo "\n" . str_repeat('=', 60) . "\n\n";
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
        
        if (!$input || !isset($input['card'], $input['address'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields: card and address objects'
            ]);
            exit;
        }

        $result = performAdvancedVerification(
            $input['card'],
            $input['address'],
            $input['customer'] ?? null
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