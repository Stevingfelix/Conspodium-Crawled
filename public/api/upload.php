<?php
// api/upload.php - PHP Image Upload Handler
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$uploadsDir = __DIR__ . '/../public/uploads';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

require_once __DIR__ . '/auth_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method not allowed"]);
    exit;
}

requireAdmin();

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "No valid image file uploaded"]);
    exit;
}

$file = $_FILES['image'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

$allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
if (!in_array($ext, $allowedExts)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Only image files (JPG, PNG, GIF, WEBP) are allowed"]);
    exit;
}

$newFilename = 'img-' . time() . '-' . rand(1000, 9999) . '.' . $ext;
$targetPath = $uploadsDir . '/' . $newFilename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Copy to root /uploads as well if root uploads directory exists
    $rootUploads = __DIR__ . '/../uploads';
    if (file_exists($rootUploads)) {
        copy($targetPath, $rootUploads . '/' . $newFilename);
    } else {
        mkdir($rootUploads, 0777, true);
        copy($targetPath, $rootUploads . '/' . $newFilename);
    }

    echo json_encode([
        "success" => true,
        "imageUrl" => "/uploads/" . $newFilename,
        "filename" => $newFilename,
        "message" => "Image uploaded successfully"
    ]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Failed to save uploaded image"]);
}
