<?php
// api/email_helper.php - Shared SMTP & Templated Notification Dispatcher
require_once __DIR__ . '/db.php';

if (!function_exists('getEmailSetting')) {
    function getEmailSetting($pdo, $key, $default = '') {
        try {
            // 1. Try settings table
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            if ($row && $row['value'] !== null && $row['value'] !== '') {
                return $row['value'];
            }

            // 2. Try site_settings table (with exact key and alias)
            $altKey = str_replace('email_', '', $key);
            $stmt2 = $pdo->prepare("SELECT value FROM site_settings WHERE key = ? OR key = ?");
            $stmt2->execute([$key, $altKey]);
            $row2 = $stmt2->fetch();
            if ($row2 && $row2['value'] !== null && $row2['value'] !== '') {
                return $row2['value'];
            }

            // 3. Try settings table with altKey
            $stmt3 = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
            $stmt3->execute([$altKey]);
            $row3 = $stmt3->fetch();
            if ($row3 && $row3['value'] !== null && $row3['value'] !== '') {
                return $row3['value'];
            }

            return $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('buildMimeEmailMessage')) {
    function buildMimeEmailMessage($fromName, $fromEmail, $toEmail, $subject, $bodyText, $bodyHtml = null, $replyToEmail = null) {
        $fromDomain = 'conspodium.com';
        if (strpos($fromEmail, '@') !== false) {
            $parts = explode('@', $fromEmail);
            if (!empty($parts[1])) $fromDomain = trim($parts[1]);
        }
        
        $msgId = '<' . md5(uniqid(microtime(), true)) . '@' . $fromDomain . '>';
        $dateStr = date("r");
        $boundary = "----=_NextPart_" . md5(uniqid(microtime(), true));
        $effectiveReplyTo = !empty($replyToEmail) ? $replyToEmail : $fromEmail;

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
        <tr>
          <td style="background:#0f172a;padding:24px;text-align:center;">
            <h1 style="color:#00AEFE;margin:0;font-size:22px;letter-spacing:0.5px;font-weight:700;">CONSPODIUM</h1>
            <p style="color:#94a3b8;margin:4px 0 0;font-size:12px;text-transform:uppercase;letter-spacing:1px;">Premium Diaspora Magazine</p>
          </td>
        </tr>
        <tr>
          <td style="padding:32px 28px;font-size:15px;color:#334155;">
            <h2 style="margin:0 0 20px;color:#0f172a;font-size:18px;font-weight:600;">{$subject}</h2>
            <div style="font-size:15px;line-height:1.6;color:#334155;">{$cleanParagraphs}</div>
          </td>
        </tr>
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
        $headers[] = "Reply-To: {$fromName} <{$effectiveReplyTo}>";
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
}

if (!function_exists('sendSmtpEmail')) {
    function sendSmtpEmail($host, $port, $user, $pass, $fromName, $fromEmail, $toEmail, $subject, $bodyText, $bodyHtml = null) {
        $cleanHost = preg_replace('#^(ssl|tls|tcp)://#i', '', trim($host));
        $cleanHost = trim($cleanHost, "/ \t\n\r\0\x0B");
        if (empty($cleanHost)) $cleanHost = 'smtp.hostinger.com';

        $primaryPort = intval($port) ?: 587;
        $portsToTry = ($primaryPort === 465) ? [465, 587] : [$primaryPort, 465];

        // Hostinger SMTP strict sender requirement: Envelope sender (MAIL FROM) must match authenticated SMTP user
        $effectiveFromEmail = (!empty($user) && strpos($user, '@') !== false) ? trim($user) : trim($fromEmail);
        $replyToEmail = (!empty($fromEmail) && $fromEmail !== $effectiveFromEmail) ? trim($fromEmail) : $effectiveFromEmail;

        $lastError = "Unable to connect to SMTP server";

        foreach ($portsToTry as $currentPort) {
            $isSsl = ($currentPort === 465);
            $prefix = $isSsl ? 'ssl://' : 'tcp://';
            $timeout = 10;

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);

            $socket = @stream_socket_client($prefix . $cleanHost . ':' . $currentPort, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
            if (!$socket) {
                $lastError = "Could not connect to SMTP server $cleanHost:$currentPort - $errstr ($errno).";
                continue;
            }

            stream_set_timeout($socket, 10);

            $read = function($sock) {
                $response = "";
                while (!feof($sock) && ($line = fgets($sock, 1024))) {
                    $response .= $line;
                    if (strlen($line) >= 4 && $line[3] === ' ') break;
                    if (strlen(trim($line)) === 3 && ctype_digit(trim($line))) break;
                    $info = stream_get_meta_data($sock);
                    if (!empty($info['timed_out'])) break;
                }
                return $response;
            };

            $send = function($sock, $cmd) use ($read) {
                fputs($sock, $cmd . "\r\n");
                return $read($sock);
            };

            $banner = $read($socket);
            if (substr(trim($banner), 0, 3) !== "220") {
                fclose($socket);
                $lastError = "SMTP banner invalid on port $currentPort: " . trim($banner);
                continue;
            }

            $clientDomain = 'conspodium.com';
            if (!empty($effectiveFromEmail) && strpos($effectiveFromEmail, '@') !== false) {
                $parts = explode('@', $effectiveFromEmail);
                if (!empty($parts[1])) $clientDomain = trim($parts[1]);
            }
            $send($socket, "EHLO " . $clientDomain);

            if ($currentPort === 587) {
                $tlsResp = $send($socket, "STARTTLS");
                if (substr(trim($tlsResp), 0, 3) === "220") {
                    $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                    if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                        $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                    }
                    if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                        $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                    }
                    $crypto = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
                    if ($crypto) {
                        $send($socket, "EHLO " . $clientDomain);
                    }
                }
            }

            if (!empty($user) && !empty($pass)) {
                $authResp = $send($socket, "AUTH LOGIN");
                if (substr(trim($authResp), 0, 3) !== "334") {
                    fclose($socket);
                    $lastError = "SMTP Auth Login rejected on port $currentPort: " . trim($authResp);
                    continue;
                }

                $userResp = $send($socket, base64_encode($user));
                if (substr(trim($userResp), 0, 3) !== "334") {
                    fclose($socket);
                    $lastError = "SMTP Username rejected on port $currentPort: " . trim($userResp);
                    continue;
                }

                $passResp = $send($socket, base64_encode($pass));
                if (substr(trim($passResp), 0, 3) !== "235") {
                    fclose($socket);
                    return ["success" => false, "error" => "SMTP Password authentication failed: " . trim($passResp)];
                }
            }

            $mailFromResp = $send($socket, "MAIL FROM:<" . $effectiveFromEmail . ">");
            if (substr(trim($mailFromResp), 0, 3) !== "250") {
                fclose($socket);
                return ["success" => false, "error" => "MAIL FROM command failed: " . trim($mailFromResp)];
            }

            $rcptResp = $send($socket, "RCPT TO:<" . $toEmail . ">");
            if (substr(trim($rcptResp), 0, 3) !== "250" && substr(trim($rcptResp), 0, 3) !== "251") {
                fclose($socket);
                return ["success" => false, "error" => "RCPT TO command failed for $toEmail: " . trim($rcptResp)];
            }

            $dataResp = $send($socket, "DATA");
            if (substr(trim($dataResp), 0, 3) !== "354") {
                fclose($socket);
                return ["success" => false, "error" => "DATA command initiation failed: " . trim($dataResp)];
            }

            $mimePayload = buildMimeEmailMessage($fromName, $effectiveFromEmail, $toEmail, $subject, $bodyText, $bodyHtml, $replyToEmail);
            $sendResp = $send($socket, $mimePayload . "\r\n.");
            @$send($socket, "QUIT");
            @fclose($socket);

            if (substr(trim($sendResp), 0, 3) === "250") {
                return ["success" => true, "message" => "Email successfully delivered via Hostinger SMTP engine!"];
            } else {
                return ["success" => false, "error" => "Message body transmission failed: " . trim($sendResp)];
            }
        }

        return ["success" => false, "error" => $lastError];
    }
}

if (!function_exists('csp_dispatch_templated_email')) {
    function csp_dispatch_templated_email($pdo, $templateKey, $recipientEmail, $recipientName, $replacements = []) {
        if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE template_key = ? AND is_active = 1");
            $stmt->execute([$templateKey]);
            $tmpl = $stmt->fetch();
            if (!$tmpl) return false;

            $subject = $tmpl['subject'];
            $body = $tmpl['body'];

            $defaultReplacements = [
                '{site_name}' => 'Conspodium',
                '{site_url}' => 'https://conspodium.com',
                '{name}' => ($recipientName ?: 'Reader'),
                '{Name}' => ($recipientName ?: 'Reader'),
                '{sender_name}' => ($recipientName ?: 'Reader'),
                '{sender name}' => ($recipientName ?: 'Reader'),
                '{Sender Name}' => ($recipientName ?: 'Reader'),
                '{Sender_Name}' => ($recipientName ?: 'Reader'),
                '{author_name}' => ($recipientName ?: 'Reader'),
                '{author name}' => ($recipientName ?: 'Reader'),
                '{user_name}' => ($recipientName ?: 'Reader'),
                '{user name}' => ($recipientName ?: 'Reader'),
                '{recipient_name}' => ($recipientName ?: 'Reader'),
                '{recipient name}' => ($recipientName ?: 'Reader'),
                '{email}' => $recipientEmail,
                '{recipient_email}' => $recipientEmail
            ];
            $allReplacements = array_merge($defaultReplacements, $replacements);

            foreach ($allReplacements as $key => $val) {
                $subject = str_replace($key, (string)$val, $subject);
                $body = str_replace($key, (string)$val, $body);
            }
            // Fallback cleanup for double or single braces {{name}} or {sender name} with whitespace/case variations
            $cleanName = ($recipientName ?: 'Reader');
            $subject = preg_replace('/\{+\s*(?:sender[_\s]*name|author[_\s]*name|user[_\s]*name|recipient[_\s]*name|name)\s*\}+/i', $cleanName, $subject);
            $body = preg_replace('/\{+\s*(?:sender[_\s]*name|author[_\s]*name|user[_\s]*name|recipient[_\s]*name|name)\s*\}+/i', $cleanName, $body);

            // Clean up any double-braced leftovers like {{site_name}}
            $subject = str_replace(['{{site_name}}', '{{site_url}}', '{{email}}'], ['Conspodium', 'https://conspodium.com', $recipientEmail], $subject);
            $body = str_replace(['{{site_name}}', '{{site_url}}', '{{email}}'], ['Conspodium', 'https://conspodium.com', $recipientEmail], $body);

            // Wrap in branded responsive HTML email container if not already a full document
            $isFullDoc = (stripos($body, '<!doctype') !== false || stripos($body, '<html') !== false);
            $finalHtml = $body;
            if (!$isFullDoc) {
                $cleanParagraphs = (stripos($body, '<p') !== false || stripos($body, '<br') !== false || stripos($body, '<div') !== false)
                    ? $body
                    : implode('</p><p style="margin:0 0 16px;line-height:1.6;color:#334155;">', array_map('nl2br', explode("\n\n", htmlspecialchars($body))));

                $finalHtml = <<<HTML
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
        <tr>
          <td style="background:#0f172a;padding:24px;text-align:center;">
            <h1 style="color:#00AEFE;margin:0;font-size:22px;letter-spacing:0.5px;font-weight:700;">CONSPODIUM</h1>
            <p style="color:#94a3b8;margin:4px 0 0;font-size:12px;text-transform:uppercase;letter-spacing:1px;">Premium Diaspora Magazine</p>
          </td>
        </tr>
        <tr>
          <td style="padding:32px 28px;font-size:15px;color:#334155;line-height:1.6;">
            {$cleanParagraphs}
          </td>
        </tr>
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

            $provider = getEmailSetting($pdo, 'email_provider', 'smtp');
            $fromName = getEmailSetting($pdo, 'email_from_name', 'Conspodium Editorial');
            $fromAddress = getEmailSetting($pdo, 'email_from_address', 'editor@conspodium.com');
            $smtpHost = getEmailSetting($pdo, 'email_smtp_host', 'smtp.hostinger.com');
            $smtpPort = getEmailSetting($pdo, 'email_smtp_port', '587');
            $smtpUser = getEmailSetting($pdo, 'email_smtp_user', '');
            $smtpPass = getEmailSetting($pdo, 'email_smtp_pass', '');

            $sendSuccess = false;
            if (($provider === 'smtp' || $provider === 'hostinger') && !empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)) {
                $smtpResult = sendSmtpEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName, $fromAddress, $recipientEmail, $subject, strip_tags($body), $finalHtml);
                if ($smtpResult['success']) $sendSuccess = true;
            }

            if (!$sendSuccess) {
                $headers = "From: $fromName <$fromAddress>\r\n" .
                    "Reply-To: $fromAddress\r\n" .
                    "MIME-Version: 1.0\r\n" .
                    "Content-Type: text/html; charset=UTF-8\r\n" .
                    "X-Mailer: PHP/" . phpversion();
                @mail($recipientEmail, $subject, $finalHtml, $headers);
                $sendSuccess = true;
            }

            if ($sendSuccess) {
                $pdo->prepare("INSERT INTO email_logs (recipient_email, recipient_name, subject, body, type, provider, status) VALUES (?, ?, ?, ?, ?, ?, 'sent')")
                    ->execute([$recipientEmail, $recipientName, $subject, $finalHtml, $templateKey, $provider]);
            }
            return $sendSuccess;
        } catch (Exception $e) {
            return false;
        }
    }
}
