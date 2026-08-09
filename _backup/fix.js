/**
 * fix.js — Patch the conspodium clone for correct local rendering.
 *
 * Fixes applied to every *.html file under output/:
 *
 *  1. PRELOADER  — The WordPress plugin bakes `#sl-preloader{visibility:visible!important}`
 *     into the HTML via a <style> tag and a <script>. Without the live WP backend JS,
 *     this preloader overlay never gets dismissed. We strip/neutralise it.
 *
 *  2. STICKY HEADER VISIBILITY — Elementor's sticky-header JS runs on the live site and
 *     sets `visibility:hidden` on the second nav container (elementor-sticky__spacer) as
 *     part of its initialisation. The snapshot captures this hidden state. We force
 *     `visibility:visible` so the header always shows on load.
 *
 *  3. ICON FONT PATH — The jkiticon CSS uses relative font URLs like `jkiticon.woff2?bv8d8l`
 *     but the crawled files were saved with the query string encoded into the filename
 *     as `jkiticon._bv8d8l.woff2`. We patch the @font-face declaration in the CSS to
 *     point at the correct local filenames.
 */

import { readFile, writeFile, readdir, stat } from 'node:fs/promises';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const OUT = fileURLToPath(new URL('./output/', import.meta.url));

// ─── helpers ────────────────────────────────────────────────────────────────

async function walk(dir) {
  const results = [];
  for (const name of await readdir(dir)) {
    const full = join(dir, name);
    const s = await stat(full);
    if (s.isDirectory()) results.push(...(await walk(full)));
    else results.push(full);
  }
  return results;
}

// ─── Fix 3: icon font CSS ────────────────────────────────────────────────────
// The crawled jkiticon CSS has relative paths like:
//   url(jkiticon.woff2?bv8d8l)
// but the font files on disk are named:
//   jkiticon._bv8d8l.woff2
// Patch the @font-face block so the fonts actually load locally.

const jkiCssPath = join(
  OUT,
  'wp-content/plugins/jeg-elementor-kit/assets/fonts/jkiticon/jkiticon._ver_3_1_2.css'
);

let jkiCss = await readFile(jkiCssPath, 'utf8');
const jkiCssBefore = jkiCss;

jkiCss = jkiCss
  // eot
  .replace(/url\(jkiticon\.eot\?bv8d8l\)/g, 'url(jkiticon._bv8d8l.eot)')
  .replace(/url\(jkiticon\.eot\?bv8d8l#iefix\) format\("embedded-opentype"\)/g,
           'url(jkiticon._bv8d8l.eot) format("embedded-opentype")')
  // woff2
  .replace(/url\(jkiticon\.woff2\?bv8d8l\) format\("woff2"\)/g,
           'url(jkiticon._bv8d8l.woff2) format("woff2")')
  // woff
  .replace(/url\(jkiticon\.woff\?bv8d8l\) format\("woff"\)/g,
           'url(jkiticon._bv8d8l.woff) format("woff")')
  // ttf — not crawled but keep reference pointing at something sensible
  .replace(/url\(jkiticon\.ttf\?bv8d8l\) format\("truetype"\)/g,
           'url(jkiticon._bv8d8l.woff) format("woff")')
  // svg — not crawled, drop gracefully
  .replace(/url\(jkiticon\.svg\?bv8d8l#jkiticon\) format\("svg"\)/g,
           'url(jkiticon._bv8d8l.woff) format("woff")');

if (jkiCss !== jkiCssBefore) {
  await writeFile(jkiCssPath, jkiCss);
  console.log('✔ Fixed jkiticon font paths in CSS');
} else {
  console.log('ℹ jkiticon CSS already patched (skipped)');
}

// ─── Fix 1 & 2: HTML patches ─────────────────────────────────────────────────

const htmlFiles = (await walk(OUT)).filter((f) => /\.html?$/i.test(f));
let editedCount = 0;

for (const file of htmlFiles) {
  let html = await readFile(file, 'utf8');
  const before = html;

  // ── Fix 1: Preloader ──────────────────────────────────────────────────────
  // Remove the inline <style> that forces the preloader visible.
  // Pattern: <style>#sl-preloader{visibility: visible !important;}</style>
  html = html.replace(
    /<style[^>]*>\s*#sl-preloader\s*\{[^}]*visibility\s*:[^}]*\}\s*<\/style>/gi,
    ''
  );

  // Also remove the inline <script> that dynamically creates the same style tag.
  // Pattern: <script id="safelayout-cute-preloader-visible" ...>var vStyle = ...
  html = html.replace(
    /<script[^>]*id="safelayout-cute-preloader-visible"[^>]*>[\s\S]*?<\/script>/gi,
    ''
  );

  // As a belt-and-suspenders, add a style override right before </head> that
  // hides the preloader and removes any animation that keeps it alive.
  if (!html.includes('/* conspodium-fix-preloader */')) {
    html = html.replace(
      '</head>',
      `<style>/* conspodium-fix-preloader */
#sl-preloader { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; }
</style>
</head>`
    );
  }

  // ── Fix 2: Sticky header visibility ──────────────────────────────────────
  // The elementor sticky spacer div has an inline style with visibility:hidden.
  // Strip that specific property from the inline style while keeping others intact.
  // The class pattern: elementor-sticky elementor-sticky__spacer
  html = html.replace(
    /(class="[^"]*elementor-sticky__spacer[^"]*"[^>]*style=")([\s\S]*?)(")/g,
    (match, prefix, styleValue, suffix) => {
      const cleaned = styleValue
        // Remove `visibility: hidden`
        .replace(/\bvisibility\s*:\s*hidden\s*;?\s*/gi, 'visibility: visible; ')
        // Remove `animation: auto ease 0s 1 normal none running none` (frozen state)
        .replace(/\banimation\s*:\s*auto ease 0s 1 normal none running none\s*;?\s*/gi, '')
        .trim()
        .replace(/;\s*$/, '');
      return prefix + cleaned + suffix;
    }
  );

  // Also ensure the non-spacer sticky header div (she-header-yes) is visible.
  // These sometimes have `display:none` applied by JS during scroll capture.
  html = html.replace(
    /(class="[^"]*she-header-yes[^"]*"[^>]*style=")([\s\S]*?)(")/g,
    (match, prefix, styleValue, suffix) => {
      const cleaned = styleValue
        .replace(/\bvisibility\s*:\s*hidden\s*;?\s*/gi, 'visibility: visible; ')
        .replace(/\bdisplay\s*:\s*none\s*;?\s*/gi, '')
        .trim();
      return prefix + cleaned + suffix;
    }
  );

  if (html !== before) {
    await writeFile(file, html);
    editedCount++;
  }
}

console.log(`✔ Fixed ${editedCount} of ${htmlFiles.length} HTML files (preloader + sticky header)`);
console.log('\nAll fixes applied. Refresh http://localhost:8080 to see the result.');
