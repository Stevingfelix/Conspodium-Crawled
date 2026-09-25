<?php
// install.php - Conspodium Web Installation Wizard (MySQL & SQLite Support)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Environment checks
$phpVersionOk = version_compare(PHP_VERSION, '8.0.0', '>=');
$pdoSqliteOk  = extension_loaded('pdo_sqlite');
$pdoMysqlOk   = extension_loaded('pdo_mysql');
$dataDir      = file_exists(__DIR__ . '/../data') ? __DIR__ . '/../data' : __DIR__ . '/data';
$uploadsDir   = file_exists(__DIR__ . '/../uploads') ? __DIR__ . '/../uploads' : __DIR__ . '/uploads';
$lockFile     = $dataDir . '/install.lock';
$forceReinstall = isset($_GET['reinstall']) || isset($_GET['force']);

if ($forceReinstall && file_exists($lockFile)) {
    @unlink($lockFile);
}
$isInstalled  = file_exists($lockFile);

if (!file_exists($dataDir)) @mkdir($dataDir, 0777, true);
if (!file_exists($uploadsDir)) @mkdir($uploadsDir, 0777, true);

$dataWritable    = is_writable($dataDir);
$uploadsWritable = is_writable($uploadsDir);
$allChecksPassed = $phpVersionOk && ($pdoSqliteOk || $pdoMysqlOk) && $dataWritable && $uploadsWritable;

$errorMessage = '';
$successMessage = '';

// Handle Installation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isInstalled && $allChecksPassed) {
    $dbType       = trim($_POST['db_type'] ?? 'sqlite');
    $dbHost       = trim($_POST['db_host'] ?? 'localhost');
    $dbPort       = trim($_POST['db_port'] ?? '3306');
    $dbName       = trim($_POST['db_name'] ?? '');
    $dbUser       = trim($_POST['db_user'] ?? '');
    $dbPass       = $_POST['db_pass'] ?? '';

    $adminName    = trim($_POST['admin_name'] ?? '');
    $adminEmail   = trim($_POST['admin_email'] ?? '');
    $adminUser    = trim($_POST['admin_user'] ?? '');
    $adminPass    = trim($_POST['admin_pass'] ?? '');
    $adminConfirm = trim($_POST['admin_confirm'] ?? '');
    $seedData     = isset($_POST['seed_data']);

    if (!$adminName || !$adminEmail || !$adminUser || !$adminPass) {
        $errorMessage = "All admin credential fields are required.";
    } elseif ($adminPass !== $adminConfirm) {
        $errorMessage = "Admin passwords do not match. Please try again.";
    } elseif (strlen($adminPass) < 6) {
        $errorMessage = "Admin password must be at least 6 characters long.";
    } elseif ($dbType === 'mysql' && (!$dbName || !$dbUser)) {
        $errorMessage = "MySQL Database Name and Database Username are required for Shared Hosting setup.";
    } else {
        try {
            if ($dbType === 'mysql') {
                if (!$pdoMysqlOk) {
                    throw new Exception("PDO MySQL extension is not enabled on this PHP server.");
                }
                $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbUser, $dbPass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Create MySQL schema
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS categories (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(191) NOT NULL UNIQUE,
                        slug VARCHAR(191) NOT NULL UNIQUE,
                        icon VARCHAR(50),
                        description TEXT,
                        image TEXT,
                        display_order INT DEFAULT 0
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS posts (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        title VARCHAR(255) NOT NULL,
                        slug VARCHAR(191) NOT NULL UNIQUE,
                        eyebrow VARCHAR(191),
                        excerpt TEXT,
                        content LONGTEXT NOT NULL,
                        category_id INT,
                        author_name VARCHAR(191),
                        author_avatar VARCHAR(191),
                        featured_image TEXT,
                        reading_time VARCHAR(50) DEFAULT '5 min read',
                        views INT DEFAULT 0,
                        is_featured INT DEFAULT 0,
                        published_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS transcripts (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        post_id INT NOT NULL UNIQUE,
                        speaker_name VARCHAR(191),
                        speaker_title VARCHAR(191),
                        audio_duration VARCHAR(50),
                        transcript_content LONGTEXT NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS polls (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        question TEXT NOT NULL,
                        options_json LONGTEXT NOT NULL,
                        is_active INT DEFAULT 0,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS poll_votes (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        poll_id INT NOT NULL,
                        option_index INT NOT NULL,
                        voter_ip VARCHAR(100) NOT NULL,
                        voted_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS story_submissions (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        author_name VARCHAR(191) NOT NULL,
                        author_email VARCHAR(191) NOT NULL,
                        author_bio TEXT,
                        category VARCHAR(191),
                        title VARCHAR(255) NOT NULL,
                        content LONGTEXT NOT NULL,
                        attachment_url TEXT,
                        status VARCHAR(50) DEFAULT 'pending',
                        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS comments (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        post_id INT NOT NULL,
                        author_name VARCHAR(191) NOT NULL,
                        author_email VARCHAR(191) NOT NULL,
                        content TEXT NOT NULL,
                        status VARCHAR(50) DEFAULT 'approved',
                        parent_id INT DEFAULT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS contact_messages (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        first_name VARCHAR(191),
                        last_name VARCHAR(191),
                        email VARCHAR(191) NOT NULL,
                        subject VARCHAR(255),
                        message TEXT NOT NULL,
                        status VARCHAR(50) DEFAULT 'unread',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS event_reminders (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        event_name VARCHAR(255) NOT NULL,
                        event_date VARCHAR(100) NOT NULL,
                        user_name VARCHAR(191),
                        user_email VARCHAR(191) NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS admins (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(191) NOT NULL UNIQUE,
                        email VARCHAR(191) NOT NULL UNIQUE,
                        password_hash VARCHAR(255) NOT NULL,
                        name VARCHAR(191) NOT NULL,
                        role VARCHAR(50) DEFAULT 'admin',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS site_settings (
                        `key` VARCHAR(191) PRIMARY KEY,
                        `value` LONGTEXT NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS rate_limits (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        action_key VARCHAR(191) NOT NULL,
                        ip_address VARCHAR(100) NOT NULL,
                        request_count INT DEFAULT 1,
                        last_request INT NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS subscribers (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(191),
                        email VARCHAR(191) NOT NULL UNIQUE,
                        list_segment VARCHAR(191) DEFAULT 'community',
                        status VARCHAR(50) DEFAULT 'active',
                        subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS email_campaigns (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        subject VARCHAR(255) NOT NULL,
                        target_list VARCHAR(191) DEFAULT 'all',
                        sender_name VARCHAR(191) DEFAULT 'Conspodium Editorial',
                        sender_email VARCHAR(191) DEFAULT 'newsletter@conspodium.com',
                        content LONGTEXT NOT NULL,
                        status VARCHAR(50) DEFAULT 'draft',
                        scheduled_at DATETIME,
                        sent_at DATETIME,
                        recipients_count INT DEFAULT 0,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS homepage_sections (
                        `key` VARCHAR(191) PRIMARY KEY,
                        value_json LONGTEXT NOT NULL,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS payment_settings (
                        `key` VARCHAR(191) PRIMARY KEY,
                        `value` LONGTEXT NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS payment_transactions (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        transaction_ref VARCHAR(191) NOT NULL UNIQUE,
                        gateway VARCHAR(50) NOT NULL,
                        amount DOUBLE NOT NULL,
                        currency VARCHAR(10) DEFAULT 'USD',
                        customer_email VARCHAR(191) NOT NULL,
                        customer_name VARCHAR(191),
                        tier_name VARCHAR(191) NOT NULL,
                        status VARCHAR(50) DEFAULT 'success',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS forum_categories (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(191) NOT NULL UNIQUE,
                        slug VARCHAR(191) NOT NULL UNIQUE,
                        description TEXT,
                        icon VARCHAR(50),
                        display_order INT DEFAULT 0
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS forum_threads (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        category_id INT NOT NULL,
                        title VARCHAR(255) NOT NULL,
                        slug VARCHAR(191) NOT NULL UNIQUE,
                        author_name VARCHAR(191) NOT NULL,
                        author_email VARCHAR(191) NOT NULL,
                        content LONGTEXT NOT NULL,
                        replies_count INT DEFAULT 0,
                        status VARCHAR(50) DEFAULT 'approved',
                        is_pinned INT DEFAULT 0,
                        views INT DEFAULT 0,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS forum_replies (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        thread_id INT NOT NULL,
                        author_name VARCHAR(191) NOT NULL,
                        author_email VARCHAR(191) NOT NULL,
                        content LONGTEXT NOT NULL,
                        parent_id INT DEFAULT NULL,
                        status VARCHAR(50) DEFAULT 'approved',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS live_discussions (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        topic VARCHAR(255) NOT NULL,
                        speaker_name VARCHAR(191) NOT NULL,
                        speaker_role VARCHAR(191),
                        speaker_avatar TEXT,
                        discussion_date VARCHAR(100) NOT NULL,
                        zoom_link TEXT,
                        ics_summary TEXT,
                        is_active INT DEFAULT 1,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS scholar_spotlights (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        scholar_name VARCHAR(191) NOT NULL,
                        title_affiliation VARCHAR(255),
                        bio TEXT,
                        image_url TEXT,
                        research_field VARCHAR(191),
                        profile_link TEXT,
                        display_order INT DEFAULT 0,
                        is_active INT DEFAULT 1,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                    CREATE TABLE IF NOT EXISTS featured_interviews (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        title VARCHAR(255) NOT NULL,
                        interviewee_name VARCHAR(191) NOT NULL,
                        interviewee_role VARCHAR(191),
                        quote TEXT,
                        photo TEXT,
                        video_url TEXT,
                        is_active INT DEFAULT 1,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
            } else {
                if (!$pdoSqliteOk) {
                    throw new Exception("PDO SQLite extension is not enabled on this PHP server.");
                }
                $dbPath = $dataDir . '/conspodium.db';
                $pdo = new PDO("sqlite:" . $dbPath);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Execute SQLite schema
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS categories (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL UNIQUE,
                        slug TEXT NOT NULL UNIQUE,
                        icon TEXT,
                        description TEXT,
                        image TEXT,
                        display_order INTEGER DEFAULT 0
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
                        category TEXT,
                        title TEXT NOT NULL,
                        content TEXT NOT NULL,
                        attachment_url TEXT,
                        status TEXT DEFAULT 'pending',
                        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                    CREATE TABLE IF NOT EXISTS comments (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        post_id INTEGER NOT NULL,
                        author_name TEXT NOT NULL,
                        author_email TEXT NOT NULL,
                        content TEXT NOT NULL,
                        status TEXT DEFAULT 'approved',
                        parent_id INTEGER DEFAULT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                    CREATE TABLE IF NOT EXISTS contact_messages (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        first_name TEXT,
                        last_name TEXT,
                        email TEXT NOT NULL,
                        subject TEXT,
                        message TEXT NOT NULL,
                        status TEXT DEFAULT 'unread',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                    CREATE TABLE IF NOT EXISTS event_reminders (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        event_name TEXT NOT NULL,
                        event_date TEXT NOT NULL,
                        user_name TEXT,
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
                    CREATE TABLE IF NOT EXISTS site_settings (
                        key TEXT PRIMARY KEY,
                        value TEXT NOT NULL
                    );
                    CREATE TABLE IF NOT EXISTS rate_limits (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        action_key TEXT NOT NULL,
                        ip_address TEXT NOT NULL,
                        request_count INTEGER DEFAULT 1,
                        last_request INTEGER NOT NULL
                    );
                    CREATE TABLE IF NOT EXISTS subscribers (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT,
                        email TEXT NOT NULL UNIQUE,
                        list_segment TEXT DEFAULT 'community',
                        status TEXT DEFAULT 'active',
                        subscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                    CREATE TABLE IF NOT EXISTS email_campaigns (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        subject TEXT NOT NULL,
                        target_list TEXT DEFAULT 'all',
                        sender_name TEXT DEFAULT 'Conspodium Editorial',
                        sender_email TEXT DEFAULT 'newsletter@conspodium.com',
                        content TEXT NOT NULL,
                        status TEXT DEFAULT 'draft',
                        scheduled_at DATETIME,
                        sent_at DATETIME,
                        recipients_count INTEGER DEFAULT 0,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                    CREATE TABLE IF NOT EXISTS homepage_sections (
                        key TEXT PRIMARY KEY,
                        value_json TEXT NOT NULL,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                    CREATE TABLE IF NOT EXISTS payment_settings (
                        key TEXT PRIMARY KEY,
                        value TEXT NOT NULL
                    );
                    CREATE TABLE IF NOT EXISTS payment_transactions (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        transaction_ref TEXT NOT NULL UNIQUE,
                        gateway TEXT NOT NULL,
                        amount REAL NOT NULL,
                        currency TEXT DEFAULT 'USD',
                        customer_email TEXT NOT NULL,
                        customer_name TEXT,
                        tier_name TEXT NOT NULL,
                        status TEXT DEFAULT 'success',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                    CREATE TABLE IF NOT EXISTS forum_categories (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL UNIQUE,
                        slug TEXT NOT NULL UNIQUE,
                        description TEXT,
                        icon TEXT,
                        display_order INTEGER DEFAULT 0
                    );
                    CREATE TABLE IF NOT EXISTS forum_threads (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        category_id INTEGER NOT NULL,
                        title TEXT NOT NULL,
                        slug TEXT NOT NULL UNIQUE,
                        author_name TEXT NOT NULL,
                        author_email TEXT NOT NULL,
                        content TEXT NOT NULL,
                        replies_count INTEGER DEFAULT 0,
                        status TEXT DEFAULT 'approved',
                        is_pinned INTEGER DEFAULT 0,
                        views INTEGER DEFAULT 0,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                    CREATE TABLE IF NOT EXISTS forum_replies (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        thread_id INTEGER NOT NULL,
                        author_name TEXT NOT NULL,
                        author_email TEXT NOT NULL,
                        content TEXT NOT NULL,
                        parent_id INTEGER DEFAULT NULL,
                        status TEXT DEFAULT 'approved',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                    CREATE TABLE IF NOT EXISTS live_discussions (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        topic TEXT NOT NULL,
                        speaker_name TEXT NOT NULL,
                        speaker_role TEXT,
                        speaker_avatar TEXT,
                        discussion_date TEXT NOT NULL,
                        zoom_link TEXT,
                        ics_summary TEXT,
                        is_active INTEGER DEFAULT 1,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                    CREATE TABLE IF NOT EXISTS scholar_spotlights (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        scholar_name TEXT NOT NULL,
                        title_affiliation TEXT,
                        bio TEXT,
                        image_url TEXT,
                        research_field TEXT,
                        profile_link TEXT,
                        display_order INTEGER DEFAULT 0,
                        is_active INTEGER DEFAULT 1,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                    CREATE TABLE IF NOT EXISTS featured_interviews (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        title TEXT NOT NULL,
                        interviewee_name TEXT NOT NULL,
                        interviewee_role TEXT,
                        quote TEXT,
                        photo TEXT,
                        video_url TEXT,
                        is_active INTEGER DEFAULT 1,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    );
                ");
            }

            // Insert admin user
            $hashedPassword = password_hash($adminPass, PASSWORD_DEFAULT);
            if ($dbType === 'mysql') {
                $stmtAdmin = $pdo->prepare("INSERT INTO admins (username, email, password_hash, name, role) VALUES (?, ?, ?, ?, 'admin') ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), name = VALUES(name)");
            } else {
                $stmtAdmin = $pdo->prepare("INSERT OR REPLACE INTO admins (username, email, password_hash, name, role) VALUES (?, ?, ?, ?, 'admin')");
            }
            $stmtAdmin->execute([$adminUser, $adminEmail, $hashedPassword, $adminName]);

            if (strtolower($adminUser) !== 'admin') {
                $defaultPassHash = password_hash('conspodium2026', PASSWORD_DEFAULT);
                $stmtAdmin->execute(['admin', 'editor@conspodium.com', $defaultPassHash, 'Editor Admin']);
            }

            // Seed content if requested
            if ($seedData) {
                $migratedFromSqlite = false;
                $sqliteCandidates = [
                    $dataDir . '/conspodium.db',
                    __DIR__ . '/data/conspodium.db',
                    __DIR__ . '/public/data/conspodium.db'
                ];
                $sqliteDb = null;
                foreach ($sqliteCandidates as $sc) {
                    if (file_exists($sc) && filesize($sc) > 10000) {
                        $sqliteDb = $sc;
                        break;
                    }
                }

                // If existing SQLite database has data, clone ALL live tables into MySQL
                if ($dbType === 'mysql' && $sqliteDb && extension_loaded('pdo_sqlite')) {
                    try {
                        $sqlitePdo = new PDO("sqlite:" . $sqliteDb);
                        $sqlitePdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                        $tablesToMigrate = [
                            'categories', 'posts', 'transcripts', 'polls', 'poll_votes',
                            'story_submissions', 'comments', 'contact_messages', 'homepage_sections',
                            'payment_settings', 'forum_categories', 'forum_threads', 'forum_replies',
                            'scholar_spotlights', 'live_discussions', 'featured_interviews', 'settings'
                        ];

                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

                        foreach ($tablesToMigrate as $tbl) {
                            $check = $sqlitePdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$tbl'")->fetch();
                            if (!$check) continue;

                            $rows = $sqlitePdo->query("SELECT * FROM `$tbl`")->fetchAll();
                            if (empty($rows)) continue;

                            try {
                                $pdo->exec("TRUNCATE TABLE `$tbl`");
                            } catch (Exception $e) {}

                            $cols = array_keys($rows[0]);
                            $escapedCols = implode(", ", array_map(function($c){ return "`$c`"; }, $cols));
                            $placeholders = implode(", ", array_fill(0, count($cols), '?'));

                            $stmtIns = $pdo->prepare("INSERT INTO `$tbl` ($escapedCols) VALUES ($placeholders)");
                            foreach ($rows as $row) {
                                try {
                                    $stmtIns->execute(array_values($row));
                                } catch (Exception $e) {}
                            }
                        }

                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                        $migratedFromSqlite = true;
                    } catch (Throwable $mErr) {
                        $migratedFromSqlite = false;
                    }
                }

                // Fallback rich seeding if no SQLite file was present
                if (!$migratedFromSqlite) {
                    $catCount = $pdo->query("SELECT COUNT(*) as count FROM categories")->fetch()['count'] ?? 0;
                    if ($catCount == 0) {
                        $stmtCat = $pdo->prepare("INSERT INTO categories (name, slug, icon, description, image, display_order) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmtCat->execute(['Culture & Heritage', 'culture-heritage', '🏛️', 'Heritage, traditions, and the African spirit abroad.', '/wp-content/uploads/2026/01/African-Diasporans-1536x864-1.jpg', 1]);
                        $stmtCat->execute(['Innovation', 'innovation', '💡', 'Africans in Diaspora influencing economic decisions worldwide.', '/wp-content/uploads/2026/01/location-1-300x210.webp', 2]);
                        $stmtCat->execute(['Art & Entertainment', 'art-entertainment', '🎨', 'Creatives are shaping and representing global culture.', '/wp-content/uploads/2026/01/MoADCover-1180x664-1.jpg', 3]);
                        $stmtCat->execute(['Community', 'community', '👥', 'Stories connecting Africans in Diaspora across the globe.', '/wp-content/uploads/2026/01/AF3-1-png-300x171.jpg', 4]);
                        $stmtCat->execute(['Success Stories', 'success-stories', '🌟', 'Growth, Success, leadership, and diaspora impact.', '/wp-content/uploads/2026/02/portrait-smiley-people-african-wedding-300x200.jpg', 5]);
                        $stmtCat->execute(['African Diaspora Matters', 'african-diaspora-matters', '🌍', 'Crucial issues, policy debates, and global diaspora developments.', '/uploads/cat_diaspora_matters.png', 6]);
                        $stmtCat->execute(['Diaspora Insights & Analysis', 'diaspora-insights-analysis', '📊', 'In-depth research, economic reports, and diaspora market analysis.', '/uploads/cat_diaspora_insights.png', 7]);
                    }

                    $postCount = $pdo->query("SELECT COUNT(*) as count FROM posts")->fetch()['count'] ?? 0;
                    if ($postCount == 0) {
                        $stmtPost = $pdo->prepare("
                            INSERT INTO posts (title, slug, eyebrow, excerpt, content, category_id, author_name, author_avatar, featured_image, reading_time, views, is_featured, published_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");

                        $stmtPost->execute([
                            "Pan-African Innovation & Heritage: Empowering Global Diaspora Networks",
                            "empowering-diaspora-communities-through-innovation-heritage",
                            "Featured Editorial",
                            "Exploring how pan-African leaders, creators, and innovators are shaping global economic policies and cultural narratives across the diaspora.",
                            "<p>Across Africa and its global diaspora, leaders in technology, finance, and arts are building bridges for sustainable economic growth and cultural exchange.</p><p>Through diaspora summits, bilateral investment funds, and cross-border tech incubator networks, pan-African innovators are turning shared history into actionable global impact.</p>",
                            2,
                            "Steving Felix",
                            "SF",
                            "/wp-content/uploads/2026/01/African-Diasporans-1536x864-1.jpg",
                            "6 min read",
                            2450,
                            1,
                            date('Y-m-d H:i:s')
                        ]);

                        $stmtPost->execute([
                            "Where Heritage, Adventure, Nature and Opportunity Come Alive: Calabar & Cross River State",
                            "calabar-cross-river-state-tourism-and-diaspora-investment",
                            "Destination Feature",
                            "Cross River State continues to demonstrate that tourism is more than sightseeing—it is a catalyst for economic growth, cultural preservation, and international partnerships.",
                            "<p>Cross River State continues to demonstrate that tourism is a catalyst for economic growth, cultural preservation, and international partnerships. For members of the global diaspora, Cross River offers a unique opportunity to reconnect with ancestral heritage while participating in one of Nigeria's most promising investment frontiers.</p>",
                            1,
                            "Editorial Feature Desk",
                            "EF",
                            "/uploads/calabar_tourism_hero.png",
                            "7 min read",
                            1640,
                            0,
                            date('Y-m-d H:i:s', strtotime('-1 days'))
                        ]);
                    }

                    $schCount = $pdo->query("SELECT COUNT(*) as count FROM scholar_spotlights")->fetch()['count'] ?? 0;
                    if ($schCount == 0) {
                        $stmtSch = $pdo->prepare("INSERT INTO scholar_spotlights (scholar_name, title_affiliation, bio, image_url, research_field, profile_link, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                        $stmtSch->execute(["Prof. Amara Diallo Jr", "American School of Economics", '"Democracy, Digital Sovereignty & the African Voice in Global Governance..."', "/uploads/upload_1789726789_a3866bad.webp", "Democracy Sovereignty", "/post/empowering-diaspora-communities-through-innovation-heritage/", 1]);
                        $stmtSch->execute(["Mrs Margaret Benson", "MIT Media Lab", '"Biotechnology and the Future of African Health Systems — Who Controls the Science?"', "/uploads/upload_1789741903_83d9f9e0.jpg", "Biotechnology & Health Systems", "/post/we-are-the-world/", 2]);
                        $stmtSch->execute(["Prof. Kwame Osei", "University of Ghana / Oxford", '"African Intellectual Heritage and the Decolonisation of Academic Thought"', "/wp-content/uploads/2026/08/scholar-kwame-osei.png", "African Intellectual Heritage", "/post/creatives-shaping-representing-global-african-culture/", 3]);
                    }

                    $fiCount = $pdo->query("SELECT COUNT(*) as count FROM featured_interviews")->fetch()['count'] ?? 0;
                    if ($fiCount == 0) {
                        $stmtFi = $pdo->prepare("INSERT INTO featured_interviews (title, interviewee_name, interviewee_role, quote, photo, video_url, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                        $stmtFi->execute(["In Conversation With", "Prof. Amara Diallo & The Panel", "Cultural Historian", "Building Bridges Across Nations", "/uploads/upload_1789745044_214929bc.jpg", ""]);
                    }

                    $ldCount = $pdo->query("SELECT COUNT(*) as count FROM live_discussions")->fetch()['count'] ?? 0;
                    if ($ldCount == 0) {
                        $stmtLd = $pdo->prepare("INSERT INTO live_discussions (topic, speaker_name, speaker_role, speaker_avatar, discussion_date, zoom_link, ics_summary, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                        $stmtLd->execute(["The Future of African Democracy", "Prof. Amara Diallo & The Panel", "London School of Economics", "/uploads/upload_1789742036_15826c02.jpg", "2026-10-17T18:00", "https://zoom.us/j/conspodium-live", ""]);
                    }

                    // Seed default homepage sections
                    try {
                        $stmtHomeSec = $pdo->prepare("INSERT INTO homepage_sections (`key`, value_json, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE value_json = VALUES(value_json)");
                        $stmtHomeSec->execute(['featured_stories_ids', json_encode([1, 2, 3, 5])]);
                        $stmtHomeSec->execute(['trending_ids', json_encode([6, 5, 4, 1, 7, 3])]);
                        $stmtHomeSec->execute(['homepage_category_ids', json_encode([1, 2, 3])]);
                    } catch (Exception $e) {}
                }
            }

            // Save Config File
            $configPhpCode = "<?php\n"
               . "// Conspodium Database Configuration - Auto-generated by install.php\n"
               . "define('DB_DRIVER', " . var_export($dbType, true) . ");\n"
               . "define('DB_HOST', " . var_export($dbHost, true) . ");\n"
               . "define('DB_PORT', " . var_export($dbPort, true) . ");\n"
               . "define('DB_NAME', " . var_export($dbName, true) . ");\n"
               . "define('DB_USER', " . var_export($dbUser, true) . ");\n"
               . "define('DB_PASS', " . var_export($dbPass, true) . ");\n";

            file_put_contents($dataDir . '/config.php', $configPhpCode);
            file_put_contents(__DIR__ . '/api/config.php', $configPhpCode);
            if (file_exists(__DIR__ . '/public/api')) {
                file_put_contents(__DIR__ . '/public/api/config.php', $configPhpCode);
            }

            // Create security lock file
            file_put_contents($lockFile, "CONSPODIUM_INSTALLED_ON=" . date('c') . "\nADMIN=" . $adminUser . "\nDRIVER=" . $dbType . "\n");
            $isInstalled = true;
            $successMessage = "Installation completed successfully! Configured for " . strtoupper($dbType) . ".";

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
      max-width: 620px;
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
  <p class="subtitle">Set up your database connection and admin credentials for your Shared Hosting / Local server.</p>

  <?php if ($isInstalled): ?>
    <div class="alert-ok">
      <strong>🎉 Conspodium is already installed & ready!</strong><br>
      For security reasons, the installer has been locked (`data/install.lock`).
    </div>
    <div class="action-links" style="flex-direction:column;gap:10px;">
      <div style="display:flex;gap:14px;">
        <a href="./" class="action-btn btn-secondary">🌐 View Homepage</a>
        <a href="./dashboard/" class="action-btn btn-primary">🔑 Admin CMS Portal →</a>
      </div>
      <a href="./install.php?reinstall=1" class="action-btn btn-secondary" style="font-size:0.8rem;color:#8a99ad;" onclick="return confirm('Are you sure you want to unlock and re-run the installation wizard?')">🔄 Re-run Installation Wizard</a>
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
        <span>PDO MySQL Extension (Shared Hosting)</span>
        <span class="<?= $pdoMysqlOk ? 'badge-ok' : 'badge-err' ?>"><?= $pdoMysqlOk ? '✓ INSTALLED' : '✗ MISSING' ?></span>
      </div>
      <div class="check-item">
        <span>PDO SQLite Extension (Embedded File)</span>
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
      <form method="POST" action="install.php">
        
        <!-- Step 2: Database Engine Selection -->
        <div class="section-title">🗄️ Database Configuration</div>
        <div class="form-group" style="margin-bottom: 20px;">
          <label class="form-label">Select Database Engine *</label>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 6px;">
            <label id="db-type-mysql-card" style="background: rgba(0, 174, 254, 0.1); border: 2px solid var(--blue); border-radius: 14px; padding: 14px; cursor: pointer; display: flex; align-items: flex-start; gap: 10px; transition: all 0.2s;">
              <input type="radio" name="db_type" value="mysql" checked onchange="toggleDbFields()" style="margin-top: 3px; accent-color: var(--blue);">
              <div>
                <strong style="display: block; font-size: 0.9rem; color: #fff;">MySQL / MariaDB</strong>
                <span style="font-size: 0.78rem; color: var(--text-muted); line-height: 1.4; display: block; margin-top: 3px;">Recommended for cPanel, Hostinger, Plesk, GoDaddy Shared Hosting.</span>
              </div>
            </label>

            <label id="db-type-sqlite-card" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 14px; padding: 14px; cursor: pointer; display: flex; align-items: flex-start; gap: 10px; transition: all 0.2s;">
              <input type="radio" name="db_type" value="sqlite" onchange="toggleDbFields()" style="margin-top: 3px; accent-color: var(--blue);">
              <div>
                <strong style="display: block; font-size: 0.9rem; color: #fff;">SQLite (File)</strong>
                <span style="font-size: 0.78rem; color: var(--text-muted); line-height: 1.4; display: block; margin-top: 3px;">Zero-configuration embedded file database (`data/conspodium.db`).</span>
              </div>
            </label>
          </div>
        </div>

        <!-- MySQL Credentials Box -->
        <div id="mysql-options-box" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 16px; padding: 20px; margin-bottom: 24px;">
          <div style="font-size: 0.8rem; font-weight: 700; color: var(--blue); margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.05em;">cPanel / Shared Host MySQL Settings</div>
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Database Host *</label>
              <input type="text" name="db_host" id="db_host_input" class="form-input" placeholder="localhost or 127.0.0.1" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Database Port</label>
              <input type="text" name="db_port" id="db_port_input" class="form-input" placeholder="3306" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Database Name *</label>
            <input type="text" name="db_name" id="db_name_input" class="form-input" placeholder="e.g. craftmyw_conspodium" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>">
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Database Username *</label>
              <input type="text" name="db_user" id="db_user_input" class="form-input" placeholder="e.g. craftmyw_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Database Password</label>
              <input type="password" name="db_pass" id="db_pass_input" class="form-input" placeholder="MySQL Password" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>">
            </div>
          </div>
        </div>

        <!-- Step 3: Admin Setup Form -->
        <div class="section-title">👤 Create Admin Account</div>
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
            Seed starter Conspodium categories, articles, scholars, and weekly poll
          </label>
        </div>

        <button type="submit" class="btn-submit">⚡ Install Conspodium Now →</button>
      </form>

      <script>
        function toggleDbFields() {
          var isMysql = document.querySelector('input[name="db_type"]:checked').value === 'mysql';
          var box = document.getElementById('mysql-options-box');
          var sqliteCard = document.getElementById('db-type-sqlite-card');
          var mysqlCard = document.getElementById('db-type-mysql-card');

          if (isMysql) {
            box.style.display = 'block';
            mysqlCard.style.background = 'rgba(0, 174, 254, 0.1)';
            mysqlCard.style.borderColor = 'var(--blue)';
            sqliteCard.style.background = 'rgba(255, 255, 255, 0.03)';
            sqliteCard.style.borderColor = 'rgba(255, 255, 255, 0.1)';
          } else {
            box.style.display = 'none';
            sqliteCard.style.background = 'rgba(0, 174, 254, 0.1)';
            sqliteCard.style.borderColor = 'var(--blue)';
            mysqlCard.style.background = 'rgba(255, 255, 255, 0.03)';
            mysqlCard.style.borderColor = 'rgba(255, 255, 255, 0.1)';
          }
        }
        toggleDbFields();
      </script>
    <?php else: ?>
      <div class="alert-err">
        Please resolve the failed server requirements above before proceeding with installation.
      </div>
    <?php endif; ?>

  <?php endif; ?>

</div>

</body>
</html>
