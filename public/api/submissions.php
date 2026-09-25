<?php
// api/submissions.php - Story Submissions & Approval API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Admin-Token, Authorization");

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
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ── GET ALL SUBMISSIONS (ADMIN ONLY) ─────────────────────────────────────────
if ($method === 'GET') {
    requireAdmin();
    $statusFilter = $_GET['status'] ?? 'all';
    if ($statusFilter && in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
        $stmt = $pdo->prepare("SELECT * FROM story_submissions WHERE status = ? ORDER BY submitted_at DESC");
        $stmt->execute([$statusFilter]);
    } else {
        $stmt = $pdo->query("SELECT * FROM story_submissions ORDER BY submitted_at DESC");
    }
    echo json_encode(["success" => true, "submissions" => $stmt->fetchAll()]);
    exit;
}

// ── EMPTY TRASH (ADMIN ONLY) ─────────────────────────────────────────────────
if (($method === 'DELETE' || $method === 'POST') && $action === 'empty_trash') {
    requireAdmin();
    $stmt = $pdo->query("DELETE FROM story_submissions WHERE status = 'rejected'");
    $count = $stmt->rowCount();
    echo json_encode(["success" => true, "message" => "Emptied trash (" . $count . " rejected submissions deleted)"]);
    exit;
}

// ── APPROVE SUBMISSION (STATUS UPDATE ONLY) ──────────────────────────────────
if ($method === 'POST' && $action === 'approve') {
    requireAdmin();
    $id = intval($_GET['id'] ?? $input['id'] ?? 0);
    $subStmt = $pdo->prepare("SELECT * FROM story_submissions WHERE id = ?");
    $subStmt->execute([$id]);
    $sub = $subStmt->fetch();

    if (!$sub) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Submission not found"]);
        exit;
    }

    $pdo->prepare("UPDATE story_submissions SET status = 'approved' WHERE id = ?")->execute([$id]);
    echo json_encode([
        "success" => true,
        "message" => "Submission accepted and marked as approved!"
    ]);
    exit;
}

// ── PUBLISH / DRAFT APPROVED SUBMISSION AS STORY ARTICLE ───────────────────────
if ($method === 'POST' && $action === 'publish_story') {
    requireAdmin();
    $id = intval($_GET['id'] ?? $input['id'] ?? 0);
    $categoryId = intval($input['categoryId'] ?? 1);
    $featuredImage = trim($input['featuredImage'] ?? '');
    $publishStatus = trim($input['publishStatus'] ?? 'published');
    if (!in_array($publishStatus, ['published', 'draft'])) {
        $publishStatus = 'published';
    }

    $subStmt = $pdo->prepare("SELECT * FROM story_submissions WHERE id = ?");
    $subStmt->execute([$id]);
    $sub = $subStmt->fetch();

    if (!$sub) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Submission not found"]);
        exit;
    }

    $title = csp_sanitize(trim($input['title'] ?? $sub['title']));
    if (empty($title)) $title = $sub['title'];

    $rawContent = trim($input['content'] ?? $sub['content']);
    if (empty($rawContent)) $rawContent = $sub['content'];

    $excerpt = csp_sanitize(trim($input['excerpt'] ?? ''));
    if (empty($excerpt)) {
        $excerpt = substr(strip_tags($rawContent), 0, 160) . '...';
    }

    $eyebrow = csp_sanitize(trim($input['eyebrow'] ?? 'Community Voice'));
    if (empty($eyebrow)) $eyebrow = 'Community Voice';

    if (empty($featuredImage) && !empty($sub['attachment_url'])) {
        $featuredImage = $sub['attachment_url'];
    }
    if (empty($featuredImage)) {
        $featuredImage = '/wp-content/uploads/2026/01/girls-walk-along-streets-city-scaled.jpg';
    }

    $slug = slugify($title);
    $checkSlug = $pdo->prepare("SELECT id FROM posts WHERE slug = ?");
    $checkSlug->execute([$slug]);
    if ($checkSlug->fetch()) {
        $slug .= '-' . substr(time(), -4);
    }

    $nameParts = explode(' ', trim($sub['author_name']));
    $initials = strtoupper(substr($nameParts[0] ?? '', 0, 1) . substr($nameParts[1] ?? '', 0, 1));
    if (!$initials) $initials = 'CP';

    $htmlContent = (strpos($rawContent, '<p>') !== false) ? $rawContent : ('<p>' . str_replace("\n\n", '</p><p>', htmlspecialchars($rawContent)) . '</p>');

    // Insert into posts table
    $postStmt = $pdo->prepare("
        INSERT INTO posts (title, slug, eyebrow, excerpt, content, category_id, author_name, author_avatar, featured_image, reading_time, views, is_featured, status, published_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '6 min read', 0, 0, ?, CURRENT_TIMESTAMP)
    ");
    $postStmt->execute([
        $title,
        $slug,
        $eyebrow,
        $excerpt,
        $htmlContent,
        $categoryId,
        $sub['author_name'],
        $initials,
        $featuredImage,
        $publishStatus
    ]);

    $newPostId = $pdo->lastInsertId();

    // Mark submission as approved
    $pdo->prepare("UPDATE story_submissions SET status = 'approved' WHERE id = ?")->execute([$id]);

    $msg = ($publishStatus === 'draft')
        ? "Article saved as draft in the Articles section!"
        : "Story article successfully published live on Conspodium!";

    echo json_encode([
        "success" => true,
        "postId" => $newPostId,
        "isDraft" => ($publishStatus === 'draft'),
        "message" => $msg
    ]);
    exit;
}

// ── REJECT SUBMISSION ────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'reject') {
    requireAdmin();
    $id = intval($_GET['id'] ?? $input['id'] ?? 0);
    $pdo->prepare("UPDATE story_submissions SET status = 'rejected' WHERE id = ?")->execute([$id]);
    echo json_encode(["success" => true, "message" => "Submission moved to rejected trash"]);
    exit;
}

// ── DELETE SUBMISSION ────────────────────────────────────────────────────────
if ($method === 'DELETE' || ($method === 'POST' && ($action === 'delete' || $action === 'bulk_delete'))) {
    requireAdmin();
    $idsParam = $_GET['ids'] ?? $_GET['id'] ?? $input['ids'] ?? $input['id'] ?? null;

    if ($idsParam) {
        $rawIds = is_array($idsParam) ? $idsParam : explode(',', (string)$idsParam);
        $ids = array_values(array_filter(array_map('intval', $rawIds), function($i) { return $i > 0; }));

        if (!empty($ids)) {
            $inClause = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM story_submissions WHERE id IN ($inClause)");
            $stmt->execute($ids);
            $count = $stmt->rowCount();
            echo json_encode(["success" => true, "message" => $count . " submission(s) permanently deleted"]);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(["success" => false, "error" => "No valid submission IDs provided"]);
    exit;
}

if ($method === 'POST') {
    if (!csp_check_rate_limit('submit_story', 3, 300)) {
        http_response_code(429);
        echo json_encode(["success" => false, "error" => "Submission rate limit exceeded. Please wait 5 minutes before submitting another story."]);
        exit;
    }

    $name = csp_sanitize($input['name'] ?? '');
    $email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? trim($input['email']) : '';
    $bio = csp_sanitize($input['bio'] ?? '');
    $title = csp_sanitize($input['title'] ?? '');
    $category = csp_sanitize($input['category'] ?? '');
    $content = csp_sanitize($input['content'] ?? '');
    $attachmentUrl = csp_sanitize($input['attachmentUrl'] ?? '');

    if (!$name || !$email || !$title || !$content) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Name, email, title, and content are required"]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO story_submissions (author_name, author_email, author_bio, category, title, content, attachment_url, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$name, $email, $bio, $category, $title, $content, $attachmentUrl]);

    $submissionId = $pdo->lastInsertId();

    // Send email notification alert to admin
    $emailSent = false;
    try {
        // Fetch email settings
        $setStmt = $pdo->query("SELECT key, value FROM site_settings WHERE key IN ('admin_email', 'sender_email', 'sender_name')");
        $setRows = $setStmt->fetchAll();
        $setMap = [];
        foreach ($setRows as $r) $setMap[$r['key']] = $r['value'];

        $adminEmail = !empty($setMap['admin_email']) ? $setMap['admin_email'] : 'admin@conspodium.com';
        $senderEmail = !empty($setMap['sender_email']) ? $setMap['sender_email'] : 'noreply@conspodium.com';
        $senderName = !empty($setMap['sender_name']) ? $setMap['sender_name'] : 'Conspodium Alerts';

        $subject = "🏛️ [Conspodium] New Story Submission: " . $title;
        $headers = implode("\r\n", [
            "MIME-Version: 1.0",
            "Content-type: text/html; charset=utf-8",
            "From: " . $senderName . " <" . $senderEmail . ">",
            "Reply-To: " . $name . " <" . $email . ">",
            "X-Mailer: PHP/" . phpversion()
        ]);

        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #061022; color: #ffffff; padding: 24px; border-radius: 12px;'>
            <h2 style='color: #00AEFE; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 12px;'>📰 New Story Submission</h2>
            <p><strong>Title:</strong> " . htmlspecialchars($title) . "</p>
            <p><strong>Author:</strong> " . htmlspecialchars($name) . " (&lt;" . htmlspecialchars($email) . "&gt;)</p>
            " . ($bio ? "<p><strong>Bio:</strong> " . htmlspecialchars($bio) . "</p>" : "") . "
            <hr style='border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 16px 0;' />
            <p><strong>Content Preview:</strong></p>
            <div style='background: rgba(255,255,255,0.05); padding: 16px; border-radius: 8px; font-size: 0.9rem; line-height: 1.6; white-space: pre-wrap;'>" . htmlspecialchars(substr($content, 0, 500)) . (strlen($content) > 500 ? "..." : "") . "</div>
            <br/>
            <p style='font-size: 0.85rem; color: #888;'>Logged into CMS: <a href='http://localhost:8080/dashboard/' style='color: #00AEFE;'>Review on Admin CMS Dashboard</a></p>
        </div>
        ";

        $emailSent = @mail($adminEmail, $subject, $body, $headers);
    } catch (Exception $e) {
        $emailSent = false;
    }

    echo json_encode([
        "success" => true,
        "submissionId" => $submissionId,
        "email_sent" => $emailSent,
        "message" => "Your story has been successfully submitted for editorial review!"
    ]);
    exit;
}
