<?php
// api/email_service.php - Pluggable Email Marketing & Dispatch Service
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Admin-Token, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_guard.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'get_config';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

function getSetting($pdo, $key, $default = '')
{
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setSetting($pdo, $key, $value)
{
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
    $stmt->execute([$key, $value]);
}

// ── ENTERPRISE MIME & SPAM-PREVENTION ENGINE ─────────────────────────────────
function buildMimeEmailMessage($fromName, $fromEmail, $toEmail, $subject, $bodyText, $bodyHtml = null) {
    $fromDomain = 'conspodium.com';
    if (strpos($fromEmail, '@') !== false) {
        $parts = explode('@', $fromEmail);
        if (!empty($parts[1])) $fromDomain = trim($parts[1]);
    }
    
    $msgId = '<' . md5(uniqid(microtime(), true)) . '@' . $fromDomain . '>';
    $dateStr = date("r");
    $boundary = "----=_NextPart_" . md5(uniqid(microtime(), true));

    if (empty($bodyHtml)) {
        $cleanParagraphs = implode('</p><p style="margin:0 0 16px;line-height:1.6;color:#334155;">', array_map('nl2br', explode("\n\n", htmlspecialchars($bodyText))));
        $bodyHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background-color:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color:#f8fafc;padding:30px 15px;">
  <tr>
    <td align="center">
      <table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width:600px;background-color:#ffffff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
        <!-- Header -->
        <tr>
          <td style="background:#0f172a;padding:24px;text-align:center;">
            <h1 style="color:#00AEFE;margin:0;font-size:22px;letter-spacing:0.5px;font-weight:700;">CONSPODIUM</h1>
            <p style="color:#94a3b8;margin:4px 0 0;font-size:12px;text-transform:uppercase;letter-spacing:1px;">Premium Diaspora Magazine</p>
          </td>
        </tr>
        <!-- Content -->
        <tr>
          <td style="padding:32px 28px;font-size:15px;color:#334155;">
            <h2 style="margin:0 0 20px;color:#0f172a;font-size:18px;font-weight:600;">{$subject}</h2>
            <div style="font-size:15px;line-height:1.6;color:#334155;">{$cleanParagraphs}</div>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="background:#f1f5f9;padding:20px;text-align:center;font-size:12px;color:#64748b;border-top:1px solid #e2e8f0;">
            <p style="margin:0 0 6px;">Sent via Conspodium Verified Editorial Engine.</p>
            <p style="margin:0;">© 2026 Conspodium. All rights reserved. • <a href="https://conspodium.com" style="color:#00AEFE;text-decoration:none;">conspodium.com</a></p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;
    }

    $headers = [];
    $headers[] = "From: {$fromName} <{$fromEmail}>";
    $headers[] = "Reply-To: {$fromName} <{$fromEmail}>";
    $headers[] = "Return-Path: <{$fromEmail}>";
    $headers[] = "To: <{$toEmail}>";
    $headers[] = "Subject: {$subject}";
    $headers[] = "Date: {$dateStr}";
    $headers[] = "Message-ID: {$msgId}";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "X-Mailer: Conspodium Enterprise Editorial Mailer v2.5";
    $headers[] = "X-Priority: 3 (Normal)";
    $headers[] = "List-Unsubscribe: <mailto:unsubscribe@{$fromDomain}?subject=unsubscribe>";
    $headers[] = "List-Unsubscribe-Post: List-Unsubscribe=One-Click";
    $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

    $mimeContent  = implode("\r\n", $headers) . "\r\n\r\n";
    $mimeContent .= "--{$boundary}\r\n";
    $mimeContent .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $mimeContent .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $mimeContent .= $bodyText . "\r\n\r\n";
    $mimeContent .= "--{$boundary}\r\n";
    $mimeContent .= "Content-Type: text/html; charset=UTF-8\r\n";
    $mimeContent .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $mimeContent .= $bodyHtml . "\r\n\r\n";
    $mimeContent .= "--{$boundary}--\r\n";

    return $mimeContent;
}

// ── NATIVE SMTP SOCKET DRIVER ────────────────────────────────────────────────
function sendSmtpEmail($host, $port, $user, $pass, $fromName, $fromEmail, $toEmail, $subject, $body) {
    $timeout = 8;
    $ssl = ($port == 465) ? 'ssl://' : '';
    
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $socket = @stream_socket_client($ssl . $host . ':' . intval($port), $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return ["success" => false, "error" => "Could not connect to SMTP server $host:$port - $errstr ($errno). Check Hostinger SMTP server and network connection."];
    }

    stream_set_timeout($socket, 5);

    $read = function($sock) {
        $response = "";
        while ($line = fgets($sock, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) == " ") break;
            $info = stream_get_meta_data($sock);
            if (!empty($info['timed_out'])) break;
        }
        return $response;
    };

    $send = function($sock, $cmd) use ($read) {
        fputs($sock, $cmd . "\r\n");
        return $read($sock);
    };

    $res = $read($socket);
    if (substr($res, 0, 3) != "220") {
        fclose($socket);
        return ["success" => false, "error" => "SMTP banner invalid: $res"];
    }

    $res = $send($socket, "EHLO " . gethostname());

    if ($port == 587) {
        $res = $send($socket, "STARTTLS");
        if (substr($res, 0, 3) == "220") {
            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            @stream_socket_enable_crypto($socket, true, $cryptoMethod);
            $res = $send($socket, "EHLO " . gethostname());
        }
    }

    if (!empty($user) && !empty($pass)) {
        $res = $send($socket, "AUTH LOGIN");
        if (substr($res, 0, 3) != "334") {
            fclose($socket);
            return ["success" => false, "error" => "SMTP Auth Login rejected: $res"];
        }

        $res = $send($socket, base64_encode($user));
        if (substr($res, 0, 3) != "334") {
            fclose($socket);
            return ["success" => false, "error" => "SMTP Username rejected: $res"];
        }

        $res = $send($socket, base64_encode($pass));
        if (substr($res, 0, 3) != "235") {
            fclose($socket);
            return ["success" => false, "error" => "SMTP Authentication failed (Check Username/Password): $res"];
        }
    }

    $res = $send($socket, "MAIL FROM: <$fromEmail>");
    if (substr($res, 0, 3) != "250") {
        fclose($socket);
        return ["success" => false, "error" => "MAIL FROM rejected: $res"];
    }

    $res = $send($socket, "RCPT TO: <$toEmail>");
    if (substr($res, 0, 3) != "250") {
        fclose($socket);
        return ["success" => false, "error" => "RCPT TO rejected: $res"];
    }

    $res = $send($socket, "DATA");
    if (substr($res, 0, 3) != "354") {
        fclose($socket);
        return ["success" => false, "error" => "DATA command rejected: $res"];
    }

    $fullMessage = buildMimeEmailMessage($fromName, $fromEmail, $toEmail, $subject, $body);
    $res = $send($socket, $fullMessage . "\r\n.");
    $send($socket, "QUIT");
    fclose($socket);

    if (substr($res, 0, 3) == "250") {
        return ["success" => true];
    } else {
        return ["success" => false, "error" => "SMTP dispatch failed: $res"];
    }
}

// ── GET CONFIGURATION ────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'get_config') {
    requireAdmin();
    $config = [
        "provider" => getSetting($pdo, 'email_provider', 'smtp'),
        "apiKey" => getSetting($pdo, 'email_api_key', ''),
        "fromName" => getSetting($pdo, 'email_from_name', 'Conspodium Editorial'),
        "fromAddress" => getSetting($pdo, 'email_from_address', 'editor@conspodium.com'),
        "webhookUrl" => getSetting($pdo, 'email_webhook_url', ''),
        "smtpHost" => getSetting($pdo, 'email_smtp_host', 'smtp.hostinger.com'),
        "smtpPort" => getSetting($pdo, 'email_smtp_port', '587'),
        "smtpUser" => getSetting($pdo, 'email_smtp_user', ''),
        "smtpPass" => getSetting($pdo, 'email_smtp_pass', '')
    ];
    echo json_encode(["success" => true, "config" => $config]);
    exit;
}

// ── SAVE CONFIGURATION ───────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'save_config') {
    requireAdmin();
    $provider = trim($input['provider'] ?? 'smtp');
    $apiKey = trim($input['apiKey'] ?? '');
    $fromName = trim($input['fromName'] ?? 'Conspodium Editorial');
    $fromAddress = trim($input['fromAddress'] ?? 'editor@conspodium.com');
    $webhookUrl = trim($input['webhookUrl'] ?? '');
    $smtpHost = trim($input['smtpHost'] ?? 'smtp.hostinger.com');
    $smtpPort = trim($input['smtpPort'] ?? '587');
    $smtpUser = trim($input['smtpUser'] ?? '');
    $smtpPass = trim($input['smtpPass'] ?? '');

    setSetting($pdo, 'email_provider', $provider);
    setSetting($pdo, 'email_api_key', $apiKey);
    setSetting($pdo, 'email_from_name', $fromName);
    setSetting($pdo, 'email_from_address', $fromAddress);
    setSetting($pdo, 'email_webhook_url', $webhookUrl);
    setSetting($pdo, 'email_smtp_host', $smtpHost);
    setSetting($pdo, 'email_smtp_port', $smtpPort);
    setSetting($pdo, 'email_smtp_user', $smtpUser);
    if (!empty($smtpPass)) {
        setSetting($pdo, 'email_smtp_pass', $smtpPass);
    }

    echo json_encode(["success" => true, "message" => "Email provider settings saved successfully!"]);
    exit;
}

// ── SEND EMAIL REPLY ─────────────────────────────────────────────────────────
if ($method === 'POST' && ($action === 'send_reply' || $action === 'test_connection')) {
    requireAdmin();

    $provider = !empty($input['provider']) ? trim($input['provider']) : getSetting($pdo, 'email_provider', 'smtp');
    $apiKey = !empty($input['apiKey']) ? trim($input['apiKey']) : getSetting($pdo, 'email_api_key', '');
    $fromName = !empty($input['fromName']) ? trim($input['fromName']) : getSetting($pdo, 'email_from_name', 'Conspodium Editorial');
    $fromAddress = !empty($input['fromAddress']) ? trim($input['fromAddress']) : getSetting($pdo, 'email_from_address', 'editor@conspodium.com');
    $webhookUrl = !empty($input['webhookUrl']) ? trim($input['webhookUrl']) : getSetting($pdo, 'email_webhook_url', '');
    $smtpHost = !empty($input['smtpHost']) ? trim($input['smtpHost']) : getSetting($pdo, 'email_smtp_host', 'smtp.hostinger.com');
    $smtpPort = !empty($input['smtpPort']) ? trim($input['smtpPort']) : getSetting($pdo, 'email_smtp_port', '587');
    $smtpUser = !empty($input['smtpUser']) ? trim($input['smtpUser']) : getSetting($pdo, 'email_smtp_user', '');
    $smtpPass = !empty($input['smtpPass']) ? trim($input['smtpPass']) : getSetting($pdo, 'email_smtp_pass', '');

    if ($action === 'test_connection') {
        $contactId = 0;
        $reqEmail = trim($input['recipientEmail'] ?? '');
        $recipientEmail = filter_var($reqEmail, FILTER_VALIDATE_EMAIL) ?: $fromAddress;
        $recipientName = 'Conspodium Tester';
        $subject = !empty($input['subject']) ? csp_sanitize(trim($input['subject'])) : ('⚡ Conspodium Email Test Ping (' . strtoupper($provider) . ')');
        $body = !empty($input['body']) ? trim($input['body']) : ("Hello!\n\nThis is a test message from your Conspodium Admin Dashboard testing connection to " . strtoupper($provider) . " email provider.\n\nAll systems operational!");
    } else {
        $contactId = intval($input['contactId'] ?? 0);
        $recipientEmail = filter_var(trim($input['recipientEmail'] ?? ''), FILTER_VALIDATE_EMAIL);
        $recipientName = csp_sanitize(trim($input['recipientName'] ?? 'Valued Contact'));
        $subject = csp_sanitize(trim($input['subject'] ?? 'Response from Conspodium'));
        $body = trim($input['body'] ?? '');

        if (!$recipientEmail || empty($body)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Recipient email and message body are required"]);
            exit;
        }
    }

    $sendSuccess = false;
    $providerMsg = "";

    // 1. HOSTINGER / CUSTOM SMTP DRIVER
    if ($provider === 'smtp' || $provider === 'hostinger') {
        if (empty($smtpHost) || empty($smtpUser) || empty($smtpPass)) {
            echo json_encode(["success" => false, "error" => "Hostinger SMTP settings incomplete. Please specify Host, Username, and Password."]);
            exit;
        }

        $smtpResult = sendSmtpEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName, $fromAddress, $recipientEmail, $subject, $body);
        if ($smtpResult['success']) {
            $sendSuccess = true;
            $providerMsg = "Hostinger SMTP ($smtpHost:$smtpPort)";
        } else {
            echo json_encode(["success" => false, "error" => "Hostinger SMTP dispatch failed: " . $smtpResult['error']]);
            exit;
        }

    // 2. RESEND API DRIVER
    } elseif ($provider === 'resend') {
        if (empty($apiKey)) {
            echo json_encode(["success" => false, "error" => "Resend API Key is missing. Please configure it in Email Settings."]);
            exit;
        }
        $ch = curl_init('https://api.resend.com/emails');
        $payload = json_encode([
            'from' => "$fromName <$fromAddress>",
            'to' => [$recipientEmail],
            'subject' => $subject,
            'text' => $body
        ]);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => $payload
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            $sendSuccess = true;
            $providerMsg = "Resend API";
        } else {
            echo json_encode(["success" => false, "error" => "Resend API call failed (HTTP $code): " . $res]);
            exit;
        }

    // 3. BREVO / SENDINBLUE API DRIVER
    } elseif ($provider === 'brevo') {
        if (empty($apiKey)) {
            echo json_encode(["success" => false, "error" => "Brevo API Key is missing."]);
            exit;
        }
        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        $payload = json_encode([
            'sender' => ['name' => $fromName, 'email' => $fromAddress],
            'to' => [['email' => $recipientEmail, 'name' => $recipientName]],
            'subject' => $subject,
            'textContent' => $body
        ]);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'api-key: ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => $payload
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            $sendSuccess = true;
            $providerMsg = "Brevo API";
        } else {
            echo json_encode(["success" => false, "error" => "Brevo API call failed (HTTP $code): " . $res]);
            exit;
        }

    // 4. SENDGRID API DRIVER
    } elseif ($provider === 'sendgrid') {
        if (empty($apiKey)) {
            echo json_encode(["success" => false, "error" => "SendGrid API Key is missing."]);
            exit;
        }
        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        $payload = json_encode([
            'personalizations' => [['to' => [['email' => $recipientEmail, 'name' => $recipientName]]]],
            'from' => ['email' => $fromAddress, 'name' => $fromName],
            'subject' => $subject,
            'content' => [['type' => 'text/plain', 'value' => $body]]
        ]);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => $payload
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            $sendSuccess = true;
            $providerMsg = "SendGrid API";
        } else {
            echo json_encode(["success" => false, "error" => "SendGrid API call failed (HTTP $code): " . $res]);
            exit;
        }

    // 5. MAILCHIMP / MANDRILL DRIVER
    } elseif ($provider === 'mailchimp') {
        if (empty($apiKey)) {
            echo json_encode(["success" => false, "error" => "Mailchimp Mandrill API Key is missing."]);
            exit;
        }
        $ch = curl_init('https://mandrillapp.com/api/1.0/messages/send.json');
        $payload = json_encode([
            'key' => $apiKey,
            'message' => [
                'from_email' => $fromAddress,
                'from_name' => $fromName,
                'to' => [['email' => $recipientEmail, 'name' => $recipientName, 'type' => 'to']],
                'subject' => $subject,
                'text' => $body
            ]
        ]);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            $sendSuccess = true;
            $providerMsg = "Mailchimp Transactional API";
        } else {
            echo json_encode(["success" => false, "error" => "Mailchimp API call failed (HTTP $code): " . $res]);
            exit;
        }

    // 6. CUSTOM WEBHOOK DRIVER
    } elseif ($provider === 'webhook') {
        if (empty($webhookUrl)) {
            echo json_encode(["success" => false, "error" => "Custom Webhook Endpoint URL is missing."]);
            exit;
        }
        $ch = curl_init($webhookUrl);
        $payload = json_encode([
            'event' => 'email_reply',
            'from_name' => $fromName,
            'from_email' => $fromAddress,
            'to' => $recipientEmail,
            'recipient_name' => $recipientName,
            'subject' => $subject,
            'body' => $body,
            'timestamp' => date('c')
        ]);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            $sendSuccess = true;
            $providerMsg = "Custom Webhook (" . parse_url($webhookUrl, PHP_URL_HOST) . ")";
        } else {
            echo json_encode(["success" => false, "error" => "Webhook endpoint returned HTTP $code: " . $res]);
            exit;
        }

    // 7. NATIVE PHP MAIL DRIVER (DEFAULT)
    } else {
        $headers = "From: $fromName <$fromAddress>\r\n" .
            "Reply-To: $fromAddress\r\n" .
            "X-Mailer: PHP/" . phpversion() . "\r\n" .
            "Content-Type: text/plain; charset=UTF-8";

        @mail($recipientEmail, $subject, $body, $headers);
        $sendSuccess = true;
        $providerMsg = "Native Server Mailer";
    }

    if ($sendSuccess) {
        if ($contactId > 0) {
            $pdo->prepare("UPDATE contact_messages SET status = 'replied' WHERE id = ?")->execute([$contactId]);
        }
        echo json_encode([
            "success" => true,
            "provider" => $provider,
            "message" => ($action === 'test_connection')
                ? "Test email dispatched successfully via $providerMsg!"
                : "Email reply successfully sent to $recipientEmail via $providerMsg!"
        ]);
        exit;
    }
}
