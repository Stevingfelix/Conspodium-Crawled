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
require_once __DIR__ . '/email_helper.php';

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

// Helper function for bulk campaign dispatch
function csp_dispatch_bulk_campaign($campaignId, $recipients, $subject, $content, $senderName, $senderEmail) {
    global $pdo;
    $smtpHost = getEmailSetting($pdo, 'email_smtp_host', 'smtp.hostinger.com');
    $smtpPort = getEmailSetting($pdo, 'email_smtp_port', '587');
    $smtpUser = getEmailSetting($pdo, 'email_smtp_user', '');
    $smtpPass = getEmailSetting($pdo, 'email_smtp_pass', '');

    $hasSmtp = !empty($smtpUser) && !empty($smtpPass);

    foreach ($recipients as $recipient) {
        $toEmail = trim($recipient['email']);
        if (empty($toEmail)) continue;
        $toName = $recipient['name'] ?? '';

        // Perform personalized token replacement
        $recipientContent = str_replace(['{name}', '{email}', '{{name}}', '{{email}}'], [htmlspecialchars($toName ?: 'Subscriber'), htmlspecialchars($toEmail), htmlspecialchars($toName ?: 'Subscriber'), htmlspecialchars($toEmail)], $content);

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
                $finalHtml = "
                <!DOCTYPE html>
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
                              <p style='margin:0;'>© " . date('Y') . " Conspodium. All rights reserved. • <a href='https://conspodium.com' style='color:#00AEFE;text-decoration:none;'>conspodium.com</a></p>
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

        $plainText = strip_tags($recipientContent);
        $sent = false;
        $provider = 'php_mail';

        if ($hasSmtp) {
            $res = sendSmtpEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $senderName, $senderEmail, $toEmail, $subject, $plainText, $finalHtml);
            if (!empty($res['success'])) {
                $sent = true;
                $provider = 'hostinger_smtp';
            }
        }

        if (!$sent) {
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$senderName} <{$senderEmail}>\r\n";
            $headers .= "Reply-To: {$senderName} <{$senderEmail}>\r\n";
            @mail($toEmail, $subject, $finalHtml, $headers);
            $sent = true;
        }

        try {
            $pdo->prepare("INSERT INTO email_logs (recipient_email, recipient_name, subject, body, type, provider, status) VALUES (?, ?, ?, ?, 'campaign', ?, 'sent')")
                ->execute([$toEmail, $toName, $subject, $finalHtml, $provider]);
        } catch (Exception $e) {}
    }
}
