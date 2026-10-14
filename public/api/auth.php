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
        'lifetime' => 1800,
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
try {
    $pdo->exec("ALTER TABLE admins ADD COLUMN session_expires_at DATETIME NULL");
} catch (Exception $e) {}

if ($method === 'GET' && ($action === 'me' || $action === 'check' || $action === 'check_auth' || empty($action))) {
    $clientToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? $_GET['token'] ?? '';
    if (empty($clientToken) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $clientToken = $matches[1];
        }
    }

    if (!empty($clientToken)) {
        $stmt = $pdo->prepare("SELECT id, username, email, name, role, session_token, session_expires_at FROM admins WHERE session_token = ? AND session_token IS NOT NULL AND session_token != ''");
        $stmt->execute([$clientToken]);
        $admin = $stmt->fetch();

        if ($admin && !empty($admin['id'])) {
            // Check session expiration (30-minute inactivity sliding window)
            if (!empty($admin['session_expires_at']) && strtotime($admin['session_expires_at']) < time()) {
                // Session expired due to inactivity
                $pdo->prepare("UPDATE admins SET session_token = NULL, session_expires_at = NULL WHERE id = ?")->execute([$admin['id']]);
                $pdo->prepare("UPDATE admin_security_logs SET status = 'Closed' WHERE admin_id = ? AND status IN ('Active Session', 'Current Session')")->execute([$admin['id']]);
                $_SESSION = [];
                http_response_code(401);
                echo json_encode([
                    "success" => false,
                    "authenticated" => false,
                    "expired" => true,
                    "error" => "Session has expired due to 30 minutes of inactivity. Please sign in again."
                ]);
                return;
            }

            // Extend session expiration window (30 minutes sliding)
            $slideExpiry = date('Y-m-d H:i:s', time() + 1800);
            $pdo->prepare("UPDATE admins SET session_expires_at = ? WHERE id = ?")->execute([$slideExpiry, $admin['id']]);

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
function csp_is_private_ip($ip) {
    if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') return true;
    if (strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) return true;
    if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip)) return true;
    if (strpos($ip, 'fe80:') === 0 || strpos($ip, 'fc00:') === 0 || strpos($ip, 'fd00:') === 0) return true;
    return false;
}

function csp_resolve_geo($targetIp = '') {
    $isPrivate = csp_is_private_ip($targetIp);
    $cacheKey = (!$isPrivate && !empty($targetIp)) ? 'csp_geo_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $targetIp) : 'csp_geo_self';
    
    if (!empty($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey]) && (time() - ($_SESSION[$cacheKey]['time'] ?? 0) < 7200)) {
        return $_SESSION[$cacheKey]['data'];
    }

    $url = 'http://ip-api.com/json/' . ((!$isPrivate && !empty($targetIp)) ? urlencode($targetIp) : '');
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 1.5,
            'user_agent' => 'Mozilla/5.0 Conspodium/2.0'
        ]
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw) {
        $data = @json_decode($raw, true);
        if ($data && ($data['status'] ?? '') === 'success') {
            $city = trim($data['city'] ?? '');
            $country = trim($data['country'] ?? '');
            $region = trim($data['regionName'] ?? '');
            $loc = '';
            if ($city && $country && $city !== $country) {
                $loc = "$city, $country";
            } elseif ($region && $country) {
                $loc = "$region, $country";
            } else {
                $loc = $country ?: $city ?: 'Live Remote Session';
            }

            $res = [
                'ip' => $data['query'] ?? '',
                'location' => $loc,
                'city' => $city,
                'country' => $country,
                'isp' => $data['isp'] ?? ''
            ];
            $_SESSION[$cacheKey] = ['time' => time(), 'data' => $res];
            return $res;
        }
    }
    return null;
}

function csp_detect_client_info($clientPayload = []) {
    $clientIp = trim($clientPayload['client_ip'] ?? $_SERVER['HTTP_X_CLIENT_IP'] ?? '');
    $clientLocation = trim($clientPayload['client_location'] ?? $_SERVER['HTTP_X_CLIENT_LOCATION'] ?? '');

    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }
    
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device';

    // 1. Detect CLI / Automated tools
    if (stripos($ua, 'curl') !== false) {
        $device = 'Automated API (cURL CLI)';
    } elseif (stripos($ua, 'Postman') !== false) {
        $device = 'Postman API Client';
    } elseif (stripos($ua, 'python') !== false) {
        $device = 'Python Automated Client';
    } else {
        // 2. Detect Standard Web Browsers
        $browser = 'Desktop Web Client';
        if (stripos($ua, 'Brave') !== false) {
            $browser = 'Brave Browser';
        } elseif (stripos($ua, 'Edg') !== false) {
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

        // 3. Detect Operating System
        $os = 'Desktop Device';
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
    }

    // Detect actual location & real public IP
    $location = '';
    $cfCountry = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';
    $cfCity = $_SERVER['HTTP_CF_IPCITY'] ?? '';
    if ($cfCity && $cfCountry) {
        $location = "$cfCity, $cfCountry";
    } elseif ($cfCountry) {
        $location = "Public ($cfCountry)";
    }

    // If on localhost or private network (dev environment or local test)
    if (csp_is_private_ip($ip)) {
        if (!empty($clientIp) && !csp_is_private_ip($clientIp)) {
            $ip = $clientIp;
            if (!empty($clientLocation)) {
                $location = $clientLocation;
            }
        } else {
            $geo = csp_resolve_geo('');
            if ($geo && !empty($geo['ip'])) {
                $ip = $geo['ip'];
                if (!empty($geo['location'])) {
                    $location = $geo['location'];
                }
            }
        }
    } else {
        if (empty($location)) {
            $geo = csp_resolve_geo($ip);
            if ($geo && !empty($geo['location'])) {
                $location = $geo['location'];
            }
        }
    }

    if (empty($location)) {
        if (!empty($clientLocation)) {
            $location = $clientLocation;
        } elseif (!csp_is_private_ip($ip)) {
            $location = 'Live Remote Session';
        } else {
            $location = 'Localhost (LAN / Development PC)';
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
        // Upgrade legacy localhost / loopback rows to real public IP and actual location
        $hasLocal = (int)$pdo->query("SELECT COUNT(*) FROM admin_security_logs WHERE ip_address IN ('::1', '127.0.0.1') OR location LIKE '%Localhost%'")->fetchColumn();
        if ($hasLocal > 0) {
            $geo = csp_resolve_geo('');
            if ($geo && !empty($geo['ip']) && !empty($geo['location'])) {
                $upd = $pdo->prepare("UPDATE admin_security_logs SET ip_address = ?, location = ? WHERE ip_address IN ('::1', '127.0.0.1') OR location LIKE '%Localhost%'");
                $upd->execute([$geo['ip'], $geo['location']]);
            }
        }

        // Clean legacy placeholder "Browser on Workstation PC" and classify cURL/CLI requests accurately
        $pdo->exec("UPDATE admin_security_logs SET browser_device = 'Automated API (cURL CLI)' WHERE user_agent LIKE '%curl%' OR browser_device = 'Browser on Workstation PC'");

        // Validate active session token
        $clientToken = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? $_GET['token'] ?? '';
        if (empty($clientToken) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
            if (preg_match('/Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
                $clientToken = $matches[1];
            }
        }

        $activeAdmin = null;
        if (!empty($clientToken)) {
            $checkStmt = $pdo->prepare("SELECT id, session_token, session_expires_at FROM admins WHERE session_token = ? AND session_token IS NOT NULL AND session_token != ''");
            $checkStmt->execute([$clientToken]);
            $activeAdmin = $checkStmt->fetch();
            if ($activeAdmin && !empty($activeAdmin['session_expires_at']) && strtotime($activeAdmin['session_expires_at']) < time()) {
                $activeAdmin = null;
            }
        }

        if (!$activeAdmin && !empty($_SESSION['admin_user']['id'])) {
            $activeAdmin = $pdo->query("SELECT id, session_token, session_expires_at FROM admins WHERE id = " . intval($_SESSION['admin_user']['id']))->fetch();
        }

        if (!$activeAdmin) {
            $activeAdmin = $pdo->query("SELECT id, session_token, session_expires_at FROM admins WHERE session_token IS NOT NULL AND session_token != '' AND (session_expires_at IS NULL OR session_expires_at > datetime('now', 'localtime')) ORDER BY id ASC LIMIT 1")->fetch();
        }

        if (!$activeAdmin) {
            $activeAdmin = $pdo->query("SELECT id, session_token, session_expires_at FROM admins WHERE id = 1")->fetch();
        }

        $latestLogId = null;
        if ($activeAdmin) {
            $latestLogId = $pdo->query("SELECT id FROM admin_security_logs WHERE admin_id = " . intval($activeAdmin['id']) . " ORDER BY id DESC LIMIT 1")->fetchColumn();
        }
        if (!$latestLogId) {
            $latestLogId = $pdo->query("SELECT MAX(id) FROM admin_security_logs")->fetchColumn();
        }

        try {
            $pdo->exec("ALTER TABLE admin_security_logs ADD COLUMN ended_at DATETIME DEFAULT NULL");
        } catch (Exception $e) {}

        if ($latestLogId) {
            $pdo->exec("UPDATE admin_security_logs SET status = 'Closed', ended_at = COALESCE(ended_at, datetime(created_at, '+30 minutes')) WHERE id != " . intval($latestLogId) . " AND status IN ('Current Session', 'Active Session')");
            $pdo->exec("UPDATE admin_security_logs SET status = 'Current Session', ended_at = NULL WHERE id = " . intval($latestLogId));
        }

        // Backfill ended_at for closed sessions if empty
        $pdo->exec("UPDATE admin_security_logs SET ended_at = datetime(created_at, '+30 minutes') WHERE status = 'Closed' AND ended_at IS NULL");

        // Filter: all, active, closed
        $filter = strtolower($_GET['filter'] ?? 'all');
        $whereSql = "";
        if ($filter === 'active') {
            $whereSql = "WHERE status IN ('Current Session', 'Active Session')";
        } elseif ($filter === 'closed') {
            $whereSql = "WHERE status NOT IN ('Current Session', 'Active Session')";
        }

        $activeCount = (int)$pdo->query("SELECT COUNT(*) FROM admin_security_logs WHERE status IN ('Current Session', 'Active Session')")->fetchColumn();
        $closedCount = (int)$pdo->query("SELECT COUNT(*) FROM admin_security_logs WHERE status NOT IN ('Current Session', 'Active Session')")->fetchColumn();
        $totalLogs = (int)$pdo->query("SELECT COUNT(*) FROM admin_security_logs $whereSql")->fetchColumn();

        // Seed initial log if empty
        if ($totalLogs === 0 && $filter === 'all') {
            $client = csp_detect_client_info();
            $adminUser = $_SESSION['admin_user']['username'] ?? 'admin';
            $adminEmail = $_SESSION['admin_user']['email'] ?? 'admin@conspodium.com';
            
            $ins = $pdo->prepare("INSERT INTO admin_security_logs (admin_id, admin_username, admin_email, ip_address, location, user_agent, browser_device, status, created_at) VALUES (1, ?, ?, ?, ?, ?, ?, 'Current Session', datetime('now', 'localtime'))");
            $ins->execute([$adminUser, $adminEmail, $client['ip'], $client['location'], $client['ua'], $client['device']]);
            $totalLogs = 1;
            $activeCount = 1;
        }

        $limitParam = $_GET['limit'] ?? 10;
        $pageParam = $_GET['page'] ?? 1;

        // Order: Current Session guaranteed at the very top, followed by descending ID
        $orderSql = "ORDER BY CASE WHEN status IN ('Current Session', 'Active Session') THEN 0 ELSE 1 END, id DESC";

        if ($limitParam === 'all') {
            $stmt = $pdo->query("SELECT * FROM admin_security_logs $whereSql $orderSql");
            $logs = $stmt->fetchAll() ?: [];
            $limit = count($logs);
            $page = 1;
            $totalPages = 1;
        } else {
            $limit = max(1, min(100, intval($limitParam)));
            $totalPages = max(1, (int)ceil($totalLogs / $limit));
            $page = max(1, min($totalPages, intval($pageParam)));
            $offset = ($page - 1) * $limit;

            $stmt = $pdo->prepare("SELECT * FROM admin_security_logs $whereSql $orderSql LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $logs = $stmt->fetchAll() ?: [];
        }

        echo json_encode([
            "success" => true,
            "logs" => $logs,
            "total" => $totalLogs,
            "activeCount" => $activeCount,
            "closedCount" => $closedCount,
            "filter" => $filter,
            "page" => $page,
            "limit" => $limit,
            "totalPages" => $totalPages
        ]);
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

    $client = csp_detect_client_info($input);
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $token = bin2hex(random_bytes(32));
        
        try {
            // Set 30-minute inactivity sliding expiration
            $loginExpiry = date('Y-m-d H:i:s', time() + 1800);
            $upToken = $pdo->prepare("UPDATE admins SET session_token = ?, session_expires_at = ? WHERE id = ?");
            $upToken->execute([$token, $loginExpiry, $admin['id']]);

            // Mark ALL previous sessions as Closed
            $pdo->exec("UPDATE admin_security_logs SET status = 'Closed' WHERE status IN ('Current Session', 'Active Session')");

            // Record new active session (Current Session, placed at top)
            $insLog = $pdo->prepare("INSERT INTO admin_security_logs (admin_id, admin_username, admin_email, ip_address, location, user_agent, browser_device, status, session_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'Current Session', ?, datetime('now', 'localtime'))");
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

    try {
        if (!empty($_SESSION['admin_user']['id'])) {
            $pdo->prepare("UPDATE admins SET session_token = NULL, session_expires_at = NULL WHERE id = ?")->execute([$_SESSION['admin_user']['id']]);
        }
        if (!empty($clientToken)) {
            $pdo->prepare("UPDATE admins SET session_token = NULL, session_expires_at = NULL WHERE session_token = ?")->execute([$clientToken]);
        }
        // All active sessions become Closed upon logout with exact ended_at timestamp
        $pdo->exec("UPDATE admin_security_logs SET status = 'Closed', ended_at = datetime('now', 'localtime') WHERE status IN ('Current Session', 'Active Session')");
    } catch (Exception $e) {}

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
