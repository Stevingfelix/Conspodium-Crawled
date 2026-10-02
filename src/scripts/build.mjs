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

// 12 Core Clean Page Templates (Dynamic URL routing handles all article/category slugs)
const PAGES = [
  { id: 'home',         out: 'index.html' },
  { id: 'about',        out: 'about-us/index.html' },
  { id: 'stories',      out: 'stories/index.html' },
  { id: 'contact',      out: 'contact-us/index.html' },
  { id: 'sponsorship',  out: 'sponsorship/index.html' },
  { id: 'advert',       out: 'advert/index.html' },
  { id: 'submit-story', out: 'submit-story/index.html' },
  { id: 'category',     out: 'category/index.html' },
  { id: 'forum',        out: 'forum/index.html' },
  { id: 'forum-thread', out: 'forum/thread/index.html' },
  { id: 'dashboard',    out: 'dashboard/index.html' },
  { id: 'post',         out: 'post/index.html' }
];

/** Recursively copy a directory (skipping .db files if target exists) */
async function copyDir(src, dest) {
  await mkdir(dest, { recursive: true });
  await cp(src, dest, { 
    recursive: true, 
    force: true,
    filter: (srcPath, destPath) => {
      if (srcPath.endsWith('.db') && existsSync(destPath)) {
        return false; // Preserve live database in public
      }
      return true;
    }
  });
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
if (existsSync(join(ROOT, 'data'))) {
  await copyDir(join(ROOT, 'data'), join(PUBLIC, 'data'));
}
if (existsSync(join(ROOT, 'install.php'))) {
  await cp(join(ROOT, 'install.php'), join(PUBLIC, 'install.php'));
}
if (existsSync(join(ROOT, 'installation-guide.html'))) {
  await cp(join(ROOT, 'installation-guide.html'), join(PUBLIC, 'installation-guide.html'));
}
if (existsSync(join(ROOT, '.htaccess'))) {
  await cp(join(ROOT, '.htaccess'), join(PUBLIC, '.htaccess'));
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
  },
  'in-conversation-with-dr-ngozi-eze': {
    id: 42,
    title: 'In Conversation with Dr. Ngozi Eze: Biotechnology, Indigenous Science, and African Data Sovereignty',
    author_name: 'The Conspodium Dialogue Desk',
    published_at: 'February 6, 2026',
    reading_time: '9 min read',
    views: 1250,
    category_name: 'Innovation',
    category_icon: '💡',
    category_slug: 'innovation',
    featured_image: '/wp-content/uploads/2026/01/couple-using-technology-while-traveling-city-scaled.jpg',
    excerpt: '“Biotechnology is the next frontier of African liberation. We must own our science, our data, and our story.” — Dr. Ngozi Eze, MIT Media Lab.',
    content: `
      <div class="csp-interview-transcript-container">
        <div class="csp-transcript-section">
          <h3 style="color: #0f172a; margin-top: 10px; font-size: 1.35rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">1. The Dawn of African Biotechnology</h3>
          
          <p><strong><span style="color: #00AEFE;">Conspodium:</span></strong> Dr. Eze, thank you for joining us on Conspodium Dialogue. In your recent papers and keynote at the Pan-African Health Summit, you made a provocative statement: <em>"Biotechnology is the next frontier of African liberation. We must own our science, our data, and our story."</em> What does data and scientific ownership mean in practice for the continent and the diaspora today?</p>

          <p><strong><span style="color: #B71F71;">Dr. Ngozi Eze:</span></strong> Thank you for having me. For decades, the global scientific model has treated Africa as a resource colony for biological discovery. International research groups extract genetic samples, endemic plant compounds, and clinical trial data, take them to Western labs, patent the synthesized derivatives, and then sell the therapies back to our people at exorbitant prices. When I speak about scientific liberation, I mean closing that extractive loop. We have the computational power, the molecular biologists, and the diaspora research networks to decode, sequence, and patent our own biomedical innovations right here.</p>

          <blockquote style="border-left: 4px solid #B71F71; margin: 24px 0; padding: 16px 24px; background: #fdf2f8; font-style: italic; font-size: 1.05rem; color: #831843; border-radius: 0 8px 8px 0;">
            "If we do not control the genomic repositories of the world’s most genetically diverse continent, we are surrendering the intellectual property of the next two centuries."
          </blockquote>

          <h3 style="color: #0f172a; margin-top: 32px; font-size: 1.35rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">2. Bridging Indigenous Knowledge Systems and Synthetic Biology</h3>

          <p><strong><span style="color: #00AEFE;">Conspodium:</span></strong> Traditional African pharmacopeia has sustained communities for millennia, yet it has often been dismissed in mainstream academia. How is your team combining ancestral ethnobotanical records with contemporary machine learning and high-throughput screening?</p>

          <p><strong><span style="color: #B71F71;">Dr. Ngozi Eze:</span></strong> Indigenous healers in Yorubaland, the Ethiopian highlands, and KwaZulu-Natal have documented sophisticated therapeutic properties for native flora for generations. At BioAfrica Labs, we don't treat this knowledge as folklore — we treat it as structured empirical evidence. Using transformer-based protein-folding models and mass spectrometry, we analyze the multi-compound synergy in these herbal formulations. We’re finding that traditional poly-herbal combinations often prevent drug resistance in pathogens far better than single-molecule synthetic drugs.</p>

          <h3 style="color: #0f172a; margin-top: 32px; font-size: 1.35rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">3. The Role of the Global Diaspora: Brain Circulation over Brain Drain</h3>

          <p><strong><span style="color: #00AEFE;">Conspodium:</span></strong> Many African scientists who trained abroad in Europe and North America struggle with the decision of whether to return permanently or build institutions abroad. How should the diaspora organize its scientific capital?</p>

          <p><strong><span style="color: #B71F71;">Dr. Ngozi Eze:</span></strong> We must retire the binary notion of "brain drain" and replace it with <em>brain circulation</em>. A bioengineer in Boston or Stockholm can run joint computational pipelines with postdocs in Ibadan or Nairobi in real time. We can co-author grants, mentor graduate students, set up remote laboratory hubs, and direct diaspora angel capital into biotech startups across Africa. Distance is no longer a barrier; institutional alignment and governance are what matter.</p>

          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin: 30px 0;">
            <h4 style="margin-top: 0; color: #00AEFE; font-size: 1.1rem;">💡 Key Takeaways from Dr. Ngozi Eze:</h4>
            <ul style="margin: 0; padding-left: 20px; line-height: 1.8; color: #334155;">
              <li><strong>Genomic Sovereignty:</strong> Establishing localized biobanks and open-source genomic sequencing infrastructure across African hubs.</li>
              <li><strong>Ethnobotanical Patenting:</strong> Protecting intellectual property rights for local communities whose traditional medicine informs modern drug discovery.</li>
              <li><strong>Diaspora R&amp;D Syndicates:</strong> Creating direct investment pipelines between diaspora researchers and continental laboratories.</li>
            </ul>
          </div>

          <h3 style="color: #0f172a; margin-top: 32px; font-size: 1.35rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">4. Looking Ahead: Conspodium Readers' Call to Action</h3>

          <p><strong><span style="color: #00AEFE;">Conspodium:</span></strong> What advice do you have for young African researchers, students, and diaspora founders eager to enter biotechnology and life sciences?</p>

          <p><strong><span style="color: #B71F71;">Dr. Ngozi Eze:</span></strong> First, master both the computational and biological disciplines. The frontier of modern biology is written in Python, linear algebra, and RNA sequencing. Second, remain curious about your own environment. Africa is home to over 45,000 plant species and unmatched human genetic diversity. The answers to humanity’s most stubborn health puzzles are right in our soil and DNA. Own your science, tell your story with unapologetic excellence, and build together.</p>
        </div>
      </div>
    `
  }
};

// Register individual post pages for static generation
for (const slug of Object.keys(POST_DATA)) {
  PAGES.push({ id: 'post', out: `post/${slug}/index.html` });
}

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
    const isFallback = parts.length === 0;
    const slug = isFallback ? null : parts[parts.length - 1];
    const pData = isFallback ? null : (POST_DATA[slug] || null);

    if (pData) {
      const canonicalUrl = `https://conspodium.com/post/${slug}/`;
      const postImageUrl = pData.featured_image.startsWith('http') ? pData.featured_image : `https://conspodium.com${pData.featured_image}`;
      
      html = html.replace(/<title[\s\S]*?<\/title>/i, `<title>${pData.title} — Conspodium | Premium Diaspora Magazine</title>`);
      html = html.replace(/<meta name="description"[\s\S]*?>/i, `<meta name="description" content="${pData.excerpt.replace(/"/g, '&quot;')}">`);
      html = html.replace(/<link rel="canonical"[\s\S]*?>/i, `<link rel="canonical" href="${canonicalUrl}">`);
      
      // Update Open Graph Tags
      html = html.replace(/<meta property="og:title"[\s\S]*?>/i, `<meta property="og:title" content="${pData.title.replace(/"/g, '&quot;')}">`);
      html = html.replace(/<meta property="og:description"[\s\S]*?>/i, `<meta property="og:description" content="${pData.excerpt.replace(/"/g, '&quot;')}">`);
      html = html.replace(/<meta property="og:url"[\s\S]*?>/i, `<meta property="og:url" content="${canonicalUrl}">`);
      html = html.replace(/<meta property="og:image"[\s\S]*?>/i, `<meta property="og:image" content="${postImageUrl}">`);

      // Update Twitter Card Tags
      html = html.replace(/<meta name="twitter:title"[\s\S]*?>/i, `<meta name="twitter:title" content="${pData.title.replace(/"/g, '&quot;')}">`);
      html = html.replace(/<meta name="twitter:description"[\s\S]*?>/i, `<meta name="twitter:description" content="${pData.excerpt.replace(/"/g, '&quot;')}">`);
      html = html.replace(/<meta name="twitter:image"[\s\S]*?>/i, `<meta name="twitter:image" content="${postImageUrl}">`);

      // Update Schema.org NewsArticle & Breadcrumbs JSON-LD
      const schemaJson = JSON.stringify({
        "@context": "https://schema.org",
        "@graph": [
          {
            "@type": "NewsArticle",
            "@id": `${canonicalUrl}#article`,
            "isPartOf": { "@id": canonicalUrl },
            "headline": pData.title,
            "description": pData.excerpt,
            "url": canonicalUrl,
            "image": [postImageUrl],
            "datePublished": pData.published_at,
            "dateModified": pData.published_at,
            "author": {
              "@type": "Person",
              "name": pData.author_name
            },
            "publisher": {
              "@type": "NewsMediaOrganization",
              "name": "Conspodium",
              "url": "https://conspodium.com",
              "logo": {
                "@type": "ImageObject",
                "url": "https://conspodium.com/wp-content/uploads/2026/01/conspodium-loader.png"
              }
            },
            "mainEntityOfPage": {
              "@type": "WebPage",
              "@id": canonicalUrl
            },
            "articleSection": pData.category_name
          },
          {
            "@type": "BreadcrumbList",
            "@id": `${canonicalUrl}#breadcrumb`,
            "itemListElement": [
              {
                "@type": "ListItem",
                "position": 1,
                "name": "Home",
                "item": "https://conspodium.com/"
              },
              {
                "@type": "ListItem",
                "position": 2,
                "name": pData.category_name,
                "item": `https://conspodium.com/category/${pData.category_slug}/`
              },
              {
                "@type": "ListItem",
                "position": 3,
                "name": pData.title,
                "item": canonicalUrl
              }
            ]
          }
        ]
      }, null, 2);

      html = html.replace(/<script type="application\/ld\+json" id="(post-schema-json|schema-article-jsonld)">[\s\S]*?<\/script>/i, `<script type="application/ld+json" id="schema-article-jsonld">\n${schemaJson}\n  </script>`);

      html = html.replace(/<h1 class="csp-post-title" id="post-title">[\s\S]*?<\/h1>/i, `<h1 class="csp-post-title" id="post-title">${pData.title}</h1>`);
      html = html.replace(/<strong id="post-author"[\s\S]*?<\/strong>/i, `<strong id="post-author" style="color:#0f172a;">${pData.author_name}</strong>`);
      html = html.replace(/<span id="post-date">[\s\S]*?<\/span>/i, `<span id="post-date">${pData.published_at}</span>`);
      html = html.replace(/<span id="post-reading-time">[\s\S]*?<\/span>/i, `<span id="post-reading-time">${pData.reading_time}</span>`);
      html = html.replace(/<span id="post-views">[\s\S]*?<\/span>/i, `<span id="post-views">${pData.views} views</span>`);
      html = html.replace(/<img id="post-hero-image"[\s\S]*?>/i, `<img id="post-hero-image" src="${pData.featured_image}" alt="${pData.title}" class="csp-post-hero-img" style="display:block;">`);
      html = html.replace(/<div id="post-excerpt-box" class="csp-post-excerpt-box" style="display:none;"><\/div>/i, `<div id="post-excerpt-box" class="csp-post-excerpt-box" style="display:block;">${pData.excerpt}</div>`);
      html = html.replace(/<div id="post-content-body" class="csp-post-body">[\s\S]*?<!-- CSP_POST_BODY_END -->/i, `<div id="post-content-body" class="csp-post-body">${pData.content}</div><!-- CSP_POST_BODY_END -->`);
      html = html.replace(/<h4 style="[\s\S]*?" id="post-author-card-name">[\s\S]*?<\/h4>/i, `<h4 style="font-family:'Merriweather',serif;font-size:1.15rem;font-weight:700;color:#0f172a;margin:0 0 6px;" id="post-author-card-name">${pData.author_name}</h4>`);
      html = html.replace(/<span id="post-author-avatar-initial">[\s\S]*?<\/span>/i, `<span id="post-author-avatar-initial">${pData.author_name.charAt(0)}</span>`);

      // Pre-render Category Pills Bar with post's active category pre-highlighted
      const categoriesList = [
        { name: 'All Stories', slug: 'stories', icon: '📚', url: '/stories/' },
        { name: 'Culture & Heritage', slug: 'culture-heritage', icon: '🏛️', url: '/category/culture-heritage/' },
        { name: 'Innovation & Tech', slug: 'innovation', icon: '💡', url: '/category/innovation/' },
        { name: 'Art & Entertainment', slug: 'art-entertainment', icon: '🎨', url: '/category/art-entertainment/' },
        { name: 'Community', slug: 'community', icon: '👥', url: '/category/community/' },
        { name: 'Success Stories', slug: 'success-stories', icon: '🌟', url: '/category/success-stories/' }
      ];

      let catTabsHtml = '';
      categoriesList.forEach(cat => {
        const isActive = (cat.slug === pData.category_slug);
        const styleAttr = isActive 
          ? 'padding:10px 22px;border-radius:50px;border:none;background:#00AEFE;color:#fff;font-weight:600;font-size:0.85rem;text-decoration:none;white-space:nowrap;'
          : 'padding:10px 22px;border-radius:50px;border:1px solid #cbd5e1;background:#ffffff;color:#475569;font-weight:600;font-size:0.85rem;text-decoration:none;white-space:nowrap;';
        catTabsHtml += `<a href="${cat.url}" class="csp-cat-tab${isActive ? ' active' : ''}" style="${styleAttr}">${cat.icon} ${cat.name}</a>\n        `;
      });

      html = html.replace(/<div class="csp-cat-nav-inner" id="csp-cat-tabs-container"[\s\S]*?<\/div>/i, `<div class="csp-cat-nav-inner" id="csp-cat-tabs-container" style="display:flex;gap:8px;overflow-x:auto;padding-bottom:2px;align-items:center;width:100%;-webkit-overflow-scrolling:touch;">\n        ${catTabsHtml}</div>`);

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

      // Pre-render Share URLs into HTML
      const encodedPostUrl = encodeURIComponent(`https://conspodium.com/${page.out.replace(/index\.html$/, '')}`);
      const encodedTitle = encodeURIComponent(pData.title);
      html = html.replace(/href="#" class="csp-share-btn csp-share-fb" id="share-fb"/gi, `href="https://www.facebook.com/sharer/sharer.php?u=${encodedPostUrl}" class="csp-share-btn csp-share-fb" id="share-fb"`);
      html = html.replace(/href="#" class="csp-share-btn csp-share-tw" id="share-tw"/gi, `href="https://twitter.com/intent/tweet?url=${encodedPostUrl}&text=${encodedTitle}" class="csp-share-btn csp-share-tw" id="share-tw"`);
      html = html.replace(/href="#" class="csp-share-btn csp-share-li" id="share-li"/gi, `href="https://www.linkedin.com/shareArticle?mini=true&url=${encodedPostUrl}&title=${encodedTitle}" class="csp-share-btn csp-share-li" id="share-li"`);
      html = html.replace(/href="#" class="csp-share-btn csp-share-wa" id="share-wa"/gi, `href="https://api.whatsapp.com/send?text=${encodedTitle}%20${encodedPostUrl}" class="csp-share-btn csp-share-wa" id="share-wa"`);
    }
  }

  // Replace main header & footer components
  if (wpHeaderHtml) {
    html = html.replace(/<!--\s*INCLUDE:(header|wp-header)\.html\s*-->/gi, wpHeaderHtml);
  }
  if (wpFooterHtml) {
    html = html.replace(/<!--\s*INCLUDE:(footer|wp-footer)\.html\s*-->/gi, wpFooterHtml);
  }

  // Clean out any legacy or duplicated header fix blocks in raw template source
  html = html.replace(/<!-- CSP_HEADER_FIXES_START -->[\s\S]*?<!-- CSP_HEADER_FIXES_END -->\n?/g, '');
  html = html.replace(/<style id="csp-header-fixes">[\s\S]*?<\/style>\n?/g, '');
  html = html.replace(/<script id="csp-mobile-menu-script">[\s\S]*?<\/script>\n?/g, '');

  // Now inject header-fixes cleanly into head once
  if (headerFixesHtml) {
    if (html.includes('<!-- INCLUDE:header-fixes.html -->')) {
      html = html.replace(/<!--\s*INCLUDE:header-fixes\.html\s*-->/gi, headerFixesHtml);
    } else if (!html.includes('id="csp-header-fixes"')) {
      html = html.replace('</head>', headerFixesHtml + '\n</head>');
    }
  }

  // Normalize relative assets & wp navigation links to root-relative paths
  html = html.replace(/(src|href|srcset|poster)=["']\.\.?\/(wp-content|wp-includes|assets|videos)\//g, '$1="/$2/');
  html = html.replace(/href=["']\.\.\/([a-zA-Z0-9_-]+\/?)["']/g, 'href="/$1"');
  html = html.replace(/href=["']\.\/([a-zA-Z0-9_-]+\/?)["']/g, 'href="/$1"');
  html = html.replace(/href=["']\.\/["']/g, 'href="/"');
  html = html.replace(/href=["']\.\.\/["']/g, 'href="/"');

  await mkdir(dirname(outFile), { recursive: true });
  await writeFile(outFile, html, 'utf8');
  console.log(`     ✓  ${page.id.padEnd(16)} → public/${page.out}`);
}

// Step 4 — Build Static Sitemap.xml
process.stdout.write('\n  3. Generating static sitemap.xml... ');
const today = new Date().toISOString().split('T')[0];
let staticSitemap = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
  <url>
    <loc>https://conspodium.com/</loc>
    <lastmod>${today}</lastmod>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://conspodium.com/stories/</loc>
    <lastmod>${today}</lastmod>
    <changefreq>daily</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc>https://conspodium.com/about-us/</loc>
    <lastmod>${today}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>https://conspodium.com/contact-us/</loc>
    <lastmod>${today}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
  </url>
  <url>
    <loc>https://conspodium.com/forum/</loc>
    <lastmod>${today}</lastmod>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc>https://conspodium.com/sponsorship/</loc>
    <lastmod>${today}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.6</priority>
  </url>
  <url>
    <loc>https://conspodium.com/advert/</loc>
    <lastmod>${today}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.6</priority>
  </url>
  <url>
    <loc>https://conspodium.com/submit-story/</loc>
    <lastmod>${today}</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.6</priority>
  </url>
`;

// Add Category URLs
const staticCategories = [
  'culture-heritage', 'innovation', 'art-entertainment', 'community', 'success-stories', 'news-features', 'events'
];
for (const cat of staticCategories) {
  staticSitemap += `  <url>
    <loc>https://conspodium.com/category/${cat}/</loc>
    <lastmod>${today}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>\n`;
}

// Add Article URLs
for (const [slug, item] of Object.entries(POST_DATA)) {
  const imgUrl = item.featured_image.startsWith('http') ? item.featured_image : `https://conspodium.com${item.featured_image}`;
  staticSitemap += `  <url>
    <loc>https://conspodium.com/post/${slug}/</loc>
    <lastmod>${today}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.85</priority>
    <image:image>
      <image:loc>${imgUrl}</image:loc>
      <image:title>${item.title.replace(/&/g, '&amp;')}</image:title>
    </image:image>
  </url>\n`;
}

staticSitemap += `</urlset>\n`;
await writeFile(join(PUBLIC, 'sitemap.xml'), staticSitemap, 'utf8');
console.log('✓');

// 4. Generate static polls JSON fallback for serverless/static platforms (Vercel)
const activePollData = {
  success: true,
  poll: {
    id: 1,
    question: "What is the most pressing economic opportunity for the African Diaspora in 2026?",
    options: [
      { option: "Cross-Border Tech Incubators & Innovations", index: 0, count: 8, percentage: 31 },
      { option: "Agricultural Value Chains & Sustainable Trade", index: 1, count: 4, percentage: 15 },
      { option: "Diaspora Development Bonds & Investment Funds", index: 2, count: 10, percentage: 38 },
      { option: "Creative Industries & Global Cultural Exports", index: 3, count: 4, percentage: 15 }
    ],
    totalVotes: 26,
    userHasVoted: false,
    userVotedIndex: null
  }
};
await mkdir(join(PUBLIC, 'api'), { recursive: true });
await writeFile(join(PUBLIC, 'api', 'polls_active.json'), JSON.stringify(activePollData, null, 2), 'utf8');
await writeFile(join(PUBLIC, 'api', 'polls.json'), JSON.stringify(activePollData, null, 2), 'utf8');

console.log('\n✅ Build complete → public/');
console.log('   Run: npm start  →  http://localhost:8080\n');
