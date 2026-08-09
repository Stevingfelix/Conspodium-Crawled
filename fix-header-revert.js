/**
 * fix-header-revert.js
 *
 * REVERTS my bad CSS changes and applies two minimal, correct fixes:
 *  1. Logo visible on desktop/tablet (was collapsing due to img height=0)
 *  2. Hamburger pushed further right on mobile ONLY (using media query)
 */

import { readFile, writeFile, readdir, stat } from 'node:fs/promises';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const OUT = fileURLToPath(new URL('./output2/', import.meta.url));

async function walk(dir) {
  const results = [];
  for (const name of await readdir(dir)) {
    const full = join(dir, name);
    const s = await stat(full);
    if (s.isDirectory()) results.push(...(await walk(full)));
    else if (full.endsWith('.html')) results.push(full);
  }
  return results;
}

// ── The correct, minimal CSS fixes ───────────────────────────────────────────
// This is the ORIGINAL working block + only the two safe targeted fixes.
// NO changes to display/flex/visibility of the mobile nav container on desktop.
const CORRECT_FIXES = `<style id="csp-header-fixes">
/* Hide the sticky spacer clone that creates a blank gap at top of page */
.elementor-sticky__spacer { display: none !important; }
/* Ensure the real fixed header is always visible */
.she-header-yes, .elementor-sticky { visibility: visible !important; }
/* Fix logo images collapsed to height:0 by JS at crawl time */
img.sticel-logo-shrink { height: auto !important; max-height: 80px !important; width: auto !important; display: block !important; opacity: 1 !important; }

/* ── FIX 1: Desktop/Tablet logo ─────────────────────────────────────────── */
/* The logo image gets its height zeroed by the crawl-time JS (sticel shrink).
   Force it back to visible. Only target the desktop/tablet logo element. */
.elementor-widget__width-initial { width: auto !important; }
.elementor-element-738d6a4 {
  visibility: visible !important;
  opacity: 1 !important;
}
.elementor-element-738d6a4 img,
.elementor-element-738d6a4 .elementor-widget-container {
  display: block !important;
  height: auto !important;
  max-height: 68px !important;
  width: auto !important;
  min-height: 0 !important;
  opacity: 1 !important;
}

/* ── FIX 2: Hamburger to far right edge — MOBILE ONLY ───────────────────── */
/* Only apply inside the mobile breakpoint so we don't touch desktop layout */
@media (max-width: 767px) {
  .elementor-element-27c23fa {
    margin-left: auto !important;
  }
  .elementor-element-27c23fa .elementor-menu-toggle {
    margin-left: auto !important;
    margin-right: 4px !important;
  }
}

</style>`;

const START_MARKER = '<style id="csp-header-fixes">';
const END_MARKER   = '</style>';

async function fixFile(filePath) {
  let html = await readFile(filePath, 'utf8');

  const startIdx = html.indexOf(START_MARKER);
  if (startIdx === -1) return false;

  const endIdx = html.indexOf(END_MARKER, startIdx + START_MARKER.length);
  if (endIdx === -1) return false;

  html = html.slice(0, startIdx) + CORRECT_FIXES + html.slice(endIdx + END_MARKER.length);
  await writeFile(filePath, html, 'utf8');
  return true;
}

const files = await walk(OUT);
let updated = 0;
for (const f of files) {
  if (await fixFile(f)) {
    console.log(`✔ Restored: ${f.replace(OUT, 'output2/')}`);
    updated++;
  }
}

console.log(`\n✅ Done — restored ${updated} file(s).`);
console.log('Hard-refresh (Cmd+Shift+R) in browser.');
