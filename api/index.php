<?php
// Vercel Serverless Function Gateway for AA TRADERS PDMS

// Ensure working directory is always the project root
chdir(__DIR__ . '/..');

// Handle Vercel session storage
if (getenv('VERCEL') || isset($_ENV['VERCEL'])) {
    $tmpDir = sys_get_temp_dir();
    if (is_dir($tmpDir) && is_writable($tmpDir) && session_status() === PHP_SESSION_NONE) {
        @session_save_path($tmpDir);
    }
}

// Extract requested path from URI
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$uriPath = parse_url($requestUri, PHP_URL_PATH);
$path = trim($uriPath, '/');

// Prevent self-recursion: strip leading 'api/' prefix if present
if (str_starts_with($path, 'api/')) {
    $path = substr($path, 4);
}
if ($path === 'api') {
    $path = '';
}

// 1. Static Assets Handler (CSS, JS, Images, Fonts)
if (str_starts_with($path, 'assets/')) {
    $staticFile = __DIR__ . '/../' . $path;
    if (file_exists($staticFile) && is_file($staticFile)) {
        $ext = strtolower(pathinfo($staticFile, PATHINFO_EXTENSION));
        $mimes = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf'
        ];
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
        }
        readfile($staticFile);
        exit;
    }
}

// 2. Default root routing
if ($path === '' || $path === 'index.php') {
    require __DIR__ . '/../index.php';
    exit;
}

// 3. Exact matching PHP file in root
$targetFile = __DIR__ . '/../' . $path;
if (file_exists($targetFile) && is_file($targetFile) && realpath($targetFile) !== realpath(__FILE__)) {
    require $targetFile;
    exit;
}

// 4. Match with .php extension if omitted
if (file_exists($targetFile . '.php') && is_file($targetFile . '.php')) {
    require $targetFile . '.php';
    exit;
}

// 5. Fallback to index.php
require __DIR__ . '/../index.php';
