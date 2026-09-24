<?php
/**
 * router.php
 * Conspodium Local Development Server Router for `php -S localhost:8080`
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicDir = __DIR__ . '/public';
$filePath = $publicDir . $uri;

// 0. Route API calls cleanly to api/index.php engine
if (strpos($uri, '/api/') === 0) {
    $_GET['__vercel_path'] = preg_replace('/^\/api\//', '', $uri);
    require __DIR__ . '/api/index.php';
    return true;
}

// 1. Direct file pass-through for existing static assets and PHP API scripts
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false;
}

// 2. Directory with index.html (e.g. /dashboard/ or /stories/)
if (is_dir($filePath) && file_exists(rtrim($filePath, '/') . '/index.html')) {
    require rtrim($filePath, '/') . '/index.html';
    return true;
}

// 3. Clean URL without trailing slash (e.g. /dashboard or /stories or /post/we-are-the-world)
if (file_exists($filePath . '/index.html')) {
    require $filePath . '/index.html';
    return true;
}

// Default fallback
return false;
