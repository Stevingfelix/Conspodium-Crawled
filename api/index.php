<?php
/**
 * api/index.php
 * Single API Front Controller / Router for Vercel Serverless Function deployment.
 * Keeps serverless function count to 1 (well below Vercel Hobby 12-function limit).
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Token");

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

// Normalize path: handle /api/posts.php, /api/posts, or /api/
$path = preg_replace('/^\/api\/?/', '', $uri);
$path = trim($path, '/');

$parts = explode('/', $path);
$endpoint = str_replace('.php', '', $parts[0]);

if (empty($endpoint) || $endpoint === 'index') {
    $endpoint = 'health';
}

$targetFile = __DIR__ . '/' . $endpoint . '.php';

if (file_exists($targetFile) && $endpoint !== 'index') {
    require $targetFile;
} else {
    header("Content-Type: application/json");
    http_response_code(404);
    echo json_encode(["success" => false, "error" => "API endpoint not found: {$endpoint}"]);
}
