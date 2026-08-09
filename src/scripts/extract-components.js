/**
 * src/scripts/extract-components.js
 *
 * Extracts the real WordPress/Elementor header, footer, page body, and head
 * from output2/index.html and saves them as reusable components.
 *
 * Run: node src/scripts/extract-components.js
 * (Also called automatically by build.js)
 */

import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { join, dirname } from 'node:path';

const __dir      = dirname(fileURLToPath(import.meta.url));
const ROOT       = join(__dir, '../..');
const OUT2       = join(ROOT, 'output2');
const COMPONENTS = join(ROOT, 'src/components');

// ── Utility: extract a top-level <div> block by searching for a marker ────────
function extractDiv(html, marker) {
  const idx = html.indexOf(marker);
  if (idx === -1) return '';

  // Walk backwards from marker to opening <
  let s = idx;
  while (s > 0 && html[s] !== '<') s--;

  // Count <div vs </div to find the matching end tag
  let depth = 0, i = s;
  while (i < html.length) {
    if (html.slice(i, i + 4) === '<div') {
      depth++;
      i += 4;
    } else if (html.slice(i, i + 6) === '</div>') {
      depth--;
      if (depth === 0) return html.slice(s, i + 6);
      i += 6;
    } else {
      i++;
    }
  }
  return '';
}

// ── Utility: remove a single <div data-id="X"> block from HTML ───────────────
function removeDivById(html, dataId) {
  const marker = `data-id="${dataId}"`;
  const idx = html.indexOf(marker);
  if (idx === -1) return html;

  let s = idx;
  while (s > 0 && html[s] !== '<') s--;

  let depth = 0, i = s;
  while (i < html.length) {
    if (html.slice(i, i + 4) === '<div') { depth++; i += 4; }
    else if (html.slice(i, i + 6) === '</div>') {
      depth--;
      if (depth === 0) return html.slice(0, s) + html.slice(i + 6);
      i += 6;
    } else { i++; }
  }
  return html;
}

// ── Utility: strip all <section id="csp-*"> blocks (our custom injected sections) ──
// output2/ may have been polluted by earlier build runs — this cleans it up.
function stripCspSections(html) {
  // Iteratively remove sections while any remain
  let changed = true;
  while (changed) {
    changed = false;
    // Match <section ... id="csp-anything" ...> ... </section>
    const re = /<section[^>]*\sid="csp-[^"]*"[^>]*>([\s\S]*?)<\/section>/;
    const m = html.match(re);
    if (m) {
      // If the matched block itself contains </section>, we may have cut short.
      // Count to find the right closing </section>.
      const start = html.indexOf(m[0]);
      let depth = 0, i = start;
      while (i < html.length) {
        if (html.slice(i, i+8) === '<section') { depth++; i += 8; }
        else if (html.slice(i, i+10) === '</section>') {
          depth--;
          if (depth === 0) {
            html = html.slice(0, start) + html.slice(i + 10);
            changed = true;
            break;
          }
          i += 10;
        } else { i++; }
      }
    }
  }
  return html;
}

export async function extractComponents() {
  const html = await readFile(join(OUT2, 'index.html'), 'utf8');


  // ── <head> ────────────────────────────────────────────────────────────────
  const headStart = html.indexOf('<head');
  const headEnd   = html.indexOf('</head>') + 7;
  let wpHead      = html.slice(headStart, headEnd);
  // Strip old csp-* style/script blocks baked into output2 by earlier build runs
  wpHead = wpHead.replace(/<style[^>]*id="csp-[^"]*"[^>]*>[\s\S]*?<\/style>/g, '');
  wpHead = wpHead.replace(/<style>\/\* conspodium-fix-preloader \*\/[\s\S]*?<\/style>/g, '');
  wpHead = wpHead.replace(/<script>\/\* Reset header[\s\S]*?<\/script>/g, '');
  wpHead = wpHead.replace(/<script>\/\* conspodium[\s\S]*?<\/script>/g, '');

  // ── WP Header ─────────────────────────────────────────────────────────────
  const wpHeader = extractDiv(html, 'elementor-location-header');

  // ── WP Page Body (crawled sections) ───────────────────────────────────────
  // Extract the wp-page div, then:
  //  1. Strip the old Elementor hero (375eb214) — replaced by our custom hero
  //  2. Strip any csp-* custom sections that were baked into output2 by earlier runs
  let wpBody = extractDiv(html, 'data-elementor-type="wp-page"');
  wpBody = removeDivById(wpBody, '375eb214');   // remove old hero
  wpBody = stripCspSections(wpBody);            // remove any injected custom sections

  // ── WP Footer ─────────────────────────────────────────────────────────────
  const wpFooter = extractDiv(html, 'elementor-location-footer');

  // ── Save components ───────────────────────────────────────────────────────
  await mkdir(COMPONENTS, { recursive: true });
  await writeFile(join(COMPONENTS, 'wp-head.html'),   wpHead,   'utf8');
  await writeFile(join(COMPONENTS, 'wp-header.html'), wpHeader, 'utf8');
  await writeFile(join(COMPONENTS, 'wp-body.html'),   wpBody,   'utf8');
  await writeFile(join(COMPONENTS, 'wp-footer.html'), wpFooter, 'utf8');

  return { wpHead, wpHeader, wpBody, wpFooter };
}

// Run standalone
if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await extractComponents();
  console.log('✅ Components extracted to src/components/');
  console.log('   wp-head.html   — <head> block');
  console.log('   wp-header.html — Elementor sticky header');
  console.log('   wp-body.html   — crawled page sections (hero stripped)');
  console.log('   wp-footer.html — Elementor footer');
}
