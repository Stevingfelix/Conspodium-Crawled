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
  { id: 'dashboard',    out: 'dashboard/index.html' },
];

/** Recursively copy a directory */
async function copyDir(src, dest) {
  await mkdir(dest, { recursive: true });
  await cp(src, dest, { recursive: true, force: true });
}

// ── Main build process ───────────────────────────────────────────────────────

console.log('\n🔨 Conspodium Build\n');

// Step 1 — Copy static assets (wp-content, wp-includes, fonts, images)
process.stdout.write('  1. Copying assets (src/assets → public)... ');
if (existsSync(ASSETS)) {
  await copyDir(ASSETS, PUBLIC);
  console.log('✓');
} else {
  console.log('⚠ (src/assets not found)');
}

// Step 2 — Load shared CSS fix component
const headerFixesHtml = existsSync(join(SRC, 'components/header-fixes.html'))
  ? await readFile(join(SRC, 'components/header-fixes.html'), 'utf8')
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

  // Clean out any legacy or duplicate header fix blocks and scripts
  html = html.replace(/<!-- CSP_HEADER_FIXES_START -->[\s\S]*?<!-- CSP_HEADER_FIXES_END -->\n?/g, '');
  html = html.replace(/<style id="csp-header-fixes">[\s\S]*?<\/style>\n?/g, '');
  html = html.replace(/<script id="csp-mobile-menu-script">[\s\S]*?<\/script>\n?/g, '');

  if (headerFixesHtml) {
    html = html.replace('</head>', headerFixesHtml + '\n</head>');
    await writeFile(srcFile, html, 'utf8');
  }

  await mkdir(dirname(outFile), { recursive: true });
  await writeFile(outFile, html, 'utf8');
  console.log(`     ✓  ${page.id.padEnd(16)} → public/${page.out}`);
}

console.log('\n✅ Build complete → public/');
console.log('   Run: npm start  →  http://localhost:8080\n');
