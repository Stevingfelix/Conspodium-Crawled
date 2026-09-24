<?php
/**
 * api/index.php
 * Single API Front Controller / Router for Vercel Serverless Function deployment.
 * Resolves requested endpoint cleanly via __vercel_path or REQUEST_URI.
 */

error_reporting(0);
ini_set('display_errors', '0');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Token, X-CSRF-Token");

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Extract requested path from Vercel rewrite parameter or request URI
    $rawPath = $_GET['__vercel_path'] ?? $_SERVER['REQUEST_URI'] ?? '';

    // If __vercel_path contains query string (e.g. auth.php?action=login), parse query params into $_GET
    if (strpos($rawPath, '?') !== false) {
        $queryString = parse_url($rawPath, PHP_URL_QUERY);
        if ($queryString) {
            parse_str($queryString, $extraGet);
            foreach ($extraGet as $k => $v) {
                if (!isset($_GET[$k])) {
                    $_GET[$k] = $v;
                }
            }
        }
    }

    $reqPath = parse_url($rawPath, PHP_URL_PATH);

    // Strip leading /api/ or api/
    $path = preg_replace('/^\/?(api\/)?/', '', $reqPath);
    $path = trim($path, '/');

    $parts = explode('/', $path);
    $endpoint = str_replace('.php', '', $parts[0] ?? '');

    // If endpoint resolves to empty or index, fallback check
    if (empty($endpoint) || $endpoint === 'index') {
        $altUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $altPath = preg_replace('/^\/?(api\/)?/', '', $altUri);
        $altEndpoint = str_replace('.php', '', explode('/', trim($altPath, '/'))[0] ?? '');
        if (!empty($altEndpoint) && $altEndpoint !== 'index') {
            $endpoint = $altEndpoint;
        } else {
            $endpoint = 'health';
        }
    }

    $targetFile = __DIR__ . '/' . $endpoint . '.php';

    if (file_exists($targetFile) && $endpoint !== 'index') {
        require $targetFile;
    } else {
        header("Content-Type: application/json");
        http_response_code(404);
        echo json_encode([
            "success" => false, 
            "error" => "API endpoint not found: {$endpoint}",
            "requested_path" => $reqPath
        ]);
    }
} catch (Throwable $t) {
    header("Content-Type: application/json");
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Server Error: " . $t->getMessage(),
        "file" => basename($t->getFile()),
        "line" => $t->getLine()
    ]);
}
