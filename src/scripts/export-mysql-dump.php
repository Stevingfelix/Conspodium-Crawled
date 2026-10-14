<?php
// src/scripts/export-mysql-dump.php
// Generates a clean, fully escaped MySQL dump from data/conspodium.db

$sqlitePath = __DIR__ . '/../../data/conspodium.db';
if (!file_exists($sqlitePath)) {
    die("SQLite database not found at: $sqlitePath\n");
}

$sqlite = new PDO("sqlite:" . $sqlitePath);
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$tablesStmt = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
$tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

$output = [];
$output[] = "-- Conspodium MySQL Database Dump";
$output[] = "-- Generated on " . date('Y-m-d H:i:s');
$output[] = "SET FOREIGN_KEY_CHECKS = 0;";
$output[] = "SET NAMES utf8mb4;";
$output[] = "";

// Table Schemas for MySQL
$schemas = [
    'categories' => "CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(191) NOT NULL UNIQUE,
  `slug` VARCHAR(191) NOT NULL UNIQUE,
  `icon` VARCHAR(50),
  `description` TEXT,
  `image` TEXT,
  `display_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'posts' => "CREATE TABLE IF NOT EXISTS `posts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(191) NOT NULL UNIQUE,
  `eyebrow` VARCHAR(191),
  `excerpt` TEXT,
  `content` LONGTEXT NOT NULL,
  `category_id` INT,
  `author_name` VARCHAR(191),
  `author_avatar` VARCHAR(191),
  `featured_image` TEXT,
  `reading_time` VARCHAR(50) DEFAULT '5 min read',
  `views` INT DEFAULT 0,
  `likes` INT DEFAULT 0,
  `is_featured` INT DEFAULT 0,
  `is_spotlight` INT DEFAULT 0,
  `status` VARCHAR(50) DEFAULT 'published',
  `published_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_posts_cat` (`category_id`),
  INDEX `idx_posts_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'transcripts' => "CREATE TABLE IF NOT EXISTS `transcripts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `post_id` INT NOT NULL UNIQUE,
  `speaker_name` VARCHAR(191),
  `speaker_title` VARCHAR(191),
  `audio_duration` VARCHAR(50),
  `transcript_content` LONGTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'polls' => "CREATE TABLE IF NOT EXISTS `polls` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `question` TEXT NOT NULL,
  `options_json` LONGTEXT NOT NULL,
  `is_active` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'poll_votes' => "CREATE TABLE IF NOT EXISTS `poll_votes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `poll_id` INT NOT NULL,
  `option_index` INT NOT NULL,
  `voter_ip` VARCHAR(100) NOT NULL,
  `voted_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'story_submissions' => "CREATE TABLE IF NOT EXISTS `story_submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `author_name` VARCHAR(191) NOT NULL,
  `author_email` VARCHAR(191) NOT NULL,
  `author_bio` TEXT,
  `category` VARCHAR(191),
  `title` VARCHAR(255) NOT NULL,
  `content` LONGTEXT NOT NULL,
  `attachment_url` TEXT,
  `status` VARCHAR(50) DEFAULT 'pending',
  `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'comments' => "CREATE TABLE IF NOT EXISTS `comments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `post_id` INT NOT NULL,
  `author_name` VARCHAR(191) NOT NULL,
  `author_email` VARCHAR(191) NOT NULL,
  `content` TEXT NOT NULL,
  `status` VARCHAR(50) DEFAULT 'approved',
  `parent_id` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'contact_messages' => "CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(191),
  `last_name` VARCHAR(191),
  `email` VARCHAR(191) NOT NULL,
  `subject` VARCHAR(255),
  `message` TEXT NOT NULL,
  `status` VARCHAR(50) DEFAULT 'unread',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'contact_replies' => "CREATE TABLE IF NOT EXISTS `contact_replies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `contact_id` INT NOT NULL,
  `subject` VARCHAR(255),
  `body` LONGTEXT,
  `sent_by` VARCHAR(191) DEFAULT 'Editor Admin',
  `provider` VARCHAR(50) DEFAULT 'smtp',
  `status` VARCHAR(50) DEFAULT 'sent',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'event_reminders' => "CREATE TABLE IF NOT EXISTS `event_reminders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_name` VARCHAR(255) NOT NULL,
  `event_date` VARCHAR(100) NOT NULL,
  `user_name` VARCHAR(191),
  `user_email` VARCHAR(191) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'admins' => "CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(191) NOT NULL UNIQUE,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `role` VARCHAR(50) DEFAULT 'admin',
  `session_token` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'admin_security_logs' => "CREATE TABLE IF NOT EXISTS `admin_security_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NULL,
  `admin_username` VARCHAR(191) NULL,
  `admin_email` VARCHAR(191) NULL,
  `ip_address` VARCHAR(100) NULL,
  `location` VARCHAR(191) NULL,
  `user_agent` TEXT NULL,
  `browser_device` VARCHAR(191) NULL,
  `status` VARCHAR(100) DEFAULT 'Active Session',
  `session_token` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'site_settings' => "CREATE TABLE IF NOT EXISTS `site_settings` (
  `key` VARCHAR(191) PRIMARY KEY,
  `value` LONGTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'settings' => "CREATE TABLE IF NOT EXISTS `settings` (
  `key` VARCHAR(191) PRIMARY KEY,
  `value` LONGTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'rate_limits' => "CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `action_key` VARCHAR(191) NOT NULL,
  `ip_address` VARCHAR(100) NOT NULL,
  `request_count` INT DEFAULT 1,
  `last_request` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'subscribers' => "CREATE TABLE IF NOT EXISTS `subscribers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(191),
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `list_segment` VARCHAR(191) DEFAULT 'community',
  `status` VARCHAR(50) DEFAULT 'active',
  `subscribed_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'email_campaigns' => "CREATE TABLE IF NOT EXISTS `email_campaigns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject` VARCHAR(255) NOT NULL,
  `target_list` VARCHAR(191) DEFAULT 'all',
  `sender_name` VARCHAR(191) DEFAULT 'Conspodium Editorial',
  `sender_email` VARCHAR(191) DEFAULT 'newsletter@conspodium.com',
  `content` LONGTEXT NOT NULL,
  `status` VARCHAR(50) DEFAULT 'draft',
  `scheduled_at` DATETIME,
  `sent_at` DATETIME,
  `recipients_count` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'email_logs' => "CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `campaign_id` INT DEFAULT 0,
  `idempotency_key` VARCHAR(191) NULL,
  `recipient_email` VARCHAR(191) NOT NULL,
  `recipient_name` VARCHAR(191) NULL,
  `subject` VARCHAR(255) NULL,
  `body` LONGTEXT NULL,
  `type` VARCHAR(50) DEFAULT 'general',
  `provider` VARCHAR(50) DEFAULT 'smtp',
  `status` VARCHAR(50) DEFAULT 'sent',
  `delivery_details` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'email_templates' => "CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_key` VARCHAR(191) NOT NULL UNIQUE,
  `name` VARCHAR(191) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` LONGTEXT NOT NULL,
  `is_active` INT DEFAULT 1,
  `variables_hint` TEXT,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'homepage_sections' => "CREATE TABLE IF NOT EXISTS `homepage_sections` (
  `key` VARCHAR(191) PRIMARY KEY,
  `value_json` LONGTEXT NOT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'payment_settings' => "CREATE TABLE IF NOT EXISTS `payment_settings` (
  `key` VARCHAR(191) PRIMARY KEY,
  `value` LONGTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'payment_transactions' => "CREATE TABLE IF NOT EXISTS `payment_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_ref` VARCHAR(191) NOT NULL UNIQUE,
  `gateway` VARCHAR(50) NOT NULL,
  `amount` DOUBLE NOT NULL,
  `currency` VARCHAR(10) DEFAULT 'USD',
  `customer_email` VARCHAR(191) NOT NULL,
  `customer_name` VARCHAR(191),
  `tier_name` VARCHAR(191) NOT NULL,
  `status` VARCHAR(50) DEFAULT 'success',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'forum_categories' => "CREATE TABLE IF NOT EXISTS `forum_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(191) NOT NULL UNIQUE,
  `slug` VARCHAR(191) NOT NULL UNIQUE,
  `description` TEXT,
  `icon` VARCHAR(50) DEFAULT 'globe',
  `icon_type` VARCHAR(50) DEFAULT 'globe',
  `color_accent` VARCHAR(50) DEFAULT '#00AEFE',
  `display_order` INT DEFAULT 0,
  `is_active` INT DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'forum_threads' => "CREATE TABLE IF NOT EXISTS `forum_threads` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(191) NOT NULL UNIQUE,
  `category` VARCHAR(191) DEFAULT 'General Discussion',
  `author_name` VARCHAR(191) NOT NULL,
  `author_email` VARCHAR(191) NOT NULL,
  `content` LONGTEXT NOT NULL,
  `replies_count` INT DEFAULT 0,
  `views` INT DEFAULT 0,
  `is_pinned` INT DEFAULT 0,
  `status` VARCHAR(50) DEFAULT 'approved',
  `upvotes` INT DEFAULT 0,
  `downvotes` INT DEFAULT 0,
  `author_location` VARCHAR(191) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'forum_replies' => "CREATE TABLE IF NOT EXISTS `forum_replies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `thread_id` INT NOT NULL,
  `parent_id` INT DEFAULT NULL,
  `author_name` VARCHAR(191) NOT NULL,
  `author_email` VARCHAR(191) NOT NULL,
  `content` LONGTEXT NOT NULL,
  `status` VARCHAR(50) DEFAULT 'approved',
  `upvotes` INT DEFAULT 0,
  `downvotes` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'post_likes' => "CREATE TABLE IF NOT EXISTS `post_likes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `post_id` INT NOT NULL,
  `voter_ip` VARCHAR(100) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_post_likes` (`post_id`, `voter_ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'forum_votes' => "CREATE TABLE IF NOT EXISTS `forum_votes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `target_type` VARCHAR(50) NOT NULL,
  `target_id` INT NOT NULL,
  `vote_type` VARCHAR(50) NOT NULL,
  `voter_ip` VARCHAR(100) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_forum_votes` (`target_type`, `target_id`, `voter_ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'device_identities' => "CREATE TABLE IF NOT EXISTS `device_identities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_key` VARCHAR(191) NOT NULL,
  `ip_hash` VARCHAR(191) NOT NULL,
  `author_name` VARCHAR(191) NOT NULL,
  `author_email` VARCHAR(191) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_device_identities_key` (`device_key`),
  KEY `idx_device_identities_ip` (`ip_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'scholar_spotlights' => "CREATE TABLE IF NOT EXISTS `scholar_spotlights` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `scholar_name` VARCHAR(191) NOT NULL,
  `title_affiliation` VARCHAR(255),
  `bio` TEXT,
  `image_url` TEXT,
  `research_field` VARCHAR(191),
  `profile_link` TEXT,
  `display_order` INT DEFAULT 0,
  `is_active` INT DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'live_discussions' => "CREATE TABLE IF NOT EXISTS `live_discussions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `topic` VARCHAR(255) NOT NULL,
  `speaker_name` VARCHAR(191) NOT NULL,
  `speaker_role` VARCHAR(191),
  `speaker_avatar` TEXT,
  `discussion_date` VARCHAR(100) NOT NULL,
  `zoom_link` TEXT,
  `ics_summary` TEXT,
  `is_active` INT DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'featured_interviews' => "CREATE TABLE IF NOT EXISTS `featured_interviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `interviewee_name` VARCHAR(191) NOT NULL,
  `interviewee_role` VARCHAR(191),
  `quote` TEXT,
  `photo` TEXT,
  `video_url` TEXT,
  `is_active` INT DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    'interviews' => "CREATE TABLE IF NOT EXISTS `interviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `interviewee_name` VARCHAR(191) NOT NULL,
  `title_role` VARCHAR(255) DEFAULT NULL,
  `quote` TEXT,
  `photo_url` TEXT,
  `transcript_content` LONGTEXT,
  `video_url` TEXT,
  `display_order` INT DEFAULT 0,
  `is_active` INT DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

// Append table definitions
foreach ($schemas as $tableName => $createSql) {
    $output[] = "-- Table structure for `$tableName`";
    $output[] = $createSql;
    $output[] = "";
}

// Dump row data
foreach ($tables as $tbl) {
    if (!isset($schemas[$tbl])) continue;
    $rows = $sqlite->query("SELECT * FROM `$tbl`")->fetchAll();
    if (empty($rows)) continue;

    $output[] = "-- Dumping data for table `$tbl` (" . count($rows) . " rows)";
    $cols = array_keys($rows[0]);
    $escapedCols = implode(", ", array_map(function($c){ return "`$c`"; }, $cols));

    foreach ($rows as $row) {
        $valStrings = [];
        foreach ($row as $k => $val) {
            if ($val === null) {
                $valStrings[] = "NULL";
            } elseif (is_numeric($val) && !is_string($val)) {
                $valStrings[] = $val;
            } else {
                // Escape string cleanly for MySQL
                $escaped = str_replace(
                    ['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
                    ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'],
                    (string)$val
                );
                $valStrings[] = "'" . $escaped . "'";
            }
        }
        $valList = implode(", ", $valStrings);
        $output[] = "INSERT INTO `$tbl` ($escapedCols) VALUES ($valList);";
    }
    $output[] = "";
}

$output[] = "SET FOREIGN_KEY_CHECKS = 1;";
$output[] = "";

$dumpContent = implode("\n", $output);
$destFile = __DIR__ . '/../../data/conspodium_mysql_dump.sql';
file_put_contents($destFile, $dumpContent);
echo "Successfully exported " . strlen($dumpContent) . " bytes to $destFile\n";
