<?php
/**
 * Development Server Router
 * 
 * This router handles URL rewriting for the PHP built-in server
 * to properly serve both public HTML files and API endpoints.
 */

$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove query string for path matching
$clean_path = strtok($path, '?');

// Route API requests to the api directory
if (strpos($clean_path, '/api/') === 0) {
    $api_file = __DIR__ . $clean_path;
    if (file_exists($api_file) && is_file($api_file)) {
        require $api_file;
        return true;
    }
}

// Handle direct config.php access for compatibility
if ($clean_path === '/config.php') {
    require __DIR__ . '/api/config.php';
    return true;
}

// Handle other API endpoints for backwards compatibility
$api_endpoints = [
    '/verify-card.php' => '/api/verify-card.php',
    '/process-payment.php' => '/api/process-payment.php', 
    '/heartland-process-payment.php' => '/api/heartland-process-payment.php',
    '/transactions-api.php' => '/api/transactions-api.php'
];

if (isset($api_endpoints[$clean_path])) {
    $api_file = __DIR__ . $api_endpoints[$clean_path];
    if (file_exists($api_file)) {
        require $api_file;
        return true;
    }
}

// Serve static files from public directory
if ($clean_path === '/' || $clean_path === '/index.html') {
    require __DIR__ . '/public/index.html';
    return true;
}

// Handle specific HTML files
if (in_array($clean_path, ['/dashboard.html', '/card-verification.html', '/payment.html'])) {
    require __DIR__ . '/public' . $clean_path;
    return true;
}

// Check if it's a public file
$public_file = __DIR__ . '/public' . $clean_path;
if (file_exists($public_file) && is_file($public_file)) {
    // Let PHP serve the file naturally for CSS, JS, images, etc.
    return false;
}

// Check if it's a root-level file
$root_file = __DIR__ . $clean_path;
if (file_exists($root_file) && is_file($root_file)) {
    return false;
}

// 404 for everything else
http_response_code(404);
echo json_encode([
    'success' => false,
    'message' => 'Endpoint not found',
    'error' => [
        'code' => 'NOT_FOUND',
        'path' => $clean_path
    ]
]);
return true;