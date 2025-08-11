<?php

declare(strict_types=1);

/**
 * Clean Mock Data Script
 * 
 * This script removes mock/test transaction data from local storage
 * and keeps only real transactions from Global Payments API.
 */

echo "🧹 Cleaning mock transaction data...\n\n";

try {
    $logFile = __DIR__ . '/../logs/all-transactions.json';
    
    if (!file_exists($logFile)) {
        echo "No transaction log file found.\n";
        exit(0);
    }
    
    $content = file_get_contents($logFile);
    $transactions = json_decode($content, true) ?: [];
    
    echo "Found " . count($transactions) . " total transactions\n";
    
    // Define mock transaction patterns to remove
    $mockPatterns = [
        // Test transaction IDs from integration tests
        '/^100[1-3]$/',           // 1001, 1002, 1003
        '/^200[1-3]$/',           // 2001, 2002, 2003  
        '/^300[1-9]$/',           // 3001-3009
        '/^30[1-9][0-9]$/',       // 3010-3019
        '/^400[1-9]$/',           // 4001-4009
        '/^500[1-9]$/',           // 5001-5009
        
        // Other common test patterns
        '/^TEST_/',
        '/^MOCK_/',
        '/^DEMO_/',
        '/^SAMPLE_/',
        '/^INTEGRATION_/',
        '/^LIMIT_/',
        '/^DATE_/',
        '/^REF_/',
        '/^BATCH_/'
    ];
    
    $originalCount = count($transactions);
    $keptTransactions = [];
    $removedCount = 0;
    
    foreach ($transactions as $transaction) {
        $transactionId = $transaction['id'] ?? '';
        $shouldKeep = true;
        
        // Check if this is a mock transaction
        foreach ($mockPatterns as $pattern) {
            if (preg_match($pattern, $transactionId)) {
                $shouldKeep = false;
                break;
            }
        }
        
        // Keep real transactions (like VER_* from Global Payments API)
        if (strpos($transactionId, 'VER_') === 0) {
            $shouldKeep = true;
        }
        
        if ($shouldKeep) {
            $keptTransactions[] = $transaction;
        } else {
            $removedCount++;
            echo "Removing mock transaction: $transactionId\n";
        }
    }
    
    // Remove duplicates based on transaction ID
    $uniqueTransactions = [];
    $seenIds = [];
    
    foreach ($keptTransactions as $transaction) {
        $id = $transaction['id'] ?? '';
        if (!in_array($id, $seenIds)) {
            $uniqueTransactions[] = $transaction;
            $seenIds[] = $id;
        } else {
            echo "Removing duplicate transaction: $id\n";
            $removedCount++;
        }
    }
    
    // Sort by timestamp (newest first)
    usort($uniqueTransactions, function($a, $b) {
        $timeA = strtotime($a['timestamp'] ?? '1970-01-01');
        $timeB = strtotime($b['timestamp'] ?? '1970-01-01');
        return $timeB - $timeA; // Descending order
    });
    
    // Save cleaned transactions
    file_put_contents($logFile, json_encode($uniqueTransactions, JSON_PRETTY_PRINT));
    
    echo "\n📊 Cleanup Summary:\n";
    echo "  Original transactions: $originalCount\n";
    echo "  Removed mock/duplicate: $removedCount\n";
    echo "  Kept real transactions: " . count($uniqueTransactions) . "\n";
    
    if (count($uniqueTransactions) > 0) {
        echo "\n✅ Kept transactions:\n";
        foreach ($uniqueTransactions as $transaction) {
            $id = $transaction['id'] ?? 'Unknown';
            $type = $transaction['type'] ?? 'Unknown';
            $amount = $transaction['amount'] ?? 'Unknown';
            echo "  - $id ($type, $amount)\n";
        }
    }
    
    echo "\n🎉 Mock data cleanup completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
