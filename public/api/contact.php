<?php
// api/contact.php - Contact Messages API
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

// ── GET ALL CONTACT MESSAGES (ADMIN ONLY) ───────────────────────────────────
if ($method === 'GET') {
    requireAdmin();
    $statusFilter = $_GET['status'] ?? 'all';
    if ($statusFilter && in_array($statusFilter, ['unread', 'read', 'archived'])) {
        $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE status = ? ORDER BY created_at DESC");
        $stmt->execute([$statusFilter]);
    } else {
        $stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
    }
    echo json_encode(["success" => true, "messages" => $stmt->fetchAll()]);
    exit;
}

// ── MARK MESSAGE STATUS (ADMIN ONLY) ───────────────────────────────────────
if ($method === 'POST' && ($action === 'mark_read' || $action === 'mark_unread' || $action === 'archive')) {
    requireAdmin();
    $id = intval($_GET['id'] ?? $input['id'] ?? 0);
    $statusMap = [
        'mark_read' => 'read',
        'mark_unread' => 'unread',
        'archive' => 'archived'
    ];
    $newStatus = $statusMap[$action];
    $stmt = $pdo->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    echo json_encode(["success" => true, "message" => "Message status updated to " . $newStatus]);
    exit;
}

// ── DELETE CONTACT MESSAGE(S) (ADMIN ONLY) ─────────────────────────────────
if ($method === 'DELETE' || ($method === 'POST' && ($action === 'delete' || $action === 'bulk_delete'))) {
    requireAdmin();
    $idsParam = $_GET['ids'] ?? $_GET['id'] ?? $input['ids'] ?? $input['id'] ?? null;

    if ($idsParam) {
        $rawIds = is_array($idsParam) ? $idsParam : explode(',', (string)$idsParam);
        $ids = array_values(array_filter(array_map('intval', $rawIds), function($i) { return $i > 0; }));

        if (!empty($ids)) {
            $inClause = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id IN ($inClause)");
            $stmt->execute($ids);
            $count = $stmt->rowCount();
            echo json_encode(["success" => true, "message" => $count . " message(s) permanently deleted"]);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(["success" => false, "error" => "No valid message IDs provided"]);
    exit;
}

// ── POST NEW CONTACT MESSAGE (PUBLIC SUBMISSION) ───────────────────────────
if ($method === 'POST') {
    $firstName = trim($input['first_name'] ?? $input['names']['first_name'] ?? '');
    $lastName = trim($input['last_name'] ?? $input['names']['last_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $subject = trim($input['subject'] ?? 'General Enquiry');
    $message = trim($input['message'] ?? '');

    if (!$email || !$message) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Email address and message content are required."]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO contact_messages (first_name, last_name, email, subject, message, status)
        VALUES (?, ?, ?, ?, ?, 'unread')
    ");
    $stmt->execute([$firstName, $lastName, $email, $subject, $message]);
    $messageId = $pdo->lastInsertId();

    // Send email alert to admin
    $emailSent = false;
    try {
        $setStmt = $pdo->query("SELECT key, value FROM site_settings WHERE key IN ('admin_email', 'sender_email', 'sender_name')");
        $setRows = $setStmt->fetchAll();
        $setMap = [];
        foreach ($setRows as $r) $setMap[$r['key']] = $r['value'];

        $adminEmail = !empty($setMap['admin_email']) ? $setMap['admin_email'] : 'admin@conspodium.com';
        $senderEmail = !empty($setMap['sender_email']) ? $setMap['sender_email'] : 'noreply@conspodium.com';
        $senderName = !empty($setMap['sender_name']) ? $setMap['sender_name'] : 'Conspodium Alerts';

        $fullName = trim($firstName . ' ' . $lastName);
        if (!$fullName) $fullName = $email;

        $emailSubject = "📩 [Conspodium Contact] " . $subject . " - " . $fullName;
        $headers = implode("\r\n", [
            "MIME-Version: 1.0",
            "Content-type: text/html; charset=utf-8",
            "From: " . $senderName . " <" . $senderEmail . ">",
            "Reply-To: " . $fullName . " <" . $email . ">",
            "X-Mailer: PHP/" . phpversion()
        ]);

        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #061022; color: #ffffff; padding: 24px; border-radius: 12px;'>
            <h2 style='color: #00AEFE; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 12px;'>📬 New Contact Inquiry</h2>
            <p><strong>From:</strong> " . htmlspecialchars($fullName) . " (&lt;" . htmlspecialchars($email) . "&gt;)</p>
            <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
            <hr style='border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 16px 0;' />
            <p><strong>Message:</strong></p>
            <div style='background: rgba(255,255,255,0.05); padding: 16px; border-radius: 8px; font-size: 0.95rem; line-height: 1.6; white-space: pre-wrap;'>" . htmlspecialchars($message) . "</div>
            <br/>
            <p style='font-size: 0.85rem; color: #888;'>Manage in CMS Dashboard: <a href='http://localhost:8080/dashboard/' style='color: #00AEFE;'>View Admin Dashboard</a></p>
        </div>
        ";

        $emailSent = @mail($adminEmail, $emailSubject, $body, $headers);
    } catch (Exception $e) {
        $emailSent = false;
    }

    echo json_encode([
        "success" => true,
        "messageId" => $messageId,
        "email_sent" => $emailSent,
        "message" => "Thank you! Your message has been sent successfully. We will respond within 24-48 hours."
    ]);
    exit;
}
