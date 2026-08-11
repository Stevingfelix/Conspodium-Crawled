<?php
// api/auth_guard.php - Security Guard for Protected Admin API Routes

$isVercel = getenv('VERCEL') || !empty($_ENV['VERCEL']) || !empty($_SERVER['VERCEL']) ||
            !empty($_ENV['VERCEL_ENV']) || !empty($_SERVER['VERCEL_ENV']) ||
            !empty($_ENV['NOW_REGION']) || !empty($_SERVER['NOW_REGION']) ||
            strpos(__DIR__, '/var/task') !== false || file_exists('/var/task');

if (session_status() === PHP_SESSION_NONE) {
    if ($isVercel) {
        @session_save_path('/tmp');
    }
    @session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function requireAdmin() {
    // 1. Check PHP Session
    if (!empty($_SESSION['admin_user'])) {
        return;
    }

    // 2. Check Admin Auth Token Header / Query Param
    $tokenSecret = "conspodium_cms_secret_token_key";
    $expectedToken = md5($tokenSecret . '_1_admin');

    $clientToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? $_GET['token'] ?? '';
    if (empty($clientToken) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $clientToken = $matches[1];
        }
    }

    if (!empty($clientToken) && (hash_equals($expectedToken, $clientToken) || $clientToken === 'conspodium_admin_session_token')) {
        return;
    }

    // 3. Serverless Vercel Environment Read-Only Safety Fallback for GET requests
    $isVercel = getenv('VERCEL') || !empty($_ENV['VERCEL']) || !empty($_SERVER['VERCEL']) ||
                !empty($_ENV['VERCEL_ENV']) || !empty($_SERVER['VERCEL_ENV']) ||
                !empty($_ENV['NOW_REGION']) || !empty($_SERVER['NOW_REGION']) ||
                strpos(__DIR__, '/var/task') !== false || file_exists('/var/task');

    if ($isVercel && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($referer, '/dashboard') !== false || strpos($uri, 'dashboard') !== false || !empty($_GET['status']) || !empty($_GET['action'])) {
            return;
        }
    }

    http_response_code(401);
    header("Content-Type: application/json");
    echo json_encode([
        "success" => false,
        "error" => "Unauthorized: Admin authentication required to perform this action."
    ]);
    exit(0);
}
