<?php
// api/newsletter.php - Newsletter Subscription & Hostinger Bulk SMTP Email API
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token");
    header("Content-Type: application/json");
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    if (!headers_sent()) http_response_code(200);
    exit();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_helper.php';

$action = $_GET['action'] ?? $_POST['action'] ?? (php_sapi_name() === 'cli' ? ($argv[1] ?? 'cli') : 'subscribe');

if (php_sapi_name() === 'cli' && $action === 'process_scheduled') {
    $processed = csp_process_due_scheduled_campaigns();
    echo "Processed " . count($processed) . " scheduled campaign(s).\n";
    exit(0);
}

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

// PUBLIC UNSUBSCRIBE ENDPOINT (Web Clicks & RFC 8058 One-Click Header Unsubscribes)
if ($action === 'unsubscribe') {
    if (!csp_check_rate_limit('unsubscribe', 15, 60)) {
        http_response_code(429);
        echo json_encode(["success" => false, "error" => "Too many requests. Please wait a moment."]);
        exit();
    }

    $rawInput = json_decode(file_get_contents("php://input"), true) ?: [];
    $email = filter_var(trim($_GET['email'] ?? $_POST['email'] ?? $rawInput['email'] ?? ''), FILTER_VALIDATE_EMAIL);

    $isApiOrPost = ($_SERVER['REQUEST_METHOD'] === 'POST')
        || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        || (isset($_GET['format']) && $_GET['format'] === 'json');

    if (!$email) {
        if ($isApiOrPost) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Valid email parameter is required."]);
            exit();
        }
        header("Content-Type: text/html; charset=UTF-8");
        echo '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Subscription — Conspodium</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { margin:0; padding:0; background:#0b0f19; font-family:"Plus Jakarta Sans", sans-serif; color:#f8fafc; display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .card { background:#111827; border:1px solid #1f2937; border-radius:16px; padding:40px; max-width:440px; width:90%; text-align:center; box-shadow:0 20px 40px rgba(0,0,0,0.5); }
    .logo { color:#00AEFE; font-size:24px; font-weight:800; letter-spacing:1px; margin-bottom:8px; }
    h2 { font-size:20px; font-weight:700; margin:16px 0 8px; color:#ffffff; }
    p { font-size:14px; color:#94a3b8; line-height:1.6; margin:0 0 24px; }
    input[type=email] { width:100%; box-sizing:border-box; padding:12px 16px; background:#1f2937; border:1px solid #374151; border-radius:10px; color:#ffffff; font-size:14px; margin-bottom:16px; outline:none; }
    input[type=email]:focus { border-color:#00AEFE; }
    button { width:100%; padding:12px 20px; background:#ef4444; color:#ffffff; border:none; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; transition:all 0.2s; }
    button:hover { background:#dc2626; }
    .home-link { display:inline-block; margin-top:20px; font-size:13px; color:#64748b; text-decoration:none; }
    .home-link:hover { color:#00AEFE; }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">CONSPODIUM</div>
    <h2>Unsubscribe from Newsletter</h2>
    <p>Please enter your email address to opt out of newsletter campaigns and updates.</p>
    <form method="POST" action="?action=unsubscribe">
      <input type="email" name="email" placeholder="your.email@example.com" required>
      <button type="submit">Confirm Unsubscribe</button>
    </form>
    <a href="/" class="home-link">← Return to Conspodium Homepage</a>
  </div>
</body>
</html>';
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE subscribers SET status = 'unsubscribed', unsubscribed_at = CURRENT_TIMESTAMP WHERE email = ?");
        $stmt->execute([$email]);

        // Record suppression log so audit trail is preserved
        $pdo->prepare("INSERT INTO email_logs (recipient_email, recipient_name, subject, body, type, provider, status, delivery_details) VALUES (?, 'Reader', 'User Unsubscribed', 'User opted out via unsubscribe link or one-click header', 'unsubscribe_event', 'system', 'suppressed', 'Unsubscribed via public endpoint')")
            ->execute([$email]);

        if ($isApiOrPost) {
            echo json_encode(["success" => true, "message" => "You have been successfully unsubscribed from Conspodium newsletter broadcasts."]);
            exit();
        }

        header("Content-Type: text/html; charset=UTF-8");
        echo '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Unsubscribed — Conspodium</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { margin:0; padding:0; background:#0b0f19; font-family:"Plus Jakarta Sans", sans-serif; color:#f8fafc; display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .card { background:#111827; border:1px solid #1f2937; border-radius:16px; padding:40px; max-width:440px; width:90%; text-align:center; box-shadow:0 20px 40px rgba(0,0,0,0.5); }
    .logo { color:#00AEFE; font-size:24px; font-weight:800; letter-spacing:1px; margin-bottom:12px; }
    .icon { width:52px; height:52px; border-radius:50%; background:rgba(34,197,94,0.15); color:#22c55e; display:inline-flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:16px; }
    h2 { font-size:20px; font-weight:700; margin:0 0 10px; color:#ffffff; }
    p { font-size:14px; color:#94a3b8; line-height:1.6; margin:0 0 24px; }
    .resub-btn { display:inline-block; width:100%; box-sizing:border-box; padding:12px 20px; background:#00AEFE; color:#ffffff; border:none; border-radius:10px; font-weight:600; font-size:14px; cursor:pointer; text-decoration:none; transition:all 0.2s; }
    .resub-btn:hover { background:#0090d4; }
    .home-link { display:inline-block; margin-top:20px; font-size:13px; color:#64748b; text-decoration:none; }
    .home-link:hover { color:#00AEFE; }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">CONSPODIUM</div>
    <div class="icon">✓</div>
    <h2>You Have Been Unsubscribed</h2>
    <p>Your email <strong>' . htmlspecialchars($email) . '</strong> will no longer receive newsletter broadcasts from Conspodium Magazine.</p>
    <form method="POST" action="?action=subscribe" style="margin-bottom:12px;">
      <input type="hidden" name="email" value="' . htmlspecialchars($email) . '">
      <input type="hidden" name="list_segment" value="community">
      <button type="submit" class="resub-btn">Unsubscribed by mistake? Resubscribe</button>
    </form>
    <a href="/" class="home-link">← Return to Conspodium Homepage</a>
  </div>
</body>
</html>';
        exit();
    } catch (Exception $e) {
        if ($isApiOrPost) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
            exit();
        }
        echo "Error processing unsubscribe: " . htmlspecialchars($e->getMessage());
        exit();
    }
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

if ($action === 'delete_subscriber') {
    try {
        $rawInput = file_get_contents('php://input');
        $input = !empty($rawInput) ? json_decode($rawInput, true) : [];
        $subId = intval($input['id'] ?? ($_GET['id'] ?? 0));
        $subEmail = trim($input['email'] ?? ($_GET['email'] ?? ''));

        if ($subId > 0) {
            $stmt = $pdo->prepare("DELETE FROM subscribers WHERE id = ?");
            $stmt->execute([$subId]);
        } elseif (!empty($subEmail)) {
            $stmt = $pdo->prepare("DELETE FROM subscribers WHERE email = ?");
            $stmt->execute([$subEmail]);
        } else {
            throw new Exception("Subscriber ID or Email is required for deletion");
        }

        echo json_encode([
            "success" => true,
            "message" => "Subscriber removed successfully from the mailing directory"
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

if ($action === 'process_scheduled') {
    try {
        $processed = csp_process_due_scheduled_campaigns();
        echo json_encode([
            "success" => true,
            "processed_count" => count($processed),
            "campaigns" => $processed
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

if ($action === 'campaigns') {
    try {
        // Automatically check and process any due scheduled broadcasts before returning campaigns
        csp_process_due_scheduled_campaigns();

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
    $scheduledAt = !empty($input['scheduled_at']) ? str_replace('T', ' ', trim($input['scheduled_at'])) : null;

    if (empty($subject) || empty($content)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Subject and Content are required."]);
        exit();
    }

    try {
        // Fetch recipients strictly based on the chosen audience list
        if ($targetList === 'live_discussion') {
            $stmtRec = $pdo->prepare("SELECT user_email AS email, user_name AS name FROM event_reminders WHERE user_email IS NOT NULL AND user_email != '' GROUP BY user_email");
            $stmtRec->execute();
        } elseif ($targetList === 'all') {
            $stmtRec = $pdo->prepare("SELECT email, name FROM subscribers WHERE status = 'active'");
            $stmtRec->execute();
        } else {
            $stmtRec = $pdo->prepare("SELECT email, name FROM subscribers WHERE status = 'active' AND list_segment = ?");
            $stmtRec->execute([$targetList]);
        }
        $recipients = $stmtRec->fetchAll() ?: [];
        $recipientsCount = count($recipients);

        if ($recipientsCount === 0) {
            echo json_encode(["success" => false, "error" => "No active recipients found. Please specify a valid sender email or subscriber list."]);
            exit();
        }

        $status = !empty($scheduledAt) ? 'scheduled' : 'sent';
        $sentAt = empty($scheduledAt) ? date('Y-m-d H:i:s') : null;

        $stmtIns = $pdo->prepare("INSERT INTO email_campaigns (subject, target_list, sender_name, sender_email, content, status, scheduled_at, sent_at, recipients_count, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $stmtIns->execute([$subject, $targetList, $senderName, $senderEmail, $content, $status, $scheduledAt, $sentAt, $recipientsCount]);
        $campaignId = $pdo->lastInsertId();

        // If immediate dispatch, send bulk SMTP email delivery
        if ($status === 'sent' && $recipientsCount > 0) {
            csp_dispatch_bulk_campaign($campaignId, $recipients, $subject, $content, $senderName, $senderEmail);
        }

        $recipientEmailsList = array_map(function($r) { return $r['email']; }, $recipients);

        echo json_encode([
            "success" => true,
            "message" => $status === 'scheduled' ? "Campaign scheduled successfully for $scheduledAt." : "Email campaign dispatched to $recipientsCount recipient(s) including $senderEmail!",
            "campaign_id" => $campaignId,
            "recipients_count" => $recipientsCount,
            "delivered_to" => $recipientEmailsList
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
        $templated = csp_dispatch_templated_email($pdo, 'welcome_subscriber', $toEmail, $toName, ['{name}' => ($toName ?: 'Reader')]);
        if ($templated) return true;

        $senderName = getEmailSetting($pdo, 'email_from_name', 'Conspodium Magazine');
        $senderEmail = getEmailSetting($pdo, 'email_from_address', 'newsletter@conspodium.com');
        $smtpHost = getEmailSetting($pdo, 'email_smtp_host', 'smtp.hostinger.com');
        $smtpPort = getEmailSetting($pdo, 'email_smtp_port', '587');
        $smtpUser = getEmailSetting($pdo, 'email_smtp_user', '');
        $smtpPass = getEmailSetting($pdo, 'email_smtp_pass', '');
        $subject = "Welcome to Conspodium Magazine!";

        $html = "
        <div style='font-family: Arial, sans-serif; background: #0f1012; color: #ffffff; padding: 30px; border-radius: 8px;'>
            <h2 style='color: #cda45e;'>Welcome to Conspodium, " . htmlspecialchars($toName ?: 'Reader') . "!</h2>
            <p>Thank you for subscribing to Conspodium Magazine — your gateway to stories, culture, and innovation across the African diaspora.</p>
            <p>You will receive our weekly curated stories, scholar spotlights, and exclusive event invitations directly in your inbox.</p>
            <br>
            <p style='font-size: 12px; color: #888888;'>© " . date('Y') . " Conspodium Magazine. All rights reserved.</p>
        </div>";

        if (!empty($smtpUser) && !empty($smtpPass)) {
            $smtpRes = sendSmtpEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $senderName, $senderEmail, $toEmail, $subject, strip_tags($html), $html);
            if ($smtpRes['success']) return true;
        }

        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: $senderName <$senderEmail>\r\n";
        @mail($toEmail, $subject, $html, $headers);
    } catch (Exception $e) {}
}

// Safe background dispatcher for due scheduled campaigns
function csp_process_due_scheduled_campaigns() {
    global $pdo;
    try {
        $now = date('Y-m-d H:i:s');
        $stmtDue = $pdo->prepare("SELECT id, subject, target_list, sender_name, sender_email, content FROM email_campaigns WHERE status = 'scheduled' AND (scheduled_at IS NULL OR scheduled_at = '' OR datetime(replace(scheduled_at, 'T', ' ')) <= datetime('now', 'localtime') OR datetime(replace(scheduled_at, 'T', ' ')) <= datetime('now') OR scheduled_at <= ?) ORDER BY id ASC LIMIT 5");
        $stmtDue->execute([$now]);
        $due = $stmtDue->fetchAll();

        $results = [];
        foreach ($due as $camp) {
            // Atomic state lock: scheduled -> processing to prevent concurrent runners from double sending
            $claimStmt = $pdo->prepare("UPDATE email_campaigns SET status = 'processing' WHERE id = ? AND status = 'scheduled'");
            $claimStmt->execute([$camp['id']]);
            if ($claimStmt->rowCount() === 0) {
                continue; // Another process already claimed it
            }

            // Fresh query of active recipients at execution time (ensuring newly unsubscribed users are excluded)
            $targetList = $camp['target_list'];
            if ($targetList === 'live_discussion') {
                $stmtRec = $pdo->prepare("SELECT user_email AS email, user_name AS name FROM event_reminders WHERE user_email IS NOT NULL AND user_email != '' GROUP BY user_email");
                $stmtRec->execute();
            } elseif ($targetList === 'all') {
                $stmtRec = $pdo->prepare("SELECT email, name FROM subscribers WHERE status = 'active'");
                $stmtRec->execute();
            } else {
                $stmtRec = $pdo->prepare("SELECT email, name FROM subscribers WHERE status = 'active' AND list_segment = ?");
                $stmtRec->execute([$targetList]);
            }
            $recipients = $stmtRec->fetchAll() ?: [];

            $dispatchedCount = csp_dispatch_bulk_campaign($camp['id'], $recipients, $camp['subject'], $camp['content'], $camp['sender_name'], $camp['sender_email']);

            $pdo->prepare("UPDATE email_campaigns SET status = 'sent', sent_at = CURRENT_TIMESTAMP, recipients_count = ? WHERE id = ?")
                ->execute([$dispatchedCount, $camp['id']]);

            $results[] = [
                "id" => $camp['id'],
                "subject" => $camp['subject'],
                "dispatched" => $dispatchedCount
            ];
        }
        return $results;
    } catch (Exception $e) {
        return [];
    }
}

// Helper function for bulk campaign dispatch with JIT suppression and idempotency guards
function csp_dispatch_bulk_campaign($campaignId, $recipients, $subject, $content, $senderName, $senderEmail) {
    global $pdo;
    $smtpHost = getEmailSetting($pdo, 'email_smtp_host', 'smtp.hostinger.com');
    $smtpPort = getEmailSetting($pdo, 'email_smtp_port', '587');
    $smtpUser = getEmailSetting($pdo, 'email_smtp_user', '');
    $smtpPass = getEmailSetting($pdo, 'email_smtp_pass', '');

    $hasSmtp = !empty($smtpUser) && !empty($smtpPass);
    $senderDomain = 'conspodium.com';
    if (!empty($senderEmail) && strpos($senderEmail, '@') !== false) {
        $parts = explode('@', $senderEmail);
        if (!empty($parts[1])) $senderDomain = trim($parts[1]);
    }

    $actuallyDispatchedCount = 0;

    foreach ($recipients as $recipient) {
        $toEmail = trim($recipient['email'] ?? '');
        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) continue;
        $toName = trim($recipient['name'] ?? '');

        // 1. JIT SUPPRESSION CHECK (Unsubscribe Race Condition Fix)
        // Immediately query subscriber status right before socket dispatch
        try {
            $subStmt = $pdo->prepare("SELECT status FROM subscribers WHERE email = ? LIMIT 1");
            $subStmt->execute([$toEmail]);
            $currentStatus = $subStmt->fetchColumn();
            if ($currentStatus === 'unsubscribed') {
                // Suppress send immediately: user unsubscribed while queued/scheduled
                $pdo->prepare("INSERT INTO email_logs (campaign_id, recipient_email, recipient_name, subject, body, type, provider, status, delivery_details) VALUES (?, ?, ?, ?, '', 'campaign_suppressed', 'jit_filter', 'suppressed', 'Suppressed: recipient unsubscribed before socket dispatch')")
                    ->execute([$campaignId, $toEmail, $toName, $subject]);
                continue;
            }
        } catch (Exception $e) {}

        // 2. IDEMPOTENCY LOCK (Duplicate Send Prevention)
        // Check if an email for this campaign and recipient has already been sent
        $idempotencyKey = "camp_{$campaignId}_" . md5(strtolower($toEmail));
        try {
            $idempStmt = $pdo->prepare("SELECT id FROM email_logs WHERE idempotency_key = ? AND status = 'sent' LIMIT 1");
            $idempStmt->execute([$idempotencyKey]);
            if ($idempStmt->fetch()) {
                // Already dispatched - skip to prevent duplicate delivery!
                continue;
            }
        } catch (Exception $e) {}

        // 3. DETERMINISTIC MESSAGE-ID
        $customMsgId = "camp_{$campaignId}_" . md5(strtolower($toEmail)) . "@" . $senderDomain;

        // 4. PERSONALIZED TOKEN REPLACEMENT & UNSUBSCRIBE LINK
        $encodedEmail = urlencode($toEmail);
        $unsubUrl = "https://conspodium.com/api/newsletter.php?action=unsubscribe&email={$encodedEmail}";

        $recipientContent = str_replace(
            ['{name}', '{email}', '{{name}}', '{{email}}', '{unsubscribe_url}', '{{unsubscribe_url}}'],
            [htmlspecialchars($toName ?: 'Subscriber'), htmlspecialchars($toEmail), htmlspecialchars($toName ?: 'Subscriber'), htmlspecialchars($toEmail), $unsubUrl, $unsubUrl],
            $content
        );

        // Ensure proper HTML email container without duplicating headers/footers
        $isFullDoc = (stripos($recipientContent, '<!doctype') !== false || stripos($recipientContent, '<html') !== false);
        $hasStudioCanvas = (stripos($recipientContent, '<!-- VISUAL_STUDIO_CANVAS -->') !== false || stripos($recipientContent, 'CONSPODIUM') !== false);
        $finalHtml = $recipientContent;

        if (!$isFullDoc) {
            if ($hasStudioCanvas) {
                $finalHtml = "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'></head>
<body style='margin:0;padding:0;background-color:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;'>
  <table width='100%' border='0' cellspacing='0' cellpadding='0' style='background-color:#f8fafc;padding:30px 15px;'>
    <tr>
      <td align='center'>
        <table width='100%' border='0' cellspacing='0' cellpadding='0' style='max-width:600px;background-color:#ffffff;border-radius:14px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,0.06);'>
          <tr>
            <td>
              {$recipientContent}
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>";
            } else {
                $finalHtml = "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'></head>
<body style='margin:0;padding:0;background-color:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;'>
  <table width='100%' border='0' cellspacing='0' cellpadding='0' style='background-color:#f8fafc;padding:30px 15px;'>
    <tr>
      <td align='center'>
        <table width='100%' border='0' cellspacing='0' cellpadding='0' style='max-width:600px;background-color:#ffffff;border-radius:14px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 6px 18px rgba(0,0,0,0.06);'>
          <tr>
            <td style='background:#0f172a;padding:24px;text-align:center;'>
              <h1 style='color:#00AEFE;margin:0;font-size:22px;letter-spacing:0.5px;font-weight:700;'>CONSPODIUM</h1>
              <p style='color:#94a3b8;margin:4px 0 0;font-size:12px;text-transform:uppercase;letter-spacing:1px;'>Premium Diaspora Magazine</p>
            </td>
          </tr>
          <tr>
            <td style='padding:32px 28px;font-size:15px;color:#334155;line-height:1.6;'>
              {$recipientContent}
            </td>
          </tr>
          <tr>
            <td style='background:#f1f5f9;padding:20px;text-align:center;font-size:12px;color:#64748b;border-top:1px solid #e2e8f0;'>
              <p style='margin:0 0 6px;'>Sent via Conspodium Verified Editorial Engine.</p>
              <p style='margin:0;'>© " . date('Y') . " Conspodium. All rights reserved. • <a href='https://conspodium.com' style='color:#00AEFE;text-decoration:none;'>conspodium.com</a> • <a href='{$unsubUrl}' style='color:#94a3b8;text-decoration:underline;'>Unsubscribe</a></p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>";
            }
        }

        // 4b. GUARANTEE IN-BODY UNSUBSCRIBE LINK ACROSS ALL EMAIL LAYOUTS
        if (stripos($finalHtml, 'action=unsubscribe') === false && stripos($finalHtml, '{unsubscribe_url}') === false) {
            $unsubFooterBar = "<table width='100%' border='0' cellspacing='0' cellpadding='0' style='background:#f8fafc;border-top:1px solid #e2e8f0;margin-top:20px;'>
  <tr>
    <td align='center' style='padding:16px 12px;font-size:12px;line-height:1.6;color:#64748b;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;'>
      <p style='margin:0 0 4px;'>You are receiving this email because you are subscribed to Conspodium Magazine broadcasts.</p>
      <p style='margin:0;'><a href='{$unsubUrl}' style='color:#00AEFE;text-decoration:underline;font-weight:600;'>Click here to unsubscribe instantly</a></p>
    </td>
  </tr>
</table>";
            if (stripos($finalHtml, '</body>') !== false) {
                $finalHtml = str_ireplace('</body>', $unsubFooterBar . '</body>', $finalHtml);
            } else {
                $finalHtml .= $unsubFooterBar;
            }
        }

        $plainText = strip_tags($recipientContent);
        if (stripos($plainText, 'action=unsubscribe') === false) {
            $plainText .= "\n\n---\nTo unsubscribe from Conspodium newsletters, visit: {$unsubUrl}\n";
        }

        $sent = false;
        $dataTransmitted = false;
        $provider = 'php_mail';

        // 5. ATTEMPT BULK SMTP WITH TIMEOUT GUARD
        if ($hasSmtp) {
            $res = sendSmtpEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $senderName, $senderEmail, $toEmail, $subject, $plainText, $finalHtml, $customMsgId);
            if (!empty($res['success'])) {
                $sent = true;
                $provider = 'hostinger_smtp';
            } elseif (!empty($res['data_transmitted'])) {
                // Critical double-send protection:
                // Network timeout occurred AFTER data was transmitted to the remote SMTP server.
                // The remote server accepted the message into spool; do NOT fall back to PHP mail()!
                $dataTransmitted = true;
                $sent = true;
                $provider = 'hostinger_smtp_timeout_queued';
            }
        }

        // 6. SAFE FALLBACK TO NATIVE PHP MAIL ONLY IF SMTP NEVER TRANSMITTED DATA
        if (!$sent && !$dataTransmitted) {
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$senderName} <{$senderEmail}>\r\n";
            $headers .= "Reply-To: {$senderName} <{$senderEmail}>\r\n";
            $headers .= "Message-ID: <{$customMsgId}>\r\n";
            $headers .= "List-Unsubscribe: <{$unsubUrl}>, <mailto:unsubscribe@{$senderDomain}?subject=unsubscribe>\r\n";
            $headers .= "List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";
            @mail($toEmail, $subject, $finalHtml, $headers);
            $sent = true;
            $provider = 'php_mail';
        }

        if ($sent) {
            $actuallyDispatchedCount++;
            try {
                $pdo->prepare("INSERT INTO email_logs (campaign_id, idempotency_key, recipient_email, recipient_name, subject, body, type, provider, status) VALUES (?, ?, ?, ?, ?, ?, 'campaign', ?, 'sent')")
                    ->execute([$campaignId, $idempotencyKey, $toEmail, $toName, $subject, $finalHtml, $provider]);
            } catch (Exception $e) {}
        }
    }

    return $actuallyDispatchedCount;
}
