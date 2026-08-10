<?php
// api/polls.php - Weekly Polls & Voting API
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_guard.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$userIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// ── GET ACTIVE POLL (PUBLIC) ──────────────────────────────────────────────────
if ($method === 'GET' && ($action === 'active' || empty($action))) {
    $stmt = $pdo->query("SELECT * FROM polls WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $poll = $stmt->fetch();

    if (!$poll) {
        $stmtAny = $pdo->query("SELECT * FROM polls ORDER BY created_at DESC LIMIT 1");
        $poll = $stmtAny->fetch();
        if ($poll) {
            $pdo->exec("UPDATE polls SET is_active = 1 WHERE id = " . intval($poll['id']));
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "No active poll found"]);
            exit;
        }
    }

    $options = json_decode($poll['options_json'], true) ?? [];
    $votesStmt = $pdo->prepare("SELECT option_index, COUNT(*) as count FROM poll_votes WHERE poll_id = ? GROUP BY option_index");
    $votesStmt->execute([$poll['id']]);
    $votes = $votesStmt->fetchAll();

    $voteMap = [];
    $totalVotes = 0;
    foreach ($votes as $v) {
        $voteMap[$v['option_index']] = intval($v['count']);
        $totalVotes += intval($v['count']);
    }

    $results = [];
    foreach ($options as $idx => $optText) {
        $count = $voteMap[$idx] ?? 0;
        $pct = $totalVotes > 0 ? round(($count / $totalVotes) * 100) : 0;
        $results[] = ["option" => $optText, "index" => $idx, "count" => $count, "percentage" => $pct];
    }

    $checkIp = $pdo->prepare("SELECT 1 FROM poll_votes WHERE poll_id = ? AND voter_ip = ?");
    $checkIp->execute([$poll['id'], $userIp]);
    $hasVoted = (bool) $checkIp->fetch();

    echo json_encode([
        "success" => true,
        "poll" => [
            "id" => intval($poll['id']),
            "question" => $poll['question'],
            "options" => $results,
            "totalVotes" => $totalVotes,
            "userHasVoted" => $hasVoted
        ]
    ]);
    exit;
}

// ── GET ALL POLLS (ADMIN ONLY) ───────────────────────────────────────────────
if ($method === 'GET' && $action === 'list') {
    requireAdmin();
    $stmt = $pdo->query("SELECT * FROM polls ORDER BY created_at DESC");
    $polls = $stmt->fetchAll();

    $formatted = [];
    foreach ($polls as $p) {
        $options = json_decode($p['options_json'], true) ?? [];
        $votesStmt = $pdo->prepare("SELECT option_index, COUNT(*) as count FROM poll_votes WHERE poll_id = ? GROUP BY option_index");
        $votesStmt->execute([$p['id']]);
        $votes = $votesStmt->fetchAll();

        $voteMap = [];
        $total = 0;
        foreach ($votes as $v) {
            $voteMap[$v['option_index']] = intval($v['count']);
            $total += intval($v['count']);
        }

        $optsFormatted = [];
        foreach ($options as $idx => $optText) {
            $optsFormatted[] = ["option" => $optText, "index" => $idx, "count" => $voteMap[$idx] ?? 0];
        }

        $formatted[] = [
            "id" => intval($p['id']),
            "question" => $p['question'],
            "options" => $optsFormatted,
            "totalVotes" => $total,
            "isActive" => (bool) $p['is_active'],
            "createdAt" => $p['created_at']
        ];
    }

    echo json_encode(["success" => true, "polls" => $formatted]);
    exit;
}

// ── VOTE IN POLL ────────────────────────────────────────────────────────────
if ($method === 'POST' && ($action === 'vote' || isset($input['optionIndex']))) {
    $pollId = intval($input['pollId'] ?? 0);
    $optionIndex = intval($input['optionIndex'] ?? -1);

    if ($pollId <= 0 || $optionIndex < 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "pollId and optionIndex are required"]);
        exit;
    }

    $checkIp = $pdo->prepare("SELECT id FROM poll_votes WHERE poll_id = ? AND voter_ip = ?");
    $checkIp->execute([$pollId, $userIp]);
    if ($checkIp->fetch()) {
        http_response_code(409);
        echo json_encode(["success" => false, "error" => "You have already voted in this poll"]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO poll_votes (poll_id, option_index, voter_ip) VALUES (?, ?, ?)");
    $stmt->execute([$pollId, $optionIndex, $userIp]);

    // Recalculate options
    $pollStmt = $pdo->prepare("SELECT * FROM polls WHERE id = ?");
    $pollStmt->execute([$pollId]);
    $poll = $pollStmt->fetch();
    $options = json_decode($poll['options_json'], true) ?? [];

    $votesStmt = $pdo->prepare("SELECT option_index, COUNT(*) as count FROM poll_votes WHERE poll_id = ? GROUP BY option_index");
    $votesStmt->execute([$pollId]);
    $votes = $votesStmt->fetchAll();

    $voteMap = [];
    $totalVotes = 0;
    foreach ($votes as $v) {
        $voteMap[$v['option_index']] = intval($v['count']);
        $totalVotes += intval($v['count']);
    }

    $results = [];
    foreach ($options as $idx => $optText) {
        $count = $voteMap[$idx] ?? 0;
        $pct = $totalVotes > 0 ? round(($count / $totalVotes) * 100) : 0;
        $results[] = ["option" => $optText, "index" => $idx, "count" => $count, "percentage" => $pct];
    }

    echo json_encode([
        "success" => true,
        "message" => "Vote recorded successfully",
        "poll" => [
            "id" => $pollId,
            "question" => $poll['question'],
            "options" => $results,
            "totalVotes" => $totalVotes,
            "userHasVoted" => true
        ]
    ]);
    exit;
}

// ── CREATE POLL ─────────────────────────────────────────────────────────────
if ($method === 'POST' && ($action === 'create' || !empty($input['question']))) {
    requireAdmin();
    $question = trim($input['question'] ?? '');
    $options = $input['options'] ?? [];
    $isActive = !empty($input['isActive']) ? 1 : 0;

    if (!$question || !is_array($options) || count($options) < 2) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Question and at least 2 options are required"]);
        exit;
    }

    if ($isActive) {
        $pdo->exec("UPDATE polls SET is_active = 0");
    }

    $stmt = $pdo->prepare("INSERT INTO polls (question, options_json, is_active) VALUES (?, ?, ?)");
    $stmt->execute([$question, json_encode($options), $isActive]);

    echo json_encode(["success" => true, "pollId" => $pdo->lastInsertId(), "message" => "Weekly poll created successfully"]);
    exit;
}

// ── ACTIVATE POLL ───────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'activate') {
    requireAdmin();
    $id = intval($_GET['id'] ?? $input['id'] ?? 0);
    $pdo->exec("UPDATE polls SET is_active = 0");
    $pdo->prepare("UPDATE polls SET is_active = 1 WHERE id = ?")->execute([$id]);

    echo json_encode(["success" => true, "message" => "Poll activated"]);
    exit;
}

// ── DEACTIVATE POLL ─────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'deactivate') {
    requireAdmin();
    $id = intval($_GET['id'] ?? $input['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("UPDATE polls SET is_active = 0 WHERE id = ?")->execute([$id]);
    } else {
        $pdo->exec("UPDATE polls SET is_active = 0");
    }

    echo json_encode(["success" => true, "message" => "Poll deactivated and hidden from homepage"]);
    exit;
}

// ── DELETE POLL ─────────────────────────────────────────────────────────────
if ($method === 'DELETE' || ($method === 'POST' && $action === 'delete')) {
    requireAdmin();
    $id = intval($_GET['id'] ?? $input['id'] ?? 0);
    $pdo->prepare("DELETE FROM poll_votes WHERE poll_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM polls WHERE id = ?")->execute([$id]);

    echo json_encode(["success" => true, "message" => "Poll deleted"]);
    exit;
}
