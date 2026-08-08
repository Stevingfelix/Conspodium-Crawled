/**
 * fix-about.js — Fix the about-us page CSS paths + inject the CSP header.
 *
 * Problems:
 *  1. about-us/index.html was crawled live, so CSS hrefs have ?ver=X.Y.Z query strings.
 *     Local files use ._ver_X_Y_Z naming. This script rewrites them to match.
 *  2. The CSP custom header needs to be injected (same as all other pages), with
 *     "About Us" marked as the active nav item.
 *  3. The button padding fix needs to be applied inside the injected CSP block.
 */

import { readFile, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const OUT   = fileURLToPath(new URL('./output/', import.meta.url));
const FILE  = join(OUT, 'about-us/index.html');

// ── Step 1: Read about-us page ───────────────────────────────────────────────
let html = await readFile(FILE, 'utf8');

// ── Step 2: Rewrite CSS/JS href/src ?ver=X.Y.Z  →  ._ver_X_Y_Z ─────────────
// Pattern: something.css?ver=1.2.3  →  something._ver_1_2_3.css
// And:     something.js?ver=1.2.3   →  something._ver_1_2_3.js
html = html.replace(
  /(["'])((?:\.\.\/)*[\w\-./]+?)\.(css|js)\?ver=([\d.]+)(["'])/g,
  (match, q1, base, ext, ver, q2) => {
    const verSuffix = '._ver_' + ver.replace(/\./g, '_');
    return `${q1}${base}${verSuffix}.${ext}${q2}`;
  }
);

// ── Step 3: Read the CSP header block from index.html ───────────────────────
const indexHtml = await readFile(join(OUT, 'index.html'), 'utf8');
const cspMatch = indexHtml.match(/<!-- CSP_HEADER_BLOCK_START -->[\s\S]*?<!-- CSP_HEADER_BLOCK_END -->/);

if (cspMatch) {
  let cspBlock = cspMatch[0];

  // Fix nav active state: home → inactive, about-us → active
  cspBlock = cspBlock
    .replace(/class="csp-nav-link csp-nav-active"(\s*)>Home/g,
             'class="csp-nav-link"$1>Home')
    .replace(/class="csp-nav-link"(\s*)>About Us/g,
             'class="csp-nav-link csp-nav-active"$1>About Us');

  // Remove any existing CSP block already in the about-us page (from crawl-about.js)
  html = html.replace(/<!-- CSP_HEADER_BLOCK_START -->[\s\S]*?<!-- CSP_HEADER_BLOCK_END -->/g, '');

  // Inject fresh CSP block right after <body ...>
  html = html.replace(/(<body[^>]*>)/, `$1\n${cspBlock}`);
  console.log('✔ CSP header injected (About Us active)');
} else {
  console.warn('⚠ Could not find CSP header block in index.html');
}

// ── Step 4: Kill preloader (belt-and-suspenders) ─────────────────────────────
html = html.replace(
  /<style[^>]*>\s*#sl-preloader\s*\{[^}]*visibility\s*:[^}]*\}\s*<\/style>/gi, ''
);
if (!html.includes('/* conspodium-fix-preloader */')) {
  html = html.replace(
    '</head>',
    `<style>/* conspodium-fix-preloader */
#sl-preloader { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; }
</style>
</head>`
  );
}

// ── Step 5: Fix padding on the .csp-submit-btn inside the about-us page ──────
// The injected CSP block may have the old 12px value; normalize it.
html = html.replace(/\.csp-submit-btn\s*\{([^}]*?)padding:\s*12px\s+28px/g,
  (m, before) => `.csp-submit-btn {${before}padding: 9px 24px`);

// ── Step 6: Save ─────────────────────────────────────────────────────────────
await writeFile(FILE, html, 'utf8');
console.log('✔ Saved output/about-us/index.html');
console.log('\nRefresh http://localhost:8080/about-us/ to verify.');
