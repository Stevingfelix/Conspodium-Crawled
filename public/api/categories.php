<?php
// api/categories.php - Dynamic Categories REST API
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Token, X-CSRF-Token");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// LIST CATEGORIES
if ($action === 'list') {
    try {
        $stmt = $pdo->query("
            SELECT c.*, COUNT(p.id) as post_count
            FROM categories c
            LEFT JOIN posts p ON c.id = p.category_id
            GROUP BY c.id
            ORDER BY c.display_order ASC, c.id ASC
        ");
        $categories = $stmt->fetchAll();
        echo json_encode(["success" => true, "categories" => $categories, "data" => $categories, "db_path" => realpath($dbPath)]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// ADMIN ACTIONS
if (in_array($action, ['create', 'update', 'delete'])) {
    require_once __DIR__ . '/auth_guard.php';

    $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;

    if ($action === 'create') {
        $name = csp_sanitize($input['name'] ?? '');
        $description = csp_sanitize($input['description'] ?? '');
        $icon = csp_sanitize($input['icon'] ?? '🏷️');
        $image = csp_sanitize($input['image'] ?? '/uploads/cat_diaspora_matters.png');
        $displayOrder = intval($input['display_order'] ?? 0);

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Category name is required."]);
            exit();
        }

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, description, image, display_order) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $slug, $icon, $description, $image, $displayOrder]);
            echo json_encode(["success" => true, "message" => "Category created successfully!", "id" => $pdo->lastInsertId(), "db_path" => realpath($dbPath)]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit();
    }

    if ($action === 'update') {
        $id = intval($input['id'] ?? 0);
        $name = csp_sanitize($input['name'] ?? '');
        $description = csp_sanitize($input['description'] ?? '');
        $icon = csp_sanitize($input['icon'] ?? '🏷️');
        $image = csp_sanitize($input['image'] ?? '');
        $displayOrder = intval($input['display_order'] ?? 0);

        if (!$id || empty($name)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Valid ID and Category name are required."]);
            exit();
        }

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

        try {
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, description = ?, image = ?, display_order = ? WHERE id = ?");
            $stmt->execute([$name, $slug, $icon, $description, $image, $displayOrder, $id]);
            echo json_encode(["success" => true, "message" => "Category updated successfully!"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit();
    }

    if ($action === 'delete') {
        $id = intval($input['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Category ID is required."]);
            exit();
        }

        try {
            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            echo json_encode(["success" => true, "message" => "Category deleted successfully!"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit();
    }
}
