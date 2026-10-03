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

// ── ENTERPRISE EMAIL BUILDER & MIME ENGINE ─────────────────────────────────
function renderEmailPlainText($content) {
    if (empty($content)) return '';
    $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/<br\s*\/?>/i', "\n", $decoded);
    $text = preg_replace('/<\/(p|div|h[1-6]|tr|li|blockquote)>/i', "\n", $text);
    $text = preg_replace('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', '$2 ($1)', $text);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[\r\n]{3,}/", "\n\n", $text);
    return trim($text);
}

function renderBrandedEmailHtml($subject, $bodyContent, $recipientEmail = '', $options = []) {
    // If incoming body was entity-encoded (e.g. &lt;p&gt;), decode it to real HTML
    if (strpos($bodyContent, '&lt;') !== false && strpos($bodyContent, '&gt;') !== false) {
        $decoded = html_entity_decode($bodyContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/<[a-z][\s\S]*>/i', $decoded)) {
            $bodyContent = $decoded;
        }
    }

    $hasHtml = (preg_match('/<[a-z][\s\S]*>/i', $bodyContent) === 1);
    
    if (!$hasHtml) {
        $paragraphs = explode("\n\n", trim($bodyContent));
        $formattedBody = '';
        foreach ($paragraphs as $p) {
            $pClean = trim($p);
            if (!empty($pClean)) {
                $formattedBody .= '<p style="margin:0 0 16px;line-height:1.7;color:#1e293b;font-size:15px;">' . nl2br(htmlspecialchars($pClean, ENT_QUOTES, 'UTF-8')) . '</p>';
            }
        }
    } else {
        // Sanitize allowed markup to prevent XSS but preserve rich styling
        $allowedTags = '<p><a><strong><b><em><i><u><s><ul><ol><li><blockquote><br><h1><h2><h3><h4><h5><h6><span><div><hr>';
        $formattedBody = strip_tags($bodyContent, $allowedTags);
        $formattedBody = preg_replace('/<p(?![^>]*style=)/i', '<p style="margin:0 0 16px;line-height:1.7;color:#1e293b;font-size:15px;"', $formattedBody);
        $formattedBody = preg_replace('/<a(?![^>]*style=)/i', '<a style="color:#00AEFE;text-decoration:underline;font-weight:600;"', $formattedBody);
        $formattedBody = preg_replace('/<blockquote(?![^>]*style=)/i', '<blockquote style="border-left:3px solid #00AEFE;background:#f8fafc;padding:12px 18px;margin:16px 0;color:#475569;font-style:italic;"', $formattedBody);
        $formattedBody = preg_replace('/<ul(?![^>]*style=)/i', '<ul style="margin:0 0 16px 20px;padding:0;color:#1e293b;line-height:1.7;font-size:15px;"', $formattedBody);
        $formattedBody = preg_replace('/<ol(?![^>]*style=)/i', '<ol style="margin:0 0 16px 20px;padding:0;color:#1e293b;line-height:1.7;font-size:15px;"', $formattedBody);
        $formattedBody = preg_replace('/<li(?![^>]*style=)/i', '<li style="margin-bottom:6px;"', $formattedBody);
    }

    $headerTitle = htmlspecialchars($options['header_title'] ?? 'CONSPODIUM', ENT_QUOTES, 'UTF-8');
    $headerSubtitle = htmlspecialchars($options['header_subtitle'] ?? 'PREMIUM DIASPORA MAGAZINE', ENT_QUOTES, 'UTF-8');
    $ctaText = !empty($options['cta_text']) ? htmlspecialchars(trim($options['cta_text']), ENT_QUOTES, 'UTF-8') : '';
    $ctaUrl = !empty($options['cta_url']) ? htmlspecialchars(trim($options['cta_url']), ENT_QUOTES, 'UTF-8') : '';
    $quotedMessage = !empty($options['quoted_message']) ? trim($options['quoted_message']) : '';

    $ctaHtml = '';
    if (!empty($ctaText) && !empty($ctaUrl)) {
        $ctaHtml = <<<CTA
        <div style="margin:28px 0 16px;text-align:center;">
          <a href="{$ctaUrl}" target="_blank" style="display:inline-block;background:#00AEFE;color:#ffffff;font-weight:700;font-size:15px;padding:12px 28px;border-radius:8px;text-decoration:none;box-shadow:0 4px 12px rgba(0,174,254,0.25);">{$ctaText}</a>
        </div>
CTA;
    }

    $quoteHtml = '';
    if (!empty($quotedMessage)) {
        $cleanQuote = nl2br(htmlspecialchars($quotedMessage, ENT_QUOTES, 'UTF-8'));
        $quoteHtml = <<<QUOTE
        <div style="margin:0 0 22px;background:#f8fafc;border-left:3px solid #00AEFE;padding:12px 16px;border-radius:0 8px 8px 0;font-size:13.5px;line-height:1.6;color:#64748b;">
          <div style="font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:4px;">Original Message Received:</div>
          <div style="font-style:italic;">{$cleanQuote}</div>
        </div>
QUOTE;
    }

    $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>{$safeSubject}</title>
<style type="text/css">
  @media only screen and (max-width: 620px) {
    .csp-email-card { width: 100% !important; border-radius: 0 !important; }
    .csp-email-body { padding: 24px 18px !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;">
<table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color:#f1f5f9;padding:32px 12px;">
  <tr>
    <td align="center">
      <table class="csp-email-card" width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width:600px;background-color:#ffffff;border-radius:14px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,0.06);">
        
        <!-- Header Banner -->
        <tr>
          <td style="background:#0a1120;padding:28px 24px;text-align:center;border-bottom:3px solid #00AEFE;">
            <table width="100%" border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td align="center">
                  <a href="https://conspodium.com" target="_blank" style="text-decoration:none;">
                    <div style="color:#00AEFE;font-size:24px;letter-spacing:1.5px;font-weight:800;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;text-transform:uppercase;">{$headerTitle}</div>
                    <div style="color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:2px;font-weight:600;margin-top:5px;">{$headerSubtitle}</div>
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Main Content Area (Clean Letter format without redundant inner subject) -->
        <tr>
          <td class="csp-email-body" style="padding:36px 32px;background:#ffffff;">
            {$quoteHtml}
            <div style="font-size:15px;line-height:1.7;color:#1e293b;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
              {$formattedBody}
            </div>
            {$ctaHtml}
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f8fafc;padding:24px;text-align:center;font-size:12px;line-height:1.6;color:#64748b;border-top:1px solid #e2e8f0;">
            <p style="margin:0 0 8px;font-weight:500;">Sent via Conspodium Verified Editorial Engine.</p>
            <p style="margin:0;color:#94a3b8;">
              © 2026 Conspodium Media Group. All rights reserved.<br>
              <a href="https://conspodium.com" target="_blank" style="color:#00AEFE;text-decoration:none;font-weight:600;">Visit Conspodium.com</a> • <a href="https://conspodium.com/stories" target="_blank" style="color:#00AEFE;text-decoration:none;font-weight:600;">Browse Diaspora Stories</a>
            </p>
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
        $bodyHtml = renderBrandedEmailHtml($subject, $bodyText, $toEmail);
    }
    $plainText = renderEmailPlainText($bodyText);

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
    $mimeContent .= $plainText . "\r\n\r\n";
    $mimeContent .= "--{$boundary}\r\n";
    $mimeContent .= "Content-Type: text/html; charset=UTF-8\r\n";
    $mimeContent .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $mimeContent .= $bodyHtml . "\r\n\r\n";
    $mimeContent .= "--{$boundary}--\r\n";

    return $mimeContent;
}

// ── NATIVE SMTP SOCKET DRIVER ────────────────────────────────────────────────
function sendSmtpEmail($host, $port, $user, $pass, $fromName, $fromEmail, $toEmail, $subject, $body, $bodyHtml = null) {
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

    $fullMessage = buildMimeEmailMessage($fromName, $fromEmail, $toEmail, $subject, $body, $bodyHtml);
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

    $emailOptions = [
        'header_title' => !empty($input['headerTitle']) ? trim($input['headerTitle']) : 'CONSPODIUM',
        'header_subtitle' => !empty($input['headerSubtitle']) ? trim($input['headerSubtitle']) : 'PREMIUM DIASPORA MAGAZINE',
        'cta_text' => !empty($input['ctaText']) ? trim($input['ctaText']) : '',
        'cta_url' => !empty($input['ctaUrl']) ? trim($input['ctaUrl']) : '',
        'quoted_message' => !empty($input['quotedMessage']) ? trim($input['quotedMessage']) : ''
    ];

    $htmlBody = renderBrandedEmailHtml($subject, $body, $recipientEmail, $emailOptions);
    $plainBody = renderEmailPlainText($body . (!empty($emailOptions['cta_url']) ? ("\n\n" . ($emailOptions['cta_text'] ?: 'Link') . ": " . $emailOptions['cta_url']) : ''));

    // 1. HOSTINGER / CUSTOM SMTP DRIVER
    if ($provider === 'smtp' || $provider === 'hostinger') {
        if (empty($smtpHost) || empty($smtpUser) || empty($smtpPass)) {
            echo json_encode(["success" => false, "error" => "Hostinger SMTP settings incomplete. Please specify Host, Username, and Password."]);
            exit;
        }

        $smtpResult = sendSmtpEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName, $fromAddress, $recipientEmail, $subject, $body, $htmlBody);
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
            'html' => $htmlBody,
            'text' => $plainBody
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
            'htmlContent' => $htmlBody,
            'textContent' => $plainBody
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
            'content' => [
                ['type' => 'text/plain', 'value' => $plainBody],
                ['type' => 'text/html', 'value' => $htmlBody]
            ]
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
                'html' => $htmlBody,
                'text' => $plainBody
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
            'html' => $htmlBody,
            'text' => $plainBody,
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
            "MIME-Version: 1.0\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "X-Mailer: Conspodium Native Mailer";

        @mail($recipientEmail, $subject, $htmlBody, $headers);
        $sendSuccess = true;
        $providerMsg = "Native Server Mailer";
    }

    if ($sendSuccess) {
        if ($contactId > 0) {
            try {
                $pdo->prepare("INSERT INTO contact_replies (contact_id, subject, body, sent_by, provider, status) VALUES (?, ?, ?, ?, ?, 'sent')")
                    ->execute([$contactId, $subject, $body, $fromName, $provider]);
                $pdo->prepare("UPDATE contact_messages SET status = 'replied' WHERE id = ?")->execute([$contactId]);
            } catch (Exception $e) {}
        }
        try {
            $pdo->prepare("INSERT INTO email_logs (recipient_email, recipient_name, subject, body, type, provider, status) VALUES (?, ?, ?, ?, ?, ?, 'sent')")
                ->execute([$recipientEmail, $recipientName, $subject, $body, ($contactId > 0 ? 'contact_reply' : ($action === 'test_connection' ? 'test_ping' : 'custom_notification')), $provider]);
        } catch (Exception $e) {}

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

// ── GET CONTACT REPLIES ──────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'get_replies') {
    requireAdmin();
    $contactId = intval($_GET['contact_id'] ?? 0);
    if ($contactId <= 0) {
        echo json_encode(["success" => true, "replies" => []]);
        exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM contact_replies WHERE contact_id = ? ORDER BY created_at DESC");
    $stmt->execute([$contactId]);
    echo json_encode(["success" => true, "replies" => $stmt->fetchAll()]);
    exit;
}

// ── GET EMAIL TEMPLATES (ADMIN ONLY) ─────────────────────────────────────────
if ($method === 'GET' && $action === 'get_templates') {
    requireAdmin();
    $stmt = $pdo->query("SELECT * FROM email_templates ORDER BY id ASC");
    echo json_encode(["success" => true, "templates" => $stmt->fetchAll()]);
    exit;
}

// ── SAVE EMAIL TEMPLATE (ADMIN ONLY) ─────────────────────────────────────────
if ($method === 'POST' && $action === 'save_template') {
    requireAdmin();
    $templateKey = trim($input['template_key'] ?? '');
    $subject = trim($input['subject'] ?? '');
    $body = trim($input['body'] ?? '');
    $isActive = isset($input['is_active']) ? (intval($input['is_active']) ? 1 : 0) : 1;

    if (empty($templateKey) || empty($subject) || empty($body)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Template key, subject, and body content are required."]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE email_templates SET subject = ?, body = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE template_key = ?");
    $stmt->execute([$subject, $body, $isActive, $templateKey]);

    echo json_encode(["success" => true, "message" => "Email template updated successfully!"]);
    exit;
}

// ── TOGGLE EMAIL TEMPLATE ACTIVE/INACTIVE (ADMIN ONLY) ────────────────────────
if ($method === 'POST' && $action === 'toggle_template') {
    requireAdmin();
    $templateKey = trim($input['template_key'] ?? '');
    $isActive = intval($input['is_active'] ?? 0) ? 1 : 0;

    if (empty($templateKey)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Template key is required."]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE email_templates SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE template_key = ?");
    $stmt->execute([$isActive, $templateKey]);

    echo json_encode(["success" => true, "message" => "Notification trigger " . ($isActive ? "activated" : "deactivated") . " successfully!"]);
    exit;
}

// ── GET EMAIL DISPATCH LOGS (ADMIN ONLY) ─────────────────────────────────────
if ($method === 'GET' && $action === 'get_logs') {
    requireAdmin();
    $stmt = $pdo->query("SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 100");
    echo json_encode(["success" => true, "logs" => $stmt->fetchAll()]);
    exit;
}

// ── NOTIFY STORY SUBMISSION AUTHOR ───────────────────────────────────────────
if ($method === 'POST' && $action === 'notify_author') {
    requireAdmin();
    $submissionId = intval($input['submissionId'] ?? $input['submission_id'] ?? 0);
    $recipientEmail = filter_var(trim($input['recipientEmail'] ?? $input['recipient_email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $recipientName = trim($input['recipientName'] ?? $input['recipient_name'] ?? 'Author');
    $subject = trim($input['subject'] ?? 'Update on your Conspodium Submission');
    $body = trim($input['body'] ?? $input['body_html'] ?? '');
    $type = trim($input['type'] ?? 'story_notification');

    if (!$recipientEmail || empty($body)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Author email and message body are required."]);
        exit;
    }

    $provider = !empty($input['provider']) ? trim($input['provider']) : getSetting($pdo, 'email_provider', 'smtp');
    $apiKey = !empty($input['apiKey']) ? trim($input['apiKey']) : getSetting($pdo, 'email_api_key', '');
    $fromName = !empty($input['fromName']) ? trim($input['fromName']) : (!empty($input['from_name']) ? trim($input['from_name']) : getSetting($pdo, 'email_from_name', 'Conspodium Editorial'));
    $fromAddress = !empty($input['fromAddress']) ? trim($input['fromAddress']) : (!empty($input['from_address']) ? trim($input['from_address']) : (!empty($input['fromEmail']) ? trim($input['fromEmail']) : getSetting($pdo, 'email_from_address', 'editor@conspodium.com')));
    $webhookUrl = !empty($input['webhookUrl']) ? trim($input['webhookUrl']) : getSetting($pdo, 'email_webhook_url', '');
    $smtpHost = !empty($input['smtpHost']) ? trim($input['smtpHost']) : getSetting($pdo, 'email_smtp_host', 'smtp.hostinger.com');
    $smtpPort = !empty($input['smtpPort']) ? trim($input['smtpPort']) : getSetting($pdo, 'email_smtp_port', '587');
    $smtpUser = !empty($input['smtpUser']) ? trim($input['smtpUser']) : getSetting($pdo, 'email_smtp_user', '');
    $smtpPass = !empty($input['smtpPass']) ? trim($input['smtpPass']) : getSetting($pdo, 'email_smtp_pass', '');

    $emailOptions = [
        'header_title' => !empty($input['headerTitle']) ? trim($input['headerTitle']) : 'CONSPODIUM',
        'header_subtitle' => !empty($input['headerSubtitle']) ? trim($input['headerSubtitle']) : 'PREMIUM DIASPORA MAGAZINE',
        'cta_text' => !empty($input['ctaText']) ? trim($input['ctaText']) : '',
        'cta_url' => !empty($input['ctaUrl']) ? trim($input['ctaUrl']) : '',
        'quoted_message' => !empty($input['quotedMessage']) ? trim($input['quotedMessage']) : ''
    ];

    $htmlBody = renderBrandedEmailHtml($subject, $body, $recipientEmail, $emailOptions);
    $plainBody = renderEmailPlainText($body . (!empty($emailOptions['cta_url']) ? ("\n\n" . ($emailOptions['cta_text'] ?: 'Link') . ": " . $emailOptions['cta_url']) : ''));

    $sendSuccess = false;
    $providerMsg = "";

    // 1. HOSTINGER / CUSTOM SMTP DRIVER
    if ($provider === 'smtp' || $provider === 'hostinger') {
        if (!empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)) {
            $smtpResult = sendSmtpEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName, $fromAddress, $recipientEmail, $subject, $body, $htmlBody);
            if ($smtpResult['success']) {
                $sendSuccess = true;
                $providerMsg = "Hostinger SMTP ($smtpHost:$smtpPort)";
            } else {
                echo json_encode(["success" => false, "error" => "Hostinger SMTP dispatch failed: " . $smtpResult['error']]);
                exit;
            }
        }
    // 2. RESEND API DRIVER
    } elseif ($provider === 'resend') {
        if (!empty($apiKey)) {
            $ch = curl_init('https://api.resend.com/emails');
            $payload = json_encode([
                'from' => "$fromName <$fromAddress>",
                'to' => [$recipientEmail],
                'subject' => $subject,
                'html' => $htmlBody,
                'text' => $plainBody
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
            }
        }
    // 3. BREVO API DRIVER
    } elseif ($provider === 'brevo') {
        if (!empty($apiKey)) {
            $ch = curl_init('https://api.brevo.com/v3/smtp/email');
            $payload = json_encode([
                'sender' => ['name' => $fromName, 'email' => $fromAddress],
                'to' => [['email' => $recipientEmail, 'name' => $recipientName]],
                'subject' => $subject,
                'htmlContent' => $htmlBody,
                'textContent' => $plainBody
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
            }
        }
    }

    if (!$sendSuccess) {
        $headers = "From: $fromName <$fromAddress>\r\n" .
            "Reply-To: $fromAddress\r\n" .
            "MIME-Version: 1.0\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "X-Mailer: Conspodium Native Mailer";
        @mail($recipientEmail, $subject, $htmlBody, $headers);
        $sendSuccess = true;
        $providerMsg = "Server Mailer";
    }

    try {
        $pdo->prepare("INSERT INTO email_logs (recipient_email, recipient_name, subject, body, type, provider, status) VALUES (?, ?, ?, ?, ?, ?, 'sent')")
            ->execute([$recipientEmail, $recipientName, $subject, $body, $type, $provider]);
    } catch (Exception $e) {}

    echo json_encode([
        "success" => true,
        "message" => "Email notification successfully dispatched to $recipientEmail via $providerMsg!"
    ]);
    exit;
}

// ── REUSABLE HELPER TO DISPATCH TEMPLATED SYSTEM NOTIFICATIONS ───────────────
function csp_dispatch_templated_email($pdo, $templateKey, $recipientEmail, $recipientName, $replacements = []) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE template_key = ? AND is_active = 1");
        $stmt->execute([$templateKey]);
        $tmpl = $stmt->fetch();
        if (!$tmpl) return false;

        $subject = $tmpl['subject'];
        $body = $tmpl['body'];

        $defaultReplacements = [
            '{site_name}' => 'Conspodium',
            '{site_url}' => 'https://conspodium.com'
        ];
        $allReplacements = array_merge($defaultReplacements, $replacements);

        foreach ($allReplacements as $key => $val) {
            $subject = str_replace($key, $val, $subject);
            $body = str_replace($key, $val, $body);
        }

        $provider = getSetting($pdo, 'email_provider', 'smtp');
        $fromName = getSetting($pdo, 'email_from_name', 'Conspodium Editorial');
        $fromAddress = getSetting($pdo, 'email_from_address', 'editor@conspodium.com');
        $smtpHost = getSetting($pdo, 'email_smtp_host', 'smtp.hostinger.com');
        $smtpPort = getSetting($pdo, 'email_smtp_port', '587');
        $smtpUser = getSetting($pdo, 'email_smtp_user', '');
        $smtpPass = getSetting($pdo, 'email_smtp_pass', '');

        $htmlBody = renderBrandedEmailHtml($subject, $body, $recipientEmail);

        $sendSuccess = false;
        if (($provider === 'smtp' || $provider === 'hostinger') && !empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)) {
            $smtpResult = sendSmtpEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName, $fromAddress, $recipientEmail, $subject, $body, $htmlBody);
            if ($smtpResult['success']) $sendSuccess = true;
        }

        if (!$sendSuccess) {
            $headers = "From: $fromName <$fromAddress>\r\n" .
                "Reply-To: $fromAddress\r\n" .
                "MIME-Version: 1.0\r\n" .
                "Content-Type: text/html; charset=UTF-8\r\n" .
                "X-Mailer: Conspodium Native Mailer";
            @mail($recipientEmail, $subject, $htmlBody, $headers);
            $sendSuccess = true;
        }

        if ($sendSuccess) {
            $pdo->prepare("INSERT INTO email_logs (recipient_email, recipient_name, subject, body, type, provider, status) VALUES (?, ?, ?, ?, ?, ?, 'sent')")
                ->execute([$recipientEmail, $recipientName, $subject, $body, $templateKey, $provider]);
        }
        return $sendSuccess;
    } catch (Exception $e) {
        return false;
    }
}
