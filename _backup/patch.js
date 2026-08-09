/**
 * patch.js — Two direct fixes:
 *  1. Increase "Submit Story" button padding on index.html
 *  2. Remap about-us CSS/JS hrefs to files that actually exist locally
 */
import { readFile, writeFile } from 'node:fs/promises';
import { join, dirname, basename } from 'node:path';
import { fileURLToPath } from 'node:url';
import { existsSync, readdirSync } from 'node:fs';

const ROOT = fileURLToPath(new URL('./output/', import.meta.url));

// ─── FIX 1: Button padding on index.html ────────────────────────────────────
let idx = await readFile(join(ROOT, 'index.html'), 'utf8');
// Replace padding in the .csp-submit-btn block — increase to 14px top/bottom, 36px left/right
idx = idx.replace(
  /\.csp-submit-btn\s*\{([^}]*?)padding:\s*[\d.]+px\s+[\d.]+px/,
  (m, before) => `.csp-submit-btn {${before}padding: 14px 36px`
);
await writeFile(join(ROOT, 'index.html'), idx, 'utf8');
console.log('✔ FIX 1: Button padding updated in index.html → 14px 36px');

// ─── FIX 2: About-us CSS/JS remapped to local files ─────────────────────────
// Build a lookup: "filename-without-ver" → actual local path
// e.g. "frontend.min" → "wp-content/plugins/elementor/assets/css/frontend.min._ver_4_1_1.css"

function buildLocalLookup(dir, map = {}, prefix = '') {
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, entry.name);
    const rel  = prefix ? `${prefix}/${entry.name}` : entry.name;
    if (entry.isDirectory()) {
      buildLocalLookup(full, map, rel);
    } else if (/\.(css|js)$/.test(entry.name)) {
      // Strip the ._ver_... suffix to get the base name
      const base = entry.name.replace(/\._ver_[\w.]+\.(css|js)$/, '');
      const ext  = entry.name.match(/\.(css|js)$/)[1];
      const key  = `${dirname(rel)}/${base}.${ext}`; // e.g. wp-content/.../frontend.min.css
      // Keep first match (oldest/only version available)
      if (!map[key]) map[key] = rel;
    }
  }
  return map;
}

const localLookup = buildLocalLookup(join(ROOT, 'wp-content'), {}, 'wp-content');

let about = await readFile(join(ROOT, 'about-us/index.html'), 'utf8');

// Rewrite hrefs/srcs: "../wp-content/path/file._ver_X_Y_Z.css" 
// AND any remaining "?ver=X.Y.Z" style (belt-and-suspenders)
let fixed = 0;

// Handle already-converted ._ver_ paths that don't match a local file
about = about.replace(
  /((?:href|src)=["'])(\.\.\/)(wp-content\/[^"'?]+?)(\._ver_[\w.]+)?\.(css|js)(["'])/g,
  (match, attr, prefix, pathBase, verSuffix, ext, quote) => {
    const keyNoVer = `${pathBase}.${ext}`;
    if (localLookup[keyNoVer]) {
      const localRel = localLookup[keyNoVer];
      fixed++;
      return `${attr}../${localRel}${quote}`;
    }
    return match; // leave alone if no local equivalent
  }
);

// Handle raw ?ver=X.Y.Z that weren't converted yet
about = about.replace(
  /((?:href|src)=["'])(\.\.\/)(wp-content\/[^"'?]+?)\.(css|js)\?ver=[\d.]+?(["'])/g,
  (match, attr, prefix, pathBase, ext, quote) => {
    const keyNoVer = `${pathBase}.${ext}`;
    if (localLookup[keyNoVer]) {
      const localRel = localLookup[keyNoVer];
      fixed++;
      return `${attr}../${localRel}${quote}`;
    }
    // fallback: just strip the query string so it at least tries the base filename
    fixed++;
    return `${attr}../${pathBase}.${ext}${quote}`;
  }
);

// Also fix the button padding in the injected CSP block inside about-us
about = about.replace(
  /\.csp-submit-btn\s*\{([^}]*?)padding:\s*[\d.]+px\s+[\d.]+px/,
  (m, before) => `.csp-submit-btn {${before}padding: 14px 36px`
);

await writeFile(join(ROOT, 'about-us/index.html'), about, 'utf8');
console.log(`✔ FIX 2: Remapped ${fixed} CSS/JS links in about-us/index.html to local files`);
console.log('✔ FIX 2: Button padding also updated in about-us/index.html');
console.log('\nHard refresh (Cmd+Shift+R) both pages to see changes.');
