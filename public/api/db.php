<?php
// api/db.php - PDO SQLite Database Connection & Schema Setup

$dbDir = file_exists(__DIR__ . '/../../data') ? __DIR__ . '/../../data' : __DIR__ . '/../data';
if (!file_exists($dbDir)) {
    mkdir($dbDir, 0777, true);
}

$dbPath = $dbDir . '/conspodium.db';

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
        $stmtCat->execute(['Democracy & Politics', 'democracy-politics', '🏛️', 'Multilateral governance and political sovereignty across the diaspora.']);
        $stmtCat->execute(['AI & Data Ethics', 'ai-data-ethics', '🤖', 'African data sovereignty, algorithm ethics, and diaspora tech innovation.']);
        $stmtCat->execute(['Climate & Environment', 'climate-environment', '🌿', 'Grassroots climate justice, indigenous knowledge, and sustainability.']);
        $stmtCat->execute(['Arts & Philosophy', 'arts-philosophy', '📚', 'Decolonising education, pan-African philosophy, and cultural literature.']);
        $stmtCat->execute(['Biotechnology', 'biotechnology', '🧬', 'Scientific independence, healthcare innovation, and biotechnology in Africa.']);
    }

    // Seed initial posts if empty
    $postCount = $pdo->query("SELECT COUNT(*) as count FROM posts")->fetch()['count'];
    if ($postCount == 0) {
        $stmtPost = $pdo->prepare("
            INSERT INTO posts (title, slug, eyebrow, excerpt, content, category_id, author_name, author_avatar, featured_image, reading_time, views, is_featured, published_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmtPost->execute([
            "The Digital Sovereignty Crisis: Who Controls Africa’s Data Future?",
            "digital-sovereignty-crisis-africas-data-future",
            "This Week's Featured Essay",
            "A landmark investigation into how global tech giants are shaping African digital policy — and what diaspora leaders are doing to fight back.",
            "<p>Across Africa and its global diaspora, a quiet battle for digital self-determination is underway. As foreign technology conglomerates expand data centers and cloud infrastructure, questions of data governance, privacy rights, and algorithmic bias have reached a critical tipping point.</p><p>African scholars and diaspora technologists are pioneering open frameworks that ensure data generated on the continent empowers local communities rather than extracting value abroad.</p>",
            2,
            "Dr. Kemi Adebayo",
            "KA",
            "./wp-content/uploads/2026/01/girls-walk-along-streets-city-scaled.jpg",
            "8 min read",
            1204,
            1,
            date('Y-m-d H:i:s')
        ]);

        $stmtPost->execute([
            "A New Perspective on Global Leadership",
            "new-perspective-on-global-leadership",
            "Coming Soon",
            "Prof. Amara Diallo of the London School of Economics shares his groundbreaking framework for African-led multilateral governance in the digital age.",
            "<p>Global governance models inherited from the 20th century are increasingly ill-equipped to address global challenges. Prof. Amara Diallo proposes a restructured pan-African diplomatic framework prioritizing youth representation, economic integration, and digital sovereignty.</p>",
            1,
            "Prof. Amara Diallo",
            "AD",
            "./wp-content/uploads/2026/01/portrait-two-friends-holding-each-other-city-scaled.jpg",
            "6 min read",
            987,
            0,
            date('Y-m-d H:i:s')
        ]);

        $stmtPost->execute([
            "In Conversation with Dr. Ngozi Eze on Biotechnology",
            "in-conversation-with-dr-ngozi-eze",
            "Exclusive Interview",
            "\"Biotechnology is the next frontier of African liberation. We must own our science, our data, and our story.\" — Dr. Ngozi Eze, MIT Media Lab.",
            "<p>In this exclusive interview, Dr. Ngozi Eze explores how bio-manufacturing, genetic research ethics, and diaspora-backed laboratories are transforming healthcare self-reliance in West Africa.</p>",
            5,
            "Dr. Ngozi Eze",
            "NE",
            "./wp-content/uploads/2026/01/couple-using-technology-while-traveling-city-scaled.jpg",
            "12 min read",
            834,
            0,
            date('Y-m-d H:i:s')
        ]);
    }

    // Seed active poll if empty
    $pollCount = $pdo->query("SELECT COUNT(*) as count FROM polls")->fetch()['count'];
    if ($pollCount == 0) {
        $options = [
            "AI Ethics & African Data Sovereignty",
            "Global Political Representation",
            "Climate Justice & African Communities",
            "Philosophy & Decolonising Education"
        ];
        $stmtPoll = $pdo->prepare("INSERT INTO polls (question, options_json, is_active) VALUES (?, ?, 1)");
        $stmtPoll->execute([
            "What is the most pressing issue facing the African diaspora today?",
            json_encode($options)
        ]);
        $pollId = $pdo->lastInsertId();

        // Seed initial vote counts
        $stmtVote = $pdo->prepare("INSERT INTO poll_votes (poll_id, option_index, voter_ip) VALUES (?, ?, ?)");
        for ($i = 0; $i < 312; $i++) $stmtVote->execute([$pollId, 0, "seed-$i"]);
        for ($i = 0; $i < 198; $i++) $stmtVote->execute([$pollId, 1, "seed-$i"]);
        for ($i = 0; $i < 271; $i++) $stmtVote->execute([$pollId, 2, "seed-$i"]);
        for ($i = 0; $i < 145; $i++) $stmtVote->execute([$pollId, 3, "seed-$i"]);
    }

} catch (PDOException $e) {
    die(json_encode(["success" => false, "error" => "Database Connection Failed: " . $e->getMessage()]));
}
