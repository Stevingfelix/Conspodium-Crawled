<?php
// api/newsletter.php - Newsletter Subscription & Hostinger Bulk SMTP Email API
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'subscribe';

// PUBLIC SUBSCRIBE ENDPOINT
if ($action === 'subscribe') {
    if (!csp_check_rate_limit('subscribe', 5, 60)) {
        http_response_code(429);
        echo json_encode(["success" => false, "error" => "Too many subscription attempts. Please try again in a minute."]);
        exit();
    }

    $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $name = csp_sanitize($input['name'] ?? '');
    $segment = csp_sanitize($input['list_segment'] ?? $input['source'] ?? 'community');

    if (!$email) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Please enter a valid email address."]);
        exit();
    }

    try {
        $stmtCheck = $pdo->prepare("SELECT id, status FROM subscribers WHERE email = ?");
        $stmtCheck->execute([$email]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            if ($existing['status'] === 'unsubscribed') {
                $stmtUp = $pdo->prepare("UPDATE subscribers SET status = 'active', list_segment = ?, subscribed_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmtUp->execute([$segment, $existing['id']]);
                echo json_encode(["success" => true, "already_subscribed" => false, "message" => "Welcome back! Your subscription has been reactivated."]);
            } else {
                echo json_encode(["success" => true, "already_subscribed" => true, "message" => "You've already subscribed to the Conspodium newsletter before."]);
            }
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO subscribers (name, email, list_segment, status, subscribed_at) VALUES (?, ?, ?, 'active', CURRENT_TIMESTAMP)");
            $stmtIns->execute([$name, $email, $segment]);

            // Attempt instant transactional welcome email via Hostinger SMTP or PHP mail
            csp_send_welcome_email($email, $name);

            echo json_encode(["success" => true, "already_subscribed" => false, "message" => "Thank you for subscribing to Conspodium! Check your inbox shortly."]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// ADMIN PROTECTED ENDPOINTS BELOW
require_once __DIR__ . '/auth_guard.php';

if ($action === 'subscribers') {
    try {
        $segment = csp_sanitize($_GET['segment'] ?? 'all');
        $search = csp_sanitize($_GET['search'] ?? '');

        $sql = "SELECT * FROM subscribers WHERE 1=1";
        $params = [];

        if ($segment !== 'all' && !empty($segment)) {
            $sql .= " AND list_segment = ?";
            $params[] = $segment;
        }

        if (!empty($search)) {
            $sql .= " AND (email LIKE ? OR name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $subscribers = $stmt->fetchAll();

        // Get count breakdown by segment
        $stmtCounts = $pdo->query("SELECT list_segment, COUNT(*) as cnt FROM subscribers WHERE status = 'active' GROUP BY list_segment");
        $segmentCounts = [];
        while ($row = $stmtCounts->fetch()) {
            $segmentCounts[$row['list_segment']] = $row['cnt'];
        }

        $totalCount = $pdo->query("SELECT COUNT(*) as count FROM subscribers WHERE status = 'active'")->fetch()['count'];

        echo json_encode([
            "success" => true,
            "data" => $subscribers,
            "total" => $totalCount,
            "segments" => $segmentCounts
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

if ($action === 'campaigns') {
    try {
        $stmt = $pdo->query("SELECT * FROM email_campaigns ORDER BY id DESC");
        $campaigns = $stmt->fetchAll();
        echo json_encode(["success" => true, "data" => $campaigns]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

if ($action === 'send_campaign') {
    $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $subject = csp_sanitize($input['subject'] ?? '');
    $targetList = csp_sanitize($input['target_list'] ?? 'all');
    $senderName = csp_sanitize($input['sender_name'] ?? 'Conspodium Editorial');
    $senderEmail = filter_var(trim($input['sender_email'] ?? 'newsletter@conspodium.com'), FILTER_VALIDATE_EMAIL) ?: 'newsletter@conspodium.com';
    $content = $input['content'] ?? '';
    $scheduledAt = $input['scheduled_at'] ?? null;

    if (empty($subject) || empty($content)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Subject and Content are required."]);
        exit();
    }

    try {
        // Fetch recipients count
        if ($targetList === 'all') {
            $stmtRec = $pdo->prepare("SELECT email, name FROM subscribers WHERE status = 'active'");
            $stmtRec->execute();
        } else {
            $stmtRec = $pdo->prepare("SELECT email, name FROM subscribers WHERE status = 'active' AND list_segment = ?");
            $stmtRec->execute([$targetList]);
        }
        $recipients = $stmtRec->fetchAll();
        $recipientsCount = count($recipients);

        $status = !empty($scheduledAt) ? 'scheduled' : 'sent';
        $sentAt = empty($scheduledAt) ? date('Y-m-d H:i:s') : null;

        $stmtIns = $pdo->prepare("INSERT INTO email_campaigns (subject, target_list, sender_name, sender_email, content, status, scheduled_at, sent_at, recipients_count, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $stmtIns->execute([$subject, $targetList, $senderName, $senderEmail, $content, $status, $scheduledAt, $sentAt, $recipientsCount]);
        $campaignId = $pdo->lastInsertId();

        // If immediate dispatch, simulate/process bulk SMTP email delivery
        if ($status === 'sent' && $recipientsCount > 0) {
            csp_dispatch_bulk_campaign($campaignId, $recipients, $subject, $content, $senderName, $senderEmail);
        }

        echo json_encode([
            "success" => true,
            "message" => $status === 'scheduled' ? "Campaign scheduled successfully for $scheduledAt." : "Bulk email campaign sent to $recipientsCount subscribers!",
            "campaign_id" => $campaignId
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

if ($action === 'duplicate_campaign') {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Valid campaign ID is required."]);
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM email_campaigns WHERE id = ?");
        $stmt->execute([$id]);
        $campaign = $stmt->fetch();

        if (!$campaign) {
            http_response_code(404);
            echo json_encode(["success" => false, "error" => "Campaign not found."]);
            exit();
        }

        echo json_encode([
            "success" => true,
            "data" => [
                "subject" => "Copy of " . $campaign['subject'],
                "target_list" => $campaign['target_list'],
                "sender_name" => $campaign['sender_name'],
                "sender_email" => $campaign['sender_email'],
                "content" => $campaign['content']
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// Helper function for sending welcome email via Hostinger SMTP or PHP mail
function csp_send_welcome_email($toEmail, $toName) {
    global $pdo;
    try {
        $stmtSet = $pdo->query("SELECT key, value FROM site_settings");
        $settings = [];
        while ($row = $stmtSet->fetch()) {
            $settings[$row['key']] = $row['value'];
        }

        $senderName = $settings['sender_name'] ?? 'Conspodium Magazine';
        $senderEmail = $settings['sender_email'] ?? 'newsletter@conspodium.com';
        $subject = "Welcome to Conspodium Magazine!";

        $html = "
        <div style='font-family: Arial, sans-serif; background: #0f1012; color: #ffffff; padding: 30px; border-radius: 8px;'>
            <h2 style='color: #cda45e;'>Welcome to Conspodium, " . htmlspecialchars($toName ?: 'Reader') . "!</h2>
            <p>Thank you for subscribing to Conspodium Magazine — your gateway to stories, culture, and innovation across the African diaspora.</p>
            <p>You will receive our weekly curated stories, scholar spotlights, and exclusive event invitations directly in your inbox.</p>
            <br>
            <p style='font-size: 12px; color: #888888;'>© " . date('Y') . " Conspodium Magazine. All rights reserved.</p>
        </div>";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: $senderName <$senderEmail>" . "\r\n";

        @mail($toEmail, $subject, $html, $headers);
    } catch (Exception $e) {}
}

// Helper function for bulk campaign dispatch
function csp_dispatch_bulk_campaign($campaignId, $recipients, $subject, $content, $senderName, $senderEmail) {
    foreach ($recipients as $recipient) {
        $toEmail = $recipient['email'];
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: $senderName <$senderEmail>" . "\r\n";

        @mail($toEmail, $subject, $content, $headers);
    }
}
