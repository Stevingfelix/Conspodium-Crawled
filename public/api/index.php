<?php
/**
 * api/index.php
 * Single API Front Controller / Router for Vercel Serverless Function deployment.
 * Resolves requested endpoint cleanly via __vercel_path or REQUEST_URI.
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Token, X-CSRF-Token");

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Extract requested path from Vercel rewrite parameter or request URI
$reqPath = $_GET['__vercel_path'] ?? $_SERVER['REQUEST_URI'] ?? '';
$reqPath = parse_url($reqPath, PHP_URL_PATH);

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
