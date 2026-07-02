<?php
/**
 * Front router for PHP's built-in web server.
 * Usage: php82 -S 127.0.0.1:8099 router.php
 *
 * - /api/*  -> handled by api/index.php
 * - static files under public/ are served with the correct MIME type
 * - anything else falls back to public/index.html (SPA)
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API requests
if (str_starts_with($uri, '/api')) {
    require __DIR__ . '/api/index.php';
    return true;
}

$publicDir = realpath(__DIR__ . '/public');
$target    = realpath($publicDir . $uri);

// Serve an existing static file (guard against path traversal).
if ($uri !== '/'
    && $target !== false
    && str_starts_with($target, $publicDir)
    && is_file($target)) {
    serve_file($target);
    return true;
}

// SPA fallback.
serve_file($publicDir . '/index.html');
return true;

function serve_file(string $path): void
{
    static $mimes = [
        'html' => 'text/html; charset=utf-8',
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'map'  => 'application/json',
    ];

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    readfile($path);
}
