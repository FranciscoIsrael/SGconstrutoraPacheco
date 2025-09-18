<?php
// PHP Development Server configuration
// This file ensures proper routing for the construction management system

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files directly
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Route API requests
if (strpos($uri, '/api/') === 0) {
    $apiFile = __DIR__ . $uri;
    if (file_exists($apiFile)) {
        require $apiFile;
        return true;
    }
}

// Route everything else to index.php
require_once __DIR__ . '/index.php';
?>