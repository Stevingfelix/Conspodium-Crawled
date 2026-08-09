/**
 * fix-logo-final.js
 *
 * THE REAL FIX: Elementor's frontend.min.css has a bare global rule:
 *   .elementor-hidden-mobile { display: none }  (no media query)
 * This hides the desktop logo on ALL screens.
 * The correct rule (with media query) ALSO exists, but the bare one wins
 * because it appears later in the file and has equal specificity.
 *
 * Fix: Add a higher-specificity rule to the csp-header-fixes block that
 * restores display on tablet + desktop for elements with elementor-hidden-mobile.
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

const NEW_CSS = `<style id="csp-header-fixes">
/* ── Hide sticky spacer gap ── */
.elementor-sticky__spacer { display: none !important; }
.she-header-yes, .elementor-sticky { visibility: visible !important; }

/* ── REAL LOGO FIX ──────────────────────────────────────────────────────────
   Elementor's frontend.min.css has TWO rules for .elementor-hidden-mobile:
     1. (correct) @media (max-width:767px) { .elementor .elementor-hidden-mobile { display:none } }
     2. (WRONG)   .elementor-hidden-mobile { display:none }  <-- bare, no media query
   Rule #2 hides the desktop logo on ALL screen sizes.
   Fix: override it for tablet + desktop with higher specificity.
────────────────────────────────────────────────────────────────────────────── */
@media (min-width: 768px) {
  .elementor .elementor-hidden-mobile,
  .elementor-hidden-mobile {
    display: block !important;
  }
}

/* ── Hamburger to far right — mobile only ── */
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

  html = html.slice(0, startIdx) + NEW_CSS + html.slice(endIdx + END_MARKER.length);
  await writeFile(filePath, html, 'utf8');
  return true;
}

const files = await walk(OUT);
let updated = 0;
for (const f of files) {
  if (await fixFile(f)) {
    console.log(`✔ ${f.replace(OUT, 'output2/')}`);
    updated++;
  }
}
console.log(`\n✅ Done — ${updated} files updated. Hard-refresh (Cmd+Shift+R).`);
