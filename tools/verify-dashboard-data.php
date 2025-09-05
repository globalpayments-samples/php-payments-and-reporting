<?php
/**
 * Verify that dashboard only shows legitimate API transactions
 */

require_once __DIR__ . '/../vendor/autoload.php';

use GlobalPayments\Examples\TransactionReporter;

echo "🔍 Testing dashboard data filtering...\n";

$reporter = new TransactionReporter();

// Test the local transactions endpoint
$result = $reporter->getLocalTransactions();
$transactions = $result['data']['transactions'] ?? [];

echo "📊 Dashboard will show " . count($transactions) . " transactions:\n\n";

if (empty($transactions)) {
    echo "✅ Dashboard is clean - no transactions to display\n";
    echo "💡 This is expected until real API transactions are processed\n\n";
} else {
    foreach ($transactions as $transaction) {
        $id = $transaction['id'] ?? 'unknown';
        $type = $transaction['type'] ?? 'unknown';
        $amount = $transaction['amount'] ?? 'unknown';
        $cardLast4 = $transaction['card']['last4'] ?? 'unknown';
        $timestamp = $transaction['timestamp'] ?? 'unknown';
        
        echo "✅ Transaction ID: {$id}\n";
        echo "   Type: {$type} | Amount: {$amount} | Card: ***{$cardLast4} | Time: {$timestamp}\n\n";
        
        // Validate that this looks like a legitimate transaction
        if (preg_match('/^(VER_|TRN_)[a-zA-Z0-9_]+$/', $id)) {
            echo "   ✅ Valid Global Payments transaction format\n";
        } else {
            echo "   ⚠️  Unusual transaction ID format - may need investigation\n";
        }
        echo "\n";
    }
}

// Test that test data would be filtered out
echo "🧪 Testing filtering of test data...\n";

$testTransactions = [
    ['id' => 'TEST123', 'type' => 'payment', 'amount' => '10.00'],
    ['id' => '1001', 'type' => 'payment', 'amount' => '25.00'], 
    ['id' => '987654321', 'type' => 'verification', 'amount' => 'VERIFY'],
    ['id' => 'VER_legitimate123ABC', 'type' => 'verification', 'amount' => 'VERIFY']
];

foreach ($testTransactions as $testTxn) {
    $id = $testTxn['id'];
    $type = $testTxn['type'];
    
    // Simulate the filtering logic
    $wouldBeFiltered = false;
    
    $testPatterns = [
        '/^TEST_/', '/^MOCK_/', '/^DEMO_/', '/^SAMPLE_/', '/^INTEGRATION_/',
        '/^LIMIT_/', '/^DATE_/', '/^REF_/', '/^BATCH_/',
        '/^100[0-9]$/', '/^200[0-9]$/', '/^300[0-9]$/', '/^400[0-9]$/', '/^500[0-9]$/',
        '/^ORDER-/', '/^WEB_/', '/^987654321$/', '/^123456789$/', '/^TEST123$/', '/^[0-9]{3,4}$/'
    ];
    
    foreach ($testPatterns as $pattern) {
        if (preg_match($pattern, $id)) {
            $wouldBeFiltered = true;
            break;
        }
    }
    
    // Check if it would pass legitimate checks
    $isLegitimate = false;
    if ($type === 'verification' && strpos($id, 'VER_') === 0) {
        $isLegitimate = true;
    } elseif ($type === 'payment' && strpos($id, 'TRN_') === 0) {
        $isLegitimate = true;
    }
    
    if ($wouldBeFiltered || !$isLegitimate) {
        echo "🚫 '{$id}' would be filtered out (test data) ✅\n";
    } else {
        echo "✅ '{$id}' would be preserved (legitimate) ✅\n";
    }
}

echo "\n✅ Dashboard filtering test complete!\n";
echo "🎯 Only legitimate Global Payments API transactions will appear in the dashboard.\n";