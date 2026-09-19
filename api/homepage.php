<?php
// api/homepage.php - Dynamic Homepage Content & Layout API
error_reporting(0);
ini_set('display_errors', '0');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token");
header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_all';

// PUBLIC EVENT REGISTRATION (SAVED TO DATABASE event_reminders & subscribers)
if ($action === 'register_event') {
    $rawInput = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $email = csp_sanitize($rawInput['email'] ?? $_POST['email'] ?? '');
    $name = csp_sanitize($rawInput['name'] ?? $_POST['name'] ?? '');
    $eventName = csp_sanitize($rawInput['event_name'] ?? $_POST['event_name'] ?? 'Next Live Discussion Event');
    $eventDate = csp_sanitize($rawInput['event_date'] ?? $_POST['event_date'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Valid email address is required."]);
        exit();
    }

    $displayName = !empty($name) ? $name : explode('@', $email)[0];

    try {
        $stmt = $pdo->prepare("INSERT INTO event_reminders (event_name, event_date, user_name, user_email) VALUES (?, ?, ?, ?)");
        $stmt->execute([$eventName, $eventDate, $displayName, $email]);

        // Also save to subscribers
        $stmtSub = $pdo->prepare("INSERT INTO subscribers (name, email, list_segment) VALUES (?, ?, 'live_discussion') ON CONFLICT(email) DO UPDATE SET name = excluded.name, list_segment = 'live_discussion'");
        $stmtSub->execute([$displayName, $email]);

        $countStmt = $pdo->query("SELECT COUNT(*) as count FROM event_reminders");
        $totalRegistered = $countStmt->fetch()['count'];

        echo json_encode(["success" => true, "message" => "Registration successful! You are now registered for the live discussion.", "totalRegistered" => $totalRegistered]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// GET REGISTERED ATTENDEES (FOR DASHBOARD & STATS)
if ($method === 'GET' && $action === 'get_event_reminders') {
    try {
        $stmt = $pdo->query("SELECT * FROM event_reminders ORDER BY id DESC LIMIT 50");
        $reminders = $stmt->fetchAll();
        $countStmt = $pdo->query("SELECT COUNT(*) as count FROM event_reminders");
        $total = $countStmt->fetch()['count'];
        echo json_encode(["success" => true, "total" => $total, "reminders" => $reminders]);
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
        if (!$liveDiscussion) {
            $liveDiscussion = [
                'topic' => 'The Future of African Democracy',
                'speaker_name' => 'Prof. Amara Diallo & Panel',
                'speaker_role' => 'London School of Economics',
                'speaker_avatar' => '/uploads/upload_1789742036_15826c02.jpg',
                'discussion_date' => date('Y-m-d H:i:s', strtotime('+5 days 18:00:00')),
                'zoom_link' => 'https://zoom.us/j/conspodium-live',
                'ics_summary' => 'Conspodium Next Live Discussion: The Future of African Democracy'
            ];
        }
        echo json_encode(["success" => true, "data" => $liveDiscussion]);
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
        if (empty($scholars) && isset($sections['scholar_spotlight'])) {
            $scholars = [[
                'id' => 1,
                'scholar_name' => $sections['scholar_spotlight']['scholar_name'] ?? 'Dr. Kemi Adebayo',
                'title_affiliation' => $sections['scholar_spotlight']['scholar_title'] ?? 'Senior Research Fellow',
                'bio' => $sections['scholar_spotlight']['bio'] ?? 'Leading research on diaspora economic impact.',
                'image_url' => $sections['scholar_spotlight']['photo'] ?? './wp-content/uploads/2026/01/African-Diasporans-1536x864-1.jpg',
                'research_field' => 'Economics & Heritage',
                'profile_link' => '#'
            ]];
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
            $stmtCats = $pdo->query("SELECT c.*, COUNT(p.id) as post_count FROM categories c LEFT JOIN posts p ON c.id = p.category_id GROUP BY c.id ORDER BY c.display_order ASC, c.id ASC LIMIT 6");
            $homepageCategories = $stmtCats->fetchAll();
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

        echo json_encode([
            "success" => true,
            "data" => [
                "sections" => $sections,
                "scholars" => $scholars,
                "featured_stories" => $featuredStories,
                "trending_posts" => $trendingPosts,
                "live_discussion" => $liveDiscussion,
                "featured_interview" => $featuredInterview,
                "homepage_categories" => $homepageCategories
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

    // SCHOLAR SPOTLIGHT CRUD
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
            $stmt = $pdo->prepare("INSERT INTO homepage_sections (key, value_json, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON CONFLICT(key) DO UPDATE SET value_json = excluded.value_json, updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$key, json_encode($data)]);

            echo json_encode(["success" => true, "message" => "Homepage settings saved successfully."]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        exit();
    }
}
