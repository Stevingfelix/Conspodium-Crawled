<?php
// api/auth.php - Admin Authentication & Session Management API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$isVercel = getenv('VERCEL') || !empty($_ENV['VERCEL']) || !empty($_SERVER['VERCEL']) ||
            !empty($_ENV['VERCEL_ENV']) || !empty($_SERVER['VERCEL_ENV']) ||
            !empty($_ENV['NOW_REGION']) || !empty($_SERVER['NOW_REGION']) ||
            strpos(__DIR__, '/var/task') !== false || file_exists('/var/task');

if (session_status() === PHP_SESSION_NONE) {
    if ($isVercel) {
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
if ($method === 'POST' && ($action === 'login' || (empty($action) && isset($input['username'])))) {
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

// ── UPDATE ADMIN PROFILE (USERNAME, DISPLAY NAME, EMAIL, PASSWORD) ───────────
if ($method === 'POST' && $action === 'update_profile') {
    require_once __DIR__ . '/auth_guard.php';
    requireAdmin();
    
    $userId = $_SESSION['admin_user']['id'] ?? 0;
    $username = trim($input['username'] ?? '');
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $currentPass = trim($input['currentPassword'] ?? '');
    $newPass = trim($input['newPassword'] ?? '');

    if (!$username || !$name || !$email) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Username, display name, and email are required"]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $admin = $stmt->fetch();

    if (!$admin) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Admin user not found"]);
        exit;
    }

    // Check if username/email belongs to another admin
    $checkStmt = $pdo->prepare("SELECT id FROM admins WHERE (username = ? OR email = ?) AND id != ?");
    $checkStmt->execute([$username, $email, $userId]);
    if ($checkStmt->fetch()) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Username or email is already in use by another admin"]);
        exit;
    }

    if ($newPass) {
        if (!$currentPass || !password_verify($currentPass, $admin['password_hash'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Current password is incorrect"]);
            exit;
        }
        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $updateStmt = $pdo->prepare("UPDATE admins SET username = ?, name = ?, email = ?, password_hash = ? WHERE id = ?");
        $updateStmt->execute([$username, $name, $email, $newHash, $userId]);
    } else {
        $updateStmt = $pdo->prepare("UPDATE admins SET username = ?, name = ?, email = ? WHERE id = ?");
        $updateStmt->execute([$username, $name, $email, $userId]);
    }

    // Update session
    $_SESSION['admin_user']['username'] = $username;
    $_SESSION['admin_user']['name'] = $name;
    $_SESSION['admin_user']['email'] = $email;

    echo json_encode([
        "success" => true,
        "user" => $_SESSION['admin_user'],
        "message" => "Admin account credentials updated successfully!"
    ]);
    exit;
}
