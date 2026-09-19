<?php
/**
 * src/scripts/prerender_homepage.php
 * Pre-renders dynamic database sections into public/index.html during build
 */

$rootDir = dirname(__DIR__, 2);
$dbPath = $rootDir . '/data/conspodium.db';
$indexPath = $rootDir . '/public/index.html';

if (!file_exists($dbPath) || !file_exists($indexPath)) {
    echo "Skipping prerender: db or index not found.\n";
    exit(0);
}

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $html = file_get_contents($indexPath);

    // 1. Pre-render Scholar Spotlights
    $scholars = $pdo->query("SELECT * FROM scholar_spotlights WHERE is_active = 1 ORDER BY display_order ASC, id DESC")->fetchAll();
    if (!empty($scholars)) {
        $schHtml = '<div class="csp-scholars-grid">' . "\n";
        foreach ($scholars as $index => $sch) {
            $badgeText = ($index === 0) ? 'Featured Scholar' : (($index === 1) ? 'Featured This Month' : 'Featured Spotlight');
            $imgSrc = !empty($sch['image_url']) ? $sch['image_url'] : '/uploads/live_speaker_avatar.png';
            $link = !empty($sch['profile_link']) ? $sch['profile_link'] : '/stories/';

            $schHtml .= '  <div class="csp-scholar-card">' . "\n";
            $schHtml .= '    <div class="csp-scholar-avatar"><img src="' . htmlspecialchars($imgSrc) . '" alt="' . htmlspecialchars($sch['scholar_name']) . '" onerror="this.style.display=\'none\'"></div>' . "\n";
            $schHtml .= '    <span class="csp-scholar-coming">' . htmlspecialchars($badgeText) . '</span>' . "\n";
            $schHtml .= '    <div class="csp-scholar-name">' . htmlspecialchars($sch['scholar_name']) . '</div>' . "\n";
            $schHtml .= '    <div class="csp-scholar-uni">' . htmlspecialchars($sch['title_affiliation'] ?? '') . '</div>' . "\n";
            $schHtml .= '    <div class="csp-scholar-topic">' . htmlspecialchars($sch['bio'] ?? '') . '</div>' . "\n";
            $schHtml .= '    <a href="' . htmlspecialchars($link) . '" class="csp-scholar-btn"><svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>Read Article</a>' . "\n";
            $schHtml .= '  </div>' . "\n";
        }
        $schHtml .= '</div>';

        $html = preg_replace('/<div class="csp-scholars-grid">[\s\S]*?<\/div>\s*<\/div>\s*<\/section>/i', $schHtml . "\n        </div>\n      </section>", $html);
    }

    // 2. Pre-render Categories (Discover By Category)
    $categories = $pdo->query("SELECT c.*, COUNT(p.id) as post_count FROM categories c LEFT JOIN posts p ON c.id = p.category_id GROUP BY c.id ORDER BY c.display_order ASC, c.id ASC LIMIT 6")->fetchAll();
    if (!empty($categories)) {
        $catGridHtml = '<div class="elementor-element elementor-element-7ad4723a e-con-full e-grid e-con e-child adv-sticky-elementor" data-id="7ad4723a" data-element_type="container" data-e-type="container" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:30px;width:100%;margin-bottom:40px;">' . "\n";
        foreach ($categories as $cat) {
            $catImg = !empty($cat['image']) ? $cat['image'] : '/uploads/cat_diaspora_matters.png';
            $catUrl = '/category/' . $cat['slug'] . '/';
            $catGridHtml .= '  <div style="background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 10px 30px rgba(0,0,0,0.04);display:flex;flex-direction:column;justify-content:space-between;height:380px;">' . "\n";
            $catGridHtml .= '    <div>' . "\n";
            $catGridHtml .= '      <div style="width:100%;height:200px;overflow:hidden;"><img src="' . htmlspecialchars($catImg) . '" alt="' . htmlspecialchars($cat['name']) . '" style="width:100%;height:100%;object-fit:cover;" /></div>' . "\n";
            $catGridHtml .= '      <div style="padding:20px 24px 12px;">' . "\n";
            $catGridHtml .= '        <div style="font-family:\'Merriweather\',serif;font-size:1.15rem;font-weight:700;color:#0f172a;margin-bottom:8px;">' . htmlspecialchars($cat['name']) . '</div>' . "\n";
            $catGridHtml .= '        <div style="font-size:0.85rem;color:#64748b;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">' . htmlspecialchars($cat['description'] ?? '') . '</div>' . "\n";
            $catGridHtml .= '      </div>' . "\n";
            $catGridHtml .= '    </div>' . "\n";
            $catGridHtml .= '    <div style="padding:0 24px 20px;">' . "\n";
            $catGridHtml .= '      <a href="' . htmlspecialchars($catUrl) . '" style="display:inline-flex;align-items:center;gap:6px;font-size:0.85rem;font-weight:600;color:#00AEFE;text-decoration:none;">Explore Category &rarr;</a>' . "\n";
            $catGridHtml .= '    </div>' . "\n";
            $catGridHtml .= '  </div>' . "\n";
        }
        $catGridHtml .= '</div>';

        $html = preg_replace('/<div class="elementor-element elementor-element-7ad4723a[\s\S]*?<\/div>\s*<\/div>\s*<div class="elementor-element elementor-element-531c41b1/i', $catGridHtml . "\n          </div>\n          <div class=\"elementor-element elementor-element-531c41b1", $html);
    }

    // 3. Pre-render Voices That Inspire (Featured Stories)
    $posts = $pdo->query("SELECT p.*, c.name as category_name, c.slug as category_slug FROM posts p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.is_featured DESC, p.id DESC LIMIT 4")->fetchAll();
    if (!empty($posts)) {
        $postGridHtml = '<div class="eael-post-grid eael-post-appender eael-post-appender-1b58ee77 eael-post-grid-style-two" data-layout-mode="grid" style="display:grid !important;grid-template-columns:repeat(4, minmax(0, 1fr)) !important;gap:28px !important;width:100% !important;max-width:100% !important;float:none !important;">' . "\n";
        foreach ($posts as $p) {
            $pImg = !empty($p['featured_image']) ? $p['featured_image'] : '/wp-content/uploads/2026/01/African-Diasporans-1536x864-1.jpg';
            $pUrl = '/post/' . $p['slug'] . '/';
            $postGridHtml .= '  <div class="csp-story-card" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.04);display:flex;flex-direction:column;justify-content:space-between;box-sizing:border-box;">' . "\n";
            $postGridHtml .= '    <div>' . "\n";
            $postGridHtml .= '      <div style="width:100%;height:180px;overflow:hidden;position:relative;"><img src="' . htmlspecialchars($pImg) . '" alt="' . htmlspecialchars($p['title']) . '" style="width:100%;height:100%;object-fit:cover;" /><span style="position:absolute;top:12px;left:12px;background:rgba(0,174,254,0.9);color:#fff;font-size:0.72rem;font-weight:700;padding:4px 10px;border-radius:50px;">' . htmlspecialchars($p['category_name'] ?? 'Story') . '</span></div>' . "\n";
            $postGridHtml .= '      <div style="padding:20px 18px 12px;">' . "\n";
            $postGridHtml .= '        <h3 style="font-family:\'Merriweather\',serif;font-size:1.02rem;font-weight:700;color:#0f172a;line-height:1.4;margin:0 0 10px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><a href="' . htmlspecialchars($pUrl) . '" style="color:inherit;text-decoration:none;">' . htmlspecialchars($p['title']) . '</a></h3>' . "\n";
            $postGridHtml .= '        <p style="font-size:0.82rem;color:#64748b;line-height:1.55;margin:0 0 14px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">' . htmlspecialchars($p['excerpt'] ?? '') . '</p>' . "\n";
            $postGridHtml .= '      </div>' . "\n";
            $postGridHtml .= '    </div>' . "\n";
            $postGridHtml .= '    <div style="padding:0 18px 18px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid #f1f5f9;padding-top:12px;">' . "\n";
            $postGridHtml .= '      <span style="font-size:0.75rem;font-weight:600;color:#0f172a;">' . htmlspecialchars($p['author_name'] ?? 'Conspodium') . '</span>' . "\n";
            $postGridHtml .= '      <span style="font-size:0.75rem;color:#94a3b8;">' . htmlspecialchars($p['reading_time'] ?? '5 min read') . '</span>' . "\n";
            $postGridHtml .= '    </div>' . "\n";
            $postGridHtml .= '  </div>' . "\n";
        }
        $postGridHtml .= '</div>';

        $html = preg_replace('/<div id="eael-post-grid-1b58ee77" class="eael-post-grid-container">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/i', '<div id="eael-post-grid-1b58ee77" class="eael-post-grid-container">' . "\n" . $postGridHtml . "\n</div>\n</div>\n</div>\n</div>", $html);
    }

    file_put_contents($indexPath, $html);
    echo "  3. Pre-rendering homepage sections from SQLite database... ✓\n";

} catch (Exception $e) {
    echo "  ⚠ Pre-render warning: " . $e->getMessage() . "\n";
}
