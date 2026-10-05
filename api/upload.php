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

if (empty($_FILES['image']) && empty($_FILES['file']) && empty($_FILES['video'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "No file uploaded. Key 'image', 'video', or 'file' required."]);
    exit();
}

$file = !empty($_FILES['video']) ? $_FILES['video'] : (!empty($_FILES['image']) ? $_FILES['image'] : $_FILES['file']);
$isVideo = !empty($_FILES['video']);

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

// Max 30MB for images, 200MB for videos
$maxSize = $isVideo ? (200 * 1024 * 1024) : (30 * 1024 * 1024);
if ($file['size'] > $maxSize) {
    http_response_code(400);
    $limitLabel = $isVideo ? '200MB' : '30MB';
    echo json_encode(["success" => false, "error" => "File size exceeds $limitLabel limit."]);
    exit();
}

$allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
$allowedVideoTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo', 'video/mpeg'];
$allowedTypes = $isVideo ? $allowedVideoTypes : $allowedImageTypes;

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

// For video, also allow by extension as finfo can misidentify some video containers
if ($isVideo && !in_array($mimeType, $allowedTypes)) {
    $origExt = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $validVideoExts = ['mp4','webm','ogg','mov','avi','mpeg','mpg'];
    if (!in_array($origExt, $validVideoExts)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Invalid video file type ($mimeType). Allowed: MP4, WEBM, OGG, MOV."]);
        exit();
    }
    $mimeType = 'video/mp4'; // normalise
} elseif (!$isVideo && !in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid file type ($mimeType). Only JPG, PNG, WEBP, GIF, and SVG allowed."]);
    exit();
}

$extMap = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'image/svg+xml' => 'svg',
    'video/mp4' => 'mp4',
    'video/webm' => 'webm',
    'video/ogg' => 'ogv',
    'video/quicktime' => 'mov',
    'video/x-msvideo' => 'avi',
    'video/mpeg' => 'mpeg',
];
$ext = $extMap[$mimeType] ?? ($isVideo ? 'mp4' : 'jpg');

$isVercel = getenv('VERCEL') || !empty($_ENV['VERCEL']) || !empty($_SERVER['VERCEL']) || file_exists('/var/task');

$fileName = 'upload_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

if ($isVercel) {
    $fileData = file_get_contents($file['tmp_name']);
    if ($fileData !== false) {
        $dataUri = 'data:' . $mimeType . ';base64,' . base64_encode($fileData);
        echo json_encode([
            "success" => true,
            "message" => ($isVideo ? "Video" : "Image") . " uploaded successfully!",
            "imageUrl" => $dataUri,
            "mediaUrl" => $dataUri,
            "image_url" => $dataUri,
            "url" => $dataUri,
            "file_name" => $fileName,
            "media_type" => $isVideo ? 'video' : 'image'
        ]);
        exit();
    }
}

// Shared Hosting & Local Server Environment File Saving
$normalizedDir = str_replace('\\', '/', __DIR__);
$uploadDirPublic = null;
$possibleUploadDirs = [
    dirname($normalizedDir) . '/uploads',          // standard public_html/uploads or public/uploads
    dirname($normalizedDir) . '/public/uploads',   // root folder with public/ subfolder
    $normalizedDir . '/../uploads',
    $normalizedDir . '/uploads'
];
foreach ($possibleUploadDirs as $cand) {
    if (file_exists($cand) && is_dir($cand)) {
        $uploadDirPublic = realpath($cand);
        break;
    }
}
if (!$uploadDirPublic) {
    $uploadDirPublic = dirname($normalizedDir) . '/uploads';
    @mkdir($uploadDirPublic, 0777, true);
}

// Optional dev sync to src/assets/uploads if it exists
$uploadDirSrc = dirname(dirname($normalizedDir)) . '/src/assets/uploads';
if (!file_exists($uploadDirSrc) && file_exists(dirname($normalizedDir) . '/src/assets/uploads')) {
    $uploadDirSrc = dirname($normalizedDir) . '/src/assets/uploads';
}
if (file_exists(dirname($uploadDirSrc))) {
    @mkdir($uploadDirSrc, 0777, true);
}

$targetPublic = $uploadDirPublic . '/' . $fileName;
$targetSrc = ($uploadDirSrc && file_exists($uploadDirSrc)) ? ($uploadDirSrc . '/' . $fileName) : null;

if (move_uploaded_file($file['tmp_name'], $targetPublic)) {
    if ($targetSrc) {
        @copy($targetPublic, $targetSrc);
    }

    $fileUrl = '/uploads/' . $fileName;
    echo json_encode([
        "success" => true,
        "message" => ($isVideo ? "Video" : "Image") . " uploaded successfully!",
        "imageUrl" => $fileUrl,
        "mediaUrl" => $fileUrl,
        "image_url" => $fileUrl,
        "url" => $fileUrl,
        "file_name" => $fileName,
        "media_type" => $isVideo ? 'video' : 'image'
    ]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Failed to save uploaded file on server."]);
}
exit();
