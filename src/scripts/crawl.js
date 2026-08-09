/**
 * src/scripts/crawl.js
 * =====================================================================
 * Re-crawls the live Conspodium site and saves the raw HTML into output2/.
 * Run this when you need to pull fresh content from the live WordPress site.
 *
 * Usage:  npm run crawl
 *
 * After crawling, run:  npm run build
 * =====================================================================
 */

import { execFile } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { existsSync } from 'node:fs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '../../');

// The main crawl script to delegate to
const CRAWL_SCRIPT = join(ROOT, 'recrawl-all.js');

if (!existsSync(CRAWL_SCRIPT)) {
  console.error('❌ recrawl-all.js not found at root. Please restore from _backup/.');
  process.exit(1);
}

console.log('🌐 Starting crawl via recrawl-all.js...');
console.log('   This will pull fresh HTML from the live Conspodium site.\n');

const child = execFile('node', [CRAWL_SCRIPT], { cwd: ROOT });

child.stdout?.pipe(process.stdout);
child.stderr?.pipe(process.stderr);

child.on('close', (code) => {
  if (code === 0) {
    console.log('\n✅ Crawl complete.');
    console.log('   Now run: npm run build\n');
  } else {
    console.error(`\n❌ Crawl exited with code ${code}`);
  }
});
