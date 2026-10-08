<?php
// api/homepage.php - Dynamic Homepage Content & Layout API
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token");
header("Content-Type: application/json");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_all';

// PUBLIC EVENT REGISTRATION (SAVED TO DATABASE event_reminders & subscribers)
if ($action === 'register_event' || $action === 'set_event_reminder') {
    $rawInput = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $email = csp_sanitize($rawInput['email'] ?? $_POST['email'] ?? '');
    $name = csp_sanitize($rawInput['name'] ?? $_POST['name'] ?? '');
    $eventName = csp_sanitize($rawInput['event_name'] ?? $rawInput['event_title'] ?? $_POST['event_name'] ?? $_POST['event_title'] ?? 'Next Live Discussion Event');
    $eventDate = csp_sanitize($rawInput['event_date'] ?? $_POST['event_date'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Valid email address is required."]);
        exit();
    }

    $displayName = !empty($name) ? $name : explode('@', $email)[0];

    try {
        $checkStmt = $pdo->prepare("SELECT id FROM event_reminders WHERE user_email = ? AND event_name = ? LIMIT 1");
        $checkStmt->execute([$email, $eventName]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("UPDATE event_reminders SET user_name = ?, event_date = ?, created_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$displayName, $eventDate, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO event_reminders (event_name, event_date, user_name, user_email) VALUES (?, ?, ?, ?)");
            $stmt->execute([$eventName, $eventDate, $displayName, $email]);
        }

        // Also save to subscribers
        try {
            $stmtSub = $pdo->prepare("INSERT OR REPLACE INTO subscribers (name, email, list_segment) VALUES (?, ?, 'live_discussion')");
            $stmtSub->execute([$displayName, $email]);
        } catch (Exception $e) {}

        $countStmt = $pdo->query("SELECT COUNT(*) as count FROM event_reminders");
        $totalRegistered = $countStmt->fetch()['count'];

        echo json_encode(["success" => true, "message" => "Registration successful! You are now registered for the live discussion.", "totalRegistered" => $totalRegistered]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// CHECK REGISTRATION STATUS FOR VISITOR (SYNC WITH DATABASE)
if ($action === 'check_registration_status') {
    $email = csp_sanitize($_GET['email'] ?? $_POST['email'] ?? '');
    $eventName = csp_sanitize($_GET['event_name'] ?? $_POST['event_name'] ?? '');
    if (empty($email)) {
        echo json_encode(["success" => true, "is_registered" => false]);
        exit();
    }
    try {
        if (!empty($eventName)) {
            $stmt = $pdo->prepare("SELECT id FROM event_reminders WHERE user_email = ? AND (event_name = ? OR event_name = '' OR event_name IS NULL) LIMIT 1");
            $stmt->execute([$email, $eventName]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM event_reminders WHERE user_email = ? LIMIT 1");
            $stmt->execute([$email]);
        }
        $found = (bool)$stmt->fetch();
        echo json_encode(["success" => true, "is_registered" => $found]);
    } catch (Exception $e) {
        echo json_encode(["success" => true, "is_registered" => false]);
    }
    exit();
}

// GET REGISTERED ATTENDEES (FOR DASHBOARD & STATS)
if ($action === 'get_event_reminders') {
    try {
        $stmt = $pdo->query("SELECT id, user_name, user_email, event_name, event_date, COALESCE(is_trash, 0) as is_trash, created_at FROM event_reminders ORDER BY id DESC LIMIT 500");
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $reminders = array_map(function($r) {
            return [
                'id' => (int)$r['id'],
                'name' => $r['user_name'] ?: 'Participant',
                'user_name' => $r['user_name'] ?: 'Participant',
                'email' => $r['user_email'] ?: '',
                'user_email' => $r['user_email'] ?: '',
                'event_title' => $r['event_name'] ?: 'Conspodium Live Discussion',
                'event_name' => $r['event_name'] ?: 'Conspodium Live Discussion',
                'event_date' => $r['event_date'] ?: '',
                'is_trash' => (int)$r['is_trash'],
                'created_at' => $r['created_at'] ?: ''
            ];
        }, $raw);
        $countActive = (int)$pdo->query("SELECT COUNT(*) as count FROM event_reminders WHERE COALESCE(is_trash, 0) = 0")->fetch()['count'];
        $countTrash = (int)$pdo->query("SELECT COUNT(*) as count FROM event_reminders WHERE COALESCE(is_trash, 0) = 1")->fetch()['count'];
        echo json_encode([
            "success" => true,
            "total" => count($reminders),
            "active_total" => $countActive,
            "trash_total" => $countTrash,
            "data" => $reminders,
            "reminders" => $reminders
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// MOVE SINGLE ATTENDEE TO TRASH
if ($action === 'trash_event_reminder') {
    $rawInput = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $id = (int)($rawInput['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Valid attendee ID is required."]);
        exit();
    }
    try {
        $stmt = $pdo->prepare("UPDATE event_reminders SET is_trash = 1 WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(["success" => true, "message" => "Attendee moved to Trash."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// RESTORE SINGLE ATTENDEE FROM TRASH
if ($action === 'restore_event_reminder') {
    $rawInput = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $id = (int)($rawInput['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Valid attendee ID is required."]);
        exit();
    }
    try {
        $stmt = $pdo->prepare("UPDATE event_reminders SET is_trash = 0 WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(["success" => true, "message" => "Attendee restored to active roster."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// PERMANENTLY DELETE SINGLE EVENT ATTENDEE
if ($action === 'delete_event_reminder') {
    $rawInput = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $id = (int)($rawInput['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Valid attendee ID is required."]);
        exit();
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM event_reminders WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(["success" => true, "message" => "Attendee permanently deleted."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// EMPTY ATTENDEES TRASH (PERMANENT DELETE ALL IN TRASH)
if ($action === 'empty_attendees_trash') {
    try {
        $pdo->exec("DELETE FROM event_reminders WHERE is_trash = 1");
        echo json_encode(["success" => true, "message" => "Attendees trash emptied successfully."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// BULK ACTIONS FOR ATTENDEES (TRASH, RESTORE, DELETE)
if ($action === 'bulk_attendees_action') {
    $rawInput = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $subAction = $rawInput['sub_action'] ?? '';
    $ids = $rawInput['ids'] ?? [];
    if (!is_array($ids) || empty($ids)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Please select at least one attendee."]);
        exit();
    }
    $cleanIds = array_map('intval', $ids);
    $inClause = implode(',', $cleanIds);
    try {
        if ($subAction === 'trash') {
            $pdo->exec("UPDATE event_reminders SET is_trash = 1 WHERE id IN ($inClause)");
            echo json_encode(["success" => true, "message" => count($cleanIds) . " attendee(s) moved to Trash."]);
        } elseif ($subAction === 'restore') {
            $pdo->exec("UPDATE event_reminders SET is_trash = 0 WHERE id IN ($inClause)");
            echo json_encode(["success" => true, "message" => count($cleanIds) . " attendee(s) restored."]);
        } elseif ($subAction === 'delete') {
            $pdo->exec("DELETE FROM event_reminders WHERE id IN ($inClause)");
            echo json_encode(["success" => true, "message" => count($cleanIds) . " attendee(s) permanently deleted."]);
        } else {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Invalid bulk sub-action."]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// CLEAR ALL EVENT ATTENDEES (LEGACY)
if ($action === 'clear_all_event_reminders') {
    try {
        $pdo->exec("DELETE FROM event_reminders");
        echo json_encode(["success" => true, "message" => "All registered attendees cleared successfully."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// SEND FOLLOW-UP EMAIL BROADCAST TO ATTENDEES
if ($action === 'send_attendees_email') {
    require_once __DIR__ . '/email_helper.php';
    $rawInput = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $subject = csp_sanitize($rawInput['subject'] ?? 'Update on Upcoming Live Discussion');
    $message = $rawInput['message'] ?? '';
    $recipientEmail = csp_sanitize($rawInput['recipient_email'] ?? '');
    $audienceMode = $rawInput['audience_mode'] ?? 'active';
    $selectedIds = $rawInput['selected_ids'] ?? [];

    if (empty($message)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Email message body is required."]);
        exit();
    }

    try {
        // Get active event details
        $stmtLive = $pdo->query("SELECT * FROM live_discussions WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $live = $stmtLive->fetch() ?: [
            'topic' => 'Conspodium Live Discussion',
            'zoom_link' => '',
            'discussion_date' => ''
        ];

        $attendees = [];
        if (!empty($recipientEmail) && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            // Single recipient mode
            $stmt = $pdo->prepare("SELECT * FROM event_reminders WHERE user_email = ? LIMIT 1");
            $stmt->execute([$recipientEmail]);
            $single = $stmt->fetch();
            if ($single) {
                $attendees = [$single];
            } else {
                $attendees = [['user_email' => $recipientEmail, 'user_name' => explode('@', $recipientEmail)[0], 'event_name' => $live['topic']]];
            }
        } elseif (!empty($selectedIds) && is_array($selectedIds)) {
            $inClause = implode(',', array_map('intval', $selectedIds));
            $stmt = $pdo->query("SELECT * FROM event_reminders WHERE id IN ($inClause)");
            $attendees = $stmt->fetchAll();
        } elseif ($audienceMode === 'active' && !empty($live['topic'])) {
            $stmt = $pdo->prepare("SELECT * FROM event_reminders WHERE event_name = ? ORDER BY id DESC");
            $stmt->execute([$live['topic']]);
            $attendees = $stmt->fetchAll();
            if (empty($attendees)) {
                $stmtAll = $pdo->query("SELECT * FROM event_reminders ORDER BY id DESC");
                $attendees = $stmtAll->fetchAll();
            }
        } else {
            $stmt = $pdo->query("SELECT * FROM event_reminders ORDER BY id DESC");
            $attendees = $stmt->fetchAll();
        }

        $provider = getEmailSetting($pdo, 'email_provider', 'smtp');
        $fromName = getEmailSetting($pdo, 'email_from_name', 'Conspodium Events');
        $fromAddress = getEmailSetting($pdo, 'email_from_address', 'events@conspodium.com');
        $smtpHost = getEmailSetting($pdo, 'email_smtp_host', 'smtp.hostinger.com');
        $smtpPort = getEmailSetting($pdo, 'email_smtp_port', '587');
        $smtpUser = getEmailSetting($pdo, 'email_smtp_user', '');
        $smtpPass = getEmailSetting($pdo, 'email_smtp_pass', '');

        $sentCount = 0;
        foreach ($attendees as $att) {
            $email = $att['user_email'] ?? $att['email'] ?? '';
            $name = $att['user_name'] ?? $att['name'] ?? 'Valued Attendee';
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

            $customBody = str_replace(
                ['{name}', '{event_name}', '{zoom_link}', '{event_date}'],
                [$name, $att['event_name'] ?: ($live['topic'] ?? 'Conspodium Live Discussion'), $live['zoom_link'] ?? '', $live['discussion_date'] ?? ''],
                $message
            );

            $sendSuccess = false;
            if (($provider === 'smtp' || $provider === 'hostinger') && !empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)) {
                $smtpRes = sendSmtpEmail($smtpHost, $smtpPort, $smtpUser, $smtpPass, $fromName, $fromAddress, $email, $subject, strip_tags($customBody), $customBody);
                if ($smtpRes['success']) $sendSuccess = true;
            }

            if (!$sendSuccess) {
                $headers = "From: $fromName <$fromAddress>\r\n" .
                    "Reply-To: $fromAddress\r\n" .
                    "MIME-Version: 1.0\r\n" .
                    "Content-Type: text/html; charset=UTF-8\r\n" .
                    "X-Mailer: Conspodium Native Mailer";
                @mail($email, $subject, $customBody, $headers);
                $sendSuccess = true;
            }

            if ($sendSuccess) {
                try {
                    $pdo->prepare("INSERT INTO email_logs (recipient_email, recipient_name, subject, body, type, provider, status) VALUES (?, ?, ?, ?, ?, ?, 'sent')")
                        ->execute([$email, $name, $subject, $customBody, 'attendee_broadcast', $provider]);
                } catch (Exception $e) {}
                $sentCount++;
            }
        }

        echo json_encode([
            "success" => true,
            "message" => "Follow-up email dispatched successfully to {$sentCount} attendee(s).",
            "sent_count" => $sentCount
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// GET SINGLE LIVE DISCUSSION EVENT
if ($method === 'GET' && $action === 'get_live_discussion') {
    try {
        $stmtLive = $pdo->query("SELECT * FROM live_discussions WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $liveDiscussion = $stmtLive->fetch();
        if ($liveDiscussion) {
            echo json_encode(["success" => true, "data" => $liveDiscussion, "has_active" => true]);
        } else {
            echo json_encode(["success" => true, "data" => null, "has_active" => false]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// GET ALL LIVE DISCUSSIONS HISTORY
if ($method === 'GET' && $action === 'get_live_discussions_history') {
    try {
        $stmtHistory = $pdo->query("
            SELECT ld.*, 
                   (SELECT COUNT(*) FROM event_reminders er WHERE er.event_name LIKE '%' || ld.topic || '%' OR er.event_date = ld.discussion_date) as attendee_count
            FROM live_discussions ld 
            ORDER BY ld.id DESC
        ");
        $history = $stmtHistory->fetchAll();
        echo json_encode(["success" => true, "data" => $history]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// GET FEATURED INTERVIEW
if ($method === 'GET' && $action === 'get_featured_interview') {
    try {
        $stmtInt = $pdo->query("SELECT * FROM featured_interviews WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $featuredInterview = $stmtInt->fetch();
        if (!$featuredInterview) {
            $featuredInterview = [
                'title' => 'In Conversation With',
                'interviewee_name' => 'Featured Diaspora Scholar',
                'interviewee_role' => 'Global African Studies & Research',
                'quote' => '"Empowering communities through critical dialogue and scholarship"',
                'photo' => '/wp-content/uploads/2026/01/African-Diasporans-1536x864-1.jpg',
                'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ'
            ];
        }
        echo json_encode(["success" => true, "data" => $featuredInterview]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// GET ALL HOMEPAGE SECTIONS & DYNAMIC DATA
if ($method === 'GET' && ($action === 'get_all' || $action === 'frontend')) {
    try {
        // 1. Saved homepage sections (featured_interview, etc.)
        $stmt = $pdo->query("SELECT key, value_json FROM homepage_sections");
        $sections = [];
        while ($row = $stmt->fetch()) {
            $sections[$row['key']] = json_decode($row['value_json'], true);
        }

        // 2. Scholar Spotlights
        $stmtScholars = $pdo->query("SELECT * FROM scholar_spotlights WHERE is_active = 1 ORDER BY display_order ASC, id DESC");
        $scholars = $stmtScholars->fetchAll();
        if (empty($scholars)) {
            $scholars = [
                [
                    'id' => 1,
                    'scholar_name' => 'Prof. Amara Diallo',
                    'title_affiliation' => 'London School of Economics',
                    'bio' => 'Pioneering democratic reforms and economic governance across West Africa.',
                    'image_url' => '/uploads/live_speaker_avatar.png',
                    'research_field' => 'Governance & Democracy',
                    'profile_link' => 'stories/'
                ],
                [
                    'id' => 2,
                    'scholar_name' => 'Dr. Kemi Adebayo',
                    'title_affiliation' => 'Senior Research Fellow, Oxford',
                    'bio' => 'Leading research on diaspora economic impact and sustainable investments.',
                    'image_url' => '/uploads/author_avatar_kemi.png',
                    'research_field' => 'Economics & Heritage',
                    'profile_link' => 'stories/'
                ],
                [
                    'id' => 3,
                    'scholar_name' => 'Dr. Marcus Vance',
                    'title_affiliation' => 'Harvard Innovation Lab',
                    'bio' => 'Fostering tech ecosystems and venture investment in African startups.',
                    'image_url' => '/uploads/author_avatar_marcus.png',
                    'research_field' => 'Innovation & Tech',
                    'profile_link' => 'stories/'
                ]
            ];
        }

        // 3. Featured Stories ("Voices That Inspire")
        $featuredStoryIds = $sections['featured_stories_ids'] ?? [];
        if (!empty($featuredStoryIds)) {
            $inClause = implode(',', array_map('intval', $featuredStoryIds));
            $stmtFeat = $pdo->query("SELECT p.*, c.name as category_name, c.slug as category_slug FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id IN ($inClause)");
            $rawFeat = $stmtFeat->fetchAll();
            $postsById = [];
            foreach ($rawFeat as $p) { $postsById[$p['id']] = $p; }
            $featuredStories = [];
            foreach ($featuredStoryIds as $id) {
                if (isset($postsById[$id])) { $featuredStories[] = $postsById[$id]; }
            }
        } else {
            $stmtFeat = $pdo->query("SELECT p.*, c.name as category_name, c.slug as category_slug FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_featured = 1 ORDER BY p.id DESC LIMIT 4");
            $featuredStories = $stmtFeat->fetchAll();
            if (empty($featuredStories)) {
                $stmtFeat = $pdo->query("SELECT p.*, c.name as category_name, c.slug as category_slug FROM posts p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC LIMIT 4");
                $featuredStories = $stmtFeat->fetchAll();
            }
        }
        if (empty($featuredStories)) {
            $featuredStories = [
                [
                    'id' => 1,
                    'title' => 'Voices That Inspire: African Leaders in Global Tech',
                    'slug' => 'voices-that-inspire-african-leaders-in-global-tech',
                    'excerpt' => 'Exploring how African innovators and scholars in the diaspora are revolutionizing technology and economic policy.',
                    'category_name' => 'Innovation',
                    'category_slug' => 'innovation',
                    'featured_image' => '/uploads/cat_diaspora_matters.png',
                    'author_name' => 'Conspodium Editorial',
                    'views' => 1842
                ],
                [
                    'id' => 2,
                    'title' => 'Preserving Cultural Heritage in Digital Spaces',
                    'slug' => 'preserving-cultural-heritage-in-digital-spaces',
                    'excerpt' => 'How digital archiving and community initiatives are safeguarding traditions for upcoming generations.',
                    'category_name' => 'Community',
                    'category_slug' => 'community',
                    'featured_image' => '/uploads/cat_diaspora_insights.png',
                    'author_name' => 'Dr. Kemi Adebayo',
                    'views' => 1420
                ],
                [
                    'id' => 3,
                    'title' => 'Economic Resilience: Diaspora Remittances & Investment',
                    'slug' => 'economic-resilience-diaspora-remittances-investment',
                    'excerpt' => 'Analyzing the structural impact of diaspora capital flows on local infrastructure and education.',
                    'category_name' => 'African Diaspora Matters',
                    'category_slug' => 'african-diaspora-matters',
                    'featured_image' => '/uploads/cat_diaspora_matters.png',
                    'author_name' => 'Prof. Kwame Mensah',
                    'views' => 2105
                ],
                [
                    'id' => 4,
                    'title' => 'African Scholarship & Contemporary Thought',
                    'slug' => 'african-scholarship-contemporary-thought',
                    'excerpt' => 'High-level dialogue on shaping policy, ethics, and global academic frameworks.',
                    'category_name' => 'Scholars Spotlight',
                    'category_slug' => 'scholars-spotlight',
                    'featured_image' => '/uploads/editorial_writer_hero.png',
                    'author_name' => 'Editorial Team',
                    'views' => 980
                ]
            ];
        }

        // 4. Trending Now
        $trendingIds = $sections['trending_ids'] ?? [];
        if (!empty($trendingIds)) {
            $inClause = implode(',', array_map('intval', $trendingIds));
            $stmtTrend = $pdo->query("SELECT p.*, c.name as category_name, c.slug as category_slug FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id IN ($inClause)");
            $rawTrend = $stmtTrend->fetchAll();
            $trendById = [];
            foreach ($rawTrend as $p) { $trendById[$p['id']] = $p; }
            $trendingPosts = [];
            foreach ($trendingIds as $id) {
                if (isset($trendById[$id])) { $trendingPosts[] = $trendById[$id]; }
            }
        } else {
            $stmtTrend = $pdo->query("SELECT p.*, c.name as category_name, c.slug as category_slug FROM posts p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.views DESC LIMIT 6");
            $trendingPosts = $stmtTrend->fetchAll();
        }
        if (empty($trendingPosts)) {
            $trendingPosts = [
                ['id' => 1, 'title' => 'Voices That Inspire in Tech', 'slug' => 'voices-that-inspire-african-leaders-in-global-tech', 'views' => 1842],
                ['id' => 2, 'title' => 'Preserving Cultural Heritage', 'slug' => 'preserving-cultural-heritage-in-digital-spaces', 'views' => 1420],
                ['id' => 3, 'title' => 'Diaspora Remittances & Investment', 'slug' => 'economic-resilience-diaspora-remittances-investment', 'views' => 2105],
                ['id' => 4, 'title' => 'African Scholarship Today', 'slug' => 'african-scholarship-contemporary-thought', 'views' => 980]
            ];
        }

        // 5. Next Live Discussion
        $stmtLive = $pdo->query("SELECT * FROM live_discussions WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $liveDiscussion = $stmtLive->fetch();
        if (!$liveDiscussion) {
            $liveDiscussion = [
                'topic' => 'The Future of African Democracy',
                'speaker_name' => 'Prof. Amara Diallo & Panel',
                'speaker_role' => 'London School of Economics',
                'speaker_avatar' => '/uploads/live_speaker_avatar.png',
                'discussion_date' => date('Y-m-d H:i:s', strtotime('+5 days 18:00:00')),
                'zoom_link' => 'https://zoom.us/j/conspodium-live',
                'ics_summary' => 'Conspodium Next Live Discussion: The Future of African Democracy'
            ];
        }

        // 6. Selected Homepage Categories
        $selectedCatIds = $sections['homepage_category_ids'] ?? [];
        if (!empty($selectedCatIds)) {
            $inClause = implode(',', array_map('intval', $selectedCatIds));
            $stmtCats = $pdo->query("SELECT c.*, COUNT(p.id) as post_count FROM categories c LEFT JOIN posts p ON c.id = p.category_id WHERE c.id IN ($inClause) GROUP BY c.id");
            $rawCats = $stmtCats->fetchAll();
            $catsById = [];
            foreach ($rawCats as $cat) { $catsById[$cat['id']] = $cat; }
            $homepageCategories = [];
            foreach ($selectedCatIds as $cid) {
                if (isset($catsById[$cid])) {
                    $homepageCategories[] = $catsById[$cid];
                }
            }
        } else {
            $stmtCats = $pdo->query("SELECT c.*, COUNT(p.id) as post_count FROM categories c LEFT JOIN posts p ON c.id = p.category_id GROUP BY c.id ORDER BY c.display_order ASC, c.id ASC");
            $homepageCategories = $stmtCats->fetchAll();
        }
        if (empty($homepageCategories)) {
            $homepageCategories = [
                [
                    'id' => 1,
                    'name' => 'Innovation',
                    'slug' => 'innovation',
                    'description' => 'Tech, entrepreneurship, and digital transformation across Africa and the diaspora.',
                    'image' => '/uploads/cat_diaspora_matters.png',
                    'post_count' => 12
                ],
                [
                    'id' => 2,
                    'name' => 'Community',
                    'slug' => 'community',
                    'description' => 'Spotlighting grassroots efforts, civic engagement, and social cohesion.',
                    'image' => '/uploads/cat_diaspora_insights.png',
                    'post_count' => 8
                ],
                [
                    'id' => 3,
                    'name' => 'African Diaspora Matters',
                    'slug' => 'african-diaspora-matters',
                    'description' => 'Empowering global African voices, policy discussions, and cultural exchanges.',
                    'image' => '/uploads/cat_diaspora_matters.png',
                    'post_count' => 15
                ]
            ];
        }

        // 7. Featured Interview
        $stmtInt = $pdo->query("SELECT * FROM featured_interviews WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
        $featuredInterview = $stmtInt->fetch();
        if (!$featuredInterview) {
            $featuredInterview = [
                'title' => 'In Conversation With',
                'interviewee_name' => 'Featured Diaspora Scholar',
                'interviewee_role' => 'Global African Studies & Research',
                'quote' => '"Empowering communities through critical dialogue and scholarship"',
                'photo' => '/wp-content/uploads/2026/01/African-Diasporans-1536x864-1.jpg',
                'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ'
            ];
        }

        // 8. Living Archive (Dynamic + Admin Overrides)
        $laMode = $sections['living_archive_mode'] ?? 'auto';
        $livingArchive = ($laMode === 'custom' && !empty($sections['living_archive'])) ? $sections['living_archive'] : null;

        if (empty($livingArchive) || !is_array($livingArchive)) {
            $topPost = $trendingPosts[0] ?? null;
            $latestPost = $featuredStories[0] ?? null;
            $mostReadPost = $trendingPosts[1] ?? $topPost;
            $historicPost = $featuredStories[1] ?? $latestPost;
            $scholarItem = $scholars[0] ?? null;

            $livingArchive = [
                "present" => [
                    [
                        "badge" => "Trending Now",
                        "title" => $topPost['title'] ?? "The Afrobeat Blueprint: What Cinema Can Learn from Music",
                        "sub" => isset($topPost['views']) ? (number_format($topPost['views']) . " views · Active debate") : "Active debate — 340 comments this week",
                        "url" => isset($topPost['slug']) ? ("/post/" . $topPost['slug'] . "/") : "/stories/"
                    ],
                    [
                        "badge" => "Latest Publication",
                        "title" => $latestPost['title'] ?? "Pan-African Innovation & Heritage",
                        "sub" => isset($latestPost['author_name']) ? ("By " . $latestPost['author_name'] . " · Latest Editorial") : "Published recently · Most shared essay of the month",
                        "url" => isset($latestPost['slug']) ? ("/post/" . $latestPost['slug'] . "/") : "/stories/"
                    ],
                    [
                        "badge" => "Live Discussion",
                        "title" => $liveDiscussion['topic'] ?? "Reader Roundtable: The Future of African Democracy",
                        "sub" => "Speaker: " . ($liveDiscussion['speaker_name'] ?? 'Prof. Amara Diallo') . " · Open thread running live",
                        "url" => "#csp-countdown"
                    ]
                ],
                "past" => [
                    [
                        "badge" => "Most Read",
                        "title" => $mostReadPost['title'] ?? "Beyond Diplomas: Rethinking What Leadership Requires",
                        "sub" => isset($mostReadPost['views']) ? (number_format($mostReadPost['views']) . " reads · High-impact landmark essay") : "Highest-performing essay, drawing new readers weekly",
                        "url" => isset($mostReadPost['slug']) ? ("/post/" . $mostReadPost['slug'] . "/") : "/stories/"
                    ],
                    [
                        "badge" => "Popular Interview",
                        "title" => ($featuredInterview['interviewee_name'] ?? 'Prof. Amara Diallo') . " — " . ($featuredInterview['quote'] ?? 'Building Bridges Across Nations'),
                        "sub" => ($featuredInterview['interviewee_role'] ?? 'Cultural Historian') . " · Featured Scholar Dialogues",
                        "url" => "#csp-featured-interview"
                    ],
                    [
                        "badge" => "Historic Archive",
                        "title" => $historicPost['title'] ?? "The Conspodium Archive: Nico Williams and the Diaspora Journey",
                        "sub" => "Historic Diaspora Archive · Key takeaways and analysis",
                        "url" => isset($historicPost['slug']) ? ("/post/" . $historicPost['slug'] . "/") : "/stories/"
                    ]
                ],
                "upcoming" => [
                    [
                        "badge" => "Future Scholar",
                        "title" => ($scholarItem['scholar_name'] ?? 'Prof. Amara Diallo Jr') . " — " . ($scholarItem['research_field'] ?? 'Governance & Heritage'),
                        "sub" => ($scholarItem['title_affiliation'] ?? 'American School of Economics') . " · Upcoming Spotlight",
                        "url" => "/stories/"
                    ],
                    [
                        "badge" => "Scheduled Event",
                        "title" => "Live Discussion: " . ($liveDiscussion['topic'] ?? 'The Future of African Democracy'),
                        "sub" => "Event Date: " . ($liveDiscussion['discussion_date'] ?? 'Coming Soon') . " · Register to attend",
                        "url" => "#csp-countdown"
                    ],
                    [
                        "badge" => "Call for Stories",
                        "title" => "Community Story Submissions Open for Editorial Review",
                        "sub" => "Submit your article, essay, or research to Conspodium Desk",
                        "url" => "/submit-story/"
                    ]
                ]
            ];
        }

        ob_clean();
        echo json_encode([
            "success" => true,
            "data" => [
                "sections" => $sections,
                "scholars" => $scholars,
                "featured_stories" => $featuredStories,
                "trending_posts" => $trendingPosts,
                "live_discussion" => $liveDiscussion,
                "featured_interview" => $featuredInterview,
                "homepage_categories" => $homepageCategories,
                "living_archive" => $livingArchive
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// ADMIN ACTIONS
if ($method === 'POST') {
    require_once __DIR__ . '/auth_guard.php';

    $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;

    // SCHOLAR SPOTLIGHT REORDER & CRUD
    if ($action === 'reorder_scholars') {
        $order = $input['order'] ?? [];
        if (is_array($order) && count($order) > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE scholar_spotlights SET display_order = ? WHERE id = ?");
                foreach ($order as $index => $sId) {
                    $stmt->execute([$index + 1, intval($sId)]);
                }
                echo json_encode(["success" => true, "message" => "Scholar spotlight order updated successfully!"]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(["success" => false, "error" => $e->getMessage()]);
            }
            exit();
        }
    }

    if ($action === 'save_scholar') {
        $id = intval($input['id'] ?? 0);
        $scholarName = csp_sanitize($input['scholar_name'] ?? '');
        $titleAffiliation = csp_sanitize($input['title_affiliation'] ?? '');
        $bio = csp_sanitize($input['bio'] ?? '');
        $imageUrl = csp_sanitize($input['image_url'] ?? '');
        $researchField = csp_sanitize($input['research_field'] ?? '');
        $profileLink = csp_sanitize($input['profile_link'] ?? '#');

        if (empty($scholarName) || empty($bio)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Scholar Name and Bio are required."]);
            exit();
        }

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE scholar_spotlights SET scholar_name = ?, title_affiliation = ?, bio = ?, image_url = ?, research_field = ?, profile_link = ? WHERE id = ?");
                $stmt->execute([$scholarName, $titleAffiliation, $bio, $imageUrl, $researchField, $profileLink, $id]);
                echo json_encode(["success" => true, "message" => "Scholar Spotlight updated successfully!"]);
            } else {
                $count = (int)$pdo->query("SELECT COUNT(*) FROM scholar_spotlights")->fetchColumn();
                if ($count >= 3) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "error" => "Maximum limit of 3 Scholar Cards reached. Please edit or delete an existing card."]);
                    exit();
                }
                $stmt = $pdo->prepare("INSERT INTO scholar_spotlights (scholar_name, title_affiliation, bio, image_url, research_field, profile_link) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$scholarName, $titleAffiliation, $bio, $imageUrl, $researchField, $profileLink]);
                echo json_encode(["success" => true, "message" => "New Scholar Spotlight card created successfully!", "id" => $pdo->lastInsertId()]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit();
    }

    if ($action === 'delete_scholar') {
        $id = intval($input['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Valid Scholar ID is required."]);
            exit();
        }

        try {
            $pdo->prepare("DELETE FROM scholar_spotlights WHERE id = ?")->execute([$id]);
            echo json_encode(["success" => true, "message" => "Scholar Spotlight card deleted successfully."]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit();
    }

    // NEXT LIVE DISCUSSION SAVE
    if ($action === 'save_live_discussion') {
        $topic = csp_sanitize($input['topic'] ?? '');
        $speakerName = csp_sanitize($input['speaker_name'] ?? '');
        $speakerRole = csp_sanitize($input['speaker_role'] ?? '');
        $speakerAvatar = csp_sanitize($input['speaker_avatar'] ?? '');
        $discussionDate = csp_sanitize($input['discussion_date'] ?? '');
        $zoomLink = csp_sanitize($input['zoom_link'] ?? '');
        $icsSummary = csp_sanitize($input['ics_summary'] ?? '');

        if (empty($topic) || empty($speakerName) || empty($discussionDate)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Topic, Speaker Name, and Event Date are required."]);
            exit();
        }

        try {
            $pdo->query("UPDATE live_discussions SET is_active = 0");
            $stmt = $pdo->prepare("INSERT INTO live_discussions (topic, speaker_name, speaker_role, speaker_avatar, discussion_date, zoom_link, ics_summary, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$topic, $speakerName, $speakerRole, $speakerAvatar, $discussionDate, $zoomLink, $icsSummary]);

            echo json_encode(["success" => true, "message" => "Next Live Discussion settings updated successfully!"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit();
    }

    // CANCEL / CONCLUDE LIVE DISCUSSION
    if ($action === 'cancel_live_discussion') {
        try {
            $pdo->query("UPDATE live_discussions SET is_active = 0");
            echo json_encode(["success" => true, "message" => "Active live discussion has been concluded / archived."]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit();
    }

    // FEATURED INTERVIEW SAVE
    if ($action === 'save_featured_interview') {
        $title = csp_sanitize($input['title'] ?? 'In Conversation With');
        $name = csp_sanitize($input['interviewee_name'] ?? '');
        $role = csp_sanitize($input['interviewee_role'] ?? '');
        $quote = csp_sanitize($input['quote'] ?? '');
        $photo = csp_sanitize($input['photo'] ?? '');
        $videoUrl = csp_sanitize($input['video_url'] ?? '');

        if (empty($name) || empty($quote)) {
            http_response_code(400);
            echo json_encode(["success" => false, "error" => "Interviewee Name and Quote are required."]);
            exit();
        }

        try {
            $pdo->query("UPDATE featured_interviews SET is_active = 0");
            $stmt = $pdo->prepare("INSERT INTO featured_interviews (title, interviewee_name, interviewee_role, quote, photo, video_url, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$title, $name, $role, $quote, $photo, $videoUrl]);

            echo json_encode(["success" => true, "message" => "Featured Interview settings updated successfully!"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit();
    }

    // SAVE FEATURED / TRENDING / HOMEPAGE CATEGORY SELECTIONS
    $key = csp_sanitize($input['key'] ?? '');
    $data = $input['data'] ?? null;

    if (!empty($key) && $data !== null) {
        try {
            // Try update first, then insert if not found
            $stmtUpd = $pdo->prepare("UPDATE homepage_sections SET value_json = ?, updated_at = CURRENT_TIMESTAMP WHERE \"key\" = ?");
            $stmtUpd->execute([json_encode($data), $key]);
            if ($stmtUpd->rowCount() === 0) {
                $stmtIns = $pdo->prepare("INSERT INTO homepage_sections (\"key\", value_json, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
                $stmtIns->execute([$key, json_encode($data)]);
            }

            echo json_encode(["success" => true, "message" => "Homepage settings saved successfully."]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit();
    }
}
