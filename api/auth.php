<?php
// api/auth.php - Admin Authentication & Session Management API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Admin-Token, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

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
    @session_start();
    register_shutdown_function(function() {
        @session_write_close();
    });
}

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ── GET CURRENT LOGGED IN ADMIN / CHECK AUTH ─────────────────────────────────
if ($method === 'GET' && ($action === 'me' || $action === 'check' || $action === 'check_auth' || empty($action))) {
    $clientToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? $_GET['token'] ?? '';
    if (empty($clientToken) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $clientToken = $matches[1];
        }
    }

    if (!empty($clientToken)) {
        $stmt = $pdo->prepare("SELECT id, username, email, name, role, session_token FROM admins WHERE session_token = ? AND session_token IS NOT NULL AND session_token != ''");
        $stmt->execute([$clientToken]);
        $admin = $stmt->fetch();

        if ($admin && !empty($admin['id'])) {
            $userData = [
                "id" => intval($admin['id']),
                "username" => $admin['username'],
                "email" => $admin['email'],
                "name" => $admin['name'],
                "role" => $admin['role']
            ];
            $_SESSION['admin_user'] = $userData;
            echo json_encode(["success" => true, "authenticated" => true, "user" => $userData]);
            return;
        } else {
            // Invalid or expired token
            $_SESSION = [];
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "authenticated" => false,
                "error" => "Invalid or expired session token"
            ]);
            return;
        }
    }

    if (!empty($_SESSION['admin_user']) && !empty($_SESSION['admin_user']['id'])) {
        echo json_encode([
            "success" => true,
            "authenticated" => true,
            "user" => $_SESSION['admin_user']
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "authenticated" => false,
            "error" => "Not authenticated"
        ]);
    }
    return;
}

// ── ADMIN LOGIN ─────────────────────────────────────────────────────────────
function csp_detect_client_info() {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }
    
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device';
    $browser = 'Browser';
    if (stripos($ua, 'Edg') !== false) {
        $browser = 'Microsoft Edge';
    } elseif (stripos($ua, 'Chrome') !== false && stripos($ua, 'Edg') === false) {
        $browser = 'Google Chrome';
    } elseif (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) {
        $browser = 'Apple Safari';
    } elseif (stripos($ua, 'Firefox') !== false) {
        $browser = 'Mozilla Firefox';
    } elseif (stripos($ua, 'Opera') !== false || stripos($ua, 'OPR') !== false) {
        $browser = 'Opera';
    }

    $os = 'Workstation PC';
    if (stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS') !== false) {
        $os = 'Mac (macOS)';
    } elseif (stripos($ua, 'Windows NT 10.0') !== false || stripos($ua, 'Windows NT 11.0') !== false) {
        $os = 'Windows 10/11 PC';
    } elseif (stripos($ua, 'Windows') !== false) {
        $os = 'Windows PC';
    } elseif (stripos($ua, 'iPhone') !== false) {
        $os = 'Apple iPhone';
    } elseif (stripos($ua, 'iPad') !== false) {
        $os = 'Apple iPad';
    } elseif (stripos($ua, 'Android') !== false) {
        $os = 'Android Device';
    } elseif (stripos($ua, 'Linux') !== false) {
        $os = 'Linux PC';
    }

    $device = "$browser on $os";

    $location = 'Local Machine';
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0 || strpos($ip, '172.16.') === 0) {
        $location = 'Localhost (LAN / Development PC)';
    } else {
        $country = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';
        $city = $_SERVER['HTTP_CF_IPCITY'] ?? '';
        if ($city && $country) {
            $location = "$city, $country";
        } elseif ($country) {
            $location = "Public ($country)";
        } else {
            $location = "Live Remote Session";
        }
    }

    return [
        'ip' => $ip,
        'device' => $device,
        'location' => $location,
        'ua' => $ua
    ];
}

// ── GET SECURITY LOGS ────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'security_logs') {
    try {
        $stmt = $pdo->query("SELECT * FROM admin_security_logs ORDER BY id DESC LIMIT 50");
        $logs = $stmt->fetchAll() ?: [];

        // If table is empty or has only older logs, seed or verify active session
        if (empty($logs)) {
            $client = csp_detect_client_info();
            $adminUser = $_SESSION['admin_user']['username'] ?? 'admin';
            $adminEmail = $_SESSION['admin_user']['email'] ?? 'admin@conspodium.com';
            
            $ins = $pdo->prepare("INSERT INTO admin_security_logs (admin_id, admin_username, admin_email, ip_address, location, user_agent, browser_device, status, created_at) VALUES (1, ?, ?, ?, ?, ?, ?, 'Active Session', datetime('now', 'localtime'))");
            $ins->execute([$adminUser, $adminEmail, $client['ip'], $client['location'], $client['ua'], $client['device']]);
            
            $logs = $pdo->query("SELECT * FROM admin_security_logs ORDER BY id DESC LIMIT 50")->fetchAll() ?: [];
        }

        echo json_encode(["success" => true, "logs" => $logs]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    return;
}

if ($method === 'POST' && ($action === 'login' || (empty($action) && isset($input['username'])))) {
    if (!csp_check_rate_limit('admin_login', 30, 60)) {
        http_response_code(429);
        echo json_encode(["success" => false, "error" => "Too many failed login attempts. Please wait 60 seconds."]);
        return;
    }

    $username = csp_sanitize($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (!$username || !$password) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Username and password are required"]);
        return;
    }

    $client = csp_detect_client_info();
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $token = bin2hex(random_bytes(32));
        
        try {
            $upToken = $pdo->prepare("UPDATE admins SET session_token = ? WHERE id = ?");
            $upToken->execute([$token, $admin['id']]);

            // Mark previous active sessions as closed
            $pdo->prepare("UPDATE admin_security_logs SET status = 'Closed' WHERE admin_id = ? AND status = 'Active Session'")
                ->execute([$admin['id']]);

            // Record new active session
            $insLog = $pdo->prepare("INSERT INTO admin_security_logs (admin_id, admin_username, admin_email, ip_address, location, user_agent, browser_device, status, session_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active Session', ?, datetime('now', 'localtime'))");
            $insLog->execute([
                $admin['id'],
                $admin['username'],
                $admin['email'],
                $client['ip'],
                $client['location'],
                $client['ua'],
                $client['device'],
                $token
            ]);
        } catch (Exception $e) {}

        $userData = [
            "id" => intval($admin['id']),
            "username" => $admin['username'],
            "email" => $admin['email'],
            "name" => $admin['name'],
            "role" => $admin['role']
        ];
        
        $_SESSION['admin_user'] = $userData;
        $_SESSION['admin_session_token'] = $token;

        echo json_encode([
            "success" => true,
            "user" => $userData,
            "token" => $token,
            "message" => "Welcome back, " . $admin['name'] . "!"
        ]);
    } else {
        // Record failed login attempt
        try {
            $insLog = $pdo->prepare("INSERT INTO admin_security_logs (admin_id, admin_username, admin_email, ip_address, location, user_agent, browser_device, status, created_at) VALUES (NULL, ?, NULL, ?, ?, ?, ?, 'Failed Attempt', datetime('now', 'localtime'))");
            $insLog->execute([
                $username,
                $client['ip'],
                $client['location'],
                $client['ua'],
                $client['device']
            ]);
        } catch (Exception $e) {}

        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Invalid username or password"]);
    }
    return;
}

// ── ADMIN LOGOUT ────────────────────────────────────────────────────────────
if ($method === 'POST' && ($action === 'logout' || $action === 'signout')) {
    $clientToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? $_GET['token'] ?? '';
    if (empty($clientToken) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $clientToken = $matches[1];
        }
    }

    if (!empty($_SESSION['admin_user']['id'])) {
        try {
            $upToken = $pdo->prepare("UPDATE admins SET session_token = NULL WHERE id = ?");
            $upToken->execute([$_SESSION['admin_user']['id']]);
            
            $pdo->prepare("UPDATE admin_security_logs SET status = 'Logged Out' WHERE admin_id = ? AND status = 'Active Session'")
                ->execute([$_SESSION['admin_user']['id']]);
        } catch (Exception $e) {}
    } elseif (!empty($clientToken)) {
        try {
            $upToken = $pdo->prepare("UPDATE admins SET session_token = NULL WHERE session_token = ?");
            $upToken->execute([$clientToken]);

            $pdo->prepare("UPDATE admin_security_logs SET status = 'Logged Out' WHERE session_token = ?")
                ->execute([$clientToken]);
        } catch (Exception $e) {}
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    @session_destroy();

    echo json_encode(["success" => true, "message" => "Logged out successfully"]);
    return;
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
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$userId]);
    $admin = $stmt->fetch();

    if (!$admin) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Admin user not found"]);
        return;
    }

    // Check if username/email belongs to another admin
    $checkStmt = $pdo->prepare("SELECT id FROM admins WHERE (username = ? OR email = ?) AND id != ?");
    $checkStmt->execute([$username, $email, $userId]);
    if ($checkStmt->fetch()) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Username or email is already in use by another admin"]);
        return;
    }

    if ($newPass) {
        if (!$currentPass || !password_verify($currentPass, $admin['password_hash'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Current password is incorrect"]);
            return;
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
    return;
}
