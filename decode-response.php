<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Test with debugging and no compression
$appId = $_ENV['GP_APP_ID'];
$appKey = $_ENV['GP_APP_KEY'];
$baseUrl = $_ENV['GP_BASE_URL'] ?? 'https://apis.sandbox.globalpay.com';

$credentials = base64_encode($appId . ':' . $appKey);

echo "Testing token generation without compression...\n";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $baseUrl . '/ucp/accesstoken',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Basic ' . $credentials,
        'Accept: application/json',
        'Accept-Encoding: identity', // Disable compression
        'X-GP-Version: 2021-03-22'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'app_id' => $appId,
        'secret' => $appKey,
        'nonce' => uniqid(),
        'grant_type' => 'client_credentials'
    ]),
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);
curl_close($curl);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

if ($httpCode === 400) {
    echo "\n❌ The GP_APP_ID and GP_APP_KEY credentials appear to be invalid.\n";
    echo "Please verify:\n";
    echo "1. The credentials are correct in your .env file\n";
    echo "2. The credentials are for the sandbox environment\n";
    echo "3. The Global Payments account is properly configured\n";
    echo "4. The credentials have not expired\n";
}