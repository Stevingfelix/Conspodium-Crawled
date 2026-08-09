<?php
// install.php - Conspodium Web Installation Wizard
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Environment checks
$phpVersionOk = version_compare(PHP_VERSION, '8.0.0', '>=');
$pdoSqliteOk  = extension_loaded('pdo_sqlite');
$dataDir      = file_exists(__DIR__ . '/../data') ? __DIR__ . '/../data' : __DIR__ . '/data';
$uploadsDir   = file_exists(__DIR__ . '/../uploads') ? __DIR__ . '/../uploads' : __DIR__ . '/uploads';
$lockFile     = $dataDir . '/install.lock';
$isInstalled  = file_exists($lockFile);

if (!file_exists($dataDir)) @mkdir($dataDir, 0777, true);
if (!file_exists($uploadsDir)) @mkdir($uploadsDir, 0777, true);

$dataWritable    = is_writable($dataDir);
$uploadsWritable = is_writable($uploadsDir);
$allChecksPassed = $phpVersionOk && $pdoSqliteOk && $dataWritable && $uploadsWritable;

$errorMessage = '';
$successMessage = '';

// Handle Installation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isInstalled && $allChecksPassed) {
    $adminName  = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminUser  = trim($_POST['admin_user'] ?? '');
    $adminPass  = trim($_POST['admin_pass'] ?? '');
    $adminConfirm = trim($_POST['admin_confirm'] ?? '');
    $seedData   = isset($_POST['seed_data']);

    if (!$adminName || !$adminEmail || !$adminUser || !$adminPass) {
        $errorMessage = "All fields are required.";
    } elseif ($adminPass !== $adminConfirm) {
        $errorMessage = "Passwords do not match. Please try again.";
    } elseif (strlen($adminPass) < 6) {
        $errorMessage = "Password must be at least 6 characters long.";
    } else {
        try {
            $dbPath = $dataDir . '/conspodium.db';
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Execute table migrations
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS categories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL UNIQUE,
                    slug TEXT NOT NULL UNIQUE,
                    icon TEXT,
                    description TEXT
                );

                CREATE TABLE IF NOT EXISTS posts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    slug TEXT NOT NULL UNIQUE,
                    eyebrow TEXT,
                    excerpt TEXT,
                    content TEXT NOT NULL,
                    category_id INTEGER,
                    author_name TEXT,
                    author_avatar TEXT,
                    featured_image TEXT,
                    reading_time TEXT DEFAULT '5 min read',
                    views INTEGER DEFAULT 0,
                    is_featured INTEGER DEFAULT 0,
                    published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
                );

                CREATE TABLE IF NOT EXISTS transcripts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    post_id INTEGER NOT NULL UNIQUE,
                    speaker_name TEXT,
                    speaker_title TEXT,
                    audio_duration TEXT,
                    transcript_content TEXT NOT NULL,
                    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS polls (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    question TEXT NOT NULL,
                    options_json TEXT NOT NULL,
                    is_active INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS poll_votes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    poll_id INTEGER NOT NULL,
                    option_index INTEGER NOT NULL,
                    voter_ip TEXT NOT NULL,
                    voted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS story_submissions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    author_name TEXT NOT NULL,
                    author_email TEXT NOT NULL,
                    author_bio TEXT,
                    title TEXT NOT NULL,
                    content TEXT NOT NULL,
                    attachment_url TEXT,
                    status TEXT DEFAULT 'pending',
                    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS event_reminders (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    event_name TEXT NOT NULL,
                    event_date TEXT NOT NULL,
                    user_email TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS admins (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL UNIQUE,
                    email TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    name TEXT NOT NULL,
                    role TEXT DEFAULT 'admin',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
            ");

            // Insert custom admin user
            $hashedPassword = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmtAdmin = $pdo->prepare("INSERT INTO admins (username, email, password_hash, name, role) VALUES (?, ?, ?, ?, 'admin')");
            $stmtAdmin->execute([$adminUser, $adminEmail, $hashedPassword, $adminName]);

            // Seed starter content if requested
            if ($seedData) {
                $stmtCat = $pdo->prepare("INSERT INTO categories (name, slug, icon, description) VALUES (?, ?, ?, ?)");
                $stmtCat->execute(['Culture & Heritage', 'culture-heritage', '🏛️', 'Heritage, traditions, and the African spirit abroad.']);
                $stmtCat->execute(['Innovation', 'innovation', '💡', 'Africans in Diaspora influencing economic decisions worldwide.']);
                $stmtCat->execute(['Art & Entertainment', 'art-entertainment', '🎨', 'Creatives are shaping and representing global culture.']);
                $stmtCat->execute(['Community', 'community', '👥', 'Stories connecting Africans in Diaspora across the globe.']);
                $stmtCat->execute(['Success Stories', 'success-stories', '🌟', 'Growth, Success, leadership, and diaspora impact.']);

                $stmtPost = $pdo->prepare("
                    INSERT INTO posts (title, slug, eyebrow, excerpt, content, category_id, author_name, author_avatar, featured_image, reading_time, views, is_featured, published_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmtPost->execute([
                    "Empowering Diaspora Communities Through Innovation & Heritage",
                    "empowering-diaspora-communities-through-innovation-heritage",
                    "Featured Story",
                    "Exploring how pan-African leaders, creators, and innovators are shaping global economic policies and cultural narratives across the diaspora.",
                    "<p>Across Africa and its global diaspora, leaders in technology, finance, and arts are building bridges for sustainable economic growth and cultural exchange.</p><p>Through diaspora summits, bilateral investment funds, and cross-border tech incubator networks, pan-African innovators are turning shared history into actionable global impact.</p>",
                    1,
                    $adminName,
                    strtoupper(substr($adminName, 0, 2)),
                    "./wp-content/uploads/2026/01/African-Diasporans-1536x864-1.jpg",
                    "8 min read",
                    1420,
                    1,
                    date('Y-m-d H:i:s')
                ]);

                $stmtPost->execute([
                    "Africans in Diaspora Influencing Global Economic Decisions",
                    "africans-in-diaspora-influencing-global-economic-decisions",
                    "Economic Horizons",
                    "How African diaspora founders, venture capitalists, and policy advisors are driving bilateral trade and technology investments in Africa.",
                    "<p>Global financial hubs are seeing an uptick in diaspora-led venture funds aimed at fueling sub-Saharan infrastructure, renewable energy, and fintech ecosystems.</p><p>This new generation of investors prioritizes both high growth and measurable social impact across the African continent.</p>",
                    2,
                    "Prof. Amara Diallo",
                    "AD",
                    "./wp-content/uploads/2026/01/WhatsApp-Image-2022-07-03-at-11.51.25-AM-1024x570-1.jpeg",
                    "6 min read",
                    980,
                    0,
                    date('Y-m-d H:i:s')
                ]);

                $stmtPost->execute([
                    "Creatives Are Shaping & Representing Global African Culture",
                    "creatives-shaping-representing-global-african-culture",
                    "Art & Identity",
                    "From visual arts exhibitions in San Francisco to Afrobeats on global stages, African artists are redefining modern creative expression.",
                    "<p>Contemporary African artists and filmmakers are captivating international audiences while staying deeply rooted in authentic storytelling and cultural heritage.</p><p>Major museum retrospectives and independent cinema showcases are ensuring that African stories are told on the world's biggest stages by African voices.</p>",
                    3,
                    "Dr. Ngozi Eze",
                    "NE",
                    "./wp-content/uploads/2026/01/MoADCover-1180x664-1.jpg",
                    "10 min read",
                    1150,
                    0,
                    date('Y-m-d H:i:s')
                ]);

                $options = ["Innovation & Venture Capital", "Cultural Heritage & Arts", "Youth Education & Mentorship", "Diaspora Trade & Economic Policy"];
                $stmtPoll = $pdo->prepare("INSERT INTO polls (question, options_json, is_active) VALUES (?, ?, 1)");
                $stmtPoll->execute(["What area of African diaspora impact should Conspodium feature next?", json_encode($options)]);
                $pollId = $pdo->lastInsertId();

                $stmtVote = $pdo->prepare("INSERT INTO poll_votes (poll_id, option_index, voter_ip) VALUES (?, ?, ?)");
                for ($i = 0; $i < 340; $i++) $stmtVote->execute([$pollId, 0, "seed-$i"]);
                for ($i = 0; $i < 210; $i++) $stmtVote->execute([$pollId, 1, "seed-$i"]);
                for ($i = 0; $i < 285; $i++) $stmtVote->execute([$pollId, 2, "seed-$i"]);
                for ($i = 0; $i < 160; $i++) $stmtVote->execute([$pollId, 3, "seed-$i"]);
            }

            // Create security lock file
            file_put_contents($lockFile, "CONSPODIUM_INSTALLED_ON=" . date('c') . "\nADMIN=" . $adminUser . "\n");
            $isInstalled = true;
            $successMessage = "Installation completed successfully! You can now log into your Admin CMS Portal.";

        } catch (Exception $e) {
            $errorMessage = "Database installation failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Conspodium Web Installer</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #030814;
      --card-bg: #061022;
      --border: rgba(0, 174, 254, 0.25);
      --text: #f0f4f8;
      --text-muted: #8a99ad;
      --blue: #00aefe;
      --pink: #b71f71;
      --green: #10b981;
      --red: #ef4444;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Inter', system-ui, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 30px 20px;
    }
    .installer-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 24px;
      width: 100%;
      max-width: 580px;
      padding: 40px;
      box-shadow: 0 25px 70px rgba(0,0,0,0.7);
    }
    .logo-badge {
      width: 54px;
      height: 54px;
      border-radius: 16px;
      background: linear-gradient(135deg, var(--blue), var(--pink));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      font-weight: 700;
      color: #fff;
      margin: 0 auto 20px;
      box-shadow: 0 10px 30px rgba(0, 174, 254, 0.3);
    }
    h1 { font-family: 'Merriweather', serif; font-size: 1.5rem; text-align: center; margin-bottom: 8px; color: #fff; }
    p.subtitle { font-size: 0.88rem; color: var(--text-muted); text-align: center; margin-bottom: 28px; line-height: 1.5; }
    
    .section-title { font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--blue); margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
    
    .check-list { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 16px; margin-bottom: 28px; }
    .check-item { display: flex; align-items: center; justify-content: space-between; font-size: 0.88rem; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .check-item:last-child { border-bottom: none; }
    .badge-ok { background: rgba(16, 185, 129, 0.15); color: var(--green); padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
    .badge-err { background: rgba(239, 68, 68, 0.15); color: var(--red); padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }

    .form-group { margin-bottom: 18px; }
    .form-label { display: block; font-size: 0.82rem; font-weight: 600; color: rgba(255,255,255,0.85); margin-bottom: 6px; }
    .form-input {
      width: 100%;
      background: rgba(255,255,255,0.05);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 12px 16px;
      color: #fff;
      font-size: 0.9rem;
      transition: border-color 0.2s;
    }
    .form-input:focus { outline: none; border-color: var(--blue); background: rgba(0, 174, 254, 0.05); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

    .btn-submit {
      width: 100%;
      background: linear-gradient(135deg, var(--blue), #0088cc);
      color: #fff;
      border: none;
      border-radius: 14px;
      padding: 14px;
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
      margin-top: 12px;
      box-shadow: 0 10px 25px rgba(0, 174, 254, 0.3);
      transition: opacity 0.2s, transform 0.1s;
    }
    .btn-submit:hover { opacity: 0.95; transform: translateY(-1px); }
    
    .alert-err { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; padding: 12px 16px; border-radius: 12px; font-size: 0.85rem; margin-bottom: 20px; }
    .alert-ok { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #6ee7b7; padding: 16px; border-radius: 14px; font-size: 0.9rem; margin-bottom: 24px; text-align: center; line-height: 1.6; }

    .action-links { display: flex; gap: 14px; margin-top: 20px; }
    .action-btn { flex: 1; text-align: center; text-decoration: none; padding: 12px; border-radius: 12px; font-size: 0.88rem; font-weight: 600; transition: background 0.2s; }
    .btn-primary { background: var(--blue); color: #fff; }
    .btn-secondary { background: rgba(255,255,255,0.08); color: #fff; border: 1px solid var(--border); }
  </style>
</head>
<body>

<div class="installer-card">
  <div class="logo-badge">C</div>
  <h1>Conspodium Installation Wizard</h1>
  <p class="subtitle">Set up your admin credentials and launch your African Diaspora Media Platform in 1 click.</p>

  <?php if ($isInstalled): ?>
    <div class="alert-ok">
      <strong>🎉 Conspodium is already installed & ready!</strong><br>
      For security reasons, the installer has been locked (`data/install.lock`).
    </div>
    <div class="action-links">
      <a href="./" class="action-btn btn-secondary">🌐 View Homepage</a>
      <a href="./dashboard/" class="action-btn btn-primary">🔑 Admin CMS Portal →</a>
    </div>
  <?php else: ?>

    <?php if ($errorMessage): ?>
      <div class="alert-err">⚠️ <?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <!-- Step 1: Environment Checks -->
    <div class="section-title">🔍 Server Environment Checks</div>
    <div class="check-list">
      <div class="check-item">
        <span>PHP Version (>= 8.0) — <strong><?= PHP_VERSION ?></strong></span>
        <span class="<?= $phpVersionOk ? 'badge-ok' : 'badge-err' ?>"><?= $phpVersionOk ? '✓ PASS' : '✗ FAIL' ?></span>
      </div>
      <div class="check-item">
        <span>PDO SQLite Extension</span>
        <span class="<?= $pdoSqliteOk ? 'badge-ok' : 'badge-err' ?>"><?= $pdoSqliteOk ? '✓ INSTALLED' : '✗ MISSING' ?></span>
      </div>
      <div class="check-item">
        <span>Writable Directory: <code>/data</code></span>
        <span class="<?= $dataWritable ? 'badge-ok' : 'badge-err' ?>"><?= $dataWritable ? '✓ WRITABLE' : '✗ NOT WRITABLE' ?></span>
      </div>
      <div class="check-item">
        <span>Writable Directory: <code>/uploads</code></span>
        <span class="<?= $uploadsWritable ? 'badge-ok' : 'badge-err' ?>"><?= $uploadsWritable ? '✓ WRITABLE' : '✗ NOT WRITABLE' ?></span>
      </div>
    </div>

    <?php if ($allChecksPassed): ?>
      <!-- Step 2: Admin Setup Form -->
      <div class="section-title">👤 Create Admin Account</div>
      <form method="POST" action="install.php">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Admin Full Name *</label>
            <input type="text" name="admin_name" class="form-input" placeholder="e.g. Steving Felix" required value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Admin Email *</label>
            <input type="email" name="admin_email" class="form-input" placeholder="admin@conspodium.com" required value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Admin Username *</label>
          <input type="text" name="admin_user" class="form-input" placeholder="admin" required value="<?= htmlspecialchars($_POST['admin_user'] ?? '') ?>">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password *</label>
            <input type="password" name="admin_pass" class="form-input" placeholder="••••••••" required>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password *</label>
            <input type="password" name="admin_confirm" class="form-input" placeholder="••••••••" required>
          </div>
        </div>

        <div class="form-group" style="margin-top: 6px;">
          <label style="display: flex; align-items: center; gap: 8px; font-size: 0.84rem; color: var(--text-muted); cursor: pointer;">
            <input type="checkbox" name="seed_data" value="1" checked style="accent-color: var(--blue);">
            Seed starter Conspodium categories, articles, and weekly poll
          </label>
        </div>

        <button type="submit" class="btn-submit">⚡ Install Conspodium Now →</button>
      </form>
    <?php else: ?>
      <div class="alert-err">
        Please resolve the failed server requirements above before proceeding with installation.
      </div>
    <?php endif; ?>

  <?php endif; ?>

</div>

</body>
</html>
