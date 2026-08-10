<?php
// api/db.php - PDO SQLite Database Connection & Schema Setup

$isVercel = getenv('VERCEL') || !empty($_ENV['VERCEL']) || !empty($_SERVER['VERCEL']);

if ($isVercel) {
    $dbPath = '/tmp/conspodium.db';
    $seedDb = file_exists(__DIR__ . '/../../data/conspodium.db') ? __DIR__ . '/../../data/conspodium.db' : __DIR__ . '/../data/conspodium.db';
    if ((!file_exists($dbPath) || @filesize($dbPath) < 1000) && file_exists($seedDb)) {
        @copy($seedDb, $dbPath);
    }
} else {
    $dbDir = file_exists(__DIR__ . '/../../data') ? __DIR__ . '/../../data' : __DIR__ . '/../data';
    if (!file_exists($dbDir)) {
        @mkdir($dbDir, 0777, true);
    }
    $dbPath = $dbDir . '/conspodium.db';
}

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Create tables if not exist
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
            category TEXT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            attachment_url TEXT,
            status TEXT DEFAULT 'pending',
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Migration for existing databases
    try {
        $pdo->exec("ALTER TABLE story_submissions ADD COLUMN category TEXT");
    } catch (Exception $e) {
        // Column already exists
    }

    $pdo->exec("
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

        CREATE TABLE IF NOT EXISTS site_settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
    ");

    // Seed default site settings if empty
    $settingsCount = $pdo->query("SELECT COUNT(*) as count FROM site_settings")->fetch()['count'];
    if ($settingsCount == 0) {
        $stmtSet = $pdo->prepare("INSERT INTO site_settings (key, value) VALUES (?, ?)");
        $stmtSet->execute(['site_name', 'Conspodium']);
        $stmtSet->execute(['site_tagline', 'Premium Diaspora Magazine']);
        $stmtSet->execute(['admin_email', 'admin@conspodium.com']);
        $stmtSet->execute(['default_author', 'Conspodium Editorial']);
        $stmtSet->execute(['posts_per_page', '6']);
        $stmtSet->execute(['allow_submissions', '1']);
        $stmtSet->execute(['smtp_host', 'smtp.gmail.com']);
        $stmtSet->execute(['smtp_port', '587']);
        $stmtSet->execute(['smtp_user', '']);
        $stmtSet->execute(['smtp_pass', '']);
        $stmtSet->execute(['sender_email', 'noreply@conspodium.com']);
        $stmtSet->execute(['sender_name', 'Conspodium Alerts']);
        $stmtSet->execute(['popup_ad_enabled', '0']);
        $stmtSet->execute(['popup_ad_title', 'Empowering Diaspora Communities Worldwide']);
        $stmtSet->execute(['popup_ad_image', './wp-content/uploads/2026/01/African-Diasporans-1536x864-1.jpg']);
        $stmtSet->execute(['popup_ad_link', '/submit-story/']);
        $stmtSet->execute(['popup_ad_delay', '3']);
    }

    // Seed default admin account if empty
    $adminCount = $pdo->query("SELECT COUNT(*) as count FROM admins")->fetch()['count'];
    if ($adminCount == 0) {
        $defaultPassword = password_hash('conspodium2026', PASSWORD_DEFAULT);
        $stmtAdmin = $pdo->prepare("INSERT INTO admins (username, email, password_hash, name, role) VALUES (?, ?, ?, ?, ?)");
        $stmtAdmin->execute(['admin', 'admin@conspodium.com', $defaultPassword, 'Editor Admin', 'admin']);
    }

    // Seed initial categories if empty
    $catCount = $pdo->query("SELECT COUNT(*) as count FROM categories")->fetch()['count'];
    if ($catCount == 0) {
        $stmtCat = $pdo->prepare("INSERT INTO categories (name, slug, icon, description) VALUES (?, ?, ?, ?)");
        $stmtCat->execute(['Culture & Heritage', 'culture-heritage', '🏛️', 'Heritage, traditions, and the African spirit abroad.']);
        $stmtCat->execute(['Innovation', 'innovation', '💡', 'Africans in Diaspora influencing economic decisions worldwide.']);
        $stmtCat->execute(['Art & Entertainment', 'art-entertainment', '🎨', 'Creatives are shaping and representing global culture.']);
        $stmtCat->execute(['Community', 'community', '👥', 'Stories connecting Africans in Diaspora across the globe.']);
        $stmtCat->execute(['Success Stories', 'success-stories', '🌟', 'Growth, Success, leadership, and diaspora impact.']);
    }

    // Seed initial posts if empty
    $postCount = $pdo->query("SELECT COUNT(*) as count FROM posts")->fetch()['count'];
    if ($postCount == 0) {
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
            "Dr. Kemi Adebayo",
            "KA",
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
    }

    // Seed active poll if empty
    $pollCount = $pdo->query("SELECT COUNT(*) as count FROM polls")->fetch()['count'];
    if ($pollCount == 0) {
        $options = [
            "Innovation & Venture Capital",
            "Cultural Heritage & Arts",
            "Youth Education & Mentorship",
            "Diaspora Trade & Economic Policy"
        ];
        $stmtPoll = $pdo->prepare("INSERT INTO polls (question, options_json, is_active) VALUES (?, ?, 1)");
        $stmtPoll->execute([
            "What area of African diaspora impact should Conspodium feature next?",
            json_encode($options)
        ]);
        $pollId = $pdo->lastInsertId();

        // Seed initial vote counts
        $stmtVote = $pdo->prepare("INSERT INTO poll_votes (poll_id, option_index, voter_ip) VALUES (?, ?, ?)");
        for ($i = 0; $i < 340; $i++) $stmtVote->execute([$pollId, 0, "seed-$i"]);
        for ($i = 0; $i < 210; $i++) $stmtVote->execute([$pollId, 1, "seed-$i"]);
        for ($i = 0; $i < 285; $i++) $stmtVote->execute([$pollId, 2, "seed-$i"]);
        for ($i = 0; $i < 160; $i++) $stmtVote->execute([$pollId, 3, "seed-$i"]);
    }

} catch (PDOException $e) {
    die(json_encode(["success" => false, "error" => "Database Connection Failed: " . $e->getMessage()]));
}
