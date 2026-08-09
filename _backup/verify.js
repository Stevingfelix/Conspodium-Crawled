import { chromium } from 'playwright';

const OUT = new URL('./output/', import.meta.url).pathname;
const browser = await chromium.launch();

async function check(localFile, label, liveUrl) {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const failed = [];
  page.on('requestfailed', (r) => failed.push(r.url()));
  page.on('response', (r) => { if (r.status() >= 400) failed.push(`${r.status()} ${r.url()}`); });
  await page.goto('file://' + OUT + localFile, { waitUntil: 'load', timeout: 30000 }).catch(()=>{});
  await page.waitForTimeout(2000);
  await page.screenshot({ path: OUT + `../_verify_${label}_local.png`, fullPage: false });
  const localFails = failed.filter((u) => !u.startsWith('blob:') && !u.includes('google') && !u.includes('gstatic') && !u.includes('facebook') && !u.includes('analytics'));
  console.log(`[${label}] local broken refs (excl. 3rd-party/blob): ${localFails.length}`);
  localFails.slice(0, 12).forEach((u) => console.log('   x', u.slice(0, 100)));
  if (liveUrl) {
    const lp = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await lp.goto(liveUrl, { waitUntil: 'commit', timeout: 60000 }).catch(()=>{});
    await lp.waitForTimeout(4000);
    await lp.screenshot({ path: OUT + `../_verify_${label}_live.png`, fullPage: false }).catch(()=>{});
    await lp.close();
  }
  await page.close();
}

await check('index.html', 'home', 'https://conspodium.com/');
await check('about-us/index.html', 'about', null);
await check('we-are-the-world/index.html', 'post', null);

await browser.close();
console.log('Screenshots written to project root (_verify_*.png)');
