<?php
// api/settings.php - Platform Settings & Admin Profile Management API
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
    @session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_guard.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ── GET ALL SETTINGS ─────────────────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $pdo->query("SELECT key, value FROM site_settings");
    $rows = $stmt->fetchAll();

    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['key']] = $row['value'];
    }

    echo json_encode([
        "success" => true,
        "settings" => $settings
    ]);
    exit;
}

// ── UPDATE SETTINGS & ADMIN PROFILE ──────────────────────────────────────────
if ($method === 'POST') {
    requireAdmin();

    $updatedKeys = 0;
    $message = "Settings updated successfully!";

    // Update key-value settings if provided
    if (!empty($input['settings']) && is_array($input['settings'])) {
        $stmtSet = $pdo->prepare("
            INSERT INTO site_settings (key, value)
            VALUES (?, ?)
            ON CONFLICT(key) DO UPDATE SET value = excluded.value
        ");

        foreach ($input['settings'] as $key => $val) {
            $stmtSet->execute([strval($key), strval($val)]);
            $updatedKeys++;
        }
    }

    // Update Admin Profile & Security Password if provided
    if (!empty($input['profile']) && is_array($input['profile'])) {
        $profile = $input['profile'];
        $adminId = intval($_SESSION['admin_user']['id'] ?? 1);

        $adminStmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
        $adminStmt->execute([$adminId]);
        $admin = $adminStmt->fetch();

        if ($admin) {
            $newName = trim($profile['name'] ?? $admin['name']);
            $newEmail = trim($profile['email'] ?? $admin['email']);
            $currentPassword = trim($profile['currentPassword'] ?? '');
            $newPassword = trim($profile['newPassword'] ?? '');

            if ($newPassword) {
                if (!$currentPassword || !password_verify($currentPassword, $admin['password_hash'])) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "error" => "Current password verification failed."]);
                    exit;
                }
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updatePass = $pdo->prepare("UPDATE admins SET name = ?, email = ?, password_hash = ? WHERE id = ?");
                $updatePass->execute([$newName, $newEmail, $newHash, $adminId]);
            } else {
                $updateProfile = $pdo->prepare("UPDATE admins SET name = ?, email = ? WHERE id = ?");
                $updateProfile->execute([$newName, $newEmail, $adminId]);
            }

            // Sync updated info into session
            $_SESSION['admin_user']['name'] = $newName;
            $_SESSION['admin_user']['email'] = $newEmail;
        }
    }

    echo json_encode([
        "success" => true,
        "message" => $message,
        "user" => $_SESSION['admin_user'] ?? null
    ]);
    exit;
}
