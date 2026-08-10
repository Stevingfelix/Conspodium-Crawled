<?php
// api/dashboard.php - Admin Dashboard Overview Metrics API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_guard.php';

// Public read overview stats

try {
    $totalPosts = $pdo->query("SELECT COUNT(*) as count FROM posts")->fetch()['count'];
    $totalViews = $pdo->query("SELECT SUM(views) as count FROM posts")->fetch()['count'] ?? 0;
    $totalSubmissions = $pdo->query("SELECT COUNT(*) as count FROM story_submissions")->fetch()['count'];
    $pendingSubmissions = $pdo->query("SELECT COUNT(*) as count FROM story_submissions WHERE status = 'pending'")->fetch()['count'];
    $totalMessages = $pdo->query("SELECT COUNT(*) as count FROM contact_messages")->fetch()['count'] ?? 0;
    $unreadMessages = $pdo->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'unread'")->fetch()['count'] ?? 0;

    $topPostsStmt = $pdo->query("
        SELECT p.id, p.title, p.slug, p.views, p.reading_time, c.name as category_name
        FROM posts p
        LEFT JOIN categories c ON p.category_id = c.id
        ORDER BY p.views DESC
        LIMIT 5
    ");
    $topPosts = $topPostsStmt->fetchAll();

    $recentSubmissionsStmt = $pdo->query("
        SELECT id, author_name, title, status, submitted_at
        FROM story_submissions
        ORDER BY submitted_at DESC
        LIMIT 5
    ");
    $recentSubmissions = $recentSubmissionsStmt->fetchAll();

    $recentMessagesStmt = $pdo->query("
        SELECT id, first_name, last_name, email, subject, status, created_at
        FROM contact_messages
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recentMessages = $recentMessagesStmt->fetchAll();

    echo json_encode([
        "success" => true,
        "stats" => [
            "totalPosts" => intval($totalPosts),
            "totalViews" => intval($totalViews),
            "totalSubmissions" => intval($totalSubmissions),
            "pendingSubmissions" => intval($pendingSubmissions),
            "totalMessages" => intval($totalMessages),
            "unreadMessages" => intval($unreadMessages)
        ],
        "topPosts" => $topPosts,
        "recentSubmissions" => $recentSubmissions,
        "recentMessages" => $recentMessages
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
