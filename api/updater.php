<?php
// api/updater.php - Secure In-App OTA System & Code Updater
// Safeguards MySQL database credentials, SQLite database files, and Media Uploads.

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Set timezone to UTC+1 (West Africa / BST)
date_default_timezone_set('Africa/Lagos');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_guard.php';

// Ensure caller is an authenticated Admin
requireAdmin();
$admin = $_SESSION['admin_user'] ?? ['username' => 'admin'];

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

// Define base directories
$rootDir = dirname(__DIR__);
if (basename($rootDir) === 'public' && file_exists(dirname($rootDir) . '/src')) {
    $rootDir = dirname($rootDir);
}
$dataDir = $rootDir . '/data';
$uploadsDir = $rootDir . '/public/wp-content/uploads';

// Critical protected files & directories that must NEVER be overwritten during update
$protectedFiles = [
    'api/config.php',
    'data/config.php',
    'data/conspodium.db',
    'api/conspodium.db',
    'api/conspodium.sqlite',
    'data/conspodium.sqlite',
    '.env',
    'config.php'
];

$protectedPrefixes = [
    'uploads/',
    'public/uploads/',
    'public/wp-content/uploads/',
    'data/backups/'
];

// Helper: check if a path is protected
function isProtectedPath($relativePath, $protectedFiles, $protectedPrefixes) {
    $normalized = str_replace('\\', '/', trim($relativePath, '/'));
    
    // Check exact matches
    foreach ($protectedFiles as $pf) {
        if (strcasecmp($normalized, $pf) === 0) {
            return true;
        }
    }
    
    // Check directory prefixes
    foreach ($protectedPrefixes as $prefix) {
        if (stripos($normalized, $prefix) === 0) {
            return true;
        }
    }
    
    return false;
}

if ($action === 'status') {
    $versionFile = $rootDir . '/version.json';
    $versionData = [
        'version' => '2.1.0',
        'build_date' => date('Y-m-d H:i:s'),
        'last_updated' => 'Initial installation',
        'environment' => defined('DB_DRIVER') ? DB_DRIVER : 'sqlite'
    ];
    
    if (file_exists($versionFile)) {
        $json = @file_get_contents($versionFile);
        $decoded = @json_decode($json, true);
        if (is_array($decoded)) {
            $versionData = array_merge($versionData, $decoded);
        }
    }
    
    echo json_encode([
        'success' => true,
        'system' => $versionData,
        'safeguards' => [
            'mysql_protected' => true,
            'sqlite_protected' => true,
            'uploads_protected' => true,
            'zip_enabled' => class_exists('ZipArchive')
        ]
    ]);
    exit;
}

if ($action === 'upload_update') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Method not allowed. POST required.']);
        exit;
    }
    
    if (empty($_FILES['update_zip']) || $_FILES['update_zip']['error'] !== UPLOAD_ERR_OK) {
        $errorCode = $_FILES['update_zip']['error'] ?? 'No file';
        echo json_encode(['success' => false, 'error' => "Update zip upload failed (Code: {$errorCode})"]);
        exit;
    }
    
    $tmpFilePath = $_FILES['update_zip']['tmp_name'];
    $originalName = $_FILES['update_zip']['name'];
    
    // Validate that it is a ZIP file
    $fileInfo = pathinfo($originalName);
    if (strtolower($fileInfo['extension'] ?? '') !== 'zip') {
        echo json_encode(['success' => false, 'error' => 'Invalid file format. Please upload a valid .zip update package.']);
        exit;
    }
    
    if (!class_exists('ZipArchive')) {
        echo json_encode(['success' => false, 'error' => 'PHP ZipArchive extension is not enabled on this server. Please enable zip in your PHP configuration.']);
        exit;
    }
    
    $zip = new ZipArchive();
    $res = $zip->open($tmpFilePath);
    if ($res !== true) {
        echo json_encode(['success' => false, 'error' => "Could not open ZIP package. Corrupted file (Zip error code: {$res})."]);
        exit;
    }
    
    $numFiles = $zip->numFiles;
    $updatedCount = 0;
    $skippedCount = 0;
    $updatedList = [];
    $skippedList = [];
    
    // Create backup directory for security
    if (!is_dir($dataDir . '/backups')) {
        @mkdir($dataDir . '/backups', 0755, true);
    }
    
    // Process each file in the zip
    for ($i = 0; $i < $numFiles; $i++) {
        $entryName = $zip->getNameIndex($i);
        $entryNameNormalized = str_replace('\\', '/', $entryName);
        
        // Strip common wrapping root folder if package has e.g. "conspodium-update/..."
        if (preg_match('#^[^/]+/(public/|api/|src/|version\.json|router\.php)#i', $entryNameNormalized, $m)) {
            $firstSlash = strpos($entryNameNormalized, '/');
            $entryNameNormalized = substr($entryNameNormalized, $firstSlash + 1);
        }
        
        // Skip directory entries and hidden/OS metadata
        if (substr($entryNameNormalized, -1) === '/' || 
            strpos($entryNameNormalized, '__MACOSX') !== false || 
            strpos($entryNameNormalized, '.DS_Store') !== false) {
            continue;
        }
        
        // Strict protection check: Never overwrite database credentials or user media
        if (isProtectedPath($entryNameNormalized, $protectedFiles, $protectedPrefixes)) {
            $skippedCount++;
            $skippedList[] = $entryNameNormalized . ' (Protected database/media resource)';
            continue;
        }
        
        // Target destination path
        $destinationPath = $rootDir . '/' . $entryNameNormalized;
        $destDir = dirname($destinationPath);
        
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }
        
        // Extract content
        $stream = $zip->getStream($entryName);
        if ($stream) {
            $contents = stream_get_contents($stream);
            fclose($stream);
            
            if (@file_put_contents($destinationPath, $contents) !== false) {
                $updatedCount++;
                if (count($updatedList) < 50) {
                    $updatedList[] = $entryNameNormalized;
                }
            }
        }
    }
    
    $zip->close();
    
    // Record update version log
    $newVersionData = [
        'version' => '2.1.0',
        'last_updated' => date('Y-m-d H:i:s'),
        'updated_by' => $admin['username'] ?? 'admin',
        'files_updated' => $updatedCount,
        'files_protected' => $skippedCount
    ];
    @file_put_contents($rootDir . '/version.json', json_encode($newVersionData, JSON_PRETTY_PRINT));
    
    echo json_encode([
        'success' => true,
        'message' => "Update successfully installed! {$updatedCount} files updated.",
        'details' => [
            'updated_count' => $updatedCount,
            'protected_count' => $skippedCount,
            'protected_items' => $skippedList,
            'sample_updated' => $updatedList,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => "Unknown action: {$action}"]);
