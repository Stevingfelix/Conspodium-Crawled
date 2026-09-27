<?php
// api/sitemap.php - Dynamic XML Sitemap Generator for Conspodium
header("Content-Type: application/xml; charset=utf-8");
header("X-Robots-Tag: noindex");

require_once __DIR__ . '/db.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'conspodium.com';
$baseUrl = $protocol . $host;

// If on localhost dev, default canonical baseUrl to https://conspodium.com for production indexing consistency
$canonicalBase = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) 
    ? 'https://conspodium.com' 
    : rtrim($baseUrl, '/');

$now = date('Y-m-d\TH:i:sP');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">

    <!-- 1. CORE STATIC HUBS -->
    <url>
        <loc><?= $canonicalBase ?>/</loc>
        <lastmod><?= $now ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?= $canonicalBase ?>/stories/</loc>
        <lastmod><?= $now ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.95</priority>
    </url>
    <url>
        <loc><?= $canonicalBase ?>/forum/</loc>
        <lastmod><?= $now ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.85</priority>
    </url>
    <url>
        <loc><?= $canonicalBase ?>/about-us/</loc>
        <lastmod><?= $now ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.70</priority>
    </url>
    <url>
        <loc><?= $canonicalBase ?>/contact-us/</loc>
        <lastmod><?= $now ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.65</priority>
    </url>
    <url>
        <loc><?= $canonicalBase ?>/submit-story/</loc>
        <lastmod><?= $now ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.80</priority>
    </url>
    <url>
        <loc><?= $canonicalBase ?>/sponsorship/</loc>
        <lastmod><?= $now ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.75</priority>
    </url>
    <url>
        <loc><?= $canonicalBase ?>/advert/</loc>
        <lastmod><?= $now ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.70</priority>
    </url>

    <!-- 2. DYNAMIC CATEGORIES -->
    <?php
    try {
        $stmtCat = $pdo->query("SELECT slug, name, image FROM categories ORDER BY name ASC");
        while ($cat = $stmtCat->fetch()) {
            $catSlug = htmlspecialchars($cat['slug'], ENT_XML1, 'UTF-8');
            $catName = htmlspecialchars($cat['name'], ENT_XML1, 'UTF-8');
            $catImg = !empty($cat['image']) ? htmlspecialchars($canonicalBase . (strpos($cat['image'], '/') === 0 ? '' : '/') . $cat['image'], ENT_XML1, 'UTF-8') : '';
    ?>
    <url>
        <loc><?= $canonicalBase ?>/category/<?= $catSlug ?>/</loc>
        <lastmod><?= $now ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.85</priority>
        <?php if ($catImg): ?>
        <image:image>
            <image:loc><?= $catImg ?></image:loc>
            <image:title><?= $catName ?> Articles &amp; Insights</image:title>
        </image:image>
        <?php endif; ?>
    </url>
    <?php
        }
    } catch (Exception $e) {}
    ?>

    <!-- 3. DYNAMIC PUBLISHED ARTICLES -->
    <?php
    try {
        $stmtPosts = $pdo->query("
            SELECT p.slug, p.title, p.featured_image, p.published_at, p.excerpt, c.name as category_name
            FROM posts p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'published'
            ORDER BY p.published_at DESC
        ");
        while ($post = $stmtPosts->fetch()) {
            $postSlug = htmlspecialchars($post['slug'], ENT_XML1, 'UTF-8');
            $postTitle = htmlspecialchars($post['title'], ENT_XML1, 'UTF-8');
            $postDate = !empty($post['published_at']) ? date('Y-m-d\TH:i:sP', strtotime($post['published_at'])) : $now;
            $postImg = !empty($post['featured_image']) ? htmlspecialchars($canonicalBase . (strpos($post['featured_image'], '/') === 0 ? '' : '/') . $post['featured_image'], ENT_XML1, 'UTF-8') : '';
            $postExcerpt = htmlspecialchars(substr(strip_tags($post['excerpt'] ?? ''), 0, 200), ENT_XML1, 'UTF-8');
    ?>
    <url>
        <loc><?= $canonicalBase ?>/post/<?= $postSlug ?>/</loc>
        <lastmod><?= $postDate ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.90</priority>
        <?php if ($postImg): ?>
        <image:image>
            <image:loc><?= $postImg ?></image:loc>
            <image:title><?= $postTitle ?></image:title>
            <?php if ($postExcerpt): ?>
            <image:caption><?= $postExcerpt ?></image:caption>
            <?php endif; ?>
        </image:image>
        <?php endif; ?>
    </url>
    <?php
        }
    } catch (Exception $e) {}
    ?>

    <!-- 4. FORUM COMMUNITY THREADS -->
    <?php
    try {
        $stmtForum = $pdo->query("SELECT id, slug, title, created_at FROM forum_threads ORDER BY id DESC LIMIT 50");
        while ($thread = $stmtForum->fetch()) {
            $thId = intval($thread['id']);
            $thDate = !empty($thread['created_at']) ? date('Y-m-d\TH:i:sP', strtotime($thread['created_at'])) : $now;
    ?>
    <url>
        <loc><?= $canonicalBase ?>/forum/thread/?id=<?= $thId ?></loc>
        <lastmod><?= $thDate ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.75</priority>
    </url>
    <?php
        }
    } catch (Exception $e) {}
    ?>

</urlset>
