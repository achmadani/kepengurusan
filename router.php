<?php
/**
 * Front router for PHP's built-in web server (development only).
 * Usage: php82 -S 127.0.0.1:8099 router.php
 *
 * This project's root IS the web home (matches how it's deployed on
 * Apache/Nginx — see .htaccess and DEPLOY.md):
 * - /api/*  -> handled by api/index.php
 * - known static assets are served with the correct MIME type
 * - anything else falls back to index.html (SPA)
 * - non-whitelisted files (.php, .md, .sh, dotfiles, etc.) are never
 *   served as raw static content
 */

const STATIC_MIMES = [
    'html'  => 'text/html; charset=utf-8',
    'css'   => 'text/css; charset=utf-8',
    'js'    => 'application/javascript; charset=utf-8',
    'json'  => 'application/json; charset=utf-8',
    'svg'   => 'image/svg+xml',
    'png'   => 'image/png',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'gif'   => 'image/gif',
    'ico'   => 'image/x-icon',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'map'   => 'application/json',
];

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API requests
if (str_starts_with($uri, '/api')) {
    require __DIR__ . '/api/index.php';
    return true;
}

$root   = realpath(__DIR__);
$target = realpath($root . $uri);
$ext    = $target ? strtolower(pathinfo($target, PATHINFO_EXTENSION)) : '';

// Serve an existing, whitelisted static file (guards path traversal too).
if ($uri !== '/'
    && $target !== false
    && str_starts_with($target, $root)
    && is_file($target)
    && isset(STATIC_MIMES[$ext])) {
    header('Content-Type: ' . STATIC_MIMES[$ext]);
    readfile($target);
    return true;
}

// SPA fallback.
header('Content-Type: ' . STATIC_MIMES['html']);
readfile($root . '/index.html');
return true;
