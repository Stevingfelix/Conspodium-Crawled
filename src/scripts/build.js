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
