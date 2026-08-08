import { readFile, writeFile, readdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';

const OUTPUT_DIR = fileURLToPath(new URL('./output', import.meta.url));

async function getHtmlFiles(dir) {
  const entries = await readdir(dir, { withFileTypes: true });
  let files = [];
  for (const entry of entries) {
    const fullPath = join(dir, entry.name);
    if (entry.isDirectory()) {
      files = files.concat(await getHtmlFiles(fullPath));
    } else if (entry.isFile() && entry.name.endsWith('.html')) {
      files.push(fullPath);
    }
  }
  return files;
}

async function removePreloaders() {
  const htmlFiles = await getHtmlFiles(OUTPUT_DIR);
  let cleanedCount = 0;

  for (const file of htmlFiles) {
    let html = await readFile(file, 'utf8');

    if (html.includes('sl-pl-canvas') || html.includes('sl-pl-spin-container') || html.includes('sl-pl-close-button')) {
      // Remove sl-pl-canvas and all preloader elements
      html = html.replace(/<div id="sl-pl-canvas">[\s\S]*?<div class="sl-pl-spin-container">[\s\S]*?<\/div><\/div>/gi, '');
      html = html.replace(/<div id="sl-pl-close-button">[\s\S]*?<\/div>/gi, '');
      html = html.replace(/<div class="sl-pl-spin-container">[\s\S]*?<\/div>/gi, '');
      
      await writeFile(file, html, 'utf8');
      cleanedCount++;
    }
  }

  console.log(`Cleaned preloader markup from ${cleanedCount} HTML files!`);
}

removePreloaders().catch(console.error);
