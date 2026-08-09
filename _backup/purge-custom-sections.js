import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const INDEX_PATH = fileURLToPath(new URL('./output/index.html', import.meta.url));

async function purge() {
  let html = await readFile(INDEX_PATH, 'utf8');

  // Strip all style tags with id starting with csp-
  html = html.replace(/<style id="csp-[^"]*">[\s\S]*?<\/style>/gi, '');

  // Strip any html block starting from <!-- 1. Live News Ticker --> down to the end of the script block
  while (html.includes('<!-- 1. Live News Ticker -->')) {
    const start = html.indexOf('<!-- 1. Live News Ticker -->');
    const end = html.indexOf('</script>', start);
    if (end !== -1) {
      html = html.slice(0, start) + html.slice(end + 9);
    } else {
      // Fallback: strip ticker section alone
      const tickerEnd = html.indexOf('</div>', start);
      if (tickerEnd !== -1) {
        html = html.slice(0, start) + html.slice(tickerEnd + 6);
      } else {
        break;
      }
    }
  }

  // Strip any remaining csp script tags
  html = html.replace(/<script id="csp-[^"]*">[\s\S]*?<\/script>/gi, '');

  await writeFile(INDEX_PATH, html, 'utf8');
  console.log('Purge completed. Check results:');
  console.log('Includes csp-ticker-bar:', html.includes('csp-ticker-bar'));
  console.log('Includes csp-full-structure-styles:', html.includes('csp-full-structure-styles'));
}

purge().catch(console.error);
