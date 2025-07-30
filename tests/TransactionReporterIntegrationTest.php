<?php

declare(strict_types=1);

namespace GlobalPayments\Examples\Tests;

use PHPUnit\Framework\TestCase;
use GlobalPayments\Examples\TransactionReporter;

/**
 * Integration tests for TransactionReporter class
 * 
 * These tests focus on the public API and integration behavior
 * 
 * @covers \GlobalPayments\Examples\TransactionReporter
 */
class TransactionReporterIntegrationTest extends TestCase
{
    private TransactionReporter $reporter;

    protected function setUp(): void
    {
        $this->reporter = new TransactionReporter();
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

    public function testRecordAndRetrieveLocalTransactions(): void
    {
        // Record multiple test transactions with numeric IDs (like real Portico transactions)
        $transactions = [
            [
                'id' => '1001',
                'amount' => '10.00',
                'currency' => 'USD',
                'status' => 'approved',
                'type' => 'payment',
                'timestamp' => '2025-07-29T10:00:00+00:00',
                'card' => [
                    'type' => 'VISA',
                    'last4' => '4242',
                    'exp_month' => '12',
                    'exp_year' => '2025'
                ],
                'response' => [
                    'code' => '00',
                    'message' => 'Approved'
                ],
                'reference' => 'REF001',
                'gateway_response_code' => '00'
            ],
            [
                'id' => '1002',
                'amount' => 'VERIFY',
                'currency' => 'USD',
                'status' => 'approved',
                'type' => 'verification',
                'timestamp' => '2025-07-29T11:00:00+00:00',
                'card' => [
                    'type' => 'MASTERCARD',
                    'last4' => '5555',
                    'exp_month' => '06',
                    'exp_year' => '2026'
                ],
                'response' => [
                    'code' => '00',
                    'message' => 'Verified'
                ],
                'reference' => 'REF002'
            ],
            [
                'id' => '1003',
                'amount' => '25.50',
                'currency' => 'USD',
                'status' => 'declined',
                'type' => 'payment',
                'timestamp' => '2025-07-29T12:00:00+00:00',
                'card' => [
                    'type' => 'AMEX',
                    'last4' => '0005',
                    'exp_month' => '03',
                    'exp_year' => '2027'
                ],
                'response' => [
                    'code' => '96',
                    'message' => 'Declined'
                ],
                'reference' => 'REF003'
            ]
        ];

        // Record all transactions
        foreach ($transactions as $transaction) {
            $this->reporter->recordTransaction($transaction);
        }

        // Retrieve all local transactions
        $retrieved = $this->reporter->getLocalTransactions();

        $this->assertIsArray($retrieved);
        $this->assertGreaterThanOrEqual(3, count($retrieved));

        // Verify transactions were stored correctly
        $foundTransactions = [];
        foreach ($retrieved as $transaction) {
            if (in_array($transaction['id'], ['1001', '1002', '1003'])) {
                $foundTransactions[$transaction['id']] = $transaction;
            }
        }

        $this->assertCount(3, $foundTransactions);

        // Verify payment transaction
        $payment = $foundTransactions['1001'];
        $this->assertEquals('10.00', $payment['amount']);
        $this->assertEquals('approved', $payment['status']);
        $this->assertEquals('payment', $payment['type']);
        $this->assertEquals('VISA', $payment['card']['type']);
        $this->assertEquals('4242', $payment['card']['last4']);
        $this->assertEquals('00', $payment['response']['code']);

        // Verify verification transaction
        $verification = $foundTransactions['1002'];
        $this->assertEquals('VERIFY', $verification['amount']);
        $this->assertEquals('verification', $verification['type']);
        $this->assertEquals('MASTERCARD', $verification['card']['type']);

        // Verify declined transaction
        $declined = $foundTransactions['1003'];
        $this->assertEquals('25.50', $declined['amount']);
        $this->assertEquals('declined', $declined['status']);
        $this->assertEquals('96', $declined['response']['code']);
    }

    public function testDateRangeFiltering(): void
    {
        // Record transactions on different dates with numeric IDs
        $transactions = [
            [
                'id' => '2001',
                'amount' => '15.00',
                'status' => 'approved',
                'type' => 'payment',
                'timestamp' => '2025-07-28T10:00:00+00:00', // Yesterday
                'card' => ['type' => 'VISA', 'last4' => '1111'],
                'response' => ['code' => '00', 'message' => 'Approved']
            ],
            [
                'id' => '2002',
                'amount' => '20.00',
                'status' => 'approved',
                'type' => 'payment',
                'timestamp' => '2025-07-29T10:00:00+00:00', // Today
                'card' => ['type' => 'MASTERCARD', 'last4' => '2222'],
                'response' => ['code' => '00', 'message' => 'Approved']
            ],
            [
                'id' => '2003',
                'amount' => '30.00',
                'status' => 'approved',
                'type' => 'payment',
                'timestamp' => '2025-07-30T10:00:00+00:00', // Tomorrow
                'card' => ['type' => 'AMEX', 'last4' => '3333'],
                'response' => ['code' => '00', 'message' => 'Approved']
            ]
        ];

        foreach ($transactions as $transaction) {
            $this->reporter->recordTransaction($transaction);
        }

        // Test filtering for today only
        $todayTransactions = $this->reporter->getLocalTransactions('2025-07-29', '2025-07-29');
        $todayIds = array_column($todayTransactions, 'id');
        
        $this->assertContains('2002', $todayIds);
        $this->assertNotContains('2001', $todayIds);
        $this->assertNotContains('2003', $todayIds);

        // Test filtering for yesterday to today
        $rangeTransactions = $this->reporter->getLocalTransactions('2025-07-28', '2025-07-29');
        $rangeIds = array_column($rangeTransactions, 'id');
        
        $this->assertContains('2001', $rangeIds);
        $this->assertContains('2002', $rangeIds);
        $this->assertNotContains('2003', $rangeIds);

        // Test filtering for future dates (should be empty)
        $futureTransactions = $this->reporter->getLocalTransactions('2025-08-01', '2025-08-02');
        $futureIds = array_column($futureTransactions, 'id');
        
        $this->assertNotContains('DATE_001', $futureIds);
        $this->assertNotContains('DATE_002', $futureIds);
        $this->assertNotContains('DATE_003', $futureIds);
    }

    public function testTransactionLimitHandling(): void
    {
        // Record many transactions with numeric IDs
        for ($i = 1; $i <= 15; $i++) {
            $transaction = [
                'id' => sprintf('%d', 3000 + $i), // Numeric IDs like 3001, 3002, etc.
                'amount' => sprintf('%.2f', $i * 5.00),
                'status' => 'approved',
                'type' => 'payment',
                'timestamp' => date('c', strtotime("-{$i} minutes")),
                'card' => ['type' => 'VISA', 'last4' => '1234'],
                'response' => ['code' => '00', 'message' => 'Approved']
            ];
            $this->reporter->recordTransaction($transaction);
        }

        // Test limiting results
        $limitedTransactions = $this->reporter->getLocalTransactions(null, null, 5);
        
        // Should get at most 5 transactions total, and at least some should be our test transactions
        $this->assertLessThanOrEqual(5, count($limitedTransactions));
        
        $limitTestTransactions = array_filter($limitedTransactions, function($tx) {
            return in_array($tx['id'], array_map(function($i) { return sprintf('%d', 3000 + $i); }, range(1, 15)));
        });
        
        // We should have some of our test transactions (but not necessarily all 5 due to other transactions)
        $this->assertGreaterThan(0, count($limitTestTransactions));

        // Test that transactions are sorted by timestamp descending (newest first)
        $allTestTransactions = array_filter(
            $this->reporter->getLocalTransactions(null, null, 100),
            function($tx) {
                return in_array($tx['id'], array_map(function($i) { return sprintf('%d', 3000 + $i); }, range(1, 15)));
            }
        );

        $this->assertGreaterThanOrEqual(15, count($allTestTransactions));

        // Check that they're sorted newest first
        $timestamps = array_map(function($tx) {
            return strtotime($tx['timestamp']);
        }, array_slice($allTestTransactions, 0, 3));

        $this->assertGreaterThanOrEqual($timestamps[1], $timestamps[0]);
        $this->assertGreaterThanOrEqual($timestamps[2], $timestamps[1]);
    }

    public function testTransactionDataIntegrity(): void
    {
        $originalTransaction = [
            'id' => '4001',
            'amount' => '99.99',
            'currency' => 'EUR',
            'status' => 'approved',
            'type' => 'payment',
            'timestamp' => '2025-07-29T15:30:45+00:00',
            'card' => [
                'type' => 'DISCOVER',
                'last4' => '6011',
                'exp_month' => '09',
                'exp_year' => '2028'
            ],
            'response' => [
                'code' => '00',
                'message' => 'Transaction Approved'
            ],
            'avs' => [
                'code' => 'Y',
                'message' => 'Address and ZIP match'
            ],
            'cvv' => [
                'code' => 'M',
                'message' => 'CVV matches'
            ],
            'reference' => 'REF_INTEGRITY_001',
            'gateway_response_code' => '00',
            'gateway_response_message' => 'Gateway approved',
            'batch_id' => 'BATCH_999'
        ];

        $this->reporter->recordTransaction($originalTransaction);

        $retrieved = $this->reporter->getLocalTransactions();
        $found = null;

        foreach ($retrieved as $transaction) {
            if ($transaction['id'] === '4001') {
                $found = $transaction;
                break;
            }
        }

        $this->assertNotNull($found, 'Transaction not found');

        // Verify all data integrity
        $this->assertEquals($originalTransaction['id'], $found['id']);
        $this->assertEquals($originalTransaction['amount'], $found['amount']);
        $this->assertEquals($originalTransaction['currency'], $found['currency']);
        $this->assertEquals($originalTransaction['status'], $found['status']);
        $this->assertEquals($originalTransaction['type'], $found['type']);
        $this->assertEquals($originalTransaction['card']['type'], $found['card']['type']);
        $this->assertEquals($originalTransaction['card']['last4'], $found['card']['last4']);
        $this->assertEquals($originalTransaction['card']['exp_month'], $found['card']['exp_month']);
        $this->assertEquals($originalTransaction['card']['exp_year'], $found['card']['exp_year']);
        $this->assertEquals($originalTransaction['response']['code'], $found['response']['code']);
        $this->assertEquals($originalTransaction['response']['message'], $found['response']['message']);
        $this->assertEquals($originalTransaction['avs']['code'], $found['avs']['code']);
        $this->assertEquals($originalTransaction['cvv']['code'], $found['cvv']['code']);
        $this->assertEquals($originalTransaction['reference'], $found['reference']);
        $this->assertEquals($originalTransaction['gateway_response_code'], $found['gateway_response_code']);
        $this->assertEquals($originalTransaction['batch_id'], $found['batch_id']);
    }

    public function testDefaultValueHandling(): void
    {
        // Test recording transaction with minimal data with numeric ID
        $minimalTransaction = [
            'id' => '5001'
        ];

        $this->reporter->recordTransaction($minimalTransaction);

        $retrieved = $this->reporter->getLocalTransactions();
        $found = null;

        foreach ($retrieved as $transaction) {
            if ($transaction['id'] === '5001') {
                $found = $transaction;
                break;
            }
        }

        $this->assertNotNull($found, 'Minimal transaction not found');

        // Verify default values are applied
        $this->assertEquals('5001', $found['id']);
        $this->assertEquals('', $found['reference']);
        $this->assertEquals('unknown', $found['status']);
        $this->assertEquals('0.00', $found['amount']);
        $this->assertEquals('USD', $found['currency']);
        $this->assertEquals('verification', $found['type']);
        $this->assertEquals('Unknown', $found['card']['type']);
        $this->assertEquals('0000', $found['card']['last4']);
        $this->assertEquals('Unknown', $found['response']['code']);
        $this->assertEquals('Transaction processed', $found['response']['message']);
        
        // Verify timestamp was added
        $this->assertNotEmpty($found['timestamp']);
        $this->assertNotFalse(strtotime($found['timestamp']));
    }
}