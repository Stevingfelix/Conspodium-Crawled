/**
 * fix-header-issues.js
 *
 * Fixes two header bugs across all output2 HTML files:
 *  1. Desktop/tablet logo not showing (Elementor hidden-desktop class overrides)
 *  2. Mobile hamburger not pushed to the far right edge of the header
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

// The new CSS block that replaces the old one
const NEW_FIXES = `<style id="csp-header-fixes">
/* Hide the sticky spacer clone that creates a blank gap at top of page */
.elementor-sticky__spacer { display: none !important; }
/* Ensure the real fixed header is always visible */
.she-header-yes, .elementor-sticky { visibility: visible !important; }
/* Fix logo images collapsed to height:0 by JS at crawl time */
img.sticel-logo-shrink { height: auto !important; max-height: 80px !important; width: auto !important; opacity: 1 !important; }

/* ── LOGO FIX: Desktop/tablet logo must be visible on non-mobile screens ────── */
/* The desktop logo (738d6a4) has class elementor-hidden-mobile:
   Elementor CSS hides it ONLY on mobile (max-width:767px).
   But a bare .elementor-hidden-desktop{display:none} rule (no media query) in
   elementor CSS can bleed into all breakpoints. Restore correct behaviour: */

/* Desktop logo: always show on tablet + desktop, hide on mobile */
.elementor-element-738d6a4 {
  display: block !important;
}
.elementor-element-738d6a4 img,
.elementor-element-738d6a4 .elementor-widget-container {
  display: block !important;
  max-height: 68px !important;
  width: auto !important;
  height: auto !important;
  opacity: 1 !important;
}
/* Restore mobile-only hiding for the desktop logo */
@media (max-width: 767px) {
  .elementor-element-738d6a4 {
    display: none !important;
  }
}

/* Mobile square logo (2d4f26f): show ONLY on mobile, hide on tablet + desktop */
.elementor-element-2d4f26f {
  display: none !important;
}
@media (max-width: 767px) {
  .elementor-element-2d4f26f {
    display: block !important;
  }
  .elementor-element-2d4f26f img,
  .elementor-element-2d4f26f .elementor-widget-container {
    display: block !important;
    max-height: 52px !important;
    width: auto !important;
    opacity: 1 !important;
  }
}

/* ── HAMBURGER FIX: push burger to far right edge of header ─────────────────── */
/* The mobile nav container (27c23fa) wraps the burger toggle.
   Make it stretch to fill remaining space so the burger sits at the right edge. */
.elementor-element-27c23fa {
  flex: 1 !important;
  display: flex !important;
  justify-content: flex-end !important;
  align-items: center !important;
  padding-right: 0 !important;
  margin-left: 0 !important;
}
/* The toggle button itself */
.elementor-element-27c23fa .elementor-menu-toggle,
.elementor-element-27c23fa .elementor-nav-menu-toggle {
  margin-left: auto !important;
  margin-right: 0 !important;
}

</style>`;

// The marker for the old csp-header-fixes block
const START_MARKER = '<style id="csp-header-fixes">';
const END_MARKER = '</style>';

async function fixFile(filePath) {
  let html = await readFile(filePath, 'utf8');

  const startIdx = html.indexOf(START_MARKER);
  if (startIdx === -1) return false; // no header fixes block — skip

  // Find the end of this style block
  const endIdx = html.indexOf(END_MARKER, startIdx + START_MARKER.length);
  if (endIdx === -1) return false;

  const endFull = endIdx + END_MARKER.length;
  html = html.slice(0, startIdx) + NEW_FIXES + html.slice(endFull);

  await writeFile(filePath, html, 'utf8');
  return true;
}

const files = await walk(OUT);
let updated = 0;
for (const f of files) {
  const changed = await fixFile(f);
  if (changed) {
    console.log(`✔ Fixed: ${f.replace(OUT, 'output2/')}`);
    updated++;
  }
}

console.log(`\n✅ Done — updated ${updated} file(s).`);
console.log('Hard-refresh (Cmd+Shift+R) in your browser to see changes.');
