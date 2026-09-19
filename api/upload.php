<?php
// api/upload.php - Media Image File Upload Handler
@ini_set('upload_max_filesize', '30M');
@ini_set('post_max_size', '30M');
@ini_set('memory_limit', '256M');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Method Not Allowed. POST required."]);
    exit();
}

if (empty($_FILES['image']) && empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "No file uploaded. Key 'image' or 'file' required."]);
    exit();
}

$file = !empty($_FILES['image']) ? $_FILES['image'] : $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => "File size exceeds server limit (upload_max_filesize). Image will be auto-compressed.",
        UPLOAD_ERR_FORM_SIZE => "File size exceeds MAX_FILE_SIZE directive.",
        UPLOAD_ERR_PARTIAL => "The file was only partially uploaded.",
        UPLOAD_ERR_NO_FILE => "No file was uploaded.",
        UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary upload folder on server.",
        UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
        UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload."
    ];
    $errMsg = $uploadErrors[$file['error']] ?? ("File upload error code: " . $file['error']);
    http_response_code(400);
    echo json_encode(["success" => false, "error" => $errMsg]);
    exit();
}

// Max 30MB limit
if ($file['size'] > 30 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "File size exceeds 30MB limit."]);
    exit();
}

$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid file type ($mimeType). Only JPG, PNG, WEBP, GIF, and SVG allowed."]);
    exit();
}

$extMap = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'image/svg+xml' => 'svg'
];
$ext = $extMap[$mimeType] ?? 'jpg';

$isVercel = getenv('VERCEL') || !empty($_ENV['VERCEL']) || !empty($_SERVER['VERCEL']) || file_exists('/var/task');

$fileName = 'upload_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

if ($isVercel) {
    $fileData = file_get_contents($file['tmp_name']);
    if ($fileData !== false) {
        $dataUri = 'data:' . $mimeType . ';base64,' . base64_encode($fileData);
        echo json_encode([
            "success" => true,
            "message" => "Image uploaded successfully!",
            "imageUrl" => $dataUri,
            "image_url" => $dataUri,
            "url" => $dataUri,
            "file_name" => $fileName
        ]);
        exit();
    }
}

// Local Server Environment File Saving
$normalizedDir = str_replace('\\', '/', __DIR__);
if (basename(dirname($normalizedDir)) === 'public' || strpos($normalizedDir, '/public/api') !== false) {
    $uploadDirPublic = dirname($normalizedDir) . '/uploads';
    $uploadDirSrc = dirname(dirname($normalizedDir)) . '/src/assets/uploads';
} else {
    $uploadDirPublic = $normalizedDir . '/../public/uploads';
    $uploadDirSrc = $normalizedDir . '/../src/assets/uploads';
}

if (!file_exists($uploadDirPublic)) @mkdir($uploadDirPublic, 0777, true);
if (!file_exists($uploadDirSrc)) @mkdir($uploadDirSrc, 0777, true);

$targetPublic = $uploadDirPublic . '/' . $fileName;
$targetSrc = $uploadDirSrc . '/' . $fileName;

if (move_uploaded_file($file['tmp_name'], $targetPublic)) {
    @copy($targetPublic, $targetSrc);

    $fileUrl = '/uploads/' . $fileName;
    echo json_encode([
        "success" => true,
        "message" => "Image uploaded successfully!",
        "imageUrl" => $fileUrl,
        "image_url" => $fileUrl,
        "url" => $fileUrl,
        "file_name" => $fileName
    ]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Failed to save uploaded file on server."]);
}
exit();
