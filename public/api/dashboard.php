<?php
// api/dashboard.php - Admin Dashboard Overview Metrics API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Admin-Token, Authorization");

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

    $activePollRow = $pdo->query("SELECT id FROM polls WHERE is_active = 1 LIMIT 1")->fetch();
    $totalPollVotes = 0;
    if ($activePollRow) {
        $totalPollVotes = $pdo->query("SELECT COUNT(*) as count FROM poll_votes WHERE poll_id = " . intval($activePollRow['id']))->fetch()['count'] ?? 0;
    } else {
        $totalPollVotes = $pdo->query("SELECT COUNT(*) as count FROM poll_votes")->fetch()['count'] ?? 0;
    }

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

    $pendingComments = $pdo->query("SELECT COUNT(*) as count FROM comments WHERE status = 'pending'")->fetch()['count'] ?? 0;

    $recentCommentsStmt = $pdo->query("
        SELECT c.id, c.author_name, c.author_email, c.content, c.status, c.created_at, p.title as post_title, p.slug as post_slug
        FROM comments c
        LEFT JOIN posts p ON c.post_id = p.id
        ORDER BY c.created_at DESC
        LIMIT 4
    ");
    $recentComments = $recentCommentsStmt->fetchAll();

    $recentForumRepliesStmt = $pdo->query("
        SELECT r.id, r.author_name, r.author_email, r.content, r.status, r.created_at, t.title as thread_title, t.id as thread_id
        FROM forum_replies r
        LEFT JOIN forum_threads t ON r.thread_id = t.id
        ORDER BY r.created_at DESC
        LIMIT 4
    ");
    $recentForumReplies = $recentForumRepliesStmt->fetchAll();

    $totalAttendees = $pdo->query("SELECT COUNT(*) as count FROM event_reminders")->fetch()['count'] ?? 0;

    $totalForumThreads = $pdo->query("SELECT COUNT(*) as count FROM forum_threads")->fetch()['count'] ?? 0;
    $totalForumReplies = $pdo->query("SELECT COUNT(*) as count FROM forum_replies")->fetch()['count'] ?? 0;

    echo json_encode([
        "success" => true,
        "stats" => [
            "totalPosts" => intval($totalPosts),
            "totalViews" => intval($totalViews),
            "totalSubmissions" => intval($totalSubmissions),
            "pendingSubmissions" => intval($pendingSubmissions),
            "totalMessages" => intval($totalMessages),
            "unreadMessages" => intval($unreadMessages),
            "pendingComments" => intval($pendingComments),
            "forumCount" => intval($totalForumThreads),
            "forumThreadsCount" => intval($totalForumThreads),
            "forumRepliesCount" => intval($totalForumReplies),
            "totalPollVotes" => intval($totalPollVotes),
            "totalAttendees" => intval($totalAttendees)
        ],
        "topPosts" => $topPosts,
        "recentSubmissions" => $recentSubmissions,
        "recentMessages" => $recentMessages,
        "recentComments" => $recentComments,
        "recentForumReplies" => $recentForumReplies
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
