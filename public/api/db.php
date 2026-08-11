<?php
// api/db.php - PDO SQLite Database Connection & Schema Setup

$isVercel = getenv('VERCEL') || !empty($_ENV['VERCEL']) || !empty($_SERVER['VERCEL']) ||
            !empty($_ENV['VERCEL_ENV']) || !empty($_SERVER['VERCEL_ENV']) ||
            !empty($_ENV['NOW_REGION']) || !empty($_SERVER['NOW_REGION']) ||
            strpos(__DIR__, '/var/task') !== false || file_exists('/var/task');

if ($isVercel) {
    $dbPath = '/tmp/conspodium.db';
    if (!file_exists($dbPath) || @filesize($dbPath) < 1000) {
        $possibleSeedPaths = [
            __DIR__ . '/../data/conspodium.db',
            __DIR__ . '/data/conspodium.db',
            __DIR__ . '/../../data/conspodium.db',
            '/var/task/data/conspodium.db',
            '/var/task/conspodium.db',
            dirname(__DIR__) . '/data/conspodium.db'
        ];
        foreach ($possibleSeedPaths as $candidate) {
            if (file_exists($candidate) && @filesize($candidate) >= 1000) {
                @copy($candidate, $dbPath);
                break;
            }
        }
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
            description TEXT,
            image TEXT
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
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
    ");

    // Migration for existing databases
    try {
        $pdo->exec("ALTER TABLE story_submissions ADD COLUMN category TEXT");
    } catch (Exception $e) {
        // Column already exists
    }
    try {
        $pdo->exec("ALTER TABLE categories ADD COLUMN image TEXT");
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

        CREATE TABLE IF NOT EXISTS rate_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            action_key TEXT NOT NULL,
            ip_address TEXT NOT NULL,
            request_count INTEGER DEFAULT 1,
            last_request INTEGER NOT NULL
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

    // Seed 4 crawled WordPress articles if missing
    $crawledPosts = [
        [
            "title" => "From mutual aid networks to cultural organizations, discover how diaspora communities create support systems that span the globe.",
            "slug" => "profiles-of-groundbreaking-tech-entrepreneurs-from-diaspora-2",
            "eyebrow" => "Community & Networks",
            "excerpt" => "When my family arrived in Minneapolis from Somalia in the early 1990s, we didn't just find a new home—we found an informal safety net built by those who came before us.",
            "content" => "<p>When my family arrived in Minneapolis from Somalia in the early 1990s, we didn't just find a new home—we found an informal safety net built by those who came before us.</p><p>From mutual aid networks to cultural organizations, diaspora communities continue to build vital support systems across the globe.</p>",
            "category_id" => 4,
            "author_name" => "Conspodium Editorial",
            "author_avatar" => "CE",
            "featured_image" => "./wp-content/uploads/2026/01/AF3-1-png-300x171.jpg",
            "reading_time" => "6 min read",
            "views" => 1540,
            "is_featured" => 0,
            "published_at" => "2026-02-05 10:00:00"
        ],
        [
            "title" => "Profiles of Groundbreaking Tech Entrepreneurs From Diaspora",
            "slug" => "profiles-of-groundbreaking-tech-entrepreneurs-from-diaspora",
            "eyebrow" => "Innovation & Tech",
            "excerpt" => "The story of Silicon Valley cannot be told without highlighting the incredible impact of diaspora founders and tech innovators driving global change.",
            "content" => "<p>The story of Silicon Valley cannot be told without highlighting the incredible impact of diaspora founders and tech innovators driving global change.</p><p>By bringing unique cross-cultural perspectives, tech founders from the African diaspora are building scalable platforms that address global challenges in fintech, agritech, and healthtech.</p>",
            "category_id" => 2,
            "author_name" => "Conspodium Tech",
            "author_avatar" => "CT",
            "featured_image" => "./wp-content/uploads/2026/01/location-1-300x210.webp",
            "reading_time" => "5 min read",
            "views" => 1890,
            "is_featured" => 0,
            "published_at" => "2026-02-05 09:00:00"
        ],
        [
            "title" => "We Are The World",
            "slug" => "we-are-the-world",
            "eyebrow" => "Culture & Identity",
            "excerpt" => "By Amara Okonkwo, Cultural Anthropologist. The first time I experienced global unity across diaspora cultures was during the pan-African cultural festival.",
            "content" => "<p>By Amara Okonkwo, Cultural Anthropologist. The first time I experienced global unity across diaspora cultures was during the pan-African cultural festival.</p><p>Our shared stories, rhythms, and artistic heritage transcend geographic borders, creating a powerful collective identity for global Africans everywhere.</p>",
            "category_id" => 1,
            "author_name" => "Amara Okonkwo",
            "author_avatar" => "AO",
            "featured_image" => "./wp-content/uploads/2026/02/tourist-carrying-luggage-300x150.jpg",
            "reading_time" => "4 min read",
            "views" => 2100,
            "is_featured" => 0,
            "published_at" => "2026-02-04 12:00:00"
        ],
        [
            "title" => "Visionary Entrepreneurs Leveraging Their Dual Cultural Knowledge",
            "slug" => "conspodium-is-all-about-community",
            "eyebrow" => "Art & Enterprise",
            "excerpt" => "When I founded my first company at 24, connecting African artisans with European fashion houses, I realized our dual heritage is our greatest superpower.",
            "content" => "<p>When I founded my first company at 24, connecting African artisans with European fashion houses, I realized our dual heritage is our greatest superpower.</p><p>Navigating both local traditions and international markets enables diaspora entrepreneurs to create unprecedented value and foster sustainable creative economies.</p>",
            "category_id" => 3,
            "author_name" => "Conspodium Editorial",
            "author_avatar" => "CE",
            "featured_image" => "./wp-content/uploads/2026/02/portrait-smiley-people-african-wedding-300x200.jpg",
            "reading_time" => "7 min read",
            "views" => 1320,
            "is_featured" => 0,
            "published_at" => "2026-02-04 11:00:00"
        ]
    ];

    $stmtCheck = $pdo->prepare("SELECT COUNT(*) as count FROM posts WHERE slug = ?");
    $stmtInsertCrawled = $pdo->prepare("
        INSERT INTO posts (title, slug, eyebrow, excerpt, content, category_id, author_name, author_avatar, featured_image, reading_time, views, is_featured, published_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($crawledPosts as $cp) {
        $stmtCheck->execute([$cp['slug']]);
        if ($stmtCheck->fetch()['count'] == 0) {
            $stmtInsertCrawled->execute([
                $cp['title'], $cp['slug'], $cp['eyebrow'], $cp['excerpt'], $cp['content'],
                $cp['category_id'], $cp['author_name'], $cp['author_avatar'], $cp['featured_image'],
                $cp['reading_time'], $cp['views'], $cp['is_featured'], $cp['published_at']
            ]);
        }
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

    // Seed initial story submissions if empty
    $subCount = $pdo->query("SELECT COUNT(*) as count FROM story_submissions")->fetch()['count'];
    if ($subCount == 0) {
        $stmtSub = $pdo->prepare("INSERT INTO story_submissions (author_name, author_email, author_bio, category, title, content, status, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtSub->execute([
            "Olamide Bakare",
            "olamide.bakare@diaspora.org",
            "Cultural preservation researcher based in London.",
            "Culture & Heritage",
            "Preserving Yoruba Cultural Heritage & Language in Modern London",
            "How third-generation African British families are creating Saturday language academies and cultural storytelling circles to keep ancestral heritage alive.",
            "pending",
            date('Y-m-d H:i:s', strtotime('-1 days'))
        ]);
        $stmtSub->execute([
            "Chidi Nnamdi",
            "chidi@pantech.io",
            "Tech founder bridging African developers with US startups.",
            "Innovation",
            "Pan-African Tech Incubators: Connecting Silicon Valley & Lagos",
            "Exploring the rise of remote-first engineering hubs that enable Nigerian software engineers to build global products while living in Lagos and Abuja.",
            "pending",
            date('Y-m-d H:i:s', strtotime('-2 days'))
        ]);
        $stmtSub->execute([
            "Fatou Sow",
            "fatou.sow@cuisineafrique.fr",
            "Culinary artist and restaurant owner in Paris.",
            "Art & Entertainment",
            "Diaspora Culinary Renaissance: West African Gastronomy in Paris",
            "How West African chefs in France are blending traditional ingredients with modern gastronomy to elevate African fine dining on world stages.",
            "pending",
            date('Y-m-d H:i:s', strtotime('-3 days'))
        ]);
        $stmtSub->execute([
            "Kwame Mensah",
            "kwame.m@torontovoices.ca",
            "Writer and documentary filmmaker based in Toronto.",
            "Community",
            "Reflections on Dual Identity: Growing Up Between Ghana & Canada",
            "A personal essay on navigating multicultural identity, homecomings, and belonging across two continents.",
            "pending",
            date('Y-m-d H:i:s', strtotime('-4 days'))
        ]);
    }

    // Seed initial contact messages if empty
    $msgCount = $pdo->query("SELECT COUNT(*) as count FROM contact_messages")->fetch()['count'];
    if ($msgCount == 0) {
        $stmtMsg = $pdo->prepare("INSERT INTO contact_messages (first_name, last_name, email, subject, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtMsg->execute([
            "Amina",
            "Kone",
            "amina.kone@globaldiaspora.org",
            "Partnership Proposal: Pan-African Leadership Summit 2026",
            "Hello Conspodium Editorial Team, We would love to explore a media partnership for our upcoming global leadership summit in October.",
            "unread",
            date('Y-m-d H:i:s', strtotime('-2 hours'))
        ]);
        $stmtMsg->execute([
            "David",
            "Okonkwo",
            "david@techdiaspora.com",
            "Advertising Inquiry: Digital Banner Placements",
            "Hi there, We are interested in booking digital banner placements on your homepage and Innovation category section. Please send your media kit.",
            "unread",
            date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);
    }

} catch (PDOException $e) {
    die(json_encode(["success" => false, "error" => "Database Connection Failed: " . $e->getMessage()]));
}

function csp_check_rate_limit($actionKey, $maxRequests = 5, $windowSeconds = 60) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $now = time();

    try {
        $stmtClean = $pdo->prepare("DELETE FROM rate_limits WHERE last_request < ?");
        $stmtClean->execute([$now - $windowSeconds]);

        $stmt = $pdo->prepare("SELECT id, request_count, last_request FROM rate_limits WHERE action_key = ? AND ip_address = ?");
        $stmt->execute([$actionKey, $ip]);
        $row = $stmt->fetch();

        if ($row) {
            if ($row['request_count'] >= $maxRequests) {
                return false;
            }
            $stmtUp = $pdo->prepare("UPDATE rate_limits SET request_count = request_count + 1, last_request = ? WHERE id = ?");
            $stmtUp->execute([$now, $row['id']]);
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO rate_limits (action_key, ip_address, request_count, last_request) VALUES (?, ?, 1, ?)");
            $stmtIns->execute([$actionKey, $ip, $now]);
        }
    } catch (Exception $e) {}

    return true;
}

function csp_get_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csp_verify_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $clientToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!empty($sessionToken) && !empty($clientToken) && hash_equals($sessionToken, $clientToken)) {
        return true;
    }
    if (!empty($_SESSION['admin_id'])) {
        return true;
    }
    return false;
}

function csp_sanitize($input) {
    if (is_array($input)) {
        return array_map('csp_sanitize', $input);
    }
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

