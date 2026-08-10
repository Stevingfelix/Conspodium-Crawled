<?php
// api/auth.php - Admin Authentication & Session Management API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (session_status() === PHP_SESSION_NONE) {
    if (getenv('VERCEL') || !empty($_ENV['VERCEL']) || !empty($_SERVER['VERCEL'])) {
        @session_save_path('/tmp');
    }
    session_start();
}

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ── GET CURRENT LOGGED IN ADMIN (ME) ─────────────────────────────────────────
if ($method === 'GET' && ($action === 'me' || empty($action))) {
    if (!empty($_SESSION['admin_user'])) {
        echo json_encode([
            "success" => true,
            "user" => $_SESSION['admin_user']
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "error" => "Not authenticated"
        ]);
    }
    exit;
}

// ── ADMIN LOGIN ─────────────────────────────────────────────────────────────
if ($method === 'POST' && ($action === 'login' || isset($input['username']))) {
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (!$username || !$password) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Username and password are required"]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $userData = [
            "id" => intval($admin['id']),
            "username" => $admin['username'],
            "email" => $admin['email'],
            "name" => $admin['name'],
            "role" => $admin['role']
        ];
        
        $_SESSION['admin_user'] = $userData;

        echo json_encode([
            "success" => true,
            "user" => $userData,
            "message" => "Welcome back, " . $admin['name'] . "!"
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Invalid username or password"]);
    }
    exit;
}

// ── ADMIN LOGOUT ────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'logout') {
    unset($_SESSION['admin_user']);
    session_destroy();
    echo json_encode(["success" => true, "message" => "Logged out successfully"]);
    exit;
}
