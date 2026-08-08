// Conspodium full-site clone crawler.
// Renders each page in headless Chromium, captures the rendered HTML, and saves
// every CSS/JS/image/font/media asset the page requests — including cross-origin
// (CDN, Google Fonts) assets — into a local mirror, rewriting URLs so the clone
// opens offline.

import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';

const ORIGIN = 'https://conspodium.com';
const OUT = new URL('./output/', import.meta.url).pathname;

// Pages to capture (from the WordPress sitemaps). Auth/payment pages are
// included so we capture their public markup, but they will only render their
// logged-out state.
const PAGES = [
  '/', '/about-us/', '/contact-us/', '/advert/', '/sponsorship/',
  '/submit-story/', '/stories/', '/community-thanks/', '/dashboard/',
  '/account/', '/edit/', '/login/', '/subscription/', '/payment/',
  '/thank-you/', '/order-received/', '/submit-story-admin/',
  // posts
  '/conspodium-is-all-about-community/', '/we-are-the-world/',
  '/profiles-of-groundbreaking-tech-entrepreneurs-from-diaspora/',
  '/profiles-of-groundbreaking-tech-entrepreneurs-from-diaspora-2/',
];

const ASSET_TYPES = new Set([
  'stylesheet', 'script', 'image', 'font', 'media', 'manifest',
]);

// Map a remote URL -> local mirror path. Cross-origin assets go under
// _ext/<host>/... so nothing collides.
function localPathFor(urlStr) {
  const u = new URL(urlStr);
  let p = u.pathname;
  if (p.endsWith('/')) p += 'index.html';
  // include query hash so ?ver=... variants don't overwrite each other
  if (u.search) {
    const dot = p.lastIndexOf('.');
    const q = u.search.replace(/[^a-z0-9]/gi, '_').slice(0, 40);
    p = dot > -1 ? `${p.slice(0, dot)}.${q}${p.slice(dot)}` : `${p}.${q}`;
  }
  if (u.origin === ORIGIN) return p.replace(/^\//, '');
  return join('_ext', u.host, p.replace(/^\//, ''));
}

const savedAssets = new Map(); // remoteURL -> localPath
const failed = [];

async function save(localPath, buf) {
  const full = join(OUT, localPath);
  await mkdir(dirname(full), { recursive: true });
  await writeFile(full, buf);
}

const browser = await chromium.launch();
const context = await browser.newContext({
  userAgent:
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 ' +
    '(KHTML, like Gecko) Chrome/124.0 Safari/537.36',
  viewport: { width: 1440, height: 900 },
});

// Capture every asset response across all pages.
context.on('response', async (resp) => {
  try {
    const req = resp.request();
    const type = req.resourceType();
    if (!ASSET_TYPES.has(type)) return;
    const url = req.url();
    if (url.startsWith('data:')) return;
    if (savedAssets.has(url)) return;
    if (!resp.ok()) return;
    const buf = await resp.body();
    const lp = localPathFor(url);
    savedAssets.set(url, lp);
    await save(lp, buf);
  } catch (e) {
    failed.push({ url: resp.url(), error: String(e) });
  }
});

for (const path of PAGES) {
  const url = ORIGIN + path;
  const page = await context.newPage();
  try {
    console.log(`-> ${url}`);
    // 'commit' fires on first bytes — avoids hanging when a blocking <head>
    // script stalls the parser. Assets keep streaming in during the waits below.
    await page.goto(url, { waitUntil: 'commit', timeout: 60000 });
    await page.waitForLoadState('domcontentloaded', { timeout: 20000 }).catch(() => {});
    await page.waitForLoadState('load', { timeout: 20000 }).catch(() => {});
    await page.waitForTimeout(3000);
    // trigger lazy-loaded images
    await page.evaluate(async () => {
      await new Promise((r) => {
        let y = 0;
        const t = setInterval(() => {
          window.scrollBy(0, 600);
          y += 600;
          if (y > document.body.scrollHeight + 1200) {
            clearInterval(t);
            r();
          }
        }, 100);
      });
    });
    await page.waitForTimeout(1200);

    // Save rendered HTML. We rewrite asset URLs to local paths in a second
    // pass after the crawl (so all assets are known); for now store raw HTML
    // plus the page's local path.
    const html = await page.content();
    let pagePath = path === '/' ? 'index.html' : path.replace(/^\//, '') + 'index.html';
    if (pagePath.endsWith('/index.html') === false && pagePath.endsWith('index.html') === false) {
      pagePath += '/index.html';
    }
    await save(pagePath, Buffer.from(html, 'utf8'));
  } catch (e) {
    console.log(`   browser load failed (${e.message.split('\n')[0]}); trying fetch fallback…`);
    // Fallback: the live site is occasionally slow to first-byte and the browser
    // times out. A plain fetch still returns the full server-rendered WordPress
    // HTML, so we never leave a page uncaptured (or a stale file from a prior run).
    try {
      const resp = await fetch(url, { headers: { 'user-agent': 'Mozilla/5.0 clone-bot' } });
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      const html = await resp.text();
      let pagePath = path === '/' ? 'index.html' : path.replace(/^\//, '') + 'index.html';
      await save(pagePath, Buffer.from(html, 'utf8'));
      console.log(`   fetch fallback OK`);
    } catch (e2) {
      console.log(`   FAILED (fallback too): ${e2.message}`);
      failed.push({ url, error: String(e) + ' | fallback: ' + String(e2) });
    }
  } finally {
    await page.close();
  }
}

await browser.close();

// Write a manifest so the rewrite pass knows every remote->local mapping.
await save(
  '_manifest.json',
  Buffer.from(
    JSON.stringify(
      {
        origin: ORIGIN,
        pages: PAGES,
        assets: Object.fromEntries(savedAssets),
        failed,
      },
      null,
      2
    ),
    'utf8'
  )
);

console.log(`\nDone. Pages: ${PAGES.length}, assets saved: ${savedAssets.size}, failures: ${failed.length}`);
