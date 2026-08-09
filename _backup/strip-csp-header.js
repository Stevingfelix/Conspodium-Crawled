/**
 * strip-csp-header.js
 * Removes the injected <!-- CSP_HEADER_BLOCK_START --> ... <!-- CSP_HEADER_BLOCK_END -->
 * and all associated <style> / <script> blocks from every HTML page,
 * and removes the `body { padding-top: 76px !important }` that was added to push
 * content down for the fake header.
 */

import { readFile, writeFile, readdir, stat } from 'node:fs/promises';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath(new URL('./output/', import.meta.url));

async function walk(dir) {
  const out = [];
  for (const name of await readdir(dir)) {
    const full = join(dir, name);
    if ((await stat(full)).isDirectory()) out.push(...await walk(full));
    else out.push(full);
  }
  return out;
}

const files = (await walk(ROOT)).filter(f => /\.html?$/i.test(f));
let changed = 0;

for (const file of files) {
  let html = await readFile(file, 'utf8');
  const before = html;

  // 1. Remove the entire CSP header block (header + drawer + script)
  html = html.replace(
    /<!-- CSP_HEADER_BLOCK_START -->[\s\S]*?<!-- CSP_HEADER_BLOCK_END -->/g,
    ''
  );

  // 2. Remove the body padding-top that was added for the fake header
  html = html.replace(/\s*body\s*\{\s*padding-top:\s*76px\s*!important;\s*\}/g, '');

  if (html !== before) {
    await writeFile(file, html, 'utf8');
    changed++;
  }
}

console.log(`✔ Stripped CSP header block from ${changed}/${files.length} HTML files.`);
console.log('The original Elementor header will now show on all pages.');
