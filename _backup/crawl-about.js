/**
 * crawl-about.js — Re-crawl the about-us page which timed out in the original crawl.
 * Saves it as output/about-us/index.html and applies the same local-URL rewrites
 * and fixes (preloader kill, header inject) that the rest of the clone uses.
 */

import { chromium } from 'playwright';
import { writeFile, mkdir } from 'node:fs/promises';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const OUT = fileURLToPath(new URL('./output/', import.meta.url));
const TARGET = 'https://conspodium.com/about-us/';
const ORIGIN  = 'https://conspodium.com';

// ── helper: rewrite absolute conspodium URLs to relative local paths ─────────
function rewriteHtml(html, pageDir) {
  // Replace absolute origin URLs with relative paths from the about-us/ dir
  html = html
    .replace(/https:\/\/conspodium\.com\//g, '../')
    .replace(/href="\/about-us\/"/g, 'href="./index.html"')
    .replace(/href="\/"/g, 'href="../index.html"')
    // Fix any double-slash artefacts
    .replace(/src="\.\.\/\//g, 'src="../')
    .replace(/href="\.\.\/\//g, 'href="../');

  // Kill preloader
  html = html.replace(
    /<style[^>]*>\s*#sl-preloader\s*\{[^}]*visibility\s*:[^}]*\}\s*<\/style>/gi,
    ''
  );
  html = html.replace(
    /<script[^>]*id="safelayout-cute-preloader-visible"[^>]*>[\s\S]*?<\/script>/gi,
    ''
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

  // Fix elementor sticky spacer visibility
  html = html.replace(
    /(class="[^"]*elementor-sticky__spacer[^"]*"[^>]*style=")([\s\S]*?)(")/g,
    (match, prefix, styleValue, suffix) => {
      const cleaned = styleValue
        .replace(/\bvisibility\s*:\s*hidden\s*;?\s*/gi, 'visibility: visible; ')
        .replace(/\banimation\s*:\s*auto ease 0s 1 normal none running none\s*;?\s*/gi, '')
        .trim()
        .replace(/;\s*$/, '');
      return prefix + cleaned + suffix;
    }
  );

  return html;
}

// ── inject the custom CSP header (same one used on index.html) ───────────────
// We'll read it from index.html so it's always in sync
import { readFile } from 'node:fs/promises';

const indexHtml = await readFile(join(OUT, 'index.html'), 'utf8');
const cspHeaderMatch = indexHtml.match(/<!-- CSP_HEADER_BLOCK_START -->[\s\S]*?<!-- CSP_HEADER_BLOCK_END -->/);
const cspBlock = cspHeaderMatch ? cspHeaderMatch[0] : '';

// Adjust nav active state: about-us should be active, not home
function adjustActiveNav(block) {
  return block
    .replace(/class="csp-nav-link csp-nav-active"\s*>Home/g, 'class="csp-nav-link">Home')
    .replace(/class="csp-nav-link"\s*>About Us/g, 'class="csp-nav-link csp-nav-active">About Us');
}

const cspBlockForAbout = adjustActiveNav(cspBlock);

// ── crawl ────────────────────────────────────────────────────────────────────
console.log(`Launching browser and navigating to ${TARGET}…`);
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120 Safari/537.36',
  viewport: { width: 1440, height: 900 },
});
const page = await context.newPage();

try {
  await page.goto(TARGET, { waitUntil: 'domcontentloaded', timeout: 90_000 });
  // Wait a bit for lazy content
  await page.waitForTimeout(3000);

  let html = await page.content();

  // Rewrite URLs
  html = rewriteHtml(html, 'about-us');

  // Inject the CSP header block right after <body ...>
  if (cspBlockForAbout) {
    html = html.replace(/(<body[^>]*>)/, `$1\n${cspBlockForAbout}`);
    // Remove original WP header that may still be present
    // (The CSP block is self-contained and already has z-index:99999)
  }

  // Save
  const destDir = join(OUT, 'about-us');
  await mkdir(destDir, { recursive: true });
  await writeFile(join(destDir, 'index.html'), html, 'utf8');
  console.log('✔ Saved output/about-us/index.html');

} catch (err) {
  console.error('✖ Failed:', err.message);
} finally {
  await browser.close();
}
