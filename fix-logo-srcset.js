/**
 * fix-logo-srcset.js
 *
 * The desktop logo image fails to load on Retina/HiDPI screens because
 * the srcset references 1536x473 and 2048x630 images that were never
 * downloaded during the crawl. The browser picks those larger sizes and
 * gets a 404. Fix: strip the srcset to only the sizes that exist locally.
 */

import { readFile, writeFile, readdir, stat } from 'node:fs/promises';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { existsSync } from 'node:fs';

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

// For all <img> tags, strip srcset entries that don't exist on disk.
// Also update sizes to match what's available.
function fixSrcset(html, fileDir) {
  return html.replace(
    /(<img\b[^>]*?)\bsrcset="([^"]+)"([^>]*?>)/gs,
    (match, before, srcsetVal, after) => {
      const entries = srcsetVal.split(',').map(e => e.trim()).filter(Boolean);
      const validEntries = entries.filter(entry => {
        const parts = entry.trim().split(/\s+/);
        const urlPart = parts[0];
        // Resolve relative path against output2 root
        const localPath = join(OUT, urlPart.replace(/^\.\//, ''));
        return existsSync(localPath);
      });

      if (validEntries.length === 0) {
        // No valid srcset entries — remove srcset entirely
        return `${before}${after}`;
      }
      if (validEntries.length === entries.length) {
        // All entries valid — leave unchanged
        return match;
      }
      // Return with only valid entries
      return `${before}srcset="${validEntries.join(', ')}"${after}`;
    }
  );
}

const files = await walk(OUT);
let updated = 0;
for (const f of files) {
  let html = await readFile(f, 'utf8');
  const fixed = fixSrcset(html, f);
  if (fixed !== html) {
    await writeFile(f, fixed, 'utf8');
    console.log(`✔ Fixed srcset: ${f.replace(OUT, 'output2/')}`);
    updated++;
  }
}
console.log(`\n✅ Done — fixed srcset in ${updated} file(s). Hard-refresh (Cmd+Shift+R).`);
