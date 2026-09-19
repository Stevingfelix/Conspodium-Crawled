<?php
// api/forum.php - Community Forum & Discussion Board REST API
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'threads';

// LIST THREADS (PUBLIC APPROVED ONLY)
if ($action === 'threads') {
    try {
        $category = csp_sanitize($_GET['category'] ?? '');
        $search = csp_sanitize($_GET['search'] ?? '');

        $sql = "
            SELECT t.*, COUNT(r.id) as reply_count
            FROM forum_threads t
            LEFT JOIN forum_replies r ON t.id = r.thread_id AND r.status = 'approved'
            WHERE t.status = 'approved'
        ";
        $params = [];

        if (!empty($category) && $category !== 'All') {
            $sql .= " AND t.category = ?";
            $params[] = $category;
        }

        if (!empty($search)) {
            $sql .= " AND (t.title LIKE ? OR t.content LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " GROUP BY t.id ORDER BY t.is_pinned DESC, t.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $threads = $stmt->fetchAll();

        echo json_encode(["success" => true, "data" => $threads]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// GET SINGLE THREAD & REPLIES
if ($action === 'thread') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Valid thread ID is required."]);
        exit();
    }

    try {
        // Increment views count
        $pdo->prepare("UPDATE forum_threads SET views = views + 1 WHERE id = ?")->execute([$id]);

        $stmtThread = $pdo->prepare("SELECT * FROM forum_threads WHERE id = ?");
        $stmtThread->execute([$id]);
        $thread = $stmtThread->fetch();

        if (!$thread) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Thread not found."]);
            exit();
        }

        $stmtReplies = $pdo->prepare("SELECT * FROM forum_replies WHERE thread_id = ? ORDER BY id ASC");
        $stmtReplies->execute([$id]);
        $replies = $stmtReplies->fetchAll();

        echo json_encode([
            "success" => true,
            "data" => [
                "thread" => $thread,
                "replies" => $replies
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// CREATE THREAD (EXTERNAL USER)
if ($action === 'create_thread') {
    if (!csp_check_rate_limit('forum_thread', 3, 60)) {
        http_response_code(429);
        echo json_encode(["success" => false, "error" => "Too many threads created. Please wait a minute."]);
        exit();
    }

    $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $title = csp_sanitize($input['title'] ?? '');
    $category = csp_sanitize($input['category'] ?? 'General Discussion');
    $authorName = csp_sanitize($input['author_name'] ?? '');
    $authorEmail = filter_var(trim($input['author_email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $content = csp_sanitize($input['content'] ?? '');

    if (empty($title) || empty($content) || !$authorEmail || empty($authorName)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Please fill in all required fields (Name, Email, Title, Content)."]);
        exit();
    }

    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-')) . '-' . time();

    try {
        $stmt = $pdo->prepare("INSERT INTO forum_threads (title, slug, category, author_name, author_email, content, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'approved', CURRENT_TIMESTAMP)");
        $stmt->execute([$title, $slug, $category, $authorName, $authorEmail, $content]);
        $threadId = $pdo->lastInsertId();

        echo json_encode([
            "success" => true,
            "message" => "Discussion topic posted successfully!",
            "thread_id" => $threadId
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// POST REPLY
if ($action === 'create_reply') {
    if (!csp_check_rate_limit('forum_reply', 5, 60)) {
        http_response_code(429);
        echo json_encode(["success" => false, "error" => "Too many replies posted. Please wait a minute."]);
        exit();
    }

    $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $threadId = intval($input['thread_id'] ?? 0);
    $parentId = !empty($input['parent_id']) ? intval($input['parent_id']) : null;
    $authorName = csp_sanitize($input['author_name'] ?? '');
    $authorEmail = filter_var(trim($input['author_email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $content = csp_sanitize($input['content'] ?? '');

    if (!$threadId || empty($content) || !$authorEmail || empty($authorName)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Please fill in all required fields (Name, Email, Reply)."]);
        exit();
    }

    try {
        // Enforce cookie-level commenter session lock if set
        if (!empty($_COOKIE['csp_user_comment_author'])) {
            $authorName = csp_sanitize($_COOKIE['csp_user_comment_author']);
        }
        if (!empty($_COOKIE['csp_user_comment_email'])) {
            $authorEmail = trim($_COOKIE['csp_user_comment_email']);
        }

        $stmt = $pdo->prepare("INSERT INTO forum_replies (thread_id, parent_id, author_name, author_email, content, status, created_at) VALUES (?, ?, ?, ?, ?, 'approved', CURRENT_TIMESTAMP)");
        $stmt->execute([$threadId, $parentId, $authorName, $authorEmail, $content]);

        // Lock identity into long-lived HTTP cookie
        @setcookie('csp_user_comment_author', $authorName, time() + 31536000, '/');
        if ($authorEmail) {
            @setcookie('csp_user_comment_email', $authorEmail, time() + 31536000, '/');
        }

        // Trigger email notification if replying to a parent reply author
        if ($parentId > 0) {
            try {
                $stmtParent = $pdo->prepare("SELECT author_name, author_email FROM forum_replies WHERE id = ?");
                $stmtParent->execute([$parentId]);
                $parentReply = $stmtParent->fetch();
                if ($parentReply && filter_var($parentReply['author_email'], FILTER_VALIDATE_EMAIL)) {
                    $pEmail = $parentReply['author_email'];
                    $pName = $parentReply['author_name'];
                    $subject = "New reply to your forum comment on Conspodium";
                    $msgBody = "Hello " . $pName . ",\n\n" . $authorName . " replied to your comment on Conspodium Forum:\n\n\"" . $content . "\"\n\nJoin the discussion on Conspodium.";
                    @mail($pEmail, $subject, $msgBody, "From: forum@conspodium.com\r\nContent-Type: text/plain; charset=UTF-8");
                }
            } catch (Exception $ex) {}
        }

        echo json_encode(["success" => true, "message" => "Reply posted successfully!"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// ADMIN MODERATION ENDPOINTS
if ($action === 'moderate' || $action === 'admin_list' || $action === 'admin_create_thread') {
    require_once __DIR__ . '/auth_guard.php';

    // ADMIN CREATE THREAD
    if ($action === 'admin_create_thread') {
        $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
        $title = csp_sanitize($input['title'] ?? '');
        $category = csp_sanitize($input['category'] ?? 'General Discussion');
        $authorName = csp_sanitize($input['author_name'] ?? 'Conspodium Admin');
        $authorEmail = filter_var(trim($input['author_email'] ?? 'admin@conspodium.com'), FILTER_VALIDATE_EMAIL);
        $content = csp_sanitize($input['content'] ?? '');
        $isPinned = !empty($input['is_pinned']) ? 1 : 0;

        if (empty($title) || empty($content)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Title and Content are required for admin topic creation."]);
            exit();
        }

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-')) . '-' . time();

        try {
            $stmt = $pdo->prepare("INSERT INTO forum_threads (title, slug, category, author_name, author_email, content, is_pinned, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', CURRENT_TIMESTAMP)");
            $stmt->execute([$title, $slug, $category, $authorName, $authorEmail, $content, $isPinned]);
            $threadId = $pdo->lastInsertId();

            echo json_encode([
                "success" => true,
                "message" => "Official discussion topic created and published!",
                "thread_id" => $threadId
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit();
    }

    if ($action === 'admin_list') {
        try {
            $stmt = $pdo->query("SELECT t.*, COUNT(r.id) as reply_count FROM forum_threads t LEFT JOIN forum_replies r ON t.id = r.thread_id GROUP BY t.id ORDER BY t.id DESC");
            echo json_encode(["success" => true, "data" => $stmt->fetchAll()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit();
    }

    $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $type = csp_sanitize($input['type'] ?? 'thread'); // 'thread' or 'reply'
    $targetId = intval($input['id'] ?? 0);
    $op = csp_sanitize($input['op'] ?? ''); // 'delete', 'pin', 'unpin', 'approve', 'reject'

    if (!$targetId || empty($op)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Valid ID and operation are required."]);
        exit();
    }

    try {
        if ($type === 'thread') {
            if ($op === 'delete') {
                $pdo->prepare("DELETE FROM forum_threads WHERE id = ?")->execute([$targetId]);
            } elseif ($op === 'pin') {
                $pdo->prepare("UPDATE forum_threads SET is_pinned = 1 WHERE id = ?")->execute([$targetId]);
            } elseif ($op === 'unpin') {
                $pdo->prepare("UPDATE forum_threads SET is_pinned = 0 WHERE id = ?")->execute([$targetId]);
            } elseif ($op === 'approve') {
                $pdo->prepare("UPDATE forum_threads SET status = 'approved' WHERE id = ?")->execute([$targetId]);
            } elseif ($op === 'reject') {
                $pdo->prepare("UPDATE forum_threads SET status = 'rejected' WHERE id = ?")->execute([$targetId]);
            }
        } elseif ($type === 'reply') {
            if ($op === 'delete') {
                $pdo->prepare("DELETE FROM forum_replies WHERE id = ?")->execute([$targetId]);
            } elseif ($op === 'approve') {
                $pdo->prepare("UPDATE forum_replies SET status = 'approved' WHERE id = ?")->execute([$targetId]);
            }
        }

        echo json_encode(["success" => true, "message" => "Moderation operation executed successfully."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}
