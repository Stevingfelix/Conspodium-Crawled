/**
 * fix-logo-desktop.js
 *
 * Two precise fixes:
 *  1. Logo on desktop/tablet: The sticky-elementor JS sets inline style="height:0px"
 *     on the logo image because it captured offsetHeight=0 at crawl time.
 *     Fix: patch data-original-height in the HTML so JS restores the correct height,
 *     AND inject a MutationObserver script that watches for the style change and resets it.
 *  2. Hamburger right edge on mobile: use margin-left:auto ONLY inside mobile breakpoint.
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

// ── CSS block (same working one we had before) ──────────────────────────────
const CORRECT_CSS = `<style id="csp-header-fixes">
/* Hide the sticky spacer clone that creates a blank gap at top of page */
.elementor-sticky__spacer { display: none !important; }
/* Ensure the real fixed header is always visible */
.she-header-yes, .elementor-sticky { visibility: visible !important; }
/* Restore logo image display - JS may zero the height, but this ensures layout */
img.sticel-logo-shrink { height: auto !important; max-height: 80px !important; width: auto !important; display: block !important; opacity: 1 !important; }
/* Desktop logo container */
.elementor-widget__width-initial { width: auto !important; }
/* Hamburger to far right edge — MOBILE ONLY */
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

// ── Script injected at end of <body> ─────────────────────────────────────────
// This MutationObserver watches for the sticky JS zeroing the logo height
// and immediately resets it to the correct natural height.
const LOGO_FIX_SCRIPT = `<script id="csp-logo-fix">
(function() {
  // IDs of the logo image elements to protect
  var LOGO_SELECTORS = [
    '.elementor-element-738d6a4 img',  // desktop/tablet horizontal logo
  ];

  function fixLogoHeight(img) {
    // Read the natural height from the img element's intrinsic size
    // or from data-original-height if it was correctly captured (non-zero)
    var storedH = parseInt(img.getAttribute('data-original-height') || '0', 10);
    var naturalH = img.naturalHeight;
    var targetH  = (storedH > 10) ? storedH : (naturalH > 0 ? Math.min(naturalH, 68) : 68);

    // Only override if style is zeroed
    if (img.style.height === '0px' || img.style.height === '0') {
      img.style.height = targetH + 'px';
    }
    // Also patch data-original-height so the sticky JS itself uses the right value
    if (storedH < 10) {
      img.setAttribute('data-original-height', targetH);
    }
  }

  function patchAllLogos() {
    LOGO_SELECTORS.forEach(function(sel) {
      document.querySelectorAll(sel).forEach(fixLogoHeight);
    });
  }

  // Run on load
  document.addEventListener('DOMContentLoaded', patchAllLogos);
  window.addEventListener('load', patchAllLogos);

  // MutationObserver: catch the sticky JS zeroing height mid-scroll
  var observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(m) {
      if (m.type === 'attributes' && m.attributeName === 'style') {
        var el = m.target;
        if (el.classList.contains('sticel-logo-shrink')) {
          fixLogoHeight(el);
        }
      }
    });
  });

  // Start observing once the DOM is ready
  document.addEventListener('DOMContentLoaded', function() {
    LOGO_SELECTORS.forEach(function(sel) {
      document.querySelectorAll(sel).forEach(function(img) {
        observer.observe(img, { attributes: true, attributeFilter: ['style'] });
      });
    });
    patchAllLogos();
  });
})();
</script>`;

const CSS_START = '<style id="csp-header-fixes">';
const CSS_END   = '</style>';
const SCRIPT_MARKER = '<script id="csp-logo-fix">';

async function fixFile(filePath) {
  let html = await readFile(filePath, 'utf8');
  let changed = false;

  // 1. Replace the CSS block
  const cssStart = html.indexOf(CSS_START);
  if (cssStart !== -1) {
    const cssEnd = html.indexOf(CSS_END, cssStart + CSS_START.length);
    if (cssEnd !== -1) {
      html = html.slice(0, cssStart) + CORRECT_CSS + html.slice(cssEnd + CSS_END.length);
      changed = true;
    }
  }

  // 2. Also patch data-original-height="0" to data-original-height="68"
  //    for the desktop logo image only (wp-image-990 is the desktop logo)
  const before = html;
  html = html.replace(
    /(class="[^"]*wp-image-990[^"]*"[^>]*data-original-height=")0"/g,
    '$168"'
  );
  if (html !== before) changed = true;

  // 3. Inject the MutationObserver script before </body> (only once)
  if (!html.includes(SCRIPT_MARKER)) {
    html = html.replace('</body>', LOGO_FIX_SCRIPT + '\n</body>');
    changed = true;
  }

  if (changed) {
    await writeFile(filePath, html, 'utf8');
  }
  return changed;
}

const files = await walk(OUT);
let updated = 0;
for (const f of files) {
  if (await fixFile(f)) {
    console.log(`✔ Fixed: ${f.replace(OUT, 'output2/')}`);
    updated++;
  }
}

console.log(`\n✅ Done — updated ${updated} file(s).`);
console.log('Hard-refresh (Cmd+Shift+R) in browser to see the logo on desktop.');
