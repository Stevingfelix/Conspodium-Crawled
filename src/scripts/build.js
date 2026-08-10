/**
 * src/scripts/build.js
 * =====================================================================
 * Builds the Conspodium site from src/ assets and templates into public/.
 *
 * Usage:  npm run build
 *
 * What it does:
 *   1. Copies src/assets/ → public/
 *   2. Ensures header fixes from src/components/header-fixes.html are injected
 *   3. Writes each page from src/pages/<id>.html → public/<destination>
 * =====================================================================
 */

import { readFile, writeFile, cp, mkdir, rm } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { existsSync } from 'node:fs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT      = join(__dirname, '../../');
const SRC       = join(ROOT, 'src');
const ASSETS    = join(SRC, 'assets');
const PUBLIC    = join(ROOT, 'public');

// Page destination map
const PAGES = [
  { id: 'home',         out: 'index.html' },
  { id: 'about',        out: 'about-us/index.html' },
  { id: 'stories',      out: 'stories/index.html' },
  { id: 'contact',      out: 'contact-us/index.html' },
  { id: 'sponsorship',  out: 'sponsorship/index.html' },
  { id: 'advert',       out: 'advert/index.html' },
  { id: 'submit-story', out: 'submit-story/index.html' },
  { id: 'category',     out: 'category/index.html' },
  { id: 'category',     out: 'category/culture-heritage/index.html' },
  { id: 'category',     out: 'category/innovation/index.html' },
  { id: 'category',     out: 'category/art-entertainment/index.html' },
  { id: 'category',     out: 'category/community/index.html' },
  { id: 'category',     out: 'category/success-stories/index.html' },
  { id: 'dashboard',    out: 'dashboard/index.html' },
  { id: 'post',         out: 'post/index.html' },
  { id: 'post',         out: 'post/we-are-the-world/index.html' },
  { id: 'post',         out: 'post/profiles-of-groundbreaking-tech-entrepreneurs-from-diaspora/index.html' },
  { id: 'post',         out: 'post/profiles-of-groundbreaking-tech-entrepreneurs-from-diaspora-2/index.html' },
  { id: 'post',         out: 'post/conspodium-is-all-about-community/index.html' },
  { id: 'post',         out: 'post/empowering-diaspora-communities-through-innovation-heritage/index.html' },
  { id: 'post',         out: 'post/africans-in-diaspora-influencing-global-economic-decisions/index.html' },
  { id: 'post',         out: 'post/creatives-shaping-representing-global-african-culture/index.html' },
  { id: 'post',         out: 'we-are-the-world/index.html' },
  { id: 'post',         out: 'profiles-of-groundbreaking-tech-entrepreneurs-from-diaspora/index.html' },
  { id: 'post',         out: 'profiles-of-groundbreaking-tech-entrepreneurs-from-diaspora-2/index.html' },
  { id: 'post',         out: 'conspodium-is-all-about-community/index.html' },
  { id: 'post',         out: 'empowering-diaspora-communities-through-innovation-heritage/index.html' },
  { id: 'post',         out: 'africans-in-diaspora-influencing-global-economic-decisions/index.html' },
  { id: 'post',         out: 'creatives-shaping-representing-global-african-culture/index.html' },
];

/** Recursively copy a directory */
async function copyDir(src, dest) {
  await mkdir(dest, { recursive: true });
  await cp(src, dest, { recursive: true, force: true });
}

// ── Main build process ───────────────────────────────────────────────────────

console.log('\n🔨 Conspodium Build\n');

// Step 1 — Clean legacy category files and copy static assets
process.stdout.write('  1. Copying assets and PHP API engine (src/assets & api → public)... ');
if (existsSync(join(PUBLIC, 'category'))) {
  await rm(join(PUBLIC, 'category'), { recursive: true, force: true });
}
if (existsSync(ASSETS)) {
  await copyDir(ASSETS, PUBLIC);
}
if (existsSync(join(ROOT, 'api'))) {
  await copyDir(join(ROOT, 'api'), join(PUBLIC, 'api'));
}
if (existsSync(join(ROOT, 'install.php'))) {
  await cp(join(ROOT, 'install.php'), join(PUBLIC, 'install.php'));
}
if (existsSync(join(ROOT, 'installation-guide.html'))) {
  await cp(join(ROOT, 'installation-guide.html'), join(PUBLIC, 'installation-guide.html'));
}
await mkdir(join(PUBLIC, 'uploads'), { recursive: true });
console.log('✓');

// Step 2 — Load shared components (header, footer, header-fixes)
const headerFixesHtml = existsSync(join(SRC, 'components/header-fixes.html'))
  ? await readFile(join(SRC, 'components/header-fixes.html'), 'utf8')
  : '';
const wpHeaderHtml = existsSync(join(SRC, 'components/wp-header.html'))
  ? await readFile(join(SRC, 'components/wp-header.html'), 'utf8')
  : '';
const wpFooterHtml = existsSync(join(SRC, 'components/wp-footer.html'))
  ? await readFile(join(SRC, 'components/wp-footer.html'), 'utf8')
  : '';

const POST_DATA = {
  'empowering-diaspora-communities-through-innovation-heritage': {
    id: 1,
    title: 'Empowering Diaspora Communities Through Innovation & Heritage',
    author_name: 'Steving Felix',
    published_at: '2026-08-01',
    reading_time: '6 min read',
    views: 1420,
    category_name: 'Culture & Heritage',
    category_icon: '🏛️',
    category_slug: 'culture-heritage',
    featured_image: '/wp-content/uploads/2026/01/African-Diasporans-1536x864-1.jpg',
    excerpt: 'Exploring how pan-African leaders, creators, and innovators are shaping global economic policies and cultural narratives across the diaspora.',
    content: `
      <p>Across Africa and its global diaspora, leaders in technology, finance, and arts are building bridges for sustainable economic growth and cultural exchange.</p>
      <p>Through diaspora summits, bilateral investment funds, and cross-border tech incubator networks, pan-African innovators are turning shared history into actionable global impact.</p>
    `
  },
  'africans-in-diaspora-influencing-global-economic-decisions': {
    id: 2,
    title: 'Africans in Diaspora Influencing Global Economic Decisions',
    author_name: 'Prof. Amara Diallo',
    published_at: '2026-08-03',
    reading_time: '6 min read',
    views: 980,
    category_name: 'Innovation',
    category_icon: '💡',
    category_slug: 'innovation',
    featured_image: '/wp-content/uploads/2026/01/WhatsApp-Image-2022-07-03-at-11.51.25-AM-1024x570-1.jpeg',
    excerpt: 'How African diaspora founders, venture capitalists, and policy advisors are driving bilateral trade and technology investments in Africa.',
    content: `
      <p>Global financial hubs are seeing an uptick in diaspora-led venture funds aimed at fueling sub-Saharan infrastructure, renewable energy, and fintech ecosystems.</p>
      <p>This new generation of investors prioritizes both high growth and measurable social impact across the African continent.</p>
    `
  },
  'creatives-shaping-representing-global-african-culture': {
    id: 3,
    title: 'Creatives Are Shaping & Representing Global African Culture',
    author_name: 'Dr. Ngozi Eze',
    published_at: '2026-08-05',
    reading_time: '10 min read',
    views: 1150,
    category_name: 'Art & Entertainment',
    category_icon: '🎨',
    category_slug: 'art-entertainment',
    featured_image: '/wp-content/uploads/2026/01/MoADCover-1180x664-1.jpg',
    excerpt: 'From visual arts exhibitions in San Francisco to Afrobeats on global stages, African artists are redefining modern creative expression.',
    content: `
      <p>Contemporary African artists and filmmakers are captivating international audiences while staying deeply rooted in authentic storytelling and cultural heritage.</p>
      <p>Major museum retrospectives and independent cinema showcases are ensuring that African stories are told on the world's biggest stages by African voices.</p>
    `
  },
  'profiles-of-groundbreaking-tech-entrepreneurs-from-diaspora-2': {
    id: 4,
    title: 'From mutual aid networks to cultural organizations, discover how diaspora communities create support systems that span the globe.',
    author_name: 'Conspodium Editorial',
    published_at: 'February 5, 2026',
    reading_time: '5 min read',
    views: 650,
    category_name: 'Success Stories',
    category_icon: '🌟',
    category_slug: 'success-stories',
    featured_image: '/wp-content/uploads/2026/01/AF3-1-png-300x171.jpg',
    excerpt: 'When my family arrived in Minneapolis from Somalia in the early 1990s, we didn\'t just find a new home—we found an informal safety net built by those who came before us.',
    content: `
      <p>When my family arrived in Minneapolis from Somalia in the early 1990s, we didn't just find a new home—we found an informal safety net built by those who came before us. From revolving savings associations to heritage weekend schools, diaspora communities create resilience across generations.</p>
      <p>Today, digital tools are amplifying these traditional support systems, allowing diaspora networks to mobilize emergency funds, mentor young professionals, and invest in continental initiatives seamlessly.</p>
    `
  },
  'profiles-of-groundbreaking-tech-entrepreneurs-from-diaspora': {
    id: 5,
    title: 'Profiles of Groundbreaking Tech Entrepreneurs From Diaspora',
    author_name: 'Conspodium Tech',
    published_at: 'February 5, 2026',
    reading_time: '6 min read',
    views: 1420,
    category_name: 'Innovation',
    category_icon: '💡',
    category_slug: 'innovation',
    featured_image: '/wp-content/uploads/2026/01/location-1-300x210.webp',
    excerpt: 'The story of Silicon Valley cannot be told without highlighting the incredible impact of diaspora founders and tech innovators driving global change.',
    content: `
      <p>The story of Silicon Valley cannot be told without highlighting the incredible impact of diaspora founders and tech innovators driving global change. Building scalable fintech, logistics, and healthcare solutions, these visionary entrepreneurs bridge global tech capital with African market dynamics.</p>
      <p>By cultivating bi-directional venture pipelines, diaspora founders are accelerating digital transformation across both Western ecosystems and the African continent.</p>
    `
  },
  'we-are-the-world': {
    id: 6,
    title: 'We Are The World',
    author_name: 'Amara Okonkwo',
    published_at: 'February 4, 2026',
    reading_time: '8 min read',
    views: 2100,
    category_name: 'Community',
    category_icon: '👥',
    category_slug: 'community',
    featured_image: '/wp-content/uploads/2026/02/tourist-carrying-luggage-300x150.jpg',
    excerpt: 'By Amara Okonkwo, Cultural Anthropologist. The first time I experienced global unity across diaspora cultures was during the pan-African cultural festival.',
    content: `
      <p>By Amara Okonkwo, Cultural Anthropologist. The first time I experienced global unity across diaspora cultures was during the pan-African cultural festival. Across language barriers and geographical distance, shared heritage binds diverse diaspora communities together.</p>
      <p>Exploring themes of identity, movement, and belonging, this landmark series documents the personal and collective journeys of global Africans across four continents.</p>
    `
  },
  'conspodium-is-all-about-community': {
    id: 7,
    title: 'Visionary Entrepreneurs Leveraging Their Dual Cultural Knowledge',
    author_name: 'Conspodium Editorial',
    published_at: 'February 4, 2026',
    reading_time: '5 min read',
    views: 834,
    category_name: 'Community',
    category_icon: '👥',
    category_slug: 'community',
    featured_image: '/wp-content/uploads/2026/02/portrait-smiley-people-african-wedding-300x200.jpg',
    excerpt: 'When I founded my first company at 24, connecting African artisans with European fashion houses, I realized our dual heritage is our greatest superpower.',
    content: `
      <p>When I founded my first company at 24, connecting African artisans with European fashion houses, I realized our dual heritage is our greatest superpower. Bicultural entrepreneurs navigate international markets with nuance, leveraging deep cultural empathy to build sustainable global brands.</p>
      <p>By celebrating authentic craftsmanship and ethical trade practices, these leaders are redefining luxury and creative commerce on a global scale.</p>
    `
  }
};

// Step 3 — Process and write pages
console.log(`  2. Building ${PAGES.length} pages:\n`);

for (const page of PAGES) {
  const srcFile = join(SRC, 'pages', `${page.id}.html`);
  const outFile = join(PUBLIC, page.out);

  if (!existsSync(srcFile)) {
    console.warn(`     ⚠  src/pages/${page.id}.html not found — skipping`);
    continue;
  }

  let html = await readFile(srcFile, 'utf8');

  // Pre-render post content into static HTML if page is a post
  if (page.id === 'post') {
    const parts = page.out.split('/').filter(p => p !== 'index.html' && p !== 'post' && p !== '');
    const slug = parts.length ? parts[parts.length - 1] : 'we-are-the-world';
    const pData = POST_DATA[slug] || POST_DATA['we-are-the-world'];

    if (pData) {
      html = html.replace(/<title>[\s\S]*?<\/title>/i, `<title>${pData.title} — Conspodium</title>`);
      html = html.replace(/<h1 class="csp-post-title" id="post-title">[\s\S]*?<\/h1>/i, `<h1 class="csp-post-title" id="post-title">${pData.title}</h1>`);
      html = html.replace(/<strong id="post-author"[\s\S]*?<\/strong>/i, `<strong id="post-author" style="color:#0f172a;">${pData.author_name}</strong>`);
      html = html.replace(/<span id="post-date">[\s\S]*?<\/span>/i, `<span id="post-date">${pData.published_at}</span>`);
      html = html.replace(/<span id="post-reading-time">[\s\S]*?<\/span>/i, `<span id="post-reading-time">${pData.reading_time}</span>`);
      html = html.replace(/<span id="post-views">[\s\S]*?<\/span>/i, `<span id="post-views">${pData.views} views</span>`);
      html = html.replace(/<a href="#" id="post-category-badge" class="csp-post-badge">[\s\S]*?<\/a>/i, `<a href="/category/${pData.category_slug}/" id="post-category-badge" class="csp-post-badge">${pData.category_icon} ${pData.category_name}</a>`);
      html = html.replace(/<img id="post-hero-image"[\s\S]*?>/i, `<img id="post-hero-image" src="${pData.featured_image}" alt="${pData.title}" class="csp-post-hero-img" style="display:block;">`);
      html = html.replace(/<div id="post-excerpt-box" class="csp-post-excerpt-box" style="display:none;"><\/div>/i, `<div id="post-excerpt-box" class="csp-post-excerpt-box" style="display:block;">${pData.excerpt}</div>`);
      html = html.replace(/<div id="post-content-body" class="csp-post-body">[\s\S]*?<\/div>/i, `<div id="post-content-body" class="csp-post-body">${pData.content}</div>`);

      // Pre-render Sidebar Related Stories
      const otherKeys = Object.keys(POST_DATA).filter(k => k !== slug).slice(0, 3);
      let sideHtml = '';
      otherKeys.forEach(k => {
        const item = POST_DATA[k];
        sideHtml += `
          <a href="/post/${k}/" class="csp-rel-item">
            <img src="${item.featured_image}" alt="${item.title}" class="csp-rel-thumb" />
            <div class="csp-rel-info">
              <h4>${item.title}</h4>
              <span class="csp-rel-meta">${item.category_name} • ${item.reading_time}</span>
            </div>
          </a>
        `;
      });
      html = html.replace(/<div id="sidebar-related-posts">[\s\S]*?<\/div>/i, `<div id="sidebar-related-posts">${sideHtml}</div>`);
    }
  }

  // Replace component include comments
  if (wpHeaderHtml) {
    html = html.replace(/<!--\s*INCLUDE:(header-fixes|header|wp-header)\.html\s*-->/gi, wpHeaderHtml);
  }
  if (wpFooterHtml) {
    html = html.replace(/<!--\s*INCLUDE:(footer|wp-footer)\.html\s*-->/gi, wpFooterHtml);
  }

  // Clean out any legacy or duplicate header fix blocks and scripts
  html = html.replace(/<!-- CSP_HEADER_FIXES_START -->[\s\S]*?<!-- CSP_HEADER_FIXES_END -->\n?/g, '');
  html = html.replace(/<style id="csp-header-fixes">[\s\S]*?<\/style>\n?/g, '');
  html = html.replace(/<script id="csp-mobile-menu-script">[\s\S]*?<\/script>\n?/g, '');

  if (headerFixesHtml) {
    html = html.replace('</head>', headerFixesHtml + '\n</head>');
  }

  // Normalize relative assets & wp navigation links to root-relative paths
  html = html.replace(/(src|href|srcset)=["']\.\.?\/(wp-content|wp-includes|assets)\//g, '$1="/$2/');
  html = html.replace(/href=["']\.\.\/([a-zA-Z0-9_-]+\/?)["']/g, 'href="/$1"');
  html = html.replace(/href=["']\.\/([a-zA-Z0-9_-]+\/?)["']/g, 'href="/$1"');
  html = html.replace(/href=["']\.\/["']/g, 'href="/"');
  html = html.replace(/href=["']\.\.\/["']/g, 'href="/"');

  await mkdir(dirname(outFile), { recursive: true });
  await writeFile(outFile, html, 'utf8');
  console.log(`     ✓  ${page.id.padEnd(16)} → public/${page.out}`);
}

console.log('\n✅ Build complete → public/');
console.log('   Run: npm start  →  http://localhost:8080\n');
