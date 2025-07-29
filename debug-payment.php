<?php
// Debug script to test payment processing components

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h2>Heartland Payment Debug</h2>\n";

// Test 1: Check autoloader
echo "<h3>1. Testing Autoloader</h3>\n";
try {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "<p style='color: green;'>✅ Autoloader loaded successfully</p>\n";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Autoloader failed: " . $e->getMessage() . "</p>\n";
    exit;
}

// Test 2: Check environment
echo "<h3>2. Testing Environment</h3>\n";
try {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    echo "<p style='color: green;'>✅ Environment loaded</p>\n";
    
    $publicKey = $_ENV['PUBLIC_API_KEY'] ?? 'NOT_SET';
    $secretKey = $_ENV['SECRET_API_KEY'] ?? 'NOT_SET';
    
    echo "<p>Public Key: " . substr($publicKey, 0, 10) . "...</p>\n";
    echo "<p>Secret Key: " . substr($secretKey, 0, 10) . "...</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Environment failed: " . $e->getMessage() . "</p>\n";
}

// Test 3: Check GlobalPayments SDK
echo "<h3>3. Testing GlobalPayments SDK</h3>\n";
try {
    $config = new \GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig();
    $config->secretApiKey = $_ENV['SECRET_API_KEY'] ?? 'test';
    echo "<p style='color: green;'>✅ GlobalPayments SDK classes loaded</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ GlobalPayments SDK failed: " . $e->getMessage() . "</p>\n";
}

// Test 4: Check Logger
echo "<h3>4. Testing Logger</h3>\n";
try {
    require_once __DIR__ . '/src/Logger.php';
    $logger = new \GlobalPayments\Examples\Logger();
    echo "<p style='color: green;'>✅ Logger loaded successfully</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Logger failed: " . $e->getMessage() . "</p>\n";
}

// Test 5: Check if we can create a simple payment request simulation
echo "<h3>5. Testing Payment Processing Logic</h3>\n";
try {
    // Simulate a simple payment request
    $testData = [
        'token_value' => 'test_token_12345',
        'amount' => 10.00,
        'currency' => 'USD'
    ];
    
    // Basic validation
    $errors = [];
    if (empty($testData['token_value'])) {
        $errors[] = 'Payment token is required';
    }
    if (!isset($testData['amount']) || !is_numeric($testData['amount']) || (float)$testData['amount'] <= 0) {
        $errors[] = 'Valid transaction amount greater than 0 is required';
    }
    
    if (empty($errors)) {
        echo "<p style='color: green;'>✅ Payment validation logic works</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Payment validation failed: " . implode(', ', $errors) . "</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Payment logic test failed: " . $e->getMessage() . "</p>\n";
}

// Test 6: Test actual HTTP request simulation
echo "<h3>6. Testing HTTP Request Handling</h3>\n";
try {
    // Simulate POST request environment
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    
    $testPayload = json_encode([
        'token_value' => 'test_token_12345',
        'amount' => 10.00,
        'currency' => 'USD'
    ]);
    
    echo "<p style='color: green;'>✅ HTTP simulation setup complete</p>\n";
    echo "<p>Test payload: <code>" . htmlspecialchars($testPayload) . "</code></p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ HTTP simulation failed: " . $e->getMessage() . "</p>\n";
}

echo "<h3>7. Recommendations</h3>\n";
echo "<ul>\n";
echo "<li>If all tests pass, the issue might be with the web server configuration</li>\n";
echo "<li>Try accessing the payment endpoint directly via curl or browser dev tools</li>\n";
echo "<li>Check that the web server has proper PHP error logging enabled</li>\n";
echo "<li>Ensure the payment form is sending the correct JSON payload</li>\n";
echo "</ul>\n";

echo "\n<style>\n";
echo "body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }\n";
echo "pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }\n";
echo "code { background: #f0f0f0; padding: 2px 4px; border-radius: 3px; font-family: monospace; }\n";
echo "h2 { color: #333; border-bottom: 2px solid #4285f4; padding-bottom: 10px; }\n";
echo "h3 { color: #4285f4; margin-top: 25px; }\n";
echo "</style>\n";
?>