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
$clean_path = rawurldecode(strtok($path, '?'));

// Never serve dot files, vendor/, logs/ or anything outside the served folders
if (preg_match('#(^|/)(\.|vendor/|logs/|node_modules/)#', $clean_path)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Endpoint not found', 'error' => ['code' => 'NOT_FOUND']]);
    return true;
}

// Route API requests to the api directory
if (preg_match('#^/api/[A-Za-z0-9_-]+\.php$#', $clean_path)) {
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
    header('Location: /public/index.html');
    return true;
}

// Handle specific HTML files
if (in_array($clean_path, ['/dashboard.html', '/card-verification.html', '/payment.html'])) {
    require __DIR__ . '/public' . $clean_path;
    return true;
}

// Handle static assets (CSS, JS, images) from public directory
$public_file = __DIR__ . '/public' . $clean_path;
if (file_exists($public_file) && is_file($public_file)) {
    // Set appropriate content type based on file extension
    $extension = pathinfo($clean_path, PATHINFO_EXTENSION);
    $content_types = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject'
    ];
    
    if (isset($content_types[$extension])) {
        header('Content-Type: ' . $content_types[$extension]);
    }
    
    // Serve the file directly
    readfile($public_file);
    return true;
}

// Pages and assets under /public/ (only real files inside public/)
if (strpos($clean_path, '/public/') === 0) {
    $public_root = realpath(__DIR__ . '/public');
    $requested = realpath(__DIR__ . $clean_path);
    if ($requested !== false && is_file($requested) && strpos($requested, $public_root . DIRECTORY_SEPARATOR) === 0) {
        return false;
    }
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