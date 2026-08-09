<?php
// api/submissions.php - Story Submissions & Approval API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

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
    $stmt = $pdo->query("SELECT * FROM story_submissions ORDER BY submitted_at DESC");
    echo json_encode(["success" => true, "submissions" => $stmt->fetchAll()]);
    exit;
}

// ── APPROVE SUBMISSION (ADMIN ONLY) ──────────────────────────────────────────
if ($method === 'POST' && $action === 'approve') {
    requireAdmin();
    $id = intval($_GET['id'] ?? $input['id'] ?? 0);
    $categoryId = intval($input['categoryId'] ?? 1);
    $featuredImage = trim($input['featuredImage'] ?? './wp-content/uploads/2026/01/girls-walk-along-streets-city-scaled.jpg');

    $subStmt = $pdo->prepare("SELECT * FROM story_submissions WHERE id = ?");
    $subStmt->execute([$id]);
    $sub = $subStmt->fetch();

    if (!$sub) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "Submission not found"]);
        exit;
    }

    $slug = slugify($sub['title']);
    $checkSlug = $pdo->prepare("SELECT id FROM posts WHERE slug = ?");
    $checkSlug->execute([$slug]);
    if ($checkSlug->fetch()) {
        $slug .= '-' . substr(time(), -4);
    }

    $nameParts = explode(' ', trim($sub['author_name']));
    $initials = strtoupper(substr($nameParts[0] ?? '', 0, 1) . substr($nameParts[1] ?? '', 0, 1));
    if (!$initials) $initials = 'CP';

    $excerpt = substr(strip_tags($sub['content']), 0, 160) . '...';
    $htmlContent = '<p>' . str_replace("\n\n", '</p><p>', htmlspecialchars($sub['content'])) . '</p>';

    // Insert into posts
    $postStmt = $pdo->prepare("
        INSERT INTO posts (title, slug, eyebrow, excerpt, content, category_id, author_name, author_avatar, featured_image, reading_time, views, is_featured, published_at)
        VALUES (?, ?, 'Community Voice', ?, ?, ?, ?, ?, ?, '6 min read', 0, 0, CURRENT_TIMESTAMP)
    ");
    $postStmt->execute([
        $sub['title'],
        $slug,
        $excerpt,
        $htmlContent,
        $categoryId,
        $sub['author_name'],
        $initials,
        $featuredImage
    ]);

    // Mark submission as approved
    $pdo->prepare("UPDATE story_submissions SET status = 'approved' WHERE id = ?")->execute([$id]);

    echo json_encode([
        "success" => true,
        "postId" => $pdo->lastInsertId(),
        "message" => "Submission approved and published as a live article!"
    ]);
    exit;
}

// ── REJECT SUBMISSION ────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'reject') {
    requireAdmin();
    $id = intval($_GET['id'] ?? $input['id'] ?? 0);
    $pdo->prepare("UPDATE story_submissions SET status = 'rejected' WHERE id = ?")->execute([$id]);
    echo json_encode(["success" => true, "message" => "Submission marked as rejected"]);
    exit;
}

// ── DELETE SUBMISSION ────────────────────────────────────────────────────────
if ($method === 'DELETE' || ($method === 'POST' && $action === 'delete')) {
    requireAdmin();
    $id = intval($_GET['id'] ?? $input['id'] ?? 0);
    $pdo->prepare("DELETE FROM story_submissions WHERE id = ?")->execute([$id]);
    echo json_encode(["success" => true, "message" => "Submission deleted"]);
    exit;
}

// ── CREATE NEW STORY SUBMISSION ──────────────────────────────────────────────
if ($method === 'POST') {
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $bio = trim($input['bio'] ?? '');
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    $attachmentUrl = trim($input['attachmentUrl'] ?? '');

    if (!$name || !$email || !$title || !$content) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Name, email, title, and content are required"]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO story_submissions (author_name, author_email, author_bio, title, content, attachment_url, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$name, $email, $bio, $title, $content, $attachmentUrl]);

    echo json_encode([
        "success" => true,
        "submissionId" => $pdo->lastInsertId(),
        "message" => "Your story has been successfully submitted for editorial review!"
    ]);
    exit;
}
