<?php
/**
 * Conspodium 1-Click Server-Side Unpacker & Deployer
 * Solves Hostinger File Manager timeout/extraction truncation issues.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

$zipFile = __DIR__ . '/conspodium-hostinger-public_html.zip';
if (!file_exists($zipFile)) {
    // Fallback: look for any .zip in the directory
    $zips = glob(__DIR__ . '/*.zip');
    if (!empty($zips)) {
        $zipFile = $zips[0];
    }
}

$action = $_GET['action'] ?? '';
$message = '';
$status = 'idle';
$extractedCount = 0;

if ($action === 'cleanup') {
    @unlink(__FILE__);
    if (file_exists($zipFile)) @unlink($zipFile);
    header('Location: /');
    exit();
}

if ($action === 'extract' || isset($_POST['start_extract'])) {
    if (!file_exists($zipFile)) {
        $status = 'error';
        $message = "Zip archive not found in public_html! Please ensure 'conspodium-hostinger-public_html.zip' is uploaded here.";
    } elseif (!class_exists('ZipArchive')) {
        $status = 'error';
        $message = "PHP ZipArchive extension is not enabled on this server.";
    } else {
        $zip = new ZipArchive();
        $res = $zip->open($zipFile);
        if ($res === TRUE) {
            $extractedCount = $zip->numFiles;
            $zip->extractTo(__DIR__);
            $zip->close();
            $status = 'success';
            $message = "Successfully extracted {$extractedCount} files & folders directly into public_html in under 2 seconds!";
        } else {
            $status = 'error';
            $message = "Failed to open zip archive. Error code: " . $res;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Conspodium 1-Click Server Unpacker</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: #030814;
      color: #f1f5f9;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .card {
      background: #091327;
      border: 1px solid rgba(0, 174, 254, 0.3);
      border-radius: 16px;
      max-width: 580px;
      width: 100%;
      padding: 32px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.5), 0 0 30px rgba(0,174,254,0.15);
      text-align: center;
    }
    .logo {
      font-size: 1.6rem;
      font-weight: 800;
      color: #fff;
      margin-bottom: 6px;
      letter-spacing: -0.5px;
    }
    .logo span { color: #00AEFE; }
    .badge {
      display: inline-block;
      background: rgba(0,174,254,0.15);
      color: #00AEFE;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.78rem;
      font-weight: 700;
      margin-bottom: 20px;
    }
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 14px 28px;
      border-radius: 50px;
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s ease;
      border: none;
      width: 100%;
      margin-top: 10px;
    }
    .btn-primary {
      background: #00AEFE;
      color: #fff;
      box-shadow: 0 4px 20px rgba(0,174,254,0.4);
    }
    .btn-primary:hover { background: #0284c7; }
    .btn-secondary {
      background: #1e293b;
      color: #cbd5e1;
      border: 1px solid #334155;
    }
    .btn-secondary:hover { background: #334155; color: #fff; }
    .btn-danger {
      background: #ef4444;
      color: #fff;
    }
    .alert-success {
      background: #064e3b;
      border: 1px solid #059669;
      color: #a7f3d0;
      padding: 16px;
      border-radius: 12px;
      margin: 20px 0;
      font-size: 0.9rem;
      text-align: left;
    }
    .alert-error {
      background: #7f1d1d;
      border: 1px solid #dc2626;
      color: #fecaca;
      padding: 16px;
      border-radius: 12px;
      margin: 20px 0;
      font-size: 0.9rem;
      text-align: left;
    }
    .checklist {
      text-align: left;
      margin: 16px 0;
      font-size: 0.85rem;
      color: #94a3b8;
    }
    .checklist div {
      padding: 6px 0;
      border-bottom: 1px solid rgba(255,255,255,0.06);
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .check { color: #10b981; font-weight: bold; }
    .cross { color: #ef4444; font-weight: bold; }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">CONSPODIUM <span>DEPLOYER</span></div>
    <div class="badge">Native PHP Server-Side Unpacker</div>

    <p style="color:#94a3b8;font-size:0.9rem;line-height:1.5;margin-bottom:20px;">
      Bypasses Hostinger File Manager timeouts by unzipping directly through the server's native PHP engine at full speed.
    </p>

    <?php if ($status === 'success'): ?>
      <div class="alert-success">
        <strong>🎉 Extraction Complete!</strong><br>
        <?= htmlspecialchars($message) ?>
      </div>

      <div class="checklist">
        <div><span class="<?= file_exists(__DIR__ . '/index.html') ? 'check' : 'cross' ?>"><?= file_exists(__DIR__ . '/index.html') ? '✓' : '✗' ?></span> Homepage (index.html)</div>
        <div><span class="<?= file_exists(__DIR__ . '/forum/index.html') ? 'check' : 'cross' ?>"><?= file_exists(__DIR__ . '/forum/index.html') ? '✓' : '✗' ?></span> Forum Page (forum/index.html)</div>
        <div><span class="<?= file_exists(__DIR__ . '/dashboard/index.html') ? 'check' : 'cross' ?>"><?= file_exists(__DIR__ . '/dashboard/index.html') ? '✓' : '✗' ?></span> Admin Dashboard (dashboard/index.html)</div>
        <div><span class="<?= file_exists(__DIR__ . '/stories/index.html') ? 'check' : 'cross' ?>"><?= file_exists(__DIR__ . '/stories/index.html') ? '✓' : '✗' ?></span> Stories Page (stories/index.html)</div>
        <div><span class="<?= file_exists(__DIR__ . '/api') ? 'check' : 'cross' ?>"><?= file_exists(__DIR__ . '/api') ? '✓' : '✗' ?></span> Backend API Engine (api/)</div>
        <div><span class="<?= file_exists(__DIR__ . '/.htaccess') ? 'check' : 'cross' ?>"><?= file_exists(__DIR__ . '/.htaccess') ? '✓' : '✗' ?></span> Apache Routing Rules (.htaccess)</div>
      </div>

      <a href="/" class="btn btn-primary" target="_blank">🌐 Open Live Website (conspodium.com)</a>
      <a href="/forum/" class="btn btn-secondary" target="_blank">💬 Open Forum Page (/forum/)</a>
      <a href="/dashboard/" class="btn btn-secondary" target="_blank">📊 Open Admin Dashboard (/dashboard/)</a>
      <a href="?action=cleanup" class="btn btn-danger" onclick="return confirm('This will delete unpacker.php and the zip file to keep your server clean and secure. Proceed?');">🗑️ Clean Up & Delete Unpacker (Recommended)</a>

    <?php elseif ($status === 'error'): ?>
      <div class="alert-error">
        <strong>⚠️ Extraction Failed</strong><br>
        <?= htmlspecialchars($message) ?>
      </div>
      <form method="POST">
        <button type="submit" name="start_extract" class="btn btn-primary">🔄 Retry Unpacking</button>
      </form>
    <?php else: ?>
      <div style="background:#0f172a;border-radius:10px;padding:14px;margin-bottom:20px;text-align:left;font-size:0.85rem;color:#cbd5e1;">
        <div><strong>Archive Detected:</strong> <?= file_exists($zipFile) ? basename($zipFile) : '<span style="color:#ef4444;">No zip file found</span>' ?></div>
        <div><strong>Archive Size:</strong> <?= file_exists($zipFile) ? round(filesize($zipFile) / (1024*1024), 2) . ' MB' : '0 MB' ?></div>
        <div><strong>Target Destination:</strong> <?= htmlspecialchars(__DIR__) ?> (public_html)</div>
      </div>

      <form method="POST">
        <button type="submit" name="start_extract" class="btn btn-primary" <?= !file_exists($zipFile) ? 'disabled style="opacity:0.5;"' : '' ?>>
          ⚡ Click to Unpack All Files (2 Seconds)
        </button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
