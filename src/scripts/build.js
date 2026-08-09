/**
 * src/scripts/build.js
 * =====================================================================
 * Builds the Conspodium site from source files into output/.
 *
 * Usage:  npm run build
 *
 * What it does:
 *   1. Copies output2/ → output/ as the crawled base
 *   2. Extracts WP header, footer, head, and page body as components
 *   3. For standalone pages (base: null): reads src/pages/<id>.html,
 *      replaces <!-- COMPONENT: xxx --> markers with extracted components,
 *      applies header fixes, writes to output/
 *   4. For crawled-base pages: reads output/<base>, injects sections
 *      from src/pages/<id>.html at anchor points, applies header fixes,
 *      writes to output/
 * =====================================================================
 */

import { readFile, writeFile, readdir, cp, mkdir } from 'node:fs/promises';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { existsSync } from 'node:fs';
import { extractComponents } from './extract-components.js';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT      = join(__dirname, '../../');
const SRC       = join(ROOT, 'src');
const OUTPUT    = join(ROOT, 'output');
const BASE      = join(ROOT, 'output2');

// ── Page map ─────────────────────────────────────────────────────────────────
// Each entry:
//   id         → matches src/pages/<id>.html
//   base       → crawled source file in output2/ (null = standalone new page)
//   out        → destination in output/
//   sections   → array of { id, anchor } — inject section before Elementor element
//                anchor: null  = inject before </body>
//
// For the homepage, sections are injected at specific Elementor anchor IDs.
// For all other pages, sections are injected before </body> by default,
// placing them before the footer — or override with a specific anchor.
// ─────────────────────────────────────────────────────────────────────────────
const PAGES = [
  {
    id:   'home',
    base: 'index.html',
    out:  'index.html',
    sections: [
      { id: 'hero',      anchor: '375eb214' },
      { id: 'ticker',    anchor: '4d857de7' },
      { id: 'scholars',  anchor: '6c7c1b5d' },
      { id: 'poll',      anchor: '4f7bfe8e' },
      { id: 'countdown', anchor: '4f7bfe8e' },
      { id: 'tabs',      anchor: '4f7bfe8e' },
      { id: 'quote',     anchor: '4f7bfe8e' },
      { id: 'trending',  anchor: '4f7bfe8e' },
      { id: 'stats',     anchor: '27d63d2d' },
    ],
  },
  {
    id:   'about',
    base: 'about-us/index.html',
    out:  'about-us/index.html',
    sections: [
      { id: 'about-cta', anchor: null }, // inject before </body>
    ],
  },
  {
    id:   'stories',
    base: 'stories/index.html',
    out:  'stories/index.html',
    sections: [
      { id: 'stories-hero', anchor: null }, // inject before </body>
    ],
  },
  {
    id:   'contact',
    base: 'contact-us/index.html',
    out:  'contact-us/index.html',
    sections: [
      { id: 'contact-banner', anchor: null },
    ],
  },
  {
    id:   'sponsorship',
    base: 'sponsorship/index.html',
    out:  'sponsorship/index.html',
    sections: [
      { id: 'sponsorship-banner', anchor: null },
    ],
  },
  {
    id:   'advert',
    base: 'advert/index.html',
    out:  'advert/index.html',
    sections: [
      { id: 'advert-banner', anchor: null },
    ],
  },
  {
    id:   'submit-story',
    base: 'submit-story/index.html',
    out:  'submit-story/index.html',
    sections: [
      { id: 'submit-banner', anchor: null },
    ],
  },
  {
    // Dashboard: standalone — no crawled base, built from src/pages/dashboard.html directly
    id:       'dashboard',
    base:     null,
    out:      'dashboard/index.html',
    sections: [],
  },
];

// ── All known CSP marker names to strip before re-injecting ─────────────────
const STRIP_MARKERS = [
  'CSP_HERO',
  'CSP_SECTION_TICKER',
  'CSP_SECTION_SCHOLARS',
  'CSP_SECTION_TRENDING',
  'CSP_SECTION_QUOTE',
  'CSP_SECTION_TABS',
  'CSP_SECTION_COUNTDOWN',
  'CSP_SECTION_POLL',
  'CSP_SECTION_STATS',
];

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Parse <!-- SECTION: name --> ... <!-- /SECTION: name --> blocks from HTML */
function parseSections(html) {
  const sections = {};
  const re = /<!--\s*SECTION:\s*([\w-]+)\s*-->([\s\S]*?)<!--\s*\/SECTION:\s*\1\s*-->/g;
  let m;
  while ((m = re.exec(html)) !== null) {
    sections[m[1].toLowerCase()] = m[2].trim();
  }
  return sections;
}

/** Strip a CSP marker block (START/END) from HTML */
function stripMarker(html, name) {
  const start = `<!-- ${name}_START -->`;
  const end   = `<!-- ${name}_END -->`;
  let si = html.indexOf(start);
  let ei = html.indexOf(end);
  while (si !== -1 && ei !== -1) {
    html = html.slice(0, si) + html.slice(ei + end.length);
    si = html.indexOf(start);
    ei = html.indexOf(end);
  }
  return html;
}

/** Inject HTML block immediately before the opening tag of an Elementor element */
function injectBefore(html, anchorId, block) {
  const marker = `elementor-element-${anchorId}`;
  const idx = html.indexOf(marker);
  if (idx === -1) {
    console.warn(`    ⚠  Anchor not found: ${anchorId} — injecting before </body>`);
    return html.replace('</body>', block + '\n</body>');
  }
  let start = idx;
  while (start > 0 && html[start] !== '<') start--;
  return html.slice(0, start) + block + '\n' + html.slice(start);
}

/** Inject HTML block before </body> */
function injectBeforeBody(html, block) {
  if (html.includes('</body>')) {
    return html.replace('</body>', block + '\n</body>');
  }
  return html + '\n' + block;
}

/** Recursively copy a directory */
async function copyDir(src, dest) {
  await mkdir(dest, { recursive: true });
  const entries = await readdir(src, { withFileTypes: true });
  for (const entry of entries) {
    const s = join(src, entry.name);
    const d = join(dest, entry.name);
    if (entry.isDirectory()) {
      await copyDir(s, d);
    } else {
      await cp(s, d);
    }
  }
}

// ── Main build ───────────────────────────────────────────────────────────────

console.log('\n🔨 Conspodium Build\n');

// Step 1 — Copy base crawled site into output/
process.stdout.write('  1. Copying base site (output2 → output)... ');
await copyDir(BASE, OUTPUT);
console.log('✓');

// Step 2 — Extract WP components from output2/index.html
process.stdout.write('  2. Extracting WP components (header / footer / body)... ');
const { wpHead, wpHeader, wpBody, wpFooter } = await extractComponents();
const components = { wpHead, wpHeader, wpBody, wpFooter };
console.log('✓');

// Step 3 — Load shared CSS fix component
const headerFixesHtml = await readFile(join(SRC, 'components/header-fixes.html'), 'utf8');

// Step 4 — Build each page
console.log(`  3. Building ${PAGES.length} pages:\n`);

for (const page of PAGES) {
  const srcFile  = join(SRC, 'pages', `${page.id}.html`);
  const outFile  = join(OUTPUT, page.out);

  // Ensure source page file exists
  if (!existsSync(srcFile)) {
    console.warn(`     ⚠  src/pages/${page.id}.html not found — skipping`);
    continue;
  }

  const pageSrc = await readFile(srcFile, 'utf8');

  // ── Standalone pages (no crawled base) ──────────────────────────────────
  if (page.base === null) {
    let html = pageSrc;

    // Inject WP components into <!-- COMPONENT: xxx --> markers
    if (html.includes('<!-- COMPONENT:')) {
      html = html.replace('<!-- COMPONENT: wp-head -->', components.wpHead);
      html = html.replace('<!-- COMPONENT: wp-header -->', components.wpHeader);
      html = html.replace('<!-- COMPONENT: wp-body -->', components.wpBody);
      html = html.replace('<!-- COMPONENT: wp-footer -->', components.wpFooter);
    }

    // Strip any stale csp-* styles baked into output2, then inject fresh fixes
    html = html.replace(/<style[^>]*id="csp-[^"]*"[^>]*>[\s\S]*?<\/style>/g, '');
    html = html.replace(/<style>\/\* conspodium-fix-preloader \*\/[\s\S]*?<\/style>/g, '');
    html = html.replace(/<script>\/\* Reset header[\s\S]*?<\/script>/g, '');
    html = html.replace('</head>', headerFixesHtml + '\n</head>');

    await mkdir(dirname(outFile), { recursive: true });
    await writeFile(outFile, html, 'utf8');
    console.log(`     ✓  ${page.id.padEnd(16)} → output/${page.out}`);
    continue;
  }

  // ── Pages with a crawled base ────────────────────────────────────────────
  const baseFile = join(OUTPUT, page.out);
  if (!existsSync(baseFile)) {
    console.warn(`     ⚠  Base not found: output/${page.out} — skipping`);
    continue;
  }

  let html = await readFile(baseFile, 'utf8');

  // Strip old CSP marker blocks
  for (const name of STRIP_MARKERS) html = stripMarker(html, name);

  // Remove old inline header fix blocks (will re-inject cleanly)
  html = html.replace(/<style id="csp-header-fixes">[\s\S]*?<\/style>/g, '');
  html = html.replace(/<style id="csp-hide-old-hero">[\s\S]*?<\/style>/g, '');

  // Strip any stale csp-* styles baked into output2, then inject fresh fixes
  html = html.replace(/<style[^>]*id="csp-[^"]*"[^>]*>[\s\S]*?<\/style>/g, '');
  html = html.replace(/<style>\/\* conspodium-fix-preloader \*\/[\s\S]*?<\/style>/g, '');
  html = html.replace(/<script>\/\* Reset header[\s\S]*?<\/script>/g, '');
  // Inject header fixes into <head>
  html = html.replace('</head>', headerFixesHtml + '\n</head>');

  // Parse and inject custom sections
  if (page.sections.length > 0) {
    const sections = parseSections(pageSrc);
    for (const { id, anchor } of page.sections) {
      const block = sections[id];
      if (!block) {
        console.warn(`     ⚠  Section "${id}" not found in src/pages/${page.id}.html`);
        continue;
      }
      html = anchor
        ? injectBefore(html, anchor, block)
        : injectBeforeBody(html, block);
    }
  }

  await writeFile(baseFile, html, 'utf8');
  console.log(`     ✓  ${page.id.padEnd(16)} → output/${page.out}`);
}

// Step 5 — Apply header fixes to all remaining pages (not in PAGES map)
console.log('\n  4. Applying header fixes to all other pages...');
const handledOuts = new Set(PAGES.filter(p => p.base).map(p => p.out));

async function fixRemainingHeaders(dir) {
  const entries = await readdir(dir, { withFileTypes: true });
  for (const entry of entries) {
    const full = join(dir, entry.name);
    if (entry.isDirectory()) {
      await fixRemainingHeaders(full);
    } else if (entry.name.endsWith('.html')) {
      const rel = full.replace(OUTPUT + '/', '');
      if (handledOuts.has(rel)) continue; // already handled above
      let html = await readFile(full, 'utf8');
      if (!html.includes('id="csp-header-fixes"')) {
        html = html.replace('</head>', headerFixesHtml + '\n</head>');
        await writeFile(full, html, 'utf8');
      }
    }
  }
}
await fixRemainingHeaders(OUTPUT);
console.log('     ✓  Done\n');

console.log('✅ Build complete → output/');
console.log('   Run: npm run serve  →  http://localhost:8080\n');
