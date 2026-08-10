import { readFile, writeFile, readdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { join, dirname } from 'node:path';

const __dir = dirname(fileURLToPath(import.meta.url));
const PAGES_DIR = join(__dir, '../pages');

function extractDiv(html, marker) {
  const idx = html.indexOf(marker);
  if (idx === -1) return null;

  let s = idx;
  while (s > 0 && html[s] !== '<') s--;

  let depth = 0, i = s;
  while (i < html.length) {
    if (html.slice(i, i + 4) === '<div') {
      depth++;
      i += 4;
    } else if (html.slice(i, i + 6) === '</div>') {
      depth--;
      if (depth === 0) return { start: s, end: i + 6, content: html.slice(s, i + 6) };
      i += 6;
    } else {
      i++;
    }
  }
  return null;
}

async function run() {
  const files = await readdir(PAGES_DIR);
  for (const file of files) {
    if (!file.endsWith('.html')) continue;
    const filePath = join(PAGES_DIR, file);
    let html = await readFile(filePath, 'utf8');
    let changed = false;

    // Replace header
    const headerBlock = extractDiv(html, 'data-elementor-type="header"');
    if (headerBlock) {
      html = html.slice(0, headerBlock.start) + '\n  <!-- INCLUDE:wp-header.html -->\n' + html.slice(headerBlock.end);
      changed = true;
    }

    // Replace footer
    const footerBlock = extractDiv(html, 'data-elementor-type="footer"');
    if (footerBlock) {
      html = html.slice(0, footerBlock.start) + '\n  <!-- INCLUDE:wp-footer.html -->\n' + html.slice(footerBlock.end);
      changed = true;
    }

    if (changed) {
      await writeFile(filePath, html, 'utf8');
      console.log(`✓ Replaced header/footer in src/pages/${file}`);
    }
  }
}

run();
