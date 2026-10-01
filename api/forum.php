<?php
// api/forum.php - Community Forum & Discussion Board REST API
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Token, X-CSRF-Token");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'threads';

// LIST FORUM CATEGORIES (PUBLIC ACTIVE ONLY)
if ($action === 'categories') {
    try {
        $stmt = $pdo->query("
            SELECT fc.*, COUNT(t.id) as threads_count
            FROM forum_categories fc
            LEFT JOIN forum_threads t ON (t.category_id = fc.id OR t.category = fc.name) AND t.status = 'approved'
            WHERE fc.is_active = 1
            GROUP BY fc.id
            ORDER BY fc.display_order ASC, fc.id ASC
        ");
        $categories = $stmt->fetchAll();
        echo json_encode(["success" => true, "data" => $categories]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// VOTE ON THREAD OR REPLY (UPVOTE / DOWNVOTE)
if ($action === 'vote') {
    $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $targetType = ($input['target_type'] ?? 'thread') === 'reply' ? 'reply' : 'thread';
    $targetId = intval($input['target_id'] ?? $input['id'] ?? 0);
    $voteType = ($input['vote_type'] ?? 'up') === 'down' ? 'down' : 'up';
    $voterIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if ($targetId <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Invalid target ID."]);
        exit();
    }

    $table = $targetType === 'reply' ? 'forum_replies' : 'forum_threads';

    try {
        // Check existing vote
        $stmtCheck = $pdo->prepare("SELECT * FROM forum_votes WHERE target_type = ? AND target_id = ? AND voter_ip = ?");
        $stmtCheck->execute([$targetType, $targetId, $voterIp]);
        $existing = $stmtCheck->fetch();

        $userVote = null;

        if ($existing) {
            if ($existing['vote_type'] === $voteType) {
                // Cancel vote
                $pdo->prepare("DELETE FROM forum_votes WHERE id = ?")->execute([$existing['id']]);
                if ($voteType === 'up') {
                    $pdo->prepare("UPDATE {$table} SET upvotes = MAX(0, COALESCE(upvotes, 0) - 1) WHERE id = ?")->execute([$targetId]);
                } else {
                    $pdo->prepare("UPDATE {$table} SET downvotes = MAX(0, COALESCE(downvotes, 0) - 1) WHERE id = ?")->execute([$targetId]);
                }
                $userVote = null;
            } else {
                // Switch vote from up->down or down->up
                $pdo->prepare("UPDATE forum_votes SET vote_type = ? WHERE id = ?")->execute([$voteType, $existing['id']]);
                if ($voteType === 'up') {
                    $pdo->prepare("UPDATE {$table} SET upvotes = COALESCE(upvotes, 0) + 1, downvotes = MAX(0, COALESCE(downvotes, 0) - 1) WHERE id = ?")->execute([$targetId]);
                } else {
                    $pdo->prepare("UPDATE {$table} SET downvotes = COALESCE(downvotes, 0) + 1, upvotes = MAX(0, COALESCE(upvotes, 0) - 1) WHERE id = ?")->execute([$targetId]);
                }
                $userVote = $voteType;
            }
        } else {
            // New vote
            $pdo->prepare("INSERT INTO forum_votes (target_type, target_id, vote_type, voter_ip) VALUES (?, ?, ?, ?)")->execute([$targetType, $targetId, $voteType, $voterIp]);
            if ($voteType === 'up') {
                $pdo->prepare("UPDATE {$table} SET upvotes = COALESCE(upvotes, 0) + 1 WHERE id = ?")->execute([$targetId]);
            } else {
                $pdo->prepare("UPDATE {$table} SET downvotes = COALESCE(downvotes, 0) + 1 WHERE id = ?")->execute([$targetId]);
            }
            $userVote = $voteType;
        }

        $stmtStats = $pdo->prepare("SELECT COALESCE(upvotes, 0) as upvotes, COALESCE(downvotes, 0) as downvotes FROM {$table} WHERE id = ?");
        $stmtStats->execute([$targetId]);
        $stats = $stmtStats->fetch() ?: ['upvotes' => 0, 'downvotes' => 0];
        $upvotes = intval($stats['upvotes'] ?? 0);
        $downvotes = intval($stats['downvotes'] ?? 0);
        $score = $upvotes - $downvotes;

        echo json_encode([
            "success" => true,
            "target_type" => $targetType,
            "target_id" => $targetId,
            "upvotes" => $upvotes,
            "downvotes" => $downvotes,
            "score" => $score,
            "user_vote" => $userVote
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// LIST THREADS (PUBLIC APPROVED ONLY)
if ($action === 'threads') {
    try {
        $category = csp_sanitize($_GET['category'] ?? '');
        $categoryId = intval($_GET['category_id'] ?? 0);
        $search = csp_sanitize($_GET['search'] ?? '');
        $voterIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $sql = "
            SELECT t.*, 
                   COALESCE(t.upvotes, 0) as upvotes, 
                   COALESCE(t.downvotes, 0) as downvotes,
                   (COALESCE(t.upvotes, 0) - COALESCE(t.downvotes, 0)) as score,
                   fc.slug as category_slug, 
                   fc.icon_type as category_icon, 
                   fc.color_accent as category_color, 
                   COUNT(DISTINCT r.id) as reply_count,
                   (SELECT vote_type FROM forum_votes v WHERE v.target_type = 'thread' AND v.target_id = t.id AND v.voter_ip = ?) as user_vote
            FROM forum_threads t
            LEFT JOIN forum_categories fc ON t.category_id = fc.id
            LEFT JOIN forum_replies r ON t.id = r.thread_id AND r.status = 'approved'
            WHERE t.status = 'approved'
        ";
        $params = [$voterIp];

        if ($categoryId > 0) {
            $sql .= " AND (t.category_id = ?)";
            $params[] = $categoryId;
        } elseif (!empty($category) && $category !== 'All') {
            $sql .= " AND (t.category = ? OR fc.name = ? OR fc.slug = ?)";
            $params[] = $category;
            $params[] = $category;
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
        $voterIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Increment views count
        $pdo->prepare("UPDATE forum_threads SET views = views + 1 WHERE id = ?")->execute([$id]);

        $stmtThread = $pdo->prepare("
            SELECT t.*, 
                   COALESCE(t.upvotes, 0) as upvotes, 
                   COALESCE(t.downvotes, 0) as downvotes,
                   (COALESCE(t.upvotes, 0) - COALESCE(t.downvotes, 0)) as score,
                   fc.slug as category_slug, 
                   fc.icon_type as category_icon, 
                   fc.color_accent as category_color,
                   (SELECT vote_type FROM forum_votes v WHERE v.target_type = 'thread' AND v.target_id = t.id AND v.voter_ip = ?) as user_vote
            FROM forum_threads t
            LEFT JOIN forum_categories fc ON t.category_id = fc.id
            WHERE t.id = ?
        ");
        $stmtThread->execute([$voterIp, $id]);
        $thread = $stmtThread->fetch();

        if (!$thread) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Thread not found."]);
            exit();
        }

        $stmtReplies = $pdo->prepare("
            SELECT r.*,
                   COALESCE(r.upvotes, 0) as upvotes,
                   COALESCE(r.downvotes, 0) as downvotes,
                   (COALESCE(r.upvotes, 0) - COALESCE(r.downvotes, 0)) as score,
                   (SELECT vote_type FROM forum_votes v WHERE v.target_type = 'reply' AND v.target_id = r.id AND v.voter_ip = ?) as user_vote
            FROM forum_replies r 
            WHERE r.thread_id = ? AND r.status = 'approved'
            ORDER BY r.id ASC
        ");
        $stmtReplies->execute([$voterIp, $id]);
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
    $categoryId = intval($input['category_id'] ?? 0);
    $category = csp_sanitize($input['category'] ?? '');
    $authorName = csp_sanitize($input['author_name'] ?? '');
    $authorEmail = filter_var(trim($input['author_email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $authorLocation = csp_sanitize($input['location'] ?? $input['author_location'] ?? '');
    $content = csp_sanitize($input['content'] ?? '');

    if (empty($title) || empty($content) || !$authorEmail || empty($authorName)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Please fill in all required fields (Name, Email, Title, Content)."]);
        exit();
    }

    if ($categoryId > 0) {
        $stmtCat = $pdo->prepare("SELECT name FROM forum_categories WHERE id = ?");
        $stmtCat->execute([$categoryId]);
        $cRow = $stmtCat->fetch();
        if ($cRow) $category = $cRow['name'];
    } elseif (!empty($category)) {
        $stmtCat = $pdo->prepare("SELECT id FROM forum_categories WHERE name = ? OR slug = ?");
        $stmtCat->execute([$category, $category]);
        $cRow = $stmtCat->fetch();
        if ($cRow) $categoryId = intval($cRow['id']);
    } else {
        $category = 'General Discussion';
    }

    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-')) . '-' . time();

    try {
        $stmt = $pdo->prepare("INSERT INTO forum_threads (category_id, title, slug, category, author_name, author_email, author_location, content, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', CURRENT_TIMESTAMP)");
        $stmt->execute([$categoryId ?: null, $title, $slug, $category, $authorName, $authorEmail, $authorLocation, $content]);
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
        if (!empty($_COOKIE['csp_user_comment_author'])) {
            $authorName = csp_sanitize($_COOKIE['csp_user_comment_author']);
        }
        if (!empty($_COOKIE['csp_user_comment_email'])) {
            $authorEmail = trim($_COOKIE['csp_user_comment_email']);
        }

        $stmt = $pdo->prepare("INSERT INTO forum_replies (thread_id, parent_id, author_name, author_email, content, status, created_at) VALUES (?, ?, ?, ?, ?, 'approved', CURRENT_TIMESTAMP)");
        $stmt->execute([$threadId, $parentId, $authorName, $authorEmail, $content]);

        @setcookie('csp_user_comment_author', $authorName, time() + 31536000, '/');
        if ($authorEmail) {
            @setcookie('csp_user_comment_email', $authorEmail, time() + 31536000, '/');
        }

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

// ADMIN MODERATION & MANAGEMENT ENDPOINTS
if ($action === 'moderate' || $action === 'admin_list' || $action === 'admin_create_thread' || $action === 'admin_categories' || $action === 'admin_save_category' || $action === 'admin_delete_category') {
    require_once __DIR__ . '/auth_guard.php';
    requireAdmin();

    // ADMIN CATEGORIES LIST
    if ($action === 'admin_categories') {
        try {
            $stmt = $pdo->query("
                SELECT fc.*, COUNT(t.id) as threads_count
                FROM forum_categories fc
                LEFT JOIN forum_threads t ON t.category_id = fc.id OR t.category = fc.name
                GROUP BY fc.id
                ORDER BY fc.display_order ASC, fc.id ASC
            ");
            echo json_encode(["success" => true, "data" => $stmt->fetchAll()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit();
    }

    // ADMIN SAVE FORUM CATEGORY (CREATE / UPDATE)
    if ($action === 'admin_save_category') {
        $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
        $id = intval($input['id'] ?? 0);
        $name = csp_sanitize($input['name'] ?? '');
        $description = csp_sanitize($input['description'] ?? '');
        $iconType = csp_sanitize($input['icon_type'] ?? 'globe');
        $colorAccent = csp_sanitize($input['color_accent'] ?? '#00AEFE');
        $displayOrder = intval($input['display_order'] ?? 0);
        $isActive = isset($input['is_active']) ? intval($input['is_active']) : 1;

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Category name is required."]);
            exit();
        }

        $slug = !empty($input['slug']) ? csp_sanitize($input['slug']) : strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE forum_categories SET name = ?, slug = ?, description = ?, icon_type = ?, color_accent = ?, display_order = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $description, $iconType, $colorAccent, $displayOrder, $isActive, $id]);
                echo json_encode(["success" => true, "message" => "Forum category updated successfully!"]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO forum_categories (name, slug, description, icon_type, color_accent, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $description, $iconType, $colorAccent, $displayOrder, $isActive]);
                echo json_encode(["success" => true, "message" => "Forum category created successfully!", "id" => $pdo->lastInsertId()]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit();
    }

    // ADMIN DELETE FORUM CATEGORY
    if ($action === 'admin_delete_category') {
        $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
        $id = intval($input['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Category ID required."]);
            exit();
        }
        try {
            $pdo->prepare("UPDATE forum_threads SET category_id = NULL WHERE category_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM forum_categories WHERE id = ?")->execute([$id]);
            echo json_encode(["success" => true, "message" => "Forum category deleted successfully."]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit();
    }

    // ADMIN CREATE THREAD
    if ($action === 'admin_create_thread') {
        $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
        $title = csp_sanitize($input['title'] ?? '');
        $categoryId = intval($input['category_id'] ?? 0);
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

        if ($categoryId > 0) {
            $stmtCat = $pdo->prepare("SELECT name FROM forum_categories WHERE id = ?");
            $stmtCat->execute([$categoryId]);
            $cRow = $stmtCat->fetch();
            if ($cRow) $category = $cRow['name'];
        }

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-')) . '-' . time();

        try {
            $stmt = $pdo->prepare("INSERT INTO forum_threads (category_id, title, slug, category, author_name, author_email, content, is_pinned, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', CURRENT_TIMESTAMP)");
            $stmt->execute([$categoryId ?: null, $title, $slug, $category, $authorName, $authorEmail, $content, $isPinned]);
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
            $stmt = $pdo->query("
                SELECT t.*, fc.name as category_title, COUNT(r.id) as reply_count,
                       COALESCE(t.upvotes, 0) as upvotes, 
                       COALESCE(t.downvotes, 0) as downvotes, 
                       (COALESCE(t.upvotes, 0) - COALESCE(t.downvotes, 0)) as score
                FROM forum_threads t 
                LEFT JOIN forum_categories fc ON t.category_id = fc.id 
                LEFT JOIN forum_replies r ON t.id = r.thread_id 
                GROUP BY t.id 
                ORDER BY t.id DESC
            ");
            echo json_encode(["success" => true, "data" => $stmt->fetchAll()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit();
    }

    $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $type = csp_sanitize($input['type'] ?? 'thread');
    $targetId = intval($input['id'] ?? 0);
    $op = csp_sanitize($input['op'] ?? '');

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
