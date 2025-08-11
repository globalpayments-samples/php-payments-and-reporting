<?php

declare(strict_types=1);

/**
 * Fix Card Numbers Script
 * 
 * This script updates local transaction data with correct card numbers
 * from the Global Payments API to fix the "****0000" display issue.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use GlobalPayments\Examples\TransactionReporter;

echo "🔧 Fixing card numbers in local transaction data...\n\n";

try {
    $reporter = new TransactionReporter();
    
    // Get local transactions
    $localResult = $reporter->getLocalTransactions();
    $localTransactions = $localResult['data']['transactions'] ?? [];
    
    echo "Found " . count($localTransactions) . " local transactions\n";
    
    $updatedCount = 0;
    $logFile = __DIR__ . '/../logs/all-transactions.json';
    
    foreach ($localTransactions as $index => $transaction) {
        $transactionId = $transaction['id'] ?? '';
        $currentLast4 = $transaction['card']['last4'] ?? null;
        
        // Skip if it's not a verification transaction or if it already has a valid card number
        if (strpos($transactionId, 'VER_') !== 0 || ($currentLast4 && $currentLast4 !== '0000' && $currentLast4 !== null)) {
            continue;
        }
        
        echo "Checking transaction: $transactionId (current last4: $currentLast4)\n";
        
        try {
            // Get transaction details from Global Payments API
            $apiResult = $reporter->getTransactionDetails($transactionId);
            
            if ($apiResult['success'] && isset($apiResult['data']['card']['last4'])) {
                $apiLast4 = $apiResult['data']['card']['last4'];
                
                if ($apiLast4 && $apiLast4 !== '0000' && $apiLast4 !== $currentLast4) {
                    echo "  → Updating last4 from '$currentLast4' to '$apiLast4'\n";
                    
                    // Update the local transaction data
                    $localTransactions[$index]['card']['last4'] = $apiLast4;
                    $updatedCount++;
                }
            }
        } catch (Exception $e) {
            echo "  → Error getting API data: " . $e->getMessage() . "\n";
        }
    }
    
    if ($updatedCount > 0) {
        // Write updated transactions back to file
        file_put_contents($logFile, json_encode($localTransactions, JSON_PRETTY_PRINT));
        echo "\n✅ Updated $updatedCount transactions with correct card numbers\n";
    } else {
        echo "\n✅ No transactions needed updating\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 Card number fix completed!\n";
