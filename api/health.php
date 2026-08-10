<?php
// api/health.php - Vercel PHP Environment & SQLite Diagnostic Endpoint
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$isVercel = getenv('VERCEL') || !empty($_ENV['VERCEL']) || !empty($_SERVER['VERCEL']) ||
            !empty($_ENV['VERCEL_ENV']) || !empty($_SERVER['VERCEL_ENV']) ||
            !empty($_ENV['NOW_REGION']) || !empty($_SERVER['NOW_REGION']) ||
            strpos(__DIR__, '/var/task') !== false || file_exists('/var/task');

$seedDbCandidates = [
    __DIR__ . '/../data/conspodium.db',
    __DIR__ . '/data/conspodium.db',
    __DIR__ . '/../../data/conspodium.db',
    '/var/task/data/conspodium.db',
    '/var/task/conspodium.db',
    dirname(__DIR__) . '/data/conspodium.db'
];

$seedStatus = [];
foreach ($seedDbCandidates as $cand) {
    $seedStatus[$cand] = [
        "exists" => file_exists($cand),
        "size" => file_exists($cand) ? filesize($cand) : 0
    ];
}

$tmpDb = '/tmp/conspodium.db';
$tmpStatus = [
    "exists" => file_exists($tmpDb),
    "size" => file_exists($tmpDb) ? filesize($tmpDb) : 0,
    "writable" => is_writable('/tmp')
];

$pdoStatus = false;
$pdoError = null;
$tableCounts = [];

if (extension_loaded('pdo_sqlite')) {
    try {
        require_once __DIR__ . '/db.php';
        $pdoStatus = true;
        $tableCounts['posts'] = $pdo->query("SELECT COUNT(*) as count FROM posts")->fetch()['count'] ?? 0;
        $tableCounts['categories'] = $pdo->query("SELECT COUNT(*) as count FROM categories")->fetch()['count'] ?? 0;
        $tableCounts['submissions'] = $pdo->query("SELECT COUNT(*) as count FROM story_submissions")->fetch()['count'] ?? 0;
    } catch (Exception $e) {
        $pdoError = $e->getMessage();
    }
}

echo json_encode([
    "success" => true,
    "php_version" => phpversion(),
    "is_vercel" => $isVercel,
    "pdo_sqlite_loaded" => extension_loaded('pdo_sqlite'),
    "pdo_status" => $pdoStatus,
    "pdo_error" => $pdoError,
    "table_counts" => $tableCounts,
    "tmp_db_status" => $tmpStatus,
    "seed_status" => $seedStatus,
    "dir" => __DIR__
], JSON_PRETTY_PRINT);
