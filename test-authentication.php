<?php

declare(strict_types=1);

/**
 * Card Authentication Integration Test
 *
 * This script demonstrates and tests the complete card authentication
 * workflow including tokenization, verification, and error handling.
 * Useful for integration testing and API validation.
 *
 * PHP version 8.0 or higher
 *
 * @category  Testing
 * @package   GlobalPayments_Examples
 * @author    Global Payments
 * @license   MIT License
 */

require_once 'vendor/autoload.php';

use Dotenv\Dotenv;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\Customer;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Examples\Logger;
use GlobalPayments\Examples\ErrorHandler;

/**
 * Test configuration and setup
 */
class AuthenticationTester
{
    private Logger $logger;
    private ErrorHandler $errorHandler;
    private array $testResults = [];

    public function __construct()
    {
        $this->logger = new Logger('logs', Logger::INFO, true, false);
        $this->errorHandler = new ErrorHandler($this->logger, true);
        $this->configureSdk();
    }

    /**
     * Configure SDK for testing
     */
    private function configureSdk(): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        $config = new PorticoConfig();
        $config->secretApiKey = $_ENV['SECRET_API_KEY'];
        $config->developerId = $_ENV['DEVELOPER_ID'] ?? '000000';
        $config->versionNumber = $_ENV['VERSION_NUMBER'] ?? '0000';
        $config->serviceUrl = $_ENV['SERVICE_URL'] ?? 'https://cert.api2.heartlandportico.com';
        
        ServicesContainer::configureService($config);
    }

    /**
     * Run all authentication tests
     */
    public function runTests(): void
    {
        echo "🔐 Starting Card Authentication Integration Tests\n";
        echo str_repeat('=', 60) . "\n\n";

        $this->testBasicVerification();
        $this->testAvsVerification();
        $this->testFullVerification();
        $this->testTokenizedCardVerification();
        $this->testErrorHandling();
        $this->testMultipleCards();

        $this->printResults();
    }

    /**
     * Test basic card verification
     */
    private function testBasicVerification(): void
    {
        echo "📋 Testing Basic Card Verification...\n";
        
        try {
            $card = new CreditCardData();
            $card->number = '4111111111111111';
            $card->expMonth = 12;
            $card->expYear = 2025;
            $card->cvn = '123';

            $response = $card->verify()
                ->withAllowDuplicates(true)
                ->execute();

            $success = $response->responseCode === '00';
            $this->addResult('Basic Verification', $success, [
                'response_code' => $response->responseCode,
                'response_message' => $response->responseMessage,
                'transaction_id' => $response->transactionReference->transactionId
            ]);

            if ($success) {
                echo "  ✅ Basic verification successful\n";
                echo "  📄 Transaction ID: {$response->transactionReference->transactionId}\n";
            } else {
                echo "  ❌ Basic verification failed: {$response->responseMessage}\n";
            }

        } catch (Exception $e) {
            $this->addResult('Basic Verification', false, ['error' => $e->getMessage()]);
            echo "  ❌ Exception: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * Test AVS verification
     */
    private function testAvsVerification(): void
    {
        echo "🏠 Testing AVS Verification...\n";
        
        try {
            $card = new CreditCardData();
            $card->number = '4111111111111111';
            $card->expMonth = 12;
            $card->expYear = 2025;
            $card->cvn = '123';

            $address = new Address();
            $address->streetAddress1 = '123 Main St';
            $address->city = 'New York';
            $address->state = 'NY';
            $address->postalCode = '12345';

            $response = $card->verify()
                ->withAddress($address)
                ->withAllowDuplicates(true)
                ->execute();

            $success = $response->responseCode === '00';
            $this->addResult('AVS Verification', $success, [
                'response_code' => $response->responseCode,
                'avs_response' => $response->avsResponseCode,
                'cvv_response' => $response->cvnResponseCode,
                'transaction_id' => $response->transactionReference->transactionId
            ]);

            if ($success) {
                echo "  ✅ AVS verification successful\n";
                echo "  🏠 AVS Response: {$response->avsResponseCode} - {$response->avsResponseMessage}\n";
                echo "  🔒 CVV Response: {$response->cvnResponseCode} - {$response->cvnResponseMessage}\n";
            } else {
                echo "  ❌ AVS verification failed: {$response->responseMessage}\n";
            }

        } catch (Exception $e) {
            $this->addResult('AVS Verification', false, ['error' => $e->getMessage()]);
            echo "  ❌ Exception: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * Test full verification with customer data
     */
    private function testFullVerification(): void
    {
        echo "👤 Testing Full Verification with Customer Data...\n";
        
        try {
            $card = new CreditCardData();
            $card->number = '5454545454545454';
            $card->expMonth = 11;
            $card->expYear = 2025;
            $card->cvn = '999';
            $card->cardHolderName = 'John Doe';

            $address = new Address();
            $address->streetAddress1 = '456 Oak Ave';
            $address->city = 'Los Angeles';
            $address->state = 'CA';
            $address->postalCode = '90210';

            $customer = new Customer();
            $customer->id = 'TEST_CUSTOMER_001';
            $customer->email = 'test@example.com';
            $customer->homePhone = '5551234567';

            $response = $card->verify()
                ->withAddress($address)
                ->withCustomerData($customer)
                ->withAllowDuplicates(true)
                ->execute();

            $success = $response->responseCode === '00';
            $this->addResult('Full Verification', $success, [
                'response_code' => $response->responseCode,
                'card_type' => $response->cardType,
                'avs_response' => $response->avsResponseCode,
                'cvv_response' => $response->cvnResponseCode,
                'transaction_id' => $response->transactionReference->transactionId
            ]);

            if ($success) {
                echo "  ✅ Full verification successful\n";
                echo "  💳 Card Type: {$response->cardType}\n";
                echo "  🏠 AVS: {$response->avsResponseCode} - {$response->avsResponseMessage}\n";
                echo "  🔒 CVV: {$response->cvnResponseCode} - {$response->cvnResponseMessage}\n";
            } else {
                echo "  ❌ Full verification failed: {$response->responseMessage}\n";
            }

        } catch (Exception $e) {
            $this->addResult('Full Verification', false, ['error' => $e->getMessage()]);
            echo "  ❌ Exception: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * Test tokenized card verification
     */
    private function testTokenizedCardVerification(): void
    {
        echo "🎫 Testing Tokenized Card Verification...\n";
        
        try {
            // First, tokenize a card
            $card = new CreditCardData();
            $card->number = '4111111111111111';
            $card->expMonth = 12;
            $card->expYear = 2025;
            $card->cvn = '123';

            echo "  📝 Tokenizing card...\n";
            $tokenResponse = $card->tokenize()->execute();
            $token = $tokenResponse->token;
            echo "  ✅ Card tokenized: " . substr($token, 0, 8) . "...\n";

            // Now verify the tokenized card
            $tokenizedCard = new CreditCardData();
            $tokenizedCard->token = $token;

            $response = $tokenizedCard->verify()
                ->withAllowDuplicates(true)
                ->execute();

            $success = $response->responseCode === '00';
            $this->addResult('Tokenized Card Verification', $success, [
                'token' => substr($token, 0, 8) . '...',
                'response_code' => $response->responseCode,
                'transaction_id' => $response->transactionReference->transactionId
            ]);

            if ($success) {
                echo "  ✅ Tokenized card verification successful\n";
                echo "  📄 Transaction ID: {$response->transactionReference->transactionId}\n";
            } else {
                echo "  ❌ Tokenized card verification failed: {$response->responseMessage}\n";
            }

        } catch (Exception $e) {
            $this->addResult('Tokenized Card Verification', false, ['error' => $e->getMessage()]);
            echo "  ❌ Exception: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * Test error handling scenarios
     */
    private function testErrorHandling(): void
    {
        echo "⚠️  Testing Error Handling...\n";
        
        // Test with expired card
        try {
            $expiredCard = new CreditCardData();
            $expiredCard->number = '4111111111111111';
            $expiredCard->expMonth = 12;
            $expiredCard->expYear = 2020; // Expired
            $expiredCard->cvn = '123';

            $response = $expiredCard->verify()
                ->withAllowDuplicates(true)
                ->execute();

            $failed = $response->responseCode !== '00';
            $this->addResult('Expired Card Error', $failed, [
                'response_code' => $response->responseCode,
                'response_message' => $response->responseMessage
            ]);

            echo "  ✅ Expired card properly rejected: {$response->responseMessage}\n";

        } catch (Exception $e) {
            $this->addResult('Expired Card Error', true, ['error' => $e->getMessage()]);
            echo "  ✅ Exception properly thrown: " . $e->getMessage() . "\n";
        }

        // Test with invalid card number
        try {
            $invalidCard = new CreditCardData();
            $invalidCard->number = '1234567890123456'; // Invalid
            $invalidCard->expMonth = 12;
            $invalidCard->expYear = 2025;
            $invalidCard->cvn = '123';

            $response = $invalidCard->verify()
                ->withAllowDuplicates(true)
                ->execute();

            $failed = $response->responseCode !== '00';
            $this->addResult('Invalid Card Error', $failed, [
                'response_code' => $response->responseCode,
                'response_message' => $response->responseMessage
            ]);

            echo "  ✅ Invalid card properly rejected: {$response->responseMessage}\n";

        } catch (Exception $e) {
            $this->addResult('Invalid Card Error', true, ['error' => $e->getMessage()]);
            echo "  ✅ Exception properly thrown: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * Test multiple cards in sequence
     */
    private function testMultipleCards(): void
    {
        echo "🔢 Testing Multiple Card Verifications...\n";
        
        $testCards = [
            ['4111111111111111', 'Visa'],
            ['5454545454545454', 'MasterCard'],
            ['374101000000608', 'Amex'],
            ['6011000000000012', 'Discover']
        ];

        $successCount = 0;
        foreach ($testCards as [$cardNumber, $cardType]) {
            try {
                $card = new CreditCardData();
                $card->number = $cardNumber;
                $card->expMonth = 12;
                $card->expYear = 2025;
                $card->cvn = $cardType === 'Amex' ? '1234' : '123';

                $response = $card->verify()
                    ->withAllowDuplicates(true)
                    ->execute();

                if ($response->responseCode === '00') {
                    echo "  ✅ {$cardType}: Verified successfully\n";
                    $successCount++;
                } else {
                    echo "  ❌ {$cardType}: {$response->responseMessage}\n";
                }

            } catch (Exception $e) {
                echo "  ❌ {$cardType}: Exception - " . $e->getMessage() . "\n";
            }
        }

        $this->addResult('Multiple Cards', $successCount > 0, [
            'total_cards' => count($testCards),
            'successful' => $successCount
        ]);

        echo "  📊 Results: {$successCount}/" . count($testCards) . " cards verified successfully\n\n";
    }

    /**
     * Add test result
     */
    private function addResult(string $testName, bool $success, array $details = []): void
    {
        $this->testResults[] = [
            'test' => $testName,
            'success' => $success,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Log the test result
        $this->logger->info("Test completed: {$testName}", [
            'success' => $success,
            'details' => $details
        ], Logger::CHANNEL_SYSTEM);
    }

    /**
     * Print final test results
     */
    private function printResults(): void
    {
        echo str_repeat('=', 60) . "\n";
        echo "📊 Test Results Summary\n";
        echo str_repeat('=', 60) . "\n\n";

        $totalTests = count($this->testResults);
        $passedTests = array_sum(array_column($this->testResults, 'success'));
        $failedTests = $totalTests - $passedTests;

        echo "Total Tests: {$totalTests}\n";
        echo "Passed: {$passedTests} ✅\n";
        echo "Failed: {$failedTests} ❌\n";
        echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n\n";

        echo "Detailed Results:\n";
        echo str_repeat('-', 40) . "\n";

        foreach ($this->testResults as $result) {
            $status = $result['success'] ? '✅' : '❌';
            echo "{$status} {$result['test']}\n";
            
            if (!empty($result['details'])) {
                foreach ($result['details'] as $key => $value) {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    echo "   {$key}: {$value}\n";
                }
            }
            echo "\n";
        }

        // Overall result
        if ($passedTests === $totalTests) {
            echo "🎉 All tests passed! Authentication system is working correctly.\n";
        } elseif ($passedTests > $failedTests) {
            echo "⚠️  Most tests passed, but some issues were found.\n";
        } else {
            echo "❌ Multiple test failures detected. Please check configuration.\n";
        }
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli') {
    try {
        $tester = new AuthenticationTester();
        $tester->runTests();
    } catch (Exception $e) {
        echo "❌ Test setup failed: " . $e->getMessage() . "\n";
        echo "Please ensure your .env file is configured with valid API credentials.\n";
        exit(1);
    }
} else {
    echo "This script must be run from the command line.\n";
    echo "Usage: php test-authentication.php\n";
}