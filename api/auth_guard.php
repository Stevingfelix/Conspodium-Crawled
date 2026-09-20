<?php
// api/auth_guard.php - Security Guard for Protected Admin API Routes

$isVercel = getenv('VERCEL') || !empty($_ENV['VERCEL']) || !empty($_SERVER['VERCEL']) ||
            !empty($_ENV['VERCEL_ENV']) || !empty($_SERVER['VERCEL_ENV']) ||
            !empty($_ENV['NOW_REGION']) || !empty($_SERVER['NOW_REGION']) ||
            strpos(__DIR__, '/var/task') !== false || file_exists('/var/task');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
           (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

if (session_status() === PHP_SESSION_NONE) {
    if ($isVercel) {
        @session_save_path('/tmp');
    }
    @session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path' => '/',
        'secure' => $isHttps,
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
    $clientToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? $_GET['token'] ?? '';
    if (empty($clientToken) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $clientToken = $matches[1];
        }
    }

    if (!empty($clientToken)) {
        global $pdo;
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT id, username, email, name, role FROM admins WHERE session_token = ?");
            $stmt->execute([$clientToken]);
            $admin = $stmt->fetch();
            if ($admin && !empty($admin['id'])) {
                $_SESSION['admin_user'] = [
                    "id" => intval($admin['id']),
                    "username" => $admin['username'],
                    "email" => $admin['email'],
                    "name" => $admin['name'],
                    "role" => $admin['role']
                ];
                return;
            }
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
