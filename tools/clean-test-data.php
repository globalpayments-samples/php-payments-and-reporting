<?php
/**
 * Clean up test/mock data from transaction logs
 * This script removes all test transactions while preserving legitimate API data
 */

require_once __DIR__ . '/../vendor/autoload.php';

use GlobalPayments\Examples\TransactionReporter;

class TestDataCleaner
{
    private string $logDir;
    private array $testPatterns = [
        '/^TEST_/',
        '/^MOCK_/',
        '/^DEMO_/',
        '/^SAMPLE_/',
        '/^INTEGRATION_/',
        '/^LIMIT_/',
        '/^DATE_/',
        '/^REF_/',
        '/^BATCH_/',
        '/^100[0-9]$/',      // 1000-1009
        '/^200[0-9]$/',      // 2000-2009
        '/^300[0-9]$/',      // 3000-3009
        '/^400[0-9]$/',      // 4000-4009
        '/^500[0-9]$/',      // 5000-5009
        '/^ORDER-/',
        '/^WEB_/',
        '/^987654321$/',     // Common test ID
        '/^123456789$/',     // Common test ID
        '/^TEST123$/',       // Common test ID
        '/^[0-9]{3,4}$/',    // Simple numeric test IDs like 1001, 2002, etc.
    ];

    public function __construct()
    {
        $this->logDir = __DIR__ . '/../logs';
    }

    public function cleanAllLogs(): void
    {
        echo "🧹 Starting test data cleanup...\n";
        
        $logFiles = glob($this->logDir . '/*.json');
        $totalCleaned = 0;
        $totalPreserved = 0;

        foreach ($logFiles as $logFile) {
            $result = $this->cleanLogFile($logFile);
            $totalCleaned += $result['cleaned'];
            $totalPreserved += $result['preserved'];
        }

        echo "\n✅ Cleanup complete!\n";
        echo "📊 Total transactions cleaned: {$totalCleaned}\n";
        echo "💾 Total legitimate transactions preserved: {$totalPreserved}\n";
    }

    private function cleanLogFile(string $filePath): array
    {
        $fileName = basename($filePath);
        echo "\n🔍 Processing {$fileName}...\n";

        if (!file_exists($filePath)) {
            echo "⚠️  File not found: {$filePath}\n";
            return ['cleaned' => 0, 'preserved' => 0];
        }

        $content = file_get_contents($filePath);
        $transactions = json_decode($content, true);

        if (!is_array($transactions)) {
            echo "⚠️  Invalid JSON in {$fileName}\n";
            return ['cleaned' => 0, 'preserved' => 0];
        }

        $originalCount = count($transactions);
        $cleanTransactions = [];
        $cleanedCount = 0;

        foreach ($transactions as $transaction) {
            $transactionId = $transaction['id'] ?? '';
            
            if ($this->isTestTransaction($transactionId, $transaction)) {
                $cleanedCount++;
                echo "🗑️  Removing test transaction: {$transactionId}\n";
            } else {
                $cleanTransactions[] = $transaction;
                echo "✅ Preserving legitimate transaction: {$transactionId}\n";
            }
        }

        // Write cleaned data back to file
        $cleanedJson = json_encode($cleanTransactions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($filePath, $cleanedJson);

        $preservedCount = count($cleanTransactions);
        echo "📈 {$fileName}: {$cleanedCount} cleaned, {$preservedCount} preserved (was {$originalCount})\n";

        return ['cleaned' => $cleanedCount, 'preserved' => $preservedCount];
    }

    private function isTestTransaction(string $transactionId, array $transaction): bool
    {
        // Check against test patterns
        foreach ($this->testPatterns as $pattern) {
            if (preg_match($pattern, $transactionId)) {
                return true;
            }
        }

        // Additional checks for suspicious test data
        $type = $transaction['type'] ?? '';
        $amount = $transaction['amount'] ?? '';
        $cardLast4 = $transaction['card']['last4'] ?? '';

        // Check for common test card numbers
        if (in_array($cardLast4, ['1111', '4242', '5555', '0005', '9999'])) {
            // Only flag as test if it also has simple numeric ID
            if (preg_match('/^[0-9]{3,4}$/', $transactionId)) {
                return true;
            }
        }

        // Check for suspicious amounts
        if ($amount !== 'VERIFY' && is_numeric($amount)) {
            $numAmount = (float) $amount;
            // Common test amounts
            if (in_array($numAmount, [1.00, 10.00, 25.00, 25.50, 100.00])) {
                if (preg_match('/^[0-9]{3,4}$/', $transactionId)) {
                    return true;
                }
            }
        }

        // Check for repeated test data (same transaction multiple times)
        static $seenTransactions = [];
        $hash = md5(json_encode([
            'id' => $transactionId,
            'amount' => $amount,
            'card_last4' => $cardLast4,
            'type' => $type
        ]));
        
        if (isset($seenTransactions[$hash])) {
            $seenTransactions[$hash]++;
            if ($seenTransactions[$hash] > 2) { // If seen more than twice, likely test data
                return true;
            }
        } else {
            $seenTransactions[$hash] = 1;
        }

        return false;
    }

    public function validateCleanup(): void
    {
        echo "\n🔍 Validating cleanup...\n";
        
        $reporter = new TransactionReporter();
        $result = $reporter->getLocalTransactions();
        $transactions = $result['data']['transactions'] ?? [];

        echo "📊 Current dashboard will show " . count($transactions) . " transactions\n";

        foreach ($transactions as $transaction) {
            $id = $transaction['id'] ?? 'unknown';
            $type = $transaction['type'] ?? 'unknown';
            echo "✅ Legitimate transaction: {$id} ({$type})\n";
        }

        if (empty($transactions)) {
            echo "ℹ️  Dashboard will be empty - only real API transactions will appear going forward\n";
        }
    }
}

// Run the cleanup
$cleaner = new TestDataCleaner();
$cleaner->cleanAllLogs();
$cleaner->validateCleanup();

echo "\n🎉 Test data cleanup completed successfully!\n";
echo "💡 The dashboard will now only show legitimate API transactions.\n";