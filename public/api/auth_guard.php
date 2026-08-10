<?php
// api/auth_guard.php - Security Guard for Protected Admin API Routes

if (session_status() === PHP_SESSION_NONE) {
    if (getenv('VERCEL') || !empty($_ENV['VERCEL']) || !empty($_SERVER['VERCEL'])) {
        @session_save_path('/tmp');
    }
    session_start();
}

function requireAdmin() {
    if (empty($_SESSION['admin_user'])) {
        http_response_code(401);
        header("Content-Type: application/json");
        echo json_encode([
            "success" => false,
            "error" => "Unauthorized: Admin authentication required to perform this action."
        ]);
        exit(0);
    }
}
