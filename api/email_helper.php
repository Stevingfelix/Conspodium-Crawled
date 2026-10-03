<?php
// api/email_helper.php - Shared SMTP & Templated Notification Dispatcher
require_once __DIR__ . '/db.php';

if (!function_exists('getEmailSetting')) {
    function getEmailSetting($pdo, $key, $default = '') {
        try {
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            return $row ? $row['value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('buildMimeEmailMessage')) {
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
}

if (!function_exists('sendSmtpEmail')) {
    function sendSmtpEmail($host, $port, $user, $pass, $fromName, $fromEmail, $toEmail, $subject, $bodyText, $bodyHtml = null) {
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
            return ["success" => false, "error" => "Could not connect to SMTP server $host:$port - $errstr ($errno)."];
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

        $write = function($sock, $cmd) {
            fputs($sock, $cmd . "\r\n");
        };

        $banner = $read($socket);
        if (substr($banner, 0, 3) != "220") {
            fclose($socket);
            return ["success" => false, "error" => "SMTP banner error: " . trim($banner)];
        }

        $clientDomain = 'conspodium.com';
        if (strpos($fromEmail, '@') !== false) {
            $parts = explode('@', $fromEmail);
            if (!empty($parts[1])) $clientDomain = trim($parts[1]);
        }
        $write($socket, "EHLO " . $clientDomain);
        $ehlo = $read($socket);

        if ($port == 587 && strpos($ehlo, 'STARTTLS') !== false) {
            $write($socket, "STARTTLS");
            $tlsResp = $read($socket);
            if (substr($tlsResp, 0, 3) == "220") {
                $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$crypto) {
                    fclose($socket);
                    return ["success" => false, "error" => "Failed to start TLS encryption with SMTP server."];
                }
                $write($socket, "EHLO " . $clientDomain);
                $read($socket);
            }
        }

        if (!empty($user) && !empty($pass)) {
            $write($socket, "AUTH LOGIN");
            $authResp = $read($socket);
            if (substr($authResp, 0, 3) != "334") {
                fclose($socket);
                return ["success" => false, "error" => "SMTP Auth Login rejected: " . trim($authResp)];
            }

            $write($socket, base64_encode($user));
            $userResp = $read($socket);
            if (substr($userResp, 0, 3) != "334") {
                fclose($socket);
                return ["success" => false, "error" => "SMTP Username rejected: " . trim($userResp)];
            }

            $write($socket, base64_encode($pass));
            $passResp = $read($socket);
            if (substr($passResp, 0, 3) != "235") {
                fclose($socket);
                return ["success" => false, "error" => "SMTP Password authentication failed: " . trim($passResp)];
            }
        }

        $write($socket, "MAIL FROM:<" . $fromEmail . ">");
        $mailFromResp = $read($socket);
        if (substr($mailFromResp, 0, 3) != "250") {
            fclose($socket);
            return ["success" => false, "error" => "MAIL FROM command failed: " . trim($mailFromResp)];
        }

        $write($socket, "RCPT TO:<" . $toEmail . ">");
        $rcptResp = $read($socket);
        if (substr($rcptResp, 0, 3) != "250" && substr($rcptResp, 0, 3) != "251") {
            fclose($socket);
            return ["success" => false, "error" => "RCPT TO command failed for $toEmail: " . trim($rcptResp)];
        }

        $write($socket, "DATA");
        $dataResp = $read($socket);
        if (substr($dataResp, 0, 3) != "354") {
            fclose($socket);
            return ["success" => false, "error" => "DATA command initiation failed: " . trim($dataResp)];
        }

        $mimePayload = buildMimeEmailMessage($fromName, $fromEmail, $toEmail, $subject, $bodyText, $bodyHtml);
        $write($socket, $mimePayload . "\r\n.");
        $sendResp = $read($socket);
        if (substr($sendResp, 0, 3) != "250") {
            fclose($socket);
            return ["success" => false, "error" => "Message body transmission failed: " . trim($sendResp)];
        }

        $write($socket, "QUIT");
        fclose($socket);

        return ["success" => true, "message" => "Email successfully delivered via Hostinger SMTP engine!"];
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
                '{site_url}' => 'https://conspodium.com'
            ];
            $allReplacements = array_merge($defaultReplacements, $replacements);

            foreach ($allReplacements as $key => $val) {
                $subject = str_replace($key, (string)$val, $subject);
                $body = str_replace($key, (string)$val, $body);
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
                $smtpResult = sendSmtpEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName, $fromAddress, $recipientEmail, $subject, strip_tags($body), $body);
                if ($smtpResult['success']) $sendSuccess = true;
            }

            if (!$sendSuccess) {
                $headers = "From: $fromName <$fromAddress>\r\n" .
                    "Reply-To: $fromAddress\r\n" .
                    "MIME-Version: 1.0\r\n" .
                    "Content-Type: text/html; charset=UTF-8\r\n" .
                    "X-Mailer: PHP/" . phpversion();
                @mail($recipientEmail, $subject, $body, $headers);
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
}
