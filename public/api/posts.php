<?php
// api/posts.php - Articles & Categories CRUD API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/db.php';

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
        $name = trim($input['name'] ?? '');
        $icon = trim($input['icon'] ?? '🏷️');
        $desc = trim($input['description'] ?? '');

        if (!$name) {
            echo json_encode(["success" => false, "error" => "Category name is required"]);
            exit;
        }

        $slug = slugify($name);
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $icon, $desc]);

        echo json_encode(["success" => true, "categoryId" => $pdo->lastInsertId(), "message" => "Category created successfully"]);
        exit;
    }

    if ($method === 'PUT') {
        $id = intval($_GET['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $icon = trim($input['icon'] ?? '🏷️');
        $desc = trim($input['description'] ?? '');

        $slug = slugify($name);
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $icon, $desc, $id]);

        echo json_encode(["success" => true, "message" => "Category updated successfully"]);
        exit;
    }

    if ($method === 'DELETE') {
        $id = intval($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(["success" => true, "message" => "Category deleted successfully"]);
        exit;
    }
}

// ── POSTS RESOURCE ───────────────────────────────────────────────────────────
if ($method === 'GET') {
    $slugOrId = $_GET['slug'] ?? $_GET['id'] ?? null;

    if ($slugOrId) {
        $isId = is_numeric($slugOrId);
        $query = $isId 
            ? "SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?"
            : "SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE p.slug = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$slugOrId]);
        $post = $stmt->fetch();

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
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);

    $sql = "
        SELECT p.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon
        FROM posts p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE 1=1
    ";
    $params = [];

    if ($category) {
        $sql .= " AND (c.slug = ? OR c.name LIKE ?)";
        $params[] = $category;
        $params[] = "%$category%";
    }

    if ($search) {
        $sql .= " AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
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

    $total = $pdo->query("SELECT COUNT(*) as count FROM posts")->fetch()['count'];

    echo json_encode(["success" => true, "posts" => $posts, "total" => intval($total), "limit" => $limit, "offset" => $offset]);
    exit;
}

if ($method === 'POST') {
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

    $stmt = $pdo->prepare("
        INSERT INTO posts (title, slug, eyebrow, excerpt, content, category_id, author_name, author_avatar, featured_image, reading_time, views, is_featured, published_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)
    ");
    $stmt->execute([$title, $slug, $eyebrow, $excerpt, $content, $categoryId, $authorName, $authorAvatar, $featuredImage, $readingTime, $isFeatured, $publishedAt]);

    echo json_encode([
        "success" => true,
        "postId" => $pdo->lastInsertId(),
        "slug" => $slug,
        "message" => "Article created successfully!"
    ]);
    exit;
}

if ($method === 'PUT') {
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

    $stmt = $pdo->prepare("
        UPDATE posts
        SET title = ?, slug = ?, eyebrow = ?, excerpt = ?, content = ?, category_id = ?, author_name = ?, author_avatar = ?, featured_image = ?, reading_time = ?, is_featured = ?
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
        $id
    ]);

    echo json_encode(["success" => true, "message" => "Article updated successfully!"]);
    exit;
}

if ($method === 'DELETE') {
    $id = intval($_GET['id'] ?? 0);
    $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$id]);
    echo json_encode(["success" => true, "message" => "Article deleted successfully"]);
    exit;
}
