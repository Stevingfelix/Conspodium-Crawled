<?php
// api/posts.php - Articles & Categories CRUD API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Admin-Token, Authorization");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_guard.php';

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

$method = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? 'posts';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ── ARTICLE LIKES RESOURCE ──────────────────────────────────────────────────
if ($resource === 'like' || ($_GET['action'] ?? '') === 'like' || ($_POST['action'] ?? '') === 'like') {
    $postId = intval($_GET['id'] ?? $input['id'] ?? $input['post_id'] ?? 0);
    $slug = trim($_GET['slug'] ?? $input['slug'] ?? '');
    if (!$postId && $slug) {
        $sP = $pdo->prepare("SELECT id FROM posts WHERE slug = ?");
        $sP->execute([$slug]);
        $fP = $sP->fetch();
        if ($fP) $postId = intval($fP['id']);
    }

    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Valid post ID is required"]);
        exit;
    }

    $voterIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Check if voter already liked
    $checkStmt = $pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND voter_ip = ?");
    $checkStmt->execute([$postId, $voterIp]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        // Unlike
        $pdo->prepare("DELETE FROM post_likes WHERE id = ?")->execute([$existing['id']]);
        $pdo->prepare("UPDATE posts SET likes = MAX(0, COALESCE(likes, 0) - 1) WHERE id = ?")->execute([$postId]);
        $liked = false;
    } else {
        // Like
        $pdo->prepare("INSERT OR IGNORE INTO post_likes (post_id, voter_ip) VALUES (?, ?)")->execute([$postId, $voterIp]);
        $pdo->prepare("UPDATE posts SET likes = COALESCE(likes, 0) + 1 WHERE id = ?")->execute([$postId]);
        $liked = true;
    }

    $cntStmt = $pdo->prepare("SELECT COALESCE(likes, 0) as likes FROM posts WHERE id = ?");
    $cntStmt->execute([$postId]);
    $likes = intval($cntStmt->fetchColumn() ?: 0);

    echo json_encode(["success" => true, "post_id" => $postId, "likes" => $likes, "liked" => $liked]);
    exit;
}

// ── DEVICE IDENTITY SESSION RESOURCE (Cross-Profile / Cross-Session Persistence) ──
if ($resource === 'device_identity') {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ipHash = hash('sha256', $clientIp . ($_SERVER['HTTP_USER_AGENT'] ?? ''));

    if ($method === 'GET') {
        $devKey = trim($_GET['device_key'] ?? $_COOKIE['csp_device_key'] ?? '');
        $cookieName = trim($_COOKIE['csp_user_comment_author'] ?? '');
        $cookieEmail = trim($_COOKIE['csp_user_comment_email'] ?? '');

        $found = null;
        if ($devKey) {
            $stmt = $pdo->prepare("SELECT author_name, author_email, updated_at FROM device_identities WHERE device_key = ? ORDER BY updated_at DESC LIMIT 1");
            $stmt->execute([$devKey]);
            $found = $stmt->fetch();
        }
        if (!$found && $cookieName) {
            $found = ["author_name" => $cookieName, "author_email" => $cookieEmail, "updated_at" => date('Y-m-d H:i:s')];
        }
        if (!$found) {
            $stmt = $pdo->prepare("SELECT author_name, author_email, updated_at FROM device_identities WHERE ip_hash = ? ORDER BY updated_at DESC LIMIT 1");
            $stmt->execute([$ipHash]);
            $found = $stmt->fetch();
        }

        if ($found && !empty($found['author_name'])) {
            echo json_encode([
                "success" => true,
                "session" => [
                    "name" => $found['author_name'],
                    "email" => $found['author_email'] ?? '',
                    "updated_at" => $found['updated_at'] ?? ''
                ]
            ]);
        } else {
            echo json_encode(["success" => true, "session" => null]);
        }
        exit;
    }

    if ($method === 'POST') {
        $name = csp_sanitize(trim($input['author_name'] ?? $input['name'] ?? ''));
        $email = trim($input['author_email'] ?? $input['email'] ?? '');
        $devKey = trim($input['device_key'] ?? $_COOKIE['csp_device_key'] ?? '');
        if (!$devKey) $devKey = bin2hex(random_bytes(16));

        if (!empty($name)) {
            $now = date('Y-m-d H:i:s');
            $chk = $pdo->prepare("SELECT id FROM device_identities WHERE device_key = ? OR ip_hash = ? LIMIT 1");
            $chk->execute([$devKey, $ipHash]);
            $existing = $chk->fetch();

            if ($existing) {
                $stmt = $pdo->prepare("UPDATE device_identities SET author_name = ?, author_email = ?, device_key = ?, ip_hash = ?, updated_at = ? WHERE id = ?");
                $stmt->execute([$name, $email, $devKey, $ipHash, $now, $existing['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO device_identities (device_key, ip_hash, author_name, author_email, updated_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$devKey, $ipHash, $name, $email, $now]);
            }

            @setcookie('csp_device_key', $devKey, time() + 31536000, '/');
            @setcookie('csp_user_comment_author', $name, time() + 31536000, '/');
            if ($email) @setcookie('csp_user_comment_email', $email, time() + 31536000, '/');

            echo json_encode(["success" => true, "device_key" => $devKey, "message" => "Identity saved"]);
            exit;
        }

        echo json_encode(["success" => false, "error" => "Name is required"]);
        exit;
    }
}

// ── CATEGORIES RESOURCE ──────────────────────────────────────────────────────
if ($resource === 'categories') {
    if ($method === 'GET') {
        $stmt = $pdo->query("
            SELECT c.*, COUNT(p.id) as post_count
            FROM categories c
            LEFT JOIN posts p ON c.id = p.category_id
            GROUP BY c.id
            ORDER BY c.name ASC
        ");
        echo json_encode(["success" => true, "categories" => $stmt->fetchAll()]);
        exit;
    }

    if ($method === 'POST') {
        requireAdmin();
        $name = trim($input['name'] ?? '');
        $icon = trim($input['icon'] ?? '🏷️');
        $desc = trim($input['description'] ?? '');
        $image = trim($input['image'] ?? '');

        if (!$name) {
            echo json_encode(["success" => false, "error" => "Category name is required"]);
            exit;
        }

        $slug = slugify($name);
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, description, image) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $icon, $desc, $image]);

        echo json_encode(["success" => true, "categoryId" => $pdo->lastInsertId(), "message" => "Category created successfully"]);
        exit;
    }

    if ($method === 'PUT') {
        requireAdmin();
        $id = intval($_GET['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $icon = trim($input['icon'] ?? '🏷️');
        $desc = trim($input['description'] ?? '');
        $image = trim($input['image'] ?? '');

        $slug = slugify($name);
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, description = ?, image = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $icon, $desc, $image, $id]);

        echo json_encode(["success" => true, "message" => "Category updated successfully"]);
        exit;
    }

    if ($method === 'DELETE') {
        requireAdmin();
        $id = intval($_GET['id'] ?? 0);
        $pdo->prepare("UPDATE posts SET category_id = NULL WHERE category_id = ?")->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(["success" => true, "message" => "Category deleted successfully"]);
        exit;
    }
}

// ── COMMENT SETTINGS RESOURCE ───────────────────────────────────────────────────
if ($resource === 'comment_settings') {
    if ($method === 'GET') {
        $row = $pdo->query("SELECT value FROM site_settings WHERE key = 'auto_approve_comments' LIMIT 1")->fetch();
        $autoApprove = ($row && $row['value'] === '1');
        echo json_encode(["success" => true, "auto_approve" => $autoApprove]);
        exit;
    }
    if ($method === 'POST') {
        $autoApproveVal = (!empty($input['auto_approve']) && ($input['auto_approve'] === true || $input['auto_approve'] === '1' || $input['auto_approve'] === 1)) ? '1' : '0';
        $chk = $pdo->prepare("SELECT COUNT(*) as cnt FROM site_settings WHERE key = 'auto_approve_comments'");
        $chk->execute();
        if ($chk->fetch()['cnt'] > 0) {
            $pdo->prepare("UPDATE site_settings SET value = ? WHERE key = 'auto_approve_comments'")->execute([$autoApproveVal]);
        } else {
            $pdo->prepare("INSERT INTO site_settings (key, value) VALUES ('auto_approve_comments', ?)")->execute([$autoApproveVal]);
        }
        echo json_encode([
            "success" => true,
            "auto_approve" => ($autoApproveVal === '1'),
            "message" => ($autoApproveVal === '1' ? "Automatic comment approval enabled." : "Manual comment moderation enabled.")
        ]);
        exit;
    }
}

// ── COMMENTS RESOURCE ────────────────────────────────────────────────────────
if ($resource === 'comments') {
    if ($method === 'GET') {
        $all = !empty($_GET['all']);
        if ($all) {
            $stmt = $pdo->query("
                SELECT cm.*, p.title as post_title, p.slug as post_slug, parent.author_name as parent_author_name
                FROM comments cm
                LEFT JOIN posts p ON cm.post_id = p.id
                LEFT JOIN comments parent ON cm.parent_id = parent.id
                ORDER BY cm.created_at DESC
            ");
            echo json_encode(["success" => true, "comments" => $stmt->fetchAll()]);
            exit;
        }

        $postIdentifier = trim($_GET['post_id'] ?? $_GET['slug'] ?? '');
        $realPostId = is_numeric($postIdentifier) ? intval($postIdentifier) : 0;
        if (!$realPostId && $postIdentifier) {
            $stmtP = $pdo->prepare("SELECT id FROM posts WHERE slug = ?");
            $stmtP->execute([$postIdentifier]);
            $foundP = $stmtP->fetch();
            if ($foundP) $realPostId = intval($foundP['id']);
        }

        if ($realPostId <= 0) {
            echo json_encode(["success" => true, "comments" => []]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM comments WHERE post_id = ? AND status = 'approved' ORDER BY created_at DESC");
        $stmt->execute([$realPostId]);
        echo json_encode(["success" => true, "comments" => $stmt->fetchAll()]);
        exit;
    }

    if ($method === 'POST') {
        $honeypot = trim($input['website_url'] ?? $input['hp'] ?? '');
        if (!empty($honeypot)) {
            // Spam bot trapped
            echo json_encode(["success" => true, "commentId" => 0, "message" => "Comment submitted"]);
            exit;
        }

        if (!csp_check_rate_limit('post_comment', 5, 120)) {
            http_response_code(429);
            echo json_encode(["success" => false, "error" => "Comment posting rate limit exceeded. Please wait 2 minutes."]);
            exit;
        }

        $parentId = !empty($input['parent_id']) ? intval($input['parent_id']) : null;
        $postIdentifier = trim($input['post_id'] ?? $input['slug'] ?? '');
        $authorName = csp_sanitize($input['author_name'] ?? 'Anonymous');
        $authorEmail = filter_var(trim($input['author_email'] ?? ''), FILTER_VALIDATE_EMAIL) ? trim($input['author_email']) : '';
        $content = csp_sanitize($input['content'] ?? '');

        // Determine Status: Check Auto-Approval Setting
        $autoRow = $pdo->query("SELECT value FROM site_settings WHERE key = 'auto_approve_comments' LIMIT 1")->fetch();
        $isAuto = ($autoRow && $autoRow['value'] === '1');
        $isAdminReply = !empty($input['is_admin_reply']) || ($authorName === 'Editor Admin') || ($authorEmail === 'admin@conspodium.com');
        $status = ($isAdminReply || $isAuto) ? 'approved' : 'pending';

        if (!$postIdentifier || !$content) {
            echo json_encode(["success" => false, "error" => "Post ID or slug and comment text are required"]);
            exit;
        }

        $realPostId = is_numeric($postIdentifier) ? intval($postIdentifier) : 0;
        if (!$realPostId) {
            $stmtP = $pdo->prepare("SELECT id FROM posts WHERE slug = ?");
            $stmtP->execute([$postIdentifier]);
            $foundP = $stmtP->fetch();
            if ($foundP) $realPostId = intval($foundP['id']);
        }

        if ($realPostId <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Valid Post ID or slug is required"]);
            exit;
        }

        // Only fallback to cookie if author_name or author_email is empty
        if (empty($authorName) && !empty($_COOKIE['csp_user_comment_author'])) {
            $authorName = csp_sanitize($_COOKIE['csp_user_comment_author']);
        }
        if (empty($authorEmail) && !empty($_COOKIE['csp_user_comment_email'])) {
            $authorEmail = trim($_COOKIE['csp_user_comment_email']);
        }
        if (empty($authorName)) {
            $authorName = 'Anonymous Reader';
        }

        $stmt = $pdo->prepare("INSERT INTO comments (post_id, parent_id, author_name, author_email, content, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$realPostId, $parentId, $authorName, $authorEmail, $content, $status]);
        $newCommentId = $pdo->lastInsertId();

        // Lock identity into long-lived HTTP cookie & Server Device Registry (Only for public reader comments)
        $isAdminReply = !empty($input['is_admin_reply']) || ($authorName === 'Editor Admin') || ($authorEmail === 'admin@conspodium.com');
        if (!$isAdminReply) {
            $devKey = trim($input['device_key'] ?? $_COOKIE['csp_device_key'] ?? '');
            if (!$devKey) $devKey = bin2hex(random_bytes(16));
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ipHash = hash('sha256', $clientIp . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            $now = date('Y-m-d H:i:s');
            try {
                $chk = $pdo->prepare("SELECT id FROM device_identities WHERE device_key = ? OR ip_hash = ? LIMIT 1");
                $chk->execute([$devKey, $ipHash]);
                $existing = $chk->fetch();
                if ($existing) {
                    $pdo->prepare("UPDATE device_identities SET author_name = ?, author_email = ?, device_key = ?, ip_hash = ?, updated_at = ? WHERE id = ?")->execute([$authorName, $authorEmail, $devKey, $ipHash, $now, $existing['id']]);
                } else {
                    $pdo->prepare("INSERT INTO device_identities (device_key, ip_hash, author_name, author_email, updated_at) VALUES (?, ?, ?, ?, ?)")->execute([$devKey, $ipHash, $authorName, $authorEmail, $now]);
                }
                @setcookie('csp_device_key', $devKey, time() + 31536000, '/');
            } catch (Exception $e) {}

            @setcookie('csp_user_comment_author', $authorName, time() + 31536000, '/');
            if ($authorEmail) {
                @setcookie('csp_user_comment_email', $authorEmail, time() + 31536000, '/');
            }
        }

        // Trigger email notification if replying to a parent comment author
        if ($parentId > 0) {
            try {
                $stmtParent = $pdo->prepare("SELECT author_name, author_email FROM comments WHERE id = ?");
                $stmtParent->execute([$parentId]);
                $parentComment = $stmtParent->fetch();
                if ($parentComment && filter_var($parentComment['author_email'], FILTER_VALIDATE_EMAIL)) {
                    $pEmail = $parentComment['author_email'];
                    $pName = $parentComment['author_name'];
                    $subject = "New reply to your comment on Conspodium";
                    $msgBody = "Hello " . $pName . ",\n\n" . $authorName . " replied to your comment on Conspodium:\n\n\"" . $content . "\"\n\nVisit Conspodium to view the conversation.";
                    @mail($pEmail, $subject, $msgBody, "From: no-reply@conspodium.com\r\nContent-Type: text/plain; charset=UTF-8");
                }
            } catch (Exception $ex) {}
        }

        // Admin Email Notification
        try {
            $adminEmail = "admin@conspodium.com";
            $subject = "New Comment on Conspodium Article #" . $realPostId;
            $msgBody = "New comment posted by " . $authorName . " (" . $authorEmail . "):\n\n\"" . $content . "\"\n\nModerate in Dashboard: " . (getenv('APP_URL') ?: 'http://localhost:8080') . "/dashboard/";
            @mail($adminEmail, $subject, $msgBody, "From: no-reply@conspodium.com\r\nContent-Type: text/plain; charset=UTF-8");
        } catch (Exception $ex) {}

        echo json_encode(["success" => true, "commentId" => $newCommentId, "message" => "Comment posted successfully"]);
        exit;
    }

    if ($method === 'PUT') {
        $id = intval($_GET['id'] ?? $input['id'] ?? 0);
        $status = csp_sanitize($_GET['status'] ?? $input['status'] ?? 'approved');
        if ($id > 0) {
            if ($status === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(["success" => true, "message" => "Comment deleted successfully"]);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE comments SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            echo json_encode(["success" => true, "message" => "Comment status updated to " . $status]);
            exit;
        }
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Comment ID required"]);
        exit;
    }

    if ($method === 'DELETE') {
        $id = intval($_GET['id'] ?? $input['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(["success" => true, "message" => "Comment deleted successfully"]);
            exit;
        }
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Comment ID required"]);
        exit;
    }
}

// ── POSTS RESOURCE ───────────────────────────────────────────────────────────
if ($method === 'GET') {
    if (isset($_GET['spotlight']) || ($_GET['action'] ?? '') === 'get_spotlight') {
        $stmt = $pdo->query("
            SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon,
                   (SELECT COUNT(id) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count
            FROM posts p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.is_spotlight = 1 AND (p.status = 'published' OR p.status IS NULL OR p.status = '')
            LIMIT 1
        ");
        $spotlight = $stmt->fetch();

        // Fallback to latest published if no custom spotlight is set
        if (!$spotlight) {
            $stmt = $pdo->query("
                SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon,
                       (SELECT COUNT(id) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count
                FROM posts p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE (p.status = 'published' OR p.status IS NULL OR p.status = '')
                ORDER BY p.published_at DESC
                LIMIT 1
            ");
            $spotlight = $stmt->fetch();
        }

        echo json_encode([
            "success" => true,
            "post" => $spotlight,
            "spotlight_post_id" => $spotlight ? intval($spotlight['id']) : null
        ]);
        exit;
    }

    if (isset($_GET['hero_slide4']) || ($_GET['action'] ?? '') === 'get_hero_slide4') {
        $stmt = $pdo->query("
            SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon,
                   (SELECT COUNT(id) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count
            FROM posts p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.is_hero_slide4 = 1 AND (p.status = 'published' OR p.status IS NULL OR p.status = '')
            LIMIT 1
        ");
        $heroPost = $stmt->fetch();

        if (!$heroPost) {
            $stmt = $pdo->query("
                SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon,
                       (SELECT COUNT(id) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count
                FROM posts p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE (c.slug = 'interview-transcripts' OR c.name LIKE '%interview%') AND (p.status = 'published' OR p.status IS NULL OR p.status = '')
                ORDER BY p.published_at DESC
                LIMIT 1
            ");
            $heroPost = $stmt->fetch();
        }

        echo json_encode([
            "success" => true,
            "post" => $heroPost
        ]);
        exit;
    }

    $slugOrId = $_GET['slug'] ?? $_GET['id'] ?? null;

    if ($slugOrId) {
        $cleanParam = trim(rtrim(trim($slugOrId), '/'));
        $isId = is_numeric($cleanParam);
        
        $post = null;
        if ($isId) {
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon,
                       (SELECT COUNT(id) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count
                FROM posts p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id = ?
            ");
            $stmt->execute([intval($cleanParam)]);
            $post = $stmt->fetch();
        }

        if (!$post) {
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon,
                       (SELECT COUNT(id) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count
                FROM posts p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.slug = ?
            ");
            $stmt->execute([$cleanParam]);
            $post = $stmt->fetch();
        }

        // Fallback: search by prefix or partial match if exact slug not found
        if (!$post && strlen($cleanParam) > 3) {
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon,
                       (SELECT COUNT(id) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count
                FROM posts p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.slug LIKE ? OR p.title LIKE ?
                LIMIT 1
            ");
            $stmt->execute(['%' . $cleanParam . '%', '%' . $cleanParam . '%']);
            $post = $stmt->fetch();
        }

        if (!$post) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Article not found"]);
            exit;
        }

        // Increment views
        $pdo->prepare("UPDATE posts SET views = views + 1 WHERE id = ?")->execute([$post['id']]);
        $post['views'] += 1;

        $transcriptStmt = $pdo->prepare("SELECT * FROM transcripts WHERE post_id = ?");
        $transcriptStmt->execute([$post['id']]);
        $transcript = $transcriptStmt->fetch();

        echo json_encode([
            "success" => true,
            "post" => $post,
            "transcript" => $transcript ? json_decode($transcript['transcript_content'], true) : null
        ]);
        exit;
    }

    // List Query
    $category = $_GET['category'] ?? null;
    $search = $_GET['search'] ?? null;
    $featured = $_GET['featured'] ?? null;
    $statusFilter = $_GET['status'] ?? null;
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);

    $sql = "
        SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon,
               (SELECT COUNT(id) FROM comments WHERE post_id = p.id AND status = 'approved') as comment_count
        FROM posts p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE 1=1
    ";
    $params = [];

    if ($statusFilter) {
        if ($statusFilter === 'draft') {
            $sql .= " AND p.status = 'draft'";
        } elseif ($statusFilter === 'published') {
            $sql .= " AND (p.status = 'published' OR p.status IS NULL OR p.status = '')";
        }
    } else {
        // Public default: only return published articles unless status=all is specified by admin
        $sql .= " AND (p.status = 'published' OR p.status IS NULL OR p.status = '')";
    }

    if ($category) {
        $sql .= " AND (c.slug = ? OR c.name LIKE ?)";
        $params[] = $category;
        $params[] = "%$category%";
    }

    if ($search) {
        $cleanSearch = trim(preg_replace('/[^a-zA-Z0-9]+/', ' ', $search));
        $tokens = array_filter(explode(' ', $cleanSearch));
        if (empty($tokens)) {
            $tokens = [$search];
        }

        foreach ($tokens as $token) {
            $sql .= " AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ? OR p.author_name LIKE ? OR c.name LIKE ?)";
            $params[] = "%$token%";
            $params[] = "%$token%";
            $params[] = "%$token%";
            $params[] = "%$token%";
            $params[] = "%$token%";
        }
    }

    if ($featured === '1' || $featured === 'true') {
        $sql .= " AND p.is_featured = 1";
    }

    $sql .= " ORDER BY p.published_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    // Calculate total count matching active filters
    $countSql = "
        SELECT COUNT(*) as count
        FROM posts p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE 1=1
    ";
    $countParams = [];

    if ($statusFilter) {
        if ($statusFilter === 'draft') {
            $countSql .= " AND p.status = 'draft'";
        } elseif ($statusFilter === 'published') {
            $countSql .= " AND (p.status = 'published' OR p.status IS NULL OR p.status = '')";
        }
    } else {
        $countSql .= " AND (p.status = 'published' OR p.status IS NULL OR p.status = '')";
    }

    if ($category) {
        $countSql .= " AND (c.slug = ? OR c.name LIKE ?)";
        $countParams[] = $category;
        $countParams[] = "%$category%";
    }

    if ($search) {
        $cleanSearch = trim(preg_replace('/[^a-zA-Z0-9]+/', ' ', $search));
        $tokens = array_filter(explode(' ', $cleanSearch));
        if (empty($tokens)) {
            $tokens = [$search];
        }

        foreach ($tokens as $token) {
            $countSql .= " AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ? OR p.author_name LIKE ? OR c.name LIKE ?)";
            $countParams[] = "%$token%";
            $countParams[] = "%$token%";
            $countParams[] = "%$token%";
            $countParams[] = "%$token%";
            $countParams[] = "%$token%";
        }
    }

    if ($featured === '1' || $featured === 'true') {
        $countSql .= " AND p.is_featured = 1";
    }

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = intval($countStmt->fetch()['count']);

    echo json_encode(["success" => true, "posts" => $posts, "total" => $total, "limit" => $limit, "offset" => $offset]);
    exit;
}

if ($method === 'POST') {
    $action = $_GET['action'] ?? ($input['action'] ?? '');
    if ($action === 'toggle_featured' || $action === 'set_spotlight') {
        requireAdmin();
        $targetId = intval($input['id'] ?? $_GET['id'] ?? 0);
        if (!$targetId) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Invalid post ID"]);
            exit;
        }
        
        if ($action === 'set_spotlight') {
            $pdo->exec("UPDATE posts SET is_spotlight = 0");
            $stmt = $pdo->prepare("UPDATE posts SET is_spotlight = 1 WHERE id = ?");
            $stmt->execute([$targetId]);

            $fetchStmt = $pdo->prepare("
                SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon
                FROM posts p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id = ?
            ");
            $fetchStmt->execute([$targetId]);
            $updatedPost = $fetchStmt->fetch();

            echo json_encode([
                "success" => true,
                "is_spotlight" => 1,
                "spotlight_post_id" => $targetId,
                "post" => $updatedPost,
                "message" => "Post successfully set as the active Featured Spotlight story!"
            ]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT is_featured FROM posts WHERE id = ?");
        $stmt->execute([$targetId]);
        $curr = $stmt->fetchColumn();
        $newStatus = ($curr == 1) ? 0 : 1;
        
        $upStmt = $pdo->prepare("UPDATE posts SET is_featured = ? WHERE id = ?");
        $upStmt->execute([$newStatus, $targetId]);
        
        echo json_encode([
            "success" => true,
            "is_featured" => $newStatus,
            "message" => $newStatus ? "Article marked as Featured ⭐" : "Article removed from Featured"
        ]);
        exit;
    }

    requireAdmin();
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    $eyebrow = trim($input['eyebrow'] ?? 'Community Essay');
    $excerpt = trim($input['excerpt'] ?? $title);
    $categoryId = !empty($input['categoryId']) ? intval($input['categoryId']) : null;
    $authorName = trim($input['authorName'] ?? 'Conspodium Editorial');
    $authorAvatar = trim($input['authorAvatar'] ?? 'CP');
    $featuredImage = trim($input['featuredImage'] ?? './wp-content/uploads/2026/01/girls-walk-along-streets-city-scaled.jpg');
    $readingTime = trim($input['readingTime'] ?? '5 min read');
    $isFeatured = !empty($input['isFeatured']) ? 1 : 0;
    $status = trim($input['status'] ?? 'published');
    $publishedAt = !empty($input['publishedAt']) ? date('Y-m-d H:i:s', strtotime($input['publishedAt'])) : date('Y-m-d H:i:s');

    if (!$title || !$content) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Title and content are required"]);
        exit;
    }

    $slug = slugify($title);
    $checkStmt = $pdo->prepare("SELECT id FROM posts WHERE slug = ?");
    $checkStmt->execute([$slug]);
    if ($checkStmt->fetch()) {
        $slug .= '-' . substr(time(), -4);
    }

    $isHeroSlide4 = isset($input['isHeroSlide4']) ? ($input['isHeroSlide4'] ? 1 : 0) : (isset($input['is_hero_slide4']) ? ($input['is_hero_slide4'] ? 1 : 0) : 0);
    if ($isHeroSlide4 === 1) {
        $pdo->exec("UPDATE posts SET is_hero_slide4 = 0");
    }

    $stmt = $pdo->prepare("
        INSERT INTO posts (title, slug, eyebrow, excerpt, content, category_id, author_name, author_avatar, featured_image, reading_time, views, is_featured, is_hero_slide4, status, published_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)
    ");
    $stmt->execute([$title, $slug, $eyebrow, $excerpt, $content, $categoryId, $authorName, $authorAvatar, $featuredImage, $readingTime, $isFeatured, $isHeroSlide4, $status, $publishedAt]);

    echo json_encode([
        "success" => true,
        "postId" => $pdo->lastInsertId(),
        "slug" => $slug,
        "message" => ($status === 'draft') ? "Article saved as draft successfully!" : "Article published successfully!"
    ]);
    exit;
}

if ($method === 'PUT') {
    requireAdmin();
    $id = intval($_GET['id'] ?? 0);
    $stmtExist = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
    $stmtExist->execute([$id]);
    $existing = $stmtExist->fetch();

    if (!$existing) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Article not found"]);
        exit;
    }

    $title = $input['title'] ?? $existing['title'];
    $slug = $existing['slug'];
    if (!empty($input['title']) && $input['title'] !== $existing['title']) {
        $slug = slugify($input['title']);
    }

    $status = isset($input['status']) ? trim($input['status']) : ($existing['status'] ?? 'published');
    $isHeroSlide4 = isset($input['isHeroSlide4']) ? ($input['isHeroSlide4'] ? 1 : 0) : (isset($input['is_hero_slide4']) ? ($input['is_hero_slide4'] ? 1 : 0) : intval($existing['is_hero_slide4'] ?? 0));

    if ($isHeroSlide4 === 1) {
        $pdo->exec("UPDATE posts SET is_hero_slide4 = 0");
    }

    $stmt = $pdo->prepare("
        UPDATE posts
        SET title = ?, slug = ?, eyebrow = ?, excerpt = ?, content = ?, category_id = ?, author_name = ?, author_avatar = ?, featured_image = ?, reading_time = ?, is_featured = ?, is_hero_slide4 = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $title,
        $slug,
        $input['eyebrow'] ?? $existing['eyebrow'],
        $input['excerpt'] ?? $existing['excerpt'],
        $input['content'] ?? $existing['content'],
        !empty($input['categoryId']) ? intval($input['categoryId']) : $existing['category_id'],
        $input['authorName'] ?? $existing['author_name'],
        $input['authorAvatar'] ?? $existing['author_avatar'],
        $input['featuredImage'] ?? $existing['featured_image'],
        $input['readingTime'] ?? $existing['reading_time'],
        isset($input['isFeatured']) ? ($input['isFeatured'] ? 1 : 0) : $existing['is_featured'],
        $isHeroSlide4,
        $status,
        $id
    ]);

    echo json_encode(["success" => true, "message" => "Article updated successfully!"]);
    exit;
}

if ($method === 'DELETE' || ($method === 'POST' && ($GET['action'] ?? '') === 'bulk_delete')) {
    requireAdmin();
    $idsParam = $_GET['ids'] ?? $_GET['id'] ?? $input['ids'] ?? $input['id'] ?? null;

    if ($idsParam) {
        $rawIds = is_array($idsParam) ? $idsParam : explode(',', (string)$idsParam);
        $ids = array_values(array_filter(array_map('intval', $rawIds), function($i) { return $i > 0; }));

        if (!empty($ids)) {
            $inClause = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM posts WHERE id IN ($inClause)");
            $stmt->execute($ids);
            $count = $stmt->rowCount();
            echo json_encode(["success" => true, "message" => $count . " article(s) deleted successfully"]);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(["success" => false, "error" => "No valid article IDs provided"]);
    exit;
}
