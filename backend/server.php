<?php

/**
 * Laravel development server router script.
 *
 * The PHP built-in server doesn't support .htaccess or URL rewriting,
 * so this script routes all requests through public/index.php.
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/'
);

// If the request is for an actual file in the public directory, serve it directly
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    return false;
}

// Otherwise, route through Laravel's front controller
require_once __DIR__ . '/public/index.php';
