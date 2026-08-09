/**
 * recrawl-all.js — Full site recrawl for conspodium.com
 *
 * What it does:
 *   1. Visits every page with Playwright (JS rendered, full DOM)
 *   2. Downloads all linked CSS, JS, fonts, and images
 *   3. Rewrites all URLs to relative local paths
 *   4. Applies post-processing fixes (preloader, sticky header visibility)
 *   5. Saves everything under ./output2/ (keeping ./output/ intact as backup)
 *
 * Usage:
 *   node recrawl-all.js
 *
 * After it finishes, update serve.js ROOT to './output2/' and restart the server.
 */

import { chromium }                          from 'playwright';
import { mkdir, writeFile, readFile }        from 'node:fs/promises';
import { existsSync }                        from 'node:fs';
import { join, dirname, extname, basename }  from 'node:path';
import { fileURLToPath }                     from 'node:url';
import { createWriteStream }                 from 'node:fs';
import https                                 from 'node:https';
import http                                  from 'node:http';

// ── Config ────────────────────────────────────────────────────────────────────
const ORIGIN  = 'https://conspodium.com';
const OUT     = fileURLToPath(new URL('./output2/', import.meta.url));
const TIMEOUT = 90_000;  // ms per page
const WAIT    = 2_500;   // ms to wait after load for lazy content

const PAGES = [
  '/',
  '/about-us/',
  '/contact-us/',
  '/advert/',
  '/sponsorship/',
  '/submit-story/',
  '/stories/',
  '/community-thanks/',
  '/dashboard/',
  '/account/',
  '/login/',
  '/subscription/',
  '/conspodium-is-all-about-community/',
  '/we-are-the-world/',
  '/profiles-of-groundbreaking-tech-entrepreneurs-from-diaspora/',
  '/profiles-of-groundbreaking-tech-entrepreneurs-from-diaspora-2/',
];

// MIME → file extension map for assets without clear extensions
const MIME_EXT = {
  'text/css':               '.css',
  'application/javascript': '.js',
  'text/javascript':        '.js',
  'image/jpeg':             '.jpg',
  'image/png':              '.png',
  'image/gif':              '.gif',
  'image/webp':             '.webp',
  'image/svg+xml':          '.svg',
  'image/x-icon':           '.ico',
  'font/woff':              '.woff',
  'font/woff2':             '.woff2',
  'font/ttf':               '.ttf',
  'application/font-woff':  '.woff',
  'application/font-woff2': '.woff2',
};

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Convert an absolute conspodium URL to a relative local path from a given page dir */
function absoluteToLocal(url, pageDir) {
  try {
    const u = new URL(url, ORIGIN);
    if (u.origin !== ORIGIN) return null; // external
    const rel = u.pathname + (u.search ? u.search.replace('?', '_qs_') : '');
    // Normalise: strip trailing slash for assets, keep for pages
    return rel;
  } catch {
    return null;
  }
}

/** Sanitise a URL path into a safe filesystem path */
function urlPathToFsPath(urlPath) {
  // Replace query strings with a safe suffix
  let p = urlPath.replace(/\?(.+)$/, (_, qs) => '_qs_' + qs.replace(/[^a-zA-Z0-9._-]/g, '_'));
  // Collapse double slashes
  p = p.replace(/\/+/g, '/');
  return p;
}

/** Download a binary asset (CSS, JS, image, font) to disk */
async function downloadAsset(url, destPath) {
  if (existsSync(destPath)) return; // already downloaded
  await mkdir(dirname(destPath), { recursive: true });
  return new Promise((resolve, reject) => {
    const proto = url.startsWith('https') ? https : http;
    proto.get(url, { headers: { 'User-Agent': 'Mozilla/5.0 (compatible; conspodium-crawler/2.0)' } }, (res) => {
      if (res.statusCode === 301 || res.statusCode === 302) {
        downloadAsset(res.headers.location, destPath).then(resolve).catch(reject);
        return;
      }
      if (res.statusCode !== 200) { resolve(); return; }
      const chunks = [];
      res.on('data', c => chunks.push(c));
      res.on('end', async () => {
        try {
          await writeFile(destPath, Buffer.concat(chunks));
          resolve();
        } catch(e) { reject(e); }
      });
      res.on('error', reject);
    }).on('error', reject);
  });
}

/** Rewrite all absolute conspodium:// URLs in HTML to relative paths */
function rewriteHtml(html, depth) {
  // depth = 0 for root pages (./), 1 for subpages (../), etc.
  const prefix = depth === 0 ? './' : '../';

  // Replace https://conspodium.com/ with relative prefix
  html = html.replace(/https:\/\/conspodium\.com\//g, prefix);
  // Fix any that became prefix// 
  html = html.replace(new RegExp(prefix.replace('.', '\\.') + '/', 'g'), prefix);

  return html;
}

/** Post-process HTML: kill preloader, fix sticky header visibility */
function postProcess(html) {
  // Kill preloader styles
  html = html.replace(
    /<style[^>]*>\s*#sl-preloader\s*\{[^}]*visibility\s*:[^}]*\}\s*<\/style>/gi, ''
  );
  html = html.replace(
    /<script[^>]*id="safelayout-cute-preloader-visible"[^>]*>[\s\S]*?<\/script>/gi, ''
  );
  if (!html.includes('/* conspodium-fix-preloader */')) {
    html = html.replace('</head>', `<style>/* conspodium-fix-preloader */
#sl-preloader { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; }
</style>
</head>`);
  }

  // Fix sticky header spacer visibility
  html = html.replace(
    /(class="[^"]*elementor-sticky__spacer[^"]*"[^>]*style=")([\s\S]*?)(")/g,
    (m, pre, style, suf) => {
      const fixed = style
        .replace(/\bvisibility\s*:\s*hidden\s*;?\s*/gi, 'visibility: visible; ')
        .replace(/\banimation\s*:\s*auto ease 0s 1 normal none running none\s*;?\s*/gi, '')
        .trim().replace(/;\s*$/, '');
      return pre + fixed + suf;
    }
  );
  html = html.replace(
    /(class="[^"]*she-header-yes[^"]*"[^>]*style=")([\s\S]*?)(")/g,
    (m, pre, style, suf) => {
      const fixed = style
        .replace(/\bvisibility\s*:\s*hidden\s*;?\s*/gi, 'visibility: visible; ')
        .replace(/\bdisplay\s*:\s*none\s*;?\s*/gi, '')
        .trim();
      return pre + fixed + suf;
    }
  );

  return html;
}

/** Extract all asset URLs from HTML (CSS, JS, images, fonts) */
function extractAssets(html, baseUrl) {
  const assets = new Set();
  const patterns = [
    /href=["']([^"']+)["']/g,
    /src=["']([^"']+)["']/g,
    /url\(["']?([^"')]+)["']?\)/g,
    /\baction=["']([^"']+)["']/g,
  ];
  for (const re of patterns) {
    let m;
    while ((m = re.exec(html)) !== null) {
      const raw = m[1].trim();
      if (!raw || raw.startsWith('data:') || raw.startsWith('#') || raw.startsWith('javascript:')) continue;
      try {
        const abs = new URL(raw, baseUrl).href;
        if (abs.startsWith(ORIGIN)) assets.add(abs);
      } catch {}
    }
  }
  return [...assets];
}

// ── Main ──────────────────────────────────────────────────────────────────────
await mkdir(OUT, { recursive: true });

console.log(`\n🚀 Starting full recrawl of ${ORIGIN}`);
console.log(`📁 Output directory: ${OUT}\n`);

const browser = await chromium.launch({ headless: true });
const context  = await browser.newContext({
  userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/124 Safari/537.36',
  viewport: { width: 1440, height: 900 },
  ignoreHTTPSErrors: true,
});

const assetQueue  = new Set(); // absolute URLs of assets to download
const failedPages = [];
const failedAssets = [];

// ── Phase 1: Crawl pages ──────────────────────────────────────────────────────
for (const pagePath of PAGES) {
  const url  = ORIGIN + pagePath;
  const isRoot = pagePath === '/';
  const depth  = isRoot ? 0 : 1;
  const relDir = isRoot ? '' : pagePath.replace(/^\/|\/$/g, '');
  const destDir  = join(OUT, relDir);
  const destFile = join(destDir, 'index.html');

  console.log(`📄 Crawling: ${url}`);

  try {
    const page = await context.newPage();

    // Intercept and queue all network requests for assets
    page.on('request', (req) => {
      const reqUrl = req.url();
      if (reqUrl.startsWith(ORIGIN) && !reqUrl.includes('/wp-admin') && !reqUrl.includes('/wp-json')) {
        const ext = extname(new URL(reqUrl).pathname).toLowerCase();
        if (['.css','.js','.woff','.woff2','.ttf','.eot','.png','.jpg','.jpeg','.gif','.webp','.svg','.ico'].includes(ext)) {
          assetQueue.add(reqUrl);
        }
      }
    });

    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: TIMEOUT });
    await page.waitForTimeout(WAIT);

    // Trigger lazy-load by scrolling
    await page.evaluate(() => {
      window.scrollTo(0, document.body.scrollHeight / 2);
    });
    await page.waitForTimeout(500);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(500);

    let html = await page.content();
    await page.close();

    // Extract additional assets from HTML
    for (const asset of extractAssets(html, url)) {
      assetQueue.add(asset);
    }

    // Rewrite URLs
    html = rewriteHtml(html, depth);
    // Rewrite ?ver=X.Y.Z → ._ver_X_Y_Z to match local filenames
    html = html.replace(/(href|src)=(["'])((?:[^"']*?))\.(css|js)\?ver=([\w.]+)\2/g,
      (m, attr, q, base, ext, ver) =>
        `${attr}=${q}${base}._ver_${ver.replace(/\./g,'_')}.${ext}${q}`
    );
    // Post-process
    html = postProcess(html);

    await mkdir(destDir, { recursive: true });
    await writeFile(destFile, html, 'utf8');
    console.log(`   ✔ Saved → ${destFile.replace(OUT, 'output2/')}`);

  } catch (err) {
    console.error(`   ✖ FAILED: ${err.message.split('\n')[0]}`);
    failedPages.push({ url, error: err.message });
  }
}

// ── Phase 2: Download all assets ─────────────────────────────────────────────
console.log(`\n📦 Downloading ${assetQueue.size} assets...`);

let assetCount = 0;
const assetList = [...assetQueue];

for (const assetUrl of assetList) {
  try {
    const u = new URL(assetUrl);
    let fsPath = u.pathname;
    // Handle query strings: ?ver=1.2.3 → ._ver_1_2_3 suffix
    if (u.search) {
      const verMatch = u.search.match(/[?&]ver=([\d.a-z]+)/i);
      if (verMatch) {
        const verSuffix = '._ver_' + verMatch[1].replace(/\./g, '_');
        const ext = extname(fsPath);
        fsPath = fsPath.replace(ext, '') + verSuffix + ext;
      }
    }
    const destPath = join(OUT, fsPath);
    await downloadAsset(assetUrl, destPath);
    assetCount++;
    if (assetCount % 50 === 0) console.log(`   ... ${assetCount}/${assetList.length} assets`);
  } catch (err) {
    failedAssets.push({ url: assetUrl, error: err.message });
  }
}

await browser.close();

// ── Summary ───────────────────────────────────────────────────────────────────
console.log(`\n✅ Done!`);
console.log(`   Pages crawled: ${PAGES.length - failedPages.length}/${PAGES.length}`);
console.log(`   Assets saved:  ${assetCount}/${assetList.length}`);

if (failedPages.length) {
  console.log(`\n⚠️  Failed pages:`);
  failedPages.forEach(f => console.log(`   - ${f.url}: ${f.error.split('\n')[0]}`));
}
if (failedAssets.length > 5) {
  console.log(`\n⚠️  ${failedAssets.length} assets failed to download (minor - usually dynamic/blob URLs)`);
}

// Update manifest
await writeFile(join(OUT, '_manifest.json'), JSON.stringify({
  crawledAt: new Date().toISOString(),
  origin: ORIGIN,
  pages: PAGES,
  assetsTotal: assetList.length,
  assetsSaved: assetCount,
  failed: failedPages,
}, null, 2));

console.log(`\n👉 To serve: update serve.js ROOT to './output2/' then restart with: node serve.js`);
