<?php

declare(strict_types=1);

namespace GlobalPayments\Examples\Tests;

use PHPUnit\Framework\TestCase;
use GlobalPayments\Examples\TransactionReporter;
use ReflectionClass;
use stdClass;

/**
 * Unit tests for TransactionReporter class
 *
 * @covers \GlobalPayments\Examples\TransactionReporter
 */
class TransactionReporterTest extends TestCase
{
    private TransactionReporter $reporter;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reporter = new TransactionReporter();
        $this->reflection = new ReflectionClass(TransactionReporter::class);
    }

    protected function tearDown(): void
    {
        // Clean up any test log files
        $logDir = __DIR__ . '/../logs';
        if (is_dir($logDir)) {
            $testFiles = glob($logDir . '/test-*');
            foreach ($testFiles as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Helper method to call private methods
     */
    private function callPrivateMethod(string $methodName, array $args = []): mixed
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($this->reporter, $args);
    }

    /**
     * Helper method to create mock transaction object
     */
    private function createMockTransaction(array $data = []): stdClass
    {
        $transaction = new stdClass();
        
        // Default values
        $defaults = [
            'transactionId' => '123456789',
            'referenceNumber' => 'REF123',
            'amount' => 10.50,
            'currency' => 'USD',
            'responseDate' => '2025-07-29T15:30:45.123Z',
            'transactionDate' => new \DateTime('2025-07-29 15:30:45'),
            'responseCode' => '00',
            'responseMessage' => 'Approved',
            'gatewayResponseCode' => '00',
            'gatewayResponseMessage' => 'Transaction approved',
            'transactionStatus' => 'A',
            'serviceName' => 'CreditSale',
            'cardType' => 'VISA',
            'maskedCardNumber' => '****4242',
            'cardExpMonth' => '12',
            'cardExpYear' => '2025',
            'avsResponseCode' => 'Y',
            'avsResponseMessage' => 'Address and ZIP match',
            'cvnResponseCode' => 'M',
            'cvnResponseMessage' => 'CVV matches',
            'batchId' => 'BATCH001',
            'authCode' => 'AUTH123',
            'username' => 'test_user'
        ];

        foreach (array_merge($defaults, $data) as $key => $value) {
            $transaction->$key = $value;
        }

        return $transaction;
    }

    public function testFormatTransactionForDashboard(): void
    {
        $transaction = $this->createMockTransaction();
        
        $result = $this->callPrivateMethod('formatTransactionForDashboard', [$transaction]);
        
        $this->assertIsArray($result);
        $this->assertEquals('123456789', $result['id']);
        $this->assertEquals(10.50, $result['amount']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertEquals('approved', $result['status']);
        $this->assertEquals('payment', $result['type']);
        $this->assertEquals('2025-07-29 15:30:45', $result['timestamp']);
        
        // Test card information
        $this->assertArrayHasKey('card', $result);
        $this->assertEquals('VISA', $result['card']['type']);
        $this->assertEquals('4242', $result['card']['last4']);
        $this->assertEquals('12', $result['card']['exp_month']);
        $this->assertEquals('2025', $result['card']['exp_year']);
        
        // Test response information
        $this->assertArrayHasKey('response', $result);
        $this->assertEquals('00', $result['response']['code']);
        $this->assertEquals('Approved', $result['response']['message']);
        
        // Test additional fields
        $this->assertEquals('REF123', $result['reference']);
        $this->assertEquals('BATCH001', $result['batch_id']);
        $this->assertEquals('00', $result['gateway_response_code']);
    }

    public function testFormatTransactionWithVerificationAmount(): void
    {
        $transaction = $this->createMockTransaction([
            'amount' => 0,
            'serviceName' => 'CreditAccountVerify'
        ]);
        
        $result = $this->callPrivateMethod('formatTransactionForDashboard', [$transaction]);
        
        $this->assertEquals('VERIFY', $result['amount']);
        $this->assertEquals('verification', $result['type']);
    }

    public function testGetTransactionType(): void
    {
        // Test payment transaction
        $paymentTransaction = $this->createMockTransaction([
            'serviceName' => 'CreditSale',
            'amount' => 25.00
        ]);
        
        $type = $this->callPrivateMethod('getTransactionType', [$paymentTransaction, 25.00]);
        $this->assertEquals('payment', $type);
        
        // Test verification transaction by amount
        $verifyTransaction = $this->createMockTransaction([
            'serviceName' => 'CreditAccountVerify',
            'amount' => 0
        ]);
        
        $type = $this->callPrivateMethod('getTransactionType', [$verifyTransaction, 'VERIFY']);
        $this->assertEquals('verification', $type);
        
        // Test different service names
        $authTransaction = $this->createMockTransaction(['serviceName' => 'CreditAuth']);
        $type = $this->callPrivateMethod('getTransactionType', [$authTransaction, 10.00]);
        $this->assertEquals('payment', $type);
        
        $tokenizeTransaction = $this->createMockTransaction(['serviceName' => 'Tokenize']);
        $type = $this->callPrivateMethod('getTransactionType', [$tokenizeTransaction, 0]);
        $this->assertEquals('verification', $type);
        
        // Test void transaction
        $voidTransaction = $this->createMockTransaction(['serviceName' => 'CreditVoid']);
        $type = $this->callPrivateMethod('getTransactionType', [$voidTransaction, 10.00]);
        $this->assertEquals('void', $type);
        
        // Test refund transaction
        $refundTransaction = $this->createMockTransaction(['serviceName' => 'CreditReturn']);
        $type = $this->callPrivateMethod('getTransactionType', [$refundTransaction, 10.00]);
        $this->assertEquals('refund', $type);
    }

    public function testGetResponseCode(): void
    {
        // Test with responseCode present
        $transaction = $this->createMockTransaction(['responseCode' => '00']);
        $code = $this->callPrivateMethod('getResponseCode', [$transaction]);
        $this->assertEquals('00', $code);
        
        // Test fallback to gatewayResponseCode
        $transaction = $this->createMockTransaction([
            'responseCode' => null,
            'gatewayResponseCode' => '10'
        ]);
        $code = $this->callPrivateMethod('getResponseCode', [$transaction]);
        $this->assertEquals('10', $code);
        
        // Test fallback to authCode
        $transaction = $this->createMockTransaction([
            'responseCode' => null,
            'gatewayResponseCode' => null,
            'authCode' => 'AUTH456'
        ]);
        $code = $this->callPrivateMethod('getResponseCode', [$transaction]);
        $this->assertEquals('AUTH456', $code);
        
        // Test fallback to transactionStatus
        $transaction = $this->createMockTransaction([
            'responseCode' => null,
            'gatewayResponseCode' => null,
            'authCode' => null,
            'transactionStatus' => 'A'
        ]);
        $code = $this->callPrivateMethod('getResponseCode', [$transaction]);
        $this->assertEquals('00', $code);
        
        // Test no fallbacks available
        $transaction = $this->createMockTransaction([
            'responseCode' => null,
            'gatewayResponseCode' => null,
            'authCode' => null,
            'transactionStatus' => 'X'
        ]);
        $code = $this->callPrivateMethod('getResponseCode', [$transaction]);
        $this->assertNull($code);
    }

    public function testGetResponseMessage(): void
    {
        // Test with responseMessage present
        $transaction = $this->createMockTransaction(['responseMessage' => 'Approved']);
        $message = $this->callPrivateMethod('getResponseMessage', [$transaction]);
        $this->assertEquals('Approved', $message);
        
        // Test fallback to gatewayResponseMessage
        $transaction = $this->createMockTransaction([
            'responseMessage' => null,
            'gatewayResponseMessage' => 'Gateway approved'
        ]);
        $message = $this->callPrivateMethod('getResponseMessage', [$transaction]);
        $this->assertEquals('Gateway approved', $message);
        
        // Test fallback to transactionStatus mapping
        $transaction = $this->createMockTransaction([
            'responseMessage' => null,
            'gatewayResponseMessage' => null,
            'transactionStatus' => 'A'
        ]);
        $message = $this->callPrivateMethod('getResponseMessage', [$transaction]);
        $this->assertEquals('Approved', $message);
        
        $transaction = $this->createMockTransaction([
            'responseMessage' => null,
            'gatewayResponseMessage' => null,
            'transactionStatus' => 'D'
        ]);
        $message = $this->callPrivateMethod('getResponseMessage', [$transaction]);
        $this->assertEquals('Declined', $message);
    }

    public function testGetTransactionStatus(): void
    {
        // Test approved status
        $transaction = $this->createMockTransaction(['responseCode' => '00']);
        $status = $this->callPrivateMethod('getTransactionStatus', [$transaction]);
        $this->assertEquals('approved', $status);
        
        // Test partially approved
        $transaction = $this->createMockTransaction(['responseCode' => '10']);
        $status = $this->callPrivateMethod('getTransactionStatus', [$transaction]);
        $this->assertEquals('partially_approved', $status);
        
        // Test declined
        $transaction = $this->createMockTransaction(['responseCode' => '96']);
        $status = $this->callPrivateMethod('getTransactionStatus', [$transaction]);
        $this->assertEquals('declined', $status);
        
        // Test error
        $transaction = $this->createMockTransaction(['responseCode' => '91']);
        $status = $this->callPrivateMethod('getTransactionStatus', [$transaction]);
        $this->assertEquals('error', $status);
        
        // Test gateway response fallback
        $transaction = $this->createMockTransaction([
            'responseCode' => null,
            'gatewayResponseCode' => '00'
        ]);
        $status = $this->callPrivateMethod('getTransactionStatus', [$transaction]);
        $this->assertEquals('approved', $status);
        
        // Test verification transaction with CVV match
        $transaction = $this->createMockTransaction([
            'responseCode' => null,
            'amount' => 0,
            'cvnResponseCode' => 'M'
        ]);
        $status = $this->callPrivateMethod('getTransactionStatus', [$transaction]);
        $this->assertEquals('approved', $status);
        
        // Test unknown status - should use getResponseCode fallback
        $transaction = $this->createMockTransaction([
            'responseCode' => '99',
            'gatewayResponseCode' => null,
            'authCode' => null,
            'transactionStatus' => null
        ]);
        $status = $this->callPrivateMethod('getTransactionStatus', [$transaction]);
        $this->assertEquals('declined', $status);
    }

    public function testValidateTransactionAuthenticity(): void
    {
        // Test valid transaction
        $validTransaction = $this->createMockTransaction();
        $isValid = $this->callPrivateMethod('validateTransactionAuthenticity', [$validTransaction]);
        $this->assertTrue($isValid);
        
        // Test transaction without ID
        $invalidTransaction = $this->createMockTransaction([
            'transactionId' => null,
            'referenceNumber' => null
        ]);
        $isValid = $this->callPrivateMethod('validateTransactionAuthenticity', [$invalidTransaction]);
        $this->assertFalse($isValid);
        
        // Test transaction with mock indicators
        $mockTransaction = $this->createMockTransaction(['transactionId' => 'MOCK12345']);
        $isValid = $this->callPrivateMethod('validateTransactionAuthenticity', [$mockTransaction]);
        $this->assertFalse($isValid);
        
        // Test transaction without responseDate
        $noDateTransaction = $this->createMockTransaction(['responseDate' => null]);
        $isValid = $this->callPrivateMethod('validateTransactionAuthenticity', [$noDateTransaction]);
        $this->assertFalse($isValid);
        
        // Test transaction with invalid service name
        $invalidServiceTransaction = $this->createMockTransaction(['serviceName' => 'InvalidService']);
        $isValid = $this->callPrivateMethod('validateTransactionAuthenticity', [$invalidServiceTransaction]);
        $this->assertFalse($isValid);
        
        // Test transaction without username - add username to mock defaults
        $noUsernameTransaction = $this->createMockTransaction(['username' => null]);
        $isValid = $this->callPrivateMethod('validateTransactionAuthenticity', [$noUsernameTransaction]);
        $this->assertFalse($isValid);
    }

    public function testRecordTransaction(): void
    {
        $transactionData = [
            'id' => 'TEST123',
            'amount' => '15.00',
            'currency' => 'USD',
            'status' => 'approved',
            'type' => 'payment',
            'card' => [
                'type' => 'VISA',
                'last4' => '4242'
            ],
            'response' => [
                'code' => '00',
                'message' => 'Approved'
            ]
        ];
        
        // Test recording transaction (this should not throw an exception)
        $this->expectNotToPerformAssertions();
        $this->reporter->recordTransaction($transactionData);
    }

    public function testGetLocalTransactions(): void
    {
        // First record a test transaction
        $testTransaction = [
            'id' => 'LOCAL_TEST_001',
            'amount' => '25.00',
            'status' => 'approved',
            'type' => 'payment',
            'timestamp' => date('c'),
            'card' => ['type' => 'MASTERCARD', 'last4' => '5555'],
            'response' => ['code' => '00', 'message' => 'Approved']
        ];
        
        $this->reporter->recordTransaction($testTransaction);
        
        // Retrieve local transactions
        $transactions = $this->reporter->getLocalTransactions();
        
        $this->assertIsArray($transactions);
        // We should have at least our test transaction
        $this->assertGreaterThanOrEqual(1, count($transactions));
        
        // Find our test transaction
        $found = false;
        foreach ($transactions as $transaction) {
            if ($transaction['id'] === 'LOCAL_TEST_001') {
                $found = true;
                $this->assertEquals('25.00', $transaction['amount']);
                $this->assertEquals('approved', $transaction['status']);
                break;
            }
        }
        $this->assertTrue($found, 'Test transaction not found in local transactions');
    }

    public function testGetLocalTransactionsWithDateFilter(): void
    {
        // Use a specific date that we know exists
        $testDate = '2025-07-29';
        $testTransaction = [
            'id' => 'DATE_TEST_001',
            'amount' => '30.00',
            'status' => 'approved',
            'timestamp' => $testDate . 'T10:00:00Z',
            'card' => ['type' => 'AMEX', 'last4' => '0005'],
            'response' => ['code' => '00', 'message' => 'Approved']
        ];
        
        $this->reporter->recordTransaction($testTransaction);
        
        // Test date filtering for the specific test date
        $transactions = $this->reporter->getLocalTransactions($testDate, $testDate, 10);
        
        $this->assertIsArray($transactions);
        
        // Find our test transaction - debug if not found
        $found = false;
        $transactionIds = [];
        foreach ($transactions as $transaction) {
            $transactionIds[] = $transaction['id'];
            if ($transaction['id'] === 'DATE_TEST_001') {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            // Debug information
            $allTransactions = $this->reporter->getLocalTransactions();
            $this->fail('Date-filtered transaction not found. Transaction IDs found: ' . 
                       implode(', ', $transactionIds) . '. Total transactions: ' . count($allTransactions));
        }
        
        $this->assertTrue($found, 'Date-filtered transaction found');
        
        // Test filtering out transactions with future dates
        $emptyResult = $this->reporter->getLocalTransactions('2026-01-01', '2026-01-02', 10);
        $this->assertIsArray($emptyResult);
    }

    public function testTimestampHandling(): void
    {
        // Test with responseDate
        $transaction = $this->createMockTransaction([
            'responseDate' => '2025-07-29T15:45:30.123Z'
        ]);
        
        $result = $this->callPrivateMethod('formatTransactionForDashboard', [$transaction]);
        $this->assertEquals('2025-07-29 15:45:30', $result['timestamp']);
        
        // Test with DateTime transactionDate
        $transaction = $this->createMockTransaction([
            'responseDate' => null,
            'transactionDate' => new \DateTime('2025-07-29 16:30:45')
        ]);
        
        $result = $this->callPrivateMethod('formatTransactionForDashboard', [$transaction]);
        $this->assertEquals('2025-07-29 16:30:45', $result['timestamp']);
        
        // Test with string transactionDate
        $transaction = $this->createMockTransaction([
            'responseDate' => null,
            'transactionDate' => '2025-07-29T17:15:20Z'
        ]);
        
        $result = $this->callPrivateMethod('formatTransactionForDashboard', [$transaction]);
        $this->assertEquals('2025-07-29 17:15:20', $result['timestamp']);
        
        // Test with transactionLocalDate
        $transaction = $this->createMockTransaction([
            'responseDate' => null,
            'transactionDate' => null,
            'transactionLocalDate' => '2025-07-29T18:00:00Z'
        ]);
        
        $result = $this->callPrivateMethod('formatTransactionForDashboard', [$transaction]);
        $this->assertEquals('2025-07-29 18:00:00', $result['timestamp']);
    }

    public function testCardDataHandling(): void
    {
        // Test with maskedCardNumber
        $transaction = $this->createMockTransaction([
            'maskedCardNumber' => '****1234',
            'cardNumber' => null
        ]);
        
        $result = $this->callPrivateMethod('formatTransactionForDashboard', [$transaction]);
        $this->assertEquals('1234', $result['card']['last4']);
        
        // Test fallback to cardNumber
        $transaction = $this->createMockTransaction([
            'maskedCardNumber' => null,
            'cardNumber' => '411111111111234'
        ]);
        
        $result = $this->callPrivateMethod('formatTransactionForDashboard', [$transaction]);
        $this->assertEquals('1234', $result['card']['last4']);
        
        // Test with neither available
        $transaction = $this->createMockTransaction([
            'maskedCardNumber' => null,
            'cardNumber' => null
        ]);
        
        $result = $this->callPrivateMethod('formatTransactionForDashboard', [$transaction]);
        $this->assertNull($result['card']['last4']);
    }
}